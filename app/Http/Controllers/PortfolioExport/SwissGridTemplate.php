<?php

namespace App\Http\Controllers\PortfolioExport;

trait SwissGridTemplate
{
    private function renderSwissGridPage(string $title, array $content, array $assets, ?array $googleForm): string
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
        $icon = fn (string $name, int $size = 16, string $class = '', string $stroke = '2'): string => $this->swissGridIcon($name, $size, $class, $stroke);
        $num = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
        $pad = static fn (int $number): string => str_pad((string) $number, 2, '0', STR_PAD_LEFT);

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

        $hasNavbar = $hasContent($navbar);
        $hasHeader = $hasContent($header);
        $hasAbout = $hasContent($about);
        $hasJourney = $hasContent($journeyData);
        $hasSkills = $hasContent($skillsData);
        $hasProjects = $hasContent($projectData);
        $hasServices = $hasContent($servicesData);
        $hasContact = $hasContent($contact);

        // Section numbering follows whichever sections are visible.
        $present = ['about' => $hasAbout, 'journey' => $hasJourney, 'skills' => $hasSkills, 'projects' => $hasProjects, 'services' => $hasServices, 'contact' => $hasContact];
        $orderedIds = array_values(array_keys(array_filter($present)));
        $numberOf = static function (string $id) use ($orderedIds, $pad): string {
            $position = array_search($id, $orderedIds, true);

            return $pad($position === false ? 1 : $position + 1);
        };

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
        $contactLabel = $text($contact, 'contact_subtitle') ?? $text($contact, 'contact_title') ?? '';
        $formReady = $flag($contact, 'contact_form_enabled', true) && $googleForm !== null;

        $navLinks = array_values(array_filter([
            ['id' => 'about', 'label' => 'About', 'present' => $hasAbout],
            ['id' => 'journey', 'label' => 'Journey', 'present' => $hasJourney],
            ['id' => 'skills', 'label' => 'Skills', 'present' => $hasSkills],
            ['id' => 'projects', 'label' => 'Work', 'present' => $hasProjects],
            ['id' => 'services', 'label' => 'Services', 'present' => $hasServices],
        ], static fn (array $definition): bool => $definition['present']));

        // Shared section header: number + rule + subtitle.
        $head = static fn (string $id, ?string $subtitle): string => '<div class="gi-head"><span class="gi-index gi-mono">'.e($numberOf($id)).'</span>'
            .($subtitle !== null ? '<p class="gi-kicker">'.e($subtitle).'</p>' : '').'</div>';

        /* ---------------------------------------------------------------
         | Markup
         * ------------------------------------------------------------- */
        $html = '<div class="gi-root">';

        // Navbar
        if ($hasNavbar || $navLinks !== [] || $hasContact || $logoText !== null) {
            $html .= '<header class="gi-header '.($stickyNav ? 'is-fixed' : 'is-absolute').'"><div class="gi-progress" data-progress aria-hidden="true"></div>';
            $html .= '<nav class="gi-nav" data-nav aria-label="Primary"><div class="gi-nav-inner">';
            if ($logoImage !== '' || $logoText !== null) {
                $html .= '<a href="#header" class="gi-logo" data-scroll-link>';
                if ($logoImage !== '') {
                    $html .= '<img src="'.$escape($logoImage).'" alt="'.$escape($logoText ?? '').'" class="gi-logo-img">';
                }
                if ($logoText !== null) {
                    $html .= '<span class="gi-mono gi-logo-text">'.$escape($logoText).'</span>';
                }
                $html .= '</a>';
            } else {
                $html .= '<span></span>';
            }
            if ($navLinks !== []) {
                $html .= '<ul class="gi-navlinks">';
                foreach ($navLinks as $link) {
                    $html .= '<li><a href="#'.$escape($link['id']).'" class="gi-navlink" data-active="false" data-section-link="'.$escape($link['id']).'" data-scroll-link><span class="gi-mono">'.$escape($numberOf($link['id'])).'</span>'.$escape($link['label']).'</a></li>';
                }
                $html .= '</ul>';
            }
            if ($hasContact) {
                $html .= '<a href="#contact" class="gi-btn gi-btn-primary gi-btn-sm" data-scroll-link>'.$escape($contactLabel).$icon('arrow-up-right', 16, 'gi-arrow').'</a>';
            } else {
                $html .= '<span></span>';
            }
            $html .= '</div></nav></header>';
        }

        // Hero
        if ($hasHeader) {
            $html .= '<section id="header" class="gi-hero" data-page-section><span id="home" class="gi-home-anchor" aria-hidden="true"></span>';
            if ($heroBackground !== '') {
                $html .= '<img src="'.$escape($heroBackground).'" alt="" class="gi-hero-bg">';
            }
            $html .= '<div class="gi-columns" aria-hidden="true"></div><div class="gi-hero-in">';

            $html .= '<div class="gi-meta-row" data-reveal style="--d: 0ms">';
            if ($greeting !== null) {
                $html .= '<p class="gi-label"><span class="gi-pulse" aria-hidden="true"></span>'.$escape($greeting).'</p>';
            }
            if ($location !== null) {
                $html .= '<span class="gi-mono gi-muted gi-loc">'.$icon('map-pin', 14, 'gi-accent').$escape($location).'</span>';
            }
            $html .= '</div>';

            $html .= '<div class="gi-hero-grid"><div class="gi-hero-name-col">';
            if ($heroName !== null) {
                $html .= '<h1 class="gi-display gi-hero-name"><span class="sr-only">'.$escape($heroName).'</span>';
                foreach ($heroWords as $index => $word) {
                    $html .= '<span class="gi-mask" aria-hidden="true"><span class="gi-word" style="--i: '.$index.'">'.$escape($word).'</span></span>';
                }
                $html .= '</h1>';
            }
            $html .= '</div>';
            if ($heroImage !== '' || $heroInitials !== '') {
                $html .= '<div class="gi-hero-portrait-col"><figure data-reveal class="gi-portrait" style="--d: 250ms"><div class="gi-photo">';
                if ($heroImage !== '') {
                    $html .= '<img src="'.$escape($heroImage).'" alt="'.$escape($heroName ?? '').'">';
                } else {
                    $html .= '<span class="gi-display gi-initials">'.$escape($heroInitials).'</span>';
                }
                $html .= '</div><span class="gi-corner gi-corner-tl" aria-hidden="true"></span><span class="gi-corner gi-corner-br" aria-hidden="true"></span></figure></div>';
            }
            $html .= '</div><div class="gi-hairline" aria-hidden="true"></div>';

            $html .= '<div class="gi-hero-lower">';
            if ($role !== null) {
                $html .= '<p data-reveal class="gi-mono gi-role gi-m4" style="--d: 380ms">'.$icon('arrow-down-right', 24, 'gi-role-arrow').$escape($role).'</p>';
            }
            $html .= '<div class="gi-m8">';
            if ($heroDescription !== null) {
                $html .= '<p data-reveal class="gi-muted gi-hero-desc" style="--d: 460ms">'.$escape($heroDescription).'</p>';
            }
            if ($heroPrimary !== null || $heroSecondary !== null) {
                $html .= '<div data-reveal class="gi-actions" style="--d: 540ms">';
                if ($heroPrimary !== null) {
                    $html .= '<a href="'.$url($heroPrimary['url']).'" class="gi-btn gi-btn-primary" data-scroll-link>'.$escape($heroPrimary['text']).$icon('arrow-up-right', 16, 'gi-arrow').'</a>';
                }
                if ($heroSecondary !== null) {
                    $html .= '<a href="'.$url($heroSecondary['url']).'" class="gi-btn gi-btn-ghost" data-scroll-link>'.$escape($heroSecondary['text']).'</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
            if ($heroSocials !== []) {
                $html .= '<ul data-reveal class="gi-icons" style="--d: 620ms">';
                foreach ($heroSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($social['label']).'" title="'.$escape($social['label']).'" class="gi-icon-btn">'.$icon($socialIcon($social['platform']), 18).'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div></div></section>';
        }

        // About
        if ($hasAbout) {
            $html .= '<section id="about" class="gi-band" data-page-section><div class="gi-in gi-block"><div data-reveal>'.$head('about', $text($about, 'about_subtitle')).'</div>';
            $html .= '<div class="gi-about-grid">';
            if ($text($about, 'about_title') !== null) {
                $html .= '<div class="gi-c7"><h2 data-reveal class="gi-display gi-h2">'.$escape($text($about, 'about_title')).'</h2></div>';
            }
            if ($aboutImage !== '') {
                $html .= '<div data-reveal class="gi-about-image"><div class="gi-frame"><img src="'.$escape($aboutImage).'" alt="'.$escape($text($about, 'about_title') ?? '').'" loading="lazy"></div></div>';
            }
            $html .= '<div class="gi-c7"><div data-reveal>';
            if ($text($about, 'about_description') !== null) {
                $html .= '<p class="gi-muted gi-prose">'.$escape($text($about, 'about_description')).'</p>';
            }
            $html .= '</div>';
            if ($highlights !== []) {
                $html .= '<ul data-reveal class="gi-cards gi-hl-list">';
                foreach ($highlights as $index => $highlight) {
                    $html .= '<li class="gi-hl" style="--k: '.$index.'"><span class="gi-tick">'.$icon('check', 14, '', '3').'</span><span><span class="gi-hl-title">'.$escape($highlight['title']).'</span>';
                    if ($highlight['description'] !== null) {
                        $html .= '<span class="gi-muted gi-hl-desc">'.$escape($highlight['description']).'</span>';
                    }
                    $html .= '</span></li>';
                }
                $html .= '</ul>';
            }
            if ($cvFile !== '' || $aboutCta !== null) {
                $html .= '<div data-reveal class="gi-actions gi-actions-lg">';
                if ($cvFile !== '') {
                    $html .= '<a href="'.$escape($cvFile).'" download target="_blank" rel="noopener" class="gi-btn gi-btn-primary">'.$icon('download', 16).'Download CV</a>';
                }
                if ($aboutCta !== null) {
                    $html .= '<a href="'.$url($aboutCta['url']).'" class="gi-btn '.($cvFile !== '' ? 'gi-btn-ghost' : 'gi-btn-primary').'" data-scroll-link>'.$escape($aboutCta['text']).$icon('arrow-up-right', 16, 'gi-arrow').'</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></div></section>';
        }

        // Journey
        if ($hasJourney) {
            $html .= '<section id="journey" data-page-section><div class="gi-in gi-block"><div data-reveal>'.$head('journey', $text($journeyData, 'journey_subtitle')).'</div>';
            if ($text($journeyData, 'journey_title') !== null) {
                $html .= '<h2 data-reveal class="gi-display gi-h2 gi-mt6 gi-maxw4xl">'.$escape($text($journeyData, 'journey_title')).'</h2>';
            }
            $html .= '<ol class="gi-rule-top gi-journey-list">';
            foreach ($journey as $index => $item) {
                $html .= '<li data-reveal style="--d: '.(($index % 4) * 70).'ms" class="gi-tl">';
                if ($item['period'] !== null) {
                    $html .= '<p class="gi-mono gi-period gi-m3">'.$escape($item['period']).'</p>';
                }
                $html .= '<div class="gi-m4">';
                if ($item['title'] !== null) {
                    $html .= '<h3 class="gi-display gi-item-title">'.$escape($item['title']).'</h3>';
                }
                if ($item['company'] !== null) {
                    $html .= '<p class="gi-mono gi-muted gi-company">'.$escape($item['company']).'</p>';
                }
                $html .= '</div>';
                if ($item['description'] !== null) {
                    $html .= '<p class="gi-muted gi-item-desc gi-m5">'.$escape($item['description']).'</p>';
                }
                $html .= '</li>';
            }
            $html .= '</ol></div></section>';
        }

        // Skills
        if ($hasSkills) {
            $html .= '<section id="skills" class="gi-band" data-page-section><div class="gi-in gi-grid gi-grid-skills"><div class="gi-c4"><div data-reveal class="gi-sticky">'.$head('skills', $text($skillsData, 'skills_subtitle'));
            if ($text($skillsData, 'skills_title') !== null) {
                $html .= '<h2 class="gi-display gi-h2 gi-mt6">'.$escape($text($skillsData, 'skills_title')).'</h2>';
            }
            if ($text($skillsData, 'skills_description') !== null) {
                $html .= '<p class="gi-muted gi-skills-desc">'.$escape($text($skillsData, 'skills_description')).'</p>';
            }
            $html .= '</div></div><ul class="gi-cards gi-skill-list">';
            foreach ($skills as $index => $skill) {
                $html .= '<li data-reveal style="--d: '.(($index % 6) * 50).'ms" class="gi-skill"><div class="gi-skill-row">';
                if ($showIcon) {
                    $skillIcon = $iconFor($skill['icon']);
                    $html .= '<span class="gi-tile">';
                    if ($skillIcon !== null) {
                        $html .= $icon($skillIcon, 18);
                    } elseif ($isGlyph($skill['icon'])) {
                        $html .= '<span class="gi-tile-glyph" aria-hidden="true">'.$escape($skill['icon']).'</span>';
                    } else {
                        $html .= '<span class="gi-display gi-tile-letter" aria-hidden="true">'.$escape(mb_strtoupper(mb_substr($skill['name'], 0, 1))).'</span>';
                    }
                    $html .= '</span>';
                }
                $html .= '<h3 class="gi-skill-name">'.$escape($skill['name']).'</h3>';
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<span class="gi-count gi-mono gi-muted gi-skill-pct" style="--p: '.(int) round($skill['percent']).'" aria-hidden="true"></span>';
                } elseif ($showLevel && $skill['label'] !== null) {
                    $html .= '<span class="gi-pill gi-pill-end">'.$escape($skill['label']).'</span>';
                }
                $html .= '</div>';
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<div class="gi-track" role="progressbar" aria-valuenow="'.(int) round($skill['percent']).'" aria-valuemin="0" aria-valuemax="100" aria-label="'.$escape($skill['name']).'"><div class="gi-bar" style="--w: '.$num($skill['percent']).'%"></div></div>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></div></section>';
        }

        // Projects
        if ($hasProjects) {
            $html .= '<section id="projects" class="gi-section" data-page-section><div class="gi-in gi-block"><div data-reveal>'.$head('projects', $text($projectData, 'projects_subtitle')).'</div>';
            $html .= '<div data-reveal class="gi-proj-head">';
            if ($text($projectData, 'projects_title') !== null) {
                $html .= '<h2 class="gi-display gi-h2 gi-c8">'.$escape($text($projectData, 'projects_title')).'</h2>';
            }
            if ($text($projectData, 'projects_description') !== null) {
                $html .= '<p class="gi-muted gi-proj-desc gi-c4">'.$escape($text($projectData, 'projects_description')).'</p>';
            }
            $html .= '</div><ul class="gi-project-grid">';
            foreach ($projects as $index => $project) {
                $featured = $featureFirst && $index === 0;
                $html .= '<li data-reveal'.($featured ? ' class="gi-feature"' : '').' style="--d: '.(($index % 3) * 80).'ms"><article class="gi-project">';
                $html .= '<div class="gi-mono gi-proj-top"><span>'.$pad($index + 1).'</span>';
                if ($featured) {
                    $html .= '<span class="gi-flag">'.$escape($numberOf('projects')).'&nbsp;/&nbsp;★</span>';
                }
                $html .= '</div>';
                $mediaClass = 'gi-media '.($featured ? 'gi-media-wide' : 'gi-media-std');
                if ($project['live'] !== '') {
                    $html .= '<a href="'.$escape($project['live']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($project['title']).'" class="'.$mediaClass.'">';
                } else {
                    $html .= '<div class="'.$mediaClass.'">';
                }
                if ($project['image'] !== '') {
                    $html .= '<img src="'.$escape($project['image']).'" alt="'.$escape($project['title']).'" loading="lazy">';
                } else {
                    $html .= '<div class="gi-display gi-tone gi-tone-'.($index % 3).'">'.$escape(mb_strtoupper(mb_substr($project['title'], 0, 1))).'</div>';
                }
                if ($project['live'] !== '') {
                    $html .= '<span class="gi-go" aria-hidden="true">'.$icon('arrow-up-right', 20).'</span>';
                }
                $html .= $project['live'] !== '' ? '</a>' : '</div>';

                $html .= '<div class="gi-proj-body"><h3 class="gi-display gi-proj-name"><span class="gi-underline">'.$escape($project['title']).'</span></h3>';
                if ($project['description'] !== null) {
                    $html .= '<p class="gi-muted gi-proj-text">'.$escape($project['description']).'</p>';
                }
                if ($project['tags'] !== []) {
                    $html .= '<ul class="gi-tags">';
                    foreach ($project['tags'] as $tag) {
                        $html .= '<li class="gi-pill">'.$escape($tag).'</li>';
                    }
                    $html .= '</ul>';
                }
                if ($project['repo'] !== '') {
                    $html .= '<div class="gi-repo"><a href="'.$escape($project['repo']).'" target="_blank" rel="noopener noreferrer" class="gi-textlink gi-mono">'.$icon('code', 16).$escape($project['repo_label']).'</a></div>';
                }
                $html .= '</div></article></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Services
        if ($hasServices) {
            $html .= '<section id="services" class="gi-band" data-page-section><div class="gi-in gi-block"><div data-reveal>'.$head('services', $text($servicesData, 'services_subtitle')).'</div>';
            if ($text($servicesData, 'services_title') !== null) {
                $html .= '<h2 data-reveal class="gi-display gi-h2 gi-mt6 gi-mb14 gi-maxw4xl">'.$escape($text($servicesData, 'services_title')).'</h2>';
            }
            $html .= '<ul class="gi-rule-top">';
            foreach ($services as $index => $service) {
                $html .= '<li data-reveal style="--d: '.(($index % 5) * 70).'ms"><div class="gi-row"><span class="gi-mono gi-row-num gi-m1" aria-hidden="true">'.$pad($index + 1).'</span><div class="gi-row-main gi-m5"><span class="gi-tile gi-row-tile">';
                if (! $isGlyph($service['icon'])) {
                    $html .= $icon($iconFor($service['icon']) ?? 'sparkles', 20);
                } else {
                    $html .= '<span class="gi-tile-glyph-lg" aria-hidden="true">'.$escape($service['icon']).'</span>';
                }
                $html .= '</span><h3 class="gi-display gi-row-title">'.$escape($service['title']).'</h3></div>';
                if ($service['description'] !== null) {
                    $html .= '<p class="gi-muted gi-row-text gi-m5">'.$escape($service['description']).'</p>';
                }
                $html .= $icon('arrow-up-right', 24, 'gi-row-arrow').'</div></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Contact (+ footer)
        if ($hasContact) {
            $html .= '<section id="contact" class="gi-contact-section" data-page-section><div data-reveal class="gi-contact"><div class="gi-contact-in"><div class="gi-c7"><div data-reveal>'.$head('contact', $text($contact, 'contact_subtitle'));
            if ($text($contact, 'contact_title') !== null) {
                $html .= '<h2 class="gi-display gi-contact-title">'.$escape($text($contact, 'contact_title')).'</h2>';
            }
            if ($text($contact, 'contact_description') !== null) {
                $html .= '<p class="gi-muted gi-contact-text">'.$escape($text($contact, 'contact_description')).'</p>';
            }
            $html .= '</div>';
            if ($emailOk || $phoneOk || $location !== null) {
                $html .= '<div data-reveal class="gi-contact-info">';
                if ($emailOk) {
                    $html .= '<a href="'.$escape($emailHref).'" class="gi-bigmail gi-display">'.$icon('mail', 28, 'gi-accent').'<span class="gi-underline-contact">'.$escape($email).'</span>'.$icon('arrow-up-right', 28, 'gi-arrow').'</a>';
                }
                if ($phoneOk || $location !== null) {
                    $html .= '<div class="gi-mono gi-contact-meta">';
                    if ($phoneOk) {
                        $html .= '<a href="'.$escape($phoneHref).'" class="gi-contact-link">'.$icon('phone', 16, 'gi-accent').$escape($phone).'</a>';
                    }
                    if ($location !== null) {
                        $html .= '<span>'.$icon('map-pin', 16, 'gi-accent').$escape($location).'</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            if ($contactSocials !== []) {
                $html .= '<ul data-reveal class="gi-contact-socials">';
                foreach ($contactSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" class="gi-social">'.$icon($socialIcon($social['platform']), 14).$escape($social['label']).$icon('arrow-up-right', 14, 'gi-arrow').'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><div class="gi-c5"><div data-reveal style="--d: 120ms">';

            if ($formReady) {
                $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="gi-gform-frame" class="gi-form" data-google-form>
                <label class="gi-field"><span class="gi-mono gi-muted gi-label-text">Name</span><input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="gi-input"></label>
                <label class="gi-field"><span class="gi-mono gi-muted gi-label-text">Email</span><input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="gi-input"></label>
                <label class="gi-field"><span class="gi-mono gi-muted gi-label-text">Message</span><textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required class="gi-input"></textarea></label>
                <button type="submit" class="gi-btn gi-btn-primary gi-btn-block"><span data-submit-label>Send</span>'.$icon('send', 16, 'gi-arrow').'</button>
                <p class="gi-sent" role="status" data-form-status hidden><span class="gi-tick">'.$icon('check', 14, '', '3').'</span>OK</p>
            </form>';
            } elseif ($emailOk) {
                $html .= '<a href="'.$escape($emailHref).'" class="gi-btn gi-btn-ghost gi-btn-block gi-btn-xl">'.$icon('mail', 20).$escape($email).'</a>';
            }
            $html .= '</div>';
            if ($formReady) {
                $html .= '<iframe name="gi-gform-frame" title="Form submission target" class="gi-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
            }
            $html .= '</div></div></div>';

            if ($heroName !== null || $logoText !== null) {
                $html .= '<footer class="gi-foot"><span class="gi-muted gi-mono gi-foot-copy">&copy; '.date('Y').' '.$escape($heroName ?? $logoText).'</span><a href="#header" class="gi-textlink" aria-label="Top" data-scroll-link>'.$icon('arrow-up-right', 16, 'gi-arrow gi-rot').'</a></footer>';
            }
            $html .= '</section>';
        }
        $html .= '</div>';

        return '<!doctype html>
<html lang="en" data-template="swiss-grid">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#0f0f11" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#fafafa" media="(prefers-color-scheme: light)">
    <title>'.$escape($title).'</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    /** Inline Lucide-style icons (static, trusted markup only). */
    private function swissGridIcon(string $name, int $size = 16, string $class = '', string $stroke = '2'): string
    {
        $paths = [
            'arrow-down-right' => '<path d="m7 7 10 10"/><path d="M17 7v10H7"/>',
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

        return '<svg class="gi-ico'.($class !== '' ? ' '.$class : '').'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$stroke.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$body.'</svg>';
    }

    private function swissGridStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');
/* ---------- Reset (Tailwind-preflight equivalent, low specificity) ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;color-scheme:dark light;-webkit-text-size-adjust:100%}
body{margin:0;background:#0f0f11}
@media(prefers-color-scheme:light){body{background:#fafafa}}
:where(.gi-root) :is(h1,h2,h3,p,ul,ol,figure){margin:0}
:where(.gi-root) :is(ul,ol){padding:0;list-style:none}
:where(.gi-root) :is(h1,h2,h3){font-size:inherit;font-weight:inherit}
:where(.gi-root) a{color:inherit;text-decoration:none}
:where(.gi-root) :is(button,input,textarea){font:inherit;color:inherit;letter-spacing:inherit}
:where(.gi-root) button{background:none;border:0;padding:0;cursor:pointer}
:where(.gi-root) img,:where(.gi-root) svg{display:block;max-width:100%}
.gi-root [hidden]{display:none!important}
.gi-ico{flex-shrink:0}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

/* ---------- Foundations + palette (dark by default, light via OS preference) ---------- */
.gi-root{--paper:#0F0F11;--soft:#131316;--surface:#18181B;--ink:#FAFAFA;--muted:#A1A1AA;--line:#27272A;--accent:#E05252;--accent-ink:#0F0F11;background:var(--paper);color:var(--ink);font-family:'Inter Tight',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;transition:background-color .4s ease,color .4s ease}
@media(prefers-color-scheme:light){.gi-root{--paper:#FAFAFA;--soft:#F1F1F3;--surface:#FFFFFF;--ink:#0F0F11;--muted:#52525B;--line:#D4D4D8;--accent:#D13A3A;--accent-ink:#FFFFFF}}
.gi-root ::selection{background:var(--accent);color:#0f0f11}
.gi-display{font-family:'Inter Tight',ui-sans-serif,system-ui,sans-serif;font-weight:700;letter-spacing:-.045em}
.gi-mono{font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:0}
.gi-muted{color:var(--muted)}
.gi-accent{color:var(--accent)}
.gi-mt6{margin-top:1.5rem}
.gi-mb14{margin-bottom:3.5rem}
.gi-maxw4xl{max-width:56rem}
.gi-h2{font-size:clamp(2.25rem,5.2vw,4.5rem);line-height:.98;letter-spacing:-.05em;text-wrap:balance;transition:letter-spacing 1.2s cubic-bezier(.2,.7,.2,1)}
.gi-band{background:var(--soft);border-block:1px solid var(--line)}
.gi-root :where(a,button,input,textarea):focus-visible{outline:2px solid var(--accent);outline-offset:3px}
.gi-section{position:relative}
.gi-in{margin-inline:auto;width:100%;max-width:1240px;padding-inline:1.25rem}
@media(min-width:640px){.gi-in{padding-inline:2rem}}
.gi-block{padding-block:7rem}
.gi-rule-top{border-top:1px solid var(--ink)}
.gi-hairline{height:1px;margin-top:3.5rem;background:var(--ink);transform-origin:left}

/* Section header: number + rule + subtitle */
.gi-head{display:flex;align-items:center;gap:1rem}
.gi-index{font-size:.78rem;font-weight:500;color:var(--accent)}
.gi-kicker{display:inline-flex;align-items:center;gap:1rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.75rem;font-weight:500;text-transform:uppercase;letter-spacing:.08em}
.gi-kicker::before{content:'';width:2.5rem;height:1px;background:currentColor;transform-origin:left;transition:transform .8s cubic-bezier(.2,.7,.2,1) .2s}
.gi-js [data-reveal]:not(.is-in) .gi-kicker::before{transform:scaleX(0)}

/* Elevated monochrome card grid with hairline gutters */
.gi-cards{display:grid;gap:1px;background:var(--line);border:1px solid var(--line)}
.gi-cards>li{background:var(--surface)}

/* ---------- Layout helpers ---------- */
.gi-grid{display:grid;gap:3rem}
.gi-grid-skills{padding-block:7rem}
.gi-c4,.gi-c5,.gi-c7,.gi-c8{min-width:0}
@media(min-width:1024px){
.gi-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:2.5rem}
.gi-c4{grid-column:span 4/span 4}.gi-c5{grid-column:span 5/span 5}.gi-c7{grid-column:span 7/span 7}.gi-c8{grid-column:span 8/span 8}
.gi-sticky{position:sticky;top:7rem}
}

/* ---------- Navbar ---------- */
.gi-header{position:fixed;inset:0 0 auto 0;z-index:50}
.gi-header.is-absolute{position:absolute}
.gi-progress{position:fixed;inset:0 0 auto 0;height:2px;background:var(--accent);transform:scaleX(0);transform-origin:left;pointer-events:none;z-index:60}
.gi-nav{border-bottom:1px solid transparent;transition:border-color .35s ease,background-color .35s ease}
.gi-nav.is-scrolled{border-color:var(--line);background:color-mix(in srgb,var(--paper) 88%,transparent);-webkit-backdrop-filter:blur(14px) saturate(1.3);backdrop-filter:blur(14px) saturate(1.3)}
.gi-nav-inner{display:flex;height:4rem;width:100%;max-width:1240px;margin-inline:auto;align-items:center;justify-content:space-between;gap:1rem;padding-inline:1.25rem}
@media(min-width:640px){.gi-nav-inner{padding-inline:2rem}}
.gi-logo{display:flex;min-width:0;align-items:center;gap:.75rem}
.gi-logo-img{height:1.75rem;width:auto;max-width:7rem;object-fit:contain}
.gi-logo-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.8rem;font-weight:500;text-transform:uppercase}
.gi-navlinks{display:none;align-items:center;gap:.25rem}
@media(min-width:768px){.gi-navlinks{display:flex}}
.gi-navlink{display:inline-flex;align-items:baseline;gap:.45rem;padding:.5rem .9rem;font-size:.9rem;font-weight:500;color:var(--muted);border:1px solid transparent;transition:color .25s ease,border-color .25s ease,background-color .25s ease}
.gi-navlink .gi-mono{font-size:.66rem;color:var(--accent)}
.gi-navlink:hover{color:var(--ink)}
.gi-navlink[data-active='true']{color:var(--ink);border-color:var(--line);background:var(--surface)}

/* ---------- Buttons & small controls ---------- */
.gi-btn{position:relative;isolation:isolate;display:inline-flex;align-items:center;gap:.5rem;border-radius:0;padding:.95rem 1.5rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.06em;line-height:1.2;cursor:pointer;border:1px solid transparent;background-repeat:no-repeat;background-position:left center;background-size:0% 100%;transition:background-size .5s cubic-bezier(.2,.7,.2,1),color .3s ease,transform .25s ease,border-color .3s ease}
.gi-btn:hover{background-size:100% 100%;transform:translate(-2px,-2px)}
.gi-btn:active{transform:translate(0,0) scale(.98)}
.gi-btn[disabled]{opacity:.7;pointer-events:none}
.gi-btn-primary{background-color:var(--accent);background-image:linear-gradient(var(--ink),var(--ink));color:var(--accent-ink)}
.gi-btn-primary:hover{color:var(--paper)}
.gi-btn-ghost{background-color:transparent;background-image:linear-gradient(var(--ink),var(--ink));border-color:var(--ink);color:var(--ink)}
.gi-btn-ghost:hover{color:var(--paper)}
.gi-btn-sm{padding:.625rem 1rem;font-size:.78rem}
.gi-btn-block{width:100%;justify-content:center}
.gi-btn-xl{padding-block:1.25rem}
.gi-arrow{transition:transform .3s cubic-bezier(.2,.7,.2,1)}
.gi-rot{transform:rotate(-45deg)}
.gi-btn:hover .gi-arrow,.gi-bigmail:hover .gi-arrow,.gi-social:hover .gi-arrow,.gi-textlink:hover .gi-arrow{transform:translate(3px,-3px)}
.gi-icon-btn{display:inline-flex;height:2.75rem;width:2.75rem;align-items:center;justify-content:center;border:1px solid var(--line);background:var(--surface);transition:transform .3s cubic-bezier(.2,.7,.2,1),background-color .25s ease,color .25s ease,border-color .25s ease}
.gi-icon-btn:hover{transform:translateY(-3px);background:var(--accent);color:var(--accent-ink);border-color:var(--accent)}
.gi-label{display:inline-flex;align-items:center;gap:.65rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.75rem;font-weight:500;text-transform:uppercase;letter-spacing:.08em}
.gi-pulse{position:relative;height:.5rem;width:.5rem;background:var(--accent)}
.gi-pulse::after{content:'';position:absolute;inset:0;background:inherit;animation:gi-ping 2.2s cubic-bezier(0,0,.2,1) infinite}
.gi-pill{display:inline-block;border:1px solid var(--line);padding:.2rem .6rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.7rem;font-weight:500;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.gi-pill-end{margin-left:auto}
.gi-tick{display:inline-flex;height:1.35rem;width:1.35rem;flex-shrink:0;align-items:center;justify-content:center;background:var(--accent);color:var(--accent-ink)}
.gi-textlink{display:inline-flex;align-items:center;gap:.4rem;font-weight:600;background:linear-gradient(currentColor,currentColor) left bottom/0% 1.5px no-repeat;transition:background-size .4s cubic-bezier(.2,.7,.2,1);padding-bottom:2px}
.gi-textlink:hover{background-size:100% 1.5px}

/* ---------- Hero ---------- */
.gi-hero{position:relative;isolation:isolate;display:flex;min-height:100vh;min-height:100svh;align-items:center;overflow:hidden}
.gi-home-anchor{position:absolute;top:0}
.gi-hero-bg{position:absolute;inset:0;z-index:-10;height:100%;width:100%;object-fit:cover;opacity:.12;filter:grayscale(1)}
.gi-columns{position:absolute;inset:0;z-index:-10;background-image:linear-gradient(to right,var(--line) 1px,transparent 1px);background-size:calc(100%/12) 100%;opacity:.4;-webkit-mask-image:linear-gradient(to bottom,transparent,#000 20%,#000 80%,transparent);mask-image:linear-gradient(to bottom,transparent,#000 20%,#000 80%,transparent)}
.gi-hero-in{margin-inline:auto;width:100%;max-width:1240px;padding:8rem 1.25rem 4rem}
@media(min-width:640px){.gi-hero-in{padding-inline:2rem}}
.gi-meta-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;border-bottom:1px solid var(--line);padding-bottom:1rem}
.gi-loc{display:inline-flex;align-items:center;gap:.5rem;font-size:.75rem;text-transform:uppercase}
.gi-hero-grid{display:grid;align-items:end;gap:3rem;margin-top:2.5rem}
@media(min-width:1024px){.gi-hero-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:2rem}.gi-hero-name-col{grid-column:span 8/span 8}.gi-hero-portrait-col{grid-column:span 4/span 4}}
.gi-hero-name-col,.gi-hero-portrait-col{min-width:0}
.gi-hero-name{font-size:clamp(3.5rem,12vw,10.5rem);line-height:.86;letter-spacing:-.065em;overflow-wrap:anywhere}
.gi-mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.12em;margin-bottom:-.12em;margin-right:.2em}
.gi-word{display:inline-block}
.gi-js .gi-word{transform:translateY(110%);animation:gi-rise 1s cubic-bezier(.2,.8,.2,1) forwards;animation-delay:calc(.15s + var(--i,0)*.1s)}
.gi-hero-lower{display:grid;gap:2rem;margin-top:2rem}
@media(min-width:768px){.gi-hero-lower{grid-template-columns:repeat(12,minmax(0,1fr))}.gi-hero-lower>.gi-m4{grid-column:span 4/span 4}.gi-hero-lower>.gi-m8{grid-column:span 8/span 8}.gi-icons{grid-column:span 12/span 12}}
@media(min-width:1024px){.gi-hero-lower>.gi-m8{grid-column:span 6/span 6}.gi-icons{grid-column:span 2/span 2;justify-content:flex-end}}
.gi-role{font-size:.85rem;font-weight:500;text-transform:uppercase;line-height:1.55}
.gi-role-arrow{margin-bottom:.75rem;color:var(--accent)}
.gi-hero-desc{max-width:34rem;white-space:pre-line;font-size:1.05rem;line-height:1.65}
.gi-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2rem}
.gi-actions-lg{margin-top:2.5rem}
.gi-icons{display:flex;flex-wrap:wrap;align-content:flex-start;gap:.5rem}
.gi-portrait{position:relative;margin-inline:auto;width:72%;max-width:20rem}
@media(min-width:640px){.gi-portrait{width:100%}}
@media(min-width:1024px){.gi-portrait{margin-left:auto;margin-right:0;max-width:18rem}}
.gi-photo{position:relative;aspect-ratio:4/5;overflow:hidden;background:var(--surface);border:1px solid var(--line)}
.gi-photo img{width:100%;height:100%;object-fit:cover;filter:grayscale(1);transition:transform .9s ease-out,filter .9s ease-out}
.gi-photo:hover img{transform:scale(1.05);filter:grayscale(0)}
.gi-initials{display:flex;width:100%;height:100%;align-items:center;justify-content:center;background:var(--surface);font-size:6rem;line-height:1;color:var(--accent)}
.gi-corner{position:absolute;height:1.25rem;width:1.25rem;border:2px solid var(--accent);pointer-events:none;transition:transform .5s cubic-bezier(.2,.7,.2,1)}
.gi-corner-tl{left:-.6rem;top:-.6rem;border-right:0;border-bottom:0}
.gi-corner-br{right:-.6rem;bottom:-.6rem;border-left:0;border-top:0}
.gi-portrait:hover .gi-corner-tl{transform:translate(-.3rem,-.3rem)}
.gi-portrait:hover .gi-corner-br{transform:translate(.3rem,.3rem)}

/* ---------- About ---------- */
.gi-about-grid{display:grid;align-items:start;gap:3rem;margin-top:2.5rem}
@media(min-width:1024px){.gi-about-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:2.5rem}.gi-about-image{grid-column:9/span 4;grid-row:span 2}}
.gi-about-image{min-width:0}
.gi-frame{position:relative;margin-inline:auto;max-width:28rem;padding:.5rem;border:1px solid var(--line);background:var(--surface);transition:transform .6s cubic-bezier(.2,.7,.2,1),box-shadow .6s cubic-bezier(.2,.7,.2,1)}
.gi-frame:hover{transform:translate(-4px,-4px);box-shadow:8px 8px 0 var(--accent)}
.gi-frame img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover;filter:grayscale(1);transition:filter .6s ease}
.gi-frame:hover img{filter:grayscale(0)}
.gi-prose{max-width:38rem;white-space:pre-line;font-size:1.05rem;line-height:1.75}
.gi-hl-list{margin-top:3rem}
@media(min-width:640px){.gi-hl-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
.gi-hl{display:flex;align-items:flex-start;gap:.9rem;padding:1.4rem 1.25rem;transition:background-color .3s ease,padding-left .35s cubic-bezier(.2,.7,.2,1)}
.gi-hl:hover{background:var(--soft);padding-left:1.75rem}
.gi-hl-title{display:block;font-weight:600;line-height:1.375}
.gi-hl-desc{display:block;margin-top:.25rem;font-size:.9rem;line-height:1.625}

/* ---------- Journey ---------- */
.gi-journey-list{margin-top:3.5rem}
.gi-tl{position:relative;display:grid;gap:.75rem;border-bottom:1px solid var(--line);padding-block:2rem}
@media(min-width:768px){.gi-tl{grid-template-columns:repeat(12,minmax(0,1fr));gap:2rem}.gi-tl>.gi-m3{grid-column:span 3/span 3}.gi-tl>.gi-m4{grid-column:span 4/span 4}.gi-tl>.gi-m5{grid-column:span 5/span 5}}
.gi-tl::before{content:'';position:absolute;left:0;top:-1px;height:2px;width:100%;background:var(--accent);transform:scaleX(0);transform-origin:left;transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.gi-tl:hover::before{transform:scaleX(1)}
.gi-period{font-size:.78rem;font-weight:500;text-transform:uppercase;color:var(--accent)}
.gi-item-title{font-size:1.5rem;line-height:1.25}
.gi-company{margin-top:.5rem;font-size:.8rem;text-transform:uppercase}
.gi-item-desc{white-space:pre-line;line-height:1.7}

/* ---------- Skills ---------- */
.gi-skills-desc{margin-top:1.5rem;white-space:pre-line;line-height:1.7}
.gi-skill-list{align-content:start;min-width:0}
@media(min-width:640px){.gi-skill-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.gi-skill-list{grid-column:span 8/span 8}}
.gi-skill{padding:1.5rem 1.25rem;transition:background-color .3s ease}
.gi-skill:hover{background:var(--soft)}
.gi-skill-row{display:flex;align-items:center;gap:.875rem}
.gi-skill-name{font-weight:600}
.gi-skill-pct{margin-left:auto;font-size:.8rem;font-weight:500}
.gi-tile{display:inline-flex;height:2.5rem;width:2.5rem;flex-shrink:0;align-items:center;justify-content:center;background:var(--paper);border:1px solid var(--line);color:var(--accent);transition:background-color .3s ease,color .3s ease,transform .35s cubic-bezier(.2,.7,.2,1)}
.gi-tile-letter{font-size:1rem;line-height:1}
.gi-tile-glyph{font-size:1.125rem;line-height:1}
.gi-tile-glyph-lg{font-size:1.25rem;line-height:1}
.gi-skill:hover .gi-tile{background:var(--accent);color:var(--accent-ink);transform:rotate(-6deg) scale(1.06)}
.gi-track{position:relative;height:3px;width:100%;margin-top:1.25rem;background:var(--line)}
.gi-bar{position:relative;height:100%;width:var(--w,0%);background:var(--accent)}
.gi-bar::after{content:'';position:absolute;right:0;top:50%;height:11px;width:11px;transform:translate(50%,-50%);background:var(--ink)}
.gi-js [data-reveal] .gi-bar{width:0;transition:width 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s)}
.gi-js [data-reveal].is-in .gi-bar{width:var(--w,0%)}
@property --n{syntax:'<integer>';inherits:false;initial-value:0}
.gi-count{--n:var(--p,0);counter-reset:n var(--n);font-variant-numeric:tabular-nums}
.gi-count::after{content:counter(n) '%'}
.gi-js [data-reveal] .gi-count{--n:0}
.gi-js [data-reveal].is-in .gi-count{animation:gi-count 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s) forwards}

/* ---------- Projects ---------- */
.gi-proj-head{display:grid;gap:1.5rem;margin:1.5rem 0 4rem}
@media(min-width:1024px){.gi-proj-head{grid-template-columns:repeat(12,minmax(0,1fr));align-items:end}}
.gi-proj-desc{white-space:pre-line;line-height:1.7}
.gi-project-grid{display:grid;gap:3.5rem 1.5rem}
.gi-project-grid>li{min-width:0}
@media(min-width:768px){.gi-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.gi-project-grid>.gi-feature{grid-column:span 2/span 2}}
@media(min-width:1280px){.gi-project-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.gi-project{display:flex;height:100%;flex-direction:column}
.gi-proj-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;border-top:1px solid var(--ink);padding-top:.75rem;font-size:.72rem;text-transform:uppercase}
.gi-flag{background:var(--accent);color:var(--accent-ink);padding:.15rem .6rem;font-weight:500}
.gi-media{position:relative;display:block;overflow:hidden;background:var(--surface);border:1px solid var(--line);isolation:isolate;transition:border-color .3s ease}
.gi-media-std{aspect-ratio:4/3}
.gi-media-wide{aspect-ratio:16/9}
.gi-project:hover .gi-media{border-color:var(--accent)}
.gi-media img{width:100%;height:100%;object-fit:cover}
.gi-media img,.gi-tone{transition:transform .9s cubic-bezier(.2,.7,.2,1)}
.gi-project:hover .gi-media img,.gi-project:hover .gi-tone{transform:scale(1.05)}
.gi-tone{display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:6rem;line-height:1}
.gi-tone-0{background:var(--accent);color:var(--accent-ink)}
.gi-tone-1{background:var(--surface);color:var(--ink)}
.gi-tone-2{background:var(--ink);color:var(--paper)}
.gi-go{position:absolute;right:0;top:0;display:inline-flex;height:3rem;width:3rem;align-items:center;justify-content:center;background:var(--accent);color:var(--accent-ink);opacity:0;transform:translate(.5rem,-.5rem);transition:opacity .35s ease,transform .45s cubic-bezier(.2,.7,.2,1)}
.gi-project:hover .gi-go,.gi-media:focus-visible .gi-go{opacity:1;transform:none}
.gi-proj-body{display:flex;flex:1;flex-direction:column;padding-top:1.25rem}
.gi-proj-name{font-size:1.5rem;line-height:1.25}
.gi-underline{background:linear-gradient(currentColor,currentColor) left 96%/0% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1)}
.gi-project:hover .gi-underline{background-size:100% 2px}
.gi-proj-text{margin-top:.75rem;line-height:1.65}
.gi-tags{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
.gi-repo{margin-top:auto;padding-top:1.25rem}
.gi-repo .gi-textlink{font-size:.8rem;text-transform:uppercase}

/* ---------- Services ---------- */
.gi-row{position:relative;display:grid;align-items:center;gap:1rem;border-bottom:1px solid var(--line);padding:1.75rem .5rem;transition:background-color .35s ease,padding .4s cubic-bezier(.2,.7,.2,1)}
@media(min-width:768px){.gi-row{grid-template-columns:repeat(12,minmax(0,1fr))}.gi-row>.gi-m1{grid-column:span 1/span 1}.gi-row>.gi-m5{grid-column:span 5/span 5}}
.gi-row:hover{background:var(--surface);padding-inline:1.25rem}
.gi-row-num{display:none;font-size:.875rem;color:var(--accent)}
@media(min-width:768px){.gi-row-num{display:block}}
.gi-row-main{display:flex;align-items:center;gap:1.25rem}
.gi-row-title{font-size:1.5rem;line-height:1.25;transition:color .3s ease}
@media(min-width:640px){.gi-row-title{font-size:1.75rem}}
.gi-row:hover .gi-row-title{color:var(--accent)}
.gi-row:hover .gi-tile{background:var(--accent);color:var(--accent-ink);transform:rotate(-6deg) scale(1.06)}
.gi-row-text{line-height:1.65}
@media(min-width:768px){.gi-row-text{font-size:1rem}}
.gi-row-arrow{display:none;justify-self:end;color:var(--accent);opacity:0;transform:translate(-.75rem,.75rem);transition:opacity .3s ease,transform .4s cubic-bezier(.2,.7,.2,1)}
@media(min-width:768px){.gi-row-arrow{display:block}}
.gi-row:hover .gi-row-arrow{opacity:1;transform:none}

/* ---------- Contact ---------- */
.gi-contact-section{padding-top:6rem}
.gi-contact{position:relative;overflow:hidden;background:var(--surface);border-top:1px solid var(--ink);border-bottom:1px solid var(--line)}
.gi-contact::before{content:'';position:absolute;right:-6rem;top:-6rem;height:22rem;width:22rem;border:1px solid var(--accent);opacity:.35;transform:rotate(18deg);pointer-events:none;animation:gi-drift 18s ease-in-out infinite}
.gi-contact::after{content:'';position:absolute;right:-2rem;top:-2rem;height:12rem;width:12rem;background:var(--accent);opacity:.1;transform:rotate(18deg);pointer-events:none;animation:gi-drift 24s ease-in-out infinite reverse}
.gi-contact-in{position:relative;z-index:10;display:grid;gap:3.5rem;margin-inline:auto;width:100%;max-width:1240px;padding:5rem 1.25rem}
@media(min-width:640px){.gi-contact-in{padding-inline:2rem}}
@media(min-width:1024px){.gi-contact-in{grid-template-columns:repeat(12,minmax(0,1fr));gap:2.5rem;padding-block:7rem}}
.gi-contact-title{margin-top:1.5rem;font-size:clamp(2.75rem,8vw,6.5rem);line-height:.93;overflow-wrap:anywhere}
.gi-contact-text{max-width:34rem;margin-top:1.75rem;white-space:pre-line;font-size:1.125rem;line-height:1.7}
.gi-contact-info{margin-top:2.5rem}
.gi-contact-info>*+*{margin-top:1.25rem}
.gi-bigmail{display:inline-flex;max-width:100%;align-items:center;gap:.75rem;word-break:break-all;font-size:1.5rem}
@media(min-width:640px){.gi-bigmail{font-size:2.25rem}}
.gi-underline-contact{background:linear-gradient(currentColor,currentColor) left bottom/100% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1);padding-bottom:4px}
.gi-bigmail:hover .gi-underline-contact{background-size:0% 2px;background-position:right bottom}
.gi-contact-meta{display:flex;flex-wrap:wrap;gap:.75rem 2rem;font-size:.85rem;text-transform:uppercase}
.gi-contact-meta>span{display:inline-flex;align-items:center;gap:.5rem}
.gi-contact-link{display:inline-flex;align-items:center;gap:.5rem}
.gi-contact-link:hover{text-decoration:underline;text-underline-offset:4px}
.gi-contact-socials{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:2rem}
.gi-social{display:inline-flex;align-items:center;gap:.45rem;border:1px solid var(--line);background:var(--paper);padding:.5rem .9rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.75rem;font-weight:500;text-transform:uppercase;letter-spacing:.05em;transition:background-color .3s ease,color .3s ease,border-color .3s ease,transform .3s ease}
.gi-social:hover{background:var(--accent);color:var(--accent-ink);border-color:var(--accent);transform:translateY(-2px)}
.gi-form{padding:1.5rem;border:1px solid var(--line);background:var(--paper);box-shadow:0 30px 60px -36px #000000aa}
@media(min-width:640px){.gi-form{padding:2rem}}
.gi-field{display:block;margin-bottom:1.5rem}
.gi-label-text{display:block;margin-bottom:.5rem;font-size:.72rem;font-weight:500;text-transform:uppercase}
.gi-input{width:100%;border:0;border-bottom:1px solid var(--line);background:transparent;padding:.7rem 0;font:inherit;font-weight:500;color:var(--ink);border-radius:0;transition:border-color .25s ease,box-shadow .25s ease}
.gi-input:hover{border-color:var(--muted)}
.gi-input:focus{outline:none;border-color:var(--accent);box-shadow:0 1px 0 var(--accent)}
textarea.gi-input{resize:vertical}
.gi-sent{display:flex;align-items:center;gap:.5rem;font-size:.875rem;font-weight:600;animation:gi-pop .5s cubic-bezier(.2,.8,.2,1) both}
.gi-hidden-frame{display:none}
.gi-foot{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;margin-inline:auto;max-width:1240px;padding:2rem 1.25rem;font-size:.875rem}
@media(min-width:640px){.gi-foot{padding-inline:2rem}}
.gi-foot-copy{font-size:.78rem;text-transform:uppercase}

/* ---------- Scroll reveal (class is added by script.js, so no-JS output stays visible) ---------- */
.gi-js [data-reveal]{opacity:0;transform:translateY(22px);transition:opacity .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms),transform .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms)}
.gi-js [data-reveal].is-in{opacity:1;transform:none}
.gi-js [data-reveal]:not(.is-in) .gi-h2,.gi-js [data-reveal]:not(.is-in).gi-h2{letter-spacing:.005em}
.gi-js [data-reveal].is-in .gi-frame{animation:gi-wipe 1.1s cubic-bezier(.7,0,.2,1) backwards}
.gi-js [data-reveal]:not(.is-in) .gi-hl{opacity:0}
.gi-js [data-reveal].is-in .gi-hl{animation:gi-slide-x .8s cubic-bezier(.2,.7,.2,1) calc(var(--k,0)*90ms + 150ms) backwards}
.gi-js [data-reveal].is-in .gi-hl .gi-tick{animation:gi-tick .6s cubic-bezier(.3,1.6,.4,1) calc(var(--k,0)*90ms + 450ms) backwards}
.gi-js [data-reveal].is-in .gi-tile{animation:gi-tile .7s cubic-bezier(.3,1.5,.4,1) calc(var(--d,0ms) + .15s) backwards}
.gi-js [data-reveal].is-in .gi-media{animation:gi-wipe 1.2s cubic-bezier(.7,0,.2,1) .1s backwards}
.gi-js [data-reveal].is-in .gi-media img{animation:gi-settle 1.6s cubic-bezier(.2,.7,.2,1) .1s backwards}
.gi-js.gi-root{animation:gi-enter .7s ease both}
@keyframes gi-rise{to{transform:translateY(0)}}
@keyframes gi-enter{from{opacity:0}}
@keyframes gi-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes gi-pop{from{opacity:0;transform:translateY(8px) scale(.96)}}
@keyframes gi-count{to{--n:var(--p)}}
@keyframes gi-wipe{from{clip-path:inset(0 0 100% 0)}}
@keyframes gi-slide-x{from{opacity:0;transform:translateX(-20px)}}
@keyframes gi-tick{from{transform:scale(0) rotate(-90deg)}}
@keyframes gi-tile{from{transform:scale(.4) rotate(-20deg);opacity:0}}
@keyframes gi-settle{from{transform:scale(1.25)}}
@keyframes gi-drift{50%{transform:translate(-2.5rem,2rem) rotate(28deg)}}

/* ---------- Reduced motion ---------- */
@media(prefers-reduced-motion:reduce){
html{scroll-behavior:auto}
.gi-root *,.gi-root *::before,.gi-root *::after{animation:none!important;transition:none!important}
.gi-js .gi-word,.gi-js [data-reveal],.gi-js [data-reveal]:not(.is-in) .gi-hl{opacity:1;transform:none}
.gi-js [data-reveal] .gi-count{--n:var(--p,0)}
.gi-js [data-reveal] .gi-bar{width:var(--w,0%)}
.gi-js [data-reveal]:not(.is-in) .gi-kicker::before{transform:none}
.gi-js [data-reveal]:not(.is-in) .gi-h2,.gi-js [data-reveal]:not(.is-in).gi-h2{letter-spacing:-.05em}
}
CSS;
    }
}
