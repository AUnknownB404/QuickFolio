<?php

namespace App\Http\Controllers;

use App\Http\Controllers\PortfolioExport\BentoGridTemplate;
use App\Http\Controllers\PortfolioExport\CobaltBrutalistTemplate;
use App\Http\Controllers\PortfolioExport\CommandDeckTemplate;
use App\Http\Controllers\PortfolioExport\DarkOrangeDesignerTemplate;
use App\Http\Controllers\PortfolioExport\IvoryEditorialTemplate;
use App\Http\Controllers\PortfolioExport\EmeraldMinimalistTemplate;
use App\Http\Controllers\PortfolioExport\NebulaGlassTemplate;
use App\Http\Controllers\PortfolioExport\NewsprintTemplate;
use App\Http\Controllers\PortfolioExport\PortfolioExportScripts;
use App\Models\Portfolio;
use App\Models\Template;
use App\Services\GoogleFormIntegration;
use App\Services\TemplateDemoData;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class PortfolioExportController extends Controller
{
    private const MAX_DEMO_ASSET_BYTES = 20971520;

    use AuthorizesRequests;
    use BentoGridTemplate;
    use CobaltBrutalistTemplate;
    use CommandDeckTemplate;
    use DarkOrangeDesignerTemplate;
    use IvoryEditorialTemplate;
    use EmeraldMinimalistTemplate;
    use PortfolioExportScripts;
    use NebulaGlassTemplate;
    use NewsprintTemplate;

    private const TEMPLATE_THEMES = [
        'emerald-minimalist' => 'emerald',
        'dark-orange-designer' => 'dark-orange',
        'ivory-editorial' => 'ivory',
        'bento-grid' => 'bento',
        'cobalt-brutalist' => 'cobalt',
        'nebula-glass' => 'nebula',
        'newsprint' => 'newsprint',
    ];

    private const TEMPLATE_RENDERERS = [
        'bento-grid' => [
            'page' => 'renderBentoGridPage',
            'styles' => 'bentoGridStyles',
        ],
        'cobalt-brutalist' => [
            'page' => 'renderCobaltBrutalistPage',
            'styles' => 'cobaltBrutalistStyles',
        ],
        'dark-orange-designer' => [
            'page' => 'renderDarkOrangeDesignerPage',
            'styles' => 'darkOrangeDesignerStyles',
        ],
        'emerald-minimalist' => [
            'page' => 'renderEmeraldMinimalistPage',
            'styles' => 'emeraldMinimalistStyles',
        ],
        'ivory-editorial' => [
            'page' => 'renderIvoryEditorialPage',
            'styles' => 'ivoryEditorialStyles',
        ],
        'command-deck' => [
            'page' => 'renderCommandDeckPage',
            'styles' => 'commandDeckStyles',
        ],
        'paper-stack' => [
            'page' => 'renderPaperStackPage',
            'styles' => 'paperStackStyles',
        ],
        'nebula-glass' => [
            'page' => 'renderNebulaGlassPage',
            'styles' => 'nebulaGlassStyles',
        ],
        'newsprint'=> [
            'page'=> 'renderNewsprintPage',
            'styles'=> 'newsprintStyles',
        ],
        'stream-bands'=> [
            'page'=> 'renderStreamBandsPage',
            'styles'=> 'streamBandsStyles',
        ],
        'swiss-grid'=> [
            'page'=> 'renderSwissGridPage',
            'styles'=> 'swissGridStyles',
        ],
        'swiss-signal'=> [
            'page'=> 'renderSwissSignalPage',
            'styles'=> 'swissSignalStyles',
        ],
        'tidepool'=> [
            'page'=> 'renderTidepoolPage',
            'styles'=> 'tidepoolStyles',
        ],
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
        $assets = $this->collectAssets($content, $portfolio);
        $brandFiles = $this->brandFiles();
        $googleForm = null;
        if (in_array($templateSlug, ['bento-grid', 'cobalt-brutalist', 'command-deck', 'dark-orange-designer', 'emerald-minimalist', 'ivory-editorial', 'nebula-glass', 'newsprint', 'orbit-folio', 'paper-stack', 'stream-bands', 'swiss-grid', 'swiss-signal', 'tidepool'], true)) {
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
            ...$brandFiles,
        ];

        return $this->downloadArchive("portfolio-{$portfolio->slug}.zip", $files, $assets);
    }

    public function downloadDemo(Template $template, TemplateDemoData $demoData): StreamedResponse
    {
        $demo = $demoData->forTemplate($template);
        $content = $demo['content'];
        $assets = $this->collectDemoAssets($content);
        $templateSlug = $template->slug;
        $portfolio = $demo['portfolio'];
        $googleForm = null;

        if (in_array($templateSlug, ['bento-grid', 'cobalt-brutalist', 'command-deck', 'dark-orange-designer', 'emerald-minimalist', 'ivory-editorial', 'nebula-glass', 'newsprint', 'orbit-folio', 'paper-stack', 'stream-bands', 'swiss-grid', 'swiss-signal', 'tidepool'], true)) {
            $responseUrl = GoogleFormIntegration::responseUrl($portfolio['google_form_url'] ?? null);
            $entryIds = [
                $portfolio['google_form_name_entry'] ?? null,
                $portfolio['google_form_email_entry'] ?? null,
                $portfolio['google_form_message_entry'] ?? null,
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
            'index.html' => $this->renderPage($templateSlug, $template->name, $content, $assets, $googleForm),
            'styles.css' => $this->stylesForTemplate($templateSlug),
            'script.js' => $this->script($templateSlug),
            ...$this->brandFiles(),
        ];

        return $this->downloadArchive("{$template->slug}-demo.zip", $files, $assets);
    }

    private function brandFiles(): array
    {
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

        return [
            'Logo.svg' => $brandLogo,
            'Logo.png' => $brandLogoPng,
        ];
    }

    private function downloadArchive(string $filename, array $files, array $assets): StreamedResponse
    {
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
                if (! $archive->addFromString($asset['archive_path'], $asset['contents'])) {
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
        }, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function renderPage(string $slug, string $title, array $content, array $assets, ?array $googleForm = null): string
    {
        $renderer = self::TEMPLATE_RENDERERS[$slug] ?? null;
        if ($renderer !== null) {
            return $this->{$renderer['page']}($title, $content, $assets, $googleForm);
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
        $projects = $this->sectionData($content['projects'] ?? []);
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
            $hero .= '<a class="button" href="'.$url($header['hero_cta_primary_url'] ?? null, '#projects').'">'.$escape($header['hero_cta_primary_text']).'</a>';
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

        $html = '<section class="content-section" id="projects"><div class="section-heading">';
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

    private function collectAssets(array $content, Portfolio $portfolio): array
    {
        $assets = [];

        array_walk_recursive($content, function (mixed $value) use (&$assets, $portfolio): void {
            if (! is_string($value)) {
                return;
            }

            $assetLocation = $this->storagePathFromUrl($value);
            if ($assetLocation === null) {
                return;
            }
            [$diskName, $path] = $assetLocation;
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
            $storageDisk = Storage::disk($diskName);
            if (! $storageDisk->exists($path)) {
                abort(422, 'A portfolio asset referenced by this export is missing.');
            }
            if (isset($assets[$value])) {
                return;
            }

            $assets[$value] = [
                'contents' => $storageDisk->get($path),
                'archive_path' => 'assets/'.substr(hash('sha256', $path), 0, 12).'-'.basename($path),
            ];
        });

        return $assets;
    }

    private function collectDemoAssets(array $content): array
    {
        $assets = [];

        array_walk_recursive($content, function (mixed $value) use (&$assets): void {
            if (! is_string($value) || isset($assets[$value])) {
                return;
            }

            $urlParts = parse_url($value);
            if (! is_array($urlParts)) {
                return;
            }
            if (
                ($urlParts['scheme'] ?? null) !== 'https'
                || strcasecmp((string) ($urlParts['host'] ?? ''), TemplateDemoData::ASSET_HOST) !== 0
            ) {
                return;
            }

            $path = rawurldecode(ltrim((string) ($urlParts['path'] ?? ''), '/'));
            if (
                ! str_starts_with($path, 'Demo/')
                || str_contains($path, '\\')
                || in_array('..', explode('/', $path), true)
            ) {
                abort(422, 'A demo asset path is invalid.');
            }

            $response = Http::timeout(20)
                ->withOptions(['allow_redirects' => false])
                ->get($value);
            if (! $response->successful()) {
                abort(502, 'A demo asset could not be downloaded for the template export.');
            }

            $contents = $response->body();
            if (strlen($contents) > self::MAX_DEMO_ASSET_BYTES) {
                abort(413, 'A demo asset is too large to include in the template export.');
            }

            $assets[$value] = [
                'contents' => $contents,
                'archive_path' => 'assets/'.substr(hash('sha256', $path), 0, 12).'-'.basename($path),
            ];
        });

        return $assets;
    }

    private function storagePathFromUrl(string $value): ?array
    {
        $urlParts = parse_url($value);
        $path = $urlParts['path'] ?? null;
        if (! is_string($path)) {
            return null;
        }

        $disk = 'public';
        if (isset($urlParts['host'])) {
            $r2Url = parse_url((string) config('filesystems.disks.r2.url'));
            if (isset($r2Url['host']) && strcasecmp($urlParts['host'], $r2Url['host']) === 0) {
                $disk = 'r2';
                $path = ltrim($path, '/');
                $basePath = trim((string) ($r2Url['path'] ?? ''), '/');
                if ($basePath !== '' && str_starts_with($path, $basePath.'/')) {
                    $path = substr($path, strlen($basePath) + 1);
                }
            } elseif (str_starts_with($path, '/storage/')) {
                $path = substr($path, strlen('/storage/'));
            } else {
                return null;
            }
        } elseif (str_starts_with($path, '/storage/')) {
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

        return [$disk, $path];
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
        $renderer = self::TEMPLATE_RENDERERS[$slug] ?? null;
        if ($renderer !== null) {
            return $this->{$renderer['styles']}();
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
}
