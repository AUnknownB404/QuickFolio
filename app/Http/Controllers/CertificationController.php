<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certification\StoreCertificationRequest;
use App\Http\Requests\Certification\UpdateCertificationRequest;
use App\Models\Certification;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CertificationController extends Controller
{
    public function index(Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('Certifications/Index', [
            'portfolio' => $this->portfolioData($portfolio),
            'certifications' => $portfolio->certifications()
                ->latest('issue_date')
                ->latest()
                ->get()
                ->map(fn (Certification $certification): array => $this->certificationData($certification)),
        ]);
    }

    public function create(Portfolio $portfolio): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('Certifications/Form', [
            'portfolio' => $this->portfolioData($portfolio),
            'certification' => null,
        ]);
    }

    public function store(StoreCertificationRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $portfolio->certifications()->create($request->validated());

        return redirect()->route('portfolios.certifications.index', $portfolio);
    }

    public function show(Portfolio $portfolio, Certification $certification): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('Certifications/Show', [
            'portfolio' => $this->portfolioData($portfolio),
            'certification' => $this->certificationData($certification),
        ]);
    }

    public function edit(Portfolio $portfolio, Certification $certification): Response
    {
        $this->authorize('update', $portfolio);

        return Inertia::render('Certifications/Form', [
            'portfolio' => $this->portfolioData($portfolio),
            'certification' => $this->certificationData($certification),
        ]);
    }

    public function update(
        UpdateCertificationRequest $request,
        Portfolio $portfolio,
        Certification $certification,
    ): RedirectResponse {
        $certification->update($request->validated());

        return redirect()->route('portfolios.certifications.show', [$portfolio, $certification]);
    }

    public function destroy(Portfolio $portfolio, Certification $certification): RedirectResponse
    {
        $this->authorize('update', $portfolio);
        $certification->delete();

        return redirect()->route('portfolios.certifications.index', $portfolio);
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
    private function certificationData(Certification $certification): array
    {
        return [
            'id' => $certification->id,
            'name' => $certification->name,
            'organization' => $certification->organization,
            'credential_id' => $certification->credential_id,
            'credential_url' => $certification->credential_url,
            'issue_date' => $certification->issue_date?->toDateString(),
            'expiry_date' => $certification->expiry_date?->toDateString(),
            'image' => $certification->image,
        ];
    }
}
