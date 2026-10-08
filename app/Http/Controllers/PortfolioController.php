<?php

namespace App\Http\Controllers;

use App\Http\Requests\Portfolio\StorePortfolioRequest;
use App\Http\Requests\Portfolio\UpdatePortfolioRequest;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Portfolio::class, 'portfolio');
    }

    public function index(Request $request): Response
    {
        $portfolios = $request->user()
            ->portfolios()
            ->with('template')
            ->latest()
            ->get()
            ->map(fn (Portfolio $portfolio) => [
                'id' => $portfolio->id,
                'title' => $portfolio->title,
                'slug' => $portfolio->slug,
                'status' => $portfolio->status,
                'template_id' => $portfolio->template_id,
                'template' => $portfolio->template ? [
                    'id' => $portfolio->template->id,
                    'name' => $portfolio->template->name,
                ] : null,
                'published_at' => $portfolio->published_at?->toIso8601String(),
                'created_at' => $portfolio->created_at?->toIso8601String(),
                'updated_at' => $portfolio->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Portfolio/Index', [
            'portfolios' => $portfolios,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Portfolio/Create');
    }

    public function store(StorePortfolioRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['user_id'] = $request->user()->id;
        $payload['slug'] = $this->resolveUniqueSlug($request->user()->id, $payload['title'], $payload['slug'] ?? null);
        $payload['status'] = $payload['status'] ?? 'draft';
        $payload['published_at'] = $payload['status'] === 'published' ? now() : null;

        $portfolio = $request->user()->portfolios()->create($payload);

        return redirect()->route('portfolios.show', $portfolio);
    }

    public function show(Portfolio $portfolio): Response
    {
        return Inertia::render('Portfolio/Show', [
            'portfolio' => [
                'id' => $portfolio->id,
                'title' => $portfolio->title,
                'slug' => $portfolio->slug,
                'status' => $portfolio->status,
                'template_id' => $portfolio->template_id,
                'template' => $portfolio->template ? [
                    'id' => $portfolio->template->id,
                    'name' => $portfolio->template->name,
                ] : null,
                'published_at' => $portfolio->published_at?->toIso8601String(),
                'created_at' => $portfolio->created_at?->toIso8601String(),
                'updated_at' => $portfolio->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function edit(Portfolio $portfolio): Response
    {
        return Inertia::render('Portfolio/Edit', [
            'portfolio' => [
                'id' => $portfolio->id,
                'title' => $portfolio->title,
                'slug' => $portfolio->slug,
                'status' => $portfolio->status,
                'template_id' => $portfolio->template_id,
                'published_at' => $portfolio->published_at?->toIso8601String(),
                'created_at' => $portfolio->created_at?->toIso8601String(),
                'updated_at' => $portfolio->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(UpdatePortfolioRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $payload = $request->validated();
        $payload['slug'] = $this->resolveUniqueSlug($request->user()->id, $payload['title'], $payload['slug'] ?? $portfolio->slug, $portfolio->id);
        $payload['published_at'] = $payload['status'] === 'published' ? ($portfolio->published_at ?? now()) : null;

        $portfolio->update($payload);

        return redirect()->route('portfolios.show', $portfolio);
    }

    public function destroy(Portfolio $portfolio): RedirectResponse
    {
        $portfolio->delete();

        return redirect()->route('portfolios.index');
    }

    protected function resolveUniqueSlug(int $userId, string $title, ?string $slug = null, ?int $ignoreId = null): string
    {
        $base = trim((string) Str::of($slug ?: $title)->slug('-'), '-');
        $base = $base !== '' ? $base : 'portfolio';

        $candidate = $base;
        $counter = 2;

        $query = Portfolio::query()
            ->where('user_id', $userId)
            ->where('slug', $candidate);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        while ($query->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;

            $query = Portfolio::query()
                ->where('user_id', $userId)
                ->where('slug', $candidate);

            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }
        }

        return $candidate;
    }
}
