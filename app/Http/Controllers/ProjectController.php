<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        $projects = $portfolio->projects()
            ->with('skills:id,name')
            ->orderBy('sort_order')
            ->latest()
            ->get()
            ->map(fn (Project $project): array => $this->projectData($project));

        return Inertia::render('Projects/Index', [
            'portfolio' => $this->portfolioData($portfolio),
            'projects' => $projects,
        ]);
    }

    public function create(Portfolio $portfolio): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('Projects/Create', [
            'portfolio' => $this->portfolioData($portfolio),
            'skills' => $portfolio->skills()->orderBy('name')->get(['skills.id', 'skills.name']),
        ]);
    }

    public function store(StoreProjectRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $data = $request->validated();
        $skillIds = $data['skills'] ?? [];
        unset($data['skills']);

        $data['slug'] = $this->resolveUniqueSlug(
            $portfolio,
            $data['title'],
            $data['slug'] ?? null,
        );

        $project = DB::transaction(function () use ($portfolio, $data, $skillIds): Project {
            $project = $portfolio->projects()->create($data);
            $project->skills()->sync($skillIds);

            return $project;
        });

        return redirect()->route('portfolios.projects.show', [$portfolio, $project]);
    }

    public function show(Portfolio $portfolio, Project $project): Response
    {
        $this->authorize('view', $portfolio);
        $project->load('skills:id,name');

        return Inertia::render('Projects/Show', [
            'portfolio' => $this->portfolioData($portfolio),
            'project' => $this->projectData($project),
        ]);
    }

    public function edit(Portfolio $portfolio, Project $project): Response
    {
        $this->authorize('update', $portfolio);
        $project->load('skills:id,name');

        return Inertia::render('Projects/Edit', [
            'portfolio' => $this->portfolioData($portfolio),
            'project' => $this->projectData($project),
            'skills' => $portfolio->skills()->orderBy('name')->get(['skills.id', 'skills.name']),
        ]);
    }

    public function update(
        UpdateProjectRequest $request,
        Portfolio $portfolio,
        Project $project,
    ): RedirectResponse {
        $data = $request->validated();
        $skillIds = $data['skills'] ?? [];
        unset($data['skills']);

        $data['slug'] = $this->resolveUniqueSlug(
            $portfolio,
            $data['title'],
            $data['slug'] ?? $project->slug,
            $project->id,
        );

        DB::transaction(function () use ($project, $data, $skillIds): void {
            $project->update($data);
            $project->skills()->sync($skillIds);
        });

        return redirect()->route('portfolios.projects.show', [$portfolio, $project]);
    }

    public function destroy(Portfolio $portfolio, Project $project): RedirectResponse
    {
        $this->authorize('update', $portfolio);
        $project->delete();

        return redirect()->route('portfolios.projects.index', $portfolio);
    }

    /**
     * @return array{id: int, title: string}
     */
    private function portfolioData(Portfolio $portfolio): array
    {
        return [
            'id' => $portfolio->id,
            'title' => $portfolio->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectData(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'slug' => $project->slug,
            'description' => $project->description,
            'image' => $project->image,
            'project_url' => $project->project_url,
            'github_url' => $project->github_url,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'is_featured' => $project->is_featured,
            'sort_order' => $project->sort_order,
            'skills' => $project->skills->map(fn ($skill): array => [
                'id' => $skill->id,
                'name' => $skill->name,
            ])->all(),
        ];
    }

    private function resolveUniqueSlug(
        Portfolio $portfolio,
        string $title,
        ?string $slug = null,
        ?int $ignoreId = null,
    ): string {
        $base = Str::slug($slug ?: $title) ?: 'project';
        $candidate = $base;
        $counter = 2;

        while (
            $portfolio->projects()
                ->where('slug', $candidate)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
