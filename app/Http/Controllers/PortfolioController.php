<?php

namespace App\Http\Controllers;

use App\Models\FieldDefinition;
use App\Models\Portfolio;
use App\Models\PortfolioFieldValue;
use App\Models\PortfolioSection;
use App\Models\Template;
use App\Services\GoogleFormIntegration;
use App\Services\PortfolioService;
use App\Services\TemplateDemoData;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class PortfolioController extends Controller
{
    private const PENDING_UPLOAD_PREFIX = 'quickfolio-pending-upload:';

    use AuthorizesRequests;

    protected PortfolioService $portfolioService;

    public function __construct(PortfolioService $portfolioService)
    {
        $this->portfolioService = $portfolioService;
    }

    public function index(Request $request)
    {
        $portfolios = $request->user()->portfolios()->with(['template.category'])->latest()->get();

        return Inertia::render('Dashboard', [
            'portfolios' => $portfolios,
        ]);
    }

    public function store(Request $request, Template $template, TemplateDemoData $demoData)
    {
        if (! $template->is_published) {
            abort(403, 'Template is not published.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $templateDemo = $demoData->forTemplate($template);
        $portfolio = $this->portfolioService->createForUser(
            $request->user(),
            $template,
            $validated['title'],
            $templateDemo['content']
        );

        return redirect()->route('portfolios.edit', $portfolio);
    }

    public function edit(Request $request, Portfolio $portfolio)
    {
        if ($request->boolean('live_preview')) {
            $this->authorize('view', $portfolio);

            $portfolio->load([
                'template.category',
                'template.sections.fields.fieldDefinition',
                'sections.fieldValues.fieldDefinition',
                'user:id,name,email',
            ]);

            return Inertia::render('Portfolios/PublicShow', [
                'portfolio' => $portfolio,
                'livePreview' => true,
            ]);
        }

        $this->authorize('update', $portfolio);

        $portfolio->load([
            'template.category',
            'template.media',
            'template.sections.fields.fieldDefinition',
            'sections.fieldValues.fieldDefinition',
        ]);

        $availableTemplates = Template::where('is_published', true)->with('category')->get();
        $fieldDefinitions = FieldDefinition::all();

        return Inertia::render('Portfolios/Edit', [
            'portfolio' => $portfolio,
            'availableTemplates' => $availableTemplates,
            'fieldDefinitions' => $fieldDefinitions,
        ]);
    }

    public function update(Request $request, Portfolio $portfolio)
    {
        $this->authorize('update', $portfolio);

        $previousUploadPaths = $this->portfolioUploadPaths($portfolio);

        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', 'exists:portfolio_sections,id'],
            'sections.*.is_enabled' => ['required', 'boolean'],
            'sections.*.field_values' => ['nullable', 'array'],
            'sections.*.field_values.*.field_definition_id' => ['required', 'exists:field_definitions,id'],
            'sections.*.field_values.*.value' => ['nullable'],
            'pending_uploads' => ['sometimes', 'array'],
            'pending_uploads.*' => ['required', 'file', 'max:10240'],
            'pending_upload_types' => ['sometimes', 'array'],
            'pending_upload_types.*' => ['required', 'in:image,file'],
            'google_form_url' => [
                'nullable',
                'url',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && $value !== '' && (! is_string($value) || ! GoogleFormIntegration::isValidUrl($value))) {
                        $fail('Use the full HTTPS Google Forms URL ending in /viewform.');
                    }
                },
            ],
            'google_form_name_entry' => ['nullable', 'required_with:google_form_url', 'regex:/^\d{1,20}$/'],
            'google_form_email_entry' => ['nullable', 'required_with:google_form_url', 'regex:/^\d{1,20}$/'],
            'google_form_message_entry' => ['nullable', 'required_with:google_form_url', 'regex:/^\d{1,20}$/'],
        ]);

        if (
            ! $request->filled('google_form_url')
            && collect(['google_form_name_entry', 'google_form_email_entry', 'google_form_message_entry'])
                ->contains(fn (string $key) => $request->filled($key))
        ) {
            throw ValidationException::withMessages([
                'google_form_url' => 'A Google Forms URL is required when question IDs are provided.',
            ]);
        }

        $entryIds = [
            $validated['google_form_name_entry'] ?? null,
            $validated['google_form_email_entry'] ?? null,
            $validated['google_form_message_entry'] ?? null,
        ];
        $configuredEntryIds = array_values(array_filter($entryIds, fn (?string $entryId) => $entryId !== null));
        if (count(array_unique($configuredEntryIds)) !== count($configuredEntryIds)) {
            throw ValidationException::withMessages([
                'google_form_message_entry' => 'Use a different Google Forms question ID for each field.',
            ]);
        }

        $pendingUploads = $request->file('pending_uploads', []);
        $pendingUploadTypes = $request->input('pending_upload_types', []);
        if (! is_array($pendingUploads) || ! is_array($pendingUploadTypes)
            || array_diff(array_keys($pendingUploads), array_keys($pendingUploadTypes)) !== []
            || array_diff(array_keys($pendingUploadTypes), array_keys($pendingUploads)) !== []) {
            throw ValidationException::withMessages([
                'pending_uploads' => 'The selected uploads could not be matched. Please select them again.',
            ]);
        }

        foreach ($pendingUploads as $uploadId => $file) {
            if (! is_string($uploadId) || preg_match('/^[a-f0-9]{32}$/', $uploadId) !== 1) {
                throw ValidationException::withMessages([
                    'pending_uploads' => 'The selected uploads are invalid. Please select them again.',
                ]);
            }

            $type = $pendingUploadTypes[$uploadId];
            $mimes = $type === 'image'
                ? 'mimes:jpg,jpeg,png,gif,webp'
                : 'mimes:pdf,doc,docx,txt,zip';

            Validator::make(
                ['file' => $file],
                ['file' => ['required', 'file', $mimes, 'max:10240']]
            )->validate();
        }

        if ($pendingUploads !== [] && (! is_string(config('filesystems.disks.r2.url')) || trim(config('filesystems.disks.r2.url')) === '')) {
            throw new \RuntimeException('R2_URL must be configured before uploading files.');
        }

        $storedUploadPaths = [];
        try {
            $storedUploadUrls = [];
            $directory = "users/{$portfolio->user_id}/templates/{$portfolio->template_id}/portfolios/{$portfolio->id}";
            foreach ($pendingUploads as $uploadId => $file) {
                $path = $file->store($directory, 'r2');
                if ($path === false) {
                    throw new \RuntimeException('Unable to store an uploaded portfolio file.');
                }

                $storedUploadPaths[] = $path;
                $storedUploadUrls[$uploadId] = Storage::disk('r2')->url($path);
            }

            $usedUploads = [];
            foreach ($validated['sections'] as &$sectionData) {
                if (! isset($sectionData['field_values'])) {
                    continue;
                }

                foreach ($sectionData['field_values'] as &$fieldValueData) {
                    $fieldValueData['value'] = $this->replacePendingUploadReferences(
                        $fieldValueData['value'] ?? null,
                        $storedUploadUrls,
                        $usedUploads
                    );
                }
                unset($fieldValueData);
            }
            unset($sectionData);

            if (array_diff_key($storedUploadUrls, $usedUploads) !== []) {
                throw ValidationException::withMessages([
                    'pending_uploads' => 'A selected upload is no longer attached to a portfolio field.',
                ]);
            }

            DB::transaction(function () use ($portfolio, $validated) {
                $portfolio->update([
                    'google_form_url' => GoogleFormIntegration::formUrl($validated['google_form_url'] ?? null),
                    'google_form_name_entry' => $validated['google_form_name_entry'] ?? null,
                    'google_form_email_entry' => $validated['google_form_email_entry'] ?? null,
                    'google_form_message_entry' => $validated['google_form_message_entry'] ?? null,
                ]);

                foreach ($validated['sections'] as $secData) {
                    $section = PortfolioSection::where('portfolio_id', $portfolio->id)
                        ->where('id', $secData['id'])
                        ->first();

                    if (! $section) {
                        continue;
                    }

                    $section->update([
                        'is_enabled' => $secData['is_enabled'],
                    ]);

                    if (isset($secData['field_values'])) {
                        foreach ($secData['field_values'] as $fvData) {
                            $fieldDefId = $fvData['field_definition_id'];
                            $fieldDef = FieldDefinition::find($fieldDefId);
                            $value = $fvData['value'] ?? null;

                            if ($fieldDef) {
                                if ($fieldDef->data_type === 'email' && ! empty($value)) {
                                    validator(['email' => $value], ['email' => 'email'])->validate();
                                } elseif ($fieldDef->data_type === 'url' && ! empty($value)) {
                                    validator(['url' => $value], ['url' => 'url'])->validate();
                                } elseif ($fieldDef->data_type === 'string' && $value !== null && $value !== '') {
                                    validator(['value' => $value], ['value' => 'string'])->validate();
                                }
                            }

                            PortfolioFieldValue::updateOrCreate(
                                [
                                    'portfolio_section_id' => $section->id,
                                    'field_definition_id' => $fieldDefId,
                                ],
                                [
                                    'value' => $value,
                                ]
                            );
                        }
                    }
                }
            });
        } catch (Throwable $exception) {
            foreach ($storedUploadPaths as $path) {
                try {
                    if (! Storage::disk('r2')->delete($path)) {
                        report(new \RuntimeException("Could not remove failed portfolio upload: {$path}"));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        $currentUploadPaths = $this->portfolioUploadPaths($portfolio);
        $unusedUploadPaths = array_diff_key($previousUploadPaths, $currentUploadPaths);
        foreach ($unusedUploadPaths as $upload) {
            if (! Storage::disk($upload['disk'])->delete($upload['path'])) {
                throw new \RuntimeException("Could not remove replaced portfolio upload: {$upload['path']}");
            }
        }

        return redirect()->back()->with('success', 'Portfolio updated successfully.');
    }

    private function replacePendingUploadReferences(mixed $value, array $uploadUrls, array &$usedUploads): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $nestedValue) {
                $value[$key] = $this->replacePendingUploadReferences($nestedValue, $uploadUrls, $usedUploads);
            }

            return $value;
        }

        if (! is_string($value) || ! str_starts_with($value, self::PENDING_UPLOAD_PREFIX)) {
            return $value;
        }

        $uploadId = substr($value, strlen(self::PENDING_UPLOAD_PREFIX));
        if (preg_match('/^[a-f0-9]{32}$/', $uploadId) !== 1 || ! array_key_exists($uploadId, $uploadUrls)) {
            throw ValidationException::withMessages([
                'pending_uploads' => 'A selected upload could not be matched. Please select it again.',
            ]);
        }

        $usedUploads[$uploadId] = true;

        return $uploadUrls[$uploadId];
    }

    private function portfolioUploadPaths(Portfolio $portfolio): array
    {
        $directory = "users/{$portfolio->user_id}/templates/{$portfolio->template_id}/portfolios/{$portfolio->id}/";
        $paths = [];

        foreach ($portfolio->sections()->with('fieldValues')->get() as $section) {
            foreach ($section->fieldValues as $fieldValue) {
                $this->collectPortfolioUploadPaths($fieldValue->value, $directory, $paths);
            }
        }

        return $paths;
    }

    private function collectPortfolioUploadPaths(mixed $value, string $directory, array &$paths): void
    {
        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                $this->collectPortfolioUploadPaths($nestedValue, $directory, $paths);
            }

            return;
        }

        if (! is_string($value) || $value === '') {
            return;
        }

        $urlParts = parse_url($value);
        $urlPath = $urlParts['path'] ?? null;
        if (! is_string($urlPath)) {
            return;
        }

        $disk = 'public';
        if (isset($urlParts['host'])) {
            $r2Url = parse_url((string) config('filesystems.disks.r2.url'));
            if (isset($r2Url['host']) && strcasecmp($urlParts['host'], $r2Url['host']) === 0) {
                $disk = 'r2';
                $urlPath = ltrim($urlPath, '/');
                $basePath = trim((string) ($r2Url['path'] ?? ''), '/');
                if ($basePath !== '' && str_starts_with($urlPath, $basePath.'/')) {
                    $urlPath = substr($urlPath, strlen($basePath) + 1);
                }
            } elseif (str_starts_with($urlPath, '/storage/')) {
                $urlPath = substr($urlPath, strlen('/storage/'));
            } else {
                return;
            }
        } elseif (str_starts_with($urlPath, '/storage/')) {
            $urlPath = substr($urlPath, strlen('/storage/'));
        } elseif (str_starts_with($urlPath, 'storage/')) {
            $urlPath = substr($urlPath, strlen('storage/'));
        } else {
            return;
        }

        $path = rawurldecode($urlPath);
        if (str_starts_with($path, $directory)) {
            $paths[$disk.':'.$path] = ['disk' => $disk, 'path' => $path];
        }
    }

    public function destroy(Request $request, Portfolio $portfolio)
    {
        $this->authorize('delete', $portfolio);

        $portfolio->delete();

        return redirect()->route('dashboard')->with('success', 'Portfolio deleted successfully.');
    }

    public function switchTemplate(Request $request, Portfolio $portfolio)
    {
        if ($request->user()->id !== $portfolio->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'template_id' => ['required', 'exists:templates,id'],
        ]);

        $newTemplate = Template::findOrFail($validated['template_id']);
        if (! $newTemplate->is_published) {
            abort(403, 'Selected template is not published.');
        }

        $this->portfolioService->switchTemplate($portfolio, $newTemplate);

        return redirect()->route('portfolios.edit', $portfolio)->with('success', 'Template switched successfully.');
    }

    public function publish(Request $request, Portfolio $portfolio)
    {
        if ($request->user()->id !== $portfolio->user_id) {
            abort(403);
        }

        // If transitioning from draft to published, enforce required fields check
        if ($portfolio->status !== 'published') {
            $portfolio->load('template.sections.fields.fieldDefinition', 'sections.fieldValues');

            $missingFields = [];
            foreach ($portfolio->template->sections as $tSec) {
                $pSec = $portfolio->sections->firstWhere('section_key', $tSec->section_key);
                if (! $pSec || ! $pSec->is_enabled) {
                    continue; // Disabled sections do not block publishing
                }

                foreach ($tSec->fields as $tField) {
                    if ($tField->is_required) {
                        $fv = $pSec->fieldValues->firstWhere('field_definition_id', $tField->field_definition_id);
                        $val = $fv?->value;
                        if (empty($val) || (is_array($val) && count($val) === 0)) {
                            $missingFields[] = $tField->fieldDefinition->label;
                        }
                    }
                }
            }

            if (! empty($missingFields)) {
                return redirect()->back()->withErrors([
                    'publish' => 'Cannot publish portfolio. Missing required fields: '.implode(', ', $missingFields),
                ]);
            }
        }

        $portfolio->update([
            'status' => $portfolio->status === 'published' ? 'draft' : 'published',
        ]);

        return redirect()->back()->with('success', 'Portfolio publication status updated.');
    }

    public function showPublished(Portfolio $portfolio)
    {
        if ($portfolio->status !== 'published') {
            abort(404);
        }

        $portfolio->load([
            'template.category',
            'template.sections.fields.fieldDefinition',
            'sections.fieldValues.fieldDefinition',
            'user',
        ]);

        return Inertia::render('Portfolios/PublicShow', [
            'portfolio' => $portfolio,
        ]);
    }
}
