<?php

namespace App\Http\Controllers;

use App\Http\Requests\Education\StoreEducationRequest;
use App\Http\Requests\Education\UpdateEducationRequest;
use App\Models\Education;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EducationController extends Controller
{
    public function index(Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('Educations/Index', [
            'portfolio' => $this->portfolioData($portfolio),
            'educations' => $portfolio->educations()
                ->orderBy('sort_order')
                ->latest('start_date')
                ->get()
                ->map(fn (Education $education): array => $this->educationData($education)),
        ]);
    }

    public function create(Portfolio $portfolio): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('Educations/Form', [
            'portfolio' => $this->portfolioData($portfolio),
        ]);
    }

    public function store(StoreEducationRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $portfolio->educations()->create($request->validated());

        return redirect()->route('portfolios.educations.index', $portfolio);
    }

    public function show(Portfolio $portfolio, Education $education): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('Educations/Show', [
            'portfolio' => $this->portfolioData($portfolio),
            'education' => $this->educationData($education),
        ]);
    }

    public function edit(Portfolio $portfolio, Education $education): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('Educations/Form', [
            'portfolio' => $this->portfolioData($portfolio),
            'education' => $this->educationData($education),
        ]);
    }

    public function update(
        UpdateEducationRequest $request,
        Portfolio $portfolio,
        Education $education,
    ): RedirectResponse {
        $education->update($request->validated());

        return redirect()->route('portfolios.educations.show', [$portfolio, $education]);
    }

    public function destroy(Portfolio $portfolio, Education $education): RedirectResponse
    {
        $this->authorize('update', $portfolio);
        $education->delete();

        return redirect()->route('portfolios.educations.index', $portfolio);
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
    private function educationData(Education $education): array
    {
        return [
            'id' => $education->id,
            'institution' => $education->institution,
            'degree' => $education->degree,
            'field_of_study' => $education->field_of_study,
            'description' => $education->description,
            'start_date' => $education->start_date?->toDateString(),
            'end_date' => $education->end_date?->toDateString(),
            'sort_order' => $education->sort_order,
        ];
    }
}
