<?php

namespace App\Http\Controllers;

use App\Http\Requests\SocialLink\StoreSocialLinkRequest;
use App\Http\Requests\SocialLink\UpdateSocialLinkRequest;
use App\Models\Portfolio;
use App\Models\SocialLink;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SocialLinkController extends Controller
{
    public function index(Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('SocialLinks/Index', [
            'portfolio' => $this->portfolioData($portfolio),
            'socialLinks' => $portfolio->socialLinks()
                ->orderBy('sort_order')
                ->orderBy('platform')
                ->get()
                ->map(fn (SocialLink $socialLink): array => $this->socialLinkData($socialLink)),
        ]);
    }

    public function create(Portfolio $portfolio): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('SocialLinks/Form', [
            'portfolio' => $this->portfolioData($portfolio),
            'socialLink' => null,
        ]);
    }

    public function store(StoreSocialLinkRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $portfolio->socialLinks()->create($request->validated());

        return redirect()->route('portfolios.social-links.index', $portfolio);
    }

    public function show(Portfolio $portfolio, SocialLink $socialLink): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('SocialLinks/Show', [
            'portfolio' => $this->portfolioData($portfolio),
            'socialLink' => $this->socialLinkData($socialLink),
        ]);
    }

    public function edit(Portfolio $portfolio, SocialLink $socialLink): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('SocialLinks/Form', [
            'portfolio' => $this->portfolioData($portfolio),
            'socialLink' => $this->socialLinkData($socialLink),
        ]);
    }

    public function update(
        UpdateSocialLinkRequest $request,
        Portfolio $portfolio,
        SocialLink $socialLink,
    ): RedirectResponse {
        $socialLink->update($request->validated());

        return redirect()->route('portfolios.social-links.show', [$portfolio, $socialLink]);
    }

    public function destroy(Portfolio $portfolio, SocialLink $socialLink): RedirectResponse
    {
        $this->authorize('update', $portfolio);
        $socialLink->delete();

        return redirect()->route('portfolios.social-links.index', $portfolio);
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
     * @return array{id: int, platform: string, url: string, sort_order: int}
     */
    private function socialLinkData(SocialLink $socialLink): array
    {
        return [
            'id' => $socialLink->id,
            'platform' => $socialLink->platform,
            'url' => $socialLink->url,
            'sort_order' => $socialLink->sort_order,
        ];
    }
}
