<?php

namespace App\Http\Controllers\PortfolioExport;

trait StreamBandsTemplate
{
    private function renderStreamBandsPage(string $title, array $content, array $assets, ?array $googleForm): string
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
        $icon = fn (string $name, int $size = 16, string $class = '', string $stroke = '2'): string => $this->streamBandsIcon($name, $size, $class, $stroke);
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

        // Band alternation + section numbers follow whichever sections are visible after the hero.
        $present = ['about' => $hasAbout, 'journey' => $hasJourney, 'skills' => $hasSkills, 'projects' => $hasProjects, 'services' => $hasServices, 'contact' => $hasContact];
        $orderedIds = array_values(array_keys(array_filter($present)));
        $bandOf = static function (string $id) use ($orderedIds): string {
            $position = array_search($id, $orderedIds, true);

            return $position !== false && $position % 2 === 0 ? 'is-paper' : 'is-surface';
        };
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
        $contactLabel = $text($contact, 'contact_subtitle') ?? $text($contact, 'contact_title') ?? $email ?? '';
        $formReady = $flag($contact, 'contact_form_enabled', true) && $googleForm !== null;

        $navLinks = array_values(array_filter([
            ['id' => 'about', 'label' => 'About', 'present' => $hasAbout],
            ['id' => 'journey', 'label' => 'Journey', 'present' => $hasJourney],
            ['id' => 'skills', 'label' => 'Skills', 'present' => $hasSkills],
            ['id' => 'projects', 'label' => 'Work', 'present' => $hasProjects],
            ['id' => 'services', 'label' => 'Services', 'present' => $hasServices],
        ], static fn (array $definition): bool => $definition['present']));

        // Shared section header: number + rule + subtitle.
        $head = static fn (string $id, ?string $subtitle): string => '<div class="sb-head"><span class="sb-index sb-mono">'.e($numberOf($id)).'</span>'
            .($subtitle !== null ? '<p class="sb-kicker">'.e($subtitle).'</p>' : '').'</div>';
        $watermark = static fn (?string $label): string => $label !== null ? '<span class="sb-watermark" aria-hidden="true">'.e($label).'</span>' : '';

        /* ---------------------------------------------------------------
         | Markup
         * ------------------------------------------------------------- */
        $html = '<div class="sb-root">';

        // Navbar
        if ($logoImage !== '' || $logoText !== null || $navLinks !== [] || $hasContact) {
            $html .= '<header class="sb-header '.($stickyNav ? 'is-fixed' : 'is-absolute').'"><div class="sb-progress" data-progress aria-hidden="true"></div>';
            $html .= '<nav class="sb-nav" data-nav aria-label="Primary"><div class="sb-nav-inner">';
            if ($logoImage !== '' || $logoText !== null) {
                $html .= '<a href="#header" class="sb-logo" data-scroll-link>';
                if ($logoImage !== '') {
                    $html .= '<img src="'.$escape($logoImage).'" alt="'.$escape($logoText ?? '').'" class="sb-logo-img">';
                }
                if ($logoText !== null) {
                    $html .= '<span class="sb-display sb-logo-text">'.$escape($logoText).'</span>';
                }
                $html .= '</a>';
            } else {
                $html .= '<span></span>';
            }
            if ($navLinks !== []) {
                $html .= '<ul class="sb-navlinks">';
                foreach ($navLinks as $link) {
                    $html .= '<li><a href="#'.$escape($link['id']).'" class="sb-navlink" data-active="false" data-section-link="'.$escape($link['id']).'" data-scroll-link>'.$escape($link['label']).'</a></li>';
                }
                $html .= '</ul>';
            }
            if ($hasContact) {
                $html .= '<a href="#contact" class="sb-btn sb-btn-primary sb-btn-sm" data-scroll-link>'.$escape('Contact me').$icon('arrow-up-right', 16, 'sb-arrow').'</a>';
            } else {
                $html .= '<span></span>';
            }
            $html .= '</div></nav></header>';
        }

        // Hero
        if ($hasHeader) {
            $html .= '<section id="header" class="sb-hero" data-page-section><span id="home" class="sb-home-anchor" aria-hidden="true"></span>';
            if ($heroBackground !== '') {
                $html .= '<img src="'.$escape($heroBackground).'" alt="" class="sb-hero-bg">';
            }
            if ($heroName !== null && isset($heroWords[0])) {
                $html .= '<span class="sb-watermark sb-watermark-hero" aria-hidden="true">'.$escape($heroWords[0]).'</span>';
            }
            $html .= '<div class="sb-hero-grid"><div class="sb-hero-main">';
            if ($greeting !== null) {
                $html .= '<p data-reveal class="sb-label" style="--d: 0ms"><span class="sb-pulse" aria-hidden="true"></span>'.$escape($greeting).'</p>';
            }
            if ($heroName !== null) {
                $html .= '<h1 class="sb-display sb-hero-name"><span class="sr-only">'.$escape($heroName).'</span>';
                foreach ($heroWords as $index => $word) {
                    $html .= '<span class="sb-mask" aria-hidden="true"><span class="sb-word'.($index % 2 === 1 ? ' sb-outline-text' : '').'" style="--i: '.$index.'">'.$escape($word).'</span></span>';
                }
                $html .= '</h1>';
            }
            $html .= '<div class="sb-hero-sub">';
            if ($role !== null) {
                $html .= '<p data-reveal class="sb-mono sb-role sb-m4" style="--d: 380ms"><span class="sb-bar-mark" aria-hidden="true"></span>'.$escape($role).'</p>';
            }
            $html .= '<div class="sb-m8">';
            if ($heroDescription !== null) {
                $html .= '<p data-reveal class="sb-muted sb-hero-desc" style="--d: 460ms">'.$escape($heroDescription).'</p>';
            }
            if ($heroPrimary !== null || $heroSecondary !== null) {
                $html .= '<div data-reveal class="sb-actions" style="--d: 540ms">';
                if ($heroPrimary !== null) {
                    $html .= '<a href="'.$url($heroPrimary['url']).'" class="sb-btn sb-btn-primary" data-scroll-link>'.$escape($heroPrimary['text']).$icon('arrow-right', 16, 'sb-arrow').'</a>';
                }
                if ($heroSecondary !== null) {
                    $html .= '<a href="'.$url($heroSecondary['url']).'" class="sb-btn sb-btn-ghost" data-scroll-link>'.$escape($heroSecondary['text']).'</a>';
                }
                $html .= '</div>';
            }
            if ($heroSocials !== []) {
                $html .= '<ul data-reveal class="sb-icons" style="--d: 620ms">';
                foreach ($heroSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($social['label']).'" title="'.$escape($social['label']).'" class="sb-icon-btn">'.$icon($socialIcon($social['platform']), 18).'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div></div></div>';

            $html .= '<div class="sb-hero-aside"><figure data-reveal class="sb-float" style="--d: 250ms"><div class="sb-mask-offset" aria-hidden="true"></div><div class="sb-mask-frame">';
            if ($heroImage !== '') {
                $html .= '<img src="'.$escape($heroImage).'" alt="'.$escape($heroName ?? '').'">';
            } elseif ($heroInitials !== '') {
                $html .= '<span class="sb-display sb-initials">'.$escape($heroInitials).'</span>';
            }
            $html .= '</div>';
            if ($location !== null) {
                $html .= '<figcaption class="sb-caption sb-mono">'.$icon('map-pin', 14).$escape($location).'</figcaption>';
            }
            $html .= '</figure></div></div></section>';
        }

        // About
        if ($hasAbout) {
            $html .= '<section id="about" class="sb-band '.$bandOf('about').'" data-page-section>'.$watermark($text($about, 'about_title'));
            $html .= '<div class="sb-in sb-grid sb-grid-about">';
            if ($aboutImage !== '') {
                $html .= '<div data-reveal class="sb-c4"><div class="sb-frame"><img src="'.$escape($aboutImage).'" alt="'.$escape($text($about, 'about_title') ?? '').'" class="sb-arch" loading="lazy"></div></div>';
            }
            $html .= '<div class="'.($aboutImage !== '' ? 'sb-c8' : 'sb-c10-s2').'"><div data-reveal>'.$head('about', $text($about, 'about_subtitle'));
            if ($text($about, 'about_title') !== null) {
                $html .= '<h2 class="sb-display sb-h2 sb-mt5">'.$escape($text($about, 'about_title')).'</h2>';
            }
            if ($text($about, 'about_description') !== null) {
                $html .= '<p class="sb-muted sb-prose">'.$escape($text($about, 'about_description')).'</p>';
            }
            $html .= '</div>';
            if ($highlights !== []) {
                $html .= '<ul data-reveal class="sb-hl-list">';
                foreach ($highlights as $index => $highlight) {
                    $html .= '<li class="sb-hl sb-stagger-'.($index % 3).'" style="--k: '.$index.'"><span class="sb-tick">'.$icon('check', 14, '', '3').'</span><span class="sb-hl-title">'.$escape($highlight['title']).'</span>';
                    if ($highlight['description'] !== null) {
                        $html .= '<span class="sb-muted sb-hl-desc">'.$escape($highlight['description']).'</span>';
                    }
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }
            if ($cvFile !== '' || $aboutCta !== null) {
                $html .= '<div data-reveal class="sb-actions sb-actions-lg">';
                if ($aboutCta !== null) {
                    $html .= '<a href="'.$url($aboutCta['url']).'" class="sb-btn '.($cvFile !== '' ? 'sb-btn-ghost' : 'sb-btn-primary').'" data-scroll-link>'.$escape($aboutCta['text']).$icon('arrow-right', 16, 'sb-arrow').'</a>';
                }
                elseif ($cvFile !== '') {
                    $html .= '<a href="'.$escape($cvFile).'" download target="_blank" rel="noopener" class="sb-btn sb-btn-primary">'.$icon('download', 16).'Download CV</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></section>';
        }

        // Journey (horizontal progression)
        if ($hasJourney) {
            $html .= '<section id="journey" class="sb-band '.$bandOf('journey').'" data-page-section>'.$watermark($text($journeyData, 'journey_title'));
            $html .= '<div class="sb-in sb-block"><div data-reveal>'.$head('journey', $text($journeyData, 'journey_subtitle')).'</div>';
            if ($text($journeyData, 'journey_title') !== null) {
                $html .= '<h2 data-reveal class="sb-display sb-h2 sb-mt5 sb-maxw4xl">'.$escape($text($journeyData, 'journey_title')).'</h2>';
            }
            $html .= '<div class="sb-rail"><ol class="sb-track-h">';
            foreach ($journey as $index => $item) {
                $html .= '<li data-reveal style="--d: '.(($index % 4) * 80).'ms" class="sb-step sb-drop-'.($index % 2).'"><span class="sb-node" aria-hidden="true"><i></i></span><div class="sb-step-card">';
                if ($item['period'] !== null) {
                    $html .= '<p class="sb-mono sb-period">'.$escape($item['period']).'</p>';
                }
                if ($item['title'] !== null) {
                    $html .= '<h3 class="sb-display sb-item-title">'.$escape($item['title']).'</h3>';
                }
                if ($item['company'] !== null) {
                    $html .= '<p class="sb-company">'.$escape($item['company']).'</p>';
                }
                if ($item['description'] !== null) {
                    $html .= '<p class="sb-muted sb-item-desc">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></li>';
            }
            $html .= '</ol></div></div></section>';
        }

        // Skills
        if ($hasSkills) {
            $html .= '<section id="skills" class="sb-band '.$bandOf('skills').'" data-page-section>'.$watermark($text($skillsData, 'skills_title'));
            $html .= '<div class="sb-in sb-grid sb-grid-skills"><div class="sb-c4"><div data-reveal class="sb-sticky">'.$head('skills', $text($skillsData, 'skills_subtitle'));
            if ($text($skillsData, 'skills_title') !== null) {
                $html .= '<h2 class="sb-display sb-h2 sb-mt5">'.$escape($text($skillsData, 'skills_title')).'</h2>';
            }
            if ($text($skillsData, 'skills_description') !== null) {
                $html .= '<p class="sb-muted sb-skills-desc">'.$escape($text($skillsData, 'skills_description')).'</p>';
            }
            $html .= '</div></div><ul class="sb-skill-list">';
            foreach ($skills as $index => $skill) {
                $html .= '<li data-reveal style="--d: '.(($index % 6) * 50).'ms" class="sb-skill sb-stagger-'.($index % 3).'"><div class="sb-skill-row">';
                if ($showIcon) {
                    $skillIcon = $iconFor($skill['icon']);
                    $html .= '<span class="sb-tile">';
                    if ($skillIcon !== null) {
                        $html .= $icon($skillIcon, 18);
                    } elseif ($isGlyph($skill['icon'])) {
                        $html .= '<span class="sb-tile-glyph" aria-hidden="true">'.$escape($skill['icon']).'</span>';
                    } else {
                        $html .= '<span class="sb-display sb-tile-letter" aria-hidden="true">'.$escape(mb_strtoupper(mb_substr($skill['name'], 0, 1))).'</span>';
                    }
                    $html .= '</span>';
                }
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<span class="sb-count sb-mono sb-muted sb-skill-pct" style="--p: '.(int) round($skill['percent']).'" aria-hidden="true"></span>';
                } elseif ($showLevel && $skill['label'] !== null) {
                    $html .= '<span class="sb-pill sb-pill-end">'.$escape($skill['label']).'</span>';
                }
                $html .= '</div><h3 class="sb-skill-name">'.$escape($skill['name']).'</h3>';
                if ($showLevel && $skill['percent'] !== null) {
                    $html .= '<div class="sb-track" role="progressbar" aria-valuenow="'.(int) round($skill['percent']).'" aria-valuemin="0" aria-valuemax="100" aria-label="'.$escape($skill['name']).'"><div class="sb-bar" style="--w: '.$num($skill['percent']).'%"></div></div>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></div></section>';
        }

        // Projects
        if ($hasProjects) {
            $html .= '<section id="projects" class="sb-band '.$bandOf('projects').'" data-page-section>'.$watermark($text($projectData, 'projects_title'));
            $html .= '<div class="sb-in sb-block"><div data-reveal>'.$head('projects', $text($projectData, 'projects_subtitle')).'</div>';
            $html .= '<div data-reveal class="sb-proj-head">';
            if ($text($projectData, 'projects_title') !== null) {
                $html .= '<h2 class="sb-display sb-h2 sb-c8">'.$escape($text($projectData, 'projects_title')).'</h2>';
            }
            if ($text($projectData, 'projects_description') !== null) {
                $html .= '<p class="sb-muted sb-proj-desc sb-c4">'.$escape($text($projectData, 'projects_description')).'</p>';
            }
            $html .= '</div><ul class="sb-project-grid">';
            foreach ($projects as $index => $project) {
                $featured = $featureFirst && $index === 0;
                $html .= '<li data-reveal class="sb-offset-'.($index % 3).($featured ? ' sb-feature' : '').'" style="--d: '.(($index % 3) * 80).'ms"><article class="sb-project">';
                if ($project['tags'] !== []) {
                    $html .= '<ul class="sb-ribbon" aria-label="Tags">';
                    foreach (array_slice($project['tags'], 0, 3) as $tag) {
                        $html .= '<li class="sb-mono">'.$escape($tag).'</li>';
                    }
                    $html .= '</ul>';
                }
                $mediaClass = 'sb-media '.($featured ? 'sb-media-wide' : 'sb-media-std');
                if ($project['live'] !== '') {
                    $html .= '<a href="'.$escape($project['live']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($project['title']).'" class="'.$mediaClass.'">';
                } else {
                    $html .= '<div class="'.$mediaClass.'">';
                }
                if ($project['image'] !== '') {
                    $html .= '<img src="'.$escape($project['image']).'" alt="'.$escape($project['title']).'" loading="lazy">';
                } else {
                    $html .= '<div class="sb-display sb-tone sb-tone-'.($index % 3).'">'.$escape(mb_strtoupper(mb_substr($project['title'], 0, 1))).'</div>';
                }
                if ($featured) {
                    $html .= '<span class="sb-flag sb-mono">'.$icon('sparkles', 14).$pad($index + 1).'</span>';
                }
                if ($project['live'] !== '') {
                    $html .= '<span class="sb-go" aria-hidden="true">'.$icon('arrow-up-right', 20).'</span>';
                }
                $html .= $project['live'] !== '' ? '</a>' : '</div>';

                $html .= '<div class="sb-project-body"><h3 class="sb-display sb-proj-name"><span class="sb-underline">'.$escape($project['title']).'</span></h3>';
                if ($project['description'] !== null) {
                    $html .= '<p class="sb-muted sb-proj-text">'.$escape($project['description']).'</p>';
                }
                if ($project['repo'] !== '') {
                    $html .= '<div class="sb-repo"><a href="'.$escape($project['repo']).'" target="_blank" rel="noopener noreferrer" class="sb-textlink sb-mono">'.$icon('code', 16).$escape($project['repo_label']).'</a></div>';
                }
                $html .= '</div></article></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Services
        if ($hasServices) {
            $html .= '<section id="services" class="sb-band '.$bandOf('services').'" data-page-section>'.$watermark($text($servicesData, 'services_title'));
            $html .= '<div class="sb-in sb-block"><div data-reveal>'.$head('services', $text($servicesData, 'services_subtitle')).'</div>';
            if ($text($servicesData, 'services_title') !== null) {
                $html .= '<h2 data-reveal class="sb-display sb-h2 sb-mt5 sb-mb16 sb-maxw4xl">'.$escape($text($servicesData, 'services_title')).'</h2>';
            }
            $html .= '<ul class="sb-service-grid">';
            foreach ($services as $index => $service) {
                $html .= '<li data-reveal style="--d: '.(($index % 6) * 70).'ms" class="sb-stagger-'.($index % 3).'"><div class="sb-service"><span class="sb-mono sb-service-num" aria-hidden="true">'.$pad($index + 1).'</span><span class="sb-tile sb-tile-lg">';
                if (! $isGlyph($service['icon'])) {
                    $html .= $icon($iconFor($service['icon']) ?? 'sparkles', 24);
                } else {
                    $html .= '<span class="sb-tile-glyph-lg" aria-hidden="true">'.$escape($service['icon']).'</span>';
                }
                $html .= '</span><h3 class="sb-display sb-service-title">'.$escape($service['title']).'</h3>';
                if ($service['description'] !== null) {
                    $html .= '<p class="sb-muted sb-service-text">'.$escape($service['description']).'</p>';
                }
                $html .= $icon('arrow-up-right', 24, 'sb-service-arrow').'</div></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Contact (+ footer)
        if ($hasContact) {
            $html .= '<section id="contact" class="sb-band sb-contact" data-page-section>'.$watermark($text($contact, 'contact_title'));
            $html .= '<div class="sb-in sb-grid sb-grid-contact"><div class="sb-c7"><div data-reveal>'.$head('contact', $text($contact, 'contact_subtitle'));
            if ($text($contact, 'contact_title') !== null) {
                $html .= '<h2 class="sb-display sb-contact-title">'.$escape($text($contact, 'contact_title')).'</h2>';
            }
            if ($text($contact, 'contact_description') !== null) {
                $html .= '<p class="sb-muted sb-contact-text">'.$escape($text($contact, 'contact_description')).'</p>';
            }
            $html .= '</div>';
            if ($emailOk || $phoneOk || $location !== null) {
                $html .= '<div data-reveal class="sb-contact-info">';
                if ($emailOk) {
                    $html .= '<a href="'.$escape($emailHref).'" class="sb-bigmail sb-display">'.$icon('mail', 28, 'sb-accent').'<span class="sb-underline-contact">'.$escape($email).'</span>'.$icon('arrow-up-right', 28, 'sb-arrow').'</a>';
                }
                if ($phoneOk || $location !== null) {
                    $html .= '<div class="sb-contact-meta">';
                    if ($phoneOk) {
                        $html .= '<a href="'.$escape($phoneHref).'" class="sb-contact-link">'.$icon('phone', 16, 'sb-accent').$escape($phone).'</a>';
                    }
                    if ($location !== null) {
                        $html .= '<span>'.$icon('map-pin', 16, 'sb-accent').$escape($location).'</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            if ($contactSocials !== []) {
                $html .= '<ul data-reveal class="sb-contact-socials">';
                foreach ($contactSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" class="sb-social">'.$icon($socialIcon($social['platform']), 14).$escape($social['label']).$icon('arrow-up-right', 14, 'sb-arrow').'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><div class="sb-c5"><div data-reveal style="--d: 120ms">';

            if ($formReady) {
                $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="sb-gform-frame" class="sb-form" data-google-form>
                <label class="sb-field"><span class="sb-mono sb-muted sb-label-text">Name</span><input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="sb-input"></label>
                <label class="sb-field"><span class="sb-mono sb-muted sb-label-text">Email</span><input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="sb-input"></label>
                <label class="sb-field"><span class="sb-mono sb-muted sb-label-text">Message</span><textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required class="sb-input"></textarea></label>
                <button type="submit" class="sb-btn sb-btn-primary sb-btn-block"><span data-submit-label>Send</span>'.$icon('send', 16, 'sb-arrow').'</button>
                <p class="sb-sent" role="status" data-form-status hidden><span class="sb-tick sb-tick-sm">'.$icon('check', 14, '', '3').'</span>OK</p>
            </form>';
            } elseif ($emailOk) {
                $html .= '<a href="'.$escape($emailHref).'" class="sb-btn sb-btn-ghost sb-btn-block sb-btn-xl">'.$icon('mail', 20).$escape($email).'</a>';
            }
            $html .= '</div>';
            if ($formReady) {
                $html .= '<iframe name="sb-gform-frame" title="Form submission target" class="sb-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
            }
            $html .= '</div></div>';

            if ($heroName !== null || $logoText !== null) {
                $html .= '<footer class="sb-in sb-foot"><span class="sb-muted sb-mono sb-foot-copy">&copy; '.date('Y').' '.$escape($heroName ?? $logoText).'</span><a href="#header" class="sb-textlink" aria-label="Top" data-scroll-link>'.$icon('arrow-up-right', 16, 'sb-arrow sb-rot').'</a></footer>';
            }
            $html .= '</section>';
        }
        $html .= '</div>';

        return '<!doctype html>
<html lang="en" data-template="stream-bands">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f4f1ea" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e0d0b" media="(prefers-color-scheme: dark)">
    <title>'.$escape($title).'</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    /** Inline Lucide-style icons (static, trusted markup only). */
    private function streamBandsIcon(string $name, int $size = 16, string $class = '', string $stroke = '2'): string
    {
        $paths = [
            'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
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

        return '<svg class="sb-ico'.($class !== '' ? ' '.$class : '').'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$stroke.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$body.'</svg>';
    }

    private function streamBandsStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=Instrument+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap');
/* ---------- Reset (Tailwind-preflight equivalent, low specificity) ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;color-scheme:light dark;-webkit-text-size-adjust:100%}
body{margin:0;background:#f4f1ea}
@media(prefers-color-scheme:dark){body{background:#0e0d0b}}
:where(.sb-root) :is(h1,h2,h3,p,ul,ol,figure){margin:0}
:where(.sb-root) :is(ul,ol){padding:0;list-style:none}
:where(.sb-root) :is(h1,h2,h3){font-size:inherit;font-weight:inherit}
:where(.sb-root) a{color:inherit;text-decoration:none}
:where(.sb-root) :is(button,input,textarea){font:inherit;color:inherit;letter-spacing:inherit}
:where(.sb-root) button{background:none;border:0;padding:0;cursor:pointer}
:where(.sb-root) img,:where(.sb-root) svg{display:block;max-width:100%}
.sb-root [hidden]{display:none!important}
.sb-ico{flex-shrink:0}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

/* ---------- Foundations + palette (light, dark via prefers-color-scheme) ---------- */
.sb-root{--paper:#F4F1EA;--soft:#EAE6DB;--surface:#FFFFFF;--ink:#14130F;--muted:#14130FAD;--line:#14130F26;--accent:#D9480F;--accent-ink:#FFFFFF;position:relative;background:var(--paper);color:var(--ink);font-family:'Instrument Sans',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;overflow-x:clip;transition:background-color .4s ease,color .4s ease}
@media(prefers-color-scheme:dark){.sb-root{--paper:#0E0D0B;--soft:#15130F;--surface:#1A1814;--ink:#F3EFE6;--muted:#F3EFE6AD;--line:#F3EFE62B;--accent:#FF7A3D;--accent-ink:#0E0D0B}}
.sb-root ::selection{background:var(--accent);color:#fff}
.sb-display{font-family:'Syne','Instrument Sans',ui-sans-serif,system-ui,sans-serif;font-weight:700;letter-spacing:-.04em}
.sb-mono{font-family:'DM Mono',ui-monospace,SFMono-Regular,Menlo,monospace}
.sb-muted{color:var(--muted)}
.sb-accent{color:var(--accent)}
.sb-mt5{margin-top:1.25rem}
.sb-mb16{margin-bottom:4rem}
.sb-maxw4xl{max-width:56rem}
.sb-h2{font-size:clamp(2.4rem,5.4vw,4.6rem);line-height:1;letter-spacing:-.045em;text-wrap:balance;transition:letter-spacing 1.2s cubic-bezier(.2,.7,.2,1)}
.sb-root :where(a,button,input,textarea):focus-visible{outline:2px solid var(--accent);outline-offset:3px}

/* Full-bleed bands alternating like slides */
.sb-band{position:relative;overflow:hidden;border-block:1px solid var(--line)}
.sb-band.is-paper{background:var(--paper)}
.sb-band.is-surface{background:var(--surface)}
.sb-contact{background:var(--soft)}
.sb-band>.sb-in{position:relative;z-index:1}
.sb-in{margin-inline:auto;width:100%;max-width:1320px;padding-inline:1.25rem}
@media(min-width:640px){.sb-in{padding-inline:2rem}}
.sb-block{padding-block:7rem}

/* Giant outline watermark titles */
.sb-watermark{position:absolute;left:50%;top:50%;z-index:0;max-width:100%;transform:translate(-50%,-50%);overflow:hidden;white-space:nowrap;text-overflow:clip;font-family:'Syne',ui-sans-serif,system-ui,sans-serif;font-size:clamp(7rem,22vw,20rem);font-weight:800;line-height:.9;letter-spacing:-.05em;text-transform:uppercase;color:transparent;-webkit-text-stroke:1.5px var(--line);opacity:.9;pointer-events:none;user-select:none;transition:transform 1.4s cubic-bezier(.2,.7,.2,1)}
.sb-watermark-hero{top:auto;bottom:-1.5rem;transform:translate(-50%,0);font-size:clamp(9rem,28vw,26rem);z-index:-1}

/* Section header: number + rule + subtitle */
.sb-head{display:flex;align-items:center;gap:1rem}
.sb-index{font-size:.8rem;font-weight:500;color:var(--accent)}
.sb-kicker{display:inline-flex;align-items:center;gap:1rem;font-family:'DM Mono',ui-monospace,monospace;font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.1em}
.sb-kicker::before{content:'';width:2.5rem;height:1px;background:currentColor;transform-origin:left;transition:transform .8s cubic-bezier(.2,.7,.2,1) .2s}
.sb-js [data-reveal]:not(.is-in) .sb-kicker::before{transform:scaleX(0)}

/* ---------- Layout helpers ---------- */
.sb-grid{display:grid;gap:3rem}
.sb-grid-about{gap:3.5rem;padding-block:7rem}
.sb-grid-skills{padding-block:7rem}
.sb-grid-contact{gap:3.5rem;padding-block:6rem}
.sb-c4,.sb-c5,.sb-c7,.sb-c8,.sb-c10-s2{min-width:0}
@media(min-width:1024px){
.sb-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:2.5rem}
.sb-grid-contact{gap:3rem;padding-block:8rem}
.sb-c4{grid-column:span 4/span 4}.sb-c5{grid-column:span 5/span 5}.sb-c7{grid-column:span 7/span 7}.sb-c8{grid-column:span 8/span 8}.sb-c10-s2{grid-column:2/span 10}
.sb-sticky{position:sticky;top:7rem}
}
/* Staggered asymmetric offsets (tablet and up) */
@media(min-width:768px){
.sb-stagger-1{transform:translateY(1.75rem)}
.sb-stagger-2{transform:translateY(.75rem)}
.sb-offset-1{margin-top:3.5rem}
.sb-offset-2{margin-top:1.25rem}
}

/* ---------- Navbar ---------- */
.sb-header{position:fixed;inset:0 0 auto 0;z-index:50}
.sb-header.is-absolute{position:absolute}
.sb-progress{position:fixed;inset:0 0 auto 0;height:3px;background:var(--accent);transform:scaleX(0);transform-origin:left;pointer-events:none;z-index:60}
.sb-nav{border-bottom:1px solid transparent;transition:border-color .35s ease,background-color .35s ease}
.sb-nav.is-scrolled{border-color:var(--line);background:color-mix(in srgb,var(--paper) 85%,transparent);-webkit-backdrop-filter:blur(14px) saturate(1.3);backdrop-filter:blur(14px) saturate(1.3)}
.sb-nav-inner{display:flex;height:4rem;width:100%;max-width:1320px;margin-inline:auto;align-items:center;justify-content:space-between;gap:1rem;padding-inline:1.25rem}
@media(min-width:640px){.sb-nav-inner{padding-inline:2rem}}
.sb-logo{display:flex;min-width:0;align-items:center;gap:.75rem}
.sb-logo-img{height:1.75rem;width:auto;max-width:7rem;object-fit:contain}
.sb-logo-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:1.125rem}
.sb-navlinks{display:none;align-items:center;gap:.25rem}
@media(min-width:768px){.sb-navlinks{display:flex}}
.sb-navlink{display:inline-block;padding:.5rem .95rem;font-size:.92rem;font-weight:500;color:var(--muted);background:linear-gradient(var(--accent),var(--accent)) .95rem calc(100% - .2rem)/0% 2px no-repeat;transition:color .25s ease,background-size .4s cubic-bezier(.2,.7,.2,1)}
.sb-navlink:hover,.sb-navlink[data-active='true']{color:var(--ink)}
.sb-navlink[data-active='true']{background-size:calc(100% - 1.9rem) 2px}

/* ---------- Buttons & small controls ---------- */
.sb-btn{position:relative;isolation:isolate;display:inline-flex;align-items:center;gap:.55rem;border-radius:999px;padding:.9rem 1.6rem;font-size:.93rem;font-weight:600;line-height:1.2;cursor:pointer;border:1px solid transparent;background-repeat:no-repeat;background-position:left center;background-size:0% 100%;transition:background-size .5s cubic-bezier(.2,.7,.2,1),color .3s ease,transform .25s ease,border-color .3s ease}
.sb-btn:hover{background-size:100% 100%;transform:translateY(-2px)}
.sb-btn:active{transform:translateY(0) scale(.98)}
.sb-btn[disabled]{opacity:.7;pointer-events:none}
.sb-btn-primary{background-color:var(--accent);background-image:linear-gradient(var(--ink),var(--ink));color:var(--accent-ink)}
.sb-btn-primary:hover{color:var(--paper)}
.sb-btn-ghost{background-color:transparent;background-image:linear-gradient(var(--ink),var(--ink));border-color:var(--ink);color:var(--ink)}
.sb-btn-ghost:hover{color:var(--paper)}
.sb-btn-sm{padding:.625rem 1.25rem;font-size:.875rem}
.sb-btn-block{width:100%;justify-content:center}
.sb-btn-xl{padding-block:1.25rem;font-size:1.125rem}
.sb-arrow{transition:transform .3s cubic-bezier(.2,.7,.2,1)}
.sb-rot{transform:rotate(-45deg)}
.sb-bigmail:hover .sb-arrow,.sb-social:hover .sb-arrow,.sb-textlink:hover .sb-arrow{transform:translate(3px,-3px)}
.sb-btn:hover .sb-arrow{transform:translateX(3px)}
.sb-icon-btn{display:inline-flex;height:2.75rem;width:2.75rem;align-items:center;justify-content:center;border-radius:999px;border:1px solid var(--ink);transition:transform .3s cubic-bezier(.2,.7,.2,1),background-color .25s ease,color .25s ease,border-color .25s ease}
.sb-icon-btn:hover{transform:translateY(-3px);background:var(--accent);color:var(--accent-ink);border-color:var(--accent)}
.sb-label{display:inline-flex;align-items:center;gap:.65rem;border-radius:999px;border:1px solid var(--line);background:var(--surface);padding:.45rem 1rem .45rem .85rem;font-family:'DM Mono',ui-monospace,monospace;font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.08em}
.sb-pulse{position:relative;height:.5rem;width:.5rem;border-radius:999px;background:var(--accent)}
.sb-pulse::after{content:'';position:absolute;inset:0;border-radius:inherit;background:inherit;animation:sb-ping 2.2s cubic-bezier(0,0,.2,1) infinite}
.sb-pill{display:inline-block;border:1px solid var(--line);border-radius:999px;padding:.2rem .65rem;font-family:'DM Mono',ui-monospace,monospace;font-size:.72rem;font-weight:500;color:var(--muted)}
.sb-pill-end{margin-left:auto}
.sb-tick{display:inline-flex;height:1.7rem;width:1.7rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:999px;background:var(--accent);color:var(--accent-ink)}
.sb-tick-sm{height:1.5rem;width:1.5rem}
.sb-textlink{display:inline-flex;align-items:center;gap:.4rem;font-weight:600;background:linear-gradient(currentColor,currentColor) left bottom/0% 1.5px no-repeat;transition:background-size .4s cubic-bezier(.2,.7,.2,1);padding-bottom:2px}
.sb-textlink:hover{background-size:100% 1.5px}

/* ---------- Hero ---------- */
.sb-hero{position:relative;isolation:isolate;display:flex;min-height:100vh;min-height:100svh;align-items:center;overflow:hidden}
.sb-home-anchor{position:absolute;top:0}
.sb-hero-bg{position:absolute;inset:0;z-index:-10;height:100%;width:100%;object-fit:cover;opacity:.1}
.sb-hero-grid{display:grid;width:100%;max-width:1320px;margin-inline:auto;align-items:center;gap:3rem;padding:9rem 1.25rem 5rem}
@media(min-width:640px){.sb-hero-grid{padding-inline:2rem}}
@media(min-width:1024px){.sb-hero-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:0;padding-top:8rem}.sb-hero-main{grid-column:span 8/span 8}.sb-hero-aside{grid-column:span 4/span 4;margin-left:-6rem}}
.sb-hero-main{position:relative;z-index:10;min-width:0}
.sb-hero-aside{position:relative;min-width:0}
.sb-hero-name{margin-top:1.75rem;font-size:clamp(3.5rem,11vw,9.5rem);line-height:.9;letter-spacing:-.06em;overflow-wrap:anywhere}
.sb-mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.14em;margin-bottom:-.14em;margin-right:.2em}
.sb-word{display:inline-block}
.sb-outline-text{color:transparent;-webkit-text-stroke:2px var(--ink)}
.sb-js .sb-word{transform:translateY(110%);animation:sb-rise 1s cubic-bezier(.2,.8,.2,1) forwards;animation-delay:calc(.15s + var(--i,0)*.1s)}
.sb-hero-sub{display:grid;gap:1.5rem;margin-top:2rem}
@media(min-width:768px){.sb-hero-sub{grid-template-columns:repeat(12,minmax(0,1fr));gap:2rem}.sb-m4{grid-column:span 4/span 4}.sb-m8{grid-column:span 8/span 8}}
.sb-role{display:flex;flex-direction:column;gap:.9rem;font-size:.88rem;font-weight:500;text-transform:uppercase;letter-spacing:.06em;line-height:1.5}
.sb-bar-mark{display:block;height:3px;width:3rem;background:var(--accent)}
.sb-hero-desc{max-width:36rem;white-space:pre-line;font-size:1.125rem;line-height:1.7}
.sb-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2rem}
.sb-actions-lg{margin-top:2.5rem}
.sb-icons{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:2rem}
.sb-float{position:relative;margin-inline:auto;width:74%;max-width:22rem;animation:sb-float 7s ease-in-out infinite}
@media(min-width:640px){.sb-float{width:100%}}
@media(min-width:1024px){.sb-float{max-width:none}}
.sb-mask-frame{position:relative;aspect-ratio:4/5;overflow:hidden;background:var(--soft);border:1px solid var(--ink);border-radius:55% 45% 12% 12%/30% 30% 12% 12%;box-shadow:0 30px 60px -30px color-mix(in srgb,var(--ink) 60%,transparent);transition:border-radius .9s cubic-bezier(.2,.7,.2,1)}
.sb-mask-frame img{width:100%;height:100%;object-fit:cover;transition:transform .9s ease-out}
.sb-mask-frame:hover img{transform:scale(1.06)}
.sb-initials{display:flex;width:100%;height:100%;align-items:center;justify-content:center;background:var(--accent);font-size:7rem;color:var(--accent-ink)}
.sb-float:hover .sb-mask-frame{border-radius:12% 12% 55% 45%/12% 12% 30% 30%}
.sb-mask-offset{position:absolute;inset:0;border:1.5px solid var(--accent);border-radius:55% 45% 12% 12%/30% 30% 12% 12%;transform:translate(1.1rem,1.1rem);transition:transform .7s cubic-bezier(.2,.7,.2,1),border-radius .9s cubic-bezier(.2,.7,.2,1)}
.sb-float:hover .sb-mask-offset{transform:translate(.4rem,.4rem);border-radius:12% 12% 55% 45%/12% 12% 30% 30%}
.sb-caption{position:absolute;left:-1rem;bottom:2rem;display:inline-flex;align-items:center;gap:.45rem;background:var(--ink);color:var(--paper);padding:.5rem .9rem;font-size:.75rem;font-weight:500;text-transform:uppercase;letter-spacing:.06em}
.sb-caption .sb-ico{color:var(--accent)}

/* ---------- About ---------- */
.sb-frame{position:relative;margin-inline:auto;max-width:24rem;transition:transform .6s cubic-bezier(.2,.7,.2,1)}
.sb-frame::before{content:'';position:absolute;inset:0;border:1.5px solid var(--accent);border-radius:999px 999px 1.25rem 1.25rem;transform:translate(-.9rem,.9rem);pointer-events:none}
.sb-arch{position:relative;display:block;width:100%;aspect-ratio:4/5;object-fit:cover;border-radius:999px 999px 1.25rem 1.25rem;border:1px solid var(--ink)}
.sb-frame:hover{transform:rotate(-1.2deg) scale(1.01)}
.sb-prose{max-width:40rem;margin-top:1.75rem;white-space:pre-line;font-size:1.125rem;line-height:1.75}
.sb-hl-list{display:grid;gap:1rem;margin-top:3rem}
@media(min-width:640px){.sb-hl-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.sb-hl-list{grid-template-columns:repeat(3,minmax(0,1fr))}}
.sb-hl{position:relative;border:1px solid var(--line);border-radius:1.25rem;background:var(--surface);padding:1.4rem 1.4rem 1.5rem;transition:border-color .3s ease,box-shadow .4s ease}
.is-surface .sb-hl{background:var(--paper)}
.sb-hl:hover{border-color:var(--accent);box-shadow:8px 8px 0 var(--accent)}
.sb-hl-title{display:block;margin-top:1rem;font-weight:600;line-height:1.375}
.sb-hl-desc{display:block;margin-top:.375rem;font-size:.92rem;line-height:1.625}

/* ---------- Journey (horizontal progression) ---------- */
.sb-rail{position:relative;margin:4rem -1.25rem 0;overflow-x:auto;padding:1rem 1.25rem 2rem;scroll-snap-type:x proximity;scrollbar-width:thin;scrollbar-color:var(--accent) transparent}
.sb-track-h{position:relative;display:flex;gap:1.25rem;width:max-content;min-width:100%;padding-top:2.5rem}
.sb-track-h::before{content:'';position:absolute;left:0;right:0;top:.65rem;height:2px;background:var(--line)}
.sb-step{position:relative;width:min(21rem,78vw);flex-shrink:0;scroll-snap-align:start}
.sb-node{position:absolute;left:0;top:-2.5rem;display:inline-flex;height:1.35rem;width:1.35rem;align-items:center;justify-content:center;border-radius:999px;border:2px solid var(--accent);background:var(--paper)}
.is-surface .sb-node{background:var(--surface)}
.sb-node i{height:.5rem;width:.5rem;border-radius:999px;background:var(--accent)}
.sb-js .sb-node i{transform:scale(0);transition:transform .5s cubic-bezier(.3,1.6,.4,1) .4s}
.sb-js .sb-step.is-in .sb-node i{transform:scale(1)}
.sb-step::before{content:'';position:absolute;left:calc(.675rem - 1px);top:-1.15rem;height:1.15rem;width:2px;background:var(--accent)}
.sb-js .sb-step::before{transform:scaleY(0);transform-origin:top;transition:transform .6s cubic-bezier(.2,.7,.2,1) .3s}
.sb-js .sb-step.is-in::before{transform:scaleY(1)}
.sb-step-card{height:100%;border:1px solid var(--line);border-radius:1.25rem;background:var(--surface);padding:1.5rem;transition:transform .45s cubic-bezier(.2,.7,.2,1),border-color .3s ease,box-shadow .45s ease}
.is-surface .sb-step-card{background:var(--paper)}
.sb-step-card:hover{transform:translateY(-5px);border-color:var(--accent);box-shadow:8px 8px 0 var(--accent)}
@media(min-width:768px){.sb-drop-1{margin-top:2rem}}
.sb-period{font-size:.78rem;font-weight:500;text-transform:uppercase;letter-spacing:.12em;color:var(--accent)}
.sb-item-title{margin-top:.5rem;font-size:1.5rem;line-height:1.25}
.sb-company{margin-top:.25rem;font-weight:600}
.sb-item-desc{margin-top:.75rem;white-space:pre-line;font-size:.95rem;line-height:1.65}

/* ---------- Skills ---------- */
.sb-skills-desc{margin-top:1.5rem;white-space:pre-line;line-height:1.7}
.sb-skill-list{display:grid;align-content:start;gap:1rem;min-width:0}
@media(min-width:640px){.sb-skill-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.sb-skill-list{grid-column:span 8/span 8}}
@media(min-width:1280px){.sb-skill-list{grid-template-columns:repeat(3,minmax(0,1fr))}}
.sb-skill{border:1px solid var(--line);border-radius:1.25rem;background:var(--surface);padding:1.35rem 1.4rem 1.5rem;transition:border-color .3s ease,box-shadow .4s ease}
.is-surface .sb-skill{background:var(--paper)}
.sb-skill:hover{border-color:var(--accent);box-shadow:6px 6px 0 var(--accent)}
.sb-skill-row{display:flex;align-items:center;gap:.75rem}
.sb-skill-pct{margin-left:auto;font-size:.8rem;font-weight:500}
.sb-skill-name{margin-top:1.25rem;font-size:1.05rem;font-weight:600;line-height:1.375}
.sb-tile{display:inline-flex;height:2.6rem;width:2.6rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:.9rem;background:color-mix(in srgb,var(--accent) 12%,transparent);border:1px solid color-mix(in srgb,var(--accent) 40%,var(--line));color:var(--accent);transition:background-color .3s ease,color .3s ease,transform .35s cubic-bezier(.2,.7,.2,1)}
.sb-tile-lg{height:3.4rem;width:3.4rem;border-radius:1.1rem}
.sb-tile-letter{font-size:1rem;line-height:1}
.sb-tile-glyph{font-size:1.125rem;line-height:1}
.sb-tile-glyph-lg{font-size:1.5rem;line-height:1}
.sb-skill:hover .sb-tile,.sb-service:hover .sb-tile{background:var(--accent);color:var(--accent-ink);transform:rotate(-6deg) scale(1.06)}
.sb-track{position:relative;height:4px;width:100%;margin-top:1rem;background:var(--line);border-radius:999px}
.sb-bar{position:relative;height:100%;width:var(--w,0%);border-radius:inherit;background:var(--accent)}
.sb-bar::after{content:'';position:absolute;right:0;top:50%;height:12px;width:12px;transform:translate(50%,-50%);border-radius:999px;background:var(--ink)}
.sb-js [data-reveal] .sb-bar{width:0;transition:width 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s)}
.sb-js [data-reveal].is-in .sb-bar{width:var(--w,0%)}
@property --n{syntax:'<integer>';inherits:false;initial-value:0}
.sb-count{--n:var(--p,0);counter-reset:n var(--n);font-variant-numeric:tabular-nums}
.sb-count::after{content:counter(n) '%'}
.sb-js [data-reveal] .sb-count{--n:0}
.sb-js [data-reveal].is-in .sb-count{animation:sb-count 1.5s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s) forwards}

/* ---------- Projects ---------- */
.sb-proj-head{display:grid;gap:1.5rem;margin:1.25rem 0 5rem}
@media(min-width:1024px){.sb-proj-head{grid-template-columns:repeat(12,minmax(0,1fr));align-items:end}}
.sb-proj-desc{white-space:pre-line;line-height:1.7}
.sb-project-grid{display:grid;gap:5rem 2rem}
.sb-project-grid>li{min-width:0}
@media(min-width:768px){.sb-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sb-project-grid>.sb-feature{grid-column:span 2/span 2}}
@media(min-width:1280px){.sb-project-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.sb-project{position:relative;display:flex;height:100%;flex-direction:column;transition:transform .5s cubic-bezier(.2,.7,.2,1)}
.sb-project:hover{transform:translateY(-6px)}
.sb-media{position:relative;display:block;overflow:hidden;background:var(--soft);border:1px solid var(--ink);border-radius:1.25rem;isolation:isolate;box-shadow:0 24px 44px -30px color-mix(in srgb,var(--ink) 65%,transparent)}
.sb-media-std{aspect-ratio:4/3}
.sb-media-wide{aspect-ratio:16/9}
.sb-media img{width:100%;height:100%;object-fit:cover}
.sb-media img,.sb-tone{transition:transform .9s cubic-bezier(.2,.7,.2,1)}
.sb-project:hover .sb-media img,.sb-project:hover .sb-tone{transform:scale(1.06)}
.sb-tone{display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:6rem;line-height:1}
.sb-tone-0{background:var(--accent);color:var(--accent-ink)}
.sb-tone-1{background:var(--soft);color:var(--ink)}
.sb-tone-2{background:var(--ink);color:var(--paper)}
.sb-project-body{position:relative;z-index:2;display:flex;flex:1;flex-direction:column;margin:-2.5rem 1rem 0 2.25rem;border:1px solid var(--line);border-radius:1.1rem;background:var(--surface);padding:1.4rem 1.4rem 1.3rem;transition:border-color .3s ease,box-shadow .45s ease}
.is-surface .sb-project-body{background:var(--paper)}
.sb-project:hover .sb-project-body{border-color:var(--accent);box-shadow:6px 6px 0 var(--accent)}
.sb-proj-name{font-size:1.5rem;line-height:1.25}
.sb-proj-text{margin-top:.75rem;line-height:1.65}
.sb-repo{margin-top:auto;padding-top:1.25rem}
.sb-repo .sb-textlink{font-size:.78rem;text-transform:uppercase;letter-spacing:.05em}
.sb-ribbon{position:absolute;left:-.2rem;top:1.25rem;z-index:3;display:flex;flex-direction:column;gap:.4rem;max-height:calc(100% - 7rem)}
.sb-ribbon li{writing-mode:vertical-rl;transform:rotate(180deg);background:var(--ink);color:var(--paper);padding:.7rem .4rem;font-size:.68rem;font-weight:500;text-transform:uppercase;letter-spacing:.12em;white-space:nowrap;transition:background-color .3s ease,color .3s ease}
.sb-ribbon li:first-child{background:var(--accent);color:var(--accent-ink)}
.sb-project:hover .sb-ribbon li{background:var(--accent);color:var(--accent-ink)}
.sb-flag{position:absolute;right:.9rem;bottom:.9rem;display:inline-flex;align-items:center;gap:.35rem;background:var(--accent);color:var(--accent-ink);padding:.25rem .65rem;font-size:.72rem;font-weight:500}
.sb-go{position:absolute;right:.9rem;top:.9rem;display:inline-flex;height:2.9rem;width:2.9rem;align-items:center;justify-content:center;border-radius:999px;background:var(--accent);color:var(--accent-ink);opacity:0;transform:translate(-.5rem,.5rem) scale(.9);transition:opacity .35s ease,transform .45s cubic-bezier(.2,.7,.2,1)}
.sb-project:hover .sb-go,.sb-media:focus-visible .sb-go{opacity:1;transform:none}
.sb-underline{background:linear-gradient(currentColor,currentColor) left 96%/0% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1)}
.sb-project:hover .sb-underline{background-size:100% 2px}

/* ---------- Services ---------- */
.sb-service-grid{display:grid;gap:1.25rem}
.sb-service-grid>li{min-width:0}
@media(min-width:768px){.sb-service-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.sb-service{position:relative;display:flex;height:100%;flex-direction:column;overflow:hidden;border:1px solid var(--line);border-radius:1.25rem;background:var(--surface);padding:1.8rem;transition:transform .45s cubic-bezier(.2,.7,.2,1),border-color .3s ease,box-shadow .45s ease}
.is-surface .sb-service{background:var(--paper)}
.sb-service:hover{transform:translateY(-5px);border-color:var(--accent);box-shadow:8px 8px 0 var(--accent)}
.sb-service-num{position:absolute;right:1.2rem;top:.9rem;font-size:2.6rem;font-weight:500;line-height:1;color:transparent;-webkit-text-stroke:1px var(--line);transition:-webkit-text-stroke-color .3s ease}
.sb-service:hover .sb-service-num{-webkit-text-stroke-color:var(--accent)}
.sb-service-title{margin-top:1.75rem;font-size:1.5rem;line-height:1.25;transition:color .3s ease}
.sb-service:hover .sb-service-title{color:var(--accent)}
.sb-service-text{margin-top:.75rem;line-height:1.65}
.sb-service-arrow{margin-top:auto;align-self:flex-end;color:var(--accent);opacity:0;transform:translate(-.6rem,.6rem);transition:opacity .3s ease,transform .4s cubic-bezier(.2,.7,.2,1)}
.sb-service:hover .sb-service-arrow{opacity:1;transform:none}

/* ---------- Contact ---------- */
.sb-contact::before{content:'';position:absolute;right:-7rem;top:-7rem;z-index:0;height:24rem;width:24rem;border-radius:999px;background:var(--accent);opacity:.14;pointer-events:none;animation:sb-drift 18s ease-in-out infinite}
.sb-contact-title{margin-top:1.25rem;font-size:clamp(2.75rem,8vw,6.5rem);line-height:.94;overflow-wrap:anywhere}
.sb-contact-text{max-width:34rem;margin-top:1.75rem;white-space:pre-line;font-size:1.125rem;line-height:1.7}
.sb-contact-info{margin-top:2.5rem}
.sb-contact-info>*+*{margin-top:1.25rem}
.sb-bigmail{display:inline-flex;max-width:100%;align-items:center;gap:.75rem;word-break:break-all;font-size:1.5rem}
@media(min-width:640px){.sb-bigmail{font-size:2.25rem}}
.sb-underline-contact{background:linear-gradient(currentColor,currentColor) left bottom/100% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1);padding-bottom:4px}
.sb-bigmail:hover .sb-underline-contact{background-size:0% 2px;background-position:right bottom}
.sb-contact-meta{display:flex;flex-wrap:wrap;gap:.75rem 2rem;font-weight:500}
.sb-contact-meta>span{display:inline-flex;align-items:center;gap:.5rem}
.sb-contact-link{display:inline-flex;align-items:center;gap:.5rem}
.sb-contact-link:hover{text-decoration:underline;text-underline-offset:4px}
.sb-contact-socials{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:2rem}
.sb-social{display:inline-flex;align-items:center;gap:.45rem;border:1px solid var(--ink);border-radius:999px;padding:.5rem 1rem;font-size:.88rem;font-weight:600;transition:background-color .3s ease,color .3s ease,border-color .3s ease,transform .3s ease}
.sb-social:hover{background:var(--accent);color:var(--accent-ink);border-color:var(--accent);transform:translateY(-2px)}
.sb-form{padding:1.5rem;border:1px solid var(--ink);border-radius:1.5rem;background:var(--surface);box-shadow:10px 10px 0 var(--accent)}
@media(min-width:640px){.sb-form{padding:2rem}}
.sb-field{display:block;margin-bottom:1.5rem}
.sb-label-text{display:block;margin-bottom:.5rem;font-size:.72rem;font-weight:500;text-transform:uppercase;letter-spacing:.12em}
.sb-input{width:100%;border:0;border-bottom:1px solid var(--line);border-radius:0;background:transparent;padding:.7rem 0;font:inherit;font-weight:500;color:var(--ink);transition:border-color .25s ease,box-shadow .25s ease}
.sb-input:hover{border-color:var(--muted)}
.sb-input:focus{outline:none;border-color:var(--accent);box-shadow:0 1px 0 var(--accent)}
textarea.sb-input{resize:vertical}
.sb-sent{display:flex;align-items:center;gap:.5rem;font-size:.875rem;font-weight:600;animation:sb-pop .5s cubic-bezier(.2,.8,.2,1) both}
.sb-hidden-frame{display:none}
.sb-foot{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;border-top:1px solid var(--line);padding-block:1.75rem;font-size:.875rem}
.sb-foot-copy{font-size:.78rem}

/* ---------- Scroll reveal (class is added by script.js, so no-JS output stays visible) ---------- */
.sb-js [data-reveal]{opacity:0;transform:translateY(24px);transition:opacity .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms),transform .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms)}
.sb-js [data-reveal].is-in{opacity:1;transform:none}
@media(min-width:768px){
.sb-js [data-reveal].sb-stagger-1.is-in,.sb-js li.sb-stagger-1[data-reveal].is-in{transform:translateY(1.75rem)}
.sb-js [data-reveal].sb-stagger-2.is-in,.sb-js li.sb-stagger-2[data-reveal].is-in{transform:translateY(.75rem)}
}
.sb-js [data-reveal]:not(.is-in) .sb-h2,.sb-js [data-reveal]:not(.is-in).sb-h2{letter-spacing:.005em}
.sb-js [data-reveal].is-in .sb-frame{animation:sb-frame-in 1.1s cubic-bezier(.7,0,.2,1) backwards}
.sb-js [data-reveal]:not(.is-in) .sb-hl{opacity:0}
.sb-js [data-reveal].is-in .sb-hl{animation:sb-slide-x .8s cubic-bezier(.2,.7,.2,1) calc(var(--k,0)*90ms + 150ms) backwards}
.sb-js [data-reveal].is-in .sb-hl .sb-tick{animation:sb-tick .6s cubic-bezier(.3,1.6,.4,1) calc(var(--k,0)*90ms + 450ms) backwards}
.sb-js [data-reveal].is-in .sb-tile{animation:sb-tile .7s cubic-bezier(.3,1.5,.4,1) calc(var(--d,0ms) + .15s) backwards}
.sb-js [data-reveal].is-in .sb-media{animation:sb-wipe 1.2s cubic-bezier(.7,0,.2,1) .1s backwards}
.sb-js [data-reveal].is-in .sb-media img{animation:sb-settle 1.6s cubic-bezier(.2,.7,.2,1) .1s backwards}
.sb-js [data-reveal].is-in .sb-ribbon li{animation:sb-ribbon-in .8s cubic-bezier(.2,.8,.2,1) .4s backwards}
.sb-js.sb-root{animation:sb-enter .7s ease both}
@keyframes sb-rise{to{transform:translateY(0)}}
@keyframes sb-enter{from{opacity:0}}
@keyframes sb-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes sb-pop{from{opacity:0;transform:translateY(8px) scale(.96)}}
@keyframes sb-float{50%{transform:translateY(-14px)}}
@keyframes sb-drift{50%{transform:translate(-2.5rem,2rem) scale(1.08)}}
@keyframes sb-count{to{--n:var(--p)}}
@keyframes sb-frame-in{from{clip-path:inset(0 0 100% 0)}}
@keyframes sb-slide-x{from{opacity:0;transform:translateX(-20px)}}
@keyframes sb-tick{from{transform:scale(0) rotate(-90deg)}}
@keyframes sb-tile{from{transform:scale(.4) rotate(-20deg);opacity:0}}
@keyframes sb-wipe{from{clip-path:inset(0 0 100% 0 round 1.25rem)}}
@keyframes sb-settle{from{transform:scale(1.25)}}
@keyframes sb-ribbon-in{from{opacity:0;transform:rotate(180deg) translateY(-1.5rem)}}

/* ---------- Reduced motion ---------- */
@media(prefers-reduced-motion:reduce){
html{scroll-behavior:auto}
.sb-root *,.sb-root *::before,.sb-root *::after{animation:none!important;transition:none!important}
.sb-js .sb-word,.sb-js [data-reveal],.sb-js [data-reveal]:not(.is-in) .sb-hl{opacity:1;transform:none}
.sb-js [data-reveal] .sb-count{--n:var(--p,0)}
.sb-js [data-reveal] .sb-bar{width:var(--w,0%)}
.sb-js [data-reveal]:not(.is-in) .sb-kicker::before{transform:none}
.sb-js [data-reveal]:not(.is-in) .sb-h2,.sb-js [data-reveal]:not(.is-in).sb-h2{letter-spacing:-.045em}
.sb-js .sb-node i,.sb-js .sb-step::before{transform:none}
}
CSS;
    }
}
