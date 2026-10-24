<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateCategory;
use App\Services\TemplateDemoData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = Template::with(['category', 'media'])
            ->where('is_published', true);

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $templates = $query->latest()->paginate(9)->withQueryString();
        $categories = TemplateCategory::all();

        return Inertia::render('Marketplace/Index', [
            'templates' => $templates,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function show(Template $template, TemplateDemoData $demoData)
    {
        return $this->renderPreview($template, $demoData);
    }

    public function preview(Template $template, TemplateDemoData $demoData)
    {
        return $this->renderPreview($template, $demoData);
    }

    private function renderPreview(Template $template, TemplateDemoData $demoData)
    {
        if (! $template->is_published) {
            abort(404);
        }

        $template->load(['category', 'media', 'sections.fields.fieldDefinition']);
        $demo = $demoData->forTemplate($template);

        return Inertia::render('Marketplace/Show', [
            'template' => $template,
            'demoContent' => $demo['content'],
            'designSettings' => [],
            'demoPortfolio' => $demo['portfolio'],
        ]);
    }
}
