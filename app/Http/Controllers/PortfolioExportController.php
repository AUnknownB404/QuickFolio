<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Services\GoogleFormIntegration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class PortfolioExportController extends Controller
{
    use AuthorizesRequests;

    private const TEMPLATE_THEMES = [
        'emerald-minimalist' => 'emerald',
        'dark-orange-designer' => 'dark-orange',
        'ivory-editorial' => 'ivory',
        'bento-grid' => 'bento',
        'cobalt-brutalist' => 'cobalt',
    ];

    public function download(Request $request, Portfolio $portfolio): StreamedResponse
    {
        $this->authorize('update', $portfolio);

        $portfolio->load([
            'template',
            'sections.fieldValues.fieldDefinition',
        ]);

        $content = [];
        foreach ($portfolio->sections as $section) {
            if (! $section->is_enabled) {
                continue;
            }

            $content[$section->section_key] = [];
            foreach ($section->fieldValues as $fieldValue) {
                if ($fieldValue->fieldDefinition) {
                    $content[$section->section_key][$fieldValue->fieldDefinition->field_key] = $fieldValue->value;
                }
            }
        }

        $templateSlug = $portfolio->template->slug;
        $storageDisk = Storage::disk('public');
        $assets = $this->collectAssets($content, $portfolio, $storageDisk);
        $brandLogoPath = base_path('storage/app/public/Logo.svg');
        if (! is_file($brandLogoPath)) {
            abort(500, 'Could not find the quickFolio logo for the portfolio export.');
        }
        $brandLogo = file_get_contents($brandLogoPath);
        if ($brandLogo === false) {
            abort(500, 'Could not read the quickFolio logo for the portfolio export.');
        }
        $brandLogoPngPath = public_path('Logo.png');
        if (! is_file($brandLogoPngPath)) {
            abort(500, 'Could not find the quickFolio PNG logo for the portfolio export.');
        }
        $brandLogoPng = file_get_contents($brandLogoPngPath);
        if ($brandLogoPng === false) {
            abort(500, 'Could not read the quickFolio PNG logo for the portfolio export.');
        }
        $googleForm = null;
        if (in_array($templateSlug, ['bento-grid', 'cobalt-brutalist', 'dark-orange-designer', 'emerald-minimalist', 'ivory-editorial'], true)) {
            $responseUrl = GoogleFormIntegration::responseUrl($portfolio->google_form_url);
            $entryIds = [
                $portfolio->google_form_name_entry,
                $portfolio->google_form_email_entry,
                $portfolio->google_form_message_entry,
            ];
            if (
                $responseUrl !== null
                && collect($entryIds)->every(fn (?string $entryId) => is_string($entryId) && preg_match('/^\d{1,20}$/', $entryId) === 1)
                && count(array_unique($entryIds)) === count($entryIds)
            ) {
                $googleForm = [
                    'action' => $responseUrl,
                    'name_entry' => $entryIds[0],
                    'email_entry' => $entryIds[1],
                    'message_entry' => $entryIds[2],
                ];
            }
        }
        $files = [
            'index.html' => $this->renderPage($templateSlug, $portfolio->title, $content, $assets, $googleForm),
            'styles.css' => $this->stylesForTemplate($templateSlug),
            'script.js' => $this->script($templateSlug),
            'Logo.svg' => $brandLogo,
            'Logo.png' => $brandLogoPng,
        ];

        $temporaryArchive = tempnam(sys_get_temp_dir(), 'portfolio_export_');
        if ($temporaryArchive === false) {
            abort(500, 'Could not create a temporary export archive.');
        }

        $archive = new ZipArchive;
        if ($archive->open($temporaryArchive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryArchive);
            abort(500, 'Could not create the export archive.');
        }

        try {
            foreach ($files as $path => $contents) {
                if (! $archive->addFromString($path, $contents)) {
                    abort(500, "Could not add {$path} to the export archive.");
                }
            }

            foreach ($assets as $asset) {
                if (! $archive->addFile($asset['absolute_path'], $asset['archive_path'])) {
                    abort(500, 'Could not add a portfolio asset to the export archive.');
                }
            }

            if (! $archive->close()) {
                abort(500, 'Could not finish the export archive.');
            }
        } catch (\Throwable $exception) {
            $archive->close();
            @unlink($temporaryArchive);

            throw $exception;
        }

        return response()->streamDownload(function () use ($temporaryArchive) {
            $file = fopen($temporaryArchive, 'rb');
            if ($file === false) {
                @unlink($temporaryArchive);
                abort(500, 'Could not read the export archive.');
            }

            try {
                fpassthru($file);
            } finally {
                fclose($file);
                @unlink($temporaryArchive);
            }
        }, "portfolio-{$portfolio->slug}.zip", [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function renderPage(string $slug, string $title, array $content, array $assets, ?array $googleForm = null): string
    {
        if ($slug === 'bento-grid') {
            return $this->renderBentoGridPage($title, $content, $assets, $googleForm);
        }
        if ($slug === 'cobalt-brutalist') {
            return $this->renderCobaltBrutalistPage($title, $content, $assets, $googleForm);
        }
        if ($slug === 'dark-orange-designer') {
            return $this->renderDarkOrangeDesignerPage($title, $content, $assets, $googleForm);
        }
        if ($slug === 'emerald-minimalist') {
            return $this->renderEmeraldMinimalistPage($title, $content, $assets, $googleForm);
        }
        if ($slug === 'ivory-editorial') {
            return $this->renderIvoryEditorialPage($title, $content, $assets, $googleForm);
        }

        $theme = self::TEMPLATE_THEMES[$slug] ?? 'emerald';
        $escape = fn (mixed $value): string => e(is_scalar($value) ? (string) $value : '');
        $assetUrl = fn (mixed $value): string => $this->assetUrl($value, $assets);
        $url = fn (mixed $value, string $fallback = '#'): string => $this->safeUrl($assetUrl($value), $fallback);

        $navbar = $this->sectionData($content['navbar'] ?? []);
        $header = $this->sectionData($content['header'] ?? []);
        $about = $this->sectionData($content['about'] ?? []);
        $journey = $this->sectionData($content['journey'] ?? []);
        $skills = $this->sectionData($content['skills'] ?? []);
        $projects = $this->sectionData($content['portfolio'] ?? $content['projects'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);
        $services = $this->sectionData($content['services'] ?? []);

        $navigation = '';
        foreach ($this->items($navbar['nav_links'] ?? []) as $link) {
            $navigation .= '<a href="'.$url($link['url'] ?? null).'">'.$escape($link['label'] ?? '').'</a>';
        }

        $heroImage = $url($header['hero_image'] ?? null, '');
        $hero = '<section class="hero" id="home"><div class="hero-copy">';
        if (! empty($header['hero_greeting'])) {
            $hero .= '<p class="eyebrow">'.$escape($header['hero_greeting']).'</p>';
        }
        $hero .= '<h1>'.$escape($header['hero_name'] ?? $title).'</h1>';
        if (! empty($header['hero_role'])) {
            $hero .= '<p class="hero-role">'.$escape($header['hero_role']).'</p>';
        }
        if (! empty($header['hero_description'])) {
            $hero .= '<p class="hero-description">'.$escape($header['hero_description']).'</p>';
        }
        if (! empty($header['hero_cta_primary_text'])) {
            $hero .= '<a class="button" href="'.$url($header['hero_cta_primary_url'] ?? null, '#portfolio').'">'.$escape($header['hero_cta_primary_text']).'</a>';
        }
        $hero .= '</div>';
        if ($heroImage !== '') {
            $hero .= '<div class="hero-image"><img src="'.$escape($heroImage).'" alt="'.$escape($header['hero_name'] ?? '').'"></div>';
        }
        $hero .= '</section>';

        $sections = $hero;
        if (! empty($about['about_title']) || ! empty($about['about_description']) || ! empty($about['about_image'])) {
            $sections .= $this->section(
                'about',
                $about['about_title'] ?? 'About',
                $about['about_description'] ?? '',
                $about['about_image'] ?? null,
                $about['about_cv_file'] ?? null,
                $url,
                $escape
            );
        }

        $sections .= $this->listSection(
            'journey',
            $journey['journey_title'] ?? 'Experience',
            $journey['journey_subtitle'] ?? '',
            $this->items($journey['journey_list'] ?? []),
            $escape
        );
        $sections .= $this->skillsSection($skills, $escape);
        $sections .= $this->projectsSection($projects, $assetUrl, $url, $escape);
        $sections .= $this->listSection(
            'services',
            $services['services_title'] ?? 'Services',
            $services['services_subtitle'] ?? '',
            $this->items($services['services_list'] ?? []),
            $escape
        );
        $sections .= $this->contactSection($contact, $url, $escape);

        $brand = $navbar['logo_text'] ?? $title;
        $showLogo = ! in_array($navbar['show_logo'] ?? true, [false, 0, '0', 'false'], true);
        $logoImage = $url($navbar['logo_image'] ?? null, 'Logo.svg');

        return '<!doctype html>
<html lang="en" data-template="'.$escape($slug).'">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>'.$escape($title).'</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="theme-'.$escape($theme).'">
    <nav class="site-nav" aria-label="Main navigation">
        <a class="brand" href="#home">'.($showLogo ? '<img src="'.$logoImage.'" alt="" width="32" height="32">' : '').$escape($brand).'</a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-links">Menu</button>
        <div class="site-links" id="site-links">'.$navigation.'</div>
    </nav>
    <main>'.$sections.'</main>
    <footer class="site-footer"><img src="Logo.svg" alt="" width="20" height="20"> Created with quickFolio</footer>
    <script src="script.js" defer></script>
</body>
</html>';
    }

    private function renderBentoGridPage(string $title, array $content, array $assets, ?array $googleForm): string
    {
        $escape = fn (mixed $value): string => e(is_scalar($value) ? (string) $value : '');
        $url = fn (mixed $value, string $fallback = '#'): string => $escape($this->safeUrl($this->assetUrl($value, $assets), $fallback));
        $navbar = $this->sectionData($content['navbar'] ?? []);
        $hero = $this->sectionData($content['header'] ?? []);
        $about = $this->sectionData($content['about'] ?? []);
        $journey = $this->sectionData($content['journey'] ?? []);
        $skills = $this->sectionData($content['skills'] ?? []);
        $portfolio = $this->sectionData($content['portfolio'] ?? $content['projects'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);
        $projects = $this->items($portfolio['projects_list'] ?? $portfolio['projects'] ?? []);
        $skillItems = $this->items($skills['skills_list'] ?? []);
        $journeyItems = $this->items($journey['journey_list'] ?? []);

        $hasAbout = ! empty($about['about_title'])
            || ! empty($about['about_description'])
            || ! empty($about['about_image'])
            || ! empty($about['about_cv_file']);
        $formEnabled = ! in_array($contact['contact_form_enabled'] ?? true, [false, 0, '0', 'false'], true);
        $hasContact = ($formEnabled && $googleForm !== null)
            || ! empty($contact['contact_title'])
            || ! empty($contact['contact_description'])
            || ! empty($contact['contact_email'])
            || ! empty($contact['contact_phone'])
            || ! empty($contact['contact_location']);
        $socialLinks = $this->items($hero['hero_social_links'] ?? $contact['contact_social_links'] ?? []);

        $links = [['id' => 'home', 'label' => 'Intro']];
        if ($hasAbout) {
            $links[] = ['id' => 'about', 'label' => 'About'];
        }
        if ($projects !== []) {
            $links[] = ['id' => 'portfolio', 'label' => 'Work'];
        }
        if ($skillItems !== []) {
            $links[] = ['id' => 'skills', 'label' => 'Skills'];
        }
        if ($journeyItems !== []) {
            $links[] = ['id' => 'journey', 'label' => 'Journey'];
        }
        if ($hasContact) {
            $links[] = ['id' => 'contact', 'label' => 'Contact'];
        }

        $mobileLinks = '';
        $desktopLinks = '';
        foreach ($links as $index => $link) {
            $mobileLinks .= '<li class="shrink-0"><a href="#'.$escape($link['id']).'" class="block rounded-full px-3 py-1.5 text-sm font-medium'.($link['id'] === 'home' ? ' mobile-active' : '').'" data-nav-link="'.$escape($link['id']).'">'.$escape($link['label']).'</a></li>';
            $desktopLinks .= '<li class="rail-in" style="--i: '.($index + 1).'"><a href="#'.$escape($link['id']).'" class="rail-link" data-nav-link="'.$escape($link['id']).'"><span class="rail-bar"></span>'.$escape($link['label']).'</a></li>';
        }

        $logo = is_string($navbar['logo_text'] ?? null) ? $navbar['logo_text'] : '';
        $showLogo = ! in_array($navbar['show_logo'] ?? true, [false, 0, '0', 'false'], true);
        $logoImage = $url($navbar['logo_image'] ?? null, 'Logo.svg');
        $html = '<div class="rail relative min-h-screen overflow-x-clip font-body">
    <div class="progress" aria-hidden="true"></div>
    <nav class="sticky top-0 z-40 flex items-center gap-4 border-b border-[var(--line)] bg-[#e3cfe6]/85 px-4 py-3 backdrop-blur lg:hidden">';
        if ($showLogo) {
            $html .= '<img src="'.$logoImage.'" alt="'.$escape($logo).'" width="32" height="32" class="shrink-0">';
        }
        $html .= '<ul class="no-scrollbar flex gap-1 overflow-x-auto">'.$mobileLinks.'</ul>
    </nav>
    <div class="relative z-[1] grid items-start lg:grid-cols-[264px_minmax(0,1fr)]">
        <aside class="rail-panel sticky top-4 hidden h-[calc(100svh-2rem)] flex-col justify-between p-6 lg:ml-4 lg:mt-4 lg:flex">
            <a href="#home" class="rail-in flex items-center gap-3 rounded-lg" style="--i: 0" aria-label="Back to top">';
        if ($showLogo) {
            $html .= '<img src="'.$logoImage.'" alt="'.$escape($logo).'" width="40" height="40">';
        }
        $html .= '</a>
            <ul class="space-y-1">'.$desktopLinks.'</ul>
            <div class="rail-in space-y-3 text-sm text-[var(--muted)]" style="--i: '.(count($links) + 1).'">';
        if (! empty($contact['contact_email'])) {
            $email = (string) $contact['contact_email'];
            $html .= '<a href="'.$url('mailto:'.$email).'" class="sweep block break-all font-semibold text-[var(--ink)]">'.$escape($email).'</a>';
        }
        if ($socialLinks !== []) {
            $html .= '<div class="flex flex-wrap gap-x-4 gap-y-1">';
            foreach ($socialLinks as $socialLink) {
                $html .= '<a href="'.$url($socialLink['url'] ?? null).'" target="_blank" rel="noreferrer" class="transition-colors hover:text-[var(--accent)]">'.$escape($socialLink['label'] ?? '').'</a>';
            }
            $html .= '</div>';
        }
        $html .= '</div>
        </aside>
        <main class="min-w-0 px-5 pb-10 sm:px-10 lg:px-16 xl:px-24">
            <header id="home" data-section class="grid min-h-[calc(100svh-60px)] items-center gap-12 py-16 lg:min-h-screen lg:grid-cols-12 lg:py-24">
                <div class="lg:col-span-8">';
        if (! empty($hero['hero_greeting'])) {
            $html .= '<p class="rail-fade mb-6 flex items-center gap-2 text-base font-medium text-[var(--muted)]" style="--i: 3"><span class="dot"></span>'.$escape($hero['hero_greeting']).'</p>';
        }
        $heroWords = is_string($hero['hero_name'] ?? null) ? preg_split('/\s+/', trim($hero['hero_name']), -1, PREG_SPLIT_NO_EMPTY) : [];
        if ($heroWords !== []) {
            $html .= '<h1 class="font-display text-[clamp(3.25rem,8vw,7.5rem)] leading-[0.95] tracking-[-0.025em]">';
            foreach ($heroWords as $index => $word) {
                $html .= '<span class="mask"><span class="mask-inner" style="--i: '.$index.'">'.$escape($word).'&nbsp;</span></span>';
            }
            $html .= '</h1>';
        }
        if (! empty($hero['hero_role'])) {
            $html .= '<p class="mt-6 text-2xl font-semibold sm:text-3xl"><span class="mark" data-reveal>'.$escape($hero['hero_role']).'</span></p>';
        }
        if (! empty($hero['hero_description'])) {
            $html .= '<p class="rail-fade mt-6 max-w-xl text-lg leading-relaxed text-[var(--muted)] sm:text-xl" style="--i: 6">'.$escape($hero['hero_description']).'</p>';
        }
        $html .= '<div class="rail-fade mt-10 flex flex-wrap items-center gap-x-8 gap-y-4" style="--i: 7">';
        if (! empty($hero['hero_cta_primary_text'])) {
            $html .= '<a href="'.$url($hero['hero_cta_primary_url'] ?? null, '#portfolio').'" class="btn-ink group">'.$escape($hero['hero_cta_primary_text']).'<span class="btn-ink-icon" aria-hidden="true">↓</span></a>';
        }
        foreach ($this->items($hero['hero_social_links'] ?? []) as $socialLink) {
            $html .= '<a href="'.$url($socialLink['url'] ?? null).'" target="_blank" rel="noreferrer" class="sweep font-semibold lg:hidden">'.$escape($socialLink['label'] ?? '').'</a>';
        }
        $heroImage = $this->assetUrl($hero['hero_image'] ?? null, $assets);
        $heroFallback = ($hero['hero_name'] ?? '') ?: ($logo ?: '•');
        $heroInitial = is_string($heroFallback) && $heroFallback !== '' ? mb_substr($heroFallback, 0, 1) : '•';
        $html .= '</div>
                </div>
                <figure class="rail-fade lg:col-span-4" style="--i: 5">
                    <div class="portrait group">';
        if ($heroImage !== '') {
            $html .= '<img src="'.$escape($this->safeUrl($heroImage, '')).'" alt="'.$escape($hero['hero_name'] ?? '').'" class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.05]" />';
        } else {
            $html .= '<span class="grid h-full w-full place-items-center font-display text-[9rem] text-[#2f1a7a]/30" aria-hidden="true">'.$escape($heroInitial).'</span>';
        }
        $html .= '</div></figure></header>';

        if ($hasAbout) {
            $aboutImage = $this->assetUrl($about['about_image'] ?? null, $assets);
            $html .= '<section id="about" data-section class="section grid gap-12 lg:grid-cols-5">';
            if ($aboutImage !== '') {
                $html .= '<div class="lg:col-span-2"><div class="portrait portrait-alt group"><img src="'.$escape($this->safeUrl($aboutImage, '')).'" alt="'.$escape($about['about_title'] ?? '').'" class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.05]" /></div></div>';
            }
            $html .= '<div class="'.($aboutImage !== '' ? 'lg:col-span-3' : 'lg:col-span-5 max-w-3xl').'">';
            if (! empty($about['about_title'])) {
                $html .= '<h2 class="h2"><span class="mark" data-reveal>'.$escape($about['about_title']).'</span></h2>';
            }
            if (! empty($about['about_description'])) {
                $html .= '<p class="mt-8 max-w-xl whitespace-pre-line text-lg leading-[1.8] text-[var(--muted)]">'.$escape($about['about_description']).'</p>';
            }
            if (! empty($about['about_cv_file'])) {
                $html .= '<a href="'.$url($about['about_cv_file']).'" class="btn-ink group mt-10">Download CV <span class="btn-ink-icon" aria-hidden="true">↓</span></a>';
            }
            $html .= '</div></section>';
        }

        if ($projects !== []) {
            $html .= '<section id="portfolio" data-section class="section">
                <div class="mb-12 flex flex-wrap items-end justify-between gap-4"><div>';
            if (! empty($portfolio['projects_title'])) {
                $html .= '<h2 class="h2">'.$escape($portfolio['projects_title']).'</h2>';
            }
            if (! empty($portfolio['projects_subtitle'])) {
                $html .= '<p class="mt-4 max-w-lg text-lg text-[var(--muted)]">'.$escape($portfolio['projects_subtitle']).'</p>';
            }
            $projectCount = count($projects);
            $html .= '</div><p class="text-[var(--muted)]">'.$projectCount.' '.($projectCount === 1 ? 'project' : 'projects').'</p></div><div>';
            foreach ($projects as $index => $project) {
                $projectUrl = ! empty($project['url']) ? $url($project['url']) : null;
                $html .= '<article class="project group mb-14 lg:sticky lg:mb-[34vh]'.($index === $projectCount - 1 ? ' lg:!mb-0' : '').'" style="top: calc(1.5rem + '.(min($index, 5) * 14).'px)"><'.($projectUrl !== null ? 'a href="'.$projectUrl.'" target="_blank" rel="noreferrer"' : 'div').' class="block">
                    <div class="relative h-[70vh] w-full aspect-[16/9] overflow-hidden bg-gradient-to-br from-[#f1e6f8] to-[#e07ad8]">';
                $projectImage = $this->assetUrl($project['image'] ?? null, $assets);
                if ($projectImage !== '') {
                    $html .= '<img src="'.$escape($this->safeUrl($projectImage, '')).'" alt="'.$escape($project['title'] ?? '').'" class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.04]" loading="lazy" />';
                } else {
                    $projectInitial = is_string($project['title'] ?? null) && $project['title'] !== '' ? mb_substr($project['title'], 0, 1) : '•';
                    $html .= '<span class="grid h-full w-full place-items-center font-display text-9xl text-[#2f1a7a]/30" aria-hidden="true">'.$escape($projectInitial).'</span>';
                }
                $html .= '</div><div class="flex items-center justify-between gap-6 p-6 sm:p-8"><div class="min-w-0"><p class="mb-2 text-sm font-semibold tabular-nums text-[var(--muted)]">'.($index + 1).' / '.$projectCount.'</p>';
                if (! empty($project['title'])) {
                    $html .= '<h3 class="font-display text-2xl sm:text-4xl">'.$escape($project['title']).'</h3>';
                }
                $html .= '<div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-[var(--muted)]">';
                if (! empty($project['category'])) {
                    $html .= '<span class="rounded-full bg-[var(--lilac)] px-3 py-1 font-semibold text-[var(--ink)]">'.$escape($project['category']).'</span>';
                }
                if (! empty($project['period'])) {
                    $html .= '<span>'.$escape($project['period']).'</span>';
                }
                $html .= '</div></div>';
                if ($projectUrl !== null) {
                    $html .= '<span class="project-arrow" aria-hidden="true">↗</span>';
                }
                $html .= '</div></'.($projectUrl !== null ? 'a' : 'div').'></article>';
            }
            $html .= '</div></section>';
        }

        if ($skillItems !== []) {
            $html .= '<section id="skills" data-section class="section">';
            if (! empty($skills['skills_title'])) {
                $html .= '<h2 class="h2">'.$escape($skills['skills_title']).'</h2>';
            }
            if (! empty($skills['skills_description'])) {
                $html .= '<p class="mt-4 max-w-lg text-lg text-[var(--muted)]">'.$escape($skills['skills_description']).'</p>';
            }
            $html .= '<ul class="mt-12 flex flex-wrap gap-3" data-reveal>';
            foreach ($skillItems as $index => $skill) {
                $html .= '<li class="pill" style="--d: '.($index * 70).'ms">';
                if (! empty($skill['percent'])) {
                    $percent = max(0, min(100, (float) $skill['percent']));
                    $html .= '<span class="pill-fill" style="width: '.$percent.'%"></span>';
                }
                $html .= '<span class="relative z-10 font-semibold">'.$escape($skill['label'] ?? '').'</span>';
                if (! empty($skill['percent'])) {
                    $html .= '<span class="relative z-10 text-sm tabular-nums text-[var(--muted)]">'.$escape($skill['percent']).'%</span>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></section>';
        }

        if ($journeyItems !== []) {
            $html .= '<section id="journey" data-section class="section"><div class="journey-panel">';
            if (! empty($journey['journey_title'])) {
                $html .= '<h2 class="h2 text-white">'.$escape($journey['journey_title']).'</h2>';
            }
            if (! empty($journey['journey_subtitle'])) {
                $html .= '<p class="mt-4 max-w-lg text-lg text-white/85">'.$escape($journey['journey_subtitle']).'</p>';
            }
            $html .= '<div class="mt-14">';
            foreach ($journeyItems as $item) {
                $html .= '<article class="jrow" data-reveal>';
                if (! empty($item['period'])) {
                    $html .= '<p class="jperiod">'.$escape($item['period']).'</p>';
                }
                $html .= '<div class="md:col-start-2">';
                if (! empty($item['title'])) {
                    $html .= '<h3 class="font-display text-2xl leading-tight text-white sm:text-4xl">'.$escape($item['title']).'</h3>';
                }
                if (! empty($item['company'])) {
                    $html .= '<p class="mt-2 text-lg font-semibold text-[#fff0fb]">'.$escape($item['company']).'</p>';
                }
                if (! empty($item['description'])) {
                    $html .= '<p class="mt-4 max-w-xl leading-relaxed text-white/85">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></article>';
            }
            $html .= '</div></div></section>';
        }

        if ($hasContact) {
            $html .= '<section id="contact" data-section class="section">';
            if (! empty($contact['contact_title'])) {
                $html .= '<h2 class="font-display text-[clamp(2.75rem,7vw,6rem)] leading-[0.98] tracking-[-0.025em]">'.$escape($contact['contact_title']).'</h2>';
            }
            if (! empty($contact['contact_description'])) {
                $html .= '<p class="mt-6 max-w-xl text-lg text-[var(--muted)] sm:text-xl">'.$escape($contact['contact_description']).'</p>';
            }
            if (! empty($contact['contact_email'])) {
                $email = (string) $contact['contact_email'];
                $html .= '<a href="'.$url('mailto:'.$email).'" class="sweep mt-10 inline-block break-all font-display text-2xl text-[var(--accent)] sm:text-4xl">'.$escape($email).'</a>';
            }
            $html .= '<div class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-[var(--muted)]">';
            if (! empty($contact['contact_phone'])) {
                $phone = (string) $contact['contact_phone'];
                $html .= '<a href="'.$url('tel:'.$phone).'" class="hover:text-[var(--ink)]">'.$escape($phone).'</a>';
            }
            if (! empty($contact['contact_location'])) {
                $html .= '<span>'.$escape($contact['contact_location']).'</span>';
            }
            foreach ($this->items($contact['contact_social_links'] ?? []) as $socialLink) {
                $html .= '<a href="'.$url($socialLink['url'] ?? null).'" target="_blank" rel="noreferrer" class="hover:text-[var(--ink)]">'.$escape($socialLink['label'] ?? '').'</a>';
            }
            $html .= '</div>';
            if ($formEnabled && $googleForm !== null) {
                $html .= '<div class="mt-14 rounded-[28px] border border-white/60 bg-white/75 p-6 shadow-[0_30px_60px_-40px_rgba(91,33,182,0.5)] backdrop-blur-md sm:p-10">
                    <form action="'.$escape($googleForm['action']).'" method="POST" target="bg-gform-frame" class="space-y-5 text-left" data-google-form>
                        <h3 class="font-display text-2xl font-semibold text-[var(--ink)]">Send a message</h3>
                        <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">Name
                            <input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="w-full rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-[var(--ink)] focus:border-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/20" />
                        </label>
                        <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">Email
                            <input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="w-full rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-[var(--ink)] focus:border-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/20" />
                        </label>
                        <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">Message
                            <textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required class="w-full resize-y rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-[var(--ink)] focus:border-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/20"></textarea>
                        </label>
                        <button type="submit" class="btn-ink disabled:cursor-not-allowed disabled:opacity-60">Send message <span class="btn-ink-icon" aria-hidden="true">→</span></button>
                        <p class="hidden text-sm font-semibold text-[var(--accent)]" role="status" data-form-status></p>
                    </form>
                </div>
                <iframe name="bg-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true"></iframe>';
            }
            $html .= '</section>';
        }

        if ($logo !== '') {
            $html .= '<footer class="mt-24 flex items-center justify-between border-t border-[var(--line)] pt-6 text-sm text-[var(--muted)]"><span>'.$escape($logo).'</span><a href="#home" class="sweep font-semibold text-[var(--ink)]">Back to top ↑</a></footer>';
        }

        return '<!doctype html>
<html lang="en" data-template="bento-grid">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e3cfe6">
    <title>'.$escape($title).'</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    private function renderCobaltBrutalistPage(string $title, array $content, array $assets, ?array $googleForm): string
    {
        $escape = fn (mixed $value): string => e(is_scalar($value) ? (string) $value : '');
        $assetUrl = fn (mixed $value): string => $this->assetUrl($value, $assets);
        $url = fn (mixed $value, string $fallback = '#'): string => $escape($this->safeUrl($assetUrl($value), $fallback));
        $list = fn (mixed $value): array => is_array($value) ? array_values($value) : [];
        $isEnabled = static fn (mixed $value, bool $default = true): bool => $value === null || $value === ''
            ? $default
            : ! in_array($value, [false, 0, '0', 'false'], true);
        $navbar = $this->sectionData($content['navbar'] ?? []);
        $header = $this->sectionData($content['header'] ?? []);
        $about = $this->sectionData($content['about'] ?? []);
        $journey = $this->sectionData($content['journey'] ?? []);
        $skillsData = $this->sectionData($content['skills'] ?? []);
        $portfolio = $this->sectionData($content['portfolio'] ?? $content['projects'] ?? []);
        $servicesData = $this->sectionData($content['services'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);

        $highlights = $list($about['about_highlights'] ?? []);
        $journeyItems = $list($journey['journey_list'] ?? []);
        $skills = $list($skillsData['skills_list'] ?? []);
        $projects = $list($portfolio['projects_list'] ?? []);
        $services = $list($servicesData['services_list'] ?? []);
        $heroSocials = $list($header['hero_social_links'] ?? []);
        $contactSocials = $list($contact['contact_social_links'] ?? []);
        if ($contactSocials === []) {
            $contactSocials = $heroSocials;
        }
        $logo = ! empty($navbar['logo_text']) ? (string) $navbar['logo_text'] : (string) ($header['hero_name'] ?? '');
        $logoImage = $isEnabled($navbar['show_logo'] ?? null)
            ? $url($navbar['logo_image'] ?? null, 'Logo.svg')
            : '';
        $heroName = is_string($header['hero_name'] ?? null) ? trim($header['hero_name']) : '';
        $heroInitials = implode('', array_map(
            static fn (string $word): string => mb_substr($word, 0, 1),
            array_slice(preg_split('/\s+/', $heroName !== '' ? $heroName : $logo, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2)
        ));
        $email = is_string($contact['contact_email'] ?? null) ? trim($contact['contact_email']) : '';
        $phone = is_string($contact['contact_phone'] ?? null) ? trim($contact['contact_phone']) : '';
        $location = is_string($contact['contact_location'] ?? null) ? trim($contact['contact_location']) : '';
        $formEnabled = $isEnabled($contact['contact_form_enabled'] ?? null);
        $formReady = $formEnabled && $googleForm !== null;
        $hasAbout = ! empty($about['about_subtitle']) || ! empty($about['about_title']) || ! empty($about['about_description'])
            || ! empty($about['about_image']) || $highlights !== [] || ! empty($about['about_cv_file'])
            || (! empty($about['about_cta_text']) && ! empty($about['about_cta_url']));
        $hasJourney = ! empty($journey['journey_subtitle']) || ! empty($journey['journey_title']) || $journeyItems !== [];
        $hasSkills = ! empty($skillsData['skills_subtitle']) || ! empty($skillsData['skills_title'])
            || ! empty($skillsData['skills_description']) || $skills !== [];
        $hasProjects = ! empty($portfolio['projects_subtitle']) || ! empty($portfolio['projects_title'])
            || ! empty($portfolio['projects_description']) || $projects !== [];
        $hasServices = ! empty($servicesData['services_subtitle']) || ! empty($servicesData['services_title']) || $services !== [];
        $hasContact = ! empty($contact['contact_subtitle']) || ! empty($contact['contact_title'])
            || ! empty($contact['contact_description']) || $email !== '' || $phone !== '' || $location !== ''
            || $contactSocials !== [] || $formReady;

        $navLinks = [];
        foreach ([
            ['about', 'About', $hasAbout],
            ['journey', 'Journey', $hasJourney],
            ['skills', 'Skills', $hasSkills],
            ['portfolio', 'Work', $hasProjects],
            ['services', 'Services', $hasServices],
        ] as [$id, $label, $sectionEnabled]) {
            if ($sectionEnabled) {
                $navLinks[] = '<li><a href="#'.$id.'" class="nb-navlink" data-section-link="'.$id.'">'.$label.'</a></li>';
            }
        }

        $html = '<div class="nb-root">
    <header class="nb-header">
<div class="nb-progress" aria-hidden="true"></div>
<nav class="nb-nav" aria-label="Primary">
    <a href="#header" class="nb-brand" data-scroll-link>';
        $html .= '<img src="'.$logoImage.'" alt="'.$escape($logo).'" class="nb-logo-image">';
        $html .= '</a><ul class="nb-navlinks">'.implode('', $navLinks).'</ul>';
        if ($hasContact) {
            $html .= '<a href="#contact" class="nb-btn nb-btn-primary nb-nav-cta" data-scroll-link>Get in touch <span class="nb-arrow" aria-hidden="true">↗</span></a>';
        } else {
            $html .= '<span></span>';
        }
        $html .= '</nav>
    </header>';

        $heroBackground = $assetUrl($header['hero_background_image'] ?? null);
        $heroImage = $assetUrl($header['hero_image'] ?? null);
        $heroHasContent = array_filter($header, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
        if ($heroHasContent !== []) {
            $html .= '<section id="header" class="nb-hero" data-page-section>';
            if ($heroBackground !== '') {
                $html .= '<img src="'.$url($header['hero_background_image'], '').'" alt="" class="nb-hero-bg">';
            }
            $html .= '<div class="nb-container nb-hero-grid"><div class="nb-hero-copy">';
            if (! empty($header['hero_greeting'])) {
                $html .= '<p class="nb-chip" data-reveal><span class="nb-pulse" aria-hidden="true"></span>'.$escape($header['hero_greeting']).'</p>';
            }
            if ($heroName !== '') {
                $html .= '<h1 class="nb-display nb-hero-name" data-reveal>'.$escape($heroName).'</h1>';
            }
            if (! empty($header['hero_role'])) {
                $html .= '<p class="nb-display nb-hero-role" data-reveal>'.$escape($header['hero_role']).'</p>';
            }
            if (! empty($header['hero_description'])) {
                $html .= '<p class="nb-muted nb-hero-description" data-reveal>'.$escape($header['hero_description']).'</p>';
            }
            $primaryText = $header['hero_cta_primary_text'] ?? null;
            $primaryUrl = $header['hero_cta_primary_url'] ?? null;
            $secondaryText = $header['hero_cta_secondary_text'] ?? null;
            $secondaryUrl = $header['hero_cta_secondary_url'] ?? null;
            if ((! empty($primaryText) && ! empty($primaryUrl)) || (! empty($secondaryText) && ! empty($secondaryUrl))) {
                $html .= '<div class="nb-actions" data-reveal>';
                if (! empty($primaryText) && ! empty($primaryUrl)) {
                    $html .= '<a href="'.$url($primaryUrl).'" class="nb-btn nb-btn-primary" data-scroll-link>'.$escape($primaryText).'<span class="nb-arrow" aria-hidden="true">↗</span></a>';
                }
                if (! empty($secondaryText) && ! empty($secondaryUrl)) {
                    $html .= '<a href="'.$url($secondaryUrl).'" class="nb-btn nb-btn-ghost" data-scroll-link>'.$escape($secondaryText).'</a>';
                }
                $html .= '</div>';
            }
            if ($heroSocials !== []) {
                $html .= '<ul class="nb-icon-list" data-reveal>';
                foreach ($heroSocials as $social) {
                    $socialUrl = $social['url'] ?? $social['href'] ?? null;
                    $socialLabel = $social['label'] ?? $social['name'] ?? $social['platform'] ?? $socialUrl;
                    $html .= '<li><a href="'.$url($socialUrl).'" target="_blank" rel="noopener noreferrer" class="nb-icon-btn" aria-label="'.$escape($socialLabel).'" title="'.$escape($socialLabel).'">'.$escape(mb_substr((string) $socialLabel, 0, 1)).'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><figure class="nb-portrait" data-reveal><span class="nb-ring"></span><span class="nb-ring nb-ring-2"></span><span class="nb-disc"></span><div class="nb-photo">';
            if ($heroImage !== '') {
                $html .= '<img src="'.$url($header['hero_image'], '').'" alt="'.$escape($heroName).'" loading="lazy">';
            } elseif ($heroInitials !== '') {
                $html .= '<span class="nb-initials">'.$escape(mb_strtoupper($heroInitials)).'</span>';
            }
            $html .= '</div><span class="nb-orbit nb-orbit-a" aria-hidden="true">✳</span><span class="nb-orbit nb-orbit-b" aria-hidden="true">&lt;/&gt;</span><span class="nb-dot" aria-hidden="true"></span>';
            if ($location !== '') {
                $html .= '<figcaption class="nb-badge"><span aria-hidden="true">⌖</span>'.$escape($location).'</figcaption>';
            }
            $html .= '</figure></div></section>';
        }

        if ($hasAbout) {
            $aboutImage = $assetUrl($about['about_image'] ?? null);
            $html .= '<section id="about" class="nb-band" data-page-section><div class="nb-container nb-two-col nb-layout-about nb-section-padding">';
            if ($aboutImage !== '') {
                $html .= '<div class="nb-about-image" data-reveal><div class="nb-frame"><img src="'.$url($about['about_image'], '').'" alt="'.$escape($about['about_title'] ?? '').'" loading="lazy"></div></div>';
            }
            $html .= '<div class="nb-about-copy'.($aboutImage === '' ? ' nb-about-copy-wide' : '').'" data-reveal>';
            if (! empty($about['about_subtitle'])) {
                $html .= '<p class="nb-kicker">'.$escape($about['about_subtitle']).'</p>';
            }
            if (! empty($about['about_title'])) {
                $html .= '<h2 class="nb-display nb-h2">'.$escape($about['about_title']).'</h2>';
            }
            if (! empty($about['about_description'])) {
                $html .= '<p class="nb-muted nb-prose">'.$escape($about['about_description']).'</p>';
            }
            if ($highlights !== []) {
                $html .= '<ul class="nb-highlights">';
                foreach ($highlights as $highlight) {
                    $hlTitle = is_array($highlight) ? ($highlight['title'] ?? $highlight['label'] ?? $highlight['text'] ?? $highlight['name'] ?? '') : $highlight;
                    $hlDescription = is_array($highlight) ? ($highlight['description'] ?? '') : '';
                    if ($hlTitle === '' && $hlDescription === '') {
                        continue;
                    }
                    $html .= '<li><span class="nb-tick" aria-hidden="true">✓</span><span><strong>'.$escape($hlTitle).'</strong>';
                    if ($hlDescription !== '') {
                        $html .= '<span class="nb-muted nb-highlight-description">'.$escape($hlDescription).'</span>';
                    }
                    $html .= '</span></li>';
                }
                $html .= '</ul>';
            }
            $aboutCv = $assetUrl($about['about_cv_file'] ?? null);
            $aboutCtaText = $about['about_cta_text'] ?? null;
            $aboutCtaUrl = $about['about_cta_url'] ?? null;
            if ($aboutCv !== '' || (! empty($aboutCtaText) && ! empty($aboutCtaUrl))) {
                $html .= '<div class="nb-actions nb-about-actions" data-reveal>';
                if ($aboutCv !== '') {
                    $html .= '<a href="'.$url($about['about_cv_file'], '').'" download target="_blank" rel="noopener" class="nb-btn nb-btn-primary"><span aria-hidden="true">↓</span>'.$escape($aboutCtaText ?: 'Download CV').'</a>';
                }
                if (! empty($aboutCtaText) && ! empty($aboutCtaUrl)) {
                    $html .= '<a href="'.$url($aboutCtaUrl).'" class="nb-btn '.($aboutCv !== '' ? 'nb-btn-ghost' : 'nb-btn-primary').'" data-scroll-link>'.$escape($aboutCtaText).'<span class="nb-arrow" aria-hidden="true">↗</span></a>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></section>';
        }

        if ($hasJourney) {
            $html .= '<section id="journey" data-page-section><div class="nb-container nb-two-col nb-layout-journey nb-section-padding"><div class="nb-sticky-copy" data-reveal>';
            if (! empty($journey['journey_subtitle'])) {
                $html .= '<p class="nb-kicker">'.$escape($journey['journey_subtitle']).'</p>';
            }
            if (! empty($journey['journey_title'])) {
                $html .= '<h2 class="nb-display nb-h2">'.$escape($journey['journey_title']).'</h2>';
            }
            $html .= '</div><ol class="nb-timeline">';
            foreach ($journeyItems as $item) {
                $period = $item['period'] ?? '';
                $itemTitle = $item['title'] ?? '';
                $company = $item['company'] ?? $item['institution'] ?? '';
                $description = $item['description'] ?? '';
                if ($period === '' && $itemTitle === '' && $company === '' && $description === '') {
                    continue;
                }
                $html .= '<li class="nb-tl-item" data-reveal>';
                if ($period !== '') {
                    $html .= '<p class="nb-period">'.$escape($period).'</p>';
                }
                if ($itemTitle !== '') {
                    $html .= '<h3 class="nb-display nb-item-title">'.$escape($itemTitle).'</h3>';
                }
                if ($company !== '') {
                    $html .= '<p class="nb-company">'.$escape($company).'</p>';
                }
                if ($description !== '') {
                    $html .= '<p class="nb-muted nb-description">'.$escape($description).'</p>';
                }
                $html .= '</li>';
            }
            $html .= '</ol></div></section>';
        }

        if ($hasSkills) {
            $showIcons = $isEnabled($skillsData['skills_show_icon'] ?? null);
            $showLevels = $isEnabled($skillsData['skills_show_level'] ?? null);
            $html .= '<section id="skills" class="nb-band" data-page-section><div class="nb-container nb-two-col nb-layout-skills nb-section-padding"><div class="nb-sticky-copy" data-reveal>';
            if (! empty($skillsData['skills_subtitle'])) {
                $html .= '<p class="nb-kicker">'.$escape($skillsData['skills_subtitle']).'</p>';
            }
            if (! empty($skillsData['skills_title'])) {
                $html .= '<h2 class="nb-display nb-h2">'.$escape($skillsData['skills_title']).'</h2>';
            }
            if (! empty($skillsData['skills_description'])) {
                $html .= '<p class="nb-muted nb-prose">'.$escape($skillsData['skills_description']).'</p>';
            }
            $html .= '</div><ul class="nb-skills">';
            foreach ($skills as $index => $skill) {
                $name = is_string($skill) ? $skill : ($skill['name'] ?? $skill['title'] ?? $skill['label'] ?? '');
                $icon = is_array($skill) ? ($skill['icon'] ?? '') : '';
                $level = is_array($skill) ? ($skill['level'] ?? $skill['percent'] ?? null) : null;
                if (! is_scalar($name) || trim((string) $name) === '') {
                    continue;
                }
                $percent = null;
                $levelLabel = '';
                if ($showLevels && is_scalar($level) && trim((string) $level) !== '') {
                    $levelText = trim((string) $level);
                    if (preg_match('/^[\d.]+\s*%?$/', $levelText)) {
                        $number = (float) str_replace('%', '', $levelText);
                        $percent = max(0, min(100, $number <= 5 ? $number * 20 : $number));
                    } else {
                        $levelLabel = $levelText;
                    }
                }
                $skillLabel = (string) $name;
                $html .= '<li class="nb-skill" data-reveal><div class="nb-skill-heading">';
                if ($showIcons) {
                    $badge = is_string($icon) && $icon !== '' && mb_strlen($icon) <= 3 ? $icon : mb_strtoupper(mb_substr($skillLabel, 0, 1));
                    $html .= '<span class="nb-skill-tile">'.$escape($badge).'</span>';
                }
                $html .= '<h3>'.$escape($skillLabel).'</h3>';
                if ($percent !== null) {
                    $html .= '<span class="nb-level">'.$escape((string) round($percent)).'%</span>';
                } elseif ($levelLabel !== '') {
                    $html .= '<span class="nb-pill">'.$escape($levelLabel).'</span>';
                }
                $html .= '</div>';
                if ($percent !== null) {
                    $html .= '<div class="nb-track" role="progressbar" aria-valuenow="'.(int) round($percent).'" aria-valuemin="0" aria-valuemax="100" aria-label="'.$escape($skillLabel).'"><span class="nb-bar" style="--w:'.$percent.'%"></span></div>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></div></section>';
        }

        if ($hasProjects) {
            $featureFirst = $isEnabled($portfolio['projects_show_featured'] ?? null, false);
            $html .= '<section id="portfolio" data-page-section><span id="projects"></span><div class="nb-container nb-projects-section"><div class="nb-project-heading" data-reveal><div>';
            if (! empty($portfolio['projects_subtitle'])) {
                $html .= '<p class="nb-kicker">'.$escape($portfolio['projects_subtitle']).'</p>';
            }
            if (! empty($portfolio['projects_title'])) {
                $html .= '<h2 class="nb-display nb-h2">'.$escape($portfolio['projects_title']).'</h2>';
            }
            $html .= '</div>';
            if (! empty($portfolio['projects_description'])) {
                $html .= '<p class="nb-muted">'.$escape($portfolio['projects_description']).'</p>';
            }
            $html .= '</div><ul class="nb-project-grid">';
            foreach ($projects as $index => $project) {
                $projectTitle = is_string($project['title'] ?? null) ? $project['title'] : (is_string($project['name'] ?? null) ? $project['name'] : '');
                if ($projectTitle === '') {
                    continue;
                }
                $tags = [];
                $rawTags = $project['tags'] ?? [];
                if (is_array($rawTags)) {
                    foreach ($rawTags as $tag) {
                        $tagText = is_array($tag) ? ($tag['name'] ?? $tag['label'] ?? '') : $tag;
                        if (is_scalar($tagText) && trim((string) $tagText) !== '') {
                            $tags[] = trim((string) $tagText);
                        }
                    }
                } elseif (is_string($rawTags)) {
                    $tags = array_filter(array_map('trim', explode(',', $rawTags)));
                }
                if (! empty($project['category']) && ! in_array($project['category'], $tags, true)) {
                    $tags[] = $project['category'];
                }
                $image = $project['image'] ?? null;
                if (is_array($image)) {
                    $image = $image['url'] ?? $image['path'] ?? null;
                }
                $live = $project['live_url'] ?? $project['url'] ?? null;
                $repo = $project['repo_url'] ?? null;
                $featured = $featureFirst && $index === 0;
                $html .= '<li class="nb-project-item'.($featured ? ' nb-featured' : '').'" data-reveal><article class="nb-project"><'.(is_string($live) && $live !== '' ? 'a href="'.$url($live).'" target="_blank" rel="noopener noreferrer" aria-label="Open '.$escape($projectTitle).'"' : 'div').' class="nb-media'.($featured ? ' nb-media-featured' : '').'">';
                if (is_string($image) && $image !== '') {
                    $html .= '<img src="'.$url($image, '').'" alt="'.$escape($projectTitle).'" loading="lazy">';
                } else {
                    $html .= '<span class="nb-tone nb-tone-'.($index % 3).'">'.$escape(mb_strtoupper(mb_substr($projectTitle, 0, 1))).'</span>';
                }
                if ($featured) {
                    $html .= '<span class="nb-flag">Featured</span>';
                }
                if (is_string($live) && $live !== '') {
                    $html .= '<span class="nb-go" aria-hidden="true">↗</span>';
                }
                $html .= '</'.(is_string($live) && $live !== '' ? 'a' : 'div').'><div class="nb-project-copy"><h3 class="nb-display"><span class="nb-underline">'.$escape($projectTitle).'</span></h3>';
                if (! empty($project['description'])) {
                    $html .= '<p class="nb-muted">'.$escape($project['description']).'</p>';
                }
                if ($tags !== []) {
                    $html .= '<ul class="nb-tags">';
                    foreach ($tags as $tag) {
                        $html .= '<li class="nb-pill">'.$escape($tag).'</li>';
                    }
                    $html .= '</ul>';
                }
                if (is_string($repo) && $repo !== '') {
                    $html .= '<a href="'.$url($repo).'" target="_blank" rel="noopener noreferrer" class="nb-textlink"><span aria-hidden="true">&lt;/&gt;</span>Source code</a>';
                }
                $html .= '</div></article></li>';
            }
            $html .= '</ul></div></section>';
        }

        if ($hasServices) {
            $html .= '<section id="services" class="nb-band" data-page-section><div class="nb-container nb-services-section">';
            if (! empty($servicesData['services_subtitle'])) {
                $html .= '<p class="nb-kicker">'.$escape($servicesData['services_subtitle']).'</p>';
            }
            if (! empty($servicesData['services_title'])) {
                $html .= '<h2 class="nb-display nb-h2">'.$escape($servicesData['services_title']).'</h2>';
            }
            $html .= '<ul class="nb-service-list">';
            foreach ($services as $service) {
                $serviceTitle = is_string($service) ? $service : ($service['title'] ?? $service['name'] ?? $service['value'] ?? '');
                $description = is_array($service) ? ($service['description'] ?? '') : '';
                $icon = is_array($service) ? ($service['icon'] ?? '') : '';
                if (! is_scalar($serviceTitle) || trim((string) $serviceTitle) === '') {
                    continue;
                }
                $html .= '<li class="nb-service" data-reveal><span class="nb-skill-tile">'.$escape(is_string($icon) && $icon !== '' && mb_strlen($icon) <= 3 ? $icon : '✳').'</span><h3 class="nb-display">'.$escape($serviceTitle).'</h3>';
                if (is_string($description) && $description !== '') {
                    $html .= '<p class="nb-muted">'.$escape($description).'</p>';
                }
                $html .= '<span class="nb-service-arrow" aria-hidden="true">↗</span></li>';
            }
            $html .= '</ul></div></section>';
        }

        if ($hasContact) {
            $html .= '<section id="contact" class="nb-contact-section" data-page-section><div class="nb-contact" data-reveal><div class="nb-contact-grid"><div class="nb-contact-copy" data-reveal>';
            if (! empty($contact['contact_subtitle'])) {
                $html .= '<p class="nb-kicker">'.$escape($contact['contact_subtitle']).'</p>';
            }
            if (! empty($contact['contact_title'])) {
                $html .= '<h2 class="nb-display nb-contact-title">'.$escape($contact['contact_title']).'</h2>';
            }
            if (! empty($contact['contact_description'])) {
                $html .= '<p class="nb-contact-description">'.$escape($contact['contact_description']).'</p>';
            }
            if ($email !== '') {
                $html .= '<a href="'.$url('mailto:'.$email).'" class="nb-bigmail"><span aria-hidden="true">✉</span><span class="nb-underline-contact">'.$escape($email).'</span><span class="nb-arrow" aria-hidden="true">↗</span></a>';
            }
            if ($phone !== '' || $location !== '') {
                $html .= '<div class="nb-contact-details">';
                if ($phone !== '') {
                    $html .= '<a href="'.$url('tel:'.preg_replace('/[^+\d]/', '', $phone)).'" class="nb-contact-link"><span aria-hidden="true">⌕</span>'.$escape($phone).'</a>';
                }
                if ($location !== '') {
                    $html .= '<span><span aria-hidden="true">⌖</span>'.$escape($location).'</span>';
                }
                $html .= '</div>';
            }
            if ($contactSocials !== []) {
                $html .= '<ul class="nb-contact-socials">';
                foreach ($contactSocials as $social) {
                    $socialUrl = $social['url'] ?? $social['href'] ?? null;
                    $socialLabel = $social['label'] ?? $social['name'] ?? $social['platform'] ?? $socialUrl;
                    $html .= '<li><a href="'.$url($socialUrl).'" target="_blank" rel="noopener noreferrer" class="nb-social">'.$escape($socialLabel).'<span aria-hidden="true">↗</span></a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><div class="nb-form-column" data-reveal>';
            if ($formReady) {
                $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="nb-gform-frame" class="nb-form" data-google-form>
            <label><span>Name</span><input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="nb-input"></label>
            <label><span>Email</span><input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="nb-input"></label>
            <label><span>Message</span><textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required class="nb-input"></textarea></label>
            <button type="submit" class="nb-btn nb-btn-primary nb-submit">Send message <span class="nb-arrow" aria-hidden="true">➤</span></button>
            <p class="nb-status" role="status" data-form-status hidden></p>
        </form>';
            } elseif ($email !== '') {
                $html .= '<a href="'.$url('mailto:'.$email).'" class="nb-btn nb-btn-ghost nb-email-button">✉ '.$escape($email).'</a>';
            }
            $html .= '</div></div></div>';
            if ($heroName !== '' || $logo !== '') {
                $html .= '<footer class="nb-footer"><span>© '.date('Y').' '.$escape($heroName !== '' ? $heroName : $logo).'</span><a href="#header" class="nb-textlink" data-scroll-link>Back to top <span class="nb-arrow" aria-hidden="true">↗</span></a></footer>';
            }
            $html .= '</section>';
            if ($formReady) {
                $html .= '<iframe name="nb-gform-frame" title="Form submission target" class="nb-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
            }
        }
        $html .= '</div>';

        return '<!doctype html>
<html lang="en" data-template="cobalt-brutalist">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f6fa">
    <title>'.$escape($title).'</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    private function renderDarkOrangeDesignerPage(string $title, array $content, array $assets, ?array $googleForm): string
    {
        $escape = fn (mixed $value): string => e(is_scalar($value) ? (string) $value : '');
        $assetUrl = fn (mixed $value): string => $this->assetUrl($value, $assets);
        $url = fn (mixed $value, string $fallback = '#'): string => $escape($this->safeUrl($assetUrl($value), $fallback));
        $navbar = $this->sectionData($content['navbar'] ?? []);
        $hero = $this->sectionData($content['header'] ?? []);
        $about = $this->sectionData($content['about'] ?? []);
        $journey = $this->sectionData($content['journey'] ?? []);
        $skills = $this->sectionData($content['skills'] ?? []);
        $portfolio = $this->sectionData($content['portfolio'] ?? $content['projects'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);
        $projects = $this->items($portfolio['projects_list'] ?? $portfolio['projects'] ?? []);
        $journeyItems = $this->items($journey['journey_list'] ?? []);
        $skillItems = $this->items($skills['skills_list'] ?? []);
        $links = [['id' => 'about', 'label' => 'About']];
        if ($journeyItems !== []) {
            $links[] = ['id' => 'journey', 'label' => 'Journey'];
        }
        if ($skillItems !== []) {
            $links[] = ['id' => 'skills', 'label' => 'Skills'];
        }
        if ($projects !== []) {
            $links[] = ['id' => 'portfolio', 'label' => 'Work'];
        }
        $links[] = ['id' => 'contact', 'label' => 'Contact'];

        $navigation = '';
        foreach ($links as $link) {
            $navigation .= '<li><a href="#'.$escape($link['id']).'" class="nav-link" data-section-link="'.$escape($link['id']).'">'.$escape($link['label']).'</a></li>';
        }

        $brand = is_string($navbar['logo_text'] ?? null) ? $navbar['logo_text'] : '';
        $showLogo = ! in_array($navbar['show_logo'] ?? true, [false, 0, '0', 'false'], true);
        $logoImage = $url($navbar['logo_image'] ?? null, 'Logo.svg');
        $heroName = is_string($hero['hero_name'] ?? null) ? trim($hero['hero_name']) : '';
        $heroWords = $heroName !== '' ? preg_split('/\s+/', $heroName, -1, PREG_SPLIT_NO_EMPTY) : [];
        $heroImage = $assetUrl($hero['hero_image'] ?? null);
        $html = '<div class="theme min-h-screen overflow-x-hidden font-body">
    <div class="progress-track"><div class="progress-bar"></div></div>
    <nav class="nav" aria-label="Main navigation">
        <div class="nav-inner"><a href="#home" class="brand" aria-label="Back to top">';
        if ($showLogo) {
            $html .= '<img src="'.$logoImage.'" alt="'.$escape($brand).'" width="36" height="36" class="brand-mark">';
        }
        $html .= '</a><ul class="nav-links">'.$navigation.'</ul>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-navigation" aria-label="Toggle menu"><span class="burger"><i></i><i></i></span></button>
        </div>
        <ul class="mobile-navigation" id="mobile-navigation" hidden>'.$navigation.'</ul>
    </nav>
    <header id="home" data-section class="hero">
        <div class="aurora" aria-hidden="true"></div>
        <div class="hero-inner"><div class="hero-copy">';
        if (! empty($hero['hero_greeting'])) {
            $html .= '<p class="hero-line greeting" style="--i:0"><span class="pulse-dot"></span>'.$escape($hero['hero_greeting']).'</p>';
        }
        if ($heroWords !== []) {
            $html .= '<h1 class="font-display hero-title">';
            foreach ($heroWords as $index => $word) {
                $html .= '<span class="mask"><span class="mask-inner" style="--i:'.$index.'">'.$escape($word).'&nbsp;</span></span>';
            }
            $html .= '</h1>';
        }
        if (! empty($hero['hero_role'])) {
            $html .= '<p class="hero-line hero-role font-display" style="--i:'.(count($heroWords) + 2).'">'.$escape($hero['hero_role']).'</p>';
        }
        if (! empty($hero['hero_description'])) {
            $html .= '<p class="hero-line hero-description" style="--i:'.(count($heroWords) + 3).'">'.$escape($hero['hero_description']).'</p>';
        }
        if (! empty($hero['hero_cta_primary_text'])) {
            $html .= '<div class="hero-line hero-cta" style="--i:'.(count($heroWords) + 4).'"><a href="'.$url($hero['hero_cta_primary_url'] ?? null, '#portfolio').'" class="btn-primary">'.$escape($hero['hero_cta_primary_text']).'<span class="btn-arrow" aria-hidden="true">→</span></a></div>';
        }
        $heroFallback = $heroName !== '' ? mb_substr($heroName, 0, 1) : ($brand !== '' ? mb_substr($brand, 0, 1) : '•');
        $html .= '</div><div class="hero-portrait hero-line" style="--i:3"><div class="portrait-wrap"><div class="portrait">';
        if ($heroImage !== '') {
            $html .= '<img src="'.$url($hero['hero_image'], '').'" alt="'.$escape($heroName).'" class="portrait-image">';
        } else {
            $html .= '<span class="portrait-fallback font-display">'.$escape($heroFallback).'</span>';
        }
        $html .= '</div></div></div></div></header>';

        $aboutImage = $assetUrl($about['about_image'] ?? null);
        $html .= '<section id="about" data-section class="section"><div class="content-width about-grid">';
        if ($aboutImage !== '') {
            $html .= '<div class="about-media reveal"><div class="img-frame"><img src="'.$url($about['about_image'], '').'" alt="'.$escape($about['about_title'] ?? '').'" loading="lazy"></div></div>';
        }
        $html .= '<div class="about-copy'.($aboutImage === '' ? ' about-copy-wide' : '').'">';
        if (! empty($about['about_title'])) {
            $html .= '<h2 class="h2 reveal">'.$escape($about['about_title']).'</h2>';
        }
        if (! empty($about['about_description'])) {
            $html .= '<p class="about-description reveal" style="--d:100ms">'.$escape($about['about_description']).'</p>';
        }
        if (! empty($about['about_cv_file'])) {
            $html .= '<div class="reveal" style="--d:200ms"><a href="'.$url($about['about_cv_file']).'" class="btn-ghost">↓ <span>Download my CV</span></a></div>';
        }
        $html .= '</div></div></section>';

        if ($journeyItems !== []) {
            $html .= '<section id="journey" data-section class="section"><div class="content-width journey-content"><div class="section-intro">';
            if (! empty($journey['journey_title'])) {
                $html .= '<h2 class="h2 reveal">'.$escape($journey['journey_title']).'</h2>';
            }
            if (! empty($journey['journey_subtitle'])) {
                $html .= '<p class="section-description reveal" style="--d:100ms">'.$escape($journey['journey_subtitle']).'</p>';
            }
            $html .= '</div><ul class="journey-list">';
            foreach ($journeyItems as $index => $item) {
                $html .= '<li class="jr reveal'.($index === 0 ? ' open' : '').'" data-journey-item><button class="jr-head" type="button" aria-expanded="'.($index === 0 ? 'true' : 'false').'"><span class="jr-period">'.$escape($item['period'] ?? '').'</span><span class="jr-main"><span class="jr-title font-display">'.$escape($item['title'] ?? '').'</span>';
                if (! empty($item['company'])) {
                    $html .= '<span class="jr-company">'.$escape($item['company']).'</span>';
                }
                $html .= '</span><span class="jr-icon" aria-hidden="true"></span></button><div class="jr-body"><div>';
                if (! empty($item['description'])) {
                    $html .= '<p class="jr-desc">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></div></li>';
            }
            $html .= '</ul></div></section>';
        }

        if ($skillItems !== []) {
            $html .= '<section id="skills" data-section class="section"><div class="content-width"><div class="section-intro">';
            if (! empty($skills['skills_title'])) {
                $html .= '<h2 class="h2 reveal">'.$escape($skills['skills_title']).'</h2>';
            }
            if (! empty($skills['skills_description'])) {
                $html .= '<p class="section-description reveal" style="--d:100ms">'.$escape($skills['skills_description']).'</p>';
            }
            $html .= '</div><ul class="skills-grid">';
            foreach ($skillItems as $index => $skill) {
                $label = $skill['label'] ?? $skill['name'] ?? $skill['title'] ?? '';
                if (! is_scalar($label) || trim((string) $label) === '') {
                    continue;
                }
                $skillPercent = $skill['percent'] ?? null;
                $percent = is_numeric($skillPercent) && (float) $skillPercent !== 0.0 ? max(0, min(100, (float) $skillPercent)) : 100;
                $html .= '<li class="skill reveal" style="--d:'.(($index % 6) * 60).'ms"><div class="skill-head"><span class="font-display">'.$escape($label).'</span><span class="skill-percent">'.$escape((string) ($percent === 100 ? 100 : round($percent))).'%</span></div><div class="skill-track"><span class="skill-bar" style="--w:'.$percent.'%"></span></div></li>';
            }
            $html .= '</ul></div></section>';
        }

        if ($projects !== []) {
            $categories = array_values(array_unique(array_filter(array_map(
                static fn (array $project): string => is_string($project['category'] ?? null) ? $project['category'] : '',
                $projects
            ))));
            $html .= '<section id="portfolio" data-section class="section"><div class="content-width"><div class="work-heading"><div>';
            if (! empty($portfolio['projects_title'])) {
                $html .= '<h2 class="h2 reveal">'.$escape($portfolio['projects_title']).'</h2>';
            }
            if (! empty($portfolio['projects_subtitle'])) {
                $html .= '<p class="section-description reveal" style="--d:100ms">'.$escape($portfolio['projects_subtitle']).'</p>';
            }
            $html .= '</div>';
            if (count($categories) > 1) {
                $html .= '<div class="filters reveal" role="tablist" aria-label="Filter projects"><button type="button" class="chip active" data-project-filter="all" aria-selected="true">All</button>';
                foreach ($categories as $category) {
                    $html .= '<button type="button" class="chip" data-project-filter="'.$escape($category).'" aria-selected="false">'.$escape($category).'</button>';
                }
                $html .= '</div>';
            }
            $html .= '</div><div class="work-list" data-work-list>';
            foreach ($projects as $projectIndex => $project) {
                $category = is_string($project['category'] ?? null) ? $project['category'] : '';
                $projectUrl = $project['url'] ?? null;
                $image = $assetUrl($project['image'] ?? null);
                $html .= '<article class="work-row" data-project-index="'.$projectIndex.'" data-project-category="'.$escape($category).'"><'.(is_string($projectUrl) && $projectUrl !== '' ? 'a href="'.$url($projectUrl).'" target="_blank" rel="noopener noreferrer"' : 'div').' class="work-link">';
                if ($image !== '') {
                    $html .= '<img src="'.$url($project['image'], '').'" alt="'.$escape($project['title'] ?? '').'" class="work-thumb" loading="lazy">';
                }
                $html .= '<span class="work-title font-display">'.$escape($project['title'] ?? '').'</span><span class="work-meta">';
                if ($category !== '') {
                    $html .= '<span>'.$escape($category).'</span>';
                }
                if (is_string($projectUrl) && $projectUrl !== '') {
                    $html .= '<span class="work-arrow" aria-hidden="true">↗</span>';
                }
                $html .= '</span></'.(is_string($projectUrl) && $projectUrl !== '' ? 'a' : 'div').'></article>';
            }
            $html .= '</div><div class="work-preview" aria-hidden="true">';
            foreach ($projects as $projectIndex => $project) {
                $image = $assetUrl($project['image'] ?? null);
                if ($image !== '') {
                    $html .= '<img src="'.$url($project['image'], '').'" alt="" data-preview-index="'.$projectIndex.'">';
                }
            }
            $html .= '</div></div></section>';
        }

        $formEnabled = ($contact['contact_form_enabled'] ?? true) !== false;
        $formReady = $formEnabled && $googleForm !== null;
        $email = is_string($contact['contact_email'] ?? null) ? $contact['contact_email'] : '';
        $html .= '<section id="contact" data-section class="section"><div class="content-width contact-content">';
        if (! empty($contact['contact_title'])) {
            $html .= '<h2 class="h2 reveal">'.$escape($contact['contact_title']).'</h2>';
        }
        if (! empty($contact['contact_description'])) {
            $html .= '<p class="section-description reveal" style="--d:100ms">'.$escape($contact['contact_description']).'</p>';
        }
        if ($email !== '' || ! empty($contact['contact_phone']) || ! empty($contact['contact_location'])) {
            $html .= '<div class="contact-details reveal" style="--d:150ms">';
            if ($email !== '') {
                $html .= '<a href="'.$url('mailto:'.$email).'" class="contact-link">'.$escape($email).'</a>';
            }
            if (! empty($contact['contact_phone'])) {
                $phone = (string) $contact['contact_phone'];
                $html .= '<span>'.$escape($phone).'</span>';
            }
            if (! empty($contact['contact_location'])) {
                $html .= '<span>'.$escape($contact['contact_location']).'</span>';
            }
            $html .= '</div>';
        }
        if ($formReady) {
            $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="do-gform-frame" class="contact-form reveal" data-google-form style="--d:200ms">
                    <h3 class="font-display">Send a message</h3>
                    <label>Name<input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name"></label>
                    <label>Email<input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email"></label>
                    <label>Message<textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required></textarea></label>
                    <button type="submit" class="btn-primary">Send message <span class="btn-arrow" aria-hidden="true">→</span></button>
                    <p class="form-status" role="status" data-form-status hidden></p>
                </form><iframe name="do-gform-frame" title="Form submission target" class="hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
        }
        $html .= '</div></section>';
        $html .= '<footer class="footer"><div class="content-width footer-inner">';
        if ($brand !== '') {
            $html .= '<img src="Logo.svg" alt="'.$escape($brand).'" width="28" height="28" class="footer-brand">';
        }
        $html .= '<span><img src="Logo.svg" alt="" width="20" height="20"> Made with quickFolio</span><a href="#home">Back to top ↑</a></div></footer></div>';

        return '<!doctype html>
<html lang="en" data-template="dark-orange-designer">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f0e0d">
    <title>'.$escape($title).'</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    private function renderEmeraldMinimalistPage(string $title, array $content, array $assets, ?array $googleForm): string
    {
        $escape = fn (mixed $value): string => e(is_scalar($value) ? (string) $value : '');
        $assetUrl = fn (mixed $value): string => $this->assetUrl($value, $assets);
        $url = fn (mixed $value, string $fallback = '#'): string => $escape($this->safeUrl($assetUrl($value), $fallback));
        $navbar = $this->sectionData($content['navbar'] ?? []);
        $hero = $this->sectionData($content['header'] ?? []);
        $about = $this->sectionData($content['about'] ?? []);
        $journey = $this->sectionData($content['journey'] ?? []);
        $skills = $this->sectionData($content['skills'] ?? []);
        $portfolio = $this->sectionData($content['portfolio'] ?? $content['projects'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);
        $projects = $this->items($portfolio['projects_list'] ?? $portfolio['projects'] ?? []);
        $journeyItems = $this->items($journey['journey_list'] ?? []);
        $skillItems = $this->items($skills['skills_list'] ?? []);
        $categories = array_values(array_unique(array_filter(array_map(
            static fn (array $project): string => is_string($project['category'] ?? null) ? $project['category'] : '',
            $projects
        ))));
        $showFilters = count($categories) > 1;
        $projectFilters = $showFilters ? array_merge(['All'], $categories) : [];
        $logo = is_string($navbar['logo_text'] ?? null) ? $navbar['logo_text'] : '';
        $showLogo = ! in_array($navbar['show_logo'] ?? true, [false, 0, '0', 'false'], true);
        $logoImage = $url($navbar['logo_image'] ?? null, 'Logo.svg');
        $heroName = is_string($hero['hero_name'] ?? null) ? trim($hero['hero_name']) : '';
        $heroWords = $heroName !== '' ? preg_split('/\s+/', $heroName, -1, PREG_SPLIT_NO_EMPTY) : [];
        $heroImage = $assetUrl($hero['hero_image'] ?? null);
        $aboutImage = $assetUrl($about['about_image'] ?? null);
        $formEnabled = ! in_array($contact['contact_form_enabled'] ?? true, [false, 0, '0', 'false'], true);
        $formReady = $formEnabled && $googleForm !== null;
        $skillLabels = [];
        foreach ($skillItems as $skill) {
            $label = $skill['label'] ?? null;
            if (is_string($label) && trim($label) !== '') {
                $skillLabels[] = trim($label);
            }
        }
        $marqueeItems = $skillLabels;
        while ($marqueeItems !== [] && count($marqueeItems) < 8) {
            $marqueeItems = array_merge($marqueeItems, $skillLabels);
        }
        $navLinks = [['id' => 'home', 'label' => 'Home']];
        if (! empty($about['about_title']) || ! empty($about['about_description'])) {
            $navLinks[] = ['id' => 'about', 'label' => 'About'];
        }
        if ($journeyItems !== []) {
            $navLinks[] = ['id' => 'journey', 'label' => 'Experience'];
        }
        if ($skillItems !== []) {
            $navLinks[] = ['id' => 'skills', 'label' => 'Skills'];
        }
        if ($projects !== []) {
            $navLinks[] = ['id' => 'portfolio', 'label' => 'Projects'];
        }
        $navLinks[] = ['id' => 'contact', 'label' => 'Contact'];
        $desktopNav = '';
        $mobileNav = '';
        foreach ($navLinks as $link) {
            $desktopNav .= '<a href="#'.$escape($link['id']).'" class="em-nav-link">'.$escape($link['label']).'</a>';
            $mobileNav .= '<a href="#'.$escape($link['id']).'" class="em-mobile-link">'.$escape($link['label']).'</a>';
        }

        $html = '<div class="folio">
    <div class="em-progress-track"><div class="em-progress-bar"></div></div>
    <nav class="em-navbar" aria-label="Main navigation">
        <div class="em-nav-inner">
            <a href="#home" class="em-brand" aria-label="Back to top">';
        if ($showLogo) {
            $html .= '<img src="'.$logoImage.'" alt="'.$escape($logo).'" width="40" height="40" class="em-logo-mark">';
        } else {
            $html .= '<span></span>';
        }
        $html .= '</a><div class="em-desktop-nav">'.$desktopNav.'</div>
            <button class="em-menu-toggle" type="button" aria-expanded="false" aria-controls="em-mobile-nav" aria-label="Toggle menu"><span class="em-burger"><i></i><i></i><i></i></span></button>
        </div>
        <div class="em-mobile-nav" id="em-mobile-nav" hidden>'.$mobileNav.'</div>
    </nav>
    <header id="home" class="em-hero">
        <div class="em-hero-bg"></div><div class="em-orb em-orb-a"></div><div class="em-orb em-orb-b"></div><div class="em-orb em-orb-c"></div><div class="em-hero-grid"></div>
        <div class="em-container em-hero-layout"><div class="em-hero-copy">';
        if (! empty($hero['hero_greeting'])) {
            $html .= '<p class="em-hero-in em-greeting" style="--delay:.1s"><span class="em-ping"><i></i><i></i></span>'.$escape($hero['hero_greeting']).'</p>';
        }
        if ($heroWords !== []) {
            $html .= '<h1 class="display em-hero-title">';
            foreach ($heroWords as $index => $word) {
                $html .= '<span class="em-word-mask"><span class="em-word" style="--delay:'.(.25 + $index * .12).'s">'.$escape($word).'&nbsp;</span></span>';
            }
            $html .= '</h1>';
        }
        if (! empty($hero['hero_role'])) {
            $html .= '<p class="em-hero-in display em-hero-role" style="--delay:.7s">'.$escape($hero['hero_role']).'</p>';
        }
        if (! empty($hero['hero_description'])) {
            $html .= '<p class="em-hero-in em-hero-description" style="--delay:.85s">'.$escape($hero['hero_description']).'</p>';
        }
        if (! empty($hero['hero_cta_primary_text'])) {
            $html .= '<div class="em-hero-in em-hero-cta" style="--delay:1s"><a href="'.$url($hero['hero_cta_primary_url'] ?? null, '#portfolio').'" class="em-cta">'.$escape($hero['hero_cta_primary_text']).'<span aria-hidden="true">→</span></a></div>';
        }
        $heroFallback = $heroName !== '' ? mb_substr($heroName, 0, 1) : '';
        $html .= '</div><div class="em-hero-portrait em-hero-in" style="--delay:.4s"><div class="em-portrait-wrap"><span class="em-ring em-ring-1"></span><span class="em-ring em-ring-2"></span><div class="em-blob">';
        if ($heroImage !== '') {
            $html .= '<img src="'.$url($hero['hero_image'], '').'" alt="'.$escape($heroName).'">';
        } elseif ($heroFallback !== '') {
            $html .= '<div class="em-hero-fallback"><span aria-hidden="true">🌿</span><span>Emerald Portfolio</span></div>';
        }
        $html .= '</div><span class="em-dot em-dot-1"></span><span class="em-dot em-dot-2"></span><span class="em-dot em-dot-3"></span></div></div></div>
        <a href="#about" class="em-scroll-cue" aria-label="Scroll down"><span><i></i></span></a>
    </header>';

        if ($marqueeItems !== []) {
            $marqueeItems = array_merge($marqueeItems, $marqueeItems);
            $html .= '<div class="em-marquee" aria-hidden="true"><div class="em-marquee-track">';
            foreach ($marqueeItems as $label) {
                $html .= '<span class="display">'.$escape($label).'<b>✦</b></span>';
            }
            $html .= '</div></div>';
        }

        $html .= '<section id="about" class="em-about em-section"><div class="em-orb em-orb-b em-opacity-50"></div><div class="em-hero-grid"></div><div class="em-container em-about-layout">';
        if ($aboutImage !== '') {
            $html .= '<div class="em-reveal em-reveal-left em-about-image"><div class="em-about-glow"></div><div class="em-about-frame"><img src="'.$url($about['about_image'], '').'" alt="'.$escape($about['about_title'] ?? '').'" loading="lazy"></div></div>';
        }
        $html .= '<div class="em-reveal em-about-copy'.($aboutImage === '' ? ' em-about-copy-wide' : '').'"><span class="em-section-tag">About me</span>';
        if (! empty($about['about_title'])) {
            $html .= '<h2 class="display em-section-title">'.$escape($about['about_title']).'</h2>';
        }
        if (! empty($about['about_description'])) {
            $html .= '<p class="em-about-description">'.$escape($about['about_description']).'</p>';
        }
        if (! empty($about['about_cv_file'])) {
            $html .= '<div class="em-about-download"><a href="'.$url($about['about_cv_file']).'" class="em-cta">Download CV / Resume <span aria-hidden="true">↓</span></a></div>';
        }
        $html .= '</div></div></section>';

        if ($journeyItems !== []) {
            $html .= '<section id="journey" class="em-journey em-section"><div class="em-orb em-orb-a em-opacity-30"></div><div class="em-hero-grid"></div><div class="em-container em-journey-content"><div class="em-reveal em-section-intro"><span class="em-section-tag">Experience</span>';
            if (! empty($journey['journey_title'])) {
                $html .= '<h2 class="display em-section-title">'.$escape($journey['journey_title']).'</h2>';
            }
            if (! empty($journey['journey_subtitle'])) {
                $html .= '<p class="em-section-subtitle">'.$escape($journey['journey_subtitle']).'</p>';
            }
            $html .= '</div><div class="em-timeline"><div class="em-spine em-reveal"></div><div class="em-timeline-items">';
            foreach ($journeyItems as $index => $item) {
                $html .= '<article class="em-reveal em-timeline-item" style="--reveal-delay:'.(($index % 3) * 100).'ms"><span class="em-timeline-marker"><i></i></span><div class="em-journey-card">';
                $html .= '<div class="em-journey-meta">';
                if (! empty($item['period'])) {
                    $html .= '<span class="em-period">'.$escape($item['period']).'</span>';
                }
                if (! empty($item['company'])) {
                    $html .= '<span class="em-company">'.$escape($item['company']).'</span>';
                }
                $html .= '</div>';
                if (! empty($item['title'])) {
                    $html .= '<h3 class="display em-journey-title">'.$escape($item['title']).'</h3>';
                }
                if (! empty($item['description'])) {
                    $html .= '<p class="em-journey-description">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></article>';
            }
            $html .= '</div></div></div></section>';
        }

        if ($skillItems !== []) {
            $html .= '<section id="skills" class="em-skills em-section"><div class="em-orb em-orb-b em-opacity-60"></div><div class="em-hero-grid"></div><div class="em-container em-skills-content"><div class="em-reveal em-section-intro"><span class="em-section-tag">Expertise</span>';
            if (! empty($skills['skills_title'])) {
                $html .= '<h2 class="display em-section-title">'.$escape($skills['skills_title']).'</h2>';
            }
            if (! empty($skills['skills_description'])) {
                $html .= '<p class="em-section-subtitle">'.$escape($skills['skills_description']).'</p>';
            }
            $html .= '</div><div class="em-skills-grid">';
            $circumference = 2 * M_PI * 52;
            foreach ($skillItems as $index => $skill) {
                $label = is_string($skill['label'] ?? null) ? $skill['label'] : '';
                if ($label === '') {
                    continue;
                }
                $percent = is_numeric($skill['percent'] ?? null) && (float) $skill['percent'] !== 0.0
                    ? max(0, min(100, (float) $skill['percent']))
                    : 100;
                $dashOffset = $circumference * (1 - $percent / 100);
                $delay = .25 + $index * .11;
                $html .= '<article class="em-reveal em-skill-card" style="--skill-delay:'.$delay.'s"><div class="em-meter"><span class="em-halo"></span><svg viewBox="0 0 120 120" aria-hidden="true"><defs><linearGradient id="em-skill-gradient-'.$index.'" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#00A388"/><stop offset="100%" stop-color="#9BF0D2"/></linearGradient></defs><circle class="em-orbit" cx="60" cy="60" r="58" fill="none" stroke="rgba(155,240,210,.3)" stroke-width=".8" stroke-dasharray="1.5 5"/><g transform="rotate(-90 60 60)"><circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="8"/><circle class="em-arc" cx="60" cy="60" r="52" fill="none" stroke="url(#em-skill-gradient-'.$index.')" stroke-width="8" stroke-linecap="round" stroke-dasharray="'.$circumference.'" style="--circ:'.$circumference.';--offset:'.$dashOffset.';--degrees:'.($percent * 3.6).'deg"/><g class="em-tip" style="--degrees:'.($percent * 3.6).'deg"><circle cx="112" cy="60" r="8" fill="rgba(92,240,189,.25)"/><circle cx="112" cy="60" r="4" fill="#fff"/></g></g></svg><span class="em-skill-count" data-target="'.(int) round($percent).'">0<span>%</span></span></div><span class="display em-skill-label">'.$escape($label).'</span></article>';
            }
            $html .= '</div></div></section>';
        }

        if ($projects !== []) {
            $html .= '<section id="portfolio" class="em-projects em-section"><div class="em-orb em-orb-a em-opacity-25"></div><div class="em-hero-grid"></div><div class="em-container em-projects-content"><div class="em-reveal em-section-intro"><span class="em-section-tag">Selected projects</span>';
            if (! empty($portfolio['projects_title'])) {
                $html .= '<h2 class="display em-section-title">'.$escape($portfolio['projects_title']).'</h2>';
            }
            if (! empty($portfolio['projects_subtitle'])) {
                $html .= '<p class="em-section-subtitle">'.$escape($portfolio['projects_subtitle']).'</p>';
            }
            $html .= '</div>';
            if ($showFilters) {
                $html .= '<div class="em-reveal em-filters" role="group" aria-label="Filter projects">';
                foreach ($projectFilters as $filter) {
                    $html .= '<button type="button" class="em-filter'.($filter === 'All' ? ' active' : '').'" data-project-filter="'.$escape($filter).'">'.$escape($filter).'</button>';
                }
                $html .= '</div>';
            }
            $html .= '<div class="em-project-grid">';
            foreach ($projects as $project) {
                $category = is_string($project['category'] ?? null) ? $project['category'] : '';
                $image = $assetUrl($project['image'] ?? null);
                $projectUrl = $project['url'] ?? null;
                $html .= '<article class="em-project-card" data-project-category="'.$escape($category).'"><div class="em-project-image">';
                if ($image !== '') {
                    $html .= '<img src="'.$url($project['image'], '').'" alt="'.$escape($project['title'] ?? '').'" loading="lazy">';
                } else {
                    $html .= '<span class="em-project-fallback" aria-hidden="true">📂</span>';
                }
                if (is_string($projectUrl) && $projectUrl !== '') {
                    $html .= '<div class="em-project-overlay"><a href="'.$url($projectUrl).'" target="_blank" rel="noopener noreferrer">View project <span aria-hidden="true">↗</span></a></div>';
                }
                $html .= '</div><div class="em-project-copy">';
                if ($category !== '') {
                    $html .= '<span class="em-project-category">'.$escape($category).'</span>';
                }
                if (! empty($project['title'])) {
                    $html .= '<h3 class="display">'.$escape($project['title']).'</h3>';
                }
                $html .= '</div></article>';
            }
            $html .= '</div></div></section>';
        }

        $html .= '<section id="contact" class="em-contact em-section"><div class="em-orb em-orb-b em-opacity-50"></div><div class="em-hero-grid"></div><div class="em-container em-contact-container"><div class="em-reveal em-contact-card"><div class="em-orb em-orb-a em-opacity-40"></div><div class="em-hero-grid"></div><div class="em-contact-heading"><span class="em-section-tag">Get in touch</span>';
        if (! empty($contact['contact_title'])) {
            $html .= '<h2 class="display em-section-title">'.$escape($contact['contact_title']).'</h2>';
        }
        if (! empty($contact['contact_description'])) {
            $html .= '<p class="em-contact-description">'.$escape($contact['contact_description']).'</p>';
        }
        $html .= '</div>';
        $email = is_string($contact['contact_email'] ?? null) ? $contact['contact_email'] : '';
        if ($email !== '') {
            $html .= '<a href="'.$escape($this->safeUrl('mailto:'.$email)).'" class="em-email-link">'.$escape($email).'<span aria-hidden="true">↗</span></a>';
        }
        $html .= '<div class="em-contact-meta">';
        if (! empty($contact['contact_phone'])) {
            $html .= '<span>Phone: '.$escape($contact['contact_phone']).'</span>';
        }
        if (! empty($contact['contact_location'])) {
            $html .= '<span>Location: '.$escape($contact['contact_location']).'</span>';
        }
        if ($formReady) {
            $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="em-gform-frame" class="em-contact-form" data-google-form>
                <h3 class="display">Send a message</h3>
                <label>Name<input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name"></label>
                <label>Email<input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email"></label>
                <label>Message<textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required></textarea></label>
                <button type="submit" class="em-cta">Send message <span aria-hidden="true">→</span></button>
                <p class="em-form-status" role="status" data-form-status hidden></p>
            </form>';
        }
        $html .= '</div></div></div>';
        if ($formReady) {
            $html .= '<iframe name="em-gform-frame" title="Form submission target" class="em-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
        }
        $html .= '</section><footer class="em-footer"><div class="em-container">';
        if ($logo !== '') {
            $html .= '<span class="display">'.$escape($logo).'</span>';
        }
        $html .= '<p><img src="Logo.svg" alt="" width="20" height="20"> Built with quickFolio · Greenish Edition</p></div></footer></div>';

        return '<!doctype html>
<html lang="en" data-template="emerald-minimalist">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#06231e">
    <title>'.$escape($title).'</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    private function renderIvoryEditorialPage(string $title, array $content, array $assets, ?array $googleForm): string
    {
        $escape = fn (mixed $value): string => e(is_scalar($value) ? (string) $value : '');
        $assetUrl = fn (mixed $value): string => $this->assetUrl($value, $assets);
        $url = fn (mixed $value, string $fallback = '#'): string => $escape($this->safeUrl($assetUrl($value), $fallback));
        $section = fn (string $key): array => $this->sectionData($content[$key] ?? []);
        $hasContent = function (mixed $value) use (&$hasContent): bool {
            if (is_string($value)) {
                return trim($value) !== '';
            }
            if (is_array($value)) {
                foreach ($value as $child) {
                    if ($hasContent($child)) {
                        return true;
                    }
                }
            }

            return false;
        };
        $navbar = $section('navbar');
        $hero = $section('header');
        $about = $section('about');
        $journey = $section('journey');
        $skills = $section('skills');
        $portfolio = $section('portfolio');
        if ($portfolio === []) {
            $portfolio = $section('projects');
        }
        $contact = $section('contact');
        $journeyItems = array_values(array_filter($this->items($journey['journey_list'] ?? []), static fn (array $item): bool => ! empty($item['title']) || ! empty($item['role']) || ! empty($item['company']) || ! empty($item['institution']) || ! empty($item['period']) || ! empty($item['description'])
        ));
        $skillItems = [];
        foreach ($this->items($skills['skills_list'] ?? []) as $skill) {
            $name = $skill['label'] ?? $skill['name'] ?? $skill['title'] ?? null;
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            $level = is_scalar($skill['percent'] ?? $skill['level'] ?? null) ? trim((string) ($skill['percent'] ?? $skill['level'])) : '';
            $percent = null;
            $label = null;
            if ($level !== '' && preg_match('/^[\d.]+\s*%?$/', $level) === 1) {
                $number = (float) $level;
                $percent = max(0, min(100, $number <= 5 ? $number * 20 : $number));
            } elseif ($level !== '') {
                $label = $level;
            }
            $skillItems[] = ['name' => trim($name), 'percent' => $percent, 'label' => $label];
        }
        $projects = [];
        foreach ($this->items($portfolio['projects_list'] ?? $portfolio['projects'] ?? []) as $project) {
            $projectTitle = $project['title'] ?? $project['name'] ?? null;
            if (! is_string($projectTitle) || trim($projectTitle) === '') {
                continue;
            }
            $image = $project['image'] ?? null;
            if (is_array($image)) {
                $image = $image['url'] ?? $image['path'] ?? null;
            }
            $projects[] = [
                'title' => trim($projectTitle),
                'category' => $project['category'] ?? '',
                'description' => $project['description'] ?? '',
                'image' => $image,
                'url' => $project['live_url'] ?? $project['url'] ?? '',
                'repo' => $project['repo_url'] ?? '',
            ];
        }
        $normalizeSocials = function (mixed $value): array {
            $socials = [];
            foreach (is_array($value) ? $value : [] as $item) {
                if (is_string($item)) {
                    $href = trim($item);
                    $host = parse_url($href, PHP_URL_HOST);
                    $label = is_string($host) ? preg_replace('/^www\./', '', $host) : $href;
                } else {
                    $href = is_string($item['url'] ?? $item['href'] ?? null) ? trim($item['url'] ?? $item['href']) : '';
                    $platform = is_string($item['platform'] ?? $item['label'] ?? $item['name'] ?? null)
                        ? trim($item['platform'] ?? $item['label'] ?? $item['name'])
                        : '';
                    $key = strtolower(preg_replace('/[^a-z]/i', '', $platform));
                    $known = [
                        'behance' => 'Behance', 'dribbble' => 'Dribbble', 'email' => 'Email',
                        'facebook' => 'Facebook', 'github' => 'GitHub', 'instagram' => 'Instagram',
                        'linkedin' => 'LinkedIn', 'website' => 'Website', 'x' => 'X',
                        'twitter' => 'X', 'youtube' => 'YouTube',
                    ];
                    $host = parse_url($href, PHP_URL_HOST);
                    $label = is_string($item['label'] ?? $item['name'] ?? null)
                        ? trim($item['label'] ?? $item['name'])
                        : ($known[$key] ?? (is_string($host) ? preg_replace('/^www\./', '', $host) : $href));
                }
                if ($href !== '') {
                    $socials[] = ['url' => $href, 'label' => $label];
                }
            }

            return $socials;
        };
        $heroSocials = $normalizeSocials($hero['hero_social_links'] ?? []);
        $contactSocials = $normalizeSocials($contact['contact_social_links'] ?? []);
        if ($contactSocials === []) {
            $contactSocials = $heroSocials;
        }
        $logoText = is_string($navbar['logo_text'] ?? null) && trim($navbar['logo_text']) !== ''
            ? trim($navbar['logo_text'])
            : (is_string($hero['hero_name'] ?? null) ? trim($hero['hero_name']) : '');
        $showLogo = ! in_array($navbar['show_logo'] ?? true, [false, 0, '0', 'false'], true);
        $logoImage = $showLogo
            ? $this->safeUrl($assetUrl($navbar['logo_image'] ?? null), 'Logo.svg')
            : '';
        $heroName = is_string($hero['hero_name'] ?? null) ? trim($hero['hero_name']) : '';
        $heroWords = $heroName !== '' ? preg_split('/\s+/', $heroName, -1, PREG_SPLIT_NO_EMPTY) : [];
        $heroInitials = '';
        foreach (array_slice($heroWords, 0, 2) as $word) {
            $heroInitials .= mb_substr($word, 0, 1);
        }
        $formEnabled = ! in_array($contact['contact_form_enabled'] ?? true, [false, 0, '0', 'false'], true);
        $formReady = $formEnabled && $googleForm !== null;
        $showAbout = $hasContent($about);
        $showJourney = $journeyItems !== [];
        $showSkills = $hasContent($skills) || $skillItems !== [];
        $showProjects = $hasContent($portfolio) || $projects !== [];
        $showContact = $hasContent($contact) || $formReady;
        $navLinks = [];
        foreach ([
            ['about', 'About', $showAbout],
            ['journey', 'Journey', $showJourney],
            ['skills', 'Skills', $showSkills],
            ['portfolio', 'Work', $showProjects],
        ] as [$id, $label, $visible]) {
            if ($visible) {
                $navLinks[] = ['id' => $id, 'label' => $label];
            }
        }
        $navigation = '';
        foreach ($navLinks as $link) {
            $navigation .= '<li><a href="#'.$escape($link['id']).'" class="ie-navlink" data-nav-link="'.$escape($link['id']).'">'.$escape($link['label']).'</a></li>';
        }

        $html = '<div class="ie-root">
    <div class="ie-glow" aria-hidden="true"></div>
    <header class="ie-navwrap" data-nav-wrap>
        <div class="ie-progress" aria-hidden="true"></div>
        <nav class="ie-nav" aria-label="Primary">';
        if ($logoImage !== '') {
            $html .= '<a class="ie-brand" href="#home">';
            $html .= '<img src="'.$escape($logoImage).'" alt="'.$escape($logoText).'" class="ie-logo-image">';
            $html .= '</a>';
        } else {
            $html .= '<span></span>';
        }
        $html .= '<ul class="ie-navlinks">'.$navigation.'</ul>';
        if ($showContact) {
            $html .= '<a href="#contact" class="ie-btn ie-btn-ink ie-nav-cta">Let\'s talk <span aria-hidden="true">↗</span></a>';
        } else {
            $html .= '<span></span>';
        }
        $html .= '</nav></header><main>';

        if ($hasContent($hero)) {
            $html .= '<section id="home" class="ie-hero"><div class="ie-rings" aria-hidden="true"><span></span><span></span><span></span></div><div class="ie-hero-copy">';
            if (! empty($hero['hero_greeting'])) {
                $html .= '<p data-reveal class="ie-chip"><i class="ie-pulse"></i>'.$escape($hero['hero_greeting']).'</p>';
            }
            if ($heroWords !== []) {
                $html .= '<h1 class="ie-serif ie-hero-name"><span class="ie-sr-only">'.$escape($heroName).'</span>';
                foreach ($heroWords as $index => $word) {
                    $html .= '<span class="ie-mask" aria-hidden="true"><span class="ie-word" style="--index:'.$index.'">'.$escape($word).'</span></span>';
                }
                $html .= '</h1>';
            }
            if (! empty($hero['hero_role'])) {
                $html .= '<p data-reveal class="ie-serif ie-hero-role">'.$escape($hero['hero_role']).'</p>';
            }
            if (! empty($hero['hero_description'])) {
                $html .= '<p data-reveal class="ie-hero-description">'.$escape($hero['hero_description']).'</p>';
            }
            $primaryText = $hero['hero_cta_primary_text'] ?? '';
            $primaryUrl = $hero['hero_cta_primary_url'] ?? '';
            $secondaryText = $hero['hero_cta_secondary_text'] ?? '';
            $secondaryUrl = $hero['hero_cta_secondary_url'] ?? '';
            if (($primaryText !== '' && $primaryUrl !== '') || ($secondaryText !== '' && $secondaryUrl !== '')) {
                $html .= '<div data-reveal class="ie-hero-ctas">';
                if ($primaryText !== '' && $primaryUrl !== '') {
                    $html .= '<a href="'.$url($primaryUrl).'" class="ie-btn ie-btn-accent">'.$escape($primaryText).'<span aria-hidden="true">↗</span></a>';
                }
                if ($secondaryText !== '' && $secondaryUrl !== '') {
                    $html .= '<a href="'.$url($secondaryUrl).'" class="ie-btn ie-btn-ghost">'.$escape($secondaryText).'</a>';
                }
                $html .= '</div>';
            }
            if ($heroSocials !== []) {
                $html .= '<ul data-reveal class="ie-social-list">';
                foreach ($heroSocials as $social) {
                    $html .= '<li><a href="'.$url($social['url']).'" target="_blank" rel="noopener noreferrer" class="ie-ulink">'.$escape($social['label']).'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><figure data-reveal class="ie-portrait"><div class="ie-outline"></div><div class="ie-portrait-img">';
            $heroImage = $assetUrl($hero['hero_image'] ?? null);
            if ($heroImage !== '') {
                $html .= '<img src="'.$url($hero['hero_image'], '').'" alt="'.$escape($heroName).'" class="ie-hero-img">';
            } elseif ($heroInitials !== '') {
                $html .= '<span class="ie-serif ie-initials">'.$escape(mb_strtoupper($heroInitials)).'</span>';
            }
            $html .= '</div></figure>';
            if ($showAbout) {
                $html .= '<a href="#about" class="ie-cue"><span class="ie-cue-line"></span><span>Scroll</span><span aria-hidden="true">↓</span></a>';
            }
            $html .= '</section>';
        }

        if ($showAbout) {
            $aboutImage = $assetUrl($about['about_image'] ?? null);
            $description = is_string($about['about_description'] ?? null) ? trim($about['about_description']) : '';
            $aboutWords = $description !== '' ? preg_split('/\s+/', $description, -1, PREG_SPLIT_NO_EMPTY) : [];
            $highlights = [];
            foreach (is_array($about['about_highlights'] ?? null) ? $about['about_highlights'] : [] as $highlight) {
                $label = is_string($highlight) ? $highlight : ($highlight['title'] ?? $highlight['label'] ?? $highlight['text'] ?? $highlight['name'] ?? '');
                if (is_string($label) && trim($label) !== '') {
                    $highlights[] = trim($label);
                }
            }
            $html .= '<section id="about" class="ie-about"><div class="ie-about-layout">';
            if ($aboutImage !== '') {
                $html .= '<div data-reveal class="ie-about-image"><div class="ie-outline"></div><div class="ie-media"><img src="'.$url($about['about_image'], '').'" alt="'.$escape($about['about_title'] ?? '').'" loading="lazy"></div></div>';
            }
            $html .= '<div><div data-reveal>';
            $aboutKicker = $about['about_title'] ?? $about['about_subtitle'] ?? '';
            if ($aboutKicker !== '') {
                $html .= '<p class="ie-kicker">'.$escape($aboutKicker).'</p>';
            }
            if ($aboutWords !== []) {
                $html .= '<p class="ie-serif ie-lead">';
                foreach ($aboutWords as $index => $word) {
                    $html .= '<span class="ie-about-word" style="--word-index:'.min($index, 70).'">'.$escape($word).' </span>';
                }
                $html .= '</p>';
            }
            $html .= '</div>';
            if ($highlights !== []) {
                $html .= '<ul data-reveal class="ie-highlights">';
                foreach ($highlights as $index => $highlight) {
                    $html .= '<li style="--item-index:'.$index.'"><span class="ie-tick">✓</span>'.$escape($highlight).'</li>';
                }
                $html .= '</ul>';
            }
            $cvUrl = $assetUrl($about['about_cv_file'] ?? null);
            $aboutCtaText = $about['about_cta_text'] ?? '';
            $aboutCtaUrl = $about['about_cta_url'] ?? '';
            if ($cvUrl !== '' || ($aboutCtaText !== '' && $aboutCtaUrl !== '')) {
                $html .= '<div data-reveal class="ie-about-actions">';
                if ($cvUrl !== '') {
                    $html .= '<a href="'.$url($about['about_cv_file']).'" download target="_blank" rel="noopener" class="ie-btn ie-btn-accent"><span aria-hidden="true">↓</span>'.$escape($aboutCtaText !== '' ? $aboutCtaText : 'Download CV').'</a>';
                }
                if ($aboutCtaText !== '' && $aboutCtaUrl !== '' && $cvUrl !== '') {
                    $html .= '<a href="'.$url($aboutCtaUrl).'" class="ie-btn ie-btn-ghost">'.$escape($aboutCtaText).'</a>';
                } elseif ($aboutCtaText !== '' && $aboutCtaUrl !== '') {
                    $html .= '<a href="'.$url($aboutCtaUrl).'" class="ie-btn ie-btn-accent">'.$escape($aboutCtaText).'<span aria-hidden="true">↗</span></a>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></section>';
        }

        if ($showJourney) {
            $html .= '<section id="journey" class="ie-journey"><div class="ie-section-inner"><div data-reveal class="ie-section-heading">';
            if (! empty($journey['journey_title'])) {
                $html .= '<h2 class="ie-serif ie-h2">'.$escape($journey['journey_title']).'</h2>';
            }
            if (! empty($journey['journey_subtitle'])) {
                $html .= '<p>'.$escape($journey['journey_subtitle']).'</p>';
            }
            $html .= '</div><div class="ie-rule" data-reveal></div><ol class="ie-journey-list">';
            foreach ($journeyItems as $index => $item) {
                $html .= '<li data-reveal style="--item-delay:'.(($index % 3) * 60).'ms"><div class="ie-journey-row">';
                if (! empty($item['period'])) {
                    $html .= '<p class="ie-period">'.$escape($item['period']).'</p>';
                }
                $html .= '<div><div class="ie-journey-title-row">';
                $itemTitle = $item['title'] ?? $item['role'] ?? '';
                $itemCompany = $item['company'] ?? $item['institution'] ?? '';
                if ($itemTitle !== '') {
                    $html .= '<h3 class="ie-serif">'.$escape($itemTitle).'</h3>';
                }
                if ($itemCompany !== '') {
                    $html .= '<p class="ie-company">'.$escape($itemCompany).'</p>';
                }
                $html .= '</div>';
                if (! empty($item['description'])) {
                    $html .= '<p class="ie-journey-description">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></div></li>';
            }
            $html .= '</ol></div></section>';
        }

        if ($showSkills) {
            $showLevels = ! in_array($skills['skills_show_level'] ?? true, [false, 0, '0', 'false'], true);
            $html .= '<section id="skills" class="ie-skills"><div class="ie-skills-layout"><div data-reveal class="ie-skills-intro">';
            if (! empty($skills['skills_subtitle'])) {
                $html .= '<p class="ie-kicker">'.$escape($skills['skills_subtitle']).'</p>';
            }
            if (! empty($skills['skills_title'])) {
                $html .= '<h2 class="ie-serif ie-h2">'.$escape($skills['skills_title']).'</h2>';
            }
            if (! empty($skills['skills_description'])) {
                $html .= '<p class="ie-skills-description">'.$escape($skills['skills_description']).'</p>';
            }
            $html .= '</div><ul class="ie-skills-list">';
            foreach ($skillItems as $index => $skill) {
                $html .= '<li data-reveal style="--item-delay:'.(($index % 5) * 60).'ms"><div class="ie-skill">';
                $html .= '<div class="ie-skill-heading"><h3 class="ie-serif">'.$escape($skill['name']).'</h3>';
                if ($showLevels && $skill['percent'] !== null) {
                    $percent = (int) round($skill['percent']);
                    $html .= '<span class="ie-count" style="--count-target:'.$percent.'"></span>';
                } elseif ($showLevels && $skill['label'] !== null) {
                    $html .= '<span class="ie-skill-label">'.$escape($skill['label']).'</span>';
                }
                $html .= '</div>';
                if ($showLevels && $skill['percent'] !== null) {
                    $ticks = (int) round(($skill['percent'] / 100) * 28);
                    $html .= '<div class="ie-ticks" role="progressbar" aria-valuenow="'.(int) round($skill['percent']).'" aria-valuemin="0" aria-valuemax="100" aria-label="'.$escape($skill['name']).'">';
                    for ($tick = 1; $tick <= 28; $tick++) {
                        $html .= '<span class="ie-tick-bar'.($tick <= $ticks ? ' on' : '').($tick === $ticks ? ' end' : '').'" style="--tick:'.$tick.'"></span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div></li>';
            }
            $html .= '</ul></div></section>';
        }

        if ($showProjects) {
            $html .= '<section id="portfolio" class="ie-projects"><div class="ie-section-inner"><div data-reveal class="ie-section-heading">';
            if (! empty($portfolio['projects_title'])) {
                $html .= '<h2 class="ie-serif ie-h2">'.$escape($portfolio['projects_title']).'</h2>';
            }
            $projectSubtitle = $portfolio['projects_subtitle'] ?? $portfolio['projects_description'] ?? '';
            if ($projectSubtitle !== '') {
                $html .= '<p>'.$escape($projectSubtitle).'</p>';
            }
            $html .= '</div><div class="ie-rule ie-project-rule" data-reveal></div><ul class="ie-project-grid">';
            foreach ($projects as $index => $project) {
                $image = $assetUrl($project['image']);
                $projectUrl = is_string($project['url']) ? trim($project['url']) : '';
                $html .= '<li data-reveal class="ie-project-item'.($index % 2 === 1 ? ' stagger' : '').'" style="--item-delay:'.(($index % 2) * 80).'ms"><article class="ie-project"><';
                if ($projectUrl !== '') {
                    $html .= 'a href="'.$url($projectUrl).'" target="_blank" rel="noopener noreferrer" aria-label="Open '.$escape($project['title']).'" class="ie-project-media">';
                } else {
                    $html .= 'div class="ie-project-media">';
                }
                if ($image !== '') {
                    $html .= '<img src="'.$url($project['image'], '').'" alt="'.$escape($project['title']).'" loading="lazy">';
                } else {
                    $html .= '<span class="ie-project-initial">'.$escape(mb_substr($project['title'], 0, 1)).'</span>';
                }
                if ($projectUrl !== '') {
                    $html .= '<span class="ie-go" aria-hidden="true">↗</span></a>';
                } else {
                    $html .= '</div>';
                }
                $html .= '<div class="ie-project-copy">';
                if (! empty($project['category'])) {
                    $html .= '<p class="ie-project-category">'.$escape($project['category']).'</p>';
                }
                $html .= '<h3 class="ie-serif"><span class="ie-underline">'.$escape($project['title']).'</span></h3>';
                if (! empty($project['description'])) {
                    $html .= '<p class="ie-project-description">'.$escape($project['description']).'</p>';
                }
                if (! empty($project['repo'])) {
                    $html .= '<a href="'.$url($project['repo']).'" target="_blank" rel="noopener noreferrer" class="ie-ulink ie-source-link">Source code</a>';
                }
                $html .= '</div></article></li>';
            }
            $html .= '</ul></div></section>';
        }

        if ($showContact) {
            $email = is_string($contact['contact_email'] ?? null) ? trim($contact['contact_email']) : '';
            $phone = is_string($contact['contact_phone'] ?? null) ? trim($contact['contact_phone']) : '';
            $location = is_string($contact['contact_location'] ?? null) ? trim($contact['contact_location']) : '';
            $html .= '<section id="contact" class="ie-contact"><div class="ie-contact-layout"><div><div data-reveal>';
            if (! empty($contact['contact_subtitle'])) {
                $html .= '<p class="ie-kicker">'.$escape($contact['contact_subtitle']).'</p>';
            }
            if (! empty($contact['contact_title'])) {
                $html .= '<h2 class="ie-serif ie-contact-title">'.$escape($contact['contact_title']).'</h2>';
            }
            if (! empty($contact['contact_description'])) {
                $html .= '<p class="ie-contact-description">'.$escape($contact['contact_description']).'</p>';
            }
            $html .= '</div>';
            if ($email !== '' || $phone !== '' || $location !== '') {
                $html .= '<div data-reveal class="ie-contact-details">';
                if ($email !== '') {
                    $html .= '<a href="'.$escape($this->safeUrl('mailto:'.$email)).'" class="ie-bigmail ie-serif"><span class="ie-ulink-flip">'.$escape($email).'</span><span aria-hidden="true">↗</span></a>';
                }
                if ($phone !== '' || $location !== '') {
                    $html .= '<div class="ie-contact-meta">';
                    if ($phone !== '') {
                        $telephone = preg_replace('/[^+\d]/', '', $phone);
                        $html .= '<a href="'.$escape($this->safeUrl('tel:'.$telephone)).'" class="ie-ulink"><span aria-hidden="true">☎</span>'.$escape($phone).'</a>';
                    }
                    if ($location !== '') {
                        $html .= '<span><span aria-hidden="true">⌖</span>'.$escape($location).'</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            if ($contactSocials !== []) {
                $html .= '<ul data-reveal class="ie-contact-socials">';
                foreach ($contactSocials as $social) {
                    $html .= '<li><a href="'.$url($social['url']).'" target="_blank" rel="noopener noreferrer" class="ie-ulink">'.$escape($social['label']).'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div>';
            if ($formReady) {
                $html .= '<div data-reveal class="ie-form-column"><form action="'.$escape($googleForm['action']).'" method="POST" target="ie-gform-frame" class="ie-form" data-google-form>
                    <p class="ie-serif ie-form-title">Send a message</p>
                    <div class="ie-form-fields">
                        <label><span class="ie-label">Name</span><input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="ie-input"></label>
                        <label><span class="ie-label">Email</span><input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="ie-input"></label>
                        <label><span class="ie-label">Message</span><textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="4" required class="ie-input"></textarea></label>
                    </div>
                    <button type="submit" class="ie-btn ie-btn-accent ie-submit">Send message <span aria-hidden="true">➤</span></button>
                    <p class="ie-sent" role="status" data-form-status hidden><span class="ie-tick">✓</span>Thanks — your message was submitted.</p>
                </form><iframe name="ie-gform-frame" title="Form submission target" class="ie-hidden-frame" tabindex="-1" aria-hidden="true"></iframe></div>';
            }
            $html .= '</div></section>';
        }
        $html .= '</main>';
        if ($logoText !== '') {
            $html .= '<footer class="ie-footer"><span>&copy; '.date('Y').' '.$escape($logoText).'</span><a href="#home" class="ie-ulink">Back to top <span aria-hidden="true">↗</span></a></footer>';
        }
        $html .= '</div>';

        return '<!doctype html>
<html lang="en" data-template="ivory-editorial">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbfaf7">
    <title>'.$escape($title).'</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    private function section(
        string $id,
        mixed $title,
        mixed $description,
        mixed $image,
        mixed $download,
        callable $url,
        callable $escape
    ): string {
        $html = '<section class="content-section" id="'.$escape($id).'">';
        if (is_string($image) && $image !== '') {
            $html .= '<div class="section-image"><img src="'.$escape($url($image, '')).'" alt="'.$escape($title).'"></div>';
        }
        $html .= '<div class="section-copy"><p class="eyebrow">About</p><h2>'.$escape($title).'</h2>';
        if (is_string($description) && $description !== '') {
            $html .= '<p>'.$escape($description).'</p>';
        }
        if (is_string($download) && $download !== '') {
            $html .= '<a class="text-link" href="'.$url($download).'" download>Download resume</a>';
        }
        $html .= '</div></section>';

        return $html;
    }

    private function listSection(string $id, mixed $title, mixed $description, array $items, callable $escape): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<section class="content-section" id="'.$escape($id).'"><div class="section-heading">';
        if (is_string($description) && $description !== '') {
            $html .= '<p class="eyebrow">'.$escape($description).'</p>';
        }
        $html .= '<h2>'.$escape($title).'</h2></div><div class="card-grid">';

        foreach ($items as $item) {
            $html .= '<article class="content-card">';
            foreach (['period', 'title', 'label', 'name'] as $key) {
                if (isset($item[$key]) && is_scalar($item[$key])) {
                    $tag = $key === 'title' || $key === 'name' ? 'h3' : 'p';
                    $html .= '<'.$tag.' class="card-'.$escape($key).'">'.$escape($item[$key]).'</'.$tag.'>';
                }
            }
            foreach (['company', 'description'] as $key) {
                if (isset($item[$key]) && is_scalar($item[$key])) {
                    $html .= '<p>'.$escape($item[$key]).'</p>';
                }
            }
            $html .= '</article>';
        }

        return $html.'</div></section>';
    }

    private function skillsSection(array $skills, callable $escape): string
    {
        $items = $this->items($skills['skills_list'] ?? []);
        if ($items === []) {
            return '';
        }

        $html = '<section class="content-section" id="skills"><div class="section-heading"><p class="eyebrow">Expertise</p>';
        $html .= '<h2>'.$escape($skills['skills_title'] ?? 'Skills').'</h2>';
        if (! empty($skills['skills_description'])) {
            $html .= '<p>'.$escape($skills['skills_description']).'</p>';
        }
        $html .= '</div><div class="skill-grid">';

        foreach ($items as $item) {
            $label = $item['label'] ?? $item['name'] ?? '';
            $percent = is_numeric($item['percent'] ?? null)
                ? max(0, min(100, (int) $item['percent']))
                : null;
            $html .= '<article class="skill-card"><span>'.$escape($label).'</span>';
            if ($percent !== null) {
                $html .= '<span class="skill-percent">'.$percent.'%</span><div class="skill-track"><span style="width:'.$percent.'%"></span></div>';
            }
            $html .= '</article>';
        }

        return $html.'</div></section>';
    }

    private function projectsSection(array $projects, callable $assetUrl, callable $url, callable $escape): string
    {
        $items = $this->items($projects['projects_list'] ?? $projects['projects'] ?? []);
        if ($items === []) {
            return '';
        }

        $html = '<section class="content-section" id="portfolio"><div class="section-heading">';
        $html .= '<p class="eyebrow">'.$escape($projects['projects_subtitle'] ?? 'Selected work').'</p>';
        $html .= '<h2>'.$escape($projects['projects_title'] ?? 'Projects').'</h2></div>';
        $categories = array_values(array_unique(array_filter(array_map(
            static fn (array $item): string => is_string($item['category'] ?? null) ? $item['category'] : '',
            $items
        ))));
        if ($categories !== []) {
            $html .= '<div class="project-filters" aria-label="Filter projects"><button type="button" class="active" data-filter="all">All</button>';
            foreach ($categories as $category) {
                $html .= '<button type="button" data-filter="'.$escape($category).'">'.$escape($category).'</button>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="project-grid">';
        foreach ($items as $index => $item) {
            $category = $item['category'] ?? '';
            $image = $this->safeUrl($assetUrl($item['image'] ?? null), '');
            $html .= '<article class="project-card" data-category="'.$escape($category).'" data-index="'.$index.'">';
            if ($image !== '') {
                $html .= '<img src="'.$escape($image).'" alt="'.$escape($item['title'] ?? '').'">';
            }
            $html .= '<div class="project-copy">';
            if ($category !== '') {
                $html .= '<p class="eyebrow">'.$escape($category).'</p>';
            }
            $html .= '<h3>'.$escape($item['title'] ?? 'Project').'</h3>';
            if (! empty($item['description'])) {
                $html .= '<p>'.$escape($item['description']).'</p>';
            }
            if (! empty($item['url'])) {
                $html .= '<a class="text-link" href="'.$url($item['url']).'" target="_blank" rel="noopener noreferrer">View project</a>';
            }
            $html .= '</div></article>';
        }

        return $html.'</div></section>';
    }

    private function contactSection(array $contact, callable $url, callable $escape): string
    {
        if (! array_filter($contact, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [])) {
            return '';
        }

        $html = '<section class="content-section contact-section" id="contact"><div class="section-heading">';
        $html .= '<p class="eyebrow">Contact</p><h2>'.$escape($contact['contact_title'] ?? 'Get in touch').'</h2>';
        if (! empty($contact['contact_description'])) {
            $html .= '<p>'.$escape($contact['contact_description']).'</p>';
        }
        $html .= '</div><div class="contact-links">';

        if (! empty($contact['contact_email'])) {
            $email = (string) $contact['contact_email'];
            $html .= '<a href="'.$url('mailto:'.$email).'">'.$escape($email).'</a>';
        }
        if (! empty($contact['contact_phone'])) {
            $phone = (string) $contact['contact_phone'];
            $html .= '<a href="'.$url('tel:'.$phone).'">'.$escape($phone).'</a>';
        }
        if (! empty($contact['contact_location'])) {
            $html .= '<p>'.$escape($contact['contact_location']).'</p>';
        }
        foreach ($this->items($contact['contact_social_links'] ?? []) as $link) {
            $html .= '<a href="'.$url($link['url'] ?? null).'" target="_blank" rel="noopener noreferrer">'.$escape($link['label'] ?? '').'</a>';
        }

        return $html.'</div></section>';
    }

    private function items(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
    }

    private function sectionData(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function collectAssets(array $content, Portfolio $portfolio, mixed $storageDisk): array
    {
        $assets = [];

        array_walk_recursive($content, function (mixed $value) use (&$assets, $storageDisk, $portfolio): void {
            if (! is_string($value)) {
                return;
            }

            $path = $this->storagePathFromUrl($value);
            if ($path === null) {
                return;
            }
            $segments = explode('/', $path);
            $belongsToPortfolio = count($segments) >= 7
                && $segments[0] === 'users'
                && $segments[1] === (string) $portfolio->user_id
                && $segments[2] === 'templates'
                && ctype_digit($segments[3])
                && $segments[4] === 'portfolios'
                && $segments[5] === (string) $portfolio->id
                && $segments[6] !== '';
            if (! $belongsToPortfolio) {
                abort(422, 'A portfolio asset referenced by this export is outside this portfolio.');
            }
            if (! $storageDisk->exists($path)) {
                abort(422, 'A portfolio asset referenced by this export is missing.');
            }
            if (isset($assets[$value])) {
                return;
            }

            $assets[$value] = [
                'absolute_path' => $storageDisk->path($path),
                'archive_path' => 'assets/'.substr(hash('sha256', $path), 0, 12).'-'.basename($path),
            ];
        });

        return $assets;
    }

    private function storagePathFromUrl(string $value): ?string
    {
        $path = parse_url($value, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        } elseif (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        } elseif (! str_starts_with($path, 'users/')) {
            return null;
        }

        $path = rawurldecode($path);
        if ($path === '' || str_contains($path, '\\') || in_array('..', explode('/', $path), true)) {
            return null;
        }

        return $path;
    }

    private function assetUrl(mixed $value, array $assets): string
    {
        if (! is_string($value)) {
            return '';
        }

        return $assets[$value]['archive_path'] ?? $value;
    }

    private function safeUrl(string $value, string $fallback = '#'): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x20]/', $value)) {
            return $fallback;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if ($scheme !== null) {
            return in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)
                ? $value
                : $fallback;
        }

        if (str_starts_with($value, '//') || str_contains($value, '\\')) {
            return $fallback;
        }

        foreach (explode('/', $value) as $segment) {
            if ($segment === '..') {
                return $fallback;
            }
        }

        return $value;
    }

    private function stylesForTemplate(string $slug): string
    {
        if ($slug === 'bento-grid') {
            return $this->bentoGridStyles();
        }
        if ($slug === 'dark-orange-designer') {
            return $this->darkOrangeDesignerStyles();
        }
        if ($slug === 'cobalt-brutalist') {
            return $this->cobaltBrutalistStyles();
        }
        if ($slug === 'emerald-minimalist') {
            return $this->emeraldMinimalistStyles();
        }
        if ($slug === 'ivory-editorial') {
            return $this->ivoryEditorialStyles();
        }

        $theme = self::TEMPLATE_THEMES[$slug] ?? 'emerald';

        return <<<'CSS'
:root{color-scheme:light;--accent:#047857;--accent-soft:#d1fae5;--page:#f8fafc;--surface:#fff;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--radius:22px;--shadow:0 12px 36px rgba(15,23,42,.08)}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--page);color:var(--ink);font:16px/1.65 Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
a{color:inherit;text-decoration:none}
img{display:block;max-width:100%}
.site-nav{position:sticky;top:0;z-index:10;display:flex;align-items:center;justify-content:space-between;gap:24px;padding:16px max(24px,calc((100vw - 1180px)/2));border-bottom:1px solid var(--line);background:color-mix(in srgb,var(--page) 92%,transparent);backdrop-filter:blur(16px)}
.brand{font-size:1.1rem;font-weight:800;letter-spacing:-.04em}
.site-links{display:flex;align-items:center;gap:24px;color:var(--muted);font-size:.9rem;font-weight:600}
.site-links a:hover,.text-link:hover{color:var(--accent)}
.menu-toggle{display:none}
main{width:min(1180px,calc(100% - 48px));margin:0 auto}
.hero{display:grid;grid-template-columns:1.1fr .9fr;align-items:center;gap:48px;min-height:76vh;padding:72px 0}
.hero-copy{max-width:680px}
.eyebrow{margin:0 0 12px;color:var(--accent);font-size:.75rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
h1,h2,h3,p{margin-top:0}
h1{margin-bottom:18px;font-size:clamp(3rem,7vw,6rem);line-height:.98;letter-spacing:-.065em}
h2{margin-bottom:14px;font-size:clamp(2rem,4vw,3.4rem);line-height:1.05;letter-spacing:-.05em}
h3{margin-bottom:8px;font-size:1.2rem;line-height:1.2;letter-spacing:-.025em}
.hero-role{margin-bottom:14px;color:var(--accent);font-size:clamp(1.2rem,2.2vw,1.7rem);font-weight:700}
.hero-description,.section-heading>p:not(.eyebrow),.section-copy>p:not(.eyebrow),.project-copy>p:not(.eyebrow){color:var(--muted)}
.hero-image,.section-image{overflow:hidden;border:1px solid var(--line);border-radius:var(--radius);background:var(--surface);box-shadow:var(--shadow)}
.hero-image img{width:100%;height:460px;object-fit:cover}
.button{display:inline-flex;margin-top:18px;padding:12px 20px;border-radius:999px;background:var(--accent);color:#fff;font-weight:700}
.content-section{padding:64px 0;border-top:1px solid var(--line)}
.section-heading{max-width:760px;margin-bottom:28px}
.section-heading>p:last-child{margin-bottom:0}
.section-image{float:right;width:min(38%,360px);margin:0 0 24px 36px}
.section-image img{width:100%;max-height:400px;object-fit:cover}
.section-copy{min-height:200px}
.text-link{display:inline-block;margin-top:8px;color:var(--accent);font-weight:700}
.card-grid,.project-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.content-card,.skill-card,.project-card{overflow:hidden;border:1px solid var(--line);border-radius:var(--radius);background:var(--surface);box-shadow:var(--shadow)}
.content-card{padding:24px}
.card-period,.card-label{color:var(--accent);font-size:.82rem;font-weight:800}
.card-title{margin-top:12px}
.skill-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.skill-card{display:grid;grid-template-columns:1fr auto;gap:10px;padding:18px;font-weight:700}
.skill-percent{color:var(--accent);font-size:.85rem}
.skill-track{grid-column:1/-1;height:6px;overflow:hidden;border-radius:99px;background:var(--line)}
.skill-track span{display:block;height:100%;border-radius:inherit;background:var(--accent)}
.project-filters{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}
.project-filters button{padding:8px 14px;border:1px solid var(--line);border-radius:99px;background:var(--surface);color:var(--muted);font:inherit;cursor:pointer}
.project-filters button.active{border-color:var(--accent);background:var(--accent);color:#fff}
.project-card[hidden]{display:none}
.project-card>img{width:100%;aspect-ratio:16/10;object-fit:cover}
.project-copy{padding:20px}
.contact-section{display:grid;grid-template-columns:1fr 1fr;gap:28px}
.contact-links{display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:12px;padding:24px;border-radius:var(--radius);background:var(--surface)}
.contact-links a{color:var(--accent);font-weight:700;overflow-wrap:anywhere}
.contact-links p{margin:0;color:var(--muted)}
.site-footer{padding:28px 24px;border-top:1px solid var(--line);color:var(--muted);text-align:center;font-size:.85rem}
.theme-dark-orange{--accent:#ff5a1f;--accent-soft:#431b0e;--page:#090909;--surface:#141414;--ink:#f5f5f5;--muted:#a1a1aa;--line:#303030;--shadow:0 14px 40px rgba(0,0,0,.28)}
.theme-dark-orange .hero-image,.theme-dark-orange .section-image{border-radius:50%}
.theme-ivory{--accent:#244fce;--accent-soft:#e8ecfa;--page:#f5f1e8;--surface:#eee9de;--ink:#20211f;--muted:#686861;--line:#d9d3c7;--radius:4px;--shadow:none}
.theme-ivory h1,.theme-ivory h2,.theme-ivory h3{font-family:Georgia,"Times New Roman",serif;font-weight:400;letter-spacing:-.04em}
.theme-bento{--accent:#637e00;--accent-soft:#d8ff54;--page:#f1f0e9;--surface:#e5e3db;--ink:#17181c;--muted:#66676c;--line:#d4d2ca;--radius:26px;--shadow:none}
.theme-bento .hero-copy,.theme-bento .content-card,.theme-bento .skill-card,.theme-bento .project-card{border-radius:var(--radius)}
.theme-bento .hero-copy{padding:36px;background:#d8ff54}
.theme-bento .project-card:nth-child(3n+1){grid-column:span 2}
.theme-cobalt{--accent:#245bff;--accent-soft:#dce6ff;--page:#f5f6f8;--surface:#fff;--ink:#111827;--muted:#4b5563;--line:#171717;--radius:0;--shadow:5px 5px 0 #171717}
.theme-cobalt .content-card,.theme-cobalt .skill-card,.theme-cobalt .project-card,.theme-cobalt .hero-image,.theme-cobalt .section-image{border:2px solid #171717}
.theme-cobalt .button{border:2px solid #171717;border-radius:0;box-shadow:4px 4px 0 #171717}
@media(max-width:760px){.site-nav{padding:14px 20px}.menu-toggle{display:inline-flex;padding:8px 12px;border:1px solid var(--line);border-radius:999px;background:var(--surface);color:var(--ink);font:inherit}.site-links{display:none;position:absolute;top:100%;right:0;left:0;align-items:stretch;flex-direction:column;gap:14px;padding:18px 20px;border-bottom:1px solid var(--line);background:var(--page)}.site-links.is-open{display:flex}main{width:calc(100% - 40px)}.hero{grid-template-columns:1fr;gap:28px;min-height:auto;padding:52px 0}.hero-image img{height:340px}.content-section{padding:44px 0}.card-grid,.project-grid,.skill-grid{grid-template-columns:1fr}.theme-bento .project-card:nth-child(3n+1){grid-column:auto}.contact-section{grid-template-columns:1fr}.section-image{float:none;width:100%;max-width:440px;margin:0 0 24px}.section-copy{min-height:0}}
CSS;
    }

    private function bentoGridStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Young+Serif&family=Hanken+Grotesk:wght@400;500;600;700&display=swap');
html{scroll-behavior:smooth;scroll-padding-top:70px}
.rail{--ink:#2a0f2d;--paper:#e3cfe6;--muted:#4a2650;--accent:#5b21b6;--orchid:#e07ad8;--orchid-soft:#f7c6f1;--lilac:#f1e6f8;--mark:rgba(255,255,255,.85);--line:rgba(42,15,45,.16);--grad:linear-gradient(180deg,#ddd5e3 0%,#dfcfe2 24%,#e4badf 44%,#e8a6dd 62%,#e484d6 82%,#d676d6 100%);--grain:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='220' height='220'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2' stitchTiles='stitch'/><feColorMatrix type='saturate' values='0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");--ease:cubic-bezier(.22,1,.36,1);background:var(--grad);color:var(--ink)}
.rail::before{content:'';position:absolute;inset:0;z-index:0;pointer-events:none;background-image:var(--grain);opacity:.55;mix-blend-mode:soft-light}
.font-display{font-family:'Young Serif',ui-serif,Georgia,serif;font-weight:400}
.font-body{font-family:'Hanken Grotesk',ui-sans-serif,system-ui,sans-serif}
.rail a:focus-visible,.rail button:focus-visible{outline:2px solid var(--accent);outline-offset:4px}
.rail-panel{position:sticky;border-radius:28px;background:var(--grad);border:1px solid rgba(255,255,255,.6);box-shadow:0 30px 60px -30px rgba(130,40,150,.55);overflow:hidden;isolation:isolate}
.rail-panel::before{content:'';position:absolute;inset:0;z-index:-1;pointer-events:none;background-image:var(--grain);opacity:.55;mix-blend-mode:soft-light}
.rail ::selection{background:var(--ink);color:var(--orchid-soft)}
.rail h1,.rail h2,.rail h3{text-wrap:balance}
.rail p{text-wrap:pretty}
.progress{position:fixed;inset:0 0 auto 0;z-index:60;height:3px;transform:scaleX(0);transform-origin:left;background:linear-gradient(90deg,var(--accent),var(--orchid))}
.no-scrollbar{scrollbar-width:none}
.no-scrollbar::-webkit-scrollbar{display:none}
.section{padding-top:clamp(5rem,12vw,9rem)}
.h2{font-family:'Young Serif',serif;font-size:clamp(2.5rem,5.5vw,4.5rem);line-height:1.02;letter-spacing:-.02em}
.rail-link{display:flex;align-items:center;gap:.8rem;padding:.5rem 0;font-size:1rem;font-weight:500;color:rgba(42,15,45,.55);transition:color .3s,transform .4s var(--ease)}
.rail-link:hover{color:var(--ink);transform:translateX(4px)}
.rail-bar{height:2px;width:14px;border-radius:2px;background:currentColor;transition:width .5s var(--ease),background .3s}
.rail-link.active{color:var(--ink)}
.rail-link.active .rail-bar{width:40px;background:var(--ink)}
.mobile-active{background:var(--ink);color:#fff}
.mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.12em;margin-bottom:-.12em}
.mask-inner{display:inline-block}
.dot{width:8px;height:8px;border-radius:50%;background:var(--accent);animation:ping 2.4s infinite}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(91,33,182,.45)}70%,100%{box-shadow:0 0 0 10px rgba(91,33,182,0)}}
.rail.motion-ready .mask-inner{transform:translateY(115%);transition:transform 1.1s var(--ease) calc(var(--i)*100ms + 250ms)}
.rail.motion-ready.is-ready .mask-inner{transform:none}
.rail.motion-ready .rail-fade{opacity:0;transform:translateY(18px);transition:opacity .9s var(--ease) calc(var(--i)*100ms + 250ms),transform .9s var(--ease) calc(var(--i)*100ms + 250ms)}
.rail.motion-ready.is-ready .rail-fade{opacity:1;transform:none}
.rail.motion-ready .rail-in{opacity:0;transform:translateX(-16px);transition:opacity .8s var(--ease) calc(var(--i)*70ms + 150ms),transform .8s var(--ease) calc(var(--i)*70ms + 150ms)}
.rail.motion-ready.is-ready .rail-in{opacity:1;transform:none}
.mark{background:linear-gradient(transparent 62%,var(--mark) 62%) no-repeat left / 0% 100%;box-decoration-break:clone;-webkit-box-decoration-break:clone;padding:0 .12em;margin:0 -.12em;transition:background-size 1.2s var(--ease) .5s}
.rail:not(.motion-ready) .mark,.mark.is-visible{background-size:100% 100%}
.portrait{aspect-ratio:4/5;overflow:hidden;background:rgba(255,255,255,.55);border-radius:140px 28px 140px 28px}
.portrait-alt{border-radius:28px 140px 28px 140px}
.btn-ink{display:inline-flex;align-items:center;gap:.9rem;padding:.5rem .5rem .5rem 1.6rem;border-radius:999px;background:var(--ink);color:#fff;font-weight:600;transition:background .3s,transform .35s var(--ease)}
.btn-ink:hover{background:var(--accent);transform:translateY(-2px)}
.btn-ink-icon{display:grid;place-items:center;width:40px;height:40px;border-radius:50%;background:var(--orchid-soft);color:var(--ink);transition:transform .45s var(--ease)}
.btn-ink:hover .btn-ink-icon{transform:translateY(3px)}
.sweep{background:linear-gradient(currentColor,currentColor) no-repeat 100% 100% / 0 2px;padding-bottom:2px;transition:background-size .45s var(--ease)}
.sweep:hover{background-position:0 100%;background-size:100% 2px}
.project{overflow:hidden;border-radius:28px;background:#fff;border:1px solid var(--line);box-shadow:0 30px 60px -36px rgba(91,33,182,.55)}
.project-arrow{display:grid;flex-shrink:0;place-items:center;width:52px;height:52px;border-radius:50%;background:var(--ink);color:#fff;font-size:1.3rem;transition:background .3s,color .3s,transform .5s var(--ease)}
.project:hover .project-arrow{background:var(--orchid);color:var(--ink);transform:rotate(45deg)}
.pill{position:relative;display:inline-flex;align-items:center;gap:.9rem;overflow:hidden;padding:.8rem 1.4rem;border-radius:999px;border:1px solid rgba(255,255,255,.7);background:rgba(255,255,255,.78);backdrop-filter:blur(10px);transition:border-color .3s,transform .4s var(--ease)}
.pill:hover{border-color:var(--accent);transform:translateY(-2px)}
.pill-fill{position:absolute;inset:0 auto 0 0;background:var(--orchid-soft);transform-origin:left}
.rail.motion-ready .pill-fill{transform:scaleX(0);transition:transform 1.2s var(--ease) calc(var(--d,0ms) + 200ms)}
.rail.motion-ready .is-visible .pill-fill{transform:none}
.journey-panel{padding:clamp(1.75rem,5vw,4.5rem);border-radius:36px;background:linear-gradient(155deg,#ea74ca 0%,#d04aa8 50%,#a82a86 100%);box-shadow:0 40px 80px -40px rgba(168,42,134,.7)}
.jrow{position:relative;display:grid;gap:1rem 3rem;padding:2.5rem 0}
@media(min-width:768px){.jrow{grid-template-columns:minmax(170px,.7fr) 2fr;padding:3.25rem 0}}
.jrow::before,.jrow::after{content:'';position:absolute;inset:0 0 auto 0;height:2px}
.jrow::before{background:rgba(255,255,255,.28)}
.jrow::after{background:#fff;transform-origin:left}
.rail.motion-ready .jrow::after{transform:scaleX(0);transition:transform 1.3s var(--ease)}
.rail.motion-ready .jrow.is-visible::after{transform:none}
.jperiod{font-family:'Young Serif',serif;font-size:clamp(2rem,4.2vw,3.5rem);line-height:1;color:#fff;transition:transform .5s var(--ease)}
.jrow:hover .jperiod{transform:translateX(10px)}
@media(prefers-reduced-motion:reduce){.rail .mask-inner,.rail .rail-fade,.rail .rail-in,.rail .pill-fill{opacity:1!important;transform:none!important;transition:none!important}.mark{background-size:100% 100%!important;transition:none!important}.dot{animation:none!important}.jrow::after{transform:none!important;transition:none!important}}
CSS;
    }

    private function cobaltBrutalistStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Figtree:wght@400;500;600;700&display=swap');
:root{color-scheme:light;--paper:#f5f6fa;--soft:#e9ecf8;--surface:#fff;--ink:#0b1020;--muted:#0b1020b0;--line:#0b102021;--accent:#2b3ff0;--accent-ink:#fff;--yellow:#ffd84d;--ease:cubic-bezier(.2,.7,.2,1)}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:6rem}
body{margin:0;background:var(--paper);color:var(--ink);font-family:Figtree,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
a{color:inherit;text-decoration:none}
img{display:block;max-width:100%}
.nb-root{position:relative;min-height:100vh;overflow:hidden;background:var(--paper);color:var(--ink);transition:background-color .4s ease,color .4s ease}
.nb-display{font-family:"Bricolage Grotesque",Figtree,ui-sans-serif,system-ui,sans-serif;font-weight:600;letter-spacing:-.035em}
.nb-muted{color:var(--muted)}
.nb-header{position:fixed;inset:0 0 auto;z-index:50;padding:12px 12px 0}
.nb-progress{position:fixed;inset:0 0 auto;z-index:60;height:3px;background:var(--accent);transform:scaleX(0);transform-origin:left;pointer-events:none}
.nb-nav{display:flex;align-items:center;justify-content:space-between;gap:12px;width:min(1240px,100%);height:56px;margin:auto;padding:0 8px 0 20px;border:1px solid transparent;border-radius:999px;background:color-mix(in srgb,var(--paper) 55%,transparent);backdrop-filter:blur(14px) saturate(1.4);transition:border-color .35s,background-color .35s,box-shadow .35s}
.nb-nav.is-scrolled{border-color:var(--line);background:color-mix(in srgb,var(--paper) 82%,transparent);box-shadow:0 10px 30px -12px color-mix(in srgb,var(--ink) 28%,transparent)}
.nb-brand{display:flex;min-width:0;align-items:center;gap:10px}
.nb-brand .nb-display{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:18px}
.nb-logo-image{width:auto;max-width:112px;height:28px;object-fit:contain}
.nb-navlinks{display:none;align-items:center;gap:4px;margin:0;padding:0;list-style:none}
.nb-navlink{display:inline-block;border-radius:999px;padding:8px 16px;color:var(--muted);font-size:14px;font-weight:500;transition:color .25s,background-color .25s}
.nb-navlink:hover,.nb-navlink[data-active=true]{color:var(--ink);background:var(--soft)}
.nb-nav-cta{padding:10px 20px!important;font-size:14px!important}
.nb-container{width:min(1240px,100%);margin-inline:auto;padding-inline:20px}
.nb-hero{position:relative;isolation:isolate;display:flex;min-height:100svh;align-items:center;overflow:hidden}
.nb-hero-bg{position:absolute;inset:0;z-index:-1;width:100%;height:100%;object-fit:cover;opacity:.1}
.nb-hero-grid{display:grid;align-items:center;gap:64px;padding-top:144px;padding-bottom:80px}
.nb-hero-copy{min-width:0}
.nb-chip{display:inline-flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:999px;background:var(--surface);padding:7px 16px 7px 14px;font-size:14px;font-weight:600}
.nb-pulse{position:relative;width:8px;height:8px;border-radius:99px;background:#22c55e}
.nb-pulse::after{position:absolute;inset:0;border-radius:inherit;background:inherit;content:"";animation:nb-ping 2.2s cubic-bezier(0,0,.2,1) infinite}
.nb-hero-name{overflow-wrap:anywhere;margin:28px 0 0;font-size:clamp(3.25rem,9.5vw,8rem);line-height:.94;letter-spacing:-.05em}
.nb-hero-role{margin:24px 0 0;color:var(--accent);font-size:clamp(1.5rem,3vw,1.875rem)}
.nb-hero-description{max-width:34rem;margin:24px 0 0;white-space:pre-line;font-size:18px;line-height:1.7}
.nb-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:36px}
.nb-btn{position:relative;isolation:isolate;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid transparent;border-radius:999px;padding:14px 24px;background-repeat:no-repeat;background-position:left center;background-size:0 100%;font-size:15px;font-weight:600;line-height:1.2;cursor:pointer;transition:background-size .5s var(--ease),color .3s,transform .25s,box-shadow .3s,border-color .3s}
.nb-btn:hover{background-size:100% 100%;transform:translateY(-2px)}
.nb-btn:active{transform:translateY(0) scale(.98)}
.nb-btn[disabled]{opacity:.7;pointer-events:none}
.nb-btn-primary{background-color:var(--accent);background-image:linear-gradient(var(--ink),var(--ink));color:var(--accent-ink);box-shadow:0 8px 22px -10px var(--accent)}
.nb-btn-primary:hover{color:var(--paper);box-shadow:0 14px 28px -12px var(--accent)}
.nb-btn-ghost{border-color:var(--line);background-color:transparent;background-image:linear-gradient(var(--soft),var(--soft));color:var(--ink)}
.nb-btn-ghost:hover{border-color:var(--ink)}
.nb-arrow{display:inline-block;transition:transform .3s var(--ease)}
.nb-btn:hover .nb-arrow,.nb-bigmail:hover .nb-arrow,.nb-social:hover .nb-arrow,.nb-textlink:hover .nb-arrow{transform:translate(3px,-3px)}
.nb-icon-list{display:flex;flex-wrap:wrap;gap:8px;margin:36px 0 0;padding:0;list-style:none}
.nb-icon-btn{display:flex;width:44px;height:44px;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:999px;background:var(--surface);font-weight:700;transition:transform .3s var(--ease),background-color .25s,color .25s,border-color .25s}
.nb-icon-btn:hover{transform:translateY(-3px);border-color:var(--accent);background:var(--accent);color:var(--accent-ink)}
.nb-portrait{position:relative;width:74%;max-width:368px;aspect-ratio:1;margin:0 auto}
.nb-ring{position:absolute;inset:-18px;border:1.5px dashed color-mix(in srgb,var(--accent) 70%,transparent);border-radius:999px;animation:nb-spin 48s linear infinite}
.nb-ring-2{inset:-38px;border:1px solid var(--line);animation-duration:30s;animation-direction:reverse}
.nb-ring-2::after{position:absolute;top:0;left:50%;width:14px;height:14px;transform:translate(-50%,-50%);border-radius:999px;background:var(--accent);box-shadow:0 0 0 5px color-mix(in srgb,var(--accent) 22%,transparent);content:""}
.nb-disc{position:absolute;inset:0;transform:translate(18px,18px);border-radius:999px;background:var(--accent);transition:transform .7s var(--ease)}
.nb-portrait:hover .nb-disc{transform:translate(7px,7px)}
.nb-photo{position:absolute;inset:0;overflow:hidden;border:6px solid var(--paper);border-radius:999px;background:var(--soft);box-shadow:0 24px 50px -24px color-mix(in srgb,var(--ink) 55%,transparent)}
.nb-photo img{width:100%;height:100%;object-fit:cover;transition:transform .9s var(--ease)}
.nb-portrait:hover .nb-photo img{transform:scale(1.06)}
.nb-initials{display:flex;width:100%;height:100%;align-items:center;justify-content:center;background:var(--accent);color:var(--accent-ink);font-family:"Bricolage Grotesque",sans-serif;font-size:8rem}
.nb-orbit{position:absolute;display:flex;width:56px;height:56px;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:999px;background:var(--surface);color:var(--accent);box-shadow:0 14px 28px -14px color-mix(in srgb,var(--ink) 45%,transparent);font-size:20px;animation:nb-float 5.5s ease-in-out infinite}
.nb-orbit-a{top:4%;right:-3%}
.nb-orbit-b{bottom:12%;left:-7%;width:48px;height:48px;color:var(--ink);animation-duration:6.5s;animation-delay:-2s;font-size:14px}
.nb-dot{position:absolute;top:14%;left:2%;width:18px;height:18px;border-radius:999px;background:var(--yellow);animation:nb-float 4.5s ease-in-out infinite -1s}
.nb-badge{position:absolute;bottom:-18px;left:50%;display:inline-flex;align-items:center;gap:8px;transform:translateX(-50%);white-space:nowrap;border:1px solid var(--line);border-radius:999px;background:var(--surface);padding:9px 16px;font-size:14px;font-weight:600;box-shadow:0 14px 30px -14px color-mix(in srgb,var(--ink) 40%,transparent)}
.nb-band{border-block:1px solid var(--line);background:var(--soft)}
.nb-section-padding{padding-block:112px}
.nb-two-col{display:grid;align-items:center;gap:56px}
.nb-about-image{min-width:0}
.nb-frame{position:relative;max-width:448px;margin:auto;border:1px solid var(--line);border-radius:28px;background:var(--surface);padding:8px;transition:transform .6s var(--ease)}
.nb-frame:hover{transform:rotate(-1.2deg) scale(1.01)}
.nb-frame img{width:100%;aspect-ratio:4/5;border-radius:28px;object-fit:cover}
.nb-kicker{display:inline-flex;align-items:center;gap:11px;color:var(--accent);font-size:15px;font-weight:600}
.nb-kicker::before{width:28px;height:2px;transform-origin:left;background:currentColor;content:"";transition:transform .7s var(--ease) .2s}
.nb-js [data-reveal]:not(.is-in) .nb-kicker::before{transform:scaleX(0)}
.nb-h2{margin:16px 0 0;font-size:clamp(2.25rem,5vw,4rem);line-height:1.02;letter-spacing:-.04em;text-wrap:balance;transition:letter-spacing 1.2s var(--ease)}
.nb-js [data-reveal]:not(.is-in) .nb-h2{letter-spacing:.01em}
.nb-prose{max-width:608px;margin:28px 0 0;white-space:pre-line;font-size:18px;line-height:1.75}
.nb-highlights{max-width:608px;margin:40px 0 0;padding:0;border-top:1px solid var(--line);list-style:none}
.nb-highlights li{display:flex;align-items:flex-start;gap:16px;border-bottom:1px solid var(--line);padding:16px 0;transition:padding-left .35s var(--ease),background-color .3s}
.nb-highlights li:hover{padding-left:12px}
.nb-tick{display:inline-flex;width:24px;height:24px;flex:none;align-items:center;justify-content:center;border-radius:999px;background:var(--accent);color:var(--accent-ink);font-size:14px;font-weight:800}
.nb-highlight-description{display:block;margin-top:4px;font-size:15px;line-height:1.6}
.nb-about-actions{margin-top:40px}
.nb-sticky-copy{align-self:start}
.nb-timeline{position:relative;margin:0 0 0 8px;border-left:1.5px solid var(--line);padding:0 0 0 36px;list-style:none}
.nb-tl-item{position:relative;padding:0 0 48px}
.nb-tl-item:last-child{padding-bottom:0}
.nb-tl-item::before{position:absolute;top:6px;left:-36px;width:14px;height:14px;transform:translateX(-50%);border:2px solid var(--accent);border-radius:999px;background:var(--paper);content:"";transition:background-color .5s .3s,box-shadow .4s}
.nb-js .nb-tl-item.is-in::before{background:var(--accent)}
.nb-tl-item:hover::before{box-shadow:0 0 0 6px color-mix(in srgb,var(--accent) 20%,transparent)}
.nb-tl-item::after{position:absolute;top:0;bottom:0;left:-37px;width:1.5px;transform:scaleY(0);transform-origin:top;background:var(--accent);content:"";transition:transform 1.1s cubic-bezier(.5,0,.2,1) .2s}
.nb-js .nb-tl-item.is-in::after{transform:scaleY(1)}
.nb-tl-item:last-child::after{display:none}
.nb-period{margin:0;color:var(--accent);font-size:14px;font-weight:600}
.nb-item-title{margin:6px 0 0;font-size:24px}
.nb-company{margin:4px 0 0;font-weight:500}
.nb-description{max-width:608px;margin:12px 0 0;white-space:pre-line;line-height:1.7}
.nb-skills{display:grid;align-content:start;gap:0 48px;margin:0;padding:0;list-style:none}
.nb-skill{border-bottom:1px solid var(--line);padding:22px 0}
.nb-skill-heading{display:flex;align-items:center;gap:14px}
.nb-skill-tile{display:inline-flex;width:40px;height:40px;flex:none;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:13px;background:var(--surface);color:var(--accent);font-weight:700;transition:background-color .3s,color .3s,transform .35s var(--ease)}
.nb-skill:hover .nb-skill-tile,.nb-service:hover .nb-skill-tile{transform:rotate(-6deg) scale(1.06);background:var(--accent);color:var(--accent-ink)}
.nb-skill h3{margin:0;font-weight:600}
.nb-level{margin-left:auto;color:var(--muted);font-size:14px;font-weight:600;font-variant-numeric:tabular-nums}
.nb-pill{display:inline-block;margin-left:auto;border:1px solid var(--line);border-radius:999px;padding:3px 11px;color:var(--muted);font-size:12px;font-weight:600}
.nb-track{position:relative;width:100%;height:6px;margin-top:16px;border-radius:999px;background:color-mix(in srgb,var(--ink) 9%,transparent)}
.nb-bar{position:relative;display:block;width:var(--w,0%);height:100%;border-radius:inherit;background:linear-gradient(90deg,color-mix(in srgb,var(--accent) 55%,transparent),var(--accent));transition:width 1.5s var(--ease) calc(var(--d,0ms) + .3s)}
.nb-js [data-reveal]:not(.is-in) .nb-bar{width:0}
.nb-media{position:relative;display:block;overflow:hidden;aspect-ratio:4/3;border:1px solid var(--line);border-radius:28px;background:var(--soft);isolation:isolate}
.nb-media-featured{aspect-ratio:16/9}
.nb-media img,.nb-tone{width:100%;height:100%;object-fit:cover;transition:transform .9s var(--ease)}
.nb-project:hover .nb-media img,.nb-project:hover .nb-tone{transform:scale(1.06)}
.nb-tone{display:flex;align-items:center;justify-content:center;font-family:"Bricolage Grotesque",sans-serif;font-size:88px}
.nb-tone-0{background:var(--accent);color:var(--accent-ink)}
.nb-tone-1{background:var(--yellow);color:var(--ink)}
.nb-tone-2{background:var(--ink);color:var(--paper)}
.nb-flag{position:absolute;top:16px;left:16px;border-radius:999px;background:var(--yellow);padding:4px 12px;color:var(--ink);font-size:12px;font-weight:700}
.nb-go{position:absolute;top:16px;right:16px;display:flex;width:44px;height:44px;align-items:center;justify-content:center;transform:translate(-8px,8px) scale(.9);border-radius:999px;background:var(--paper);color:var(--ink);opacity:0;transition:opacity .35s,transform .45s var(--ease)}
.nb-project:hover .nb-go,.nb-media:focus-visible .nb-go{transform:none;opacity:1}
.nb-project-grid{display:grid;gap:64px 32px;margin:0;padding:0;list-style:none}
.nb-project-item{min-width:0}
.nb-project-copy{display:flex;height:100%;flex-direction:column;padding-top:20px}
.nb-project-copy h3{margin:0;font-size:24px;line-height:1.15}
.nb-underline{background:linear-gradient(currentColor,currentColor) left 96% / 0% 2px no-repeat;transition:background-size .5s var(--ease)}
.nb-project:hover .nb-underline{background-size:100% 2px}
.nb-project-copy>p{margin:12px 0 0;line-height:1.65}
.nb-tags{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0 0;padding:0;list-style:none}
.nb-tags .nb-pill{margin:0}
.nb-textlink{display:inline-flex;align-items:center;gap:7px;margin-top:auto;padding-top:20px;background:linear-gradient(currentColor,currentColor) left bottom / 0% 1.5px no-repeat;font-weight:600;transition:background-size .4s var(--ease)}
.nb-textlink:hover{background-size:100% 1.5px}
.nb-services-section{padding-block:112px}
.nb-services-section>.nb-h2{margin-bottom:48px}
.nb-service-list{margin:44px 0 0;padding:0;border-top:1px solid var(--line);list-style:none}
.nb-service{position:relative;display:grid;align-items:center;gap:16px;border-bottom:1px solid var(--line);padding:28px 8px;transition:background-color .35s,padding .4s var(--ease)}
.nb-service:hover{padding-inline:24px;background:var(--surface)}
.nb-service h3{margin:0;font-size:26px;line-height:1.2}
.nb-service p{margin:0;line-height:1.65}
.nb-service-arrow{display:none;color:var(--accent);font-size:24px;opacity:0;transform:translate(-12px,12px);transition:opacity .3s,transform .4s var(--ease)}
.nb-service:hover .nb-service-arrow{transform:none;opacity:1}
.nb-contact-section{scroll-margin-top:16px;padding:96px 12px 24px}
.nb-contact{position:relative;max-width:1240px;margin:auto;overflow:hidden;border-radius:40px;background:var(--accent);color:var(--accent-ink)}
.nb-contact::before,.nb-contact::after{position:absolute;border-radius:999px;background:var(--accent-ink);opacity:.08;pointer-events:none;content:""}
.nb-contact::before{top:-160px;right:-128px;width:416px;height:416px;animation:nb-drift 16s ease-in-out infinite}
.nb-contact::after{bottom:-96px;left:-80px;width:256px;height:256px;animation:nb-drift 20s ease-in-out infinite reverse}
.nb-contact-grid{position:relative;z-index:1;display:grid;gap:56px;padding:28px}
.nb-contact .nb-kicker{color:currentColor}
.nb-contact-title{overflow-wrap:anywhere;margin:16px 0 0;font-size:clamp(2.75rem,7.5vw,6rem);line-height:.95}
.nb-contact-description{max-width:544px;margin:24px 0 0;white-space:pre-line;font-size:18px;line-height:1.7;opacity:.8}
.nb-bigmail{display:inline-flex;max-width:100%;align-items:center;gap:12px;margin-top:40px;overflow-wrap:anywhere;font-size:clamp(1.5rem,4vw,2.5rem)}
.nb-underline-contact{padding-bottom:4px;background:linear-gradient(currentColor,currentColor) left bottom / 100% 2px no-repeat;transition:background-size .5s var(--ease)}
.nb-bigmail:hover .nb-underline-contact{background-position:right bottom;background-size:0% 2px}
.nb-contact-details{display:flex;flex-wrap:wrap;gap:12px 32px;margin-top:20px;font-weight:500}
.nb-contact-details>span,.nb-contact-link{display:inline-flex;align-items:center;gap:8px}
.nb-contact-link{opacity:.9;transition:opacity .2s}
.nb-contact-link:hover{opacity:1;text-decoration:underline;text-underline-offset:4px}
.nb-contact-socials{display:flex;flex-wrap:wrap;gap:8px;margin:32px 0 0;padding:0;list-style:none}
.nb-social{display:inline-flex;align-items:center;gap:6px;border:1px solid color-mix(in srgb,currentColor 45%,transparent);border-radius:999px;padding:7px 16px;font-size:14px;font-weight:600;transition:background-color .3s,color .3s,border-color .3s,transform .3s}
.nb-social:hover{transform:translateY(-2px);border-color:var(--accent-ink);background:var(--accent-ink);color:var(--accent)}
.nb-form{border-radius:28px;background:var(--surface);padding:24px;color:var(--ink);box-shadow:0 30px 60px -30px #0006}
.nb-form label{display:block;margin-bottom:20px}
.nb-form label span{display:block;margin-bottom:8px;font-size:14px;font-weight:600}
.nb-input{width:100%;border:1px solid var(--line);border-radius:14px;background:var(--paper);padding:13px 16px;color:var(--ink);font:500 16px/1.5 Figtree,ui-sans-serif,system-ui,sans-serif;transition:border-color .25s,box-shadow .25s,background-color .25s}
.nb-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 22%,transparent)}
.nb-submit{width:100%}
.nb-status{display:flex;align-items:center;gap:8px;color:var(--accent);font-size:14px;font-weight:600}
.nb-email-button{width:100%;justify-content:center;padding-block:20px}
.nb-hidden-frame{display:none}
.nb-footer{display:flex;max-width:1240px;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin:auto;padding:32px 8px;font-size:14px}
.nb-footer>span{color:var(--muted)}
.nb-js [data-reveal]{opacity:0;transform:translateY(22px);transition:opacity .8s var(--ease) var(--d,0ms),transform .8s var(--ease) var(--d,0ms)}
.nb-js [data-reveal].is-in{opacity:1;transform:none}
.nb-js .nb-word{animation:nb-rise 1s var(--ease) forwards;animation-delay:calc(.15s + var(--i,0)*.1s)}
.nb-js [data-reveal].is-in .nb-skill-tile{animation:nb-tile .7s cubic-bezier(.3,1.5,.4,1) .15s backwards}
.nb-js [data-reveal].is-in .nb-media{animation:nb-wipe 1.2s cubic-bezier(.7,0,.2,1) .1s backwards}
.nb-js [data-reveal].is-in .nb-media img{animation:nb-settle 1.6s var(--ease) .1s backwards}
.nb-js.nb-root{animation:nb-enter .7s ease both}
.nb-root :where(a,button,input,textarea):focus-visible{outline:2px solid var(--accent);outline-offset:3px;border-radius:8px}
@keyframes nb-spin{to{transform:rotate(360deg)}}
@keyframes nb-float{50%{transform:translateY(-12px)}}
@keyframes nb-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes nb-rise{to{transform:translateY(0)}}
@keyframes nb-enter{from{opacity:0}}
@keyframes nb-tile{from{transform:scale(.4) rotate(-20deg);opacity:0}}
@keyframes nb-wipe{from{clip-path:inset(0 0 100% 0 round 28px)}}
@keyframes nb-settle{from{transform:scale(1.25)}}
@keyframes nb-drift{50%{transform:translate(-40px,32px) scale(1.08)}}
@media(min-width:640px){.nb-header{padding:16px 24px 0}.nb-container{padding-inline:32px}.nb-contact-section{padding-inline:24px}.nb-contact-grid{padding:48px}.nb-form{padding:32px}}
@media(min-width:768px){.nb-navlinks{display:flex}.nb-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.nb-featured{grid-column:span 2}.nb-media-featured{aspect-ratio:16/9}.nb-service{grid-template-columns: minmax(260px,5fr) minmax(0,6fr) 32px}.nb-service-arrow{display:block}}
@media(min-width:1024px){.nb-container{padding-inline:32px}.nb-hero-grid{grid-template-columns:7fr 5fr;gap:40px;padding-top:128px}.nb-section-padding{padding-block:112px}.nb-two-col{grid-template-columns:repeat(12,minmax(0,1fr));gap:80px}.nb-about-image{grid-column:span 5}.nb-about-copy{grid-column:span 7}.nb-about-copy-wide{grid-column:span 10}.nb-layout-journey>.nb-sticky-copy,.nb-layout-skills>.nb-sticky-copy{grid-column:span 4}.nb-timeline{grid-column:span 8}.nb-sticky-copy{position:sticky;top:112px}.nb-skills{grid-column:span 8;grid-template-columns:repeat(2,minmax(0,1fr))}.nb-contact-section{padding-top:96px}.nb-contact-grid{grid-template-columns:7fr 5fr;gap:48px;padding:64px}}
@media(min-width:1280px){.nb-project-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.nb-featured{grid-column:span 2}}
@media(max-width:767px){.nb-navlinks{display:none}.nb-nav-cta{padding-inline:16px!important}.nb-contact{border-radius:32px}.nb-bigmail{font-size:clamp(1.35rem,6vw,2rem)}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation:none!important;transition:none!important}.nb-js [data-reveal],.nb-js [data-reveal]:not(.is-in) .nb-h2{opacity:1;transform:none;letter-spacing:-.04em}.nb-js [data-reveal] .nb-bar{width:var(--w,0%)}}
CSS;
    }

    private function darkOrangeDesignerStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Instrument+Sans:wght@400;500;600&display=swap');
html{scroll-behavior:smooth;scroll-padding-top:80px}
:root{--ink:#0f0e0d;--surface:#181614;--paper:#f3efe9;--muted:#a39d94;--ember:#ff5a1f;--line:rgba(243,239,233,.1);--ease:cubic-bezier(.22,1,.36,1)}
*{box-sizing:border-box}
body{margin:0;background:var(--ink);color:var(--paper);font:16px/1.55 "Instrument Sans",ui-sans-serif,system-ui,sans-serif}
a{color:inherit;text-decoration:none}
img{display:block;max-width:100%}
.theme{min-height:100vh;overflow-x:hidden;background:var(--ink);color:var(--paper)}
.font-display{font-family:"Bricolage Grotesque",ui-sans-serif,system-ui,sans-serif}
.content-width{width:min(1280px,100%);margin-inline:auto}
.progress-track{position:fixed;inset:0 0 auto;z-index:60;height:2px}
.progress-bar{width:100%;height:100%;transform:scaleX(0);transform-origin:left;background:var(--ember)}
.nav{position:fixed;inset:0 0 auto;z-index:50;transform:translateY(-100%);border-bottom:1px solid var(--line);background:rgba(15,14,13,.72);backdrop-filter:blur(16px) saturate(1.4);transition:transform .8s var(--ease) .1s}
.nav.nav-in{transform:none}
.nav-inner{display:flex;height:72px;max-width:1280px;align-items:center;justify-content:space-between;gap:24px;margin:auto;padding:0 24px}
.brand{display:flex;align-items:center;gap:12px;border-radius:8px;font-size:18px;font-weight:600}
.brand-mark{display:grid;width:36px;height:36px;place-items:center;border-radius:999px;background:var(--ember);color:var(--ink);font-family:"Bricolage Grotesque",sans-serif;font-size:18px;font-weight:700}
.nav-links{display:none;align-items:center;gap:4px;margin:0;padding:0;list-style:none}
.nav-link{display:block;border-radius:999px;padding:8px 14px;color:var(--muted);transition:color .25s,background .25s}
.nav-link:hover{background:#ffffff0d;color:var(--paper)}
.nav-link.active{background:#ffffff14;color:var(--paper)}
.menu-toggle{display:grid;width:40px;height:40px;place-items:center;border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--paper);cursor:pointer}
.burger{position:relative;display:block;width:16px;height:10px}
.burger i{position:absolute;left:0;width:100%;height:2px;border-radius:2px;background:currentColor;transition:transform .35s var(--ease),top .35s var(--ease)}
.burger i:first-child{top:0}
.burger i:last-child{top:8px}
.burger.open i:first-child{top:4px;transform:rotate(45deg)}
.burger.open i:last-child{top:4px;transform:rotate(-45deg)}
.mobile-navigation{position:absolute;top:100%;right:16px;left:16px;margin:0 0 16px;padding:8px;border:1px solid var(--line);border-radius:16px;background:var(--surface);list-style:none}
.mobile-navigation[hidden]{display:none}
.mobile-navigation a{display:block;border-radius:12px;padding:12px 16px;font-size:18px}
.mobile-navigation a:hover{background:#ffffff0d}
.hero{position:relative;display:flex;min-height:100svh;align-items:center;padding:112px 24px 64px}
.aurora{position:absolute;inset:0;overflow:hidden;pointer-events:none}
.aurora::before{position:absolute;top:5%;right:-10%;width:60vw;height:60vw;max-width:760px;max-height:760px;filter:blur(40px);background:radial-gradient(closest-side,rgba(255,90,31,.22),transparent);content:"";animation:drift 16s ease-in-out infinite alternate}
.hero-inner{position:relative;z-index:1;display:grid;width:min(1280px,100%);align-items:center;gap:56px;margin:auto}
.hero-copy{min-width:0}
.greeting{display:inline-flex;align-items:center;gap:8px;margin:0 0 24px;color:var(--muted);font-size:16px}
.pulse-dot{width:8px;height:8px;border-radius:50%;background:var(--ember);box-shadow:0 0 0 0 rgba(255,90,31,.6);animation:ping 2.2s infinite}
.hero-title{margin:0;font-size:clamp(3.25rem,10vw,8rem);font-weight:600;line-height:.92;letter-spacing:-.04em}
.mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.08em}
.mask-inner{display:inline-block;transform:translateY(110%);transition:transform 1s var(--ease);transition-delay:calc(var(--i)*90ms + 250ms)}
.is-ready .mask-inner{transform:none}
.hero-role{margin:24px 0 0;color:var(--ember);font-size:clamp(1.5rem,3vw,1.875rem);font-weight:500;letter-spacing:-.025em}
.hero-description{max-width:544px;margin:24px 0 0;color:var(--muted);font-size:18px;line-height:1.65}
.hero-cta{margin-top:40px}
.hero-line{opacity:0;transform:translateY(18px);transition:opacity .9s var(--ease),transform .9s var(--ease);transition-delay:calc(var(--i)*90ms + 250ms)}
.is-ready .hero-line{opacity:1;transform:none}
.btn-primary{display:inline-flex;align-items:center;gap:14px;border:0;border-radius:999px;background:var(--ember);padding:9px 9px 9px 26px;color:var(--ink);font-weight:600;cursor:pointer;transition:transform .35s var(--ease),box-shadow .35s}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 14px 34px -10px rgba(255,90,31,.6)}
.btn-arrow{display:grid;width:40px;height:40px;flex:none;place-items:center;border-radius:50%;background:var(--ink);color:var(--paper);transition:transform .35s var(--ease)}
.btn-primary:hover .btn-arrow{transform:translateX(3px) rotate(-45deg)}
.btn-ghost{display:inline-flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:999px;padding:14px 24px;font-weight:500;transition:border-color .3s,background .3s}
.btn-ghost:hover{border-color:var(--ember);background:rgba(255,90,31,.08)}
.hero-portrait{display:flex;justify-content:center}
.portrait-wrap{width:min(100%,380px);perspective:900px}
.portrait{position:relative;overflow:hidden;aspect-ratio:4/5;border:1px solid var(--line);border-radius:999px 999px 28px 28px;background:var(--surface);box-shadow:0 40px 80px -30px rgba(255,90,31,.3);transition:transform .25s ease-out}
.portrait-image{width:100%;height:100%;object-fit:cover}
.portrait-fallback{display:grid;width:100%;height:100%;place-items:center;color:var(--ember);font-size:8rem;font-weight:600}
.section{position:relative;border-top:1px solid var(--line);padding:clamp(5rem,12vw,9rem) 24px}
.h2{margin:0;font-family:"Bricolage Grotesque",sans-serif;font-size:clamp(2.5rem,6vw,4.5rem);font-weight:600;line-height:1;letter-spacing:-.03em}
.about-grid{display:grid;align-items:center;gap:48px}
.about-media{min-width:0}
.img-frame{position:relative;width:100%;max-width:448px;overflow:hidden;border:1px solid var(--line);border-radius:24px;background:var(--surface)}
.img-frame img{width:100%;aspect-ratio:4/5;object-fit:cover}
.about-copy{min-width:0}
.about-copy-wide{max-width:768px;margin-inline:auto}
.about-description{max-width:608px;margin:32px 0 0;white-space:pre-line;color:var(--muted);font-size:18px;line-height:1.75}
.about-copy .btn-ghost{margin-top:40px}
.section-intro{max-width:672px}
.section-description{margin:20px 0 0;color:var(--muted);font-size:18px;line-height:1.6}
.journey-list{margin:64px 0 0;padding:0;border-top:1px solid var(--line);list-style:none}
.jr{border-bottom:1px solid var(--line)}
.jr-head{display:grid;width:100%;align-items:center;grid-template-columns:1fr auto;gap:4px 24px;padding:28px 0;border:0;background:transparent;color:var(--paper);text-align:left;cursor:pointer;transition:padding .4s var(--ease)}
.jr-period{grid-column:1/-1;color:var(--muted);font-size:15px;transition:color .3s}
.jr-main{display:flex;flex-direction:column;gap:4px}
.jr-title{font-size:clamp(1.5rem,3.4vw,2.5rem);font-weight:600;line-height:1.1;letter-spacing:-.025em;transition:transform .5s var(--ease),color .3s}
.jr-company{color:var(--muted)}
.jr:hover .jr-title{transform:translateX(10px)}
.jr:hover .jr-period,.jr.open .jr-period{color:var(--ember)}
.jr-icon{position:relative;width:44px;height:44px;border:1px solid var(--line);border-radius:50%;transition:background .3s,border-color .3s,transform .5s var(--ease)}
.jr-icon::before,.jr-icon::after{position:absolute;top:50%;left:50%;width:14px;height:2px;margin:-1px 0 0 -7px;background:var(--paper);content:"";transition:transform .4s var(--ease),background .3s}
.jr-icon::after{transform:rotate(90deg)}
.jr.open .jr-icon{transform:rotate(180deg);border-color:var(--ember);background:var(--ember)}
.jr.open .jr-icon::before,.jr.open .jr-icon::after{background:var(--ink)}
.jr.open .jr-icon::after{transform:none}
.jr-body{display:grid;grid-template-rows:0fr;transition:grid-template-rows .55s var(--ease)}
.jr-body>div{overflow:hidden}
.jr.open .jr-body{grid-template-rows:1fr}
.jr-desc{max-width:640px;margin:0;padding:0 0 36px;color:var(--muted);font-size:17px;line-height:1.75;opacity:0;transform:translateY(8px);transition:opacity .5s .1s,transform .5s var(--ease) .1s}
.jr.open .jr-desc{opacity:1;transform:none}
.skills-grid{display:grid;gap:32px 64px;margin:56px 0 0;padding:0;list-style:none}
.skill-head{display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin-bottom:12px}
.skill-head>span:first-child{font-size:18px;font-weight:500}
.skill-percent{color:var(--muted);font-size:14px;font-variant-numeric:tabular-nums}
.skill-track{height:6px;overflow:hidden;border-radius:999px;background:rgba(255,255,255,.07)}
.skill-bar{display:block;width:var(--w);height:100%;transform:scaleX(0);transform-origin:left;border-radius:inherit;background:linear-gradient(90deg,#ff8a3d,var(--ember));transition:transform 1.3s var(--ease) .2s}
.skill.is-in .skill-bar{transform:none}
.work-heading{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:32px}
.filters{display:flex;flex-wrap:wrap;gap:8px}
.chip{border:1px solid var(--line);border-radius:999px;background:transparent;padding:8px 18px;color:var(--muted);font:inherit;font-size:15px;cursor:pointer;transition:all .3s var(--ease)}
.chip:hover{border-color:#f3efe94d;color:var(--paper)}
.chip.active{border-color:var(--paper);background:var(--paper);color:var(--ink)}
.work-list{position:relative;margin-top:56px;border-top:1px solid var(--line)}
.work-row{border-bottom:1px solid var(--line)}
.work-row[hidden]{display:none}
.work-link{display:flex;flex-direction:column;gap:16px;border-radius:8px;padding:24px 0}
.work-thumb{width:100%;aspect-ratio:16/10;border:1px solid var(--line);border-radius:20px;object-fit:cover}
.work-title{color:var(--paper);font-size:clamp(1.6rem,5.5vw,4rem);font-weight:600;line-height:1;letter-spacing:-.03em;transition:color .35s,transform .6s var(--ease)}
.work-meta{display:inline-flex;align-items:center;gap:20px;color:var(--muted)}
.work-arrow{color:var(--ember);font-size:24px;transition:transform .5s var(--ease)}
.work-preview{position:fixed;top:0;left:0;z-index:40;display:none;width:340px;transform:translate3d(-1000px,-1000px,0);pointer-events:none;opacity:0;transition:opacity .35s,transform .28s var(--ease)}
.work-preview.show{opacity:1}
.work-preview img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .35s}
.work-preview img.on{opacity:1}
.work-preview::before{display:block;aspect-ratio:4/3;border:1px solid var(--line);border-radius:20px;background:var(--surface);box-shadow:0 30px 60px -20px #000b;content:""}
.contact-content{max-width:896px}
.contact-details{display:flex;flex-wrap:wrap;gap:12px 40px;margin-top:40px;color:var(--muted)}
.contact-link{color:var(--paper);background:linear-gradient(var(--ember),var(--ember)) no-repeat 0 100%/100% 1px;transition:background-size .35s var(--ease),color .25s}
.contact-link:hover{color:var(--ember);background-size:100% 100%}
.contact-form{margin-top:48px;border:1px solid var(--line);border-radius:24px;background:var(--surface);padding:24px;text-align:left}
.contact-form h3{margin:0 0 24px;color:var(--paper);font-size:24px}
.contact-form label{display:block;margin-top:20px;color:var(--muted);font-size:14px;font-weight:500}
.contact-form input,.contact-form textarea{display:block;width:100%;margin-top:8px;border:1px solid var(--line);border-radius:12px;background:var(--ink);padding:12px 16px;color:var(--paper);font:inherit}
.contact-form input:focus,.contact-form textarea:focus{outline:2px solid rgba(255,90,31,.25);border-color:var(--ember)}
.contact-form button{margin-top:24px}
.form-status{color:var(--ember);font-size:14px}
.hidden-frame{display:none}
.footer{border-top:1px solid var(--line);padding:48px 24px}
.footer-inner{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;color:var(--muted);font-size:14px}
.footer-brand{color:var(--paper);font-size:16px;font-weight:600}
.footer a:hover{color:var(--paper)}
.reveal{opacity:0;transform:translateY(24px);transition:opacity .8s var(--ease),transform .8s var(--ease);transition-delay:var(--d,0ms)}
.reveal.is-in{opacity:1;transform:none}
.nav-link:focus-visible,.menu-toggle:focus-visible,.jr-head:focus-visible,.a:focus-visible{outline:2px solid var(--ember);outline-offset:3px}
@keyframes drift{to{transform:translate(-6%,8%) scale(1.12)}}
@keyframes ping{70%{box-shadow:0 0 0 10px rgba(255,90,31,0)}100%{box-shadow:0 0 0 0 rgba(255,90,31,0)}}
@media(min-width:768px){.nav-links{display:flex}.menu-toggle{display:none}.mobile-navigation{display:none}.jr-head{grid-template-columns:220px 1fr auto;gap:32px;padding:36px 0}.jr-period{grid-column:auto}.jr-desc{margin-left:calc(220px + 2rem)}.skills-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.hero-inner{grid-template-columns:7fr 5fr;gap:56px}.hero-portrait{justify-content:flex-end}.about-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:64px}.about-media{grid-column:span 5}.about-copy{grid-column:span 7}.about-copy-wide{grid-column:3/span 8}.work-link{flex-direction:row;align-items:center;justify-content:space-between;gap:32px;padding:40px 0;transition:padding .5s var(--ease)}.work-row:hover .work-link{padding-inline:20px}.work-row:hover .work-title{color:var(--ember)}.work-row:hover .work-arrow{transform:translate(4px,-4px)}.work-row:hover .work-meta{color:var(--paper)}.work-thumb{display:none}.work-preview{display:block}.work-preview.show{opacity:1}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.theme *, .theme *::before,.theme *::after{animation:none!important;transition-duration:.01ms!important;transition-delay:0ms!important}.reveal,.hero-line,.mask-inner,.nav{opacity:1!important;transform:none!important}.skill-bar{transform:none!important}}
CSS;
    }

    private function emeraldMinimalistStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=DM+Sans:wght@400;500;600;700&display=swap');
:root{--em-bg:#06231e;--em-deep:#041a16;--em-section:#05201b;--em-green:#5cf0bd;--em-mint:#9bf0d2;--em-text:#f0fff9;--em-muted:#a9c9bf;--em-ease:cubic-bezier(.22,1,.36,1)}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:88px}
body{margin:0;background:var(--em-bg);color:var(--em-text);font:16px/1.55 "DM Sans",ui-sans-serif,system-ui,sans-serif}
a{color:inherit;text-decoration:none}
button,input,textarea{font:inherit}
img{display:block;max-width:100%}
.folio{min-height:100vh;overflow:hidden;background:var(--em-bg);color:var(--em-text)}
.display{font-family:"Bricolage Grotesque","DM Sans",ui-sans-serif,system-ui,sans-serif}
.em-container{width:min(1280px,100% - 48px);margin-inline:auto}
.em-progress-track{position:fixed;inset:0 0 auto;z-index:100;height:3px}
.em-progress-bar{width:100%;height:100%;transform:scaleX(0);transform-origin:left;background:var(--em-green)}
.em-navbar{position:fixed;inset:0 0 auto;z-index:90;border-bottom:1px solid transparent;transition:background .35s,border-color .35s,backdrop-filter .35s}
.em-navbar.is-scrolled{border-color:rgba(255,255,255,.08);background:rgba(4,26,22,.84);backdrop-filter:blur(18px)}
.em-nav-inner{width:min(1280px,100% - 48px);height:78px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:24px}
.em-brand{display:inline-flex;align-items:center;gap:12px;min-width:150px}
.em-logo-mark{display:grid;width:40px;height:40px;place-items:center;border-radius:12px;background:linear-gradient(135deg,#00a388,#004d40);color:white;font-size:18px;font-weight:700;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:transform .5s}
.em-brand:hover .em-logo-mark{transform:rotate(-8deg) scale(1.05)}
.em-logo-text{font-size:18px;font-weight:700}
.em-desktop-nav{display:flex;align-items:center;gap:30px}
.em-nav-link{padding:8px 16px;border-radius:999px;color:rgba(255,255,255,.75);font-size:14px;font-weight:500;transition:color .2s,background .2s}
.em-nav-link:hover,.em-nav-link.active{background:rgba(255,255,255,.1);color:white}
.em-menu-toggle{display:none;width:44px;height:44px;align-items:center;justify-content:center;border:0;border-radius:12px;background:rgba(255,255,255,.1);color:white;cursor:pointer}
.em-burger{display:grid;gap:4px}
.em-burger i{display:block;width:18px;height:2px;border-radius:3px;background:currentColor;transition:transform .3s,opacity .3s}
.em-menu-toggle[aria-expanded=true] .em-burger i:first-child{transform:translateY(6px) rotate(45deg)}
.em-menu-toggle[aria-expanded=true] .em-burger i:nth-child(2){opacity:0}
.em-menu-toggle[aria-expanded=true] .em-burger i:last-child{transform:translateY(-6px) rotate(-45deg)}
.em-mobile-nav{position:absolute;top:calc(100% + 1px);left:0;right:0;display:grid;gap:4px;padding:12px 24px 20px;border-bottom:1px solid rgba(255,255,255,.1);background:rgba(4,26,22,.97)}
.em-mobile-nav[hidden]{display:none}
.em-mobile-link{padding:12px 8px;color:#bfe0d6;font-weight:600}
.em-mobile-link:hover{color:var(--em-green)}
.em-hero{position:relative;display:flex;min-height:100svh;align-items:center;overflow:hidden;padding:112px 0 80px;background:radial-gradient(ellipse at 70% 50%,rgba(0,105,92,.17),transparent 48%),var(--em-bg)}
.em-hero-bg{position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,163,136,.08),transparent 55%)}
.em-hero-grid,.em-hero-grid:before{position:absolute;inset:0;pointer-events:none}
.em-hero-grid{background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);background-size:56px 56px;mask-image:radial-gradient(ellipse at center,#000 20%,transparent 76%)}
.em-orb{position:absolute;border-radius:50%;filter:blur(90px);pointer-events:none}
.em-orb-a{top:-140px;left:-120px;width:560px;height:560px;background:#00a388;opacity:.35;animation:em-drift 18s ease-in-out infinite}
.em-orb-b{right:-100px;bottom:-120px;width:480px;height:480px;background:#2fbf9b;opacity:.23;animation:em-drift 22s ease-in-out infinite reverse}
.em-orb-c{top:40%;left:45%;width:320px;height:320px;background:#9bf0d2;opacity:.1;animation:em-drift 26s ease-in-out infinite}
.em-opacity-25{opacity:.25}.em-opacity-30{opacity:.3}.em-opacity-40{opacity:.4}.em-opacity-50{opacity:.5}.em-opacity-60{opacity:.6}
.em-hero-layout{position:relative;z-index:1;display:grid;grid-template-columns:7fr 5fr;align-items:center;gap:48px}
.em-hero-copy{max-width:720px}
.em-greeting{display:inline-flex;align-items:center;gap:12px;margin:0 0 24px;padding:9px 15px;border:1px solid rgba(155,240,210,.2);border-radius:999px;background:rgba(255,255,255,.04);color:var(--em-mint);font-size:14px;font-weight:600}
.em-ping{position:relative;width:9px;height:9px;border-radius:50%;background:var(--em-green)}
.em-ping i:first-child{position:absolute;inset:0;border-radius:50%;background:var(--em-green);animation:em-ping 1.8s ease-out infinite}
.em-ping i:last-child{position:absolute;inset:0;border-radius:50%;background:var(--em-green)}
.em-hero-title{margin:0 0 18px;font-size:48px;font-weight:700;line-height:1.02;letter-spacing:-.03em}
.em-word-mask{display:inline-block;overflow:hidden;padding-bottom:.12em;margin-bottom:-.12em;margin-right:.12em;vertical-align:top}
.em-word{display:inline-block;transform:translateY(110%);animation:em-word-up 1s var(--em-ease) forwards;animation-delay:var(--delay,0s)}
.em-hero-role{margin:0 0 18px;background:linear-gradient(100deg,#5cf0bd 20%,#d4fff0 48%,#5cf0bd 74%);background-size:220% 100%;background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:clamp(1.25rem,2vw,2rem);font-weight:600;animation:em-hero-in .9s var(--em-ease) forwards,em-shimmer 5s linear 1.8s infinite;animation-delay:var(--delay,0s),1.8s}
.em-hero-description{max-width:600px;margin:0;color:var(--em-muted);font-size:clamp(1rem,1.5vw,1.15rem);line-height:1.8}
.em-hero-cta{margin-top:28px}
.em-cta{display:inline-flex;align-items:center;justify-content:center;gap:12px;padding:15px 25px;border:0;border-radius:999px;background:var(--em-green);color:#04201a;font-size:14px;font-weight:700;box-shadow:0 10px 30px rgba(92,240,189,.25);transition:transform .3s,box-shadow .3s;cursor:pointer}
.em-cta:hover{transform:translateY(-3px);box-shadow:0 14px 40px rgba(92,240,189,.45)}
.em-cta span{font-size:18px}
.em-hero-in{opacity:0;transform:translateY(22px);animation:em-hero-in .9s var(--em-ease) forwards;animation-delay:var(--delay,0s)}
.em-hero-portrait{display:flex;justify-content:center}
.em-portrait-wrap{position:relative;width:min(380px,75vw);aspect-ratio:1}
.em-ring{position:absolute;border:1px dashed rgba(155,240,210,.35);border-radius:50%}
.em-ring-1{inset:-16px;animation:em-spin 40s linear infinite}
.em-ring-2{inset:-40px;border-color:rgba(155,240,210,.15);animation:em-spin 60s linear infinite reverse}
.em-blob{position:absolute;inset:16px;display:grid;overflow:hidden;place-items:center;border:1px solid rgba(255,255,255,.1);border-radius:58% 42% 60% 40% / 48% 55% 45% 52%;background:linear-gradient(135deg,#0e8f7c,#06352d);box-shadow:0 30px 80px rgba(0,0,0,.45);animation:em-morph 12s ease-in-out infinite}
.em-blob img{position:absolute;width:100%;height:100%;object-fit:cover}
.em-hero-fallback{display:grid;justify-items:center;gap:12px;color:#bdf3e0;font-size:12px;font-weight:700;letter-spacing:.15em}
.em-hero-fallback span:first-child{font-size:60px}
.em-dot{position:absolute;z-index:2;border-radius:50%;background:var(--em-green);box-shadow:0 0 24px rgba(92,240,189,.8);animation:em-float 5s ease-in-out infinite}
.em-dot-1{top:6%;right:8%;width:14px;height:14px}
.em-dot-2{bottom:14%;left:-2%;width:9px;height:9px;animation-delay:1s}
.em-dot-3{right:14%;bottom:2%;width:18px;height:18px;background:#2fbf9b;animation-delay:.5s}
.em-scroll-cue{position:absolute;bottom:32px;left:50%;display:flex;transform:translateX(-50%);color:rgba(255,255,255,.55)}
.em-scroll-cue>span{display:flex;width:24px;height:40px;justify-content:center;padding-top:8px;border:1px solid currentColor;border-radius:99px}
.em-scroll-cue i{width:4px;height:8px;border-radius:99px;background:currentColor;animation:em-wheel 1.8s ease-in-out infinite}
.em-marquee{position:relative;overflow:hidden;border-block:1px solid #0a7f70;background:#00695c;padding:20px 0}
.em-marquee-track{display:flex;width:max-content;align-items:center;gap:48px;white-space:nowrap;animation:em-marquee 32s linear infinite}
.em-marquee:hover .em-marquee-track{animation-play-state:paused}
.em-marquee-track>span{display:flex;align-items:center;gap:48px;font-size:19px;font-weight:600}
.em-marquee-track b{color:var(--em-mint);font-family:Arial,sans-serif;font-size:17px}
.em-section{position:relative;display:flex;min-height:100svh;flex-direction:column;justify-content:center;overflow:hidden;padding:112px 0;border-top:1px solid rgba(255,255,255,.05)}
.em-section>.em-hero-grid{inset:0}
.em-about,.em-skills,.em-contact{background:var(--em-bg)}
.em-journey,.em-projects{background:var(--em-section)}
.em-about-layout{position:relative;z-index:1;display:grid;grid-template-columns:5fr 7fr;align-items:center;gap:64px}
.em-about-image{position:relative;display:flex;justify-content:center}
.em-about-glow{position:absolute;inset:-16px;border-radius:40px;background:linear-gradient(135deg,#00a388,#9bf0d2);opacity:.28;filter:blur(2px);transform:rotate(3deg)}
.em-about-frame{position:relative;width:min(360px,100%);height:480px;overflow:hidden;border:1px solid rgba(255,255,255,.1);border-radius:32px;background:#0b3b33;box-shadow:0 30px 80px rgba(0,0,0,.45)}
.em-about-frame img{width:100%;height:100%;object-fit:cover;transition:transform .7s}
.em-about-frame:hover img{transform:scale(1.05)}
.em-about-copy{position:relative}
.em-about-copy-wide{grid-column:1/-1;max-width:760px;margin:auto;text-align:center}
.em-section-tag{display:inline-flex;align-items:center;gap:8px;padding:7px 16px;border:1px solid rgba(255,255,255,.15);border-radius:999px;background:rgba(255,255,255,.07);color:var(--em-mint);font-size:14px;font-weight:600}
.em-section-tag:before{width:6px;height:6px;border-radius:50%;background:currentColor;content:""}
.em-section-title{margin:18px 0 0;font-size:clamp(2.5rem,6vw,4.5rem);font-weight:700;line-height:1.05;letter-spacing:-.05em}
.em-about-description,.em-section-subtitle{max-width:700px;margin:24px 0 0;color:var(--em-muted);font-size:clamp(1rem,1.5vw,1.125rem);line-height:1.75;white-space:pre-line}
.em-about-copy-wide .em-about-description{margin-inline:auto}
.em-about-download{margin-top:28px}
.em-journey-content,.em-skills-content,.em-projects-content{position:relative;z-index:1;width:min(1152px,100% - 48px);margin-inline:auto}
.em-section-intro{max-width:720px;margin:0 auto 60px;text-align:center}
.em-section-subtitle{margin-inline:auto}
.em-timeline{position:relative;padding-left:56px}
.em-timeline-items{display:grid;gap:32px}
.em-spine{position:absolute;top:8px;bottom:8px;left:19px;width:2px;transform:scaleY(0);transform-origin:top;background:linear-gradient(#5cf0bd,#00a388,transparent);transition:transform 1.6s var(--em-ease)}
.em-spine.is-in{transform:scaleY(1)}
.em-timeline-item{position:relative}
.em-timeline-marker{position:absolute;top:28px;left:-56px;display:grid;width:40px;height:40px;place-items:center}
.em-timeline-marker i{width:16px;height:16px;border:3px solid var(--em-green);border-radius:50%;background:var(--em-bg);animation:em-pulse 2.6s ease-out infinite}
.em-journey-card,.em-skill-card{border:1px solid rgba(255,255,255,.1);border-radius:24px;background:rgba(255,255,255,.05);backdrop-filter:blur(12px);transition:transform .4s,border-color .4s,background .4s,box-shadow .4s}
.em-journey-card{padding:28px}
.em-journey-card:hover,.em-skill-card:hover{transform:translateY(-4px);border-color:rgba(92,240,189,.4);background:rgba(255,255,255,.09);box-shadow:0 24px 50px rgba(0,0,0,.3)}
.em-journey-meta{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px}
.em-period,.em-project-category{padding:5px 14px;border:1px solid rgba(255,255,255,.1);border-radius:999px;background:rgba(255,255,255,.07);color:var(--em-mint);font-size:14px;font-weight:600}
.em-company,.em-journey-description{color:var(--em-muted)}
.em-journey-title{margin:14px 0 0;font-size:24px;font-weight:600;letter-spacing:-.03em}
.em-journey-description{margin:10px 0 0;font-size:15px;line-height:1.7}
.em-skills-grid{display:grid;grid-template-columns:1fr;gap:24px}
.em-skill-card{display:flex;flex-direction:column;align-items:center;gap:24px;padding:32px}
.em-skill-card.is-in{box-shadow:0 10px 35px rgba(0,0,0,.12)}
.em-meter{position:relative;width:176px;height:176px;transition:transform .5s var(--em-ease)}
.em-skill-card:hover .em-meter{transform:scale(1.04)}
.em-meter svg{position:relative;width:100%;height:100%;overflow:visible}
.em-orbit{transform-origin:60px 60px;animation:em-spin 30s linear infinite}
.em-skill-card:hover .em-orbit{animation-duration:8s}
.em-arc{stroke-dashoffset:var(--circ);filter:drop-shadow(0 0 6px rgba(92,240,189,.55));transition:stroke-dashoffset 2s var(--em-ease);transition-delay:var(--skill-delay,0s)}
.em-skill-card.is-in .em-arc{stroke-dashoffset:var(--offset)}
.em-tip{transform-origin:60px 60px;filter:drop-shadow(0 0 6px rgba(92,240,189,.9));transition:transform 2s var(--em-ease);transition-delay:var(--skill-delay,0s)}
.em-skill-card.is-in .em-tip{transform:rotate(var(--degrees))}
.em-skill-count{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;letter-spacing:-.04em;font-variant-numeric:tabular-nums}
.em-skill-count>span{margin-left:2px;color:var(--em-green);font-size:20px}
.em-skill-label{font-size:19px;font-weight:600;text-align:center}
.em-halo{position:absolute;inset:14px;border:1px solid rgba(92,240,189,.35);border-radius:50%;opacity:0}
.em-skill-card.is-in .em-halo{animation:em-halo 3.2s ease-out infinite;animation-delay:calc(var(--skill-delay,0s) + 1.6s)}
.em-projects-content{width:min(1280px,100% - 48px)}
.em-projects .em-section-intro{margin-bottom:52px}
.em-filters{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin:-16px 0 36px}
.em-filter{padding:10px 20px;border:1px solid rgba(255,255,255,.15);border-radius:999px;background:rgba(255,255,255,.05);color:#bdf3e0;font-size:14px;font-weight:600;cursor:pointer;transition:all .3s}
.em-filter:hover{border-color:rgba(92,240,189,.6);color:white}
.em-filter.active{border-color:var(--em-green);background:var(--em-green);color:#04201a;box-shadow:0 10px 30px rgba(92,240,189,.25)}
.em-project-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:32px}
.em-project-card{display:flex;overflow:hidden;flex-direction:column;border:1px solid rgba(255,255,255,.1);border-radius:24px;background:rgba(255,255,255,.05);transition:transform .45s,border-color .45s,box-shadow .45s}
.em-project-card[hidden]{display:none}
.em-project-card:hover{transform:translateY(-8px);border-color:rgba(92,240,189,.4);box-shadow:0 30px 60px rgba(0,0,0,.4)}
.em-project-image{position:relative;display:flex;height:256px;overflow:hidden;align-items:center;justify-content:center;background:linear-gradient(135deg,#0b4a40,#06352d)}
.em-project-image>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .7s}
.em-project-card:hover .em-project-image>img{transform:scale(1.1)}
.em-project-fallback{font-size:50px;transition:transform .5s}
.em-project-card:hover .em-project-fallback{transform:scale(1.2) rotate(6deg)}
.em-project-overlay{position:absolute;inset:0;display:flex;align-items:flex-end;padding:20px;background:linear-gradient(0deg,rgba(4,32,26,.9),rgba(4,32,26,.3),transparent);opacity:0;transition:opacity .5s}
.em-project-card:hover .em-project-overlay{opacity:1}
.em-project-overlay a{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;background:var(--em-green);color:#04201a;font-size:14px;font-weight:700}
.em-project-copy{display:grid;justify-items:start;gap:12px;padding:24px}
.em-project-copy h3{margin:0;font-size:24px;font-weight:600;letter-spacing:-.03em}
.em-contact-container{position:relative;z-index:1;width:min(896px,100% - 48px)}
.em-contact-card{position:relative;display:flex;overflow:hidden;flex-direction:column;align-items:center;gap:32px;padding:80px 64px;border:1px solid rgba(255,255,255,.1);border-radius:40px;background:linear-gradient(135deg,#0b5a4d,#06352d);text-align:center;box-shadow:0 40px 100px rgba(0,0,0,.4)}
.em-contact-card>.em-orb,.em-contact-card>.em-hero-grid{z-index:0}
.em-contact-heading{position:relative;z-index:1}
.em-contact-description{max-width:600px;margin:20px auto 0;color:#bfe0d6;font-size:18px}
.em-contact-form{position:relative;z-index:1;width:100%;max-width:672px;display:grid;gap:18px;padding:28px;border:1px solid rgba(155,240,210,.2);border-radius:24px;background:rgba(4,26,22,.7);text-align:left;box-shadow:0 24px 60px rgba(0,0,0,.3)}
.em-contact-form h3{margin:0;font-size:24px}
.em-contact-form label{display:grid;gap:8px;color:#bfe0d6;font-size:14px;font-weight:600}
.em-contact-form input,.em-contact-form textarea{width:100%;border:1px solid rgba(155,240,210,.2);border-radius:12px;background:#06231e;padding:12px 16px;color:var(--em-text);outline:0}
.em-contact-form input:focus,.em-contact-form textarea:focus{border-color:var(--em-green);box-shadow:0 0 0 3px rgba(92,240,189,.2)}
.em-contact-form textarea{resize:vertical}
.em-contact-form button{justify-self:start}
.em-form-status{margin:0;color:var(--em-mint);font-size:14px;font-weight:600}
.em-email-link{position:relative;z-index:1;display:inline-flex;max-width:100%;align-items:center;gap:12px;overflow-wrap:anywhere;border-radius:999px;background:var(--em-green);padding:16px 28px;color:#04201a;font-weight:700;box-shadow:0 10px 30px rgba(92,240,189,.25);transition:transform .3s}
.em-email-link:hover{transform:translateY(-3px)}
.em-contact-meta{position:relative;z-index:1;display:flex;flex-wrap:wrap;justify-content:center;gap:12px 40px;color:#bfe0d6;font-size:14px;font-weight:500}
.em-hidden-frame{display:none}
.em-footer{border-top:1px solid rgba(255,255,255,.1);background:var(--em-deep);padding:38px 0}
.em-footer>.em-container{display:flex;align-items:center;justify-content:space-between;gap:16px}
.em-footer .display{font-size:18px;font-weight:600}
.em-footer p{margin:0;color:#86a79c;font-size:14px}
.em-reveal{opacity:0;transform:translateY(28px);transition:opacity .8s var(--em-ease),transform .8s var(--em-ease)}
.em-reveal-left{transform:translateX(-36px)}
.em-reveal.is-in{opacity:1;transform:none}
.em-timeline-item{transition-delay:var(--reveal-delay,0ms)}
.folio :where(a,button,input,textarea):focus-visible{outline:3px solid var(--em-green);outline-offset:3px}
@keyframes em-drift{33%{transform:translate(60px,40px) scale(1.1)}66%{transform:translate(-40px,60px) scale(.95)}}
@keyframes em-hero-in{to{opacity:1;transform:none}}
@keyframes em-word-up{to{transform:translateY(0)}}
@keyframes em-shimmer{to{background-position:-220% 0}}
@keyframes em-morph{25%{border-radius:42% 58% 45% 55% / 58% 42% 58% 42%}50%{border-radius:55% 45% 38% 62% / 42% 60% 40% 58%}75%{border-radius:40% 60% 58% 42% / 55% 45% 55% 45%}}
@keyframes em-spin{to{transform:rotate(360deg)}}
@keyframes em-float{50%{transform:translateY(-16px)}}
@keyframes em-wheel{80%{transform:translateY(12px);opacity:0}100%{opacity:0}}
@keyframes em-marquee{to{transform:translateX(-50%)}}
@keyframes em-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes em-pulse{70%,100%{box-shadow:0 0 0 12px rgba(92,240,189,0)}}
@keyframes em-halo{0%{transform:scale(.85);opacity:.7}100%{transform:scale(1.3);opacity:0}}
@media(min-width:640px){.em-hero-title{font-size:72px}.em-skills-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:768px){.em-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.em-hero-layout{grid-template-columns:repeat(12,minmax(0,1fr));gap:56px}.em-hero-copy{grid-column:span 7}.em-hero-portrait{grid-column:span 5}.em-hero-title{font-size:88px}.em-about-layout{grid-template-columns:repeat(12,minmax(0,1fr));gap:64px}.em-about-image{grid-column:span 5}.em-about-copy{grid-column:span 7}.em-about-copy.em-about-copy-wide{grid-column:3/span 8}.em-skills-grid,.em-project-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:1023px){.em-hero-layout{grid-template-columns:1fr;gap:54px}.em-hero-copy{order:2;text-align:center;margin:auto}.em-hero-portrait{order:1}.em-greeting{margin-inline:auto}.em-hero-description{margin-inline:auto}.em-hero-cta{display:flex;justify-content:center}.em-about-layout{grid-template-columns:1fr;gap:48px}.em-about-image{order:1}.em-about-copy{order:2}.em-about-copy-wide{grid-column:auto}.em-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767px){.em-container,.em-journey-content,.em-skills-content,.em-projects-content,.em-contact-container{width:calc(100% - 40px)}.em-nav-inner{width:calc(100% - 40px)}.em-desktop-nav{display:none}.em-menu-toggle{display:flex}.em-hero{min-height:auto;padding:124px 0 96px}.em-portrait-wrap{width:min(380px,75vw)}.em-ring-2{inset:-28px}.em-scroll-cue{display:none}.em-section{min-height:auto;padding:88px 0}.em-about-frame{height:420px}.em-about-copy{text-align:center}.em-about-description{margin-inline:auto}.em-section-intro{margin-bottom:44px}.em-timeline{padding-left:42px}.em-spine{left:10px}.em-timeline-marker{left:-42px;width:22px}.em-journey-card{padding:22px}.em-company{font-size:14px}.em-skills-grid,.em-project-grid{grid-template-columns:1fr;gap:20px}.em-skill-card{padding:28px}.em-contact-card{gap:28px;padding:56px 22px;border-radius:30px}.em-contact-form{padding:20px}.em-contact-meta{gap:10px 20px}.em-footer>.em-container{flex-direction:column;text-align:center}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.folio *,.folio *:before,.folio *:after{animation:none!important;scroll-behavior:auto!important;transition-duration:.01ms!important;transition-delay:0ms!important}.em-reveal{opacity:1;transform:none}.em-arc{stroke-dashoffset:var(--offset)}.em-tip{transform:rotate(var(--degrees))}}
CSS;
    }

    private function ivoryEditorialStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap');
:root{--paper:#fbfaf7;--paper-2:#f3f1ea;--paper-3:#e9e6dc;--tint:#edf0fc;--ink:#20211f;--muted:#62635d;--line:#20211f24;--accent:#244fce;--ease:cubic-bezier(.2,.7,.2,1)}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:6rem}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.6 "Hanken Grotesk",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
a{color:inherit;text-decoration:none}
button,input,textarea{font:inherit}
img{display:block;max-width:100%}
.ie-root{position:relative;min-height:100vh;overflow:hidden;background:var(--paper);color:var(--ink)}
.ie-serif{font-family:"Instrument Serif","Iowan Old Style",Georgia,"Times New Roman",serif;font-weight:400;letter-spacing:-.015em}
.ie-glow{position:fixed;inset:0;z-index:5;pointer-events:none;background:radial-gradient(560px circle at var(--gx,72%) var(--gy,18%),#244fce12,transparent 65%)}
.ie-navwrap{position:fixed;inset:0 0 auto;z-index:50;padding:16px 24px 0;transition:transform .55s var(--ease)}
.ie-navwrap.is-hidden{transform:translateY(-130%)}
.ie-nav{position:relative;display:flex;width:min(1280px,100%);height:56px;align-items:center;justify-content:space-between;gap:16px;margin:auto;padding:0 8px 0 12px;border:1px solid transparent;border-radius:999px;background:rgba(251,250,247,.55);backdrop-filter:blur(16px) saturate(1.3);-webkit-backdrop-filter:blur(16px) saturate(1.3);transition:border-color .35s,background-color .35s,box-shadow .35s}
.ie-nav.is-scrolled{border-color:var(--line);background:rgba(251,250,247,.88);box-shadow:0 14px 34px -18px rgba(32,33,31,.45)}
.ie-brand{display:flex;min-width:0;align-items:center;gap:12px}
.ie-logo-image{width:auto;max-width:112px;height:32px;object-fit:contain}
.ie-mark{display:inline-flex;width:34px;height:34px;flex:none;align-items:center;justify-content:center;border-radius:50%;background:var(--accent);color:white;font-family:"Instrument Serif",Georgia,serif;font-size:19px;transition:transform .5s cubic-bezier(.3,1.5,.4,1)}
.ie-brand:hover .ie-mark{transform:rotate(-12deg) scale(1.08)}
.ie-logo-text{overflow:hidden;font-size:14px;font-weight:600;letter-spacing:.025em;text-overflow:ellipsis;white-space:nowrap}
.ie-navlinks{display:flex;align-items:center;gap:4px;margin:0;padding:0;list-style:none}
.ie-navlink{display:inline-block;border-radius:999px;padding:8px 16px;color:var(--muted);font-size:14px;font-weight:500;transition:color .25s,background-color .25s}
.ie-navlink:hover{color:var(--ink)}
.ie-navlink[data-active=true]{background:var(--paper-2);color:var(--ink)}
.ie-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;gap:9px;border:1px solid transparent;border-radius:999px;padding:15px 26px;font-size:14px;font-weight:600;line-height:1.2;cursor:pointer;background-repeat:no-repeat;background-position:left center;background-size:0 100%;transition:background-size .55s var(--ease),color .3s,box-shadow .35s,border-color .3s,transform .3s var(--ease)}
.ie-btn:hover{background-size:100% 100%}
.ie-btn:active{transform:scale(.98)}
.ie-btn[disabled]{opacity:.7;pointer-events:none}
.ie-btn-accent{background-color:var(--accent);background-image:linear-gradient(var(--ink),var(--ink));color:white;box-shadow:0 10px 24px -12px var(--accent)}
.ie-btn-ink{background-color:var(--ink);background-image:linear-gradient(var(--accent),var(--accent));color:var(--paper)}
.ie-btn-ghost{border-color:var(--line);background-color:transparent;background-image:linear-gradient(var(--paper-2),var(--paper-2));color:var(--ink)}
.ie-btn-ghost:hover{border-color:var(--ink)}
.ie-nav-cta{padding:10px 20px;font-size:13px}
.ie-arrow{transition:transform .35s var(--ease)}
.ie-btn:hover .ie-arrow,.ie-bigmail:hover .ie-arrow{transform:translate(3px,-3px)}
.ie-ulink{padding-bottom:3px;background:linear-gradient(currentColor,currentColor) left 100%/0 1.5px no-repeat;transition:background-size .45s var(--ease),color .25s}
.ie-ulink:hover{background-size:100% 1.5px}
.ie-hero{position:relative;isolation:isolate;display:grid;width:min(1280px,100%);min-height:100svh;grid-template-columns:1.1fr .9fr;align-items:center;gap:56px;margin:auto;padding:128px 40px 96px}
.ie-hero-copy{position:relative;z-index:2}
.ie-rings{position:absolute;top:50%;right:-224px;z-index:-1;width:832px;height:832px;transform:translateY(-50%);pointer-events:none}
.ie-rings span{position:absolute;inset:0;border:1px solid var(--line);border-radius:50%;animation:ie-breathe 9s ease-in-out infinite}
.ie-rings span:nth-child(2){inset:14%;animation-delay:-3s}
.ie-rings span:nth-child(3){inset:28%;border-color:color-mix(in srgb,var(--accent) 35%,transparent);animation-delay:-6s}
.ie-chip{display:inline-flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:999px;background:rgba(255,255,255,.6);padding:7px 16px 7px 14px;font-size:14px;font-weight:600}
.ie-pulse{position:relative;width:8px;height:8px;border-radius:50%;background:#22a55a}
.ie-pulse:after{position:absolute;inset:0;border-radius:inherit;background:inherit;content:"";animation:ie-ping 2.2s cubic-bezier(0,0,.2,1) infinite}
.ie-hero-name{overflow-wrap:anywhere;margin:28px 0 0;font-size:clamp(60px,11vw,148px);line-height:.9;letter-spacing:-.03em}
.ie-sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;clip-path:inset(50%)}
.ie-mask{display:inline-block;overflow:hidden;margin-right:.2em;padding-bottom:.14em;margin-bottom:-.14em;vertical-align:top}
.ie-word{display:inline-block}
.ie-js .ie-word{transform:translateY(112%);animation:ie-rise 1.1s cubic-bezier(.2,.8,.2,1) forwards;animation-delay:calc(.15s + var(--index,0)*.11s)}
.ie-hero-role{margin:24px 0 0;color:var(--accent);font-size:clamp(30px,4vw,40px);font-style:italic;line-height:1.2}
.ie-hero-description{max-width:544px;margin:24px 0 0;color:var(--muted);font-size:18px;line-height:1.75;white-space:pre-line}
.ie-hero-ctas{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:40px}
.ie-social-list,.ie-contact-socials{display:flex;flex-wrap:wrap;gap:12px 24px;margin:36px 0 0;padding:0;list-style:none}
.ie-social-list a,.ie-contact-socials a{color:var(--muted);font-size:14px;font-weight:600}
.ie-portrait{position:relative;width:100%;max-width:460px;margin:0 auto}
.ie-outline{position:absolute;inset:0;border:1px solid color-mix(in srgb,var(--accent) 55%,transparent);transform:translate(18px,-18px);transition:transform .7s var(--ease)}
.ie-portrait:hover .ie-outline,.ie-about-image:hover .ie-outline{transform:translate(7px,-7px)}
.ie-portrait-img{position:relative;aspect-ratio:4/5;overflow:hidden;background:var(--paper-3)}
.ie-hero-img{width:100%;height:100%;object-fit:cover;transform:translate3d(0,calc(var(--scroll-y,0)*.04px),0) scale(1.1);transition:filter .6s;will-change:transform}
.ie-portrait-img:hover .ie-hero-img{filter:saturate(1.08) contrast(1.03)}
.ie-initials{display:flex;width:100%;height:100%;align-items:center;justify-content:center;color:var(--accent);font-size:144px}
.ie-cue{position:absolute;bottom:32px;left:40px;display:flex;align-items:center;gap:12px;color:var(--muted);font-size:12px;font-weight:600}
.ie-cue-line{position:relative;width:1px;height:40px;overflow:hidden;background:var(--line)}
.ie-cue-line:after{position:absolute;inset:0;background:var(--accent);content:"";animation:ie-cue 2.4s cubic-bezier(.6,0,.2,1) infinite}
.ie-about,.ie-journey,.ie-skills,.ie-projects,.ie-contact{scroll-margin-top:16px;padding:112px 40px}
.ie-about{border-top:1px solid var(--line);background:var(--paper-2)}
.ie-about-layout{display:grid;width:min(1280px,100%);grid-template-columns:.8fr 1.2fr;align-items:center;gap:96px;margin:auto}
.ie-about-image{position:relative;width:100%;max-width:448px;margin:auto}
.ie-media{position:relative;aspect-ratio:4/5;overflow:hidden;background:var(--paper-3);isolation:isolate}
.ie-media img{width:100%;height:100%;object-fit:cover;transition:transform 1s var(--ease)}
.ie-media:hover img,.ie-project:hover .ie-project-media img{transform:scale(1.05)}
.ie-kicker{display:inline-flex;align-items:center;gap:12px;color:var(--accent);font-size:15px;font-weight:600}
.ie-kicker:before{width:32px;height:1.5px;transform-origin:left;background:currentColor;content:"";transition:transform .8s var(--ease) .2s}
.ie-js [data-reveal]:not(.is-in) .ie-kicker:before{transform:scaleX(0)}
.ie-lead{max-width:768px;margin:28px 0 0;font-size:clamp(30px,3.6vw,54px);line-height:1.22;letter-spacing:-.02em}
.ie-about-word{transition:opacity .7s ease calc(var(--word-index,0)*18ms + .1s)}
.ie-js [data-reveal]:not(.is-in) .ie-about-word{opacity:.14}
.ie-highlights{max-width:576px;margin:40px 0 0;padding:0;border-top:1px solid var(--line);list-style:none}
.ie-highlights li{display:flex;align-items:center;gap:16px;border-bottom:1px solid var(--line);padding:16px 0;font-weight:500;transition:padding-left .4s var(--ease)}
.ie-highlights li:hover{padding-left:10px}
.ie-js [data-reveal]:not(.is-in) .ie-highlights li{opacity:0}
.ie-js [data-reveal].is-in .ie-highlights li{animation:ie-slide-x .8s var(--ease) calc(var(--item-index,0)*90ms + 150ms) backwards}
.ie-tick{display:inline-flex;width:24px;height:24px;flex:none;align-items:center;justify-content:center;border-radius:50%;background:var(--accent);color:white;font-size:13px;font-weight:700}
.ie-about-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:40px}
.ie-section-inner{width:min(1280px,100%);margin:auto}
.ie-journey{background:var(--paper)}
.ie-section-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:56px}
.ie-h2{max-width:768px;margin:0;font-size:clamp(44px,6.5vw,80px);line-height:1;letter-spacing:-.025em;text-wrap:balance}
.ie-section-heading>p{max-width:400px;margin:0;color:var(--muted);line-height:1.75}
.ie-rule{height:1px;transform-origin:left;background:var(--line);transition:transform 1.2s cubic-bezier(.6,0,.2,1)}
.ie-js .ie-rule[data-reveal]{opacity:1;transform:scaleX(0)}
.ie-js .ie-rule[data-reveal].is-in{transform:scaleX(1)}
.ie-journey-list{margin:0;padding:0;list-style:none}
.ie-journey-list>li{position:relative}
.ie-journey-list>li:after{position:absolute;inset:auto 0 0;height:1px;transform:scaleX(0);transform-origin:left;background:var(--line);content:"";transition:transform 1.1s cubic-bezier(.6,0,.2,1) calc(var(--item-delay,0ms) + .15s)}
.ie-journey-list>li.is-in:after{transform:scaleX(1)}
.ie-journey-row{position:relative;display:grid;gap:12px;padding:36px 0;transition:padding-inline .45s var(--ease),background-color .4s}
.ie-journey-row:hover{padding-inline:20px;background:color-mix(in srgb,var(--paper-2) 70%,transparent)}
.ie-period{margin:0;padding-top:6px;color:var(--accent);font-size:14px;font-weight:600}
.ie-journey-title-row{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:6px 24px}
.ie-journey-title-row h3{margin:0;font-size:36px;line-height:1.2}
.ie-journey-title-row p{margin:0;color:var(--muted);font-size:14px;font-weight:600}
.ie-journey-row:hover h3{color:var(--accent);font-style:italic}
.ie-journey-description{max-width:768px;margin:12px 0 0;color:var(--muted);line-height:1.75;white-space:pre-line}
.ie-skills{border-block:1px solid var(--line);background:var(--tint)}
.ie-skills-layout{display:grid;width:min(1280px,100%);gap:56px;margin:auto}
.ie-skills-intro{position:relative}
.ie-skills-intro .ie-h2{margin-top:20px}
.ie-skills-description{max-width:400px;margin:24px 0 0;color:var(--muted);line-height:1.75;white-space:pre-line}
.ie-skills-list{margin:0;padding:0;border-top:1px solid var(--line);list-style:none}
.ie-skill{padding:32px 0;border-bottom:1px solid var(--line);transition:padding-left .5s var(--ease)}
.ie-skill:hover{padding-left:16px}
.ie-skill-heading{display:flex;align-items:baseline;justify-content:space-between;gap:24px}
.ie-skill-heading h3{margin:0;font-size:42px;line-height:1.2;transition:color .3s}
.ie-skill:hover h3{color:var(--accent);font-style:italic}
@property --number{syntax:"<integer>";inherits:false;initial-value:0}
.ie-count{--number:var(--count-target,0);counter-reset:skill-number var(--number);font-size:18px;font-weight:600;color:var(--accent);font-variant-numeric:tabular-nums}
.ie-count:after{content:counter(skill-number) "%"}
.ie-js [data-reveal] .ie-count{--number:0}
.ie-js [data-reveal].is-in .ie-count{animation:ie-count 1.6s var(--ease) calc(var(--item-delay,0ms) + .3s) forwards}
.ie-skill-label{color:var(--accent);font-size:14px;font-weight:600}
.ie-ticks{display:flex;height:36px;align-items:flex-end;gap:5px;margin-top:24px}
.ie-tick-bar{flex:1;min-width:2px;max-width:11px;height:22px;transform-origin:bottom;border-radius:2px;background:color-mix(in srgb,var(--ink) 16%,transparent);transition:background-color .5s ease calc(var(--item-delay,0ms) + .3s + var(--tick,0)*32ms),transform .3s cubic-bezier(.3,1.4,.4,1)}
.ie-tick-bar.on{background:var(--accent)}
.ie-tick-bar.end{transform:scaleY(1.55)}
.ie-tick-bar:hover{transform:scaleY(1.65)}
.ie-js [data-reveal]:not(.is-in) .ie-tick-bar.on{background:color-mix(in srgb,var(--ink) 16%,transparent)}
.ie-js [data-reveal].is-in .ie-tick-bar{animation:ie-tick-in .6s cubic-bezier(.3,1.5,.4,1) calc(var(--item-delay,0ms) + .2s + var(--tick,0)*32ms) backwards}
.ie-projects{background:var(--paper)}
.ie-project-rule{margin-bottom:56px}
.ie-project-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:64px 40px;margin:0;padding:0;list-style:none}
.ie-project-item.stagger{margin-top:96px}
.ie-project-media{position:relative;display:block;aspect-ratio:1.35;overflow:hidden;background:var(--paper-3);isolation:isolate}
.ie-project-media img{width:100%;height:100%;object-fit:cover;transition:transform 1s var(--ease)}
.ie-project-initial{display:flex;width:100%;height:100%;align-items:flex-end;padding:32px;color:var(--accent);font-size:112px}
.ie-go{position:absolute;top:16px;right:16px;display:inline-flex;width:48px;height:48px;align-items:center;justify-content:center;transform:translate(-8px,8px) scale(.9);border-radius:50%;background:var(--paper);color:var(--ink);opacity:0;transition:opacity .35s,transform .5s var(--ease),background-color .3s,color .3s}
.ie-project:hover .ie-go,.ie-project-media:focus-visible .ie-go{transform:none;opacity:1}
.ie-go:hover{background:var(--accent);color:white}
.ie-project-copy{margin-top:24px}
.ie-project-category{margin:0;color:var(--accent);font-size:14px;font-weight:600}
.ie-project-copy h3{margin:4px 0 0;font-size:36px;line-height:1.2}
.ie-underline{background:linear-gradient(currentColor,currentColor) left 94%/0 1.5px no-repeat;transition:background-size .55s var(--ease)}
.ie-project:hover .ie-underline{background-size:100% 1.5px}
.ie-project-description{max-width:448px;margin:12px 0 0;color:var(--muted);line-height:1.7}
.ie-source-link{display:inline-block;margin-top:16px;font-size:14px;font-weight:600}
.ie-contact{border-top:1px solid var(--line);background:var(--paper-2)}
.ie-contact-layout{display:grid;width:min(1280px,100%);gap:56px;margin:auto}
.ie-contact-title{overflow-wrap:anywhere;margin:20px 0 0;font-size:clamp(48px,7.5vw,96px);line-height:.96;letter-spacing:-.03em}
.ie-contact-description{max-width:544px;margin:24px 0 0;color:var(--muted);font-size:18px;line-height:1.75;white-space:pre-line}
.ie-contact-details{margin-top:40px;border-top:1px solid var(--line);padding-top:32px}
.ie-bigmail{display:inline-flex;max-width:100%;align-items:center;gap:12px;overflow-wrap:anywhere;color:var(--accent);font-size:clamp(28px,4vw,40px)}
.ie-ulink-flip{padding-bottom:4px;background:linear-gradient(currentColor,currentColor) left bottom/100% 1.5px no-repeat;transition:background-size .5s var(--ease)}
.ie-bigmail:hover .ie-ulink-flip{background-position:right bottom;background-size:0 1.5px}
.ie-contact-meta{display:flex;flex-wrap:wrap;gap:12px 32px;margin-top:20px;color:var(--muted)}
.ie-contact-meta>a,.ie-contact-meta>span{display:inline-flex;align-items:center;gap:8px}
.ie-contact-socials{margin-top:32px}
.ie-form-column{min-width:0}
.ie-form{border:1px solid var(--line);border-radius:28px;background:rgba(255,255,255,.56);padding:28px;box-shadow:0 40px 70px -48px rgba(32,33,31,.6)}
.ie-form-title{margin:0;font-size:30px}
.ie-form-fields{display:grid;gap:28px;margin-top:32px}
.ie-form-fields label{display:block}
.ie-label{display:block;margin-bottom:4px;color:var(--muted);font-size:14px;font-weight:600;transition:color .25s}
.ie-form-fields label:focus-within .ie-label{color:var(--accent)}
.ie-input{width:100%;border:0;border-bottom:1px solid var(--line);outline:0;background:linear-gradient(var(--accent),var(--accent)) left bottom/0 2px no-repeat;padding:9px 0;color:var(--ink);font:inherit;font-size:17px;transition:background-size .5s var(--ease),border-color .25s}
.ie-input:hover{border-bottom-color:rgba(32,33,31,.45)}
.ie-input:focus{background-size:100% 2px}
.ie-submit{width:100%;margin-top:36px}
.ie-sent{display:flex;align-items:center;gap:12px;margin:20px 0 0;color:var(--ink);font-size:14px;font-weight:600}
.ie-sent[hidden]{display:none}
.ie-sent .ie-tick{width:24px;height:24px}
.ie-hidden-frame{display:none}
.ie-footer{display:flex;width:min(1280px,100%);align-items:center;justify-content:space-between;gap:12px;margin:auto;padding:32px 40px;color:var(--muted);font-size:14px}
.ie-footer a{display:inline-flex;align-items:center;gap:6px;font-weight:600}
.ie-js [data-reveal]{opacity:0;transform:translateY(24px);transition:opacity .9s var(--ease) var(--delay,0ms),transform .9s var(--ease) var(--delay,0ms)}
.ie-js [data-reveal].is-in{opacity:1;transform:none}
.ie-js .ie-portrait-img{animation:ie-wipe-up 1.4s cubic-bezier(.7,0,.2,1) .25s backwards}
.ie-js [data-reveal].is-in .ie-media{animation:ie-wipe-up 1.3s cubic-bezier(.7,0,.2,1) .1s backwards}
.ie-js [data-reveal].is-in .ie-media img{animation:ie-settle 1.7s var(--ease) .1s backwards}
.ie-js.ie-root{animation:ie-enter .8s ease both}
.ie-root :where(a,button,input,textarea):focus-visible{outline:2px solid var(--accent);outline-offset:3px}
@keyframes ie-enter{from{opacity:0}}
@keyframes ie-rise{to{transform:translateY(0)}}
@keyframes ie-wipe-up{from{clip-path:inset(100% 0 0 0)}}
@keyframes ie-settle{from{transform:scale(1.25)}}
@keyframes ie-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes ie-cue{0%{transform:translateY(-100%)}60%,100%{transform:translateY(100%)}}
@keyframes ie-count{to{--number:var(--count-target)}}
@keyframes ie-slide-x{from{opacity:0;transform:translateX(-20px)}}
@keyframes ie-tick-in{from{transform:scaleY(0)}}
@keyframes ie-breathe{50%{transform:scale(1.04)}}
@media(min-width:640px){.ie-navwrap{padding:16px 24px 0}.ie-hero{padding-inline:40px}.ie-journey-row{grid-template-columns:170px 1fr;gap:48px}.ie-about,.ie-journey,.ie-skills,.ie-projects,.ie-contact{padding-block:144px}.ie-form{padding:40px}}
@media(min-width:1024px){.ie-skills-layout{grid-template-columns:repeat(12,minmax(0,1fr));gap:80px}.ie-skills-intro{position:sticky;top:112px;align-self:start;grid-column:span 4}.ie-skills-list{grid-column:span 8}.ie-contact-layout{grid-template-columns:repeat(12,minmax(0,1fr));gap:56px}.ie-contact-layout>div:first-child{grid-column:span 6}.ie-form-column{grid-column:span 6}}
@media(max-width:767px){html{scroll-padding-top:5rem}.ie-navwrap{padding:12px 12px 0}.ie-nav{height:54px;padding-left:10px}.ie-navlinks{display:none}.ie-logo-text{max-width:120px}.ie-nav-cta{padding:9px 14px;font-size:12px}.ie-hero{grid-template-columns:1fr;gap:40px;padding:112px 24px 96px}.ie-hero-copy{order:2}.ie-portrait{order:1;max-width:360px}.ie-rings{right:-440px;width:700px;height:700px}.ie-hero-name{font-size:clamp(58px,14vw,100px)}.ie-cue{display:none}.ie-about,.ie-journey,.ie-skills,.ie-projects,.ie-contact{padding:88px 24px}.ie-about-layout{grid-template-columns:1fr;gap:48px}.ie-about-image{max-width:400px}.ie-section-heading{align-items:flex-start;flex-direction:column;margin-bottom:40px}.ie-journey-title-row h3{font-size:30px}.ie-skills-layout{gap:40px}.ie-skill-heading h3{font-size:34px}.ie-project-rule{margin-bottom:40px}.ie-project-grid{grid-template-columns:1fr;gap:48px}.ie-project-item.stagger{margin-top:0}.ie-project-copy h3{font-size:30px}.ie-contact-layout{grid-template-columns:1fr;gap:40px}.ie-form{padding:24px}.ie-footer{padding:28px 24px}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.ie-root *,.ie-root *:before,.ie-root *:after{animation:none!important;transition:none!important}.ie-js .ie-word,.ie-js [data-reveal],.ie-js [data-reveal]:not(.is-in) .ie-highlights li,.ie-js [data-reveal]:not(.is-in) .ie-about-word{opacity:1;transform:none}.ie-js .ie-rule[data-reveal],.ie-journey-list>li:after{transform:scaleX(1)}.ie-js [data-reveal]:not(.is-in) .ie-tick-bar.on{background:var(--accent)}.ie-js [data-reveal] .ie-count{--number:var(--count-target,0)}.ie-hero-img{transform:none}.ie-navwrap.is-hidden{transform:none}}
CSS;
    }

    private function script(string $slug): string
    {
        if ($slug === 'ivory-editorial') {
            return <<<'JS'
const root = document.querySelector(".ie-root");
const navWrap = document.querySelector("[data-nav-wrap]");
const nav = document.querySelector(".ie-nav");
const progress = document.querySelector(".ie-progress");
root?.classList.add("ie-js");
let lastY = 0;
let scheduled = false;
const updateScroll = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
        const y = window.scrollY;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, y / max) : 0})`;
        nav?.classList.toggle("is-scrolled", y > 16);
        if (Math.abs(y - lastY) > 6) {
            navWrap?.classList.toggle("is-hidden", y > 160 && y > lastY);
            lastY = y;
        }
        root?.style.setProperty("--scroll-y", String(Math.min(y, 900)));
        scheduled = false;
    });
};
window.addEventListener("scroll", updateScroll, { passive: true });
updateScroll();
if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches && window.matchMedia("(pointer: fine)").matches) {
    let glowFrame = 0;
    window.addEventListener("pointermove", (event) => {
        if (glowFrame) return;
        glowFrame = requestAnimationFrame(() => {
            glowFrame = 0;
            root?.style.setProperty("--gx", `${event.clientX}px`);
            root?.style.setProperty("--gy", `${event.clientY}px`);
        });
    }, { passive: true });
}
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
if ("IntersectionObserver" in window) {
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-in");
            revealObserver.unobserve(entry.target);
        });
    }, { threshold: 0.14, rootMargin: "0px 0px -5% 0px" });
    if (reduceMotion) {
        document.querySelectorAll("[data-reveal]").forEach((element) => element.classList.add("is-in"));
    } else {
        document.querySelectorAll("[data-reveal]").forEach((element) => revealObserver.observe(element));
    }
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll("[data-nav-link]").forEach((link) => {
                link.dataset.active = String(link.getAttribute("data-nav-link") === entry.target.id);
            });
        });
    }, { rootMargin: "-45% 0px -50% 0px" });
    document.querySelectorAll(".ie-hero[id], .ie-about[id], .ie-journey[id], .ie-skills[id], .ie-projects[id], .ie-contact[id]").forEach((section) => sectionObserver.observe(section));
} else {
    document.querySelectorAll("[data-reveal]").forEach((element) => element.classList.add("is-in"));
}
document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener("click", (event) => {
        const id = link.getAttribute("href")?.slice(1);
        if (!id) return;
        const target = document.getElementById(id);
        if (!target) return;
        event.preventDefault();
        navWrap?.classList.remove("is-hidden");
        target.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
    });
});
document.querySelectorAll(".ie-magnet").forEach((button) => {
    button.addEventListener("mousemove", (event) => {
        if (reduceMotion) return;
        const bounds = button.getBoundingClientRect();
        button.style.transform = `translate(${(event.clientX - (bounds.left + bounds.width / 2)) * .2}px, ${(event.clientY - (bounds.top + bounds.height / 2)) * .28}px)`;
    });
    button.addEventListener("mouseleave", () => { button.style.transform = ""; });
});
const googleForm = document.querySelector("[data-google-form]");
if (googleForm) {
    const button = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = "Sending…";
        }
        if (status) status.hidden = true;
        window.setTimeout(() => {
            googleForm.reset();
            if (button) {
                button.disabled = false;
                button.textContent = button.dataset.originalText || "Send message";
            }
            if (status) status.hidden = false;
        }, 700);
    });
}
JS;
        }
        if ($slug === 'bento-grid') {
            return <<<'JS'
const root = document.querySelector(".rail");
const googleForm = document.querySelector("[data-google-form]");
const formFrame = document.querySelector('iframe[name="bg-gform-frame"]');
if (googleForm && formFrame) {
    let pendingSubmission = false;
    const submitButton = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        pendingSubmission = true;
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.firstChild.textContent = "Sending… ";
        }
        if (status) {
            status.textContent = "";
            status.classList.add("hidden");
        }
    });
    formFrame.addEventListener("load", () => {
        if (!pendingSubmission) return;
        pendingSubmission = false;
        googleForm.reset();
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.firstChild.textContent = "Send message ";
        }
        if (status) {
            status.textContent = "Your message was submitted.";
            status.classList.remove("hidden");
        }
    });
}
const progress = document.querySelector(".progress");
const updateProgress = () => {
    if (!progress) return;
    const height = document.documentElement.scrollHeight - window.innerHeight;
    progress.style.transform = `scaleX(${height > 0 ? Math.min(1, window.scrollY / height) : 0})`;
};
window.addEventListener("scroll", updateProgress, { passive: true });
updateProgress();

const sections = document.querySelectorAll("[data-section]");
if ("IntersectionObserver" in window) {
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll("[data-nav-link]").forEach((link) => {
                const active = link.getAttribute("data-nav-link") === entry.target.id;
                link.classList.toggle("active", active);
                if (link.closest("nav")) {
                    link.classList.toggle("mobile-active", active);
                }
            });
        });
    }, { rootMargin: "-40% 0px -55% 0px" });
    sections.forEach((section) => sectionObserver.observe(section));
}

if (root && !window.matchMedia("(prefers-reduced-motion: reduce)").matches && "IntersectionObserver" in window) {
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-visible");
            revealObserver.unobserve(entry.target);
        });
    }, { threshold: 0.35 });
    root.classList.add("motion-ready");
    root.querySelectorAll("[data-reveal]").forEach((element) => revealObserver.observe(element));
    requestAnimationFrame(() => root.classList.add("is-ready"));
}
JS;
        }
        if ($slug === 'cobalt-brutalist') {
            return <<<'JS'
const root = document.querySelector(".nb-root");
const progress = document.querySelector(".nb-progress");
const nav = document.querySelector(".nb-nav");
const updateScroll = () => {
    const max = document.documentElement.scrollHeight - window.innerHeight;
    if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`;
    if (nav) nav.classList.toggle("is-scrolled", window.scrollY > 16);
};
window.addEventListener("scroll", updateScroll, { passive: true });
updateScroll();

document.querySelectorAll("[data-scroll-link]").forEach((link) => {
    link.addEventListener("click", (event) => {
        const href = link.getAttribute("href");
        if (!href || !href.startsWith("#") || href.length < 2) return;
        const target = document.getElementById(href.slice(1));
        if (!target) return;
        event.preventDefault();
        target.scrollIntoView({
            behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
            block: "start",
        });
    });
});

if ("IntersectionObserver" in window) {
    const navObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll("[data-section-link]").forEach((link) => {
                link.dataset.active = String(link.getAttribute("data-section-link") === entry.target.id);
            });
        });
    }, { rootMargin: "-45% 0px -50% 0px" });
    document.querySelectorAll("[data-page-section]").forEach((section) => navObserver.observe(section));
}

if (root && !window.matchMedia("(prefers-reduced-motion: reduce)").matches && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-in");
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" });
    root.classList.add("nb-js");
    root.querySelectorAll("[data-reveal]").forEach((element) => observer.observe(element));
}

const googleForm = document.querySelector("[data-google-form]");
const formFrame = document.querySelector('iframe[name="nb-gform-frame"]');
if (googleForm && formFrame) {
    let pending = false;
    const button = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        pending = true;
        if (button) {
            button.disabled = true;
            button.firstChild.textContent = "Sending ";
        }
        if (status) {
            status.hidden = true;
            status.textContent = "";
        }
    });
    formFrame.addEventListener("load", () => {
        if (!pending) return;
        pending = false;
        googleForm.reset();
        if (button) {
            button.disabled = false;
            button.firstChild.textContent = "Send message ";
        }
        if (status) {
            status.textContent = "Your message was submitted.";
            status.hidden = false;
        }
    });
}
JS;
        }
        if ($slug === 'dark-orange-designer') {
            return <<<'JS'
const theme = document.querySelector(".theme");
const nav = document.querySelector(".nav");
const menuToggle = document.querySelector(".menu-toggle");
const mobileNavigation = document.querySelector("#mobile-navigation");
requestAnimationFrame(() => {
    theme?.classList.add("is-ready");
    nav?.classList.add("nav-in");
});
if (menuToggle && mobileNavigation) {
    menuToggle.addEventListener("click", () => {
        const open = menuToggle.getAttribute("aria-expanded") !== "true";
        menuToggle.setAttribute("aria-expanded", String(open));
        menuToggle.querySelector(".burger")?.classList.toggle("open", open);
        mobileNavigation.hidden = !open;
    });
    mobileNavigation.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => {
        menuToggle.setAttribute("aria-expanded", "false");
        menuToggle.querySelector(".burger")?.classList.remove("open");
        mobileNavigation.hidden = true;
    }));
}
const progress = document.querySelector(".progress-bar");
let scheduled = false;
const updateScroll = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`;
        scheduled = false;
    });
};
window.addEventListener("scroll", updateScroll, { passive: true });
updateScroll();

if ("IntersectionObserver" in window) {
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll("[data-section-link]").forEach((link) => {
                link.classList.toggle("active", link.getAttribute("data-section-link") === entry.target.id);
            });
        });
    }, { rootMargin: "-45% 0px -50% 0px" });
    document.querySelectorAll("[data-section]").forEach((section) => sectionObserver.observe(section));

    if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-in");
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.15, rootMargin: "0px 0px -8% 0px" });
        document.querySelectorAll(".reveal").forEach((element) => revealObserver.observe(element));
    } else {
        document.querySelectorAll(".reveal").forEach((element) => element.classList.add("is-in"));
    }
}

document.querySelectorAll("[data-journey-item] .jr-head").forEach((button) => {
    button.addEventListener("click", () => {
        const item = button.closest("[data-journey-item]");
        if (!item) return;
        const open = !item.classList.contains("open");
        item.classList.toggle("open", open);
        button.setAttribute("aria-expanded", String(open));
    });
});
document.querySelectorAll("[data-project-filter]").forEach((button) => {
    button.addEventListener("click", () => {
        const category = button.getAttribute("data-project-filter");
        document.querySelectorAll("[data-project-filter]").forEach((filter) => {
            const active = filter === button;
            filter.classList.toggle("active", active);
            filter.setAttribute("aria-selected", String(active));
        });
        document.querySelectorAll("[data-project-category]").forEach((project) => {
            project.hidden = category !== "all" && project.getAttribute("data-project-category") !== category;
        });
    });
});
const workList = document.querySelector("[data-work-list]");
const workPreview = document.querySelector(".work-preview");
if (workList && workPreview) {
    workList.addEventListener("pointermove", (event) => {
        workPreview.style.transform = `translate3d(${event.clientX + 28}px, ${event.clientY - 150}px, 0)`;
    });
    workList.addEventListener("pointerleave", () => workPreview.classList.remove("show"));
    workList.querySelectorAll("[data-project-index]").forEach((row) => {
        row.addEventListener("pointerenter", () => {
            const image = workPreview.querySelector(`[data-preview-index="${row.getAttribute("data-project-index")}"]`);
            workPreview.querySelectorAll("img").forEach((previewImage) => previewImage.classList.toggle("on", previewImage === image));
            workPreview.classList.toggle("show", !!image && !row.hidden);
        });
    });
}

const googleForm = document.querySelector("[data-google-form]");
const formFrame = document.querySelector('iframe[name="do-gform-frame"]');
if (googleForm && formFrame) {
    let pending = false;
    const button = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        pending = true;
        if (button) {
            button.disabled = true;
            button.firstChild.textContent = "Sending… ";
        }
    });
    formFrame.addEventListener("load", () => {
        if (!pending) return;
        pending = false;
        googleForm.reset();
        if (button) {
            button.disabled = false;
            button.firstChild.textContent = "Send message ";
        }
        if (status) {
            status.textContent = "Your message was submitted.";
            status.hidden = false;
        }
    });
}
JS;
        }
        if ($slug === 'emerald-minimalist') {
            return <<<'JS'
const nav = document.querySelector(".em-navbar");
const menuToggle = document.querySelector(".em-menu-toggle");
const mobileNav = document.querySelector("#em-mobile-nav");
const progress = document.querySelector(".em-progress-bar");
let scheduled = false;
const updateScroll = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`;
        nav?.classList.toggle("is-scrolled", window.scrollY > 24 || menuToggle?.getAttribute("aria-expanded") === "true");
        scheduled = false;
    });
};
window.addEventListener("scroll", updateScroll, { passive: true });
updateScroll();
if (menuToggle && mobileNav) {
    menuToggle.addEventListener("click", () => {
        const open = menuToggle.getAttribute("aria-expanded") !== "true";
        menuToggle.setAttribute("aria-expanded", String(open));
        mobileNav.hidden = !open;
        nav?.classList.toggle("is-scrolled", open || window.scrollY > 24);
    });
    mobileNav.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => {
        menuToggle.setAttribute("aria-expanded", "false");
        mobileNav.hidden = true;
        nav?.classList.toggle("is-scrolled", window.scrollY > 24);
    }));
}
if ("IntersectionObserver" in window) {
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll(".em-nav-link").forEach((link) => {
                link.classList.toggle("active", link.getAttribute("href") === `#${entry.target.id}`);
            });
        });
    }, { rootMargin: "-45% 0px -50% 0px" });
    document.querySelectorAll(".em-section[id], .em-hero[id]").forEach((section) => sectionObserver.observe(section));
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const revealElements = document.querySelectorAll(".em-reveal");
    if (reduced) {
        revealElements.forEach((element) => element.classList.add("is-in"));
    } else {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-in");
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.15, rootMargin: "0px 0px -8% 0px" });
        revealElements.forEach((element) => revealObserver.observe(element));
    }
} else {
    document.querySelectorAll(".em-reveal").forEach((element) => element.classList.add("is-in"));
}
document.querySelectorAll(".em-filter").forEach((button) => {
    button.addEventListener("click", () => {
        const category = button.getAttribute("data-project-filter");
        document.querySelectorAll(".em-filter").forEach((filter) => filter.classList.toggle("active", filter === button));
        document.querySelectorAll(".em-project-card").forEach((project) => {
            project.hidden = category !== "All" && project.getAttribute("data-project-category") !== category;
        });
    });
});
document.querySelectorAll(".em-skill-card").forEach((card) => {
    const count = card.querySelector(".em-skill-count");
    if (!count) return;
    const target = Number(count.dataset.target);
    const render = (value) => { count.firstChild.textContent = String(value); };
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) {
        render(target);
    } else {
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const started = performance.now();
                const duration = 1900;
                const tick = (now) => {
                    const progress = Math.min(1, (now - started) / duration);
                    render(Math.round(target * (1 - Math.pow(1 - progress, 3))));
                    if (progress < 1) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
                counterObserver.unobserve(card);
            });
        }, { threshold: 0.2 });
        counterObserver.observe(card);
    }
});
const googleForm = document.querySelector("[data-google-form]");
const formFrame = document.querySelector('iframe[name="em-gform-frame"]');
if (googleForm && formFrame) {
    let pending = false;
    const button = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        pending = true;
        if (button) {
            button.disabled = true;
            button.textContent = "Sending…";
        }
        if (status) {
            status.hidden = true;
            status.textContent = "";
        }
    });
    formFrame.addEventListener("load", () => {
        if (!pending) return;
        pending = false;
        googleForm.reset();
        if (button) {
            button.disabled = false;
            button.textContent = "Send message →";
        }
        if (status) {
            status.textContent = "Your message was submitted.";
            status.hidden = false;
        }
    });
}
JS;
        }

        return <<<'JS'
const menuToggle = document.querySelector(".menu-toggle");
const siteLinks = document.querySelector("#site-links");
if (menuToggle && siteLinks) {
    menuToggle.addEventListener("click", () => {
        const open = siteLinks.classList.toggle("is-open");
        menuToggle.setAttribute("aria-expanded", String(open));
    });
    siteLinks.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => {
        siteLinks.classList.remove("is-open");
        menuToggle.setAttribute("aria-expanded", "false");
    }));
}
document.querySelectorAll("[data-filter]").forEach((button) => {
    button.addEventListener("click", () => {
        const category = button.dataset.filter;
        document.querySelectorAll("[data-filter]").forEach((item) => item.classList.toggle("active", item === button));
        document.querySelectorAll(".project-card").forEach((card) => {
            card.hidden = category !== "all" && card.dataset.category !== category;
        });
    });
});
JS;
    }
}
