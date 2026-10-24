<?php

namespace App\Http\Controllers\PortfolioExport;

trait CommandDeckTemplate
{
    private function renderCommandDeckPage(string $title, array $content, array $assets, ?array $googleForm): string
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
        $icon = fn (string $name, int $size = 16, string $class = '', string $stroke = '2'): string => $this->commandDeckIcon($name, $size, $class, $stroke);
        $pad = static fn (int $number): string => str_pad((string) $number, 2, '0', STR_PAD_LEFT);
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
        $heroInitials = '';
        foreach (array_slice(preg_split('/\s+/u', $heroName ?? $logoText ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2) as $word) {
            $heroInitials .= mb_strtoupper(mb_substr($word, 0, 1));
        }
        $heroSocials = $socials($header, 'hero_social_links');
        $greeting = $text($header, 'hero_greeting');
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
        $projectFilters = [];
        foreach ($projects as $project) {
            foreach ($project['tags'] as $tag) {
                if (! in_array($tag, $projectFilters, true)) {
                    $projectFilters[] = $tag;
                }
            }
        }
        $projectFilters = array_slice($projectFilters, 0, 8);
        $showFilters = count($projectFilters) > 1;

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
        $contactLabel = $text($contact, 'contact_subtitle') ?? $text($contact, 'contact_title') ?? '';
        $formReady = $flag($contact, 'contact_form_enabled', true) && $googleForm !== null;

        $navDefs = [
            ['id' => 'about', 'label' => 'About', 'icon' => 'user', 'present' => $hasAbout],
            ['id' => 'journey', 'label' => 'Journey', 'icon' => 'activity', 'present' => $hasJourney],
            ['id' => 'skills', 'label' => 'Skills', 'icon' => 'wrench', 'present' => $hasSkills],
            ['id' => 'projects', 'label' => 'Work', 'icon' => 'layout-grid', 'present' => $hasProjects],
            ['id' => 'services', 'label' => 'Services', 'icon' => 'briefcase', 'present' => $hasServices],
        ];
        $navLinks = array_values(array_filter($navDefs, static fn (array $definition): bool => $definition['present']));

        // Window frame title bar.
        $winBar = static fn (?string $titleText, string $badge = '') => '<div class="cd-win-bar"><span class="cd-lights" aria-hidden="true"><i></i><i></i><i></i></span>'
            .($titleText !== null ? '<span class="cd-mono cd-win-title">'.e($titleText).'</span>' : '')
            .$badge.'</div>';
        $countPill = static fn (string $value): string => '<span class="cd-count-pill cd-mono">'.e($value).'</span>';

        /* ---------------------------------------------------------------
         | Left icon rail
         * ------------------------------------------------------------- */
        $html = '<div class="cd-root" data-view="overview">';
        $html .= '<aside class="cd-rail '.($stickyNav ? 'is-sticky' : 'is-static').'" aria-label="Workspace">';
        if ($logoImage !== '' || $logoText !== null) {
            $html .= '<a href="#header" class="cd-logo"'.($logoText !== null ? ' title="'.$escape($logoText).'"' : '').' data-view-target="overview">';
            if ($logoImage !== '') {
                $html .= '<img src="'.$escape($logoImage).'" alt="'.$escape($logoText ?? '').'" class="cd-logo-img">';
            } else {
                $html .= '<span class="cd-display cd-logo-letter">'.$escape(mb_strtoupper(mb_substr((string) $logoText, 0, 1))).'</span>';
            }
            $html .= '</a>';
        }

        $html .= '<nav class="cd-rail-nav" aria-label="Sections">';
        if ($hasHeader) {
            $railLabel = $heroName ?? $logoText;
            $html .= '<button type="button" class="cd-rail-btn" data-active="true" data-view-target="overview"'
                .($railLabel !== null ? ' title="'.$escape($railLabel).'"' : '')
                .' aria-label="'.$escape($railLabel ?? 'Overview').'">'.$icon('layout-grid', 18).'</button>';
        }
        foreach ($navLinks as $link) {
            $html .= '<button type="button" class="cd-rail-btn" data-active="false" data-view-target="'.$escape($link['id']).'" data-view-label="'.$escape($link['label']).'" title="'.$escape($link['label']).'" aria-label="'.$escape($link['label']).'">'.$icon($link['icon'], 18).'</button>';
        }
        if ($hasContact) {
            $contactTitle = $text($contact, 'contact_title') ?? $email;
            $html .= '<button type="button" class="cd-rail-btn" data-active="false" data-view-target="contact" data-view-label="'.$escape($contactLabel).'"'
                .($contactTitle !== null ? ' title="'.$escape($contactTitle).'"' : '')
                .' aria-label="'.$escape($contactTitle ?? 'Contact').'">'.$icon('mail', 18).'</button>';
        }
        $html .= '</nav>';

        if ($heroSocials !== []) {
            $html .= '<ul class="cd-rail-foot">';
            foreach (array_slice($heroSocials, 0, 4) as $social) {
                $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" class="cd-rail-btn" title="'.$escape($social['label']).'" aria-label="'.$escape($social['label']).'">'.$icon($socialIcon($social['platform']), 17).'</a></li>';
            }
            $html .= '</ul>';
        }
        $html .= '</aside>';

        /* ---------------------------------------------------------------
         | Top system bar
         * ------------------------------------------------------------- */
        $tabs = '';
        if ($hasHeader) {
            $tabs .= '<button type="button" role="tab" class="cd-tab" aria-selected="true" data-view-target="overview">'.$icon('layout-grid', 14).$escape($greeting ?? $heroName ?? '').'</button>';
        }
        foreach ($navLinks as $link) {
            $tabs .= '<button type="button" role="tab" class="cd-tab" aria-selected="false" data-view-target="'.$escape($link['id']).'">'.$icon($link['icon'], 14).$escape($link['label']).'</button>';
        }
        $brand = $logoText ?? $heroName;

        $html .= '<div class="cd-shell"><header class="cd-top'.($stickyNav ? ' is-sticky' : '').'">';
        $html .= '<div class="cd-progress" data-progress aria-hidden="true"></div>';
        $html .= '<div class="cd-top-inner"><div class="cd-top-left"><span class="cd-dot" aria-hidden="true"></span>';
        if ($brand !== null) {
            $html .= '<p class="cd-mono cd-crumb">'.$escape($brand).'</p>';
            $html .= '<span class="cd-chev-wrap" data-crumb-sep hidden>'.$icon('chevron-right', 14).'</span>';
        }
        $html .= '<p class="cd-mono cd-crumb cd-crumb-current" data-crumb hidden></p></div>';
        $html .= '<div class="cd-tabs" role="tablist" aria-label="Views">'.$tabs.'</div>';
        $html .= '<div class="cd-top-right"><span class="cd-mono cd-pct" data-scroll-pct aria-hidden="true">000%</span>';
        if ($hasContact) {
            $html .= '<a href="#contact" class="cd-btn cd-btn-primary cd-btn-sm" data-view-target="contact">'.$escape($contactLabel !== '' ? $contactLabel : ($email ?? '')).$icon('arrow-up-right', 14, 'cd-arrow').'</a>';
        }
        $html .= '</div></div>';
        $html .= '<div class="cd-tabs-mobile" role="tablist" aria-label="Views">'.$tabs.'</div></header>';

        /* ---------------------------------------------------------------
         | Workspace
         * ------------------------------------------------------------- */
        $html .= '<main class="cd-main">';

        // Hero / overview
        if ($hasHeader) {
            $heroImage = $mediaUrl($header, 'hero_image');
            $heroBackground = $mediaUrl($header, 'hero_background_image');
            $role = $text($header, 'hero_role');
            $description = $text($header, 'hero_description');
            $liveBadge = $greeting !== null ? '<span class="cd-live"><span class="cd-pulse" aria-hidden="true"></span>'.$escape($greeting).'</span>' : '';

            $html .= '<section id="header" class="cd-grid" data-view-section="header"><article data-reveal class="cd-win cd-span-12" style="--d: 0ms">';
            $html .= $winBar($role, $liveBadge);
            $html .= '<div class="cd-hero">';
            if ($heroBackground !== '') {
                $html .= '<img src="'.$escape($heroBackground).'" alt="" class="cd-hero-bg">';
            }
            $html .= '<div class="cd-hero-layout">';
            if ($heroImage !== '' || $heroInitials !== '') {
                $html .= '<div class="cd-avatar">';
                if ($heroImage !== '') {
                    $html .= '<img src="'.$escape($heroImage).'" alt="'.$escape($heroName ?? '').'" class="cd-cover">';
                } else {
                    $html .= '<span class="cd-display cd-initials">'.$escape($heroInitials).'</span>';
                }
                $html .= '</div>';
            }
            $html .= '<div class="cd-hero-copy">';
            if ($heroName !== null) {
                $html .= '<h1 class="cd-display cd-hero-name"><span class="cd-name-line">'.$escape($heroName).'</span></h1>';
            }
            if ($description !== null) {
                $html .= '<p class="cd-hero-desc">'.$escape($description).'</p>';
            }
            if ($heroPrimary !== null || $heroSecondary !== null) {
                $html .= '<div class="cd-actions">';
                if ($heroPrimary !== null) {
                    $html .= '<a href="'.$url($heroPrimary['url']).'" class="cd-btn cd-btn-primary" data-jump>'.$escape($heroPrimary['text']).$icon('arrow-up-right', 16, 'cd-arrow').'</a>';
                }
                if ($heroSecondary !== null) {
                    $html .= '<a href="'.$url($heroSecondary['url']).'" class="cd-btn cd-btn-ghost" data-jump>'.$escape($heroSecondary['text']).'</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></div>';

            if ($heroSocials !== [] || $location !== null) {
                $html .= '<div class="cd-win-foot">';
                if ($heroSocials !== []) {
                    $html .= '<ul class="cd-chips">';
                    foreach ($heroSocials as $social) {
                        $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" class="cd-chip-link" title="'.$escape($social['label']).'">'.$icon($socialIcon($social['platform']), 14).$escape($social['label']).'</a></li>';
                    }
                    $html .= '</ul>';
                }
                if ($location !== null) {
                    $html .= '<span class="cd-mono cd-location">'.$icon('map-pin', 14).$escape($location).'</span>';
                }
                $html .= '</div>';
            }
            $html .= '</article>';

            if ($highlights !== []) {
                $html .= '<aside class="cd-stats cd-span-4">';
                foreach (array_slice($highlights, 0, 3) as $index => $highlight) {
                    $html .= '<article data-reveal style="--d: '.(($index + 1) * 90).'ms" class="cd-win cd-stat"><div class="cd-stat-head"><span class="cd-mono cd-stat-num">'.$pad($index + 1).'</span>'.$icon('activity', 16).'</div>';
                    $html .= '<p class="cd-display cd-stat-value">'.$escape($highlight['title']).'</p>';
                    if ($highlight['description'] !== null) {
                        $html .= '<p class="cd-stat-desc">'.$escape($highlight['description']).'</p>';
                    }
                    $html .= '<span class="cd-spark" aria-hidden="true"><i></i></span></article>';
                }
                $html .= '</aside>';
            }
            $html .= '</section>';
        }

        // About
        if ($hasAbout) {
            $html .= '<section id="about" class="cd-grid" data-view-section="about"><article data-reveal class="cd-win cd-span-12">';
            $html .= $winBar($text($about, 'about_subtitle'));
            $html .= '<div class="cd-pad cd-about-grid'.($aboutImage !== '' ? ' has-image' : '').'">';
            if ($aboutImage !== '') {
                $html .= '<div class="cd-col-4"><div class="cd-shot"><img src="'.$escape($aboutImage).'" alt="'.$escape($text($about, 'about_title') ?? '').'" loading="lazy"></div></div>';
            }
            $html .= '<div'.($aboutImage !== '' ? ' class="cd-col-8"' : '').'>';
            if ($text($about, 'about_title') !== null) {
                $html .= '<h2 class="cd-display cd-h2">'.$escape($text($about, 'about_title')).'</h2>';
            }
            if ($text($about, 'about_description') !== null) {
                $html .= '<p class="cd-prose">'.$escape($text($about, 'about_description')).'</p>';
            }
            if ($highlights !== []) {
                $html .= '<ul class="cd-hl-list">';
                foreach ($highlights as $index => $highlight) {
                    $html .= '<li class="cd-hl" style="--k: '.$index.'"><span class="cd-tick">'.$icon('check', 12, '', '3').'</span><span><span class="cd-hl-title">'.$escape($highlight['title']).'</span>';
                    if ($highlight['description'] !== null) {
                        $html .= '<span class="cd-hl-desc">'.$escape($highlight['description']).'</span>';
                    }
                    $html .= '</span></li>';
                }
                $html .= '</ul>';
            }
            if ($cvFile !== '' || $aboutCta !== null) {
                $html .= '<div class="cd-actions cd-actions-lg">';
                if ($cvFile !== '') {
                    $cvLabel = $aboutCta['text'] ?? 'Download CV';
                    $html .= '<a href="'.$escape($cvFile).'" download target="_blank" rel="noopener" class="cd-btn cd-btn-primary">'.$icon('download', 16).$escape($cvLabel).'</a>';
                }
                if ($aboutCta !== null) {
                    $html .= '<a href="'.$url($aboutCta['url']).'" class="cd-btn '.($cvFile !== '' ? 'cd-btn-ghost' : 'cd-btn-primary').'" data-jump>'.$escape($aboutCta['text']).$icon('arrow-up-right', 16, 'cd-arrow').'</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></article></section>';
        }

        // Journey + skills
        if ($hasJourney || $hasSkills) {
            $tokens = implode(' ', array_filter([$hasJourney ? 'journey' : '', $hasSkills ? 'skills' : '']));
            $html .= '<section class="cd-grid" data-view-section="'.$tokens.'">';

            if ($hasJourney) {
                $html .= '<article id="journey" data-view-section="journey" data-reveal class="cd-win'.($hasSkills ? ' cd-split-a' : '').'">';
                $html .= $winBar($text($journeyData, 'journey_subtitle'), $countPill($pad(count($journey))));
                $html .= '<div class="cd-pad">';
                if ($text($journeyData, 'journey_title') !== null) {
                    $html .= '<h2 class="cd-display cd-h3">'.$escape($text($journeyData, 'journey_title')).'</h2>';
                }
                $html .= '<ol class="cd-timeline">';
                foreach ($journey as $index => $item) {
                    $html .= '<li data-reveal style="--d: '.(($index % 4) * 70).'ms" class="cd-tl-item"><div class="cd-card"><div class="cd-tl-head">';
                    if ($item['company'] !== null) {
                        $html .= '<p class="cd-mono cd-company">'.$escape($item['company']).'</p>';
                    }
                    if ($item['period'] !== null) {
                        $html .= '<span class="cd-pill">'.$escape($item['period']).'</span>';
                    }
                    $html .= '</div>';
                    if ($item['title'] !== null) {
                        $html .= '<h3 class="cd-display cd-item-title">'.$escape($item['title']).'</h3>';
                    }
                    if ($item['description'] !== null) {
                        $html .= '<p class="cd-item-desc">'.$escape($item['description']).'</p>';
                    }
                    $html .= '</div></li>';
                }
                $html .= '</ol></div></article>';
            }

            if ($hasSkills) {
                $html .= '<article id="skills" data-view-section="skills" data-reveal class="cd-win'.($hasJourney ? ' cd-split-b' : '').'" style="--d: 90ms">';
                $html .= $winBar($text($skillsData, 'skills_subtitle'), $countPill($pad(count($skills))));
                $html .= '<div class="cd-pad">';
                if ($text($skillsData, 'skills_title') !== null) {
                    $html .= '<h2 class="cd-display cd-h3">'.$escape($text($skillsData, 'skills_title')).'</h2>';
                }
                if ($text($skillsData, 'skills_description') !== null) {
                    $html .= '<p class="cd-skills-desc">'.$escape($text($skillsData, 'skills_description')).'</p>';
                }
                $html .= '<ul class="cd-skill-list">';
                foreach ($skills as $index => $skill) {
                    $html .= '<li data-reveal style="--d: '.(($index % 6) * 50).'ms" class="cd-skill"><div class="cd-skill-row">';
                    if ($showIcon) {
                        $skillIcon = $iconFor($skill['icon']);
                        $html .= '<span class="cd-tile">';
                        if ($skillIcon !== null) {
                            $html .= $icon($skillIcon, 16);
                        } elseif ($isGlyph($skill['icon'])) {
                            $html .= '<span class="cd-tile-glyph" aria-hidden="true">'.$escape($skill['icon']).'</span>';
                        } else {
                            $html .= '<span class="cd-display cd-tile-letter" aria-hidden="true">'.$escape(mb_strtoupper(mb_substr($skill['name'], 0, 1))).'</span>';
                        }
                        $html .= '</span>';
                    }
                    $html .= '<h3 class="cd-skill-name">'.$escape($skill['name']).'</h3>';
                    if ($showLevel && $skill['percent'] !== null) {
                        $html .= '<span class="cd-count cd-mono cd-skill-pct" style="--p: '.(int) round($skill['percent']).'" aria-hidden="true"></span>';
                    } elseif ($showLevel && $skill['label'] !== null) {
                        $html .= '<span class="cd-pill">'.$escape($skill['label']).'</span>';
                    }
                    $html .= '</div>';
                    if ($showLevel && $skill['percent'] !== null) {
                        $html .= '<div class="cd-track" role="progressbar" aria-valuenow="'.(int) round($skill['percent']).'" aria-valuemin="0" aria-valuemax="100" aria-label="'.$escape($skill['name']).'"><div class="cd-bar" style="--w: '.$num($skill['percent']).'%"></div></div>';
                    }
                    $html .= '</li>';
                }
                $html .= '</ul></div></article>';
            }
            $html .= '</section>';
        }

        // Projects
        if ($hasProjects) {
            $html .= '<section id="projects" class="cd-grid" data-view-section="projects"><article data-reveal class="cd-win cd-span-12">';
            $html .= $winBar(
                $text($projectData, 'projects_subtitle'),
                '<span class="cd-count-pill cd-mono" data-project-count data-total="'.$pad(count($projects)).'">'.$pad(count($projects)).'/'.$pad(count($projects)).'</span>'
            );
            $html .= '<div class="cd-pad"><div class="cd-proj-head">';
            if ($text($projectData, 'projects_title') !== null) {
                $html .= '<h2 class="cd-display cd-h3 cd-proj-title">'.$escape($text($projectData, 'projects_title')).'</h2>';
            }
            if ($text($projectData, 'projects_description') !== null) {
                $html .= '<p class="cd-proj-desc">'.$escape($text($projectData, 'projects_description')).'</p>';
            }
            $html .= '</div>';

            if ($showFilters) {
                $html .= '<div class="cd-filters" role="tablist" aria-label="Filter"><button type="button" role="tab" class="cd-filter" aria-selected="true" data-project-filter="">'.$icon('layers', 14).count($projects).'</button>';
                foreach ($projectFilters as $tag) {
                    $html .= '<button type="button" role="tab" class="cd-filter" aria-selected="false" data-project-filter="'.$escape($tag).'">'.$escape($tag).'</button>';
                }
                $html .= '</div>';
            }

            $html .= '<ul class="cd-project-grid" data-project-grid>';
            foreach ($projects as $index => $project) {
                $featured = $featureFirst && $index === 0;
                $tagsJson = json_encode($project['tags'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $html .= '<li data-reveal data-project-item data-tags="'.$escape($tagsJson === false ? '[]' : $tagsJson).'" class="cd-project-item'.($featured ? ' cd-feature' : '').'" style="--d: '.(($index % 3) * 80).'ms">';
                $html .= '<article class="cd-project">';
                if ($project['live'] !== '') {
                    $html .= '<a href="'.$escape($project['live']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($project['title']).'" class="cd-media">';
                } else {
                    $html .= '<div class="cd-media">';
                }
                if ($project['image'] !== '') {
                    $html .= '<img src="'.$escape($project['image']).'" alt="'.$escape($project['title']).'" loading="lazy">';
                } else {
                    $html .= '<div class="cd-display cd-tone cd-tone-'.($index % 3).'">'.$escape(mb_strtoupper(mb_substr($project['title'], 0, 1))).'</div>';
                }
                if ($featured) {
                    $html .= '<span class="cd-flag cd-mono">'.$icon('sparkles', 12).$pad($index + 1).'</span>';
                }
                if ($project['live'] !== '') {
                    $html .= '<span class="cd-go" aria-hidden="true">'.$icon('arrow-up-right', 16).'</span>';
                }
                $html .= $project['live'] !== '' ? '</a>' : '</div>';

                $html .= '<div class="cd-proj-body"><h3 class="cd-display cd-proj-name"><span class="cd-underline">'.$escape($project['title']).'</span></h3>';
                if ($project['description'] !== null) {
                    $html .= '<p class="cd-proj-text">'.$escape($project['description']).'</p>';
                }
                if ($project['tags'] !== []) {
                    $html .= '<ul class="cd-tags">';
                    foreach ($project['tags'] as $tag) {
                        $html .= '<li><button type="button" class="cd-pill cd-pill-btn" data-project-tag="'.$escape($tag).'">'.$escape($tag).'</button></li>';
                    }
                    $html .= '</ul>';
                }
                if ($project['repo'] !== '') {
                    $html .= '<div class="cd-repo"><a href="'.$escape($project['repo']).'" target="_blank" rel="noopener noreferrer" class="cd-textlink cd-mono">'.$icon('code', 16).$escape($project['repo_label']).'</a></div>';
                }
                $html .= '</div></article></li>';
            }
            $html .= '</ul></div></article></section>';
        }

        // Services
        if ($hasServices) {
            $html .= '<section id="services" class="cd-grid" data-view-section="services"><article data-reveal class="cd-win cd-span-12">';
            $html .= $winBar($text($servicesData, 'services_subtitle'), $countPill($pad(count($services))));
            $html .= '<div class="cd-pad">';
            if ($text($servicesData, 'services_title') !== null) {
                $html .= '<h2 class="cd-display cd-h3">'.$escape($text($servicesData, 'services_title')).'</h2>';
            }
            $html .= '<ul class="cd-service-grid">';
            foreach ($services as $index => $service) {
                $html .= '<li data-reveal style="--d: '.(($index % 6) * 70).'ms"><div class="cd-service"><span class="cd-tile cd-tile-lg">';
                if (! $isGlyph($service['icon'])) {
                    $html .= $icon($iconFor($service['icon']) ?? 'sparkles', 20);
                } else {
                    $html .= '<span class="cd-tile-glyph-lg" aria-hidden="true">'.$escape($service['icon']).'</span>';
                }
                $html .= '</span><h3 class="cd-display cd-service-title">'.$escape($service['title']).'</h3>';
                if ($service['description'] !== null) {
                    $html .= '<p class="cd-service-text">'.$escape($service['description']).'</p>';
                }
                $html .= $icon('arrow-up-right', 20, 'cd-service-arrow').'</div></li>';
            }
            $html .= '</ul></div></article></section>';
        }

        // Contact
        if ($hasContact) {
            $html .= '<section id="contact" class="cd-grid" data-view-section="contact"><article data-reveal class="cd-win cd-span-12">';
            $html .= $winBar($text($contact, 'contact_subtitle'));
            $html .= '<div class="cd-pad cd-contact-grid"><div class="cd-col-7">';
            if ($text($contact, 'contact_title') !== null) {
                $html .= '<h2 class="cd-display cd-contact-title">'.$escape($text($contact, 'contact_title')).'</h2>';
            }
            if ($text($contact, 'contact_description') !== null) {
                $html .= '<p class="cd-contact-text">'.$escape($text($contact, 'contact_description')).'</p>';
            }
            if ($email !== null || $phone !== null || $location !== null) {
                $html .= '<div class="cd-contact-info">';
                if ($email !== null && $emailHref !== '') {
                    $html .= '<a href="'.$escape($emailHref).'" class="cd-bigmail cd-display">'.$icon('mail', 24).'<span class="cd-underline-contact">'.$escape($email).'</span>'.$icon('arrow-up-right', 24, 'cd-arrow').'</a>';
                }
                if ($phone !== null || $location !== null) {
                    $html .= '<div class="cd-mono cd-contact-meta">';
                    if ($phone !== null && $phoneHref !== '') {
                        $html .= '<a href="'.$escape($phoneHref).'" class="cd-contact-link">'.$icon('phone', 16).$escape($phone).'</a>';
                    }
                    if ($location !== null) {
                        $html .= '<span>'.$icon('map-pin', 16).$escape($location).'</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            if ($contactSocials !== []) {
                $html .= '<ul class="cd-contact-socials">';
                foreach ($contactSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" class="cd-chip-link">'.$icon($socialIcon($social['platform']), 14).$escape($social['label']).$icon('arrow-up-right', 14, 'cd-arrow').'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><div class="cd-col-5">';

            if ($formReady) {
                $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="cd-gform-frame" class="cd-form" data-google-form>
                <label class="cd-field"><span class="cd-mono cd-label">Name</span><input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="cd-input"></label>
                <label class="cd-field"><span class="cd-mono cd-label">Email</span><input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="cd-input"></label>
                <label class="cd-field"><span class="cd-mono cd-label">Message</span><textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required class="cd-input"></textarea></label>
                <button type="submit" class="cd-btn cd-btn-primary cd-btn-block"><span data-submit-label>Send</span>'.$icon('send', 16, 'cd-arrow').'</button>
                <p class="cd-sent" role="status" data-form-status hidden><span class="cd-tick">'.$icon('check', 12, '', '3').'</span>OK</p>
            </form>
            <iframe name="cd-gform-frame" title="Form submission target" class="cd-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
            } elseif ($email !== null && $emailHref !== '') {
                $html .= '<a href="'.$escape($emailHref).'" class="cd-btn cd-btn-ghost cd-btn-block cd-btn-xl">'.$icon('mail', 20).$escape($email).'</a>';
            }
            $html .= '</div></div></article></section>';
        }

        if ($heroName !== null || $logoText !== null) {
            $html .= '<footer class="cd-foot"><span class="cd-mono cd-foot-copy">&copy; '.date('Y').' '.$escape($heroName ?? $logoText).'</span><button type="button" class="cd-textlink" aria-label="Top" data-view-target="overview">'.$icon('arrow-up-right', 16, 'cd-arrow cd-rot').'</button></footer>';
        }

        $html .= '</main></div></div>';

        return '<!doctype html>
<html lang="en" data-template="command-deck">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f3f4f8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0b0e17" media="(prefers-color-scheme: dark)">
    <title>'.$escape($title).'</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    /** Inline Lucide-style icons (static, trusted markup only). */
    private function commandDeckIcon(string $name, int $size = 16, string $class = '', string $stroke = '2'): string
    {
        $paths = [
            'activity' => '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/>',
            'arrow-up-right' => '<path d="M7 7h10v10"/><path d="M7 17 17 7"/>',
            'at-sign' => '<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>',
            'briefcase' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
            'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
            'code' => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
            'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
            'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
            'dribbble' => '<circle cx="12" cy="12" r="10"/><path d="M19.13 5.09C15.22 9.14 10 10.44 2.25 10.94"/><path d="M21.75 12.84c-6.62-1.41-12.14 1-16.38 6.32"/><path d="M8.56 2.75c4.37 6 6 9.42 8 17.72"/>',
            'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
            'github' => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/>',
            'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
            'instagram' => '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
            'layers' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
            'layout-grid' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
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
            'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
            'youtube' => '<path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
        ];
        $body = $paths[$name] ?? $paths['globe'];

        return '<svg class="cd-ico'.($class !== '' ? ' '.$class : '').'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$stroke.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$body.'</svg>';
    }

    private function commandDeckStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');
/* ---------- Reset (Tailwind-preflight equivalent, low specificity) ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;color-scheme:light dark;-webkit-text-size-adjust:100%}
body{margin:0;background:#f3f4f8}
@media(prefers-color-scheme:dark){body{background:#0b0e17}}
:where(.cd-root) :is(h1,h2,h3,p,ul,ol,figure){margin:0}
:where(.cd-root) :is(ul,ol){padding:0;list-style:none}
:where(.cd-root) :is(h1,h2,h3){font-size:inherit;font-weight:inherit}
:where(.cd-root) a{color:inherit;text-decoration:none}
:where(.cd-root) :is(button,input,textarea){font:inherit;color:inherit;letter-spacing:inherit}
:where(.cd-root) button{background:none;border:0;padding:0;cursor:pointer}
:where(.cd-root) img,:where(.cd-root) svg{display:block;max-width:100%}
.cd-root [hidden]{display:none!important}
.cd-ico{flex-shrink:0}

/* ---------- Foundations + palette (light, dark via prefers-color-scheme) ---------- */
.cd-root{--paper:#F3F4F8;--soft:#E9EBF2;--surface:#FFFFFF;--ink:#0E1220;--muted:#0E1220A8;--line:#0E122021;--accent:#3B5BFF;--accent-2:#12B981;--accent-ink:#FFFFFF;--rail:4.25rem;min-height:100vh;background:var(--paper);color:var(--ink);font-family:'Manrope',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;background-image:radial-gradient(color-mix(in srgb,var(--ink) 9%,transparent) 1px,transparent 1px);background-size:22px 22px;transition:background-color .4s ease,color .4s ease}
@media(prefers-color-scheme:dark){.cd-root{--paper:#0B0E17;--soft:#10141F;--surface:#151A28;--ink:#EEF1FB;--muted:#EEF1FBA8;--line:#EEF1FB1F;--accent:#7C93FF;--accent-2:#34D399;--accent-ink:#0B0E17}}
.cd-root ::selection{background:var(--accent);color:#fff}
.cd-display{font-family:'Manrope',ui-sans-serif,system-ui,sans-serif;font-weight:700;letter-spacing:-.03em}
.cd-mono{font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,monospace}
.cd-h2{font-size:clamp(2rem,4.4vw,3.25rem);line-height:1.05;letter-spacing:-.04em;text-wrap:balance}
.cd-h3{font-size:clamp(1.6rem,3vw,2.35rem);line-height:1.08;letter-spacing:-.035em;text-wrap:balance}
.cd-root :where(a,button,input,textarea):focus-visible{outline:2px solid var(--accent);outline-offset:3px;border-radius:.5rem}
.cd-pad{padding:1.5rem}
@media(min-width:640px){.cd-pad{padding:2.25rem}}

/* ---------- Left rail ---------- */
.cd-rail{position:fixed;inset:0 auto 0 0;z-index:55;display:flex;width:var(--rail);flex-direction:column;align-items:center;gap:1.25rem;border-right:1px solid var(--line);background:color-mix(in srgb,var(--surface) 88%,transparent);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);padding:1rem 0}
.cd-rail.is-static{position:absolute;min-height:100%}
.cd-logo{display:inline-flex;height:2.5rem;width:2.5rem;align-items:center;justify-content:center;overflow:hidden;border-radius:.8rem;background:var(--accent);color:var(--accent-ink);transition:transform .35s cubic-bezier(.2,.7,.2,1)}
.cd-logo:hover{transform:rotate(-6deg) scale(1.06)}
.cd-logo-img{width:100%;height:100%;object-fit:contain}
.cd-logo-letter{font-size:.875rem;line-height:1.25rem}
.cd-rail-nav{display:flex;flex:1;flex-direction:column;align-items:center;gap:.4rem;padding-top:.5rem;border-top:1px solid var(--line);width:100%;padding-inline:.6rem}
.cd-rail-foot{display:flex;flex-direction:column;align-items:center;gap:.4rem}
.cd-rail-btn{position:relative;display:inline-flex;height:2.6rem;width:2.6rem;align-items:center;justify-content:center;border-radius:.85rem;color:var(--muted);cursor:pointer;transition:background-color .25s ease,color .25s ease,transform .3s cubic-bezier(.2,.7,.2,1)}
.cd-rail-btn:hover{background:var(--soft);color:var(--ink);transform:translateX(2px)}
.cd-rail-btn[data-active='true']{background:color-mix(in srgb,var(--accent) 16%,transparent);color:var(--accent)}
.cd-rail-btn[data-active='true']::before{content:'';position:absolute;left:-.6rem;top:22%;bottom:22%;width:3px;border-radius:0 3px 3px 0;background:var(--accent)}

/* ---------- Shell / top bar ---------- */
.cd-shell{position:relative;margin-left:var(--rail);min-width:0}
.cd-top{z-index:50;border-bottom:1px solid var(--line);background:color-mix(in srgb,var(--paper) 78%,transparent);-webkit-backdrop-filter:blur(14px) saturate(1.3);backdrop-filter:blur(14px) saturate(1.3)}
.cd-top.is-sticky{position:sticky;top:0}
.cd-top-inner{display:flex;height:3.5rem;align-items:center;justify-content:space-between;gap:1rem;padding-inline:1.25rem;transition:box-shadow .3s ease}
.cd-top-inner.is-scrolled{box-shadow:0 12px 30px -20px color-mix(in srgb,var(--ink) 55%,transparent)}
.cd-top-left{display:flex;min-width:0;align-items:center;gap:.75rem}
.cd-top-right{display:flex;align-items:center;gap:.75rem}
.cd-crumb{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.1em}
.cd-crumb-current{color:var(--accent);font-weight:400}
.cd-chev-wrap{display:inline-flex;flex-shrink:0;color:var(--muted)}
.cd-pct{display:none;font-size:.72rem;font-variant-numeric:tabular-nums;color:var(--muted)}
@media(min-width:640px){.cd-pct{display:inline}}
.cd-progress{position:absolute;inset:auto 0 -1px 0;height:2px;background:linear-gradient(90deg,var(--accent),var(--accent-2));transform:scaleX(0);transform-origin:left;pointer-events:none}
.cd-dot{height:.55rem;width:.55rem;flex-shrink:0;border-radius:999px;background:var(--accent-2);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent-2) 25%,transparent)}
.cd-tabs{display:none;align-items:center;gap:.25rem;border:1px solid var(--line);border-radius:.95rem;background:var(--surface);padding:.25rem}
.cd-tabs-mobile{display:flex;gap:.25rem;overflow-x:auto;border-top:1px solid var(--line);padding:.5rem 1rem;scrollbar-width:none}
.cd-tabs-mobile::-webkit-scrollbar{display:none}
@media(min-width:1024px){.cd-tabs{display:inline-flex}.cd-tabs-mobile{display:none}}
.cd-tab{display:inline-flex;flex-shrink:0;align-items:center;gap:.45rem;border-radius:.7rem;padding:.45rem .85rem;font-size:.84rem;font-weight:600;color:var(--muted);cursor:pointer;transition:color .25s ease,background-color .25s ease,box-shadow .25s ease}
.cd-tab:hover{color:var(--ink)}
.cd-tab[aria-selected='true']{background:var(--accent);color:var(--accent-ink);box-shadow:0 6px 16px -8px var(--accent)}

/* ---------- Workspace ---------- */
.cd-main{display:grid;gap:1.25rem;padding:1.25rem;max-width:1240px;margin-inline:auto}
@media(min-width:640px){.cd-main{padding:1.75rem;gap:1.75rem}}
.cd-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:1.25rem;scroll-margin-top:5rem}
.cd-grid>*{grid-column:span 12/span 12;min-width:0}
@media(min-width:1024px){
.cd-span-4{grid-column:span 4/span 4!important}
.cd-root[data-view="overview"] .cd-split-a{grid-column:span 7/span 7!important}
.cd-root[data-view="overview"] .cd-split-b{grid-column:span 5/span 5!important}
}
.cd-win{overflow:hidden;border:1px solid var(--line);border-radius:1.25rem;background:var(--surface);box-shadow:0 18px 40px -30px color-mix(in srgb,var(--ink) 60%,transparent);transition:border-color .3s ease,box-shadow .4s ease}
.cd-win:hover{border-color:color-mix(in srgb,var(--accent) 40%,var(--line))}
.cd-win-bar{display:flex;align-items:center;gap:.9rem;border-bottom:1px solid var(--line);background:var(--soft);padding:.65rem 1rem}
.cd-lights{display:inline-flex;gap:.35rem}
.cd-lights i{height:.6rem;width:.6rem;border-radius:999px;background:color-mix(in srgb,var(--ink) 22%,transparent);transition:background-color .3s ease}
.cd-win:hover .cd-lights i:nth-child(1){background:#ff5f57}
.cd-win:hover .cd-lights i:nth-child(2){background:#febc2e}
.cd-win:hover .cd-lights i:nth-child(3){background:#28c840}
.cd-win-title{min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.74rem;font-weight:500;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}
.cd-win-foot{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;border-top:1px solid var(--line);background:var(--soft);padding:.8rem 1rem}
.cd-count-pill{margin-left:auto;border-radius:999px;background:color-mix(in srgb,var(--accent) 14%,transparent);padding:.1rem .6rem;font-size:.7rem;font-weight:500;color:var(--accent)}
.cd-live{display:inline-flex;flex-shrink:0;align-items:center;gap:.5rem;margin-left:auto;border-radius:999px;border:1px solid var(--line);background:var(--surface);padding:.15rem .7rem;font-size:.75rem;font-weight:600}
.cd-pulse{position:relative;height:.45rem;width:.45rem;border-radius:999px;background:var(--accent-2)}
.cd-pulse::after{content:'';position:absolute;inset:0;border-radius:inherit;background:inherit;animation:cd-ping 2.2s cubic-bezier(0,0,.2,1) infinite}

/* ---------- Buttons & small controls ---------- */
.cd-btn{position:relative;isolation:isolate;display:inline-flex;align-items:center;gap:.5rem;border-radius:.8rem;padding:.8rem 1.35rem;font-size:.9rem;font-weight:700;line-height:1.2;cursor:pointer;border:1px solid transparent;transition:transform .25s ease,box-shadow .3s ease,background-color .3s ease,border-color .3s ease}
.cd-btn:hover{transform:translateY(-2px)}
.cd-btn:active{transform:translateY(0) scale(.98)}
.cd-btn[disabled]{opacity:.7;pointer-events:none}
.cd-btn-primary{background:var(--accent);color:var(--accent-ink);box-shadow:0 10px 22px -12px var(--accent)}
.cd-btn-primary:hover{box-shadow:0 16px 28px -12px var(--accent)}
.cd-btn-ghost{background:var(--surface);border-color:var(--line);color:var(--ink)}
.cd-btn-ghost:hover{border-color:var(--accent)}
.cd-btn-sm{padding:.5rem 1rem;font-size:.8rem}
.cd-btn-block{width:100%;justify-content:center}
.cd-btn-xl{padding-block:1.25rem;font-size:1rem}
.cd-arrow{transition:transform .3s cubic-bezier(.2,.7,.2,1)}
.cd-rot{transform:rotate(-45deg)}
.cd-btn:hover .cd-arrow,.cd-bigmail:hover .cd-arrow,.cd-chip-link:hover .cd-arrow,.cd-textlink:hover .cd-arrow{transform:translate(3px,-3px)}
.cd-chip-link{display:inline-flex;align-items:center;gap:.45rem;border-radius:.65rem;border:1px solid var(--line);background:var(--surface);padding:.4rem .8rem;font-size:.82rem;font-weight:600;transition:background-color .25s ease,color .25s ease,border-color .25s ease,transform .3s ease}
.cd-chip-link:hover{background:var(--accent);color:var(--accent-ink);border-color:var(--accent);transform:translateY(-2px)}
.cd-pill{display:inline-block;border-radius:.5rem;border:1px solid var(--line);background:var(--soft);padding:.15rem .6rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.7rem;font-weight:500;color:var(--muted)}
.cd-pill-btn{cursor:pointer;transition:background-color .25s ease,color .25s ease,border-color .25s ease}
.cd-pill-btn:hover{border-color:var(--accent);color:var(--accent)}
.cd-tick{display:inline-flex;height:1.3rem;width:1.3rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:.4rem;background:var(--accent);color:var(--accent-ink)}
.cd-textlink{display:inline-flex;align-items:center;gap:.4rem;font-weight:600;cursor:pointer;background:linear-gradient(currentColor,currentColor) left bottom/0% 1.5px no-repeat;transition:background-size .4s cubic-bezier(.2,.7,.2,1);padding-bottom:2px}
.cd-textlink:hover{background-size:100% 1.5px}

/* ---------- Hero ---------- */
.cd-hero{position:relative;overflow:hidden;padding:2rem 1.5rem}
@media(min-width:640px){.cd-hero{padding:2.75rem 2.5rem}}
.cd-hero-bg{position:absolute;inset:0;height:100%;width:100%;object-fit:cover;opacity:.12}
.cd-hero-layout{position:relative;z-index:10;display:grid;align-items:center;gap:2rem}
@media(min-width:640px){.cd-hero-layout{grid-template-columns:auto 1fr}}
.cd-hero-copy{min-width:0}
.cd-hero-name{font-size:clamp(2.5rem,6.4vw,5rem);line-height:.98;letter-spacing:-.05em;overflow-wrap:anywhere}
.cd-name-line{display:inline-block;background:linear-gradient(100deg,var(--ink) 30%,var(--accent) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
.cd-hero-desc{max-width:36rem;margin-top:1rem;white-space:pre-line;line-height:1.7;color:var(--muted)}
.cd-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem}
.cd-actions-lg{margin-top:2rem}
.cd-avatar{height:8.5rem;width:8.5rem;overflow:hidden;border-radius:1.75rem;background:var(--accent);border:1px solid var(--line);box-shadow:0 0 0 6px color-mix(in srgb,var(--accent) 14%,transparent),0 24px 40px -22px color-mix(in srgb,var(--ink) 60%,transparent);transition:transform .6s cubic-bezier(.2,.7,.2,1)}
@media(min-width:640px){.cd-avatar{height:11rem;width:11rem}}
.cd-avatar:hover{transform:rotate(-3deg) scale(1.03)}
.cd-cover{width:100%;height:100%;object-fit:cover}
.cd-initials{display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:3rem;line-height:1;color:var(--accent-ink)}
.cd-chips{display:flex;flex-wrap:wrap;gap:.5rem}
.cd-location{display:inline-flex;align-items:center;gap:.5rem;font-size:.75rem;color:var(--muted)}
.cd-location .cd-ico{color:var(--accent)}

/* Stat widgets */
.cd-stats{display:grid;align-content:start;gap:1rem}
@media(min-width:640px){.cd-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.cd-stats{grid-template-columns:minmax(0,1fr)}}
.cd-stat{position:relative;padding:1.2rem 1.3rem 1.35rem;transition:transform .4s cubic-bezier(.2,.7,.2,1),border-color .3s ease}
.cd-stat:hover{transform:translateY(-3px)}
.cd-stat-head{display:flex;align-items:center;justify-content:space-between}
.cd-stat-head .cd-ico{color:var(--accent)}
.cd-stat-num{font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;color:var(--muted)}
.cd-stat-value{margin-top:.7rem;font-size:clamp(1.4rem,2.6vw,2rem);line-height:1.1;letter-spacing:-.04em;overflow-wrap:anywhere}
.cd-stat-desc{margin-top:.5rem;font-size:.9rem;line-height:1.375;color:var(--muted)}
.cd-spark{display:block;margin-top:1rem;height:4px;overflow:hidden;border-radius:999px;background:color-mix(in srgb,var(--ink) 9%,transparent)}
.cd-spark i{display:block;height:100%;width:100%;border-radius:inherit;background:linear-gradient(90deg,var(--accent),var(--accent-2));transform-origin:left;transition:transform 1.4s cubic-bezier(.2,.7,.2,1) .4s}
.cd-js [data-reveal]:not(.is-in) .cd-spark i{transform:scaleX(0)}

/* ---------- About ---------- */
.cd-about-grid{display:grid;gap:2.5rem}
@media(min-width:1024px){.cd-about-grid.has-image{grid-template-columns:repeat(12,minmax(0,1fr))}.cd-col-4{grid-column:span 4/span 4}.cd-col-8{grid-column:span 8/span 8}}
.cd-shot{overflow:hidden;border:1px solid var(--line);border-radius:1.1rem;background:var(--soft);transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.cd-shot:hover{transform:rotate(-1deg) scale(1.01)}
.cd-shot img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover}
.cd-prose{max-width:40rem;margin-top:1.5rem;white-space:pre-line;font-size:1.05rem;line-height:1.75;color:var(--muted)}
.cd-hl-list{display:grid;gap:.75rem;margin-top:2rem}
@media(min-width:640px){.cd-hl-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
.cd-hl{display:flex;align-items:flex-start;gap:.8rem;border:1px solid var(--line);border-radius:.95rem;background:var(--paper);padding:.95rem 1rem;transition:border-color .3s ease,transform .35s cubic-bezier(.2,.7,.2,1)}
.cd-hl:hover{border-color:var(--accent);transform:translateY(-2px)}
.cd-hl-title{display:block;font-weight:600;line-height:1.375}
.cd-hl-desc{display:block;margin-top:.25rem;font-size:.88rem;line-height:1.625;color:var(--muted)}

/* ---------- Journey ---------- */
.cd-timeline{position:relative;margin:2rem 0 0 .4rem;border-left:1.5px solid var(--line);padding-left:1.75rem}
.cd-tl-item{position:relative;padding-bottom:1rem}
.cd-tl-item:last-child{padding-bottom:0}
.cd-tl-item::before{content:'';position:absolute;left:-1.75rem;top:1.35rem;height:.75rem;width:.75rem;transform:translateX(-50%);border-radius:999px;background:var(--surface);border:2px solid var(--accent);transition:background-color .5s ease .3s,box-shadow .4s ease}
.cd-js .cd-tl-item.is-in::before{background:var(--accent)}
.cd-tl-item:hover::before{box-shadow:0 0 0 6px color-mix(in srgb,var(--accent) 20%,transparent)}
.cd-card{border:1px solid var(--line);border-radius:1rem;background:var(--paper);padding:1.1rem 1.25rem 1.2rem;transition:transform .4s cubic-bezier(.2,.7,.2,1),border-color .3s ease,box-shadow .4s ease}
.cd-card:hover{transform:translateX(4px);border-color:color-mix(in srgb,var(--accent) 55%,var(--line));box-shadow:0 16px 30px -22px var(--accent)}
.cd-tl-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem}
.cd-company{font-size:.75rem;font-weight:500;text-transform:uppercase;letter-spacing:.1em;color:var(--accent)}
.cd-item-title{margin-top:.5rem;font-size:1.25rem;line-height:1.25}
.cd-item-desc{margin-top:.5rem;white-space:pre-line;font-size:.95rem;line-height:1.65;color:var(--muted)}

/* ---------- Skills ---------- */
.cd-skills-desc{margin-top:1rem;white-space:pre-line;line-height:1.7;color:var(--muted)}
.cd-skill-list{display:grid;gap:.75rem;margin-top:1.75rem}
@media(min-width:640px){.cd-root[data-view="skills"] .cd-skill-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.cd-root[data-view="skills"] .cd-skill-list{grid-template-columns:repeat(3,minmax(0,1fr))}}
.cd-skill{border:1px solid var(--line);border-radius:.95rem;background:var(--paper);padding:.9rem 1rem 1rem;transition:border-color .3s ease,transform .35s cubic-bezier(.2,.7,.2,1)}
.cd-skill:hover{border-color:var(--accent);transform:translateY(-2px)}
.cd-skill-row{display:flex;align-items:center;gap:.75rem}
.cd-skill-name{font-size:.95rem;font-weight:600}
.cd-skill-pct{margin-left:auto;font-size:.78rem;font-weight:500;color:var(--muted)}
.cd-skill-row>.cd-pill{margin-left:auto}
.cd-tile{display:inline-flex;height:2rem;width:2rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:.65rem;background:color-mix(in srgb,var(--accent) 14%,transparent);color:var(--accent);transition:background-color .3s ease,color .3s ease,transform .35s cubic-bezier(.2,.7,.2,1)}
.cd-tile-lg{height:2.9rem;width:2.9rem;border-radius:.9rem}
.cd-tile-letter{font-size:.875rem;line-height:1}
.cd-tile-glyph{font-size:1rem;line-height:1}
.cd-tile-glyph-lg{font-size:1.25rem;line-height:1}
.cd-skill:hover .cd-tile,.cd-service:hover .cd-tile{background:var(--accent);color:var(--accent-ink);transform:rotate(-6deg) scale(1.06)}
.cd-track{position:relative;height:6px;width:100%;margin-top:.75rem;overflow:hidden;border-radius:999px;background:color-mix(in srgb,var(--ink) 9%,transparent)}
.cd-bar{position:relative;height:100%;width:var(--w,0%);border-radius:inherit;background:linear-gradient(90deg,var(--accent),var(--accent-2))}
.cd-js [data-reveal] .cd-bar{width:0;transition:width 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s)}
.cd-js [data-reveal].is-in .cd-bar{width:var(--w,0%)}
@property --n{syntax:'<integer>';inherits:false;initial-value:0}
.cd-count{--n:var(--p,0);counter-reset:n var(--n);font-variant-numeric:tabular-nums}
.cd-count::after{content:counter(n) '%'}
.cd-js [data-reveal] .cd-count{--n:0}
.cd-js [data-reveal].is-in .cd-count{animation:cd-count 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s) forwards}

/* ---------- Projects ---------- */
.cd-proj-head{display:grid;gap:1.25rem}
@media(min-width:1024px){.cd-proj-head{grid-template-columns:repeat(12,minmax(0,1fr));align-items:end}.cd-proj-title{grid-column:span 7/span 7}.cd-proj-desc{grid-column:span 5/span 5}}
.cd-proj-desc{white-space:pre-line;line-height:1.7;color:var(--muted)}
.cd-filters{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:1.75rem;border-bottom:1px solid var(--line);padding-bottom:1rem}
.cd-filter{display:inline-flex;align-items:center;gap:.4rem;border-radius:.65rem;border:1px solid var(--line);background:var(--paper);padding:.38rem .8rem;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.74rem;font-weight:500;color:var(--muted);cursor:pointer;transition:background-color .25s ease,color .25s ease,border-color .25s ease}
.cd-filter:hover{color:var(--ink);border-color:var(--accent)}
.cd-filter[aria-selected='true']{background:var(--accent);border-color:var(--accent);color:var(--accent-ink)}
.cd-project-grid{display:grid;gap:1.25rem;margin-top:2rem}
.cd-project-item{min-width:0}
@media(min-width:768px){.cd-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cd-project-grid>.cd-feature{grid-column:span 2/span 2}}
@media(min-width:1280px){.cd-project-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.cd-project-grid.is-filtered>.cd-feature{grid-column:auto}
.cd-project{display:flex;height:100%;flex-direction:column;overflow:hidden;border:1px solid var(--line);border-radius:1.1rem;background:var(--paper);transition:transform .45s cubic-bezier(.2,.7,.2,1),border-color .3s ease,box-shadow .45s ease}
.cd-project:hover{transform:translateY(-5px);border-color:color-mix(in srgb,var(--accent) 60%,var(--line));box-shadow:0 26px 44px -26px color-mix(in srgb,var(--accent) 55%,transparent)}
.cd-media{position:relative;display:block;overflow:hidden;aspect-ratio:16/10;background:var(--soft);border-bottom:1px solid var(--line);isolation:isolate}
.cd-feature .cd-media{aspect-ratio:16/8}
.cd-project-grid.is-filtered .cd-feature .cd-media{aspect-ratio:16/10}
.cd-project-grid.is-filtered .cd-flag{display:none}
.cd-media img{width:100%;height:100%;object-fit:cover}
.cd-media img,.cd-tone{transition:transform .9s cubic-bezier(.2,.7,.2,1)}
.cd-project:hover .cd-media img,.cd-project:hover .cd-tone{transform:scale(1.06)}
.cd-tone{display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:4.5rem;line-height:1}
.cd-tone-0{background:var(--accent);color:var(--accent-ink)}
.cd-tone-1{background:var(--accent-2);color:var(--accent-ink)}
.cd-tone-2{background:var(--ink);color:var(--paper)}
.cd-flag{position:absolute;left:.8rem;top:.8rem;display:inline-flex;align-items:center;gap:.35rem;border-radius:.55rem;background:var(--accent-2);color:var(--accent-ink);padding:.2rem .6rem;font-size:.72rem;font-weight:500}
.cd-go{position:absolute;right:.8rem;top:.8rem;display:inline-flex;height:2.4rem;width:2.4rem;align-items:center;justify-content:center;border-radius:.75rem;background:var(--paper);color:var(--ink);opacity:0;transform:translate(-.5rem,.5rem) scale(.9);transition:opacity .35s ease,transform .45s cubic-bezier(.2,.7,.2,1)}
.cd-project:hover .cd-go,.cd-media:focus-visible .cd-go{opacity:1;transform:none}
.cd-proj-body{display:flex;flex:1;flex-direction:column;padding:1.25rem}
.cd-proj-name{font-size:1.25rem;line-height:1.25}
.cd-underline{background:linear-gradient(currentColor,currentColor) left 96%/0% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1)}
.cd-project:hover .cd-underline{background-size:100% 2px}
.cd-proj-text{margin-top:.5rem;font-size:.95rem;line-height:1.6;color:var(--muted)}
.cd-tags{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
.cd-repo{margin-top:auto;padding-top:1rem}
.cd-repo .cd-textlink{font-size:.78rem}

/* ---------- Services ---------- */
.cd-service-grid{display:grid;gap:1rem;margin-top:2rem}
@media(min-width:640px){.cd-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.cd-service-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.cd-service{display:flex;height:100%;flex-direction:column;border:1px solid var(--line);border-radius:1.1rem;background:var(--paper);padding:1.4rem;transition:transform .4s cubic-bezier(.2,.7,.2,1),border-color .3s ease,box-shadow .4s ease}
.cd-service:hover{transform:translateY(-4px);border-color:color-mix(in srgb,var(--accent) 60%,var(--line));box-shadow:0 22px 38px -26px var(--accent)}
.cd-service-title{margin-top:1.25rem;font-size:1.25rem;line-height:1.25;transition:color .3s ease}
.cd-service:hover .cd-service-title{color:var(--accent)}
.cd-service-text{margin-top:.5rem;font-size:.95rem;line-height:1.6;color:var(--muted)}
.cd-service-arrow{margin-top:auto;align-self:flex-end;color:var(--accent);opacity:0;transform:translate(-.5rem,.5rem);transition:opacity .3s ease,transform .4s cubic-bezier(.2,.7,.2,1)}
.cd-service:hover .cd-service-arrow{opacity:1;transform:none}

/* ---------- Contact ---------- */
.cd-contact-grid{display:grid;gap:3rem}
@media(min-width:1024px){.cd-contact-grid{grid-template-columns:repeat(12,minmax(0,1fr))}.cd-col-7{grid-column:span 7/span 7}.cd-col-5{grid-column:span 5/span 5}}
.cd-contact-title{font-size:clamp(2.25rem,5.4vw,4.25rem);line-height:1;letter-spacing:-.045em;overflow-wrap:anywhere}
.cd-contact-text{max-width:34rem;margin-top:1.25rem;white-space:pre-line;font-size:1.05rem;line-height:1.7;color:var(--muted)}
.cd-contact-info{margin-top:2rem}
.cd-contact-info>*+*{margin-top:1rem}
.cd-bigmail{display:inline-flex;max-width:100%;align-items:center;gap:.75rem;word-break:break-all;font-size:1.25rem}
@media(min-width:640px){.cd-bigmail{font-size:1.875rem}}
.cd-bigmail>.cd-ico:first-child{color:var(--accent)}
.cd-underline-contact{background:linear-gradient(currentColor,currentColor) left bottom/100% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1);padding-bottom:4px}
.cd-bigmail:hover .cd-underline-contact{background-size:0% 2px;background-position:right bottom}
.cd-contact-meta{display:flex;flex-wrap:wrap;gap:.75rem 2rem;font-size:.85rem}
.cd-contact-meta>span{display:inline-flex;align-items:center;gap:.5rem}
.cd-contact-meta .cd-ico{color:var(--accent)}
.cd-contact-link{display:inline-flex;align-items:center;gap:.5rem}
.cd-contact-link:hover{text-decoration:underline;text-underline-offset:4px}
.cd-contact-socials{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.75rem}
.cd-form{border:1px solid var(--line);border-radius:1.1rem;background:var(--paper);padding:1.5rem}
.cd-field{display:block;margin-bottom:1.25rem}
.cd-label{display:block;margin-bottom:.5rem;font-size:.7rem;font-weight:500;text-transform:uppercase;letter-spacing:.12em;color:var(--muted)}
.cd-input{width:100%;border-radius:.75rem;border:1px solid var(--line);background:var(--surface);padding:.75rem .95rem;font:inherit;font-weight:500;color:var(--ink);transition:border-color .25s ease,box-shadow .25s ease}
.cd-input:hover{border-color:color-mix(in srgb,var(--ink) 35%,transparent)}
.cd-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 22%,transparent)}
textarea.cd-input{resize:vertical}
.cd-sent{display:flex;align-items:center;gap:.5rem;margin-top:1.25rem;font-size:.875rem;font-weight:600;animation:cd-pop .5s cubic-bezier(.2,.8,.2,1) both}
.cd-hidden-frame{display:none}
.cd-foot{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.5rem .25rem 1rem}
.cd-foot-copy{font-size:.75rem;color:var(--muted)}

/* ---------- Scroll reveal (class is added by script.js, so no-JS output stays visible) ---------- */
.cd-js [data-reveal]{opacity:0;transform:translateY(20px);transition:opacity .75s cubic-bezier(.2,.7,.2,1) var(--d,0ms),transform .75s cubic-bezier(.2,.7,.2,1) var(--d,0ms)}
.cd-js [data-reveal].is-in{opacity:1;transform:none}
.cd-js [data-reveal].is-in .cd-hl{animation:cd-slide-x .7s cubic-bezier(.2,.7,.2,1) calc(var(--k,0)*80ms + 150ms) backwards}
.cd-js [data-reveal].is-in .cd-hl .cd-tick{animation:cd-tick .6s cubic-bezier(.3,1.6,.4,1) calc(var(--k,0)*80ms + 400ms) backwards}
.cd-js [data-reveal].is-in .cd-tile{animation:cd-tile .7s cubic-bezier(.3,1.5,.4,1) calc(var(--d,0ms) + .15s) backwards}
.cd-js [data-reveal].is-in .cd-media{animation:cd-wipe 1.1s cubic-bezier(.7,0,.2,1) .1s backwards}
.cd-js [data-reveal].is-in .cd-media img{animation:cd-settle 1.5s cubic-bezier(.2,.7,.2,1) .1s backwards}
.cd-js [data-reveal].is-in .cd-avatar{animation:cd-pop .9s cubic-bezier(.2,.8,.2,1) .2s backwards}
.cd-js.cd-root{animation:cd-enter .6s ease both}
@keyframes cd-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes cd-pop{from{opacity:0;transform:translateY(8px) scale(.96)}}
@keyframes cd-count{to{--n:var(--p)}}
@keyframes cd-slide-x{from{opacity:0;transform:translateX(-16px)}}
@keyframes cd-tick{from{transform:scale(0) rotate(-90deg)}}
@keyframes cd-tile{from{transform:scale(.4) rotate(-20deg);opacity:0}}
@keyframes cd-wipe{from{clip-path:inset(0 0 100% 0)}}
@keyframes cd-settle{from{transform:scale(1.2)}}
@keyframes cd-enter{from{opacity:0}}

/* ---------- Reduced motion ---------- */
@media(prefers-reduced-motion:reduce){
html{scroll-behavior:auto}
.cd-root *,.cd-root *::before,.cd-root *::after{animation:none!important;transition:none!important}
.cd-js [data-reveal],.cd-js [data-reveal]:not(.is-in) .cd-hl{opacity:1;transform:none}
.cd-js [data-reveal] .cd-count{--n:var(--p,0)}
.cd-js [data-reveal] .cd-bar{width:var(--w,0%)}
.cd-js [data-reveal]:not(.is-in) .cd-spark i{transform:none}
}
CSS;
    }
}
