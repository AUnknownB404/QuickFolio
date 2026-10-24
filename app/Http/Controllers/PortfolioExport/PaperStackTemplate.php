<?php

namespace App\Http\Controllers\PortfolioExport;

trait PaperStackTemplate
{
    private function renderPaperStackPage(string $title, array $content, array $assets, ?array $googleForm): string
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
        $icon = fn (string $name, int $size = 16, string $class = '', string $stroke = '2'): string => $this->paperStackIcon($name, $size, $class, $stroke);
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
        $heroImage = $mediaUrl($header, 'hero_image');
        $heroBackground = $mediaUrl($header, 'hero_background_image');
        $heroSocials = $socials($header, 'hero_social_links');
        $greeting = $text($header, 'hero_greeting');
        $role = $text($header, 'hero_role');
        $heroDescription = $text($header, 'hero_description');
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
        $emailOk = $email !== null && $emailHref !== '';
        $phoneOk = $phone !== null && $phoneHref !== '';
        $contactLabel = $text($contact, 'contact_subtitle') ?? $text($contact, 'contact_title') ?? $email ?? '';
        $formReady = $flag($contact, 'contact_form_enabled', true) && $googleForm !== null;

        // Orbital badge pills around the hero (role, location, first two socials; max four).
        $orbitBadges = [];
        if ($role !== null) {
            $orbitBadges[] = ['label' => $role, 'icon' => 'sparkles', 'href' => ''];
        }
        if ($location !== null) {
            $orbitBadges[] = ['label' => $location, 'icon' => 'map-pin', 'href' => ''];
        }
        foreach (array_slice($heroSocials, 0, 2) as $social) {
            $orbitBadges[] = ['label' => $social['label'], 'icon' => $socialIcon($social['platform']), 'href' => $social['url']];
        }
        $orbitBadges = array_slice($orbitBadges, 0, 4);

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
        $html = '<div class="ps-root">
    <div class="ps-ambient" aria-hidden="true"><span class="ps-spot ps-spot-a"></span><span class="ps-spot ps-spot-b"></span><span class="ps-spot ps-spot-c"></span></div>';

        // Navbar
        if ($logoImage !== '' || $logoText !== null || $navLinks !== [] || $hasContact) {
            $html .= '<header class="ps-header '.($stickyNav ? 'is-fixed' : 'is-absolute').'"><div class="ps-progress" data-progress aria-hidden="true"></div>';
            $html .= '<nav class="ps-nav" data-nav aria-label="Primary">';
            if ($logoImage !== '' || $logoText !== null) {
                $html .= '<a href="#header" class="ps-logo" data-scroll-link>';
                if ($logoImage !== '') {
                    $html .= '<img src="'.$escape($logoImage).'" alt="'.$escape($logoText ?? '').'" class="ps-logo-img">';
                }
                if ($logoText !== null) {
                    $html .= '<span class="ps-display ps-logo-text">'.$escape($logoText).'</span>';
                }
                $html .= '</a>';
            } else {
                $html .= '<span></span>';
            }
            if ($navLinks !== []) {
                $html .= '<ul class="ps-navlinks">';
                foreach ($navLinks as $link) {
                    $html .= '<li><a href="#'.$escape($link['id']).'" class="ps-navlink" data-active="false" data-section-link="'.$escape($link['id']).'" data-scroll-link>'.$escape($link['label']).'</a></li>';
                }
                $html .= '</ul>';
            }
            if ($hasContact) {
                $html .= '<a href="#contact" class="ps-btn ps-btn-primary ps-btn-sm" data-scroll-link>'.$escape($contactLabel).$icon('arrow-up-right', 16, 'ps-arrow').'</a>';
            } else {
                $html .= '<span></span>';
            }
            $html .= '</nav></header>';
        }

        // Hero
        if ($hasHeader) {
            $html .= '<section id="header" class="ps-hero" data-page-section><span id="home" class="ps-home-anchor" aria-hidden="true"></span>';
            if ($heroBackground !== '') {
                $html .= '<img src="'.$escape($heroBackground).'" alt="" class="ps-hero-bg">';
            }
            $html .= '<div class="ps-hero-inner">';
            if ($greeting !== null) {
                $html .= '<p data-reveal class="ps-chip" style="--d: 0ms"><span class="ps-pulse" aria-hidden="true"></span>'.$escape($greeting).'</p>';
            }
            $html .= '<div class="ps-orbit-stage"><div class="ps-avatar-wrap" data-reveal style="--d: 150ms"><span class="ps-ring" aria-hidden="true"></span><span class="ps-ring ps-ring-2" aria-hidden="true"></span><div class="ps-avatar">';
            if ($heroImage !== '') {
                $html .= '<img src="'.$escape($heroImage).'" alt="'.$escape($heroName ?? '').'">';
            } elseif ($heroInitials !== '') {
                $html .= '<span class="ps-display ps-initials">'.$escape($heroInitials).'</span>';
            }
            $html .= '</div></div>';
            foreach ($orbitBadges as $index => $badge) {
                if ($badge['href'] !== '') {
                    $html .= '<a href="'.$escape($badge['href']).'" target="_blank" rel="noopener noreferrer" class="ps-badge ps-badge-'.$index.'">'.$icon($badge['icon'], 14, 'ps-accent').$escape($badge['label']).'</a>';
                } else {
                    $html .= '<span class="ps-badge ps-badge-'.$index.'">'.$icon($badge['icon'], 14, 'ps-accent').$escape($badge['label']).'</span>';
                }
            }
            $html .= '</div>';
            if ($heroName !== null) {
                $html .= '<h1 class="ps-display ps-hero-name"><span class="sr-only">'.$escape($heroName).'</span>';
                foreach ($heroWords as $index => $word) {
                    $html .= '<span class="ps-mask" aria-hidden="true"><span class="ps-word" style="--i: '.$index.'">'.$escape($word).'</span></span>';
                }
                $html .= '</h1>';
            }
            if ($heroDescription !== null) {
                $html .= '<p data-reveal class="ps-muted ps-hero-desc" style="--d: 460ms">'.$escape($heroDescription).'</p>';
            }
            if ($heroPrimary !== null || $heroSecondary !== null) {
                $html .= '<div data-reveal class="ps-actions ps-actions-center" style="--d: 540ms">';
                if ($heroPrimary !== null) {
                    $html .= '<a href="'.$url($heroPrimary['url']).'" class="ps-btn ps-btn-primary" data-scroll-link>'.$escape($heroPrimary['text']).$icon('arrow-up-right', 16, 'ps-arrow').'</a>';
                }
                if ($heroSecondary !== null) {
                    $html .= '<a href="'.$url($heroSecondary['url']).'" class="ps-btn ps-btn-ghost" data-scroll-link>'.$escape($heroSecondary['text']).'</a>';
                }
                $html .= '</div>';
            }
            if ($heroSocials !== []) {
                $html .= '<ul data-reveal class="ps-icons" style="--d: 620ms">';
                foreach ($heroSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($social['label']).'" title="'.$escape($social['label']).'" class="ps-icon-btn">'.$icon($socialIcon($social['platform']), 18).'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div></section>';
        }

        // Stack of overlapping sheets
        $html .= '<main class="ps-stack">';

        // About
        if ($hasAbout) {
            $html .= '<section id="about" class="ps-sheet" style="--z: 1" data-page-section><div class="ps-sheet-in"><div class="ps-split ps-split-center">';
            if ($aboutImage !== '') {
                $html .= '<div data-reveal class="ps-c5"><div class="ps-frame"><img src="'.$escape($aboutImage).'" alt="'.$escape($text($about, 'about_title') ?? '').'" loading="lazy"></div></div>';
            }
            $html .= '<div class="'.($aboutImage !== '' ? 'ps-c7' : 'ps-c12').'"><div data-reveal>';
            if ($text($about, 'about_subtitle') !== null) {
                $html .= '<p class="ps-kicker">'.$escape($text($about, 'about_subtitle')).'</p>';
            }
            if ($text($about, 'about_title') !== null) {
                $html .= '<h2 class="ps-display ps-h2 ps-mt4">'.$escape($text($about, 'about_title')).'</h2>';
            }
            if ($text($about, 'about_description') !== null) {
                $html .= '<p class="ps-muted ps-prose">'.$escape($text($about, 'about_description')).'</p>';
            }
            $html .= '</div>';
            if ($highlights !== []) {
                $html .= '<ul data-reveal class="ps-hl-list">';
                foreach ($highlights as $index => $highlight) {
                    $html .= '<li class="ps-hl" style="--k: '.$index.'"><span class="ps-tick">'.$icon('check', 14, '', '3').'</span><span><span class="ps-hl-title">'.$escape($highlight['title']).'</span>';
                    if ($highlight['description'] !== null) {
                        $html .= '<span class="ps-muted ps-hl-desc">'.$escape($highlight['description']).'</span>';
                    }
                    $html .= '</span></li>';
                }
                $html .= '</ul>';
            }
            if ($cvFile !== '' || $aboutCta !== null) {
                $html .= '<div data-reveal class="ps-actions ps-actions-lg">';
                if ($aboutCta !== null) {
                    $html .= '<a href="'.$url($aboutCta['url']).'" class="ps-btn '.($cvFile !== '' ? 'ps-btn-ghost' : 'ps-btn-primary').'" data-scroll-link>'.$escape($aboutCta['text']).$icon('arrow-up-right', 16, 'ps-arrow').'</a>';
                    }
                    else if ($cvFile !== '') {
                        $html .= '<a href="'.$escape($cvFile).'" download target="_blank" rel="noopener" class="ps-btn ps-btn-primary">'.$icon('download', 16).'Download CV</a>';
                        }
                        $html .= '</div>';
            }
            $html .= '</div></div></div></section>';
        }

        // Journey
        if ($hasJourney) {
            $html .= '<section id="journey" class="ps-sheet" style="--z: 2" data-page-section><div class="ps-sheet-in"><div class="ps-split"><div class="ps-c4"><div data-reveal class="ps-sticky">';
            if ($text($journeyData, 'journey_subtitle') !== null) {
                $html .= '<p class="ps-kicker">'.$escape($text($journeyData, 'journey_subtitle')).'</p>';
            }
            if ($text($journeyData, 'journey_title') !== null) {
                $html .= '<h2 class="ps-display ps-h2 ps-mt4">'.$escape($text($journeyData, 'journey_title')).'</h2>';
            }
            $html .= '</div></div><ol class="ps-timeline ps-c8">';
            foreach ($journey as $index => $item) {
                $html .= '<li data-reveal style="--d: '.(($index % 4) * 70).'ms" class="ps-tl-item"><div class="ps-tl-card">';
                if ($item['period'] !== null) {
                    $html .= '<p class="ps-mono ps-period">'.$escape($item['period']).'</p>';
                }
                if ($item['title'] !== null) {
                    $html .= '<h3 class="ps-display ps-item-title">'.$escape($item['title']).'</h3>';
                }
                if ($item['company'] !== null) {
                    $html .= '<p class="ps-company">'.$escape($item['company']).'</p>';
                }
                if ($item['description'] !== null) {
                    $html .= '<p class="ps-muted ps-item-desc">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></li>';
            }
            $html .= '</ol></div></div></section>';
        }

        // Skills
        if ($hasSkills) {
            $html .= '<section id="skills" class="ps-sheet" style="--z: 3" data-page-section><div class="ps-sheet-in"><div class="ps-split"><div class="ps-c4"><div data-reveal class="ps-sticky">';
            if ($text($skillsData, 'skills_subtitle') !== null) {
                $html .= '<p class="ps-kicker">'.$escape($text($skillsData, 'skills_subtitle')).'</p>';
            }
            if ($text($skillsData, 'skills_title') !== null) {
                $html .= '<h2 class="ps-display ps-h2 ps-mt4">'.$escape($text($skillsData, 'skills_title')).'</h2>';
            }
            if ($text($skillsData, 'skills_description') !== null) {
                $html .= '<p class="ps-muted ps-skills-desc">'.$escape($text($skillsData, 'skills_description')).'</p>';
            }
            $html .= '</div></div><ul class="ps-skill-list ps-c8">';
            foreach ($skills as $index => $skill) {
                $html .= '<li data-reveal style="--d: '.(($index % 6) * 50).'ms" class="ps-skill"><div class="ps-skill-row">';
                if ($showIcon) {
                    $skillIcon = $iconFor($skill['icon']);
                    $html .= '<span class="ps-tile">';
                    if ($skillIcon !== null) {
                        $html .= $icon($skillIcon, 18);
                    } elseif ($isGlyph($skill['icon'])) {
                        $html .= '<span class="ps-tile-glyph" aria-hidden="true">'.$escape($skill['icon']).'</span>';
                    } else {
                        $html .= '<span class="ps-display ps-tile-letter" aria-hidden="true">'.$escape(mb_strtoupper(mb_substr($skill['name'], 0, 1))).'</span>';
                    }
                    $html .= '</span>';
                }
                $html .= '<h3 class="ps-skill-name">'.$escape($skill['name']).'</h3>';
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<span class="ps-count ps-mono ps-muted ps-skill-pct" style="--p: '.(int) round($skill['percent']).'" aria-hidden="true"></span>';
                } elseif ($showLevel && $skill['label'] !== null) {
                    $html .= '<span class="ps-pill ps-pill-end">'.$escape($skill['label']).'</span>';
                }
                $html .= '</div>';
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<div class="ps-track" role="progressbar" aria-valuenow="'.(int) round($skill['percent']).'" aria-valuemin="0" aria-valuemax="100" aria-label="'.$escape($skill['name']).'"><div class="ps-bar" style="--w: '.$num($skill['percent']).'%"><span class="ps-knob"></span></div></div>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></div></div></section>';
        }

        // Projects
        if ($hasProjects) {
            $html .= '<section id="projects" class="ps-sheet" style="--z: 4" data-page-section><div class="ps-sheet-in"><div data-reveal class="ps-proj-head"><div class="ps-c7">';
            if ($text($projectData, 'projects_subtitle') !== null) {
                $html .= '<p class="ps-kicker">'.$escape($text($projectData, 'projects_subtitle')).'</p>';
            }
            if ($text($projectData, 'projects_title') !== null) {
                $html .= '<h2 class="ps-display ps-h2 ps-mt4">'.$escape($text($projectData, 'projects_title')).'</h2>';
            }
            $html .= '</div>';
            if ($text($projectData, 'projects_description') !== null) {
                $html .= '<p class="ps-muted ps-proj-desc ps-c5">'.$escape($text($projectData, 'projects_description')).'</p>';
            }
            $html .= '</div><ul class="ps-project-grid">';
            foreach ($projects as $index => $project) {
                $featured = $featureFirst && $index === 0;
                $html .= '<li data-reveal'.($featured ? ' class="ps-feature"' : '').' style="--d: '.(($index % 3) * 80).'ms"><article class="ps-project ps-elevate">';
                $mediaClass = 'ps-media '.($featured ? 'ps-media-wide' : 'ps-media-std');
                if ($project['live'] !== '') {
                    $html .= '<a href="'.$escape($project['live']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($project['title']).'" class="'.$mediaClass.'">';
                } else {
                    $html .= '<div class="'.$mediaClass.'">';
                }
                if ($project['image'] !== '') {
                    $html .= '<img src="'.$escape($project['image']).'" alt="'.$escape($project['title']).'" loading="lazy">';
                } else {
                    $html .= '<div class="ps-display ps-tone ps-tone-'.($index % 3).'">'.$escape(mb_strtoupper(mb_substr($project['title'], 0, 1))).'</div>';
                }
                if ($featured) {
                    $html .= '<span class="ps-flag ps-mono">'.$icon('sparkles', 14).str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'</span>';
                }
                if ($project['live'] !== '') {
                    $html .= '<span class="ps-go" aria-hidden="true">'.$icon('arrow-up-right', 20).'</span>';
                }
                $html .= $project['live'] !== '' ? '</a>' : '</div>';

                $html .= '<div class="ps-proj-body"><h3 class="ps-display ps-proj-name"><span class="ps-underline">'.$escape($project['title']).'</span></h3>';
                if ($project['description'] !== null) {
                    $html .= '<p class="ps-muted ps-proj-text">'.$escape($project['description']).'</p>';
                }
                if ($project['tags'] !== []) {
                    $html .= '<ul class="ps-tags">';
                    foreach ($project['tags'] as $tag) {
                        $html .= '<li class="ps-pill">'.$escape($tag).'</li>';
                    }
                    $html .= '</ul>';
                }
                if ($project['repo'] !== '') {
                    $html .= '<div class="ps-repo"><a href="'.$escape($project['repo']).'" target="_blank" rel="noopener noreferrer" class="ps-textlink ps-mono">'.$icon('code', 16).$escape($project['repo_label']).'</a></div>';
                }
                $html .= '</div></article></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Services
        if ($hasServices) {
            $html .= '<section id="services" class="ps-sheet" style="--z: 5" data-page-section><div class="ps-sheet-in"><div data-reveal class="ps-services-head">';
            if ($text($servicesData, 'services_subtitle') !== null) {
                $html .= '<p class="ps-kicker">'.$escape($text($servicesData, 'services_subtitle')).'</p>';
            }
            if ($text($servicesData, 'services_title') !== null) {
                $html .= '<h2 class="ps-display ps-h2 ps-mt4">'.$escape($text($servicesData, 'services_title')).'</h2>';
            }
            $html .= '</div><ul class="ps-service-grid">';
            foreach ($services as $index => $service) {
                $html .= '<li data-reveal style="--d: '.(($index % 6) * 70).'ms"><div class="ps-service ps-elevate"><span class="ps-tile ps-tile-lg">';
                if (! $isGlyph($service['icon'])) {
                    $html .= $icon($iconFor($service['icon']) ?? 'sparkles', 24);
                } else {
                    $html .= '<span class="ps-tile-glyph-lg" aria-hidden="true">'.$escape($service['icon']).'</span>';
                }
                $html .= '</span><h3 class="ps-display ps-service-title">'.$escape($service['title']).'</h3>';
                if ($service['description'] !== null) {
                    $html .= '<p class="ps-muted ps-service-text">'.$escape($service['description']).'</p>';
                }
                $html .= $icon('arrow-up-right', 24, 'ps-service-arrow').'</div></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Contact
        if ($hasContact) {
            $html .= '<section id="contact" class="ps-sheet ps-sheet-contact" style="--z: 6" data-page-section><div class="ps-sheet-in ps-sheet-in-top"><div class="ps-split ps-split-contact"><div class="ps-c7"><div data-reveal>';
            if ($text($contact, 'contact_subtitle') !== null) {
                $html .= '<p class="ps-kicker">'.$escape($text($contact, 'contact_subtitle')).'</p>';
            }
            if ($text($contact, 'contact_title') !== null) {
                $html .= '<h2 class="ps-display ps-contact-title">'.$escape($text($contact, 'contact_title')).'</h2>';
            }
            if ($text($contact, 'contact_description') !== null) {
                $html .= '<p class="ps-contact-text">'.$escape($text($contact, 'contact_description')).'</p>';
            }
            $html .= '</div>';
            if ($emailOk || $phoneOk || $location !== null) {
                $html .= '<div data-reveal class="ps-contact-info">';
                if ($emailOk) {
                    $html .= '<a href="'.$escape($emailHref).'" class="ps-bigmail ps-display">'.$icon('mail', 28).'<span class="ps-underline-contact">'.$escape($email).'</span>'.$icon('arrow-up-right', 28, 'ps-arrow').'</a>';
                }
                if ($phoneOk || $location !== null) {
                    $html .= '<div class="ps-contact-meta">';
                    if ($phoneOk) {
                        $html .= '<a href="'.$escape($phoneHref).'" class="ps-contact-link">'.$icon('phone', 16).$escape($phone).'</a>';
                    }
                    if ($location !== null) {
                        $html .= '<span>'.$icon('map-pin', 16).$escape($location).'</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            if ($contactSocials !== []) {
                $html .= '<ul data-reveal class="ps-contact-socials">';
                foreach ($contactSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" class="ps-social">'.$icon($socialIcon($social['platform']), 14).$escape($social['label']).$icon('arrow-up-right', 14, 'ps-arrow').'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><div class="ps-c5"><div data-reveal style="--d: 120ms">';

            if ($formReady) {
                $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="ps-gform-frame" class="ps-form" data-google-form>
                <label class="ps-field"><span class="ps-mono ps-label">Name</span><input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="ps-input"></label>
                <label class="ps-field"><span class="ps-mono ps-label">Email</span><input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="ps-input"></label>
                <label class="ps-field"><span class="ps-mono ps-label">Message</span><textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required class="ps-input"></textarea></label>
                <button type="submit" class="ps-btn ps-btn-primary ps-btn-block"><span data-submit-label>Send</span>'.$icon('send', 16, 'ps-arrow').'</button>
                <p class="ps-sent" role="status" data-form-status hidden><span class="ps-tick">'.$icon('check', 14, '', '3').'</span>OK</p>
            </form>';
            } elseif ($emailOk) {
                $html .= '<a href="'.$escape($emailHref).'" class="ps-btn ps-btn-ghost ps-btn-block ps-btn-xl">'.$icon('mail', 20).$escape($email).'</a>';
            }
            $html .= '</div>';
            if ($formReady) {
                $html .= '<iframe name="ps-gform-frame" title="Form submission target" class="ps-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
            }
            $html .= '</div></div></div></section>';
        }

        if ($heroName !== null || $logoText !== null) {
            $html .= '<footer class="ps-foot"><span class="ps-muted ps-mono ps-foot-copy">&copy; '.date('Y').' '.$escape($heroName ?? $logoText).'</span><a href="#header" class="ps-textlink" aria-label="Top" data-scroll-link>'.$icon('arrow-up-right', 16, 'ps-arrow ps-rot').'</a></footer>';
        }
        $html .= '</main></div>';

        return '<!doctype html>
<html lang="en" data-template="paper-stack">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#e7e4dd" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0b0a10" media="(prefers-color-scheme: dark)">
    <title>'.$escape($title).'</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    /** Inline Lucide-style icons (static, trusted markup only). */
    private function paperStackIcon(string $name, int $size = 16, string $class = '', string $stroke = '2'): string
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

        return '<svg class="ps-ico'.($class !== '' ? ' '.$class : '').'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$stroke.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$body.'</svg>';
    }

    private function paperStackStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500..700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');
/* ---------- Reset (Tailwind-preflight equivalent, low specificity) ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;color-scheme:light dark;-webkit-text-size-adjust:100%}
body{margin:0;background:#e7e4dd}
@media(prefers-color-scheme:dark){body{background:#0b0a10}}
:where(.ps-root) :is(h1,h2,h3,p,ul,ol,figure){margin:0}
:where(.ps-root) :is(ul,ol){padding:0;list-style:none}
:where(.ps-root) :is(h1,h2,h3){font-size:inherit;font-weight:inherit}
:where(.ps-root) a{color:inherit;text-decoration:none}
:where(.ps-root) :is(button,input,textarea){font:inherit;color:inherit;letter-spacing:inherit}
:where(.ps-root) button{background:none;border:0;padding:0;cursor:pointer}
:where(.ps-root) img,:where(.ps-root) svg{display:block;max-width:100%}
.ps-root [hidden]{display:none!important}
.ps-ico{flex-shrink:0}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

/* ---------- Foundations + palette (light, dark via prefers-color-scheme) ---------- */
.ps-root{--paper:#E7E4DD;--soft:#EFECE5;--surface:#FFFFFF;--ink:#16151A;--muted:#16151AB0;--line:#16151A1F;--accent:#7C3AED;--accent-2:#F59E0B;--accent-ink:#FFFFFF;position:relative;background:var(--paper);color:var(--ink);font-family:'Plus Jakarta Sans',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;overflow-x:clip;transition:background-color .4s ease,color .4s ease}
@media(prefers-color-scheme:dark){.ps-root{--paper:#0B0A10;--soft:#14121C;--surface:#1A1824;--ink:#F2F0F8;--muted:#F2F0F8B0;--line:#F2F0F82B;--accent:#A78BFA;--accent-2:#FBBF24;--accent-ink:#0B0A10}}
.ps-root ::selection{background:var(--accent);color:#fff}
.ps-display{font-family:'Fraunces','Plus Jakarta Sans',ui-serif,Georgia,serif;font-weight:600;letter-spacing:-.03em}
.ps-mono{font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,monospace}
.ps-muted{color:var(--muted)}
.ps-accent{color:var(--accent)}
.ps-mt4{margin-top:1rem}
.ps-h2{font-size:clamp(2.2rem,4.8vw,3.9rem);line-height:1.04;letter-spacing:-.035em;text-wrap:balance;transition:letter-spacing 1.2s cubic-bezier(.2,.7,.2,1)}
.ps-root :where(a,button,input,textarea):focus-visible{outline:2px solid var(--accent);outline-offset:3px;border-radius:.5rem}
.ps-kicker{display:inline-flex;align-items:center;gap:.7rem;font-size:.92rem;font-weight:600;color:var(--accent)}
.ps-kicker::before{content:'';width:1.75rem;height:2px;background:currentColor;transform-origin:left;transition:transform .7s cubic-bezier(.2,.7,.2,1) .2s}
.ps-js [data-reveal]:not(.is-in) .ps-kicker::before{transform:scaleX(0)}

/* ---------- Ambient light spots ---------- */
.ps-ambient{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.ps-spot{position:absolute;border-radius:999px;filter:blur(90px)}
.ps-spot-a{height:30rem;width:30rem;left:-8rem;top:-6rem;background:var(--accent);opacity:.28;animation:ps-drift 22s ease-in-out infinite}
.ps-spot-b{height:24rem;width:24rem;right:-6rem;top:20%;background:var(--accent-2);opacity:.2;animation:ps-drift 28s ease-in-out infinite reverse}
.ps-spot-c{height:26rem;width:26rem;left:30%;bottom:-12rem;background:var(--accent);opacity:.18;animation:ps-drift 34s ease-in-out infinite}
.ps-root>.ps-hero,.ps-root>.ps-stack{position:relative}

/* ---------- Layout helpers ---------- */
.ps-split{display:grid;gap:3rem}
.ps-split-center{align-items:center}
.ps-split-contact{gap:3.5rem}
.ps-c4,.ps-c5,.ps-c7,.ps-c8,.ps-c12{min-width:0}
@media(min-width:1024px){
.ps-split{grid-template-columns:repeat(12,minmax(0,1fr));gap:3.5rem}
.ps-split-contact{gap:3rem}
.ps-c4{grid-column:span 4/span 4}.ps-c5{grid-column:span 5/span 5}.ps-c7{grid-column:span 7/span 7}.ps-c8{grid-column:span 8/span 8}.ps-c12{grid-column:span 12/span 12}
.ps-sticky{position:sticky;top:7rem}
}

/* ---------- Navbar ---------- */
.ps-header{position:fixed;inset:0 0 auto 0;z-index:50;padding:.75rem .75rem 0}
.ps-header.is-absolute{position:absolute}
@media(min-width:640px){.ps-header{padding:1rem 1.5rem 0}}
.ps-progress{position:fixed;inset:0 0 auto 0;height:3px;background:linear-gradient(90deg,var(--accent),var(--accent-2));transform:scaleX(0);transform-origin:left;pointer-events:none;z-index:60}
.ps-nav{display:flex;height:3.5rem;max-width:1180px;margin-inline:auto;align-items:center;justify-content:space-between;gap:.75rem;padding:0 .5rem 0 1.25rem;border:1px solid transparent;border-radius:999px;background:color-mix(in srgb,var(--surface) 55%,transparent);-webkit-backdrop-filter:blur(14px) saturate(1.4);backdrop-filter:blur(14px) saturate(1.4);transition:border-color .35s ease,background-color .35s ease,box-shadow .35s ease}
.ps-nav.is-scrolled{border-color:var(--line);background:color-mix(in srgb,var(--surface) 85%,transparent);box-shadow:0 16px 36px -16px color-mix(in srgb,var(--ink) 40%,transparent)}
.ps-logo{display:flex;min-width:0;align-items:center;gap:.625rem}
.ps-logo-img{height:1.75rem;width:auto;max-width:7rem;object-fit:contain}
.ps-logo-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:1rem}
.ps-navlinks{display:none;align-items:center;gap:.25rem}
@media(min-width:768px){.ps-navlinks{display:flex}}
.ps-navlink{display:inline-block;border-radius:999px;padding:.5rem 1rem;font-size:.92rem;font-weight:500;color:var(--muted);transition:color .25s ease,background-color .25s ease}
.ps-navlink:hover{color:var(--ink)}
.ps-navlink[data-active='true']{color:var(--ink);background:var(--soft)}

/* ---------- Buttons & small controls ---------- */
.ps-btn{position:relative;isolation:isolate;display:inline-flex;align-items:center;gap:.5rem;border-radius:999px;padding:.85rem 1.5rem;font-size:.95rem;font-weight:600;line-height:1.2;cursor:pointer;border:1px solid transparent;background-repeat:no-repeat;background-position:left center;background-size:0% 100%;transition:background-size .5s cubic-bezier(.2,.7,.2,1),color .3s ease,transform .25s ease,box-shadow .3s ease,border-color .3s ease}
.ps-btn:hover{background-size:100% 100%;transform:translateY(-2px)}
.ps-btn:active{transform:translateY(0) scale(.98)}
.ps-btn[disabled]{opacity:.7;pointer-events:none}
.ps-btn-primary{background-color:var(--accent);background-image:linear-gradient(var(--ink),var(--ink));color:var(--accent-ink);box-shadow:0 10px 24px -12px var(--accent)}
.ps-btn-primary:hover{color:var(--surface)}
.ps-btn-ghost{background-color:transparent;background-image:linear-gradient(var(--ink),var(--ink));border-color:var(--line);color:var(--ink)}
.ps-btn-ghost:hover{color:var(--surface);border-color:var(--ink)}
.ps-btn-sm{padding:.625rem 1.25rem;font-size:.875rem}
.ps-btn-block{width:100%;justify-content:center}
.ps-btn-xl{padding-block:1.25rem;font-size:1.125rem}
.ps-arrow{transition:transform .3s cubic-bezier(.2,.7,.2,1)}
.ps-rot{transform:rotate(-45deg)}
.ps-btn:hover .ps-arrow,.ps-bigmail:hover .ps-arrow,.ps-social:hover .ps-arrow,.ps-textlink:hover .ps-arrow{transform:translate(3px,-3px)}
.ps-icon-btn{display:inline-flex;height:2.75rem;width:2.75rem;align-items:center;justify-content:center;border-radius:999px;border:1px solid var(--line);background:var(--surface);transition:transform .3s cubic-bezier(.2,.7,.2,1),background-color .25s ease,color .25s ease,border-color .25s ease}
.ps-icon-btn:hover{transform:translateY(-3px);background:var(--accent);color:var(--accent-ink);border-color:var(--accent)}
.ps-chip{display:inline-flex;align-items:center;gap:.6rem;border-radius:999px;border:1px solid var(--line);background:var(--surface);padding:.45rem 1rem .45rem .85rem;font-size:.9rem;font-weight:600;box-shadow:0 10px 24px -16px color-mix(in srgb,var(--ink) 50%,transparent)}
.ps-pulse{position:relative;height:.5rem;width:.5rem;border-radius:999px;background:#22c55e}
.ps-pulse::after{content:'';position:absolute;inset:0;border-radius:inherit;background:inherit;animation:ps-ping 2.2s cubic-bezier(0,0,.2,1) infinite}
.ps-pill{display:inline-block;border-radius:999px;border:1px solid var(--line);padding:.2rem .7rem;font-size:.76rem;font-weight:600;color:var(--muted)}
.ps-pill-end{margin-left:auto}
.ps-tick{display:inline-flex;height:1.5rem;width:1.5rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:999px;background:var(--accent);color:var(--accent-ink)}
.ps-textlink{display:inline-flex;align-items:center;gap:.4rem;font-weight:600;background:linear-gradient(currentColor,currentColor) left bottom/0% 1.5px no-repeat;transition:background-size .4s cubic-bezier(.2,.7,.2,1);padding-bottom:2px}
.ps-textlink:hover{background-size:100% 1.5px}

/* ---------- Hero ---------- */
.ps-hero{position:relative;isolation:isolate;display:flex;min-height:100vh;min-height:100svh;align-items:center;justify-content:center;overflow:hidden}
.ps-home-anchor{position:absolute;top:0}
.ps-hero-bg{position:absolute;inset:0;z-index:-10;height:100%;width:100%;object-fit:cover;opacity:.14}
.ps-hero-inner{position:relative;display:flex;width:100%;max-width:980px;flex-direction:column;align-items:center;margin-inline:auto;padding:8rem 1.25rem 10rem;text-align:center}
@media(min-width:640px){.ps-hero-inner{padding-inline:2rem}}
.ps-hero-name{margin-top:2.5rem;font-size:clamp(3rem,9vw,7.5rem);line-height:.96;letter-spacing:-.045em;overflow-wrap:anywhere}
.ps-mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.14em;margin-bottom:-.14em;margin-right:.22em}
.ps-word{display:inline-block}
.ps-js .ps-word{transform:translateY(110%);animation:ps-rise 1s cubic-bezier(.2,.8,.2,1) forwards;animation-delay:calc(.15s + var(--i,0)*.1s)}
.ps-hero-desc{max-width:38rem;margin-top:1.5rem;white-space:pre-line;font-size:1.125rem;line-height:1.7}
.ps-actions{display:flex;flex-wrap:wrap;gap:.75rem}
.ps-actions-center{justify-content:center;margin-top:2.25rem}
.ps-actions-lg{margin-top:2.5rem}
.ps-icons{display:flex;flex-wrap:wrap;justify-content:center;gap:.5rem;margin-top:2.25rem}
.ps-orbit-stage{position:relative;display:flex;height:11rem;width:min(100%,34rem);align-items:center;justify-content:center;margin-top:2rem}
.ps-avatar-wrap{position:relative;height:9.5rem;width:9.5rem}
.ps-avatar{position:absolute;inset:0;overflow:hidden;border-radius:999px;border:5px solid var(--surface);background:var(--soft);box-shadow:0 26px 50px -22px color-mix(in srgb,var(--ink) 60%,transparent)}
.ps-avatar img{width:100%;height:100%;object-fit:cover;transition:transform .9s ease-out}
.ps-avatar:hover img{transform:scale(1.07)}
.ps-initials{display:flex;width:100%;height:100%;align-items:center;justify-content:center;background:var(--accent);font-size:3rem;line-height:1;color:var(--accent-ink)}
.ps-ring{position:absolute;inset:-.9rem;border-radius:999px;border:1.5px dashed color-mix(in srgb,var(--accent) 65%,transparent);animation:ps-spin 40s linear infinite}
.ps-ring-2{inset:-1.9rem;border:1px solid var(--line);animation-duration:26s;animation-direction:reverse}
.ps-ring-2::after{content:'';position:absolute;top:0;left:50%;height:.7rem;width:.7rem;transform:translate(-50%,-50%);border-radius:999px;background:var(--accent-2);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent-2) 25%,transparent)}
.ps-badge{position:absolute;display:inline-flex;max-width:14rem;align-items:center;gap:.45rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-radius:999px;border:1px solid var(--line);background:color-mix(in srgb,var(--surface) 85%,transparent);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);padding:.45rem .9rem;font-size:.8rem;font-weight:600;box-shadow:0 14px 28px -16px color-mix(in srgb,var(--ink) 50%,transparent);animation:ps-float 6s ease-in-out infinite;transition:border-color .3s ease,transform .3s ease}
a.ps-badge:hover{border-color:var(--accent)}
.ps-badge-0{left:2%;top:4%}
.ps-badge-1{right:2%;top:12%;animation-delay:-1.5s;animation-duration:7s}
.ps-badge-2{left:6%;bottom:2%;animation-delay:-3s}
.ps-badge-3{right:6%;bottom:6%;animation-delay:-4.5s;animation-duration:7.5s}
@media(max-width:639px){.ps-orbit-stage{height:14rem}.ps-badge{font-size:.72rem;max-width:9rem}}

/* ---------- Overlapping paper sheets ---------- */
.ps-stack{position:relative;z-index:2;margin-top:-6rem;padding-inline:.5rem}
@media(min-width:640px){.ps-stack{padding-inline:1.5rem}}
.ps-sheet{position:relative;z-index:var(--z,1);margin:0 auto;max-width:1180px;border:1px solid var(--line);border-radius:2.5rem;background:var(--surface);box-shadow:0 -22px 60px -30px color-mix(in srgb,var(--ink) 45%,transparent),0 40px 90px -40px color-mix(in srgb,var(--ink) 55%,transparent);scroll-margin-top:3rem}
.ps-sheet+.ps-sheet{margin-top:-3.5rem;padding-top:3.5rem}
.ps-sheet:first-child{transform:translateY(calc(var(--sy,0)*-.02px))}
.ps-sheet:nth-child(even){background:var(--soft)}
.ps-sheet-in{padding:4rem 1.4rem 5rem}
@media(min-width:640px){.ps-sheet-in{padding:5rem 2.75rem 6rem}}
@media(min-width:1024px){.ps-sheet-in{padding:6rem 4.25rem 7rem}}
.ps-sheet-contact{position:relative;overflow:hidden;background:var(--ink);color:var(--surface);border-color:transparent;margin-bottom:0}
.ps-sheet-contact.ps-sheet:nth-child(even){background:var(--ink)}
.ps-sheet-in-top{position:relative;z-index:10}
.ps-sheet-contact::before,.ps-sheet-contact::after{content:'';position:absolute;border-radius:999px;pointer-events:none;filter:blur(70px)}
.ps-sheet-contact::before{height:24rem;width:24rem;right:-8rem;top:-9rem;background:var(--accent);opacity:.55;animation:ps-drift 16s ease-in-out infinite}
.ps-sheet-contact::after{height:16rem;width:16rem;left:-5rem;bottom:-6rem;background:var(--accent-2);opacity:.35;animation:ps-drift 20s ease-in-out infinite reverse}
.ps-sheet-contact .ps-kicker{color:currentColor}

/* ---------- About ---------- */
.ps-frame{margin-inline:auto;max-width:28rem;border-radius:2rem;border:1px solid var(--line);background:var(--paper);padding:.55rem;box-shadow:0 28px 50px -30px color-mix(in srgb,var(--ink) 60%,transparent);transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.ps-frame:hover{transform:rotate(-1.4deg) scale(1.02)}
.ps-frame img{display:block;width:100%;aspect-ratio:4/5;border-radius:1.6rem;object-fit:cover}
.ps-prose{max-width:38rem;margin-top:1.75rem;white-space:pre-line;font-size:1.125rem;line-height:1.75}
.ps-hl-list{display:grid;gap:.75rem;margin-top:2.5rem}
@media(min-width:640px){.ps-hl-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
.ps-hl{display:flex;align-items:flex-start;gap:.9rem;border:1px solid var(--line);border-radius:1.25rem;background:var(--paper);padding:1.1rem 1.2rem;transition:transform .4s cubic-bezier(.2,.7,.2,1),box-shadow .4s ease,border-color .3s ease}
.ps-hl:hover{transform:translateY(-4px) scale(1.02);border-color:var(--accent);box-shadow:0 20px 36px -22px var(--accent)}
.ps-hl-title{display:block;font-weight:600;line-height:1.375}
.ps-hl-desc{display:block;margin-top:.25rem;font-size:.92rem;line-height:1.625}

/* ---------- Journey ---------- */
.ps-timeline{position:relative;margin-left:.5rem;border-left:1.5px solid var(--line);padding-left:2.25rem}
.ps-tl-item{position:relative;padding-bottom:1.5rem}
.ps-tl-item:last-child{padding-bottom:0}
.ps-tl-item::before{content:'';position:absolute;left:-2.25rem;top:1.7rem;height:.85rem;width:.85rem;transform:translateX(-50%);border-radius:999px;background:var(--surface);border:2px solid var(--accent);transition:background-color .5s ease .3s,box-shadow .4s ease}
.ps-js .ps-tl-item.is-in::before{background:var(--accent)}
.ps-tl-item:hover::before{box-shadow:0 0 0 6px color-mix(in srgb,var(--accent) 20%,transparent)}
.ps-tl-item::after{content:'';position:absolute;left:calc(-2.25rem - 1.5px);top:0;bottom:0;width:1.5px;background:var(--accent);transform:scaleY(0);transform-origin:top;transition:transform 1.1s cubic-bezier(.5,0,.2,1) .2s}
.ps-js .ps-tl-item.is-in::after{transform:scaleY(1)}
.ps-tl-item:last-child::after{display:none}
.ps-tl-card{border:1px solid var(--line);border-radius:1.5rem;background:var(--paper);padding:1.4rem 1.6rem 1.5rem;transition:transform .45s cubic-bezier(.2,.7,.2,1),box-shadow .45s ease,border-color .3s ease}
.ps-tl-card:hover{transform:translateX(6px);border-color:color-mix(in srgb,var(--accent) 55%,var(--line));box-shadow:0 22px 40px -26px var(--accent)}
.ps-period{font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.12em;color:var(--accent)}
.ps-item-title{margin-top:.5rem;font-size:1.5rem;line-height:1.25}
.ps-company{margin-top:.25rem;font-weight:600}
.ps-item-desc{max-width:38rem;margin-top:.75rem;white-space:pre-line;line-height:1.7}

/* ---------- Skills ---------- */
.ps-skills-desc{margin-top:1.5rem;white-space:pre-line;line-height:1.7}
.ps-skill-list{display:grid;align-content:start;gap:1rem}
@media(min-width:640px){.ps-skill-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
.ps-skill{border:1px solid var(--line);border-radius:1.4rem;background:var(--paper);padding:1.2rem 1.3rem 1.3rem;transition:transform .4s cubic-bezier(.2,.7,.2,1),border-color .3s ease,box-shadow .4s ease}
.ps-skill:hover{transform:translateY(-3px);border-color:var(--accent);box-shadow:0 18px 32px -22px var(--accent)}
.ps-skill-row{display:flex;align-items:center;gap:.875rem}
.ps-skill-name{font-weight:600}
.ps-skill-pct{margin-left:auto;font-size:.8rem;font-weight:500}
.ps-tile{display:inline-flex;height:2.6rem;width:2.6rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:.95rem;background:color-mix(in srgb,var(--accent) 13%,transparent);border:1px solid color-mix(in srgb,var(--accent) 35%,var(--line));color:var(--accent);transition:background-color .3s ease,color .3s ease,transform .35s cubic-bezier(.2,.7,.2,1)}
.ps-tile-lg{height:3.5rem;width:3.5rem;border-radius:1.15rem}
.ps-tile-letter{font-size:1rem;line-height:1}
.ps-tile-glyph{font-size:1.125rem;line-height:1}
.ps-tile-glyph-lg{font-size:1.5rem;line-height:1}
.ps-skill:hover .ps-tile,.ps-service:hover .ps-tile{background:var(--accent);color:var(--accent-ink);transform:rotate(-6deg) scale(1.06)}
.ps-track{position:relative;height:6px;width:100%;margin-top:1rem;border-radius:999px;background:color-mix(in srgb,var(--ink) 10%,transparent)}
.ps-bar{position:relative;height:100%;width:var(--w,0%);border-radius:inherit;background:linear-gradient(90deg,color-mix(in srgb,var(--accent) 55%,transparent),var(--accent))}
.ps-knob{position:absolute;right:0;top:50%;height:14px;width:14px;transform:translate(50%,-50%);border-radius:999px;border:3px solid var(--accent);background:var(--surface)}
.ps-js [data-reveal] .ps-bar{width:0;transition:width 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s)}
.ps-js [data-reveal].is-in .ps-bar{width:var(--w,0%)}
.ps-js [data-reveal].is-in .ps-knob{animation:ps-knob .6s cubic-bezier(.2,.8,.2,1) calc(var(--d,0ms) + 1.6s) backwards}
@property --n{syntax:'<integer>';inherits:false;initial-value:0}
.ps-count{--n:var(--p,0);counter-reset:n var(--n);font-variant-numeric:tabular-nums}
.ps-count::after{content:counter(n) '%'}
.ps-js [data-reveal] .ps-count{--n:0}
.ps-js [data-reveal].is-in .ps-count{animation:ps-count 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s) forwards}

/* ---------- 3D scale elevation (projects + services) ---------- */
.ps-elevate{position:relative;display:flex;height:100%;flex-direction:column;border:1px solid var(--line);border-radius:1.9rem;background:var(--paper);transform:perspective(1100px) translateZ(0) scale(1);transform-style:preserve-3d;transition:transform .5s cubic-bezier(.2,.8,.2,1),box-shadow .5s ease,border-color .3s ease;will-change:transform}
.ps-elevate:hover{z-index:5;transform:perspective(1100px) translateY(-10px) translateZ(40px) scale(1.045) rotateX(1.5deg);border-color:color-mix(in srgb,var(--accent) 60%,var(--line));box-shadow:0 50px 90px -34px color-mix(in srgb,var(--ink) 65%,transparent),0 24px 50px -30px var(--accent)}

/* ---------- Projects ---------- */
.ps-proj-head{display:grid;gap:1.5rem;margin-bottom:3.5rem}
@media(min-width:1024px){.ps-proj-head{grid-template-columns:repeat(12,minmax(0,1fr));align-items:end}}
.ps-proj-desc{white-space:pre-line;line-height:1.7}
.ps-project-grid{display:grid;gap:1.75rem}
.ps-project-grid>li{min-width:0}
@media(min-width:768px){.ps-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ps-project-grid>.ps-feature{grid-column:span 2/span 2}}
@media(min-width:1280px){.ps-project-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.ps-project{padding:.75rem}
.ps-media{position:relative;display:block;overflow:hidden;border-radius:1.4rem;background:var(--soft);border:1px solid var(--line);isolation:isolate}
.ps-media-std{aspect-ratio:4/3}
.ps-media-wide{aspect-ratio:16/9}
.ps-media img{width:100%;height:100%;object-fit:cover}
.ps-media img,.ps-tone{transition:transform .9s cubic-bezier(.2,.7,.2,1)}
.ps-project:hover .ps-media img,.ps-project:hover .ps-tone{transform:scale(1.07)}
.ps-tone{display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:6rem;line-height:1}
.ps-tone-0{background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 45%,#000));color:var(--accent-ink)}
.ps-tone-1{background:var(--accent-2);color:#16151a}
.ps-tone-2{background:var(--ink);color:var(--surface)}
.ps-flag{position:absolute;left:.9rem;top:.9rem;display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;background:var(--accent-2);color:#16151a;padding:.25rem .75rem;font-size:.74rem;font-weight:500}
.ps-go{position:absolute;right:.9rem;top:.9rem;display:inline-flex;height:2.75rem;width:2.75rem;align-items:center;justify-content:center;border-radius:999px;background:var(--surface);color:var(--ink);opacity:0;transform:translate(-.5rem,.5rem) scale(.9);transition:opacity .35s ease,transform .45s cubic-bezier(.2,.7,.2,1)}
.ps-project:hover .ps-go,.ps-media:focus-visible .ps-go{opacity:1;transform:none}
.ps-proj-body{display:flex;flex:1;flex-direction:column;padding:1.25rem .5rem .5rem}
.ps-proj-name{font-size:1.5rem;line-height:1.25}
.ps-underline{background:linear-gradient(currentColor,currentColor) left 96%/0% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1)}
.ps-project:hover .ps-underline{background-size:100% 2px}
.ps-proj-text{margin-top:.75rem;line-height:1.65}
.ps-tags{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
.ps-repo{margin-top:auto;padding-top:1.25rem}
.ps-repo .ps-textlink{font-size:.8rem}

/* ---------- Services ---------- */
.ps-services-head{max-width:48rem;margin-bottom:3.5rem}
.ps-service-grid{display:grid;gap:1.5rem}
.ps-service-grid>li{min-width:0}
@media(min-width:640px){.ps-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.ps-service-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.ps-service{padding:1.9rem}
.ps-service-title{margin-top:1.75rem;font-size:1.5rem;line-height:1.25;transition:color .3s ease}
.ps-service:hover .ps-service-title{color:var(--accent)}
.ps-service-text{margin-top:.75rem;line-height:1.65}
.ps-service-arrow{margin-top:auto;align-self:flex-end;color:var(--accent);opacity:0;transform:translate(-.6rem,.6rem);transition:opacity .3s ease,transform .4s cubic-bezier(.2,.7,.2,1)}
.ps-service:hover .ps-service-arrow{opacity:1;transform:none}

/* ---------- Contact ---------- */
.ps-contact-title{margin-top:1rem;font-size:clamp(2.75rem,7.5vw,6rem);line-height:.95;overflow-wrap:anywhere}
.ps-contact-text{max-width:34rem;margin-top:1.5rem;white-space:pre-line;font-size:1.125rem;line-height:1.7;opacity:.8}
.ps-contact-info{margin-top:2.5rem}
.ps-contact-info>*+*{margin-top:1.25rem}
.ps-bigmail{display:inline-flex;max-width:100%;align-items:center;gap:.75rem;word-break:break-all;font-size:1.5rem}
@media(min-width:640px){.ps-bigmail{font-size:2.25rem}}
.ps-underline-contact{background:linear-gradient(currentColor,currentColor) left bottom/100% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1);padding-bottom:4px}
.ps-bigmail:hover .ps-underline-contact{background-size:0% 2px;background-position:right bottom}
.ps-contact-meta{display:flex;flex-wrap:wrap;gap:.75rem 2rem;font-weight:500}
.ps-contact-meta>span{display:inline-flex;align-items:center;gap:.5rem}
.ps-contact-link{display:inline-flex;align-items:center;gap:.5rem;opacity:.9}
.ps-contact-link:hover{opacity:1;text-decoration:underline;text-underline-offset:4px}
.ps-contact-socials{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:2rem}
.ps-social{display:inline-flex;align-items:center;gap:.45rem;border-radius:999px;border:1px solid color-mix(in srgb,currentColor 40%,transparent);padding:.5rem 1rem;font-size:.88rem;font-weight:600;transition:background-color .3s ease,color .3s ease,border-color .3s ease,transform .3s ease}
.ps-social:hover{background:var(--surface);color:var(--ink);border-color:var(--surface);transform:translateY(-2px)}
.ps-form{padding:1.5rem;border-radius:1.9rem;border:1px solid color-mix(in srgb,var(--surface) 22%,transparent);background:color-mix(in srgb,var(--surface) 8%,transparent);-webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px)}
@media(min-width:640px){.ps-form{padding:2rem}}
.ps-field{display:block;margin-bottom:1.25rem}
.ps-label{display:block;margin-bottom:.5rem;font-size:.72rem;font-weight:500;text-transform:uppercase;letter-spacing:.12em;opacity:.7}
.ps-input{width:100%;border-radius:.95rem;border:1px solid color-mix(in srgb,var(--surface) 28%,transparent);background:color-mix(in srgb,var(--surface) 6%,transparent);padding:.8rem 1rem;font:inherit;font-weight:500;color:var(--surface);transition:border-color .25s ease,box-shadow .25s ease,background-color .25s ease}
.ps-input:hover{border-color:color-mix(in srgb,var(--surface) 55%,transparent)}
.ps-input:focus{outline:none;border-color:var(--accent-2);background:color-mix(in srgb,var(--surface) 10%,transparent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent-2) 25%,transparent)}
textarea.ps-input{resize:vertical}
.ps-sent{display:flex;align-items:center;gap:.5rem;margin-top:1.25rem;font-size:.875rem;font-weight:600;animation:ps-pop .5s cubic-bezier(.2,.8,.2,1) both}
.ps-sheet-contact .ps-btn-primary{background-color:var(--accent-2);color:#16151a;background-image:linear-gradient(var(--surface),var(--surface))}
.ps-sheet-contact .ps-btn-primary:hover{color:var(--ink)}
.ps-sheet-contact .ps-btn-ghost{background-image:linear-gradient(var(--surface),var(--surface));border-color:color-mix(in srgb,var(--surface) 45%,transparent);color:var(--surface)}
.ps-sheet-contact .ps-btn-ghost:hover{color:var(--ink)}
.ps-sheet-contact .ps-tick{background:var(--accent-2);color:#16151a}
.ps-hidden-frame{display:none}
.ps-foot{display:flex;max-width:1180px;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;margin-inline:auto;padding:2.5rem 1.25rem;font-size:.875rem}
@media(min-width:640px){.ps-foot{padding-inline:2rem}}
.ps-foot-copy{font-size:.78rem}

/* ---------- Scroll reveal (class is added by script.js, so no-JS output stays visible) ---------- */
.ps-js [data-reveal]{opacity:0;transform:translateY(24px);transition:opacity .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms),transform .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms)}
.ps-js [data-reveal].is-in{opacity:1;transform:none}
.ps-js .ps-tl-item[data-reveal]{transform:translateX(26px)}
.ps-js .ps-tl-item[data-reveal].is-in{transform:none}
.ps-js [data-reveal].is-in .ps-elevate{animation:ps-card-in .9s cubic-bezier(.2,.8,.2,1) backwards}
.ps-js [data-reveal]:not(.is-in) .ps-h2{letter-spacing:.01em}
.ps-js [data-reveal].is-in .ps-frame{animation:ps-frame-in 1.1s cubic-bezier(.2,.8,.2,1) backwards}
.ps-js [data-reveal]:not(.is-in) .ps-hl{opacity:0}
.ps-js [data-reveal].is-in .ps-hl{animation:ps-slide-x .8s cubic-bezier(.2,.7,.2,1) calc(var(--k,0)*90ms + 150ms) backwards}
.ps-js [data-reveal].is-in .ps-hl .ps-tick{animation:ps-tick .6s cubic-bezier(.3,1.6,.4,1) calc(var(--k,0)*90ms + 450ms) backwards}
.ps-js [data-reveal].is-in .ps-tile{animation:ps-tile .7s cubic-bezier(.3,1.5,.4,1) calc(var(--d,0ms) + .15s) backwards}
.ps-js [data-reveal].is-in .ps-media{animation:ps-wipe 1.2s cubic-bezier(.7,0,.2,1) .1s backwards}
.ps-js [data-reveal].is-in .ps-media img{animation:ps-settle 1.6s cubic-bezier(.2,.7,.2,1) .1s backwards}
.ps-js.ps-root{animation:ps-enter .7s ease both}
@keyframes ps-spin{to{transform:rotate(360deg)}}
@keyframes ps-float{50%{transform:translateY(-10px)}}
@keyframes ps-drift{50%{transform:translate(-2.5rem,2rem) scale(1.08)}}
@keyframes ps-rise{to{transform:translateY(0)}}
@keyframes ps-enter{from{opacity:0}}
@keyframes ps-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes ps-pop{from{opacity:0;transform:translateY(8px) scale(.96)}}
@keyframes ps-knob{from{transform:translate(50%,-50%) scale(0)}}
@keyframes ps-count{to{--n:var(--p)}}
@keyframes ps-frame-in{from{clip-path:inset(0 0 100% 0 round 2rem);transform:rotate(4deg) scale(.95)}}
@keyframes ps-slide-x{from{opacity:0;transform:translateX(-20px)}}
@keyframes ps-tick{from{transform:scale(0) rotate(-90deg)}}
@keyframes ps-tile{from{transform:scale(.4) rotate(-20deg);opacity:0}}
@keyframes ps-wipe{from{clip-path:inset(0 0 100% 0 round 1.4rem)}}
@keyframes ps-settle{from{transform:scale(1.25)}}
@keyframes ps-card-in{from{transform:perspective(1100px) translateY(30px) rotateX(8deg) scale(.96)}}

/* ---------- Reduced motion ---------- */
@media(prefers-reduced-motion:reduce){
html{scroll-behavior:auto}
.ps-root *,.ps-root *::before,.ps-root *::after{animation:none!important;transition:none!important}
.ps-js .ps-word,.ps-js [data-reveal],.ps-js .ps-tl-item[data-reveal],.ps-js [data-reveal]:not(.is-in) .ps-hl{opacity:1;transform:none}
.ps-js [data-reveal] .ps-count{--n:var(--p,0)}
.ps-js [data-reveal] .ps-bar{width:var(--w,0%)}
.ps-js [data-reveal]:not(.is-in) .ps-kicker::before{transform:none}
.ps-js [data-reveal]:not(.is-in) .ps-h2{letter-spacing:-.035em}
.ps-tl-item::after{transform:scaleY(1)}
.ps-sheet:first-child{transform:none}
.ps-elevate:hover{transform:none}
}
CSS;
    }
}
