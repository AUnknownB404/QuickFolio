<?php

namespace App\Http\Controllers;

use App\Http\Requests\Experience\StoreExperienceRequest;
use App\Http\Requests\Experience\UpdateExperienceRequest;
use App\Models\Experience;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExperienceController extends Controller
{
    public function index(Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('Experiences/Index', [
            'portfolio' => $this->portfolioData($portfolio),
            'experiences' => $portfolio->experiences()
                ->orderBy('sort_order')
                ->latest('start_date')
                ->get()
                ->map(fn (Experience $experience): array => $this->experienceData($experience)),
        ]);
    }

    public function create(Portfolio $portfolio): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('Experiences/Form', [
            'portfolio' => $this->portfolioData($portfolio),
        ]);
    }

    public function store(StoreExperienceRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $data = $request->validated();

        if ($data['is_current']) {
            $data['end_date'] = null;
        }

        $portfolio->experiences()->create($data);

        return redirect()->route('portfolios.experiences.index', $portfolio);
    }

    public function show(Portfolio $portfolio, Experience $experience): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('Experiences/Show', [
            'portfolio' => $this->portfolioData($portfolio),
            'experience' => $this->experienceData($experience),
        ]);
    }

    public function edit(Portfolio $portfolio, Experience $experience): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('Experiences/Form', [
            'portfolio' => $this->portfolioData($portfolio),
            'experience' => $this->experienceData($experience),
        ]);
    }

    public function update(
        UpdateExperienceRequest $request,
        Portfolio $portfolio,
        Experience $experience,
    ): RedirectResponse {
        $data = $request->validated();

        if ($data['is_current']) {
            $data['end_date'] = null;
        }

        $experience->update($data);

        return redirect()->route('portfolios.experiences.show', [$portfolio, $experience]);
    }

    public function destroy(Portfolio $portfolio, Experience $experience): RedirectResponse
    {
        $this->authorize('update', $portfolio);
        $experience->delete();

        return redirect()->route('portfolios.experiences.index', $portfolio);
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
    private function experienceData(Experience $experience): array
    {
        return [
            'id' => $experience->id,
            'company' => $experience->company,
            'position' => $experience->position,
            'description' => $experience->description,
            'location' => $experience->location,
            'start_date' => $experience->start_date?->toDateString(),
            'end_date' => $experience->end_date?->toDateString(),
            'is_current' => $experience->is_current,
            'sort_order' => $experience->sort_order,
        ];
    }
}
