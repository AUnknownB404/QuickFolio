<?php

namespace App\Http\Controllers;

use App\Http\Requests\PortfolioProfile\StorePortfolioProfileRequest;
use App\Http\Requests\PortfolioProfile\UpdatePortfolioProfileRequest;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioProfileController extends Controller
{
    public function create(Portfolio $portfolio): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('PortfolioProfile/Create', [
            'portfolio' => [
                'id' => $portfolio->id,
                'title' => $portfolio->title,
            ],
            'profile' => null,
        ]);
    }

    public function store(StorePortfolioProfileRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $portfolio->profile()->create($request->validated());

        return redirect()->route('portfolios.profile.show', $portfolio);
    }

    public function show(Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('PortfolioProfile/Show', [
            'portfolio' => [
                'id' => $portfolio->id,
                'title' => $portfolio->title,
            ],
            'profile' => $portfolio->profile ? [
                'id' => $portfolio->profile->id,
                'first_name' => $portfolio->profile->first_name,
                'last_name' => $portfolio->profile->last_name,
                'headline' => $portfolio->profile->headline,
                'about' => $portfolio->profile->about,
                'location' => $portfolio->profile->location,
                'phone' => $portfolio->profile->phone,
                'profile_image' => $portfolio->profile->profile_image,
                'resume' => $portfolio->profile->resume,
                'created_at' => $portfolio->profile->created_at?->toIso8601String(),
                'updated_at' => $portfolio->profile->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function edit(Portfolio $portfolio): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('PortfolioProfile/Edit', [
            'portfolio' => [
                'id' => $portfolio->id,
                'title' => $portfolio->title,
            ],
            'profile' => $portfolio->profile ? [
                'id' => $portfolio->profile->id,
                'first_name' => $portfolio->profile->first_name,
                'last_name' => $portfolio->profile->last_name,
                'headline' => $portfolio->profile->headline,
                'about' => $portfolio->profile->about,
                'location' => $portfolio->profile->location,
                'phone' => $portfolio->profile->phone,
                'profile_image' => $portfolio->profile->profile_image,
                'resume' => $portfolio->profile->resume,
            ] : null,
        ]);
    }

    public function update(UpdatePortfolioProfileRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        if ($portfolio->profile) {
            $portfolio->profile()->update($request->validated());
        } else {
            $portfolio->profile()->create($request->validated());
        }

        return redirect()->route('portfolios.profile.show', $portfolio);
    }

    public function destroy(Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $portfolio->profile()->delete();

        return redirect()->route('portfolios.show', $portfolio);
    }
}
