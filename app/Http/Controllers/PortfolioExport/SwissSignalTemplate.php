<?php

namespace App\Http\Controllers\PortfolioExport;

trait SwissSignalTemplate
{
    private function renderSwissSignalPage(string $title, array $content, array $assets, ?array $googleForm): string
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
        $icon = fn (string $name, int $size = 16, string $class = '', string $stroke = '2'): string => $this->swissSignalIcon($name, $size, $class, $stroke);
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
            $projects[] = [
                'title' => $projectTitle,
                'description' => $clean($item['description'] ?? null),
                'image' => $image !== null ? $safe($image, '') : '',
                'repo' => $repoRaw !== null ? $safe($repoRaw, '') : '',
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

        // Contact form: this template shows the form whenever it is enabled. With a valid Google Form it posts
        // there; otherwise it falls back to composing an email to the contact address (handled in script.js).
        $formMode = $googleForm !== null ? 'google' : ($emailOk ? 'mailto' : null);
        $formReady = $flag($contact, 'contact_form_enabled', true) && $formMode !== null;

        $navLinks = array_values(array_filter([
            ['id' => 'about', 'label' => 'About', 'present' => $hasAbout],
            ['id' => 'journey', 'label' => 'Journey', 'present' => $hasJourney],
            ['id' => 'skills', 'label' => 'Skills', 'present' => $hasSkills],
            ['id' => 'projects', 'label' => 'Work', 'present' => $hasProjects],
            ['id' => 'services', 'label' => 'Services', 'present' => $hasServices],
        ], static fn (array $definition): bool => $definition['present']));

        // Shared section header: number + rule + subtitle.
        $head = static fn (string $id, ?string $subtitle, string $extra = ''): string => '<div class="sg-head"><span class="sg-index sg-mono'.$extra.'">'.e($numberOf($id)).'</span>'
            .($subtitle !== null ? '<p class="sg-kicker'.$extra.'">'.e($subtitle).'</p>' : '').'</div>';

        /* ---------------------------------------------------------------
         | Markup
         * ------------------------------------------------------------- */
        $html = '<div class="sg-root">';

        // Navbar
        $html .= '<header class="sg-header '.($stickyNav ? 'is-fixed' : 'is-absolute').'"><div class="sg-progress" data-progress aria-hidden="true"></div>';
        $html .= '<nav class="sg-nav" data-nav aria-label="Primary"><div class="sg-nav-inner">';
        if ($logoImage !== '' || $logoText !== null) {
            $html .= '<a href="#header" class="sg-logo" data-scroll-link>';
            if ($logoImage !== '') {
                $html .= '<img src="'.$escape($logoImage).'" alt="'.$escape($logoText ?? '').'" class="sg-logo-img">';
            }
            if ($logoText !== null) {
                $html .= '<span class="sg-display sg-logo-text">'.$escape($logoText).'</span>';
            }
            $html .= '</a>';
        } else {
            $html .= '<span></span>';
        }
        if ($navLinks !== []) {
            $html .= '<ul class="sg-navlinks">';
            foreach ($navLinks as $link) {
                $html .= '<li><a href="#'.$escape($link['id']).'" class="sg-navlink" data-active="false" data-section-link="'.$escape($link['id']).'" data-scroll-link><span class="sg-mono">'.$escape($numberOf($link['id'])).'</span>'.$escape($link['label']).'</a></li>';
            }
            $html .= '</ul>';
        }
        if ($hasContact) {
            $html .= '<a href="#contact" class="sg-btn sg-btn-primary sg-btn-sm" data-scroll-link>Contact'.$icon('arrow-up-right', 16, 'sg-arrow').'</a>';
        } else {
            $html .= '<span></span>';
        }
        $html .= '</div></nav></header>';

        // Hero
        if ($hasHeader) {
            $html .= '<section id="header" class="sg-hero" data-page-section><span id="home" class="sg-home-anchor" aria-hidden="true"></span>';
            if ($heroBackground !== '') {
                $html .= '<img src="'.$escape($heroBackground).'" alt="" class="sg-hero-bg">';
            }
            $html .= '<div class="sg-gridlines" aria-hidden="true"></div><div class="sg-hero-grid"><div class="sg-hero-main">';
            if ($greeting !== null) {
                $html .= '<p data-reveal class="sg-label" style="--d: 0ms"><span class="sg-pulse" aria-hidden="true"></span>'.$escape($greeting).'</p>';
            }
            if ($heroName !== null) {
                $html .= '<h1 class="sg-display sg-hero-name"><span class="sr-only">'.$escape($heroName).'</span>';
                foreach ($heroWords as $index => $word) {
                    $html .= '<span class="sg-mask" aria-hidden="true"><span class="sg-word" style="--i: '.$index.'">'.$escape($word).'</span></span>';
                }
                $html .= '</h1>';
            }
            $html .= '<div class="sg-hero-sub">';
            if ($role !== null) {
                $html .= '<p data-reveal class="sg-mono sg-role sg-m5" style="--d: 380ms">'.$icon('arrow-down-right', 24, 'sg-role-arrow').$escape($role).'</p>';
            }
            $html .= '<div class="sg-m7">';
            if ($heroDescription !== null) {
                $html .= '<p data-reveal class="sg-muted sg-hero-desc" style="--d: 460ms">'.$escape($heroDescription).'</p>';
            }
            if ($heroPrimary !== null || $heroSecondary !== null) {
                $html .= '<div data-reveal class="sg-actions" style="--d: 540ms">';
                if ($heroPrimary !== null) {
                    $html .= '<a href="'.$url($heroPrimary['url']).'" class="sg-btn sg-btn-primary" data-scroll-link>'.$escape($heroPrimary['text']).$icon('arrow-up-right', 16, 'sg-arrow').'</a>';
                }
                if ($heroSecondary !== null) {
                    $html .= '<a href="'.$url($heroSecondary['url']).'" class="sg-btn sg-btn-ghost" data-scroll-link>'.$escape($heroSecondary['text']).'</a>';
                }
                $html .= '</div>';
            }
            if ($heroSocials !== []) {
                $html .= '<ul data-reveal class="sg-icons" style="--d: 620ms">';
                foreach ($heroSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($social['label']).'" title="'.$escape($social['label']).'" class="sg-icon-btn">'.$icon($socialIcon($social['platform']), 18).'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div></div></div>';

            $html .= '<div class="sg-hero-aside"><figure data-reveal class="sg-portrait" style="--d: 250ms"><div class="sg-offset" aria-hidden="true"></div><div class="sg-photo">';
            if ($heroImage !== '') {
                $html .= '<img src="'.$escape($heroImage).'" alt="'.$escape($heroName ?? '').'">';
            } elseif ($heroInitials !== '') {
                $html .= '<span class="sg-display sg-initials">'.$escape($heroInitials).'</span>';
            }
            $html .= '</div>';
            if ($location !== null) {
                $html .= '<figcaption class="sg-caption sg-mono">'.$icon('map-pin', 14, 'sg-accent').$escape($location).'</figcaption>';
            }
            $html .= '</figure></div></div></section>';
        }

        // About
        if ($hasAbout) {
            $html .= '<section id="about" class="sg-band" data-page-section><div class="sg-in sg-block"><div data-reveal>'.$head('about', $text($about, 'about_subtitle')).'</div>';
            $html .= '<div class="sg-about-grid">';
            if ($aboutImage !== '') {
                $html .= '<div data-reveal class="sg-c5"><div class="sg-frame"><img src="'.$escape($aboutImage).'" alt="'.$escape($text($about, 'about_title') ?? '').'" loading="lazy"></div></div>';
            }
            $html .= '<div class="'.($aboutImage !== '' ? 'sg-c7' : 'sg-c10').'"><div data-reveal>';
            if ($text($about, 'about_title') !== null) {
                $html .= '<h2 class="sg-display sg-h2">'.$escape($text($about, 'about_title')).'</h2>';
            }
            if ($text($about, 'about_description') !== null) {
                $html .= '<p class="sg-muted sg-prose">'.$escape($text($about, 'about_description')).'</p>';
            }
            $html .= '</div>';
            if ($highlights !== []) {
                $html .= '<ul data-reveal class="sg-hl-list">';
                foreach ($highlights as $index => $highlight) {
                    $html .= '<li class="sg-hl" style="--k: '.$index.'"><span class="sg-tick">'.$icon('check', 14, '', '3').'</span><span><span class="sg-hl-title">'.$escape($highlight['title']).'</span>';
                    if ($highlight['description'] !== null) {
                        $html .= '<span class="sg-muted sg-hl-desc">'.$escape($highlight['description']).'</span>';
                    }
                    $html .= '</span></li>';
                }
                $html .= '</ul>';
            }
            if ($cvFile !== '' || $aboutCta !== null) {
                $html .= '<div data-reveal class="sg-actions sg-actions-lg">';
                if ($cvFile !== '') {
                    $html .= '<a href="'.$escape($cvFile).'" download target="_blank" rel="noopener" class="sg-btn sg-btn-primary">'.$icon('download', 16).'Download CV</a>';
                }
                if ($aboutCta !== null) {
                    $html .= '<a href="'.$url($aboutCta['url']).'" class="sg-btn '.($cvFile !== '' ? 'sg-btn-ghost' : 'sg-btn-primary').'" data-scroll-link>'.$escape($aboutCta['text']).$icon('arrow-up-right', 16, 'sg-arrow').'</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></div></section>';
        }

        // Journey
        if ($hasJourney) {
            $html .= '<section id="journey" data-page-section><div class="sg-in sg-block"><div data-reveal>'.$head('journey', $text($journeyData, 'journey_subtitle')).'</div>';
            if ($text($journeyData, 'journey_title') !== null) {
                $html .= '<h2 data-reveal class="sg-display sg-h2 sg-mt6 sg-maxw4xl">'.$escape($text($journeyData, 'journey_title')).'</h2>';
            }
            $html .= '<ol class="sg-journey-list">';
            foreach ($journey as $index => $item) {
                $html .= '<li data-reveal style="--d: '.(($index % 4) * 70).'ms" class="sg-tl">';
                if ($item['period'] !== null) {
                    $html .= '<p class="sg-mono sg-period sg-m3">'.$escape($item['period']).'</p>';
                }
                $html .= '<div class="sg-m4">';
                if ($item['title'] !== null) {
                    $html .= '<h3 class="sg-display sg-item-title">'.$escape($item['title']).'</h3>';
                }
                if ($item['company'] !== null) {
                    $html .= '<p class="sg-company">'.$escape($item['company']).'</p>';
                }
                $html .= '</div>';
                if ($item['description'] !== null) {
                    $html .= '<p class="sg-muted sg-item-desc sg-m5">'.$escape($item['description']).'</p>';
                }
                $html .= '</li>';
            }
            $html .= '</ol></div></section>';
        }

        // Skills
        if ($hasSkills) {
            $html .= '<section id="skills" class="sg-band" data-page-section><div class="sg-in sg-grid sg-grid-skills"><div class="sg-c4"><div data-reveal class="sg-sticky">'.$head('skills', $text($skillsData, 'skills_subtitle'));
            if ($text($skillsData, 'skills_title') !== null) {
                $html .= '<h2 class="sg-display sg-h2 sg-mt6">'.$escape($text($skillsData, 'skills_title')).'</h2>';
            }
            if ($text($skillsData, 'skills_description') !== null) {
                $html .= '<p class="sg-muted sg-skills-desc">'.$escape($text($skillsData, 'skills_description')).'</p>';
            }
            $html .= '</div></div><ul class="sg-skill-list">';
            foreach ($skills as $index => $skill) {
                $html .= '<li data-reveal style="--d: '.(($index % 6) * 50).'ms" class="sg-skill"><div class="sg-skill-row">';
                if ($showIcon) {
                    $skillIcon = $iconFor($skill['icon']);
                    $html .= '<span class="sg-tile">';
                    if ($skillIcon !== null) {
                        $html .= $icon($skillIcon, 18);
                    } elseif ($isGlyph($skill['icon'])) {
                        $html .= '<span class="sg-tile-glyph" aria-hidden="true">'.$escape($skill['icon']).'</span>';
                    } else {
                        $html .= '<span class="sg-display sg-tile-letter" aria-hidden="true">'.$escape(mb_strtoupper(mb_substr($skill['name'], 0, 1))).'</span>';
                    }
                    $html .= '</span>';
                }
                $html .= '<h3 class="sg-skill-name">'.$escape($skill['name']).'</h3>';
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<span class="sg-count sg-mono sg-muted sg-skill-pct" style="--p: '.(int) round($skill['percent']).'" aria-hidden="true"></span>';
                } elseif ($showLevel && $skill['label'] !== null) {
                    $html .= '<span class="sg-pill sg-pill-end">'.$escape($skill['label']).'</span>';
                }
                $html .= '</div>';
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<div class="sg-track" role="progressbar" aria-valuenow="'.(int) round($skill['percent']).'" aria-valuemin="0" aria-valuemax="100" aria-label="'.$escape($skill['name']).'"><div class="sg-bar" style="--w: '.$num($skill['percent']).'%"></div></div>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></div></section>';
        }

        // Projects
        if ($hasProjects) {
            $html .= '<section id="projects" class="sg-section" data-page-section><div class="sg-in sg-block"><div data-reveal>'.$head('projects', $text($projectData, 'projects_subtitle')).'</div>';
            $html .= '<div data-reveal class="sg-proj-head">';
            if ($text($projectData, 'projects_title') !== null) {
                $html .= '<h2 class="sg-display sg-h2 sg-c8">'.$escape($text($projectData, 'projects_title')).'</h2>';
            }
            if ($text($projectData, 'projects_description') !== null) {
                $html .= '<p class="sg-muted sg-proj-desc sg-c4">'.$escape($text($projectData, 'projects_description')).'</p>';
            }
            $html .= '</div><ul class="sg-project-grid">';
            foreach ($projects as $index => $project) {
                $featured = $featureFirst && $index === 0;
                $html .= '<li data-reveal'.($featured ? ' class="sg-feature"' : '').' style="--d: '.(($index % 3) * 80).'ms"><article class="sg-project">';
                $html .= '<div class="sg-mono sg-proj-top"><span>'.$pad($index + 1).'</span>';
                if ($featured) {
                    $html .= '<span class="sg-flag">Featured</span>';
                }
                $html .= '</div>';
                $mediaClass = 'sg-media '.($featured ? 'sg-media-wide' : 'sg-media-std');
                if ($project['live'] !== '') {
                    $html .= '<a href="'.$escape($project['live']).'" target="_blank" rel="noopener noreferrer" aria-label="Open '.$escape($project['title']).'" class="'.$mediaClass.'">';
                } else {
                    $html .= '<div class="'.$mediaClass.'">';
                }
                if ($project['image'] !== '') {
                    $html .= '<img src="'.$escape($project['image']).'" alt="'.$escape($project['title']).'" loading="lazy">';
                } else {
                    $html .= '<div class="sg-display sg-tone sg-tone-'.($index % 3).'">'.$escape(mb_strtoupper(mb_substr($project['title'], 0, 1))).'</div>';
                }
                if ($project['live'] !== '') {
                    $html .= '<span class="sg-go" aria-hidden="true">'.$icon('arrow-up-right', 20).'</span>';
                }
                $html .= $project['live'] !== '' ? '</a>' : '</div>';

                $html .= '<div class="sg-proj-body"><h3 class="sg-display sg-proj-name"><span class="sg-underline">'.$escape($project['title']).'</span></h3>';
                if ($project['description'] !== null) {
                    $html .= '<p class="sg-muted sg-proj-text">'.$escape($project['description']).'</p>';
                }
                if ($project['tags'] !== []) {
                    $html .= '<ul class="sg-tags">';
                    foreach ($project['tags'] as $tag) {
                        $html .= '<li class="sg-pill">'.$escape($tag).'</li>';
                    }
                    $html .= '</ul>';
                }
                if ($project['repo'] !== '') {
                    $html .= '<div class="sg-repo"><a href="'.$escape($project['repo']).'" target="_blank" rel="noopener noreferrer" class="sg-textlink">'.$icon('code', 16).'Source code</a></div>';
                }
                $html .= '</div></article></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Services
        if ($hasServices) {
            $html .= '<section id="services" class="sg-band" data-page-section><div class="sg-in sg-block"><div data-reveal>'.$head('services', $text($servicesData, 'services_subtitle')).'</div>';
            if ($text($servicesData, 'services_title') !== null) {
                $html .= '<h2 data-reveal class="sg-display sg-h2 sg-mt6 sg-mb14 sg-maxw4xl">'.$escape($text($servicesData, 'services_title')).'</h2>';
            }
            $html .= '<ul class="sg-rule-top">';
            foreach ($services as $index => $service) {
                $html .= '<li data-reveal style="--d: '.(($index % 5) * 70).'ms"><div class="sg-row"><span class="sg-mono sg-row-num sg-m1">'.$pad($index + 1).'</span><div class="sg-row-main sg-m5"><span class="sg-tile sg-row-tile">';
                if (! $isGlyph($service['icon'])) {
                    $html .= $icon($iconFor($service['icon']) ?? 'sparkles', 20);
                } else {
                    $html .= '<span class="sg-tile-glyph-lg" aria-hidden="true">'.$escape($service['icon']).'</span>';
                }
                $html .= '</span><h3 class="sg-display sg-row-title">'.$escape($service['title']).'</h3></div>';
                if ($service['description'] !== null) {
                    $html .= '<p class="sg-muted sg-row-text sg-m5">'.$escape($service['description']).'</p>';
                }
                $html .= $icon('arrow-up-right', 24, 'sg-row-arrow').'</div></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Contact (+ footer)
        if ($hasContact) {
            $html .= '<section id="contact" class="sg-contact-section" data-page-section><div data-reveal class="sg-contact"><div class="sg-contact-in"><div class="sg-c7"><div data-reveal>'.$head('contact', $text($contact, 'contact_subtitle'), ' sg-current');
            if ($text($contact, 'contact_title') !== null) {
                $html .= '<h2 class="sg-display sg-contact-title">'.$escape($text($contact, 'contact_title')).'</h2>';
            }
            if ($text($contact, 'contact_description') !== null) {
                $html .= '<p class="sg-contact-text">'.$escape($text($contact, 'contact_description')).'</p>';
            }
            $html .= '</div>';
            if ($emailOk || $phoneOk || $location !== null) {
                $html .= '<div data-reveal class="sg-contact-info">';
                if ($emailOk) {
                    $html .= '<a href="'.$escape($emailHref).'" class="sg-bigmail sg-display">'.$icon('mail', 28).'<span class="sg-underline-contact">'.$escape($email).'</span>'.$icon('arrow-up-right', 28, 'sg-arrow').'</a>';
                }
                if ($phoneOk || $location !== null) {
                    $html .= '<div class="sg-contact-meta">';
                    if ($phoneOk) {
                        $html .= '<a href="'.$escape($phoneHref).'" class="sg-contact-link">'.$icon('phone', 16).$escape($phone).'</a>';
                    }
                    if ($location !== null) {
                        $html .= '<span>'.$icon('map-pin', 16).$escape($location).'</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            if ($contactSocials !== []) {
                $html .= '<ul data-reveal class="sg-contact-socials">';
                foreach ($contactSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" class="sg-social">'.$icon($socialIcon($social['platform']), 14).$escape($social['label']).$icon('arrow-up-right', 14, 'sg-arrow').'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><div class="sg-c5"><div data-reveal style="--d: 120ms">';

            if ($formReady) {
                if ($formMode === 'google') {
                    $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="sg-gform-frame" class="sg-form" data-contact-form data-mode="google">';
                    $nameField = 'entry.'.$escape($googleForm['name_entry']);
                    $emailField = 'entry.'.$escape($googleForm['email_entry']);
                    $messageField = 'entry.'.$escape($googleForm['message_entry']);
                } else {
                    $html .= '<form class="sg-form" data-contact-form data-mode="mailto" data-mailto="'.$escape($email).'">';
                    $nameField = 'name';
                    $emailField = 'email';
                    $messageField = 'message';
                }
                $html .= '
                <label class="sg-field"><span class="sg-mono sg-label-text">Name</span><input name="'.$nameField.'" type="text" required autocomplete="name" class="sg-input"></label>
                <label class="sg-field"><span class="sg-mono sg-label-text">Email</span><input name="'.$emailField.'" type="email" required autocomplete="email" class="sg-input"></label>
                <label class="sg-field"><span class="sg-mono sg-label-text">Message</span><textarea name="'.$messageField.'" rows="5" required class="sg-input"></textarea></label>
                <button type="submit" class="sg-btn sg-btn-primary sg-btn-block"><span data-submit-label>Send message</span>'.$icon('send', 16, 'sg-arrow').'</button>
                <p class="sg-sent" role="status" data-form-status hidden><span class="sg-tick">'.$icon('check', 14, '', '3').'</span><span data-status-text>Your message was submitted.</span></p>
            </form>';
            } elseif ($emailOk) {
                $html .= '<a href="'.$escape($emailHref).'" class="sg-btn sg-btn-light sg-btn-block sg-btn-xl">'.$icon('mail', 20).$escape($email).'</a>';
            }
            $html .= '</div>';
            if ($formReady && $formMode === 'google') {
                $html .= '<iframe name="sg-gform-frame" title="Form submission target" class="sg-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
            }
            $html .= '</div></div></div>';

            if ($heroName !== null || $logoText !== null) {
                $html .= '<footer class="sg-foot"><span class="sg-muted sg-mono">&copy; '.date('Y').' '.$escape($heroName ?? $logoText).'</span><a href="#header" class="sg-textlink" data-scroll-link>Back to top'.$icon('arrow-up-right', 16, 'sg-arrow sg-rot').'</a></footer>';
            }
            $html .= '</section>';
        }
        $html .= '</div>';

        return '<!doctype html>
<html lang="en" data-template="swiss-signal">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#eeebe4" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f0f0e" media="(prefers-color-scheme: dark)">
    <title>'.$escape($title).'</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    /** Inline Lucide-style icons (static, trusted markup only). */
    private function swissSignalIcon(string $name, int $size = 16, string $class = '', string $stroke = '2'): string
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

        return '<svg class="sg-ico'.($class !== '' ? ' '.$class : '').'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$stroke.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$body.'</svg>';
    }

    private function swissSignalStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap');
/* ---------- Reset (Tailwind-preflight equivalent, low specificity) ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;color-scheme:light dark;-webkit-text-size-adjust:100%}
body{margin:0;background:#eeebe4}
@media(prefers-color-scheme:dark){body{background:#0f0f0e}}
:where(.sg-root) :is(h1,h2,h3,p,ul,ol,figure){margin:0}
:where(.sg-root) :is(ul,ol){padding:0;list-style:none}
:where(.sg-root) :is(h1,h2,h3){font-size:inherit;font-weight:inherit}
:where(.sg-root) a{color:inherit;text-decoration:none}
:where(.sg-root) :is(button,input,textarea){font:inherit;color:inherit;letter-spacing:inherit}
:where(.sg-root) button{background:none;border:0;padding:0;cursor:pointer}
:where(.sg-root) img,:where(.sg-root) svg{display:block;max-width:100%}
.sg-root [hidden]{display:none!important}
.sg-ico{flex-shrink:0}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

/* ---------- Foundations + palette (light by default, dark via prefers-color-scheme) ---------- */
.sg-root{--paper:#EEEBE4;--soft:#E4E0D7;--surface:#F7F5F0;--ink:#121211;--muted:#121211A8;--line:#12121233;--accent:#E8431A;--accent-ink:#FFFFFF;background:var(--paper);color:var(--ink);font-family:'Inter Tight',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;transition:background-color .4s ease,color .4s ease}
@media(prefers-color-scheme:dark){.sg-root{--paper:#0F0F0E;--soft:#161614;--surface:#1C1C19;--ink:#F1EEE6;--muted:#F1EEE6A8;--line:#F1EEE62E;--accent:#FF6A3D;--accent-ink:#0F0F0E}}
.sg-root ::selection{background:var(--accent);color:#fff}
.sg-display{font-family:'Inter Tight',ui-sans-serif,system-ui,sans-serif;font-weight:700;letter-spacing:-.045em}
.sg-mono{font-family:'IBM Plex Mono',ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:0}
.sg-muted{color:var(--muted)}
.sg-accent{color:var(--accent)}
.sg-mt6{margin-top:1.5rem}
.sg-mb14{margin-bottom:3.5rem}
.sg-maxw4xl{max-width:56rem}
.sg-h2{font-size:clamp(2.5rem,5.6vw,4.75rem);line-height:.98;letter-spacing:-.05em;text-wrap:balance;transition:letter-spacing 1.2s cubic-bezier(.2,.7,.2,1)}
.sg-band{background:var(--soft);border-block:1px solid var(--line)}
.sg-root :where(a,button,input,textarea):focus-visible{outline:2px solid var(--accent);outline-offset:3px}
.sg-section{position:relative}
.sg-in{margin-inline:auto;width:100%;max-width:1240px;padding-inline:1.25rem}
@media(min-width:640px){.sg-in{padding-inline:2rem}}
.sg-block{padding-block:7rem}
.sg-rule-top{border-top:1px solid var(--ink)}

/* Section header: number + rule + subtitle */
.sg-head{display:flex;align-items:center;gap:1rem}
.sg-index{font-size:.8rem;font-weight:500;color:var(--accent)}
.sg-kicker{position:relative;display:inline-flex;align-items:center;gap:1rem;font-family:'IBM Plex Mono',ui-monospace,monospace;font-size:.8rem;font-weight:500;text-transform:uppercase;letter-spacing:.08em}
.sg-kicker::before{content:'';width:2.5rem;height:1px;background:currentColor;transform-origin:left;transition:transform .8s cubic-bezier(.2,.7,.2,1) .2s}
.sg-js [data-reveal]:not(.is-in) .sg-kicker::before{transform:scaleX(0)}

/* ---------- Layout helpers ---------- */
.sg-grid{display:grid;gap:3rem}
.sg-grid-skills{padding-block:7rem}
.sg-c4,.sg-c5,.sg-c7,.sg-c8,.sg-c10{min-width:0}
@media(min-width:1024px){
.sg-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:4rem}
.sg-c4{grid-column:span 4/span 4}.sg-c5{grid-column:span 5/span 5}.sg-c7{grid-column:span 7/span 7}.sg-c8{grid-column:span 8/span 8}.sg-c10{grid-column:span 10/span 10}
.sg-sticky{position:sticky;top:7rem}
}

/* ---------- Navbar ---------- */
.sg-header{position:fixed;inset:0 0 auto 0;z-index:50}
.sg-header.is-absolute{position:absolute}
.sg-progress{position:fixed;inset:0 0 auto 0;height:3px;background:var(--accent);transform:scaleX(0);transform-origin:left;pointer-events:none;z-index:60}
.sg-nav{border-bottom:1px solid transparent;transition:border-color .35s ease,background-color .35s ease}
.sg-nav.is-scrolled{border-color:var(--line);background:color-mix(in srgb,var(--paper) 85%,transparent);-webkit-backdrop-filter:blur(14px) saturate(1.3);backdrop-filter:blur(14px) saturate(1.3)}
.sg-nav-inner{display:flex;height:4rem;width:100%;max-width:1240px;margin-inline:auto;align-items:center;justify-content:space-between;gap:1rem;padding-inline:1.25rem}
@media(min-width:640px){.sg-nav-inner{padding-inline:2rem}}
.sg-logo{display:flex;min-width:0;align-items:center;gap:.75rem}
.sg-logo-img{height:1.75rem;width:auto;max-width:7rem;object-fit:contain}
.sg-logo-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:1.125rem}
.sg-navlinks{display:none;align-items:center;gap:1.75rem}
@media(min-width:768px){.sg-navlinks{display:flex}}
.sg-navlink{display:inline-flex;align-items:baseline;gap:.45rem;padding-block:.4rem;font-size:.92rem;font-weight:500;color:var(--muted);background:linear-gradient(var(--accent),var(--accent)) left bottom/0% 2px no-repeat;transition:color .25s ease,background-size .4s cubic-bezier(.2,.7,.2,1)}
.sg-navlink .sg-mono{font-size:.68rem;color:var(--accent)}
.sg-navlink:hover,.sg-navlink[data-active='true']{color:var(--ink)}
.sg-navlink[data-active='true']{background-size:100% 2px}

/* ---------- Buttons & small controls ---------- */
.sg-btn{position:relative;isolation:isolate;display:inline-flex;align-items:center;gap:.5rem;border-radius:0;padding:.95rem 1.5rem;font-size:.92rem;font-weight:600;line-height:1.2;cursor:pointer;border:1px solid transparent;background-repeat:no-repeat;background-position:left center;background-size:0% 100%;transition:background-size .5s cubic-bezier(.2,.7,.2,1),color .3s ease,transform .25s ease,border-color .3s ease}
.sg-btn:hover{background-size:100% 100%;transform:translate(-2px,-2px)}
.sg-btn:active{transform:translate(0,0) scale(.98)}
.sg-btn[disabled]{opacity:.7;pointer-events:none}
.sg-btn-primary{background-color:var(--accent);background-image:linear-gradient(var(--ink),var(--ink));color:var(--accent-ink)}
.sg-btn-primary:hover{color:var(--paper)}
.sg-btn-ghost{background-color:transparent;background-image:linear-gradient(var(--ink),var(--ink));border-color:var(--ink);color:var(--ink)}
.sg-btn-ghost:hover{color:var(--paper)}
.sg-btn-light{background-color:var(--paper);background-image:linear-gradient(var(--accent),var(--accent));color:var(--ink)}
.sg-btn-light:hover{color:var(--accent-ink)}
.sg-btn-sm{padding:.625rem 1rem;font-size:.8rem}
.sg-btn-block{width:100%;justify-content:center}
.sg-btn-xl{padding-block:1.25rem}
.sg-arrow{transition:transform .3s cubic-bezier(.2,.7,.2,1)}
.sg-rot{transform:rotate(-45deg)}
.sg-btn:hover .sg-arrow,.sg-bigmail:hover .sg-arrow,.sg-social:hover .sg-arrow,.sg-textlink:hover .sg-arrow{transform:translate(3px,-3px)}
.sg-icon-btn{display:inline-flex;height:2.75rem;width:2.75rem;align-items:center;justify-content:center;border:1px solid var(--ink);background:transparent;transition:transform .3s cubic-bezier(.2,.7,.2,1),background-color .25s ease,color .25s ease,border-color .25s ease}
.sg-icon-btn:hover{transform:translateY(-3px);background:var(--accent);color:var(--accent-ink);border-color:var(--accent)}
.sg-label{display:inline-flex;align-items:center;gap:.65rem;border:1px solid var(--line);background:var(--surface);padding:.5rem .9rem;font-family:'IBM Plex Mono',ui-monospace,monospace;font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.08em}
.sg-pulse{position:relative;height:.5rem;width:.5rem;background:var(--accent)}
.sg-pulse::after{content:'';position:absolute;inset:0;background:inherit;animation:sg-ping 2.2s cubic-bezier(0,0,.2,1) infinite}
.sg-pill{display:inline-block;border:1px solid var(--line);padding:.2rem .6rem;font-family:'IBM Plex Mono',ui-monospace,monospace;font-size:.72rem;font-weight:500;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.sg-pill-end{margin-left:auto}
.sg-tick{display:inline-flex;height:1.4rem;width:1.4rem;flex-shrink:0;align-items:center;justify-content:center;background:var(--accent);color:var(--accent-ink)}
.sg-textlink{display:inline-flex;align-items:center;gap:.4rem;font-weight:600;background:linear-gradient(currentColor,currentColor) left bottom/0% 1.5px no-repeat;transition:background-size .4s cubic-bezier(.2,.7,.2,1);padding-bottom:2px}
.sg-textlink:hover{background-size:100% 1.5px}

/* ---------- Hero ---------- */
.sg-hero{position:relative;isolation:isolate;display:flex;min-height:100vh;min-height:100svh;align-items:center;overflow:hidden}
.sg-home-anchor{position:absolute;top:0}
.sg-hero-bg{position:absolute;inset:0;z-index:-10;height:100%;width:100%;object-fit:cover;opacity:.1;filter:grayscale(1)}
@media(prefers-color-scheme:dark){.sg-hero-bg{opacity:.14}}
.sg-gridlines{position:absolute;inset:0;z-index:-10;background-image:linear-gradient(to right,var(--line) 1px,transparent 1px);background-size:calc(100%/12) 100%;opacity:.35;-webkit-mask-image:linear-gradient(to bottom,transparent,#000 25%,#000 75%,transparent);mask-image:linear-gradient(to bottom,transparent,#000 25%,#000 75%,transparent)}
.sg-hero-grid{display:grid;width:100%;max-width:1240px;margin-inline:auto;align-items:end;gap:3.5rem;padding:9rem 1.25rem 4rem}
@media(min-width:640px){.sg-hero-grid{padding-inline:2rem}}
@media(min-width:1024px){.sg-hero-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:2rem;padding-top:8rem}.sg-hero-main{grid-column:span 8/span 8}.sg-hero-aside{grid-column:span 4/span 4}}
.sg-hero-main,.sg-hero-aside{min-width:0}
.sg-hero-name{margin-top:2rem;font-size:clamp(3.5rem,11vw,9.5rem);line-height:.88;letter-spacing:-.06em;overflow-wrap:anywhere}
.sg-mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.12em;margin-bottom:-.12em;margin-right:.2em}
.sg-word{display:inline-block}
.sg-js .sg-word{transform:translateY(110%);animation:sg-rise 1s cubic-bezier(.2,.8,.2,1) forwards;animation-delay:calc(.15s + var(--i,0)*.1s)}
.sg-hero-sub{display:grid;gap:2rem;margin-top:2.5rem}
@media(min-width:768px){.sg-hero-sub{grid-template-columns:repeat(12,minmax(0,1fr))}.sg-hero-sub>.sg-m5{grid-column:span 5/span 5}.sg-hero-sub>.sg-m7{grid-column:span 7/span 7}}
.sg-role{font-size:.95rem;font-weight:500;text-transform:uppercase;line-height:1.5}
.sg-role-arrow{margin-bottom:.5rem;color:var(--accent)}
.sg-hero-desc{max-width:34rem;white-space:pre-line;font-size:1.125rem;line-height:1.65}
.sg-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2rem}
.sg-actions-lg{margin-top:2.5rem}
.sg-icons{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:2rem}
.sg-portrait{position:relative;margin-inline:auto;width:78%;max-width:22rem}
@media(min-width:640px){.sg-portrait{width:100%}}
@media(min-width:1024px){.sg-portrait{max-width:none}}
.sg-offset{position:absolute;inset:0;background:var(--accent);transform:translate(1rem,1rem);transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.sg-portrait:hover .sg-offset{transform:translate(.35rem,.35rem)}
.sg-photo{position:relative;aspect-ratio:4/5;overflow:hidden;background:var(--soft);border:1px solid var(--ink)}
.sg-photo img{width:100%;height:100%;object-fit:cover;filter:grayscale(1);transition:transform .9s ease-out,filter .9s ease-out}
.sg-photo:hover img{transform:scale(1.05);filter:grayscale(0)}
.sg-initials{display:flex;width:100%;height:100%;align-items:center;justify-content:center;background:var(--ink);font-size:8rem;color:var(--paper)}
.sg-caption{position:absolute;left:0;bottom:-1.9rem;display:inline-flex;align-items:center;gap:.45rem;font-size:.75rem;font-weight:500;text-transform:uppercase}

/* ---------- About ---------- */
.sg-about-grid{display:grid;align-items:start;gap:3.5rem;margin-top:3rem}
@media(min-width:1024px){.sg-about-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:4rem}}
.sg-frame{position:relative;margin-inline:auto;max-width:28rem;padding:.5rem;border:1px solid var(--ink);background:var(--surface);transition:transform .6s cubic-bezier(.2,.7,.2,1),box-shadow .6s cubic-bezier(.2,.7,.2,1)}
.sg-frame:hover{transform:translate(-4px,-4px);box-shadow:8px 8px 0 var(--accent)}
.sg-frame img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover}
.sg-prose{max-width:38rem;margin-top:2rem;white-space:pre-line;font-size:1.125rem;line-height:1.75}
.sg-hl-list{display:grid;margin-top:3rem;border-top:1px solid var(--line)}
@media(min-width:640px){.sg-hl-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
.sg-hl{display:flex;align-items:flex-start;gap:.9rem;border-bottom:1px solid var(--line);padding-block:1.25rem;transition:padding-left .35s cubic-bezier(.2,.7,.2,1)}
@media(min-width:640px){.sg-hl{padding-right:1.5rem}}
.sg-hl:hover{padding-left:.5rem}
.sg-hl-title{display:block;font-weight:600;line-height:1.375}
.sg-hl-desc{display:block;margin-top:.25rem;font-size:.93rem;line-height:1.625}

/* ---------- Journey ---------- */
.sg-journey-list{margin-top:3.5rem;border-top:1px solid var(--ink)}
.sg-tl{position:relative;display:grid;gap:.75rem;border-bottom:1px solid var(--line);padding-block:2rem;transition:background-color .35s ease,padding .4s cubic-bezier(.2,.7,.2,1)}
@media(min-width:768px){.sg-tl{grid-template-columns:repeat(12,minmax(0,1fr));gap:2rem}.sg-tl>.sg-m3{grid-column:span 3/span 3}.sg-tl>.sg-m4{grid-column:span 4/span 4}.sg-tl>.sg-m5{grid-column:span 5/span 5}}
.sg-tl::before{content:'';position:absolute;left:0;top:-1px;height:2px;width:100%;background:var(--accent);transform:scaleX(0);transform-origin:left;transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.sg-tl:hover::before{transform:scaleX(1)}
.sg-period{font-size:.8rem;font-weight:500;text-transform:uppercase;color:var(--accent)}
.sg-item-title{font-size:1.5rem;line-height:1.25}
.sg-company{margin-top:.375rem;font-weight:500}
.sg-item-desc{white-space:pre-line;line-height:1.7}

/* ---------- Skills ---------- */
.sg-skills-desc{margin-top:1.5rem;white-space:pre-line;line-height:1.7}
.sg-skill-list{display:grid;align-content:start;column-gap:3rem;min-width:0}
@media(min-width:640px){.sg-skill-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.sg-skill-list{grid-column:span 8/span 8}}
.sg-skill{padding-block:1.4rem;border-bottom:1px solid var(--line)}
.sg-skill-row{display:flex;align-items:center;gap:.875rem}
.sg-skill-name{font-weight:600}
.sg-skill-pct{margin-left:auto;font-size:.875rem;font-weight:500}
.sg-tile{display:inline-flex;height:2.5rem;width:2.5rem;flex-shrink:0;align-items:center;justify-content:center;background:var(--surface);border:1px solid var(--line);color:var(--accent);transition:background-color .3s ease,color .3s ease,transform .35s cubic-bezier(.2,.7,.2,1)}
.sg-tile-letter{font-size:1rem;line-height:1}
.sg-tile-glyph{font-size:1.125rem;line-height:1}
.sg-tile-glyph-lg{font-size:1.25rem;line-height:1}
.sg-skill:hover .sg-tile{background:var(--accent);color:var(--accent-ink);transform:rotate(-6deg) scale(1.06)}
.sg-track{position:relative;height:4px;width:100%;margin-top:1rem;background:color-mix(in srgb,var(--ink) 12%,transparent)}
.sg-bar{position:relative;height:100%;width:var(--w,0%);background:var(--accent)}
.sg-bar::after{content:'';position:absolute;right:0;top:50%;height:12px;width:12px;transform:translate(50%,-50%);background:var(--ink)}
.sg-js [data-reveal] .sg-bar{width:0;transition:width 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s)}
.sg-js [data-reveal].is-in .sg-bar{width:var(--w,0%)}
@property --n{syntax:'<integer>';inherits:false;initial-value:0}
.sg-count{--n:var(--p,0);counter-reset:n var(--n);font-variant-numeric:tabular-nums}
.sg-count::after{content:counter(n) '%'}
.sg-js [data-reveal] .sg-count{--n:0}
.sg-js [data-reveal].is-in .sg-count{animation:sg-count 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s) forwards}

/* ---------- Projects ---------- */
.sg-proj-head{display:grid;gap:1.5rem;margin:1.5rem 0 4rem}
@media(min-width:1024px){.sg-proj-head{grid-template-columns:repeat(12,minmax(0,1fr));align-items:end}}
.sg-proj-desc{white-space:pre-line;line-height:1.7}
.sg-project-grid{display:grid;gap:4rem 2rem}
.sg-project-grid>li{min-width:0}
@media(min-width:768px){.sg-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sg-project-grid>.sg-feature{grid-column:span 2/span 2}}
@media(min-width:1280px){.sg-project-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.sg-project{display:flex;height:100%;flex-direction:column}
.sg-proj-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;border-top:1px solid var(--ink);padding-top:.75rem;font-size:.75rem;text-transform:uppercase}
.sg-flag{background:var(--accent);color:var(--accent-ink);padding:.15rem .6rem;font-weight:500}
.sg-media{position:relative;display:block;overflow:hidden;background:var(--soft);border:1px solid var(--ink);isolation:isolate}
.sg-media-std{aspect-ratio:4/3}
.sg-media-wide{aspect-ratio:16/9}
.sg-media img{width:100%;height:100%;object-fit:cover}
.sg-media img,.sg-tone{transition:transform .9s cubic-bezier(.2,.7,.2,1),filter .6s ease}
.sg-project:hover .sg-media img,.sg-project:hover .sg-tone{transform:scale(1.05)}
.sg-tone{display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:6rem;line-height:1}
.sg-tone-0{background:var(--accent);color:var(--accent-ink)}
.sg-tone-1{background:var(--soft);color:var(--ink)}
.sg-tone-2{background:var(--ink);color:var(--paper)}
.sg-go{position:absolute;right:0;top:0;display:inline-flex;height:3rem;width:3rem;align-items:center;justify-content:center;background:var(--accent);color:var(--accent-ink);opacity:0;transform:translate(.5rem,-.5rem);transition:opacity .35s ease,transform .45s cubic-bezier(.2,.7,.2,1)}
.sg-project:hover .sg-go,.sg-media:focus-visible .sg-go{opacity:1;transform:none}
.sg-proj-body{display:flex;flex:1;flex-direction:column;padding-top:1.25rem}
.sg-proj-name{font-size:1.5rem;line-height:1.25}
.sg-underline{background:linear-gradient(currentColor,currentColor) left 96%/0% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1)}
.sg-project:hover .sg-underline{background-size:100% 2px}
.sg-proj-text{margin-top:.75rem;line-height:1.65}
.sg-tags{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
.sg-repo{margin-top:auto;padding-top:1.25rem}

/* ---------- Services ---------- */
.sg-row{position:relative;display:grid;align-items:center;gap:1rem;border-bottom:1px solid var(--line);padding:1.75rem .5rem;transition:background-color .35s ease,padding .4s cubic-bezier(.2,.7,.2,1)}
@media(min-width:768px){.sg-row{grid-template-columns:repeat(12,minmax(0,1fr))}.sg-row>.sg-m1{grid-column:span 1/span 1}.sg-row>.sg-m5{grid-column:span 5/span 5}}
.sg-row:hover{background:var(--surface);padding-inline:1.25rem}
.sg-row-num{display:none;font-size:.875rem;color:var(--accent)}
@media(min-width:768px){.sg-row-num{display:block}}
.sg-row-main{display:flex;align-items:center;gap:1.25rem}
.sg-row-title{font-size:1.5rem;line-height:1.25;transition:color .3s ease}
@media(min-width:640px){.sg-row-title{font-size:1.75rem}}
.sg-row:hover .sg-row-title{color:var(--accent)}
.sg-row:hover .sg-tile{background:var(--accent);color:var(--accent-ink);transform:rotate(-6deg) scale(1.06)}
.sg-row-text{line-height:1.65}
@media(min-width:768px){.sg-row-text{font-size:1.02rem}}
.sg-row-arrow{display:none;justify-self:end;color:var(--accent);opacity:0;transform:translate(-.75rem,.75rem);transition:opacity .3s ease,transform .4s cubic-bezier(.2,.7,.2,1)}
@media(min-width:768px){.sg-row-arrow{display:block}}
.sg-row:hover .sg-row-arrow{opacity:1;transform:none}

/* ---------- Contact ---------- */
.sg-contact-section{padding-top:6rem}
.sg-contact{position:relative;overflow:hidden;background:var(--ink);color:var(--paper)}
.sg-contact .sg-index,.sg-contact .sg-kicker{color:currentColor}
.sg-contact::before{content:'';position:absolute;right:-6rem;top:-6rem;height:22rem;width:22rem;background:var(--accent);opacity:.16;transform:rotate(18deg);pointer-events:none;animation:sg-drift 18s ease-in-out infinite}
.sg-contact-in{position:relative;z-index:10;display:grid;gap:3.5rem;margin-inline:auto;width:100%;max-width:1240px;padding:5rem 1.25rem}
@media(min-width:640px){.sg-contact-in{padding-inline:2rem}}
@media(min-width:1024px){.sg-contact-in{grid-template-columns:repeat(12,minmax(0,1fr));gap:3rem;padding-block:7rem}}
.sg-contact-title{margin-top:1.5rem;font-size:clamp(2.75rem,8vw,6.5rem);line-height:.93;overflow-wrap:anywhere}
.sg-contact-text{max-width:34rem;margin-top:1.75rem;white-space:pre-line;font-size:1.125rem;line-height:1.7;opacity:.75}
.sg-contact-info{margin-top:2.5rem}
.sg-contact-info>*+*{margin-top:1.25rem}
.sg-bigmail{display:inline-flex;max-width:100%;align-items:center;gap:.75rem;word-break:break-all;font-size:1.5rem}
@media(min-width:640px){.sg-bigmail{font-size:2.25rem}}
.sg-underline-contact{background:linear-gradient(currentColor,currentColor) left bottom/100% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1);padding-bottom:4px}
.sg-bigmail:hover .sg-underline-contact{background-size:0% 2px;background-position:right bottom}
.sg-contact-meta{display:flex;flex-wrap:wrap;gap:.75rem 2rem;font-weight:500}
.sg-contact-meta>span{display:inline-flex;align-items:center;gap:.5rem}
.sg-contact-link{display:inline-flex;align-items:center;gap:.5rem;opacity:.9}
.sg-contact-link:hover{opacity:1;text-decoration:underline;text-underline-offset:4px}
.sg-contact-socials{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:2rem}
.sg-social{display:inline-flex;align-items:center;gap:.45rem;border:1px solid color-mix(in srgb,currentColor 45%,transparent);padding:.5rem .9rem;font-size:.88rem;font-weight:600;transition:background-color .3s ease,color .3s ease,border-color .3s ease,transform .3s ease}
.sg-social:hover{background:var(--accent);color:var(--accent-ink);border-color:var(--accent);transform:translateY(-2px)}
.sg-form{padding:1.5rem;border:1px solid color-mix(in srgb,var(--paper) 30%,transparent);background:color-mix(in srgb,var(--paper) 6%,transparent)}
@media(min-width:640px){.sg-form{padding:2rem}}
.sg-field{display:block;margin-bottom:1.5rem}
.sg-label-text{display:block;margin-bottom:.5rem;font-size:.75rem;font-weight:500;text-transform:uppercase}
.sg-input{width:100%;border:0;border-bottom:1px solid color-mix(in srgb,var(--paper) 40%,transparent);border-radius:0;background:transparent;padding:.7rem 0;font:inherit;font-weight:500;color:var(--paper);transition:border-color .25s ease,box-shadow .25s ease}
.sg-input:hover{border-color:var(--paper)}
.sg-input:focus{outline:none;border-color:var(--accent);box-shadow:0 2px 0 var(--accent)}
textarea.sg-input{resize:vertical}
.sg-sent{display:flex;align-items:center;gap:.5rem;font-size:.875rem;font-weight:600;animation:sg-pop .5s cubic-bezier(.2,.8,.2,1) both}
.sg-hidden-frame{display:none}
.sg-foot{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;margin-inline:auto;max-width:1240px;padding:2rem 1.25rem;font-size:.875rem}
@media(min-width:640px){.sg-foot{padding-inline:2rem}}

/* ---------- Scroll reveal (class is added by script.js, so no-JS output stays visible) ---------- */
.sg-js [data-reveal]{opacity:0;transform:translateY(22px);transition:opacity .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms),transform .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms)}
.sg-js [data-reveal].is-in{opacity:1;transform:none}
.sg-js [data-reveal]:not(.is-in) .sg-h2,.sg-js [data-reveal]:not(.is-in).sg-h2{letter-spacing:.005em}
.sg-js [data-reveal].is-in .sg-frame{animation:sg-wipe 1.1s cubic-bezier(.7,0,.2,1) backwards}
.sg-js [data-reveal]:not(.is-in) .sg-hl{opacity:0}
.sg-js [data-reveal].is-in .sg-hl{animation:sg-slide-x .8s cubic-bezier(.2,.7,.2,1) calc(var(--k,0)*90ms + 150ms) backwards}
.sg-js [data-reveal].is-in .sg-hl .sg-tick{animation:sg-tick .6s cubic-bezier(.3,1.6,.4,1) calc(var(--k,0)*90ms + 450ms) backwards}
.sg-js [data-reveal].is-in .sg-tile{animation:sg-tile .7s cubic-bezier(.3,1.5,.4,1) calc(var(--d,0ms) + .15s) backwards}
.sg-js [data-reveal].is-in .sg-media{animation:sg-wipe 1.2s cubic-bezier(.7,0,.2,1) .1s backwards}
.sg-js [data-reveal].is-in .sg-media img{animation:sg-settle 1.6s cubic-bezier(.2,.7,.2,1) .1s backwards}
.sg-js.sg-root{animation:sg-enter .7s ease both}
@keyframes sg-rise{to{transform:translateY(0)}}
@keyframes sg-enter{from{opacity:0}}
@keyframes sg-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes sg-pop{from{opacity:0;transform:translateY(8px) scale(.96)}}
@keyframes sg-count{to{--n:var(--p)}}
@keyframes sg-wipe{from{clip-path:inset(0 0 100% 0)}}
@keyframes sg-slide-x{from{opacity:0;transform:translateX(-20px)}}
@keyframes sg-tick{from{transform:scale(0) rotate(-90deg)}}
@keyframes sg-tile{from{transform:scale(.4) rotate(-20deg);opacity:0}}
@keyframes sg-settle{from{transform:scale(1.25)}}
@keyframes sg-drift{50%{transform:translate(-2.5rem,2rem) rotate(28deg)}}

/* ---------- Reduced motion ---------- */
@media(prefers-reduced-motion:reduce){
html{scroll-behavior:auto}
.sg-root *,.sg-root *::before,.sg-root *::after{animation:none!important;transition:none!important}
.sg-js .sg-word,.sg-js [data-reveal],.sg-js [data-reveal]:not(.is-in) .sg-hl{opacity:1;transform:none}
.sg-js [data-reveal] .sg-count{--n:var(--p,0)}
.sg-js [data-reveal] .sg-bar{width:var(--w,0%)}
.sg-js [data-reveal]:not(.is-in) .sg-kicker::before{transform:none}
.sg-js [data-reveal]:not(.is-in) .sg-h2,.sg-js [data-reveal]:not(.is-in).sg-h2{letter-spacing:-.05em}
}
CSS;
    }
}
