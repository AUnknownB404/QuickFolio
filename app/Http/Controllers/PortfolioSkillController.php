<?php

namespace App\Http\Controllers;

use App\Http\Requests\PortfolioSkill\StorePortfolioSkillRequest;
use App\Http\Requests\PortfolioSkill\UpdatePortfolioSkillRequest;
use App\Models\Portfolio;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioSkillController extends Controller
{
    public function index(Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        $skills = $portfolio->skills()
            ->orderByPivot('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Skill $skill): array => $this->skillData($skill));

        return Inertia::render('Skills/Index', [
            'portfolio' => $this->portfolioData($portfolio),
            'skills' => $skills,
        ]);
    }

    public function create(Portfolio $portfolio): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('Skills/Create', [
            'portfolio' => $this->portfolioData($portfolio),
        ]);
    }

    public function store(StorePortfolioSkillRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($portfolio, $data): void {
            $skillName = trim($data['skill_name']);
            $skill = Skill::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($skillName)])
                ->first();

            if ($skill === null) {
                $skill = Skill::query()->create([
                    'name' => $skillName,
                    'slug' => $this->resolveUniqueSlug($skillName),
                ]);
            }

            $portfolio->skills()->syncWithoutDetaching([
                $skill->id => [
                    'level' => $data['level'] ?? null,
                    'sort_order' => $data['sort_order'],
                ],
            ]);
        });

        return redirect()->route('portfolios.skills.index', $portfolio);
    }

    public function show(Portfolio $portfolio, Skill $skill): Response
    {
        $this->authorize('view', $portfolio);
        $skill = $this->attachedSkill($portfolio, $skill);

        return Inertia::render('Skills/Show', [
            'portfolio' => $this->portfolioData($portfolio),
            'skill' => $this->skillData($skill),
        ]);
    }

    public function edit(Portfolio $portfolio, Skill $skill): Response
    {
        $this->authorize('update', $portfolio);
        $skill = $this->attachedSkill($portfolio, $skill);

        return Inertia::render('Skills/Edit', [
            'portfolio' => $this->portfolioData($portfolio),
            'skill' => $this->skillData($skill),
        ]);
    }

    public function update(
        UpdatePortfolioSkillRequest $request,
        Portfolio $portfolio,
        Skill $skill,
    ): RedirectResponse {
        $skill = $this->attachedSkill($portfolio, $skill);
        $portfolio->skills()->updateExistingPivot($skill->id, $request->validated());

        return redirect()->route('portfolios.skills.show', [$portfolio, $skill]);
    }

    public function destroy(Portfolio $portfolio, Skill $skill): RedirectResponse
    {
        $this->authorize('update', $portfolio);
        $skill = $this->attachedSkill($portfolio, $skill);
        $portfolio->skills()->detach($skill->id);

        return redirect()->route('portfolios.skills.index', $portfolio);
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
     * @return array{id: int, name: string, slug: string, level: int|null, sort_order: int}
     */
    private function skillData(Skill $skill): array
    {
        return [
            'id' => $skill->id,
            'name' => $skill->name,
            'slug' => $skill->slug,
            'level' => $skill->pivot->level,
            'sort_order' => $skill->pivot->sort_order,
        ];
    }

    private function attachedSkill(Portfolio $portfolio, Skill $skill): Skill
    {
        return $portfolio->skills()
            ->whereKey($skill->id)
            ->firstOrFail();
    }

    private function resolveUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'skill';
        $candidate = $base;
        $counter = 2;

        while (Skill::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
