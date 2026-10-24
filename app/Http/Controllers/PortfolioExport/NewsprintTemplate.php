<?php

namespace App\Http\Controllers\PortfolioExport;

trait NewsprintTemplate
{
    private function renderNewsprintPage(string $title, array $content, array $assets, ?array $googleForm): string
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
        $icon = fn (string $name, int $size = 16, string $class = '', string $stroke = '2'): string => $this->newsprintIcon($name, $size, $class, $stroke);
        $num = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
        $pad = static fn (int $index): string => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

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
        $header = $this->sectionData($content['header'] ?? []);
        $about = $this->sectionData($content['about'] ?? []);
        $journeyData = $this->sectionData($content['journey'] ?? []);
        $skillsData = $this->sectionData($content['skills'] ?? []);
        $projectData = $this->sectionData($content['projects'] ?? []);
        $servicesData = $this->sectionData($content['services'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);

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
        $logoText = $text($navbar, 'logo_text') ?? $heroName;
        $logoImage = $flag($navbar, 'show_logo', true) ? $mediaUrl($navbar, 'logo_image') : '';
        $stickyNav = $flag($navbar, 'sticky_nav', true);
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
                $highlightTitle = $clean($item['title'] ?? $item['value'] ?? $item['label'] ?? $item['text'] ?? $item['name'] ?? null);
                $highlightDescription = $clean($item['description'] ?? $item['label'] ?? null);
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
        // Masonry rhythm: 7/5, 5/7 pairs; a featured first dispatch spans the full width.
        $projectSpan = static function (int $index) use ($featureFirst): string {
            if ($featureFirst && $index === 0) {
                return 'np-s12';
            }
            $offset = $featureFirst ? $index - 1 : $index;

            return in_array($offset % 4, [0, 3], true) ? 'np-s7' : 'np-s5';
        };

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
        $hasMeta = $emailOk || $phoneOk || $location !== null;
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
        $html = '<div class="np-root">';

        // Masthead / navbar
        $html .= '<header class="np-header '.($stickyNav ? 'is-fixed' : 'is-absolute').'"><div class="np-progress" data-progress aria-hidden="true"></div>';
        $html .= '<nav class="np-nav" data-nav aria-label="Primary"><div class="np-wrap np-nav-inner">';
        if ($logoImage !== '' || $logoText !== null) {
            $html .= '<a href="#header" class="np-logo" data-scroll-link>';
            if ($logoImage !== '') {
                $html .= '<img src="'.$escape($logoImage).'" alt="'.$escape($logoText ?? '').'" class="np-logo-img">';
            }
            if ($logoText !== null) {
                $html .= '<span class="np-display np-logo-text">'.$escape($logoText).'</span>';
            }
            $html .= '</a>';
        } else {
            $html .= '<span></span>';
        }
        if ($navLinks !== []) {
            $html .= '<ul class="np-navlinks">';
            foreach ($navLinks as $link) {
                $html .= '<li><a href="#'.$escape($link['id']).'" class="np-navlink" data-active="false" data-section-link="'.$escape($link['id']).'" data-scroll-link>'.$escape($link['label']).'</a></li>';
            }
            $html .= '</ul>';
        }
        if ($hasContact) {
            $html .= '<a href="#contact" class="np-btn np-btn-primary np-btn-sm" data-scroll-link>Contact'.$icon('arrow-up-right', 16, 'np-arrow').'</a>';
        } else {
            $html .= '<span></span>';
        }
        $html .= '</div></nav></header>';

        // Hero
        if ($hasHeader) {
            $showPortrait = $heroImage !== '' || $heroInitials !== '';
            $centerSpan = $heroSocials !== [] && $hasMeta ? 'np-s10' : ($heroSocials !== [] || $hasMeta ? 'np-s11' : 'np-s12');

            $html .= '<section id="header" class="np-hero" data-page-section>';
            if ($heroBackground !== '') {
                $html .= '<img src="'.$escape($heroBackground).'" alt="" class="np-hero-bg">';
            }
            $html .= '<div class="np-wrap np-g12">';

            if ($heroSocials !== []) {
                $html .= '<aside class="np-margin np-aside-l"><ul class="np-rail np-rail-l">';
                foreach ($heroSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$escape($social['label']).'" title="'.$escape($social['label']).'" class="np-icon-btn">'.$icon($socialIcon($social['platform']), 18).'</a></li>';
                }
                $html .= '</ul></aside>';
            }

            $html .= '<div class="np-center '.$centerSpan.'">';
            if ($greeting !== null || $role !== null) {
                $html .= '<div class="np-load np-strip" style="--d: 0ms">';
                if ($greeting !== null) {
                    $html .= '<span class="np-strip-greeting"><span class="np-pulse" aria-hidden="true"></span>'.$escape($greeting).'</span>';
                }
                if ($role !== null) {
                    $html .= '<span class="np-accent">'.$escape($role).'</span>';
                }
                $html .= '</div>';
            }
            if ($heroName !== null) {
                $html .= '<h1 class="np-display np-hero-name"><span class="sr-only">'.$escape($heroName).'</span>';
                foreach ($heroWords as $index => $word) {
                    $html .= '<span class="np-mask" aria-hidden="true"><span class="np-word" style="--i: '.$index.'">'.$escape($word).'</span></span>';
                }
                $html .= '</h1>';
            }
            $html .= '<div class="np-hero-bottom"><div class="np-load np-hero-copy'.($showPortrait ? ' np-has-portrait' : '').'" style="--d: 520ms">';
            if ($heroDescription !== null) {
                $html .= '<p class="np-muted np-body np-hero-desc">'.$escape($heroDescription).'</p>';
            }
            if ($heroPrimary !== null || $heroSecondary !== null) {
                $html .= '<div class="np-actions">';
                if ($heroPrimary !== null) {
                    $html .= '<a href="'.$url($heroPrimary['url']).'" class="np-btn np-btn-primary" data-scroll-link>'.$escape($heroPrimary['text']).$icon('arrow-up-right', 16, 'np-arrow').'</a>';
                }
                if ($heroSecondary !== null) {
                    $html .= '<a href="'.$url($heroSecondary['url']).'" class="np-btn np-btn-ghost" data-scroll-link>'.$escape($heroSecondary['text']).'</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
            if ($showPortrait) {
                $html .= '<figure class="np-load np-portrait" style="--d: 640ms">';
                if ($heroImage !== '') {
                    $html .= '<img src="'.$escape($heroImage).'" alt="'.$escape($heroName ?? '').'">';
                } else {
                    $html .= '<span class="np-display np-initials">'.$escape($heroInitials).'</span>';
                }
                $html .= '</figure>';
            }
            $html .= '</div></div>';

            if ($hasMeta) {
                $html .= '<aside class="np-margin np-aside-r"><ul class="np-rail np-rail-r">';
                if ($emailOk) {
                    $html .= '<li><a href="'.$escape($emailHref).'" class="np-meta" title="'.$escape($email).'">'.$icon('mail', 16).'<span class="np-vert">'.$escape($email).'</span></a></li>';
                }
                if ($phoneOk) {
                    $html .= '<li><a href="'.$escape($phoneHref).'" class="np-meta" title="'.$escape($phone).'">'.$icon('phone', 16).'<span class="np-vert">'.$escape($phone).'</span></a></li>';
                }
                if ($location !== null) {
                    $html .= '<li><span class="np-meta" title="'.$escape($location).'">'.$icon('map-pin', 16, 'np-accent').'<span class="np-vert">'.$escape($location).'</span></span></li>';
                }
                $html .= '</ul></aside>';
            }
            $html .= '</div></section>';
        }

        // About
        if ($hasAbout) {
            $html .= '<section id="about" class="np-anchor np-bt" data-page-section><div class="np-wrap np-g12">';
            if ($aboutImage !== '') {
                $html .= '<div data-reveal class="np-img-cell np-s5"><img src="'.$escape($aboutImage).'" alt="'.$escape($text($about, 'about_title') ?? '').'" loading="lazy"></div>';
            }
            $html .= '<div class="'.($aboutImage !== '' ? 'np-s7' : 'np-s12').'"><div data-reveal class="np-pad np-pad-lg">';
            if ($text($about, 'about_title') !== null) {
                $html .= '<h2 class="np-display np-h2">'.$escape($text($about, 'about_title')).'</h2>';
            }
            if ($text($about, 'about_subtitle') !== null) {
                $html .= '<p class="np-dek np-mt4">'.$escape($text($about, 'about_subtitle')).'</p>';
            }
            if ($text($about, 'about_description') !== null) {
                $html .= '<p class="np-muted np-body np-dropcap np-about-text">'.$escape($text($about, 'about_description')).'</p>';
            }
            $html .= '</div>';
            if ($highlights !== []) {
                $html .= '<ul class="np-cells np-hl-grid">';
                foreach ($highlights as $index => $highlight) {
                    $html .= '<li data-reveal style="--d: '.(($index % 4) * 60).'ms" class="np-cell np-hl"><span class="np-tick">'.$icon('check', 14, '', '3').'</span><span class="np-display np-hl-title">'.$escape($highlight['title']).'</span>';
                    if ($highlight['description'] !== null) {
                        $html .= '<span class="np-muted np-hl-desc">'.$escape($highlight['description']).'</span>';
                    }
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }
            if ($cvFile !== '' || $aboutCta !== null) {
                $html .= '<div data-reveal class="np-about-actions">';
                if ($cvFile !== '') {
                    $html .= '<a href="'.$escape($cvFile).'" download target="_blank" rel="noopener" class="np-btn np-btn-primary">'.$icon('download', 16).'Download CV</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></section>';
        }

        // Journey + skills (4 / 8 when both exist, full width otherwise)
        if ($hasJourney || $hasSkills) {
            $html .= '<div class="np-bt"><div class="np-wrap np-g12">';

            if ($hasJourney) {
                $html .= '<section id="journey" class="np-anchor np-journey '.($hasSkills ? 'np-s4 np-journey-split' : 'np-s12').'" data-page-section>';
                $html .= '<div data-reveal class="np-head">';
                if ($text($journeyData, 'journey_title') !== null) {
                    $html .= '<h2 class="np-display np-h3">'.$escape($text($journeyData, 'journey_title')).'</h2>';
                }
                if ($text($journeyData, 'journey_subtitle') !== null) {
                    $html .= '<p class="np-dek np-mt3">'.$escape($text($journeyData, 'journey_subtitle')).'</p>';
                }
                $html .= '</div><ol>';
                foreach ($journey as $index => $item) {
                    $html .= '<li data-reveal style="--d: '.(($index % 4) * 60).'ms" class="np-entry">';
                    if ($item['period'] !== null) {
                        $html .= '<p class="np-period">'.$escape($item['period']).'</p>';
                    }
                    if ($item['title'] !== null) {
                        $html .= '<h3 class="np-display np-item-title">'.$escape($item['title']).'</h3>';
                    }
                    if ($item['company'] !== null) {
                        $html .= '<p class="np-company">'.$escape($item['company']).'</p>';
                    }
                    if ($item['description'] !== null) {
                        $html .= '<p class="np-muted np-item-desc">'.$escape($item['description']).'</p>';
                    }
                    $html .= '</li>';
                }
                $html .= '</ol></section>';
            }

            if ($hasSkills) {
                $html .= '<section id="skills" class="np-anchor '.($hasJourney ? 'np-s8' : 'np-s12').'" data-page-section>';
                $html .= '<div data-reveal class="np-head np-skills-head"><div class="np-skills-title">';
                if ($text($skillsData, 'skills_title') !== null) {
                    $html .= '<h2 class="np-display np-h3">'.$escape($text($skillsData, 'skills_title')).'</h2>';
                }
                if ($text($skillsData, 'skills_subtitle') !== null) {
                    $html .= '<p class="np-dek np-mt3">'.$escape($text($skillsData, 'skills_subtitle')).'</p>';
                }
                $html .= '</div>';
                if ($text($skillsData, 'skills_description') !== null) {
                    $html .= '<p class="np-muted np-skills-desc">'.$escape($text($skillsData, 'skills_description')).'</p>';
                }
                $html .= '</div><ul class="np-cells np-skill-grid">';
                foreach ($skills as $index => $skill) {
                    $html .= '<li data-reveal style="--d: '.(($index % 6) * 45).'ms" class="np-cell np-skill"><div class="np-skill-row">';
                    if ($showIcon) {
                        $skillIcon = $iconFor($skill['icon']);
                        $html .= '<span class="np-tile">';
                        if ($skillIcon !== null) {
                            $html .= $icon($skillIcon, 17);
                        } elseif ($isGlyph($skill['icon'])) {
                            $html .= '<span class="np-tile-glyph" aria-hidden="true">'.$escape($skill['icon']).'</span>';
                        } else {
                            $html .= '<span class="np-display np-tile-letter" aria-hidden="true">'.$escape(mb_strtoupper(mb_substr($skill['name'], 0, 1))).'</span>';
                        }
                        $html .= '</span>';
                    }
                    $html .= '<h3 class="np-skill-name">'.$escape($skill['name']).'</h3>';
                    if ($showLevel && $skill['percent'] !== null) {
                        $html .= '<span class="np-muted np-skill-pct">'.(int) round($skill['percent']).'%</span>';
                    } elseif ($showLevel && $skill['label'] !== null) {
                        $html .= '<span class="np-pill np-pill-end">'.$escape($skill['label']).'</span>';
                    }
                    $html .= '</div>';
                    if ($showLevel && $skill['percent'] !== null) {
                        $html .= '<div class="np-track" role="progressbar" aria-valuenow="'.(int) round($skill['percent']).'" aria-valuemin="0" aria-valuemax="100" aria-label="'.$escape($skill['name']).'"><div class="np-bar" style="--w: '.$num($skill['percent']).'%"></div></div>';
                    }
                    $html .= '</li>';
                }
                $html .= '</ul></section>';
            }
            $html .= '</div></div>';
        }

        // Projects
        if ($hasProjects) {
            $html .= '<section id="projects" class="np-anchor np-bt" data-page-section><div class="np-wrap">';
            $html .= '<div data-reveal class="np-head np-head-lg np-proj-head"><div class="np-proj-title">';
            if ($text($projectData, 'projects_title') !== null) {
                $html .= '<h2 class="np-display np-h2">'.$escape($text($projectData, 'projects_title')).'</h2>';
            }
            if ($text($projectData, 'projects_subtitle') !== null) {
                $html .= '<p class="np-dek np-mt4">'.$escape($text($projectData, 'projects_subtitle')).'</p>';
            }
            $html .= '</div>';
            if ($text($projectData, 'projects_description') !== null) {
                $html .= '<p class="np-muted np-proj-desc">'.$escape($text($projectData, 'projects_description')).'</p>';
            }
            $html .= '</div><ul class="np-proj-list">';
            foreach ($projects as $index => $project) {
                $featured = $featureFirst && $index === 0;
                $html .= '<li data-reveal style="--d: '.(($index % 2) * 70).'ms" class="np-proj-item '.$projectSpan($index).($featured ? ' np-wide' : '').'"><article class="np-project">';
                $mediaClass = 'np-media '.($featured ? 'np-media-wide' : 'np-media-std');
                if ($project['live'] !== '') {
                    $html .= '<a href="'.$escape($project['live']).'" target="_blank" rel="noopener noreferrer" aria-label="Open '.$escape($project['title']).'" class="'.$mediaClass.'">';
                } else {
                    $html .= '<div class="'.$mediaClass.'">';
                }
                if ($project['image'] !== '') {
                    $html .= '<img src="'.$escape($project['image']).'" alt="'.$escape($project['title']).'" loading="lazy">';
                } else {
                    $html .= '<div class="np-display np-tone np-tone-'.($index % 3).'">'.$escape(mb_strtoupper(mb_substr($project['title'], 0, 1))).'</div>';
                }
                if ($featured) {
                    $html .= '<span class="np-flag">Featured</span>';
                }
                if ($project['live'] !== '') {
                    $html .= '<span class="np-go" aria-hidden="true">'.$icon('arrow-up-right', 20).'</span>';
                }
                $html .= $project['live'] !== '' ? '</a>' : '</div>';

                $html .= '<div class="np-proj-body"><div class="np-proj-main"><div class="np-proj-titlerow"><span class="np-display np-num" aria-hidden="true">'.$pad($index).'</span>';
                $html .= '<h3 class="np-display np-proj-name"><span class="np-underline">'.$escape($project['title']).'</span></h3></div>';
                if ($project['description'] !== null) {
                    $html .= '<p class="np-muted np-proj-text">'.$escape($project['description']).'</p>';
                }
                $html .= '</div>';
                if ($project['tags'] !== [] || $project['repo'] !== '') {
                    $html .= '<div class="np-proj-side">';
                    if ($project['tags'] !== []) {
                        $html .= '<ul class="np-tags">';
                        foreach ($project['tags'] as $tag) {
                            $html .= '<li class="np-pill">'.$escape($tag).'</li>';
                        }
                        $html .= '</ul>';
                    }
                    if ($project['repo'] !== '') {
                        $html .= '<a href="'.$escape($project['repo']).'" target="_blank" rel="noopener noreferrer" class="np-textlink np-repo">'.$icon('code', 16).'Source code</a>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div></article></li>';
            }
            $html .= '</ul></div></section>';
        }

        // Services
        if ($hasServices) {
            $html .= '<section id="services" class="np-anchor np-bt" data-page-section><div class="np-wrap"><div data-reveal class="np-head np-head-lg">';
            if ($text($servicesData, 'services_title') !== null) {
                $html .= '<h2 class="np-display np-h2">'.$escape($text($servicesData, 'services_title')).'</h2>';
            }
            if ($text($servicesData, 'services_subtitle') !== null) {
                $html .= '<p class="np-dek np-mt4 np-prewrap">'.$escape($text($servicesData, 'services_subtitle')).'</p>';
            }
            $html .= '</div><ul class="np-cells np-service-grid">';
            foreach ($services as $index => $service) {
                $html .= '<li data-reveal style="--d: '.(($index % 3) * 60).'ms" class="np-cell np-service"><span class="np-tile np-tile-lg">';
                if (! $isGlyph($service['icon'])) {
                    $html .= $icon($iconFor($service['icon']) ?? 'sparkles', 20);
                } else {
                    $html .= '<span class="np-tile-glyph-lg" aria-hidden="true">'.$escape($service['icon']).'</span>';
                }
                $html .= '</span><h3 class="np-display np-service-title">'.$escape($service['title']).'</h3>';
                if ($service['description'] !== null) {
                    $html .= '<p class="np-muted np-service-text">'.$escape($service['description']).'</p>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></div></section>';
        }

        // Contact (+ footer)
        if ($hasContact) {
            $html .= '<section id="contact" class="np-anchor np-bt" data-page-section><div class="np-wrap"><div class="np-contact np-g12"><div data-reveal class="np-contact-left np-s7">';
            if ($text($contact, 'contact_title') !== null) {
                $html .= '<h2 class="np-display np-contact-title">'.$escape($text($contact, 'contact_title')).'</h2>';
            }
            if ($text($contact, 'contact_subtitle') !== null) {
                $html .= '<p class="np-contact-sub">'.$escape($text($contact, 'contact_subtitle')).'</p>';
            }
            if ($text($contact, 'contact_description') !== null) {
                $html .= '<p class="np-contact-text">'.$escape($text($contact, 'contact_description')).'</p>';
            }
            if ($emailOk || $phoneOk || $location !== null) {
                $html .= '<div class="np-contact-info">';
                if ($emailOk) {
                    $html .= '<a href="'.$escape($emailHref).'" class="np-bigmail np-display">'.$icon('mail', 28, 'np-accent').'<span class="np-underline-contact">'.$escape($email).'</span>'.$icon('arrow-up-right', 28, 'np-arrow').'</a>';
                }
                if ($phoneOk || $location !== null) {
                    $html .= '<div class="np-contact-meta">';
                    if ($phoneOk) {
                        $html .= '<a href="'.$escape($phoneHref).'" class="np-contact-link">'.$icon('phone', 16).$escape($phone).'</a>';
                    }
                    if ($location !== null) {
                        $html .= '<span>'.$icon('map-pin', 16).$escape($location).'</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            if ($contactSocials !== []) {
                $html .= '<ul class="np-contact-socials">';
                foreach ($contactSocials as $social) {
                    $html .= '<li><a href="'.$escape($social['url']).'" target="_blank" rel="noopener noreferrer" class="np-social">'.$escape($social['label']).$icon('arrow-up-right', 14, 'np-arrow').'</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div><div data-reveal style="--d: 100ms" class="np-contact-right np-s5">';

            if ($formReady) {
                $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="np-gform-frame" class="np-form" data-google-form>
                <label class="np-field"><span class="np-label">Name</span><input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="np-input"></label>
                <label class="np-field"><span class="np-label">Email</span><input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="np-input"></label>
                <label class="np-field"><span class="np-label">Message</span><textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required class="np-input"></textarea></label>
                <button type="submit" class="np-btn np-btn-accent np-btn-block"><span data-submit-label>Send message</span>'.$icon('send', 16, 'np-arrow').'</button>
                <p class="np-sent" role="status" data-form-status hidden><span class="np-tick">'.$icon('check', 14, '', '3').'</span>Your message was submitted.</p>
            </form>
            <iframe name="np-gform-frame" title="Form submission target" class="np-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
            } elseif ($emailOk) {
                $html .= '<a href="'.$escape($emailHref).'" class="np-btn np-btn-accent np-btn-block np-btn-xl">'.$icon('mail', 20).$escape($email).'</a>';
            }
            $html .= '</div></div>';

            if ($heroName !== null || $logoText !== null) {
                $html .= '<footer class="np-foot"><span class="np-muted">&copy; '.date('Y').' '.$escape($heroName ?? $logoText).'</span><a href="#header" class="np-textlink" data-scroll-link>Back to top'.$icon('arrow-up-right', 16, 'np-arrow np-rot').'</a></footer>';
            }
            $html .= '</div></section>';
        }
        $html .= '</div>';

        return '<!doctype html>
<html lang="en" data-template="newsprint">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#edf0f3" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e1217" media="(prefers-color-scheme: dark)">
    <title>'.$escape($title).'</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    /** Inline Lucide-style icons (static, trusted markup only). */
    private function newsprintIcon(string $name, int $size = 16, string $class = '', string $stroke = '2'): string
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

        return '<svg class="np-ico'.($class !== '' ? ' '.$class : '').'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$stroke.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$body.'</svg>';
    }

    private function newsprintStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600..900;1,500..700&family=Archivo:wght@400;500;600;700&display=swap');
/* ---------- Reset (Tailwind-preflight equivalent, low specificity) ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;color-scheme:light dark;-webkit-text-size-adjust:100%}
body{margin:0;background:#edf0f3}
@media(prefers-color-scheme:dark){body{background:#0e1217}}
:where(.np-root) :is(h1,h2,h3,p,ul,ol,figure){margin:0}
:where(.np-root) :is(ul,ol){padding:0;list-style:none}
:where(.np-root) :is(h1,h2,h3){font-size:inherit;font-weight:inherit}
:where(.np-root) a{color:inherit;text-decoration:none}
:where(.np-root) :is(button,input,textarea){font:inherit;color:inherit;letter-spacing:inherit}
:where(.np-root) button{background:none;border:0;padding:0;cursor:pointer}
:where(.np-root) img,:where(.np-root) svg{display:block;max-width:100%}
.np-root [hidden]{display:none!important}
.np-ico{flex-shrink:0}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

/* ---------- Foundations + palette (light, dark via prefers-color-scheme) ---------- */
.np-root{--paper:#EDF0F3;--soft:#E1E6EB;--surface:#F8F9FB;--ink:#10161E;--muted:#10161EB0;--line:#10161E38;--accent:#0A7A63;--accent-ink:#F2FFFB;background:var(--paper);color:var(--ink);font-family:'Archivo',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;overflow-x:clip;transition:background-color .4s ease,color .4s ease}
@media(prefers-color-scheme:dark){.np-root{--paper:#0E1217;--soft:#141A21;--surface:#1A212A;--ink:#ECEFF3;--muted:#ECEFF3AD;--line:#ECEFF33A;--accent:#5FE0B8;--accent-ink:#0E1217}}
.np-root ::selection{background:#0A7A63;color:#fff}
.np-wrap{max-width:1240px;margin-inline:auto}
@media(min-width:1241px){.np-wrap{border-inline:1px solid var(--line)}}
.np-display{font-family:'Playfair Display',Georgia,'Times New Roman',serif;font-weight:800;letter-spacing:-.025em}
.np-body{font-family:'Playfair Display',Georgia,serif;font-weight:500}
.np-muted{color:var(--muted)}
.np-accent{color:var(--accent)}
.np-prewrap{white-space:pre-line}
.np-mt3{margin-top:.75rem}
.np-mt4{margin-top:1rem}
.np-h2{font-size:clamp(2.25rem,5vw,4rem);line-height:1.02;letter-spacing:-.035em;text-wrap:balance}
.np-h3{font-size:clamp(1.75rem,3vw,2.5rem);line-height:1.05;letter-spacing:-.03em}
.np-dek{max-width:34rem;font-family:'Playfair Display',Georgia,serif;font-style:italic;font-weight:500;font-size:1.15rem;line-height:1.5;color:var(--accent)}
.np-dropcap::first-letter{float:left;margin:.08em .12em 0 0;font-family:'Playfair Display',Georgia,serif;font-weight:800;font-size:4.2em;line-height:.78;color:var(--ink)}
.np-root :where(a,button,input,textarea):focus-visible{outline:2px solid var(--accent);outline-offset:3px}
.np-anchor{scroll-margin-top:3.5rem}
.np-bt{border-top:1px solid var(--line)}

/* ---------- 12-column grid ---------- */
.np-g12{display:grid}
.np-g12>*{min-width:0}
@media(min-width:1024px){
.np-g12{grid-template-columns:repeat(12,minmax(0,1fr))}
.np-s1{grid-column:span 1/span 1}.np-s4{grid-column:span 4/span 4}.np-s5{grid-column:span 5/span 5}.np-s7{grid-column:span 7/span 7}.np-s8{grid-column:span 8/span 8}.np-s10{grid-column:span 10/span 10}.np-s11{grid-column:span 11/span 11}.np-s12{grid-column:span 12/span 12}
}

/* Hairline cells: 1px gaps over a rule-coloured backplate */
.np-cells{display:grid;gap:1px;background:var(--line);border-top:1px solid var(--line)}
.np-cell{background:var(--paper);transition:background-color .3s ease}
.np-cell:hover{background:var(--surface)}
.np-pad{padding:1.25rem}
@media(min-width:640px){.np-pad-lg{padding:2.5rem}}
.np-head{padding:1.25rem;border-bottom:1px solid var(--line)}
@media(min-width:640px){.np-head{padding:2rem}}
@media(min-width:640px){.np-head-lg{padding:2.5rem}}

/* ---------- Navbar (masthead) ---------- */
.np-header{position:fixed;inset:0 0 auto 0;z-index:50}
.np-header.is-absolute{position:absolute}
.np-progress{position:fixed;inset:0 0 auto 0;height:3px;background:var(--accent);transform:scaleX(0);transform-origin:left;pointer-events:none;z-index:60}
.np-nav{border-bottom:1px solid var(--line);background:color-mix(in srgb,var(--paper) 92%,transparent);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);transition:box-shadow .35s ease}
.np-nav.is-scrolled{box-shadow:0 1px 0 var(--ink)}
.np-nav-inner{display:flex;height:3.5rem;align-items:center;justify-content:space-between;gap:1rem;padding-inline:1.25rem}
@media(min-width:640px){.np-nav-inner{padding-inline:2rem}}
.np-logo{display:flex;min-width:0;align-items:center;gap:.75rem}
.np-logo-img{height:1.75rem;width:auto;max-width:7rem;object-fit:contain}
.np-logo-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:1.25rem}
.np-navlinks{display:none;align-items:center;gap:1.5rem}
@media(min-width:768px){.np-navlinks{display:flex}}
.np-navlink{position:relative;display:inline-block;padding:.35rem 0;font-size:.92rem;font-weight:500;color:var(--muted);background:linear-gradient(var(--accent),var(--accent)) left bottom/0% 2px no-repeat;transition:color .25s ease,background-size .4s cubic-bezier(.2,.7,.2,1)}
.np-navlink:hover,.np-navlink[data-active='true']{color:var(--ink)}
.np-navlink[data-active='true']{background-size:100% 2px}

/* ---------- Buttons & small controls ---------- */
.np-btn{display:inline-flex;align-items:center;gap:.5rem;border-radius:.2rem;padding:.8rem 1.4rem;font-size:.95rem;font-weight:600;line-height:1.2;cursor:pointer;border:1px solid transparent;transition:background-color .3s ease,color .3s ease,transform .25s ease,border-color .3s ease}
.np-btn:hover{transform:translateY(-2px)}
.np-btn:active{transform:translateY(0) scale(.98)}
.np-btn[disabled]{opacity:.7;pointer-events:none}
.np-btn-primary{background:var(--ink);color:var(--paper)}
.np-btn-primary:hover{background:var(--accent);color:var(--accent-ink)}
.np-btn-ghost{border-color:var(--ink);color:var(--ink)}
.np-btn-ghost:hover{background:var(--ink);color:var(--paper)}
.np-btn-accent{background:var(--accent);color:var(--accent-ink)}
.np-btn-accent:hover{background:var(--paper);color:var(--ink)}
.np-btn-sm{padding:.5rem 1rem;font-size:.875rem}
.np-btn-block{width:100%;justify-content:center}
.np-btn-xl{padding-block:1.25rem;font-size:1.125rem}
.np-arrow{transition:transform .3s cubic-bezier(.2,.7,.2,1)}
.np-rot{transform:rotate(-45deg)}
.np-btn:hover .np-arrow,.np-bigmail:hover .np-arrow,.np-social:hover .np-arrow,.np-textlink:hover .np-arrow{transform:translate(3px,-3px)}
.np-icon-btn{display:inline-flex;height:2.5rem;width:2.5rem;align-items:center;justify-content:center;border:1px solid var(--line);background:var(--surface);transition:transform .3s cubic-bezier(.2,.7,.2,1),background-color .25s ease,color .25s ease,border-color .25s ease}
.np-icon-btn:hover{transform:translateY(-3px);background:var(--accent);color:var(--accent-ink);border-color:var(--accent)}
.np-meta{display:inline-flex;align-items:center;gap:.5rem;color:var(--muted);transition:color .25s ease}
a.np-meta:hover{color:var(--accent)}
@media(min-width:1024px){.np-meta{flex-direction:column}.np-vert{writing-mode:vertical-rl;max-height:11rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}}
.np-pulse{position:relative;height:.5rem;width:.5rem;border-radius:999px;background:var(--accent)}
.np-pulse::after{content:'';position:absolute;inset:0;border-radius:inherit;background:inherit;animation:np-ping 2.2s cubic-bezier(0,0,.2,1) infinite}
.np-pill{display:inline-block;border:1px solid var(--line);padding:.2rem .65rem;font-size:.8rem;font-weight:600;color:var(--muted)}
.np-pill-end{margin-left:auto}
.np-tick{display:inline-flex;height:1.5rem;width:1.5rem;flex-shrink:0;align-items:center;justify-content:center;background:var(--accent);color:var(--accent-ink)}
.np-textlink{display:inline-flex;align-items:center;gap:.4rem;font-weight:600;background:linear-gradient(currentColor,currentColor) left bottom/0% 1.5px no-repeat;transition:background-size .4s cubic-bezier(.2,.7,.2,1);padding-bottom:2px}
.np-textlink:hover{background-size:100% 1.5px}

/* ---------- Hero (one orchestrated load sequence) ---------- */
.np-hero{position:relative;isolation:isolate;overflow:hidden;padding-top:3.5rem}
.np-hero-bg{position:absolute;inset:0;z-index:-10;height:100%;width:100%;object-fit:cover;opacity:.1}
@media(prefers-color-scheme:dark){.np-hero-bg{opacity:.16}}
.np-center{order:1;text-align:center}
.np-aside-l{order:2;border-top:1px solid var(--line)}
.np-aside-r{order:3;border-top:1px solid var(--line)}
@media(min-width:1024px){
.np-center{order:2}
.np-aside-l{order:1;grid-column:span 1/span 1;border-top:0;border-right:1px solid var(--line)}
.np-aside-r{grid-column:span 1/span 1;border-top:0;border-left:1px solid var(--line)}
}
.np-rail{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.5rem;padding:1rem}
.np-rail-r{gap:1rem;font-size:.875rem;font-weight:600}
@media(min-width:1024px){
.np-rail{position:sticky;top:6rem;flex-direction:column;gap:.75rem;padding-block:2.5rem}
.np-rail-r{gap:1.5rem}
}
.np-strip{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.25rem 1.5rem;border-bottom:1px solid var(--line);padding:.75rem 1.25rem;font-size:.875rem;font-weight:600}
@media(min-width:640px){.np-strip{padding-inline:2rem}}
.np-strip-greeting{display:inline-flex;align-items:center;gap:.5rem}
.np-hero-name{padding:3.5rem 1rem 1.5rem;font-size:clamp(3.25rem,11vw,9.5rem);line-height:.92;letter-spacing:-.045em;overflow-wrap:anywhere;text-wrap:balance}
@media(min-width:640px){.np-hero-name{padding-inline:2rem}}
@media(min-width:1024px){.np-hero-name{padding-top:5rem}}
.np-mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.14em;margin-bottom:-.14em;margin-inline:.1em}
.np-word{display:inline-block}
.np-js .np-word{transform:translateY(110%);animation:np-rise 1s cubic-bezier(.2,.8,.2,1) forwards;animation-delay:calc(.15s + var(--i,0)*.12s)}
.np-js .np-load{opacity:0;transform:translateY(16px);animation:np-fade .9s cubic-bezier(.2,.7,.2,1) forwards;animation-delay:var(--d,0ms)}
.np-hero-bottom{display:grid;border-top:1px solid var(--line);text-align:left}
.np-hero-copy{display:flex;flex-direction:column;justify-content:center;padding:1.25rem}
@media(min-width:640px){.np-hero-copy{padding:2rem}}
@media(min-width:768px){
.np-hero-bottom{grid-template-columns:repeat(12,minmax(0,1fr))}
.np-hero-bottom>*{min-width:0}
.np-hero-copy{grid-column:span 12/span 12}
.np-hero-copy.np-has-portrait{grid-column:span 7/span 7;border-right:1px solid var(--line)}
.np-portrait{grid-column:span 5/span 5}
}
.np-hero-desc{max-width:36rem;white-space:pre-line;font-size:1.125rem;line-height:1.7}
.np-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.75rem}
.np-portrait{position:relative;overflow:hidden;background:var(--soft);min-height:16rem}
.np-portrait img{aspect-ratio:4/3;height:100%;width:100%;object-fit:cover;filter:grayscale(.35) contrast(1.03);transition:transform .9s cubic-bezier(.2,.7,.2,1),filter .6s ease}
.np-portrait:hover img{transform:scale(1.04);filter:grayscale(0)}
.np-initials{display:flex;aspect-ratio:4/3;height:100%;width:100%;align-items:center;justify-content:center;background:var(--soft);font-size:6rem;line-height:1;color:var(--accent)}
@media(min-width:768px){.np-portrait img,.np-initials{aspect-ratio:auto}}

/* ---------- About ---------- */
.np-img-cell{overflow:hidden;background:var(--soft)}
@media(min-width:1024px){.np-img-cell{border-right:1px solid var(--line)}}
.np-img-cell img{height:100%;max-height:44rem;min-height:18rem;width:100%;object-fit:cover;filter:grayscale(.3);transition:transform .9s cubic-bezier(.2,.7,.2,1),filter .6s ease}
.np-img-cell:hover img{transform:scale(1.03);filter:grayscale(0)}
.np-about-text{max-width:40rem;margin-top:1.75rem;white-space:pre-line;font-size:1.125rem;line-height:1.75}
.np-hl-grid{border-top:1px solid var(--line)}
@media(min-width:640px){.np-hl-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
.np-hl{padding:1.25rem}
@media(min-width:640px){.np-hl{padding:1.75rem}}
.np-hl-title{display:block;margin-top:.75rem;font-size:1.25rem;line-height:1.25}
.np-hl-desc{display:block;margin-top:.5rem;font-size:.95rem;line-height:1.625}
.np-about-actions{display:flex;flex-wrap:wrap;gap:.75rem;border-top:1px solid var(--line);padding:1.25rem}
@media(min-width:640px){.np-about-actions{padding:2.5rem}}

/* ---------- Journey ---------- */
.np-journey-split{border-bottom:1px solid var(--line)}
@media(min-width:1024px){.np-journey{border-right:1px solid var(--line)}.np-journey-split{border-bottom:0}}
.np-entry{position:relative;border-bottom:1px solid var(--line);padding:1.25rem;transition:background-color .3s ease}
.np-entry:last-child{border-bottom:0}
@media(min-width:640px){.np-entry{padding-inline:2rem}}
.np-entry::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--accent);transform:scaleY(0);transform-origin:top;transition:transform .5s cubic-bezier(.2,.7,.2,1)}
.np-entry:hover{background:var(--surface)}
.np-entry:hover::before{transform:scaleY(1)}
.np-period{font-size:.875rem;font-weight:600;color:var(--accent)}
.np-item-title{margin-top:.25rem;font-size:1.25rem;line-height:1.375}
.np-company{margin-top:.125rem;font-size:.95rem;font-weight:600}
.np-item-desc{margin-top:.5rem;white-space:pre-line;font-size:.95rem;line-height:1.65}

/* ---------- Skills & services ---------- */
.np-skills-head{display:grid;gap:1rem}
@media(min-width:768px){.np-skills-head{grid-template-columns:repeat(12,minmax(0,1fr));align-items:end}.np-skills-title{grid-column:span 7/span 7}.np-skills-desc{grid-column:span 5/span 5}}
.np-skills-desc{white-space:pre-line;font-size:.95rem;line-height:1.7}
@media(min-width:640px){.np-skill-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1280px){.np-skill-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.np-skill{padding:1.25rem}
@media(min-width:640px){.np-skill{padding:1.5rem}}
.np-skill-row{display:flex;align-items:center;gap:.75rem}
.np-skill-name{font-weight:600}
.np-skill-pct{margin-left:auto;font-size:.875rem;font-weight:600;font-variant-numeric:tabular-nums}
.np-tile{display:inline-flex;height:2.25rem;width:2.25rem;flex-shrink:0;align-items:center;justify-content:center;border:1px solid var(--line);background:var(--surface);color:var(--accent);transition:background-color .3s ease,color .3s ease,transform .35s cubic-bezier(.2,.7,.2,1)}
.np-tile-lg{height:3.25rem;width:3.25rem}
.np-tile-letter{font-size:.875rem;line-height:1}
.np-tile-glyph{font-size:1rem;line-height:1}
.np-tile-glyph-lg{font-size:1.25rem;line-height:1}
.np-skill:hover .np-tile,.np-service:hover .np-tile{background:var(--accent);color:var(--accent-ink);transform:rotate(-6deg) scale(1.06)}
.np-track{height:4px;width:100%;margin-top:1rem;background:color-mix(in srgb,var(--ink) 12%,transparent);overflow:hidden}
.np-bar{height:100%;width:var(--w,0%);background:var(--accent)}
.np-js [data-reveal] .np-bar{width:0;transition:width 1.4s cubic-bezier(.2,.7,.2,1) calc(var(--d,0ms) + .3s)}
.np-js [data-reveal].is-in .np-bar{width:var(--w,0%)}
@media(min-width:640px){.np-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.np-service-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.np-service{padding:1.5rem}
@media(min-width:640px){.np-service{padding:2rem}}
.np-service-title{margin-top:1.75rem;font-size:1.5rem;line-height:1.25}
.np-service-text{margin-top:.75rem;line-height:1.65}

/* ---------- Projects ---------- */
.np-proj-head{display:grid;gap:1rem}
@media(min-width:1024px){.np-proj-head{grid-template-columns:repeat(12,minmax(0,1fr));align-items:end}.np-proj-title{grid-column:span 7/span 7}.np-proj-desc{grid-column:span 5/span 5}}
.np-proj-desc{white-space:pre-line;line-height:1.7}
.np-proj-list{display:grid}
.np-proj-list>li{min-width:0}
@media(min-width:1024px){.np-proj-list{grid-template-columns:repeat(12,minmax(0,1fr))}.np-proj-list>li:not(.np-wide):nth-child(odd){border-right:1px solid var(--line)}}
.np-proj-item{border-bottom:1px solid var(--line)}
.np-project{display:flex;height:100%;flex-direction:column}
.np-media{position:relative;display:block;overflow:hidden;border-bottom:1px solid var(--line);background:var(--soft);isolation:isolate}
.np-media-std{aspect-ratio:16/9}
.np-media-wide{aspect-ratio:21/9}
.np-media img{width:100%;height:100%;object-fit:cover;filter:grayscale(.4)}
.np-media img,.np-tone{transition:transform .9s cubic-bezier(.2,.7,.2,1),filter .6s ease}
.np-project:hover .np-media img{filter:grayscale(0)}
.np-project:hover .np-media img,.np-project:hover .np-tone{transform:scale(1.04)}
.np-tone{display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-size:6rem;line-height:1}
.np-tone-0{background:var(--accent);color:var(--accent-ink)}
.np-tone-1{background:var(--surface);color:var(--accent)}
.np-tone-2{background:var(--ink);color:var(--paper)}
.np-flag{position:absolute;left:1rem;top:1rem;background:var(--accent);color:var(--accent-ink);padding:.25rem .75rem;font-size:.8rem;font-weight:700}
.np-go{position:absolute;right:1rem;top:1rem;display:inline-flex;height:2.75rem;width:2.75rem;align-items:center;justify-content:center;background:var(--paper);color:var(--ink);opacity:0;transform:translate(-.5rem,.5rem);transition:opacity .35s ease,transform .45s cubic-bezier(.2,.7,.2,1)}
.np-project:hover .np-go,.np-media:focus-visible .np-go{opacity:1;transform:none}
.np-proj-body{display:grid;flex:1;gap:1.25rem;padding:1.25rem}
@media(min-width:640px){.np-proj-body{grid-template-columns:repeat(12,minmax(0,1fr));padding:2rem}.np-proj-main{grid-column:span 7/span 7}.np-proj-side{grid-column:span 5/span 5;border-left:1px solid var(--line);padding-left:1.5rem}}
.np-proj-main{min-width:0}
.np-proj-titlerow{display:flex;align-items:baseline;gap:1rem}
.np-num{flex-shrink:0;width:3.5rem;font-size:3rem;line-height:.9;color:var(--accent);font-variant-numeric:tabular-nums}
.np-proj-name{font-size:1.5rem;line-height:1.25}
@media(min-width:640px){.np-proj-name{font-size:1.7rem}}
.np-underline{background:linear-gradient(var(--accent),var(--accent)) left 96%/0% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1)}
.np-project:hover .np-underline{background-size:100% 2px}
.np-proj-text{margin-top:1rem;line-height:1.65}
@media(min-width:640px){.np-proj-text{padding-left:4.5rem}}
.np-proj-side{display:flex;flex-direction:column;gap:1rem}
.np-tags{display:flex;flex-wrap:wrap;gap:.5rem}
.np-repo{margin-top:auto;align-self:flex-start}

/* ---------- Contact ---------- */
.np-contact{background:var(--ink);color:var(--paper)}
.np-contact-left,.np-contact-right{padding:1.5rem}
@media(min-width:640px){.np-contact-left,.np-contact-right{padding:3rem}}
@media(min-width:1024px){.np-contact-right{border-left:1px solid color-mix(in srgb,var(--paper) 28%,transparent)}}
.np-contact-title{font-size:clamp(2.75rem,7.5vw,6rem);line-height:.95;overflow-wrap:anywhere}
.np-contact-sub{margin-top:1.25rem;font-size:1.25rem;font-weight:500;color:var(--accent)}
.np-contact-text{max-width:34rem;margin-top:1.25rem;white-space:pre-line;font-size:1.125rem;line-height:1.7;opacity:.8}
.np-contact-info{margin-top:2.5rem}
.np-contact-info>*+*{margin-top:1.25rem}
.np-bigmail{display:inline-flex;max-width:100%;align-items:center;gap:.75rem;word-break:break-all;font-size:1.5rem}
@media(min-width:640px){.np-bigmail{font-size:2.25rem}}
.np-underline-contact{background:linear-gradient(currentColor,currentColor) left bottom/100% 2px no-repeat;transition:background-size .5s cubic-bezier(.2,.7,.2,1);padding-bottom:4px}
.np-bigmail:hover .np-underline-contact{background-size:0% 2px;background-position:right bottom}
.np-contact-meta{display:flex;flex-wrap:wrap;gap:.75rem 2rem;font-weight:500}
.np-contact-meta>span{display:inline-flex;align-items:center;gap:.5rem}
.np-contact-link{display:inline-flex;align-items:center;gap:.5rem;opacity:.9}
.np-contact-link:hover{opacity:1;text-decoration:underline;text-underline-offset:4px}
.np-contact-socials{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:2rem}
.np-social{display:inline-flex;align-items:center;gap:.35rem;border:1px solid color-mix(in srgb,currentColor 40%,transparent);padding:.45rem 1rem;font-size:.9rem;font-weight:600;transition:background-color .3s ease,color .3s ease,border-color .3s ease,transform .3s ease}
.np-social:hover{background:var(--paper);color:var(--ink);border-color:var(--paper);transform:translateY(-2px)}
.np-field{display:block;margin-bottom:1.25rem}
.np-label{display:block;margin-bottom:.5rem;font-size:.875rem;font-weight:600}
.np-input{width:100%;border:1px solid color-mix(in srgb,var(--paper) 35%,transparent);background:color-mix(in srgb,var(--paper) 8%,transparent);padding:.8rem 1rem;font:inherit;font-weight:500;color:var(--paper);transition:border-color .25s ease,box-shadow .25s ease}
.np-input:hover{border-color:color-mix(in srgb,var(--paper) 65%,transparent)}
.np-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 35%,transparent)}
textarea.np-input{resize:vertical}
.np-sent{display:flex;align-items:center;gap:.5rem;margin-top:1.25rem;font-size:.875rem;font-weight:600;animation:np-pop .5s cubic-bezier(.2,.8,.2,1) both}
.np-hidden-frame{display:none}
.np-foot{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;border-top:1px solid var(--line);padding:1.5rem 1.25rem;font-size:.875rem}
@media(min-width:640px){.np-foot{padding-inline:2.5rem}}

/* ---------- Scroll reveal (class is added by script.js, so no-JS output stays visible) ---------- */
.np-js [data-reveal]{opacity:0;transform:translateY(20px);transition:opacity .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms),transform .8s cubic-bezier(.2,.7,.2,1) var(--d,0ms)}
.np-js [data-reveal].is-in{opacity:1;transform:none}
.np-js [data-reveal].is-in .np-media{animation:np-wipe 1.1s cubic-bezier(.7,0,.2,1) .1s backwards}
@keyframes np-rise{to{transform:translateY(0)}}
@keyframes np-fade{to{opacity:1;transform:none}}
@keyframes np-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes np-pop{from{opacity:0;transform:translateY(8px) scale(.96)}}
@keyframes np-wipe{from{clip-path:inset(0 0 100% 0)}}

/* ---------- Reduced motion ---------- */
@media(prefers-reduced-motion:reduce){
html{scroll-behavior:auto}
.np-root *,.np-root *::before,.np-root *::after{animation:none!important;transition:none!important}
.np-js .np-word,.np-js .np-load,.np-js [data-reveal]{opacity:1;transform:none}
.np-js [data-reveal] .np-bar{width:var(--w,0%)}
}
CSS;
    }
}
