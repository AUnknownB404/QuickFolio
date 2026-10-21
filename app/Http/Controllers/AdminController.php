<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->withCount('portfolios')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(10, ['id', 'name', 'email', 'role', 'created_at'])
            ->withQueryString();

        $templates = Template::query()
            ->with('category')
            ->withCount('portfolios')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(9, ['*'], 'templates_page')
            ->withQueryString();

        return Inertia::render('Admin/Index', [
            'stats' => [
                'users' => User::count(),
                'templates' => Template::count(),
                'publishedTemplates' => Template::where('is_published', true)->count(),
                'portfolios' => Portfolio::count(),
            ],
            'users' => $users,
            'templates' => $templates,
            'categories' => TemplateCategory::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search],
        ]);
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['user', 'admin'])],
        ]);

        if ($user->is($request->user()) && $validated['role'] !== 'admin') {
            return back()->with('error', 'You cannot remove your own admin access.');
        }

        if ($user->isAdmin() && $validated['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'The last administrator cannot be demoted.');
        }

        $user->role = $validated['role'];
        $user->save();

        return back()->with('success', 'User role updated.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account from the admin panel.');
        }

        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'The last administrator cannot be deleted.');
        }

        $user->delete();

        return back()->with('success', 'User and their portfolios were deleted.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name', '')) ?: Str::lower(Str::random(8))]);
        $validated = $request->validate($this->templateRules());
        $thumbnail = $validated['thumbnail'] ?? null;
        unset($validated['thumbnail']);

        $thumbnailPath = $thumbnail?->store('templates', 'public');
        if ($thumbnail && ! is_string($thumbnailPath)) {
            throw new RuntimeException('The template thumbnail could not be stored.');
        }

        $validated['thumbnail'] = $thumbnailPath
            ? '/storage/'.$thumbnailPath
            : null;

        Template::create($validated);

        return back()->with('success', 'Template created.');
    }

    public function updateTemplate(Request $request, Template $template): RedirectResponse
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name', '')) ?: Str::lower(Str::random(8))]);
        $validated = $request->validate($this->templateRules($template));
        $thumbnail = $validated['thumbnail'] ?? null;
        unset($validated['thumbnail']);

        $thumbnailPath = $thumbnail?->store('templates', 'public');
        if ($thumbnail && ! is_string($thumbnailPath)) {
            throw new RuntimeException('The template thumbnail could not be stored.');
        }

        if ($thumbnail) {
            $previousThumbnail = $template->thumbnail;
            $validated['thumbnail'] = '/storage/'.$thumbnailPath;
        } else {
            $previousThumbnail = null;
        }

        $template->update($validated);

        if ($previousThumbnail) {
            $this->deleteThumbnail($previousThumbnail);
        }

        return back()->with('success', 'Template updated.');
    }

    public function destroyTemplate(Template $template): RedirectResponse
    {
        if ($template->portfolios()->exists()) {
            return back()->with('error', 'This template is in use by portfolios and cannot be deleted.');
        }

        $this->deleteThumbnail($template->thumbnail);
        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    private function templateRules(?Template $template = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('templates', 'slug')->ignore($template?->id),
            ],
            'category_id' => ['nullable', 'integer', 'exists:template_categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'preview_url' => ['nullable', 'string', 'max:2048'],
            'is_published' => ['required', 'boolean'],
            'thumbnail' => ['nullable', 'image', 'max:5120'],
        ];
    }

    private function deleteThumbnail(?string $thumbnail): void
    {
        if (! $thumbnail) {
            return;
        }

        if (Str::startsWith($thumbnail, ['http://', 'https://'])) {
            $thumbnailHost = parse_url($thumbnail, PHP_URL_HOST);
            $applicationHost = parse_url(config('app.url'), PHP_URL_HOST);

            if ($thumbnailHost !== $applicationHost) {
                return;
            }

            $thumbnail = parse_url($thumbnail, PHP_URL_PATH) ?: '';
        }

        $path = Str::startsWith($thumbnail, '/storage/')
            ? Str::after($thumbnail, '/storage/')
            : ltrim($thumbnail, '/');

        Storage::disk('public')->delete($path);
    }
}
