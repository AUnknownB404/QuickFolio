<?php

namespace App\Http\Controllers\PortfolioExport;

trait CobaltBrutalistTemplate
{
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
        $projectContent = $this->sectionData($content['projects'] ?? []);
        $servicesData = $this->sectionData($content['services'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);

        $highlights = $list($about['about_highlights'] ?? []);
        $journeyItems = $list($journey['journey_list'] ?? []);
        $skills = $list($skillsData['skills_list'] ?? []);
        $projects = $list($projectContent['projects_list'] ?? []);
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
        $hasProjects = ! empty($projectContent['projects_subtitle']) || ! empty($projectContent['projects_title'])
            || ! empty($projectContent['projects_description']) || $projects !== [];
        $hasServices = ! empty($servicesData['services_subtitle']) || ! empty($servicesData['services_title']) || $services !== [];
        $hasContact = ! empty($contact['contact_subtitle']) || ! empty($contact['contact_title'])
            || ! empty($contact['contact_description']) || $email !== '' || $phone !== '' || $location !== ''
            || $contactSocials !== [] || $formReady;

        $navLinks = [];
        foreach ([
            ['about', 'About', $hasAbout],
            ['journey', 'Journey', $hasJourney],
            ['skills', 'Skills', $hasSkills],
            ['projects', 'Work', $hasProjects],
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
            $featureFirst = $isEnabled($projectContent['projects_show_featured'] ?? null, false);
            $html .= '<section id="projects" data-page-section><div class="nb-container nb-projects-section"><div class="nb-project-heading" data-reveal><div>';
            if (! empty($projectContent['projects_subtitle'])) {
                $html .= '<p class="nb-kicker">'.$escape($projectContent['projects_subtitle']).'</p>';
            }
            if (! empty($projectContent['projects_title'])) {
                $html .= '<h2 class="nb-display nb-h2">'.$escape($projectContent['projects_title']).'</h2>';
            }
            $html .= '</div>';
            if (! empty($projectContent['projects_description'])) {
                $html .= '<p class="nb-muted">'.$escape($projectContent['projects_description']).'</p>';
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
}
