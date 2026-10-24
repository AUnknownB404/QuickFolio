<?php

namespace App\Http\Controllers\PortfolioExport;

trait NebulaGlassTemplate
{
    private function renderNebulaGlassPage(string $title, array $content, array $assets, ?array $googleForm): string
    {
        /* ---------------------------------------------------------------
         | Helpers (closures, so nothing can collide with other traits)
         * ------------------------------------------------------------- */
        $escape = fn (mixed $value): string => e(is_scalar($value) ? (string) $value : '');
        $assetUrl = fn (mixed $value): string => $this->assetUrl($value, $assets);
        // Raw, validated URL ('' or $fallback when unsafe). Escape it again when printing.
        $safe = fn (mixed $value, string $fallback = ''): string => $this->safeUrl($assetUrl($value), $fallback);
        // Escaped, validated URL ready for an attribute.
        $url = fn (mixed $value, string $fallback = '#'): string => $escape($this->safeUrl($assetUrl($value), $fallback));
        $icon = fn (string $name, int $size = 16, string $class = '', string $stroke = '2'): string => $this->nebulaGlassIcon($name, $size, $class, $stroke);
        $num = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';

        $clean = static function (mixed $value): ?string {
            if ($value === null || ! is_scalar($value)) {
                return null;
            }
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        };
        $text = fn (array $section, string $key): ?string => $clean($section[$key] ?? null);
        $list = static function (array $section, string $key): array {
            $value = $section[$key] ?? null;
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
            if (! is_array($value)) {
                return [];
            }

            return array_values($value) === $value ? $value : [];
        };
        $flag = static function (array $section, string $key, bool $fallback): bool {
            $value = $section[$key] ?? null;
            if ($value === null || $value === '') {
                return $fallback;
            }

            return in_array($value, [true, 1, '1', 'true'], true);
        };
        $hasContent = null;
        $hasContent = static function (mixed $value) use (&$hasContent): bool {
            if (is_string($value)) {
                return trim($value) !== '';
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($hasContent($item)) {
                        return true;
                    }
                }
            }

            return false;
        };
        $mediaUrl = function (array $section, string $key) use ($clean, $safe): string {
            $value = $section[$key] ?? null;
            if (is_array($value)) {
                $value = $value['url'] ?? $value['path'] ?? $value['src'] ?? null;
            }
            $string = $clean($value);

            return $string === null ? '' : $safe(is_string($value) ? $value : $string, '');
        };

        /* ---------------------------------------------------------------
         | Sections
         * ------------------------------------------------------------- */
        $navbar = $this->sectionData($content['navbar'] ?? []);
        $sticky = $this->sectionData($content['sticky'] ?? []);
        $show = $this->sectionData($content['show'] ?? []);
        $header = $this->sectionData($content['header'] ?? []);
        $about = $this->sectionData($content['about'] ?? []);
        $journeyData = $this->sectionData($content['journey'] ?? []);
        $skillsData = $this->sectionData($content['skills'] ?? []);
        $projectData = $this->sectionData($content['projects'] ?? []);
        $servicesData = $this->sectionData($content['services'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);

        // Navbar-level switches may be seeded under `navbar`, `sticky` or `show`.
        $navSources = [$navbar, $sticky, $show];
        $navFlag = function (string $key, bool $fallback) use ($navSources, $flag): bool {
            foreach ($navSources as $source) {
                $value = $source[$key] ?? null;
                if ($value !== null && $value !== '') {
                    return $flag($source, $key, $fallback);
                }
            }

            return $fallback;
        };
        $navMedia = function (string $key) use ($navSources, $mediaUrl): string {
            foreach ($navSources as $source) {
                $value = $mediaUrl($source, $key);
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        };
        $navText = function (string $key) use ($navSources, $text): ?string {
            foreach ($navSources as $source) {
                $value = $text($source, $key);
                if ($value !== null) {
                    return $value;
                }
            }

            return null;
        };

        $hasHeader = $hasContent($header);
        $hasAbout = $hasContent($about);
        $hasJourney = $hasContent($journeyData);
        $hasSkills = $hasContent($skillsData);
        $hasProjects = $hasContent($projectData);
        $hasServices = $hasContent($servicesData);
        $hasContact = $hasContent($contact);

        /* ---------------------------------------------------------------
         | Icons
         * ------------------------------------------------------------- */
        $iconAliases = [
            'code' => 'code', 'terminal' => 'terminal', 'globe' => 'globe', 'web' => 'globe', 'layers' => 'layers',
            'stack' => 'layers', 'palette' => 'palette', 'design' => 'palette', 'pen' => 'pen-tool', 'pentool' => 'pen-tool',
            'smartphone' => 'smartphone', 'mobile' => 'smartphone', 'server' => 'server', 'backend' => 'server',
            'database' => 'database', 'db' => 'database', 'rocket' => 'rocket', 'briefcase' => 'briefcase',
            'camera' => 'camera', 'megaphone' => 'megaphone', 'marketing' => 'megaphone', 'monitor' => 'monitor',
            'mail' => 'mail', 'link' => 'link', 'sparkles' => 'sparkles',
        ];
        $iconFor = static function (?string $name) use ($iconAliases): ?string {
            if ($name === null || $name === '') {
                return null;
            }
            $key = (string) preg_replace('/[^a-z]/', '', mb_strtolower($name));

            return $iconAliases[$key] ?? null;
        };
        $isGlyph = static fn (?string $name): bool => $name !== null && $name !== '' && $iconFor($name) === null && mb_strlen($name) <= 3;
        $socialIcons = [
            'atsign' => 'at-sign', 'behance' => 'briefcase', 'dribbble' => 'dribbble', 'email' => 'mail',
            'facebook' => 'facebook', 'github' => 'github', 'instagram' => 'instagram', 'linkedin' => 'linkedin',
            'website' => 'globe', 'x' => 'at-sign', 'twitter' => 'at-sign', 'youtube' => 'youtube',
        ];
        $socialIcon = static fn (string $platform): string => $socialIcons[(string) preg_replace('/[^a-z]/', '', mb_strtolower($platform))] ?? 'globe';

        /* ---------------------------------------------------------------
         | Normalised content
         * ------------------------------------------------------------- */
        $socialLabels = [
            'behance' => 'Behance', 'dribbble' => 'Dribbble', 'email' => 'Email', 'facebook' => 'Facebook',
            'github' => 'GitHub', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'website' => 'Website',
            'x' => 'X', 'twitter' => 'X', 'youtube' => 'YouTube',
        ];
        $hostOf = static function (string $value): string {
            $host = parse_url($value, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? (string) preg_replace('/^www\./i', '', $host) : $value;
        };
        $socials = function (array $section, string $key) use ($list, $clean, $safe, $hostOf, $socialLabels): array {
            $result = [];
            foreach ($list($section, $key) as $item) {
                if (is_string($item)) {
                    $rawUrl = $clean($item);
                    if ($rawUrl === null) {
                        continue;
                    }
                    $platform = '';
                    $label = $hostOf($rawUrl);
                } elseif (is_array($item)) {
                    $rawUrl = $clean($item['url'] ?? $item['href'] ?? null);
                    if ($rawUrl === null) {
                        continue;
                    }
                    $platform = $clean($item['platform'] ?? $item['label'] ?? $item['name'] ?? null) ?? '';
                    $platformKey = (string) preg_replace('/[^a-z]/', '', mb_strtolower($platform));
                    $label = $clean($item['label'] ?? $item['name'] ?? null) ?? ($socialLabels[$platformKey] ?? $hostOf($rawUrl));
                } else {
                    continue;
                }
                $href = $safe($rawUrl, '');
                if ($href === '') {
                    continue;
                }
                $result[] = ['label' => $label, 'platform' => $platform, 'url' => $href];
            }

            return $result;
        };

        $heroName = $text($header, 'hero_name');
        $logoText = $navText('logo_text') ?? $heroName;
        $logoImage = $navFlag('show_logo', true) ? $navMedia('logo_image') : '';
        $stickyNav = $navFlag('sticky_nav', true);
        $heroWords = $heroName !== null ? (preg_split('/\s+/u', $heroName, -1, PREG_SPLIT_NO_EMPTY) ?: []) : [];
        $heroInitials = '';
        foreach (array_slice(preg_split('/\s+/u', $heroName ?? $logoText ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2) as $word) {
            $heroInitials .= mb_strtoupper(mb_substr($word, 0, 1));
        }
        $heroSocials = $socials($header, 'hero_social_links');
        $makeCta = static fn (?string $label, ?string $target): ?array => $label !== null && $target !== null ? ['text' => $label, 'url' => $target] : null;
        $heroPrimary = $makeCta($text($header, 'hero_cta_primary_text'), $text($header, 'hero_cta_primary_url'));
        $heroSecondary = $makeCta($text($header, 'hero_cta_secondary_text'), $text($header, 'hero_cta_secondary_url'));

        $highlights = [];
        foreach ($list($about, 'about_highlights') as $item) {
            if (is_string($item)) {
                $highlightTitle = $clean($item);
                $highlightDescription = null;
            } elseif (is_array($item)) {
                $highlightTitle = $clean($item['title'] ?? $item['label'] ?? $item['text'] ?? $item['name'] ?? $item['value'] ?? null);
                $highlightDescription = $clean($item['description'] ?? null);
            } else {
                continue;
            }
            if ($highlightTitle !== null) {
                $highlights[] = ['title' => $highlightTitle, 'description' => $highlightDescription];
            }
        }
        $aboutCta = $makeCta($text($about, 'about_cta_text'), $text($about, 'about_cta_url'));
        $cvFile = $mediaUrl($about, 'about_cv_file');
        $aboutImage = $mediaUrl($about, 'about_image');

        $journey = [];
        foreach ($list($journeyData, 'journey_list') as $item) {
            if (! is_array($item)) {
                continue;
            }
            $entry = [
                'period' => $clean($item['period'] ?? null),
                'title' => $clean($item['title'] ?? $item['role'] ?? null),
                'company' => $clean($item['company'] ?? $item['institution'] ?? null),
                'description' => $clean($item['description'] ?? null),
            ];
            if ($entry['title'] !== null || $entry['company'] !== null || $entry['period'] !== null || $entry['description'] !== null) {
                $journey[] = $entry;
            }
        }

        // Skills: numbers up to 5 are a 5-point scale, larger numbers a percentage, anything else is shown as text.
        $skills = [];
        foreach ($list($skillsData, 'skills_list') as $item) {
            $isArray = is_array($item);
            $name = $clean(is_string($item) ? $item : ($isArray ? ($item['name'] ?? $item['title'] ?? $item['label'] ?? null) : null));
            if ($name === null) {
                continue;
            }
            $level = $isArray ? $clean($item['level'] ?? $item['percent'] ?? null) : null;
            $percent = null;
            $levelLabel = null;
            if ($level !== null) {
                if (preg_match('/^[\d.]+\s*%?$/', $level) === 1) {
                    $number = (float) $level;
                    $percent = max(0.0, min(100.0, $number <= 5 ? $number * 20 : $number));
                } else {
                    $levelLabel = $level;
                }
            }
            $skills[] = [
                'name' => $name,
                'icon' => $isArray ? $clean($item['icon'] ?? null) : null,
                'percent' => $percent,
                'label' => $levelLabel,
            ];
        }
        $showLevel = $flag($skillsData, 'skills_show_level', true);
        $showIcon = $flag($skillsData, 'skills_show_icon', true);

        $projects = [];
        foreach ($list($projectData, 'projects_list') as $item) {
            if (! is_array($item)) {
                continue;
            }
            $projectTitle = $clean($item['title'] ?? $item['name'] ?? null);
            if ($projectTitle === null) {
                continue;
            }
            $rawTags = $item['tags'] ?? null;
            $tags = [];
            if (is_array($rawTags)) {
                foreach ($rawTags as $tag) {
                    $tagText = $clean(is_string($tag) ? $tag : (is_array($tag) ? ($tag['name'] ?? $tag['label'] ?? null) : null));
                    if ($tagText !== null) {
                        $tags[] = $tagText;
                    }
                }
            } elseif (is_string($rawTags)) {
                foreach (explode(',', $rawTags) as $tag) {
                    $tag = trim($tag);
                    if ($tag !== '') {
                        $tags[] = $tag;
                    }
                }
            }
            $category = $clean($item['category'] ?? null);
            if ($category !== null && ! in_array($category, $tags, true)) {
                $tags[] = $category;
            }
            $imageValue = $item['image'] ?? null;
            $image = is_string($imageValue)
                ? $clean($imageValue)
                : (is_array($imageValue) ? $clean($imageValue['url'] ?? $imageValue['path'] ?? null) : null);
            $liveRaw = $clean($item['live_url'] ?? $item['url'] ?? null);
            $repoRaw = $clean($item['repo_url'] ?? null);
            $repoHref = $repoRaw !== null ? $safe($repoRaw, '') : '';
            $projects[] = [
                'title' => $projectTitle,
                'description' => $clean($item['description'] ?? null),
                'image' => $image !== null ? $safe($image, '') : '',
                'repo' => $repoHref,
                'repo_label' => $repoHref !== ''
                    ? implode('/', array_slice(explode('/', (string) preg_replace('#^https?://(www\.)?#i', '', $repoHref)), 0, 2))
                    : '',
                'live' => $liveRaw !== null ? $safe($liveRaw, '') : '',
                'tags' => $tags,
            ];
        }
        $featureFirst = $flag($projectData, 'projects_show_featured', false);

        $services = [];
        foreach ($list($servicesData, 'services_list') as $item) {
            $isArray = is_array($item);
            $serviceTitle = $clean(is_string($item) ? $item : ($isArray ? ($item['title'] ?? $item['name'] ?? $item['value'] ?? null) : null));
            if ($serviceTitle === null) {
                continue;
            }
            $services[] = [
                'title' => $serviceTitle,
                'description' => $isArray ? $clean($item['description'] ?? null) : null,
                'icon' => $isArray ? $clean($item['icon'] ?? null) : null,
            ];
        }

        $contactSocials = $socials($contact, 'contact_social_links');
        if ($contactSocials === []) {
            $contactSocials = $heroSocials;
        }
        $email = $text($contact, 'contact_email');
        $phone = $text($contact, 'contact_phone');
        $location = $text($contact, 'contact_location');
        $emailHref = $email !== null ? $this->safeUrl('mailto:'.$email, '') : '';
        $phoneHref = $phone !== null ? $this->safeUrl('tel:'.preg_replace('/[^+\d]/', '', $phone), '') : '';
        $contactLabel = $text($contact, 'contact_subtitle') ?? $text($contact, 'contact_title') ?? $email ?? '';
        $formReady = $flag($contact, 'contact_form_enabled', true) && $googleForm !== null;

        $navLinks = array_values(array_filter([
            ['id' => 'about', 'label' => 'About', 'present' => $hasAbout],
            ['id' => 'journey', 'label' => 'Journey', 'present' => $hasJourney],
            ['id' => 'skills', 'label' => 'Skills', 'present' => $hasSkills],
            ['id' => 'projects', 'label' => 'Work', 'present' => $hasProjects],
            ['id' => 'services', 'label' => 'Services', 'present' => $hasServices],
        ], static fn (array $definition): bool => $definition['present']));

        /* ---------------------------------------------------------------
         | Markup
         * ------------------------------------------------------------- */
        $html = '<div class="ng-root">
    <div class="ng-ambient" aria-hidden="true"><span class="ng-orb ng-orb-a"></span><span class="ng-orb ng-orb-b"></span><span class="ng-orb ng-orb-c"></span><span class="ng-grain"></span></div>';

        // Navbar
        if ($logoImage !== '' || $logoText !== null || $navLinks !== [] || $hasContact) {
            $html .= '<header class="ng-header '.($stickyNav ? 'is-fixed' : 'is-absolute').'"><div class="ng-progress" data-progress aria-hidden="true"></div>';
            $html .= '<nav class="ng-nav" data-nav aria-label="Primary">';
            if ($logoImage !== '' || $logoText !== null) {
                $html .= '<a href="#header" class="ng-logo" data-scroll-link>';
                if ($logoImage !== '') {
                    $html .= '<img src="'.$escape($logoImage).'" alt="'.$escape($logoText ?? '').'" class="ng-logo-img">';
                }
                if ($logoText !== null) {
                    $html .= '<span class="ng-display ng-logo-text">'.$escape($logoText).'</span>';
                }
                $html .= '</a>';
            } else {
                $html .= '<span></span>';
            }
            if ($navLinks !== []) {
                $html .= '<ul class="ng-navlinks">';
                foreach ($navLinks as $link) {
                    $html .= '<li><a href="#'.$escape($link['id']).'" class="ng-navlink" data-active="false" data-section-link="'.$escape($link['id']).'" data-scroll-link>'.$escape($link['label']).'</a></li>';
                }
                $html .= '</ul>';
            }
            if ($hasContact) {
                $html .= '<a href="#contact" class="ng-btn ng-btn-primary ng-btn-sm" data-scroll-link>'.$escape($contactLabel).$icon('arrow-up-right', 16, 'ng-arrow').'</a>';
            } else {
                $html .= '<span></span>';
            }
            $html .= '</nav></header>';
        }

        // Hero
        if ($hasHeader) {
            $heroImage = $mediaUrl($header, 'hero_image');
            $heroBackground = $mediaUrl($header, 'hero_background_image');
            $greeting = $text($header, 'hero_greeting');
            $role = $text($header, 'hero_role');
            $description = $text($header, 'hero_description');

            $html .= '<section id="header" class="ng-hero" data-page-section><span id="home" class="ng-home-anchor" aria-hidden="true"></span>';
            if ($heroBackground !== '') {
                $html .= '<img src="'.$escape($heroBackground).'" alt="" class="ng-hero-bg">';
            }
            $html .= '<div class="ng-vignette" aria-hidden="true"></div>';
            $html .= '<div class="ng-wrap ng-hero-grid"><div class="ng-col-7">';
            if ($greeting !== null) {
                $html .= '<p data-reveal class="ng-chip" style="--d: 0ms"><span class="ng-pulse" aria-hidden="true"></span>'.$escape($greeting).'</p>';
            }
            if ($heroName !== null) {
                $html .= '<h1 class="ng-display ng-hero-name"><span class="sr-only">'.$escape($heroName).'</span>';
                foreach ($heroWords as $index => $word) {
                    $html .= '<span class="ng-mask" aria-hidden="true"><span class="ng-word ng-gradient-text" style="--i: '.$index.'">'.$escape($word).'</span></span>';
                }
                $html .= '</h1>';
            }
            if ($role !== null) {
                $html .= '<p data-reveal class="ng-mono ng-role" style="--d: 380ms">'.$escape($role).'</p>';
            }
            if ($description !== null) {
                $html .= '<p data-reveal class="ng-muted ng-hero-desc" style="--d: 460ms">'.$escape($description).'</p>';
            }
            if ($heroPrimary !== null || $heroSecondary !== null) {
                $html .= '<div data-reveal class="ng-actions" style="--d: 540ms">';
                if ($heroPrimary !== null) {
                    $html .= '<a href="'.$url($heroPrimary['url']).'" class="ng-btn ng-btn-primary" data-scroll-link>'.$escape($heroPrimary['text']).$icon('arrow-up-right', 16, 'ng-arrow').'</a>';
                }
                if ($heroSecondary !== null) {
                    $html .= '<a href="'.$url($heroSecondary['url']).'" class="ng-btn ng-btn-ghost" data-scroll-link>'.$escape($heroSecondary['text']).'</a>';
                }
                $html .= '</div>';
            }
            if ($heroSocials !== []) {
                $html .= '<ul data-reveal class="ng-icons" style="--d: 620ms">';
                foreach ($heroSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($social['label']).'" title="'.$escape($social['label']).'" class="ng-icon-btn">'.$icon($socialIcon($social['platform']), 18).'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><div class="ng-col-5"><figure data-reveal class="ng-portrait" style="--d: 250ms">';
            $html .= '<div class="ng-halo" aria-hidden="true"></div><div class="ng-ring" aria-hidden="true"></div><div class="ng-ring ng-ring-2" aria-hidden="true"></div><div class="ng-photo">';
            if ($heroImage !== '') {
                $html .= '<img src="'.$escape($heroImage).'" alt="'.$escape($heroName ?? '').'">';
            } elseif ($heroInitials !== '') {
                $html .= '<span class="ng-display ng-gradient-bg ng-initials">'.$escape($heroInitials).'</span>';
            }
            $html .= '</div><span class="ng-orbit ng-orbit-a" aria-hidden="true">'.$icon('sparkles', 20).'</span><span class="ng-orbit ng-orbit-b" aria-hidden="true">'.$icon('code', 20).'</span>';
            if ($location !== null) {
                $html .= '<figcaption class="ng-badge ng-glass">'.$icon('map-pin', 16, 'ng-accent2').$escape($location).'</figcaption>';
            }
            $html .= '</figure></div></div></section>';
        }

        // About
        if ($hasAbout) {
            $html .= '<section id="about" class="ng-section" data-page-section><div class="ng-wrap ng-split ng-split-center">';
            if ($aboutImage !== '') {
                $html .= '<div data-reveal class="ng-col-5"><div class="ng-frame ng-glass"><img src="'.$escape($aboutImage).'" alt="'.$escape($text($about, 'about_title') ?? '').'" loading="lazy"></div></div>';
            }
            $html .= '<div class="'.($aboutImage !== '' ? 'ng-col-7' : 'ng-col-10').'"><div data-reveal>';
            if ($text($about, 'about_subtitle') !== null) {
                $html .= '<p class="ng-kicker">'.$escape($text($about, 'about_subtitle')).'</p>';
            }
            if ($text($about, 'about_title') !== null) {
                $html .= '<h2 class="ng-display ng-h2 ng-mt4">'.$escape($text($about, 'about_title')).'</h2>';
            }
            if ($text($about, 'about_description') !== null) {
                $html .= '<p class="ng-muted ng-prose">'.$escape($text($about, 'about_description')).'</p>';
            }
            $html .= '</div>';
            if ($highlights !== []) {
                $html .= '<ul data-reveal class="ng-hl-list">';
                foreach ($highlights as $index => $highlight) {
                    $html .= '<li class="ng-hl ng-glass ng-lift" style="--k: '.$index.'"><span class="ng-tick">'.$icon('check', 14, '', '3').'</span><span><span class="ng-hl-title">'.$escape($highlight['title']).'</span>';
                    if ($highlight['description'] !== null) {
                        $html .= '<span class="ng-muted ng-hl-desc">'.$escape($highlight['description']).'</span>';
                    }
                    $html .= '</span></li>';
                }
                $html .= '</ul>';
            }
            if ($cvFile !== '' || $aboutCta !== null) {
                $html .= '<div data-reveal class="ng-actions ng-actions-lg">';
                if ($cvFile !== '') {
                    $html .= '<a href="'.$escape($cvFile).'" download target="_blank" rel="noopener" class="ng-btn ng-btn-primary">'.$icon('download', 16).'Download CV</a>';
                }
                if ($aboutCta !== null) {
                    $html .= '<a href="'.$url($aboutCta['url']).'" class="ng-btn '.($cvFile !== '' ? 'ng-btn-ghost' : 'ng-btn-primary').'" data-scroll-link>'.$escape($aboutCta['text']).$icon('arrow-up-right', 16, 'ng-arrow').'</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></section>';
        }

        // Journey
        if ($hasJourney) {
            $html .= '<section id="journey" class="ng-section" data-page-section><div class="ng-wrap ng-split"><div class="ng-col-4"><div data-reveal class="ng-sticky">';
            if ($text($journeyData, 'journey_subtitle') !== null) {
                $html .= '<p class="ng-kicker">'.$escape($text($journeyData, 'journey_subtitle')).'</p>';
            }
            if ($text($journeyData, 'journey_title') !== null) {
                $html .= '<h2 class="ng-display ng-h2 ng-mt4">'.$escape($text($journeyData, 'journey_title')).'</h2>';
            }
            $html .= '</div></div><ol class="ng-timeline ng-col-8">';
            foreach ($journey as $index => $item) {
                $html .= '<li data-reveal style="--d: '.(($index % 4) * 70).'ms" class="ng-tl-item"><div class="ng-glass ng-lift ng-card">';
                if ($item['period'] !== null) {
                    $html .= '<p class="ng-mono ng-period">'.$escape($item['period']).'</p>';
                }
                if ($item['title'] !== null) {
                    $html .= '<h3 class="ng-display ng-item-title">'.$escape($item['title']).'</h3>';
                }
                if ($item['company'] !== null) {
                    $html .= '<p class="ng-company">'.$escape($item['company']).'</p>';
                }
                if ($item['description'] !== null) {
                    $html .= '<p class="ng-muted ng-item-desc">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></li>';
            }
            $html .= '</ol></div></section>';
        }

        // Skills
        if ($hasSkills) {
            $html .= '<section id="skills" class="ng-section" data-page-section><div class="ng-wrap ng-split"><div class="ng-col-4"><div data-reveal class="ng-sticky">';
            if ($text($skillsData, 'skills_subtitle') !== null) {
                $html .= '<p class="ng-kicker">'.$escape($text($skillsData, 'skills_subtitle')).'</p>';
            }
            if ($text($skillsData, 'skills_title') !== null) {
                $html .= '<h2 class="ng-display ng-h2 ng-mt4">'.$escape($text($skillsData, 'skills_title')).'</h2>';
            }
            if ($text($skillsData, 'skills_description') !== null) {
                $html .= '<p class="ng-muted ng-skills-desc">'.$escape($text($skillsData, 'skills_description')).'</p>';
            }
            $html .= '</div></div><ul class="ng-skill-list ng-col-8">';
            foreach ($skills as $index => $skill) {
                $html .= '<li data-reveal style="--d: '.(($index % 6) * 50).'ms" class="ng-skill ng-glass ng-lift"><div class="ng-skill-row">';
                if ($showIcon) {
                    $skillIcon = $iconFor($skill['icon']);
                    $html .= '<span class="ng-tile">';
                    if ($skillIcon !== null) {
                        $html .= $icon($skillIcon, 18);
                    } elseif ($isGlyph($skill['icon'])) {
                        $html .= '<span class="ng-tile-glyph" aria-hidden="true">'.$escape($skill['icon']).'</span>';
                    } else {
                        $html .= '<span class="ng-display ng-tile-letter" aria-hidden="true">'.$escape(mb_strtoupper(mb_substr($skill['name'], 0, 1))).'</span>';
                    }
                    $html .= '</span>';
                }
                $html .= '<h3 class="ng-skill-name">'.$escape($skill['name']).'</h3>';
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<span class="ng-count ng-mono ng-muted ng-skill-pct" style="--p: '.(int) round($skill['percent']).'" aria-hidden="true"></span>';
                } elseif ($showLevel && $skill['label'] !== null) {
                    $html .= '<span class="ng-pill ng-pill-end">'.$escape($skill['label']).'</span>';
                }
                $html .= '</div>';
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<div class="ng-track" role="progressbar" aria-valuenow="'.(int) round($skill['percent']).'" aria-valuemin="0" aria-valuemax="100" aria-label="'.$escape($skill['name']).'"><div class="ng-bar" style="--w: '.$num($skill['percent']).'%"><span class="ng-knob"></span></div></div>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></div></section>';
        }

        // Projects
        if ($hasProjects) {
            $html .= '<section id="projects" class="ng-section" data-page-section><div class="ng-wrap ng-block"><div data-reveal class="ng-proj-head"><div class="ng-col-7">';
            if ($text($projectData, 'projects_subtitle') !== null) {
                $html .= '<p class="ng-kicker">'.$escape($text($projectData, 'projects_subtitle')).'</p>';
            }
            if ($text($projectData, 'projects_title') !== null) {
                $html .= '<h2 class="ng-display ng-h2 ng-mt4">'.$escape($text($projectData, 'projects_title')).'</h2>';
            }
            $html .= '</div>';
            if ($text($projectData, 'projects_description') !== null) {
                $html .= '<p class="ng-muted ng-proj-desc ng-col-5">'.$escape($text($projectData, 'projects_description')).'</p>';
            }
            $html .= '</div><ul class="ng-project-grid">';
            foreach ($projects as $index => $project) {
                $featured = $featureFirst && $index === 0;
                $html .= '<li data-reveal'.($featured ? ' class="ng-feature"' : '').' style="--d: '.(($index % 3) * 80).'ms"><article class="ng-project ng-glass">';
                if ($project['live'] !== '') {
                    $html .= '<a href="'.$escape($project['live']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($project['title']).'" class="ng-media'.($featured ? ' ng-media-featured' : '').'">';
                } else {
                    $html .= '<div class="ng-media'.($featured ? ' ng-media-featured' : '').'">';
                }
                if ($project['image'] !== '') {
                    $html .= '<img src="'.$escape($project['image']).'" alt="'.$escape($project['title']).'" loading="lazy">';
                } else {
                    $html .= '<div class="ng-display ng-tone ng-tone-'.($index % 3).'">'.$escape(mb_strtoupper(mb_substr($project['title'], 0, 1))).'</div>';
                }
                if ($featured) {
                    $html .= '<span class="ng-flag ng-mono">'.$icon('sparkles', 14).str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'</span>';
                }
                if ($project['live'] !== '') {
                    $html .= '<span class="ng-go" aria-hidden="true">'.$icon('arrow-up-right', 20).'</span>';
                }
                $html .= $project['live'] !== '' ? '</a>' : '</div>';

                $html .= '<div class="ng-proj-body"><h3 class="ng-display ng-proj-name"><span class="ng-underline">'.$escape($project['title']).'</span></h3>';
                if ($project['description'] !== null) {
                    $html .= '<p class="ng-muted ng-proj-text">'.$escape($project['description']).'</p>';
                }
                if ($project['tags'] !== []) {
                    $html .= '<ul class="ng-tags">';
                    foreach ($project['tags'] as $tag) {
                        $html .= '<li class="ng-pill">'.$escape($tag).'</li>';
                    }
                    $html .= '</ul>';
                }
                if ($project['repo'] !== '') {
                    $html .= '<div class="ng-repo"><a href="'.$escape($project['repo']).'" target="_blank" rel="noopener noreferrer" class="ng-textlink ng-mono">'.$icon('code', 16).$escape($project['repo_label']).'</a></div>';
                }
                $html .= '</div></article></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Services
        if ($hasServices) {
            $html .= '<section id="services" class="ng-section" data-page-section><div class="ng-wrap ng-block"><div data-reveal class="ng-services-head">';
            if ($text($servicesData, 'services_subtitle') !== null) {
                $html .= '<p class="ng-kicker">'.$escape($text($servicesData, 'services_subtitle')).'</p>';
            }
            if ($text($servicesData, 'services_title') !== null) {
                $html .= '<h2 class="ng-display ng-h2 ng-mt4">'.$escape($text($servicesData, 'services_title')).'</h2>';
            }
            $html .= '</div><ul class="ng-service-grid">';
            foreach ($services as $index => $service) {
                $html .= '<li data-reveal style="--d: '.(($index % 6) * 70).'ms"><div class="ng-service ng-glass ng-lift"><span class="ng-tile ng-tile-lg">';
                if (! $isGlyph($service['icon'])) {
                    $html .= $icon($iconFor($service['icon']) ?? 'sparkles', 24);
                } else {
                    $html .= '<span class="ng-tile-glyph-lg" aria-hidden="true">'.$escape($service['icon']).'</span>';
                }
                $html .= '</span><h3 class="ng-display ng-service-title">'.$escape($service['title']).'</h3>';
                if ($service['description'] !== null) {
                    $html .= '<p class="ng-muted ng-service-text">'.$escape($service['description']).'</p>';
                }
                $html .= $icon('arrow-up-right', 20, 'ng-service-arrow').'</div></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Contact (+ footer)
        if ($hasContact) {
            $html .= '<section id="contact" class="ng-section ng-contact-section" data-page-section><div data-reveal class="ng-contact ng-glass"><div class="ng-contact-inner"><div class="ng-col-7"><div data-reveal>';
            if ($text($contact, 'contact_subtitle') !== null) {
                $html .= '<p class="ng-kicker">'.$escape($text($contact, 'contact_subtitle')).'</p>';
            }
            if ($text($contact, 'contact_title') !== null) {
                $html .= '<h2 class="ng-display ng-gradient-text ng-contact-title">'.$escape($text($contact, 'contact_title')).'</h2>';
            }
            if ($text($contact, 'contact_description') !== null) {
                $html .= '<p class="ng-muted ng-contact-text">'.$escape($text($contact, 'contact_description')).'</p>';
            }
            $html .= '</div>';
            if ($email !== null || $phone !== null || $location !== null) {
                $html .= '<div data-reveal class="ng-contact-info">';
                if ($email !== null && $emailHref !== '') {
                    $html .= '<a href="'.$escape($emailHref).'" class="ng-bigmail ng-display">'.$icon('mail', 28, 'ng-accent2').'<span class="ng-underline-contact">'.$escape($email).'</span>'.$icon('arrow-up-right', 28, 'ng-arrow').'</a>';
                }
                if ($phone !== null || $location !== null) {
                    $html .= '<div class="ng-contact-meta">';
                    if ($phone !== null && $phoneHref !== '') {
                        $html .= '<a href="'.$escape($phoneHref).'" class="ng-contact-link">'.$icon('phone', 16, 'ng-accent2').$escape($phone).'</a>';
                    }
                    if ($location !== null) {
                        $html .= '<span>'.$icon('map-pin', 16, 'ng-accent2').$escape($location).'</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            if ($contactSocials !== []) {
                $html .= '<ul data-reveal class="ng-contact-socials">';
                foreach ($contactSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" class="ng-social">'.$icon($socialIcon($social['platform']), 14).$escape($social['label']).$icon('arrow-up-right', 14, 'ng-arrow').'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><div class="ng-col-5"><div data-reveal style="--d: 120ms">';

            if ($formReady) {
                $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="ng-gform-frame" class="ng-form" data-google-form>
                <label class="ng-field"><span class="ng-mono ng-muted ng-label">Name</span><input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="ng-input"></label>
                <label class="ng-field"><span class="ng-mono ng-muted ng-label">Email</span><input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="ng-input"></label>
                <label class="ng-field"><span class="ng-mono ng-muted ng-label">Message</span><textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required class="ng-input"></textarea></label>
                <button type="submit" class="ng-btn ng-btn-primary ng-btn-block"><span data-submit-label>Send</span>'.$icon('send', 16, 'ng-arrow').'</button>
                <p class="ng-sent" role="status" data-form-status hidden><span class="ng-tick">'.$icon('check', 14, '', '3').'</span>OK</p>
            </form>';
            } elseif ($email !== null && $emailHref !== '') {
                $html .= '<a href="'.$escape($emailHref).'" class="ng-btn ng-btn-ghost ng-btn-block ng-btn-xl">'.$icon('mail', 20).$escape($email).'</a>';
            }
            $html .= '</div>';
            if ($formReady) {
                $html .= '<iframe name="ng-gform-frame" title="Form submission target" class="ng-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
            }
            $html .= '</div></div></div>';

            if ($heroName !== null || $logoText !== null) {
                $html .= '<footer class="ng-foot"><span class="ng-muted ng-mono ng-foot-copy">&copy; '.date('Y').' '.$escape($heroName ?? $logoText).'</span><a href="#header" class="ng-textlink" aria-label="Back to top" data-scroll-link>'.$icon('arrow-up-right', 16, 'ng-arrow ng-rot').'</a></footer>';
            }
            $html .= '</section>';
        }
        $html .= '</div>';

        return '<!doctype html>
<html lang="en" data-template="nebula-glass">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#05070e" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#eef1fb" media="(prefers-color-scheme: light)">
    <title>'.$escape($title).'</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    /** Inline Lucide-style icons (static, trusted markup only). */
    private function nebulaGlassIcon(string $name, int $size = 16, string $class = '', string $stroke = '2'): string
    {
        $paths = [
            'arrow-up-right' => '<path d="M7 7h10v10"/><path d="M7 17 17 7"/>',
            'at-sign' => '<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>',
            'briefcase' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
            'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'code' => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
            'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
            'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
            'dribbble' => '<circle cx="12" cy="12" r="10"/><path d="M19.13 5.09C15.22 9.14 10 10.44 2.25 10.94"/><path d="M21.75 12.84c-6.62-1.41-12.14 1-16.38 6.32"/><path d="M8.56 2.75c4.37 6 6 9.42 8 17.72"/>',
            'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
            'github' => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/>',
            'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
            'instagram' => '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
            'layers' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
            'link' => '<path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><line x1="8" x2="16" y1="12" y2="12"/>',
            'linkedin' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/>',
            'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
            'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
            'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
            'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
            'palette' => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
            'pen-tool' => '<path d="M15.707 21.293a1 1 0 0 1-1.414 0l-1.586-1.586a1 1 0 0 1 0-1.414l5.586-5.586a1 1 0 0 1 1.414 0l1.586 1.586a1 1 0 0 1 0 1.414z"/><path d="m18 13-1.375-6.874a1 1 0 0 0-.746-.776L3.235 2.028a1 1 0 0 0-1.207 1.207L5.35 15.879a1 1 0 0 0 .776.746L13 18"/><path d="m2.3 2.3 7.286 7.286"/><circle cx="11" cy="11" r="2"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'rocket' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
            'send' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
            'server' => '<rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/>',
            'smartphone' => '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
            'sparkles' => '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>',
            'terminal' => '<polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/>',
            'youtube' => '<path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
        ];
        $body = $paths[$name] ?? $paths['globe'];

        return '<svg class="ng-ico'.($class !== '' ? ' '.$class : '').'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$stroke.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$body.'</svg>';
    }

    private function nebulaGlassStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');
/* ---------- Reset (Tailwind-preflight equivalent, low specificity) ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;color-scheme:dark light;-webkit-text-size-adjust:100%}
body{margin:0;background:#05070e}
@media(prefers-color-scheme:light){body{background:#eef1fb}}
:where(.ng-root) :is(h1,h2,h3,p,ul,ol,figure){margin:0}
:where(.ng-root) :is(ul,ol){padding:0;list-style:none}
:where(.ng-root) :is(h1,h2,h3){font-size:inherit;font-weight:inherit}
:where(.ng-root) a{color:inherit;text-decoration:none}
:where(.ng-root) :is(button,input,textarea){font:inherit;color:inherit;letter-spacing:inherit}
:where(.ng-root) button{background:none;border:0;padding:0;cursor:pointer}
:where(.ng-root) img,:where(.ng-root) svg{display:block;max-width:100%}
.ng-root [hidden]{display:none!important}
.ng-ico{flex-shrink:0}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

/* ---------- Foundations + palette (dark by default, light via OS preference) ---------- */
.ng-root{--paper:#05070E;--soft:#080B16;--surface:#0D111D80;--ink:#F0F4FF;--muted:#94A3B8;--line:#1E293B;--accent:#6366F1;--accent-2:#22D3EE;--accent-ink:#FFFFFF;position:relative;min-height:100vh;background:var(--paper);color:var(--ink);font-family:'Inter',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;overflow-x:clip;transition:background-color .4s ease,color .4s ease}
@media(prefers-color-scheme:light){.ng-root{--paper:#eef1fb;--soft:#e6eaf8;--surface:#ffffffb3;--ink:#0b1020;--muted:#4b5675;--line:#c9d0e8;--accent:#4f46e5;--accent-2:#0891b2}}
.ng-root ::selection{background:var(--accent);color:#fff}
.ng-display{font-family:'Space Grotesk','Inter',ui-sans-serif,system-ui,sans-serif;font-weight:600;letter-spacing:-.035em}
.ng-mono{font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,monospace}
.ng-muted{color:var(--muted)}
.ng-accent2{color:var(--accent-2)}
.ng-mt4{margin-top:1rem}
.ng-h2{font-size:clamp(2.25rem,5vw,4rem);line-height:1.04;letter-spacing:-.04em;text-wrap:balance;transition:letter-spacing 1.2s cubic-bezier(.2,.7,.2,1)}
.ng-gradient-text{background:linear-gradient(100deg,var(--ink) 10%,var(--accent) 55%,var(--accent-2) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
.ng-gradient-bg{background:linear-gradient(135deg,var(--accent),var(--accent-2))}
.ng-root :where(a,button,input,textarea):focus-visible{outline:2px solid var(--accent-2);outline-offset:3px;border-radius:.5rem}

/* Frosted glass surface */
.ng-glass{position:relative;background:var(--surface);border:1px solid var(--line);border-radius:1.5rem;-webkit-backdrop-filter:blur(14px) saturate(1.4);backdrop-filter:blur(14px) saturate(1.4);box-shadow:inset 0 1px 0 color-mix(in srgb,var(--ink) 8%,transparent),0 24px 48px -28px #000000b3}
.ng-lift{transition:transform .45s cubic-bezier(.2,.7,.2,1),border-color .35s ease,box-shadow .45s ease}
.ng-lift:hover{transform:translateY(-4px);border-color:color-mix(in srgb,var(--accent) 65%,var(--line));box-shadow:inset 0 1px 0 color-mix(in srgb,var(--ink) 12%,transparent),0 0 0 1px color-mix(in srgb,var(--accent) 30%,transparent),0 28px 60px -24px color-mix(in srgb,var(--accent) 55%,transparent)}

/* Ambient background */
.ng-ambient{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none;background:radial-gradient(60% 50% at 15% 0%,color-mix(in srgb,var(--accent) 22%,transparent),transparent 70%),radial-gradient(50% 45% at 100% 30%,color-mix(in srgb,var(--accent-2) 14%,transparent),transparent 70%),radial-gradient(70% 60% at 50% 110%,color-mix(in srgb,var(--accent) 16%,transparent),transparent 70%)}
.ng-orb{position:absolute;border-radius:999px;filter:blur(80px);opacity:.45}
.ng-orb-a{height:28rem;width:28rem;left:-8rem;top:18%;background:var(--accent);animation:ng-drift 22s ease-in-out infinite}
.ng-orb-b{height:22rem;width:22rem;right:-6rem;top:45%;background:var(--accent-2);opacity:.25;animation:ng-drift 28s ease-in-out infinite reverse}
.ng-orb-c{height:26rem;width:26rem;left:35%;bottom:-10rem;background:var(--accent);opacity:.3;animation:ng-drift 34s ease-in-out infinite}
.ng-grain{position:absolute;inset:0;background-image:linear-gradient(var(--line) 1px,transparent 1px),linear-gradient(90deg,var(--line) 1px,transparent 1px);background-size:56px 56px;opacity:.18;-webkit-mask-image:radial-gradient(70% 60% at 50% 30%,#000,transparent 80%);mask-image:radial-gradient(70% 60% at 50% 30%,#000,transparent 80%)}
.ng-section{position:relative;scroll-margin-top:1rem}

/* Section subtitle */
.ng-kicker{display:inline-flex;align-items:center;gap:.7rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.16em;color:var(--accent-2)}
.ng-kicker::before{content:'';width:1.75rem;height:1px;background:linear-gradient(90deg,var(--accent),var(--accent-2));box-shadow:0 0 10px var(--accent-2);transform-origin:left;transition:transform .7s cubic-bezier(.2,.7,.2,1) .2s}
.ng-js [data-reveal]:not(.is-in) .ng-kicker::before{transform:scaleX(0)}

/* ---------- Layout helpers ---------- */
.ng-wrap{width:100%;max-width:1240px;margin-inline:auto;padding-inline:1.25rem}
@media(min-width:640px){.ng-wrap{padding-inline:2rem}}
.ng-wrap>*,.ng-col-4>*,.ng-col-5>*,.ng-col-7>*,.ng-col-8>*{min-width:0}
.ng-split{display:grid;gap:3rem;padding-block:7rem}
.ng-split-center{align-items:center;gap:3.5rem}
.ng-block{padding-block:7rem}
.ng-col-4,.ng-col-5,.ng-col-7,.ng-col-8,.ng-col-10{min-width:0}
@media(min-width:1024px){
.ng-split{grid-template-columns:repeat(12,minmax(0,1fr));gap:4rem}
.ng-col-4{grid-column:span 4/span 4}.ng-col-5{grid-column:span 5/span 5}.ng-col-7{grid-column:span 7/span 7}.ng-col-8{grid-column:span 8/span 8}.ng-col-10{grid-column:span 10/span 10}
.ng-sticky{position:sticky;top:7rem}
}

/* ---------- Navbar ---------- */
.ng-header{position:fixed;inset:0 0 auto 0;z-index:500;padding:.75rem .75rem 0}
.ng-header.is-absolute{position:absolute}
@media(min-width:640px){.ng-header{padding:1rem 1.5rem 0}}
.ng-progress{position:fixed;inset:0 0 auto 0;height:2px;background:linear-gradient(90deg,var(--accent),var(--accent-2));box-shadow:0 0 12px var(--accent);transform:scaleX(0);transform-origin:left;pointer-events:none;z-index:60}
.ng-nav{display:flex;height:3.5rem;max-width:1240px;margin-inline:auto;align-items:center;justify-content:space-between;gap:.75rem;padding:0 .5rem 0 1.25rem;border:1px solid var(--line);border-radius:999px;background:color-mix(in srgb,var(--paper) 45%,transparent);-webkit-backdrop-filter:blur(16px) saturate(1.5);backdrop-filter:blur(16px) saturate(1.5);transition:border-color .35s ease,background-color .35s ease,box-shadow .35s ease}
.ng-nav.is-scrolled{background:color-mix(in srgb,var(--paper) 75%,transparent);box-shadow:0 14px 40px -16px color-mix(in srgb,var(--accent) 45%,#000)}
.ng-logo{display:flex;min-width:0;align-items:center;gap:.625rem}
.ng-logo-img{height:1.75rem;width:auto;max-width:7rem;object-fit:contain}
.ng-logo-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:1rem}
.ng-navlinks{display:none;align-items:center;gap:.25rem}
@media(min-width:768px){.ng-navlinks{display:flex}}
.ng-navlink{display:inline-block;border-radius:999px;padding:.5rem 1rem;font-size:.92rem;font-weight:500;color:var(--muted);transition:color .25s ease,background-color .25s ease,box-shadow .25s ease}
.ng-navlink:hover{color:var(--ink)}
.ng-navlink[data-active='true']{color:var(--ink);background:color-mix(in srgb,var(--accent) 20%,transparent);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--accent) 45%,transparent)}

/* ---------- Buttons & small controls ---------- */
.ng-btn{position:relative;isolation:isolate;display:inline-flex;align-items:center;gap:.5rem;border-radius:999px;padding:.85rem 1.5rem;font-size:.95rem;font-weight:600;line-height:1.2;cursor:pointer;border:1px solid transparent;transition:transform .25s ease,box-shadow .35s ease,border-color .3s ease,background-color .3s ease}
.ng-btn:hover{transform:translateY(-2px)}
.ng-btn:active{transform:translateY(0) scale(.98)}
.ng-btn[disabled]{opacity:.7;pointer-events:none}
.ng-btn-primary{background:linear-gradient(120deg,var(--accent),color-mix(in srgb,var(--accent) 55%,var(--accent-2)));color:var(--accent-ink);box-shadow:0 0 0 1px color-mix(in srgb,var(--accent) 60%,transparent),0 10px 28px -10px var(--accent)}
.ng-btn-primary:hover{box-shadow:0 0 0 1px var(--accent-2),0 14px 36px -8px var(--accent),0 0 28px -4px color-mix(in srgb,var(--accent-2) 70%,transparent)}
.ng-btn-ghost{background:var(--surface);border-color:var(--line);color:var(--ink);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px)}
.ng-btn-ghost:hover{border-color:var(--accent);box-shadow:0 0 24px -6px color-mix(in srgb,var(--accent) 70%,transparent)}
.ng-btn-sm{padding:.625rem 1.25rem;font-size:.875rem}
.ng-btn-block{width:100%;justify-content:center}
.ng-btn-xl{padding-block:1.25rem;font-size:1.125rem}
.ng-arrow{transition:transform .3s cubic-bezier(.2,.7,.2,1)}
.ng-rot{transform:rotate(-45deg)}
.ng-btn:hover .ng-arrow,.ng-bigmail:hover .ng-arrow,.ng-social:hover .ng-arrow,.ng-textlink:hover .ng-arrow{transform:translate(3px,-3px)}
.ng-icon-btn{display:inline-flex;height:2.75rem;width:2.75rem;align-items:center;justify-content:center;border-radius:999px;border:1px solid var(--line);background:var(--surface);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);transition:transform .3s cubic-bezier(.2,.7,.2,1),background-color .25s ease,border-color .25s ease,box-shadow .3s ease}
.ng-icon-btn:hover{transform:translateY(-3px);border-color:var(--accent);background:color-mix(in srgb,var(--accent) 25%,var(--surface));box-shadow:0 0 22px -4px var(--accent)}
.ng-chip{display:inline-flex;align-items:center;gap:.6rem;border-radius:999px;border:1px solid var(--line);background:var(--surface);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);padding:.45rem 1rem .45rem .85rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.8rem;font-weight:500}
.ng-pulse{position:relative;height:.5rem;width:.5rem;border-radius:999px;background:var(--accent-2);box-shadow:0 0 10px var(--accent-2)}
.ng-pulse::after{content:'';position:absolute;inset:0;border-radius:inherit;background:inherit;animation:ng-ping 2.2s cubic-bezier(0,0,.2,1) infinite}
.ng-pill{display:inline-block;border-radius:999px;border:1px solid color-mix(in srgb,var(--accent) 40%,var(--line));background:color-mix(in srgb,var(--accent) 12%,transparent);padding:.2rem .7rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.72rem;font-weight:500;color:color-mix(in srgb,var(--ink) 80%,var(--accent))}
.ng-pill-end{margin-left:auto}
.ng-tick{display:inline-flex;height:1.5rem;width:1.5rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:999px;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;box-shadow:0 0 14px -2px var(--accent)}
.ng-textlink{display:inline-flex;align-items:center;gap:.4rem;font-weight:600;background:linear-gradient(var(--accent-2),var(--accent-2)) left bottom/0% 1.5px no-repeat;transition:background-size .4s cubic-bezier(.2,.7,.2,1);padding-bottom:2px}
.ng-textlink:hover{background-size:100% 1.5px}

/* ---------- Hero ---------- */
.ng-hero{position:relative;isolation:isolate;display:flex;min-height:100vh;min-height:100svh;align-items:center;overflow:hidden}
.ng-home-anchor{position:absolute;top:0}
.ng-hero-bg{position:absolute;inset:0;z-index:-10;height:100%;width:100%;object-fit:cover;opacity:.2}
.ng-vignette{position:absolute;inset:0;z-index:-10;background:radial-gradient(60% 60% at 70% 40%,transparent,color-mix(in srgb,var(--paper) 70%,transparent))}
.ng-hero-grid{display:grid;align-items:center;gap:4rem;padding-top:9rem;padding-bottom:5rem}
@media(min-width:1024px){.ng-hero-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:2.5rem;padding-top:8rem}}
.ng-hero-name{margin-top:1.75rem;font-size:clamp(3.25rem,9.5vw,8rem);line-height:.96;letter-spacing:-.05em;overflow-wrap:anywhere}
.ng-mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.14em;margin-bottom:-.14em;margin-right:.22em}
.ng-word{display:inline-block}
.ng-js .ng-word{transform:translateY(110%);animation:ng-rise 1s cubic-bezier(.2,.8,.2,1) forwards;animation-delay:calc(.15s + var(--i,0)*.1s)}
.ng-role{margin-top:1.5rem;font-size:.875rem;text-transform:uppercase;letter-spacing:.18em;color:var(--accent-2)}
@media(min-width:640px){.ng-role{font-size:1rem}}
.ng-hero-desc{max-width:34rem;margin-top:1.5rem;white-space:pre-line;font-size:1.125rem;line-height:1.7}
.ng-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2.25rem}
.ng-actions-lg{margin-top:2.5rem}
.ng-icons{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:2.25rem}
.ng-portrait{position:relative;margin-inline:auto;aspect-ratio:1;width:74%;max-width:23rem}
@media(min-width:640px){.ng-portrait{width:100%}}
.ng-halo{position:absolute;inset:-12%;border-radius:999px;background:radial-gradient(closest-side,color-mix(in srgb,var(--accent) 55%,transparent),transparent 72%);filter:blur(28px);animation:ng-breathe 6s ease-in-out infinite}
.ng-ring{position:absolute;inset:-1.1rem;border-radius:999px;border:1.5px dashed color-mix(in srgb,var(--accent-2) 60%,transparent);animation:ng-spin 48s linear infinite}
.ng-ring-2{inset:-2.3rem;border:1px solid var(--line);animation-duration:30s;animation-direction:reverse}
.ng-ring-2::after{content:'';position:absolute;top:0;left:50%;height:.8rem;width:.8rem;transform:translate(-50%,-50%);border-radius:999px;background:var(--accent-2);box-shadow:0 0 0 5px color-mix(in srgb,var(--accent-2) 22%,transparent),0 0 18px var(--accent-2)}
.ng-photo{position:absolute;inset:0;overflow:hidden;border-radius:999px;border:1px solid color-mix(in srgb,var(--ink) 25%,transparent);padding:0;background:var(--soft);box-shadow:0 0 0 6px color-mix(in srgb,var(--paper) 60%,transparent),0 30px 60px -24px #000000cc}
.ng-photo img{width:100%;height:100%;object-fit:cover;transition:transform .9s ease-out}
.ng-photo:hover img{transform:scale(1.06)}
.ng-initials{display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:7rem;color:#fff}
.ng-orbit{position:absolute;display:inline-flex;height:3.25rem;width:3.25rem;align-items:center;justify-content:center;border-radius:1rem;border:1px solid var(--line);background:var(--surface);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);color:var(--accent-2);box-shadow:0 0 24px -6px var(--accent);animation:ng-float 5.5s ease-in-out infinite}
.ng-orbit-a{top:4%;right:-3%}
.ng-orbit-b{bottom:14%;left:-7%;color:var(--accent);animation-duration:6.5s;animation-delay:-2s}
.ng-badge{position:absolute;bottom:-1.1rem;left:50%;transform:translateX(-50%);display:inline-flex;align-items:center;gap:.5rem;white-space:nowrap;border-radius:999px;padding:.55rem 1rem;font-size:.875rem;font-weight:600}

/* ---------- About ---------- */
.ng-frame{margin-inline:auto;max-width:28rem;padding:.5rem;transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.ng-frame::before{content:'';position:absolute;inset:-1px;z-index:-1;border-radius:inherit;background:linear-gradient(135deg,var(--accent),transparent 40%,var(--accent-2));opacity:.55;filter:blur(14px)}
.ng-frame:hover{transform:rotate(-1deg) scale(1.01)}
.ng-frame img{display:block;width:100%;aspect-ratio:4/5;border-radius:1.1rem;object-fit:cover}
.ng-prose{max-width:38rem;margin-top:1.75rem;white-space:pre-line;font-size:1.125rem;line-height:1.75}
.ng-hl-list{display:grid;gap:.75rem;margin-top:2.5rem}
@media(min-width:640px){.ng-hl-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
.ng-hl{display:flex;align-items:flex-start;gap:.9rem;padding:1.15rem 1.25rem;border-radius:1.1rem}
.ng-hl-title{display:block;font-weight:600;line-height:1.375}
.ng-hl-desc{display:block;margin-top:.25rem;font-size:.92rem;line-height:1.625}

/* ---------- Journey ---------- */
.ng-timeline{position:relative;margin-left:.5rem;border-left:1px solid var(--line);padding-left:2.25rem}
.ng-tl-item{position:relative;padding-bottom:1.5rem}
.ng-tl-item:last-child{padding-bottom:0}
.ng-tl-item::before{content:'';position:absolute;left:-2.25rem;top:1.7rem;height:.8rem;width:.8rem;transform:translateX(-50%);border-radius:999px;background:var(--paper);border:2px solid var(--accent);transition:background-color .5s ease .3s,box-shadow .4s ease}
.ng-js .ng-tl-item.is-in::before{background:var(--accent-2);border-color:var(--accent-2);box-shadow:0 0 14px var(--accent-2)}
.ng-tl-item::after{content:'';position:absolute;left:calc(-2.25rem - 1px);top:0;bottom:0;width:1px;background:linear-gradient(var(--accent),var(--accent-2));box-shadow:0 0 8px var(--accent);transform:scaleY(0);transform-origin:top;transition:transform 1.1s cubic-bezier(.5,0,.2,1) .2s}
.ng-js .ng-tl-item.is-in::after{transform:scaleY(1)}
.ng-tl-item:last-child::after{display:none}
.ng-card{padding:1.5rem}
@media(min-width:640px){.ng-card{padding:1.75rem}}
.ng-period{font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.14em;color:var(--accent-2)}
.ng-item-title{margin-top:.5rem;font-size:1.5rem;line-height:1.25}
.ng-company{margin-top:.25rem;font-weight:500;color:var(--accent)}
.ng-item-desc{max-width:38rem;margin-top:.75rem;white-space:pre-line;line-height:1.7}

/* ---------- Skills ---------- */
.ng-skills-desc{margin-top:1.5rem;white-space:pre-line;line-height:1.7}
.ng-skill-list{display:grid;align-content:start;gap:1rem}
@media(min-width:640px){.ng-skill-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
.ng-skill{padding:1.4rem 1.4rem 1.5rem}
.ng-skill-row{display:flex;align-items:center;gap:.875rem}
.ng-skill-name{font-weight:600}
.ng-skill-pct{margin-left:auto;font-size:.8rem;font-weight:500}
.ng-tile{display:inline-flex;height:2.5rem;width:2.5rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:.85rem;background:color-mix(in srgb,var(--accent) 16%,transparent);border:1px solid color-mix(in srgb,var(--accent) 40%,var(--line));color:var(--accent-2);transition:background-color .3s ease,color .3s ease,box-shadow .3s ease,transform .35s cubic-bezier(.2,.7,.2,1)}
.ng-tile-lg{height:3.25rem;width:3.25rem;border-radius:1rem}
.ng-tile-letter{font-size:1rem;line-height:1}
.ng-tile-glyph{font-size:1.125rem;line-height:1}
.ng-tile-glyph-lg{font-size:1.5rem;line-height:1}
.ng-skill:hover .ng-tile,.ng-service:hover .ng-tile{background:var(--accent);color:#fff;box-shadow:0 0 22px -2px var(--accent);transform:rotate(-6deg) scale(1.06)}
.ng-track{position:relative;height:6px;width:100%;margin-top:1.25rem;border-radius:999px;background:color-mix(in srgb,var(--ink) 10%,transparent)}
.ng-bar{position:relative;height:100%;width:var(--w,0%);border-radius:inherit;background:linear-gradient(90deg,var(--accent),var(--accent-2));box-shadow:0 0 14px -2px var(--accent)}
.ng-knob{position:absolute;right:0;top:50%;height:14px;width:14px;transform:translate(50%,-50%);border-radius:999px;border:3px solid var(--accent-2);background:var(--paper);box-shadow:0 0 12px var(--accent-2)}
.ng-js [data-reveal] .ng-bar{width:0;transition:width 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s)}
.ng-js [data-reveal].is-in .ng-bar{width:var(--w,0%)}
.ng-js [data-reveal].is-in .ng-knob{animation:ng-knob .6s cubic-bezier(.2,.8,.2,1) calc(var(--d,0ms) + 1.6s) backwards}
@property --n{syntax:'<integer>';inherits:false;initial-value:0}
.ng-count{--n:var(--p,0);counter-reset:n var(--n);font-variant-numeric:tabular-nums}
.ng-count::after{content:counter(n) '%'}
.ng-js [data-reveal] .ng-count{--n:0}
.ng-js [data-reveal].is-in .ng-count{animation:ng-count 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s) forwards}

/* ---------- Projects ---------- */
.ng-proj-head{display:grid;gap:1.5rem;margin-bottom:4rem}
@media(min-width:1024px){.ng-proj-head{grid-template-columns:repeat(12,minmax(0,1fr));align-items:end}}
.ng-proj-desc{white-space:pre-line;line-height:1.7}
.ng-project-grid{display:grid;gap:1.5rem}
@media(min-width:768px){.ng-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ng-project-grid>.ng-feature{grid-column:span 2/span 2}}
@media(min-width:1280px){.ng-project-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.ng-project-grid>li{min-width:0}
.ng-project{display:flex;height:100%;flex-direction:column;padding:.75rem;transition:transform .45s cubic-bezier(.2,.7,.2,1),border-color .35s ease,box-shadow .45s ease}
.ng-project:hover{transform:translateY(-5px);border-color:color-mix(in srgb,var(--accent) 65%,var(--line));box-shadow:0 0 0 1px color-mix(in srgb,var(--accent) 30%,transparent),0 30px 64px -24px color-mix(in srgb,var(--accent) 60%,transparent)}
.ng-media{position:relative;display:block;overflow:hidden;aspect-ratio:4/3;border-radius:1.1rem;background:var(--soft);border:1px solid var(--line);isolation:isolate}
.ng-media-featured{aspect-ratio:16/9}
.ng-media img{width:100%;height:100%;object-fit:cover}
.ng-media img,.ng-tone{transition:transform .9s cubic-bezier(.2,.7,.2,1)}
.ng-project:hover .ng-media img,.ng-project:hover .ng-tone{transform:scale(1.06)}
.ng-tone{display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:6rem;line-height:1}
.ng-tone-0{background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 40%,#000));color:#fff}
.ng-tone-1{background:linear-gradient(135deg,var(--accent-2),color-mix(in srgb,var(--accent-2) 40%,#000));color:#fff}
.ng-tone-2{background:linear-gradient(135deg,color-mix(in srgb,var(--accent) 60%,var(--accent-2)),var(--soft));color:#fff}
.ng-flag{position:absolute;left:.8rem;top:.8rem;display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;background:color-mix(in srgb,var(--paper) 70%,transparent);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border:1px solid var(--accent-2);color:var(--accent-2);padding:.25rem .7rem;font-size:.75rem;font-weight:500;box-shadow:0 0 16px -4px var(--accent-2)}
.ng-go{position:absolute;right:.8rem;top:.8rem;display:inline-flex;height:2.75rem;width:2.75rem;align-items:center;justify-content:center;border-radius:999px;background:color-mix(in srgb,var(--paper) 70%,transparent);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border:1px solid var(--line);color:var(--ink);opacity:0;transform:translate(-.5rem,.5rem) scale(.9);transition:opacity .35s ease,transform .45s cubic-bezier(.2,.7,.2,1)}
.ng-project:hover .ng-go,.ng-media:focus-visible .ng-go{opacity:1;transform:none}
.ng-proj-body{display:flex;flex:1;flex-direction:column;padding:1.25rem .75rem .75rem}
.ng-proj-name{font-size:1.5rem;line-height:1.25}
.ng-underline{background:linear-gradient(90deg,var(--accent),var(--accent-2)) left 96%/0% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1)}
.ng-project:hover .ng-underline{background-size:100% 2px}
.ng-proj-text{margin-top:.75rem;line-height:1.65}
.ng-tags{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
.ng-repo{margin-top:auto;padding-top:1.25rem}
.ng-repo .ng-textlink{font-size:.8rem}

/* ---------- Services ---------- */
.ng-services-head{max-width:48rem;margin-bottom:3.5rem}
.ng-service-grid{display:grid;gap:1rem}
@media(min-width:640px){.ng-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.ng-service-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.ng-service-grid>li{min-width:0}
.ng-service{display:flex;height:100%;flex-direction:column;padding:1.75rem}
.ng-service-title{margin-top:1.5rem;font-size:1.5rem;line-height:1.25;transition:color .3s ease}
.ng-service:hover .ng-service-title{color:var(--accent-2)}
.ng-service-text{margin-top:.75rem;line-height:1.65}
.ng-service-arrow{margin-top:auto;align-self:flex-end;color:var(--accent-2);opacity:0;transform:translate(-.5rem,.5rem);transition:opacity .3s ease,transform .4s cubic-bezier(.2,.7,.2,1)}
.ng-service:hover .ng-service-arrow{opacity:1;transform:none}

/* ---------- Contact ---------- */
.ng-contact-section{padding:6rem .75rem 1.5rem}
@media(min-width:640px){.ng-contact-section{padding-inline:1.5rem}}
.ng-contact{max-width:1240px;margin-inline:auto;border-radius:2.25rem;overflow:hidden;border-color:color-mix(in srgb,var(--accent) 40%,var(--line));box-shadow:inset 0 1px 0 color-mix(in srgb,var(--ink) 10%,transparent),0 0 80px -30px var(--accent)}
.ng-contact::before,.ng-contact::after{content:'';position:absolute;border-radius:999px;pointer-events:none;filter:blur(60px)}
.ng-contact::before{height:24rem;width:24rem;right:-8rem;top:-10rem;background:var(--accent);opacity:.35;animation:ng-drift 16s ease-in-out infinite}
.ng-contact::after{height:16rem;width:16rem;left:-5rem;bottom:-6rem;background:var(--accent-2);opacity:.22;animation:ng-drift 20s ease-in-out infinite reverse}
.ng-contact-inner{position:relative;z-index:10;display:grid;gap:3.5rem;padding:1.75rem}
@media(min-width:640px){.ng-contact-inner{padding:3rem}}
@media(min-width:1024px){.ng-contact-inner{grid-template-columns:repeat(12,minmax(0,1fr));gap:3rem;padding:4rem}}
.ng-contact-title{margin-top:1rem;font-size:clamp(2.75rem,7.5vw,6rem);line-height:.95;overflow-wrap:anywhere}
.ng-contact-text{max-width:34rem;margin-top:1.5rem;white-space:pre-line;font-size:1.125rem;line-height:1.7}
.ng-contact-info{margin-top:2.5rem}
.ng-contact-info>*+*{margin-top:1.25rem}
.ng-bigmail{display:inline-flex;max-width:100%;align-items:center;gap:.75rem;word-break:break-all;font-size:1.5rem}
@media(min-width:640px){.ng-bigmail{font-size:2.25rem}}
.ng-bigmail .ng-ico{flex-shrink:0}
.ng-underline-contact{background:linear-gradient(90deg,var(--accent),var(--accent-2)) left bottom/100% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1);padding-bottom:4px}
.ng-bigmail:hover .ng-underline-contact{background-size:0% 2px;background-position:right bottom}
.ng-contact-meta{display:flex;flex-wrap:wrap;gap:.75rem 2rem;font-weight:500}
.ng-contact-meta>span{display:inline-flex;align-items:center;gap:.5rem}
.ng-contact-link{display:inline-flex;align-items:center;gap:.5rem}
.ng-contact-link:hover{text-decoration:underline;text-underline-offset:4px}
.ng-contact-socials{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:2rem}
.ng-social{display:inline-flex;align-items:center;gap:.45rem;border-radius:999px;border:1px solid var(--line);background:color-mix(in srgb,var(--ink) 5%,transparent);padding:.45rem 1rem;font-size:.88rem;font-weight:600;transition:background-color .3s ease,border-color .3s ease,box-shadow .3s ease,transform .3s ease}
.ng-social:hover{border-color:var(--accent);background:color-mix(in srgb,var(--accent) 22%,transparent);box-shadow:0 0 20px -4px var(--accent);transform:translateY(-2px)}
.ng-form{padding:1.5rem;border-radius:1.5rem;border:1px solid var(--line);background:color-mix(in srgb,var(--paper) 55%,transparent);-webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px);box-shadow:inset 0 1px 0 color-mix(in srgb,var(--ink) 8%,transparent)}
@media(min-width:640px){.ng-form{padding:2rem}}
.ng-field{display:block;margin-bottom:1.25rem}
.ng-label{display:block;margin-bottom:.5rem;font-size:.72rem;font-weight:500;text-transform:uppercase;letter-spacing:.14em}
.ng-input{width:100%;border-radius:.85rem;border:1px solid var(--line);background:color-mix(in srgb,var(--ink) 4%,transparent);padding:.8rem 1rem;font:inherit;font-weight:500;color:var(--ink);transition:border-color .25s ease,box-shadow .25s ease,background-color .25s ease}
.ng-input:hover{border-color:color-mix(in srgb,var(--accent) 50%,var(--line))}
.ng-input:focus{outline:none;border-color:var(--accent);background:color-mix(in srgb,var(--accent) 8%,transparent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 22%,transparent),0 0 24px -6px var(--accent)}
textarea.ng-input{resize:vertical}
.ng-sent{display:flex;align-items:center;gap:.5rem;margin-top:1.25rem;font-size:.875rem;font-weight:600;animation:ng-pop .5s cubic-bezier(.2,.8,.2,1) both}
.ng-hidden-frame{display:none}
.ng-foot{display:flex;max-width:1240px;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;margin-inline:auto;padding:2rem .5rem;font-size:.875rem}
.ng-foot-copy{font-size:.78rem}

/* ---------- Scroll reveal (class is added by script.js, so no-JS output stays visible) ---------- */
.ng-js [data-reveal]{opacity:0;transform:translateY(22px);transition:opacity .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms),transform .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms)}
.ng-js [data-reveal].is-in{opacity:1;transform:none}
.ng-js .ng-tl-item[data-reveal]{transform:translateX(28px)}
.ng-js .ng-tl-item[data-reveal].is-in{transform:none}
.ng-js [data-reveal]:not(.is-in) .ng-h2{letter-spacing:.01em}
.ng-js [data-reveal].is-in .ng-frame{animation:ng-frame-in 1.1s cubic-bezier(.2,.8,.2,1) backwards}
.ng-js [data-reveal]:not(.is-in) .ng-hl{opacity:0}
.ng-js [data-reveal].is-in .ng-hl{animation:ng-slide-x .8s cubic-bezier(.2,.7,.2,1) calc(var(--k,0)*90ms + 150ms) backwards}
.ng-js [data-reveal].is-in .ng-hl .ng-tick{animation:ng-tick .6s cubic-bezier(.3,1.6,.4,1) calc(var(--k,0)*90ms + 450ms) backwards}
.ng-js [data-reveal].is-in .ng-tile{animation:ng-tile .7s cubic-bezier(.3,1.5,.4,1) calc(var(--d,0ms) + .15s) backwards}
.ng-js [data-reveal].is-in .ng-media{animation:ng-wipe 1.2s cubic-bezier(.7,0,.2,1) .1s backwards}
.ng-js [data-reveal].is-in .ng-media img{animation:ng-settle 1.6s cubic-bezier(.2,.7,.2,1) .1s backwards}
.ng-js.ng-root{animation:ng-enter .7s ease both}
@keyframes ng-spin{to{transform:rotate(360deg)}}
@keyframes ng-float{50%{transform:translateY(-12px)}}
@keyframes ng-breathe{50%{transform:scale(1.08);opacity:.8}}
@keyframes ng-drift{50%{transform:translate(-2.5rem,2rem) scale(1.08)}}
@keyframes ng-rise{to{transform:translateY(0)}}
@keyframes ng-enter{from{opacity:0}}
@keyframes ng-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes ng-pop{from{opacity:0;transform:translateY(8px) scale(.96)}}
@keyframes ng-knob{from{transform:translate(50%,-50%) scale(0)}}
@keyframes ng-count{to{--n:var(--p)}}
@keyframes ng-frame-in{from{clip-path:inset(0 0 100% 0 round 1.5rem);transform:rotate(4deg) scale(.95)}}
@keyframes ng-slide-x{from{opacity:0;transform:translateX(-20px)}}
@keyframes ng-tick{from{transform:scale(0) rotate(-90deg)}}
@keyframes ng-tile{from{transform:scale(.4) rotate(-20deg);opacity:0}}
@keyframes ng-wipe{from{clip-path:inset(0 0 100% 0 round 1.1rem)}}
@keyframes ng-settle{from{transform:scale(1.25)}}

/* ---------- Reduced motion ---------- */
@media(prefers-reduced-motion:reduce){
html{scroll-behavior:auto}
.ng-root *,.ng-root *::before,.ng-root *::after{animation:none!important;transition:none!important}
.ng-js .ng-word,.ng-js [data-reveal],.ng-js .ng-tl-item[data-reveal],.ng-js [data-reveal]:not(.is-in) .ng-hl{opacity:1;transform:none}
.ng-js [data-reveal] .ng-count{--n:var(--p,0)}
.ng-js [data-reveal] .ng-bar{width:var(--w,0%)}
.ng-js [data-reveal]:not(.is-in) .ng-kicker::before{transform:none}
.ng-js [data-reveal]:not(.is-in) .ng-h2{letter-spacing:-.04em}
.ng-tl-item::after{transform:scaleY(1)}
}
CSS;
    }
}
