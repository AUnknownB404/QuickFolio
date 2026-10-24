<?php

namespace App\Http\Controllers\PortfolioExport;

trait IvoryEditorialTemplate
{
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
        $projectContent = $section('projects');
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
        foreach ($this->items($projectContent['projects_list'] ?? $projectContent['projects'] ?? []) as $project) {
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
        $showProjects = $hasContent($projectContent) || $projects !== [];
        $showContact = $hasContent($contact) || $formReady;
        $navLinks = [];
        foreach ([
            ['about', 'About', $showAbout],
            ['journey', 'Journey', $showJourney],
            ['skills', 'Skills', $showSkills],
            ['projects', 'Work', $showProjects],
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
            $html .= '<section id="projects" class="ie-projects"><div class="ie-section-inner"><div data-reveal class="ie-section-heading">';
            if (! empty($projectContent['projects_title'])) {
                $html .= '<h2 class="ie-serif ie-h2">'.$escape($projectContent['projects_title']).'</h2>';
            }
            $projectSubtitle = $projectContent['projects_subtitle'] ?? $projectContent['projects_description'] ?? '';
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
}
