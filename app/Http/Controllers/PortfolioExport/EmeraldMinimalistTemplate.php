<?php

namespace App\Http\Controllers\PortfolioExport;

trait EmeraldMinimalistTemplate
{
    private function renderEmeraldMinimalistPage(string $title, array $content, array $assets, ?array $googleForm): string
    {
        $escape = fn (mixed $value): string => e(is_scalar($value) ? (string) $value : '');
        $assetUrl = fn (mixed $value): string => $this->assetUrl($value, $assets);
        $url = fn (mixed $value, string $fallback = '#'): string => $escape($this->safeUrl($assetUrl($value), $fallback));
        $navbar = $this->sectionData($content['navbar'] ?? []);
        $hero = $this->sectionData($content['header'] ?? []);
        $about = $this->sectionData($content['about'] ?? []);
        $journey = $this->sectionData($content['journey'] ?? []);
        $skills = $this->sectionData($content['skills'] ?? []);
        $projectContent = $this->sectionData($content['projects'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);
        $projects = $this->items($projectContent['projects_list'] ?? $projectContent['projects'] ?? []);
        $journeyItems = $this->items($journey['journey_list'] ?? []);
        $skillItems = $this->items($skills['skills_list'] ?? []);
        $categories = array_values(array_unique(array_filter(array_map(
            static fn (array $project): string => is_string($project['category'] ?? null) ? $project['category'] : '',
            $projects
        ))));
        $showFilters = count($categories) > 1;
        $projectFilters = $showFilters ? array_merge(['All'], $categories) : [];
        $logo = is_string($navbar['logo_text'] ?? null) ? $navbar['logo_text'] : '';
        $showLogo = ! in_array($navbar['show_logo'] ?? true, [false, 0, '0', 'false'], true);
        $logoImage = $url($navbar['logo_image'] ?? null, 'Logo.svg');
        $heroName = is_string($hero['hero_name'] ?? null) ? trim($hero['hero_name']) : '';
        $heroWords = $heroName !== '' ? preg_split('/\s+/', $heroName, -1, PREG_SPLIT_NO_EMPTY) : [];
        $heroImage = $assetUrl($hero['hero_image'] ?? null);
        $aboutImage = $assetUrl($about['about_image'] ?? null);
        $formEnabled = ! in_array($contact['contact_form_enabled'] ?? true, [false, 0, '0', 'false'], true);
        $formReady = $formEnabled && $googleForm !== null;
        $skillLabels = [];
        foreach ($skillItems as $skill) {
            $label = $skill['label'] ?? null;
            if (is_string($label) && trim($label) !== '') {
                $skillLabels[] = trim($label);
            }
        }
        $marqueeItems = $skillLabels;
        while ($marqueeItems !== [] && count($marqueeItems) < 8) {
            $marqueeItems = array_merge($marqueeItems, $skillLabels);
        }
        $navLinks = [['id' => 'home', 'label' => 'Home']];
        if (! empty($about['about_title']) || ! empty($about['about_description'])) {
            $navLinks[] = ['id' => 'about', 'label' => 'About'];
        }
        if ($journeyItems !== []) {
            $navLinks[] = ['id' => 'journey', 'label' => 'Experience'];
        }
        if ($skillItems !== []) {
            $navLinks[] = ['id' => 'skills', 'label' => 'Skills'];
        }
        if ($projects !== []) {
            $navLinks[] = ['id' => 'projects', 'label' => 'Projects'];
        }
        $navLinks[] = ['id' => 'contact', 'label' => 'Contact'];
        $desktopNav = '';
        $mobileNav = '';
        foreach ($navLinks as $link) {
            $desktopNav .= '<a href="#'.$escape($link['id']).'" class="em-nav-link">'.$escape($link['label']).'</a>';
            $mobileNav .= '<a href="#'.$escape($link['id']).'" class="em-mobile-link">'.$escape($link['label']).'</a>';
        }

        $html = '<div class="folio">
    <div class="em-progress-track"><div class="em-progress-bar"></div></div>
    <nav class="em-navbar" aria-label="Main navigation">
        <div class="em-nav-inner">
            <a href="#home" class="em-brand" aria-label="Back to top">';
        if ($showLogo) {
            $html .= '<img src="'.$logoImage.'" alt="'.$escape($logo).'" width="40" height="40" class="em-logo-mark">';
        } else {
            $html .= '<span></span>';
        }
        $html .= '</a><div class="em-desktop-nav">'.$desktopNav.'</div>
            <button class="em-menu-toggle" type="button" aria-expanded="false" aria-controls="em-mobile-nav" aria-label="Toggle menu"><span class="em-burger"><i></i><i></i><i></i></span></button>
        </div>
        <div class="em-mobile-nav" id="em-mobile-nav" hidden>'.$mobileNav.'</div>
    </nav>
    <header id="home" class="em-hero">
        <div class="em-hero-bg"></div><div class="em-orb em-orb-a"></div><div class="em-orb em-orb-b"></div><div class="em-orb em-orb-c"></div><div class="em-hero-grid"></div>
        <div class="em-container em-hero-layout"><div class="em-hero-copy">';
        if (! empty($hero['hero_greeting'])) {
            $html .= '<p class="em-hero-in em-greeting" style="--delay:.1s"><span class="em-ping"><i></i><i></i></span>'.$escape($hero['hero_greeting']).'</p>';
        }
        if ($heroWords !== []) {
            $html .= '<h1 class="display em-hero-title">';
            foreach ($heroWords as $index => $word) {
                $html .= '<span class="em-word-mask"><span class="em-word" style="--delay:'.(.25 + $index * .12).'s">'.$escape($word).'&nbsp;</span></span>';
            }
            $html .= '</h1>';
        }
        if (! empty($hero['hero_role'])) {
            $html .= '<p class="em-hero-in display em-hero-role" style="--delay:.7s">'.$escape($hero['hero_role']).'</p>';
        }
        if (! empty($hero['hero_description'])) {
            $html .= '<p class="em-hero-in em-hero-description" style="--delay:.85s">'.$escape($hero['hero_description']).'</p>';
        }
        if (! empty($hero['hero_cta_primary_text'])) {
            $html .= '<div class="em-hero-in em-hero-cta" style="--delay:1s"><a href="'.$url($hero['hero_cta_primary_url'] ?? null, '#projects').'" class="em-cta">'.$escape($hero['hero_cta_primary_text']).'<span aria-hidden="true">→</span></a></div>';
        }
        $heroFallback = $heroName !== '' ? mb_substr($heroName, 0, 1) : '';
        $html .= '</div><div class="em-hero-portrait em-hero-in" style="--delay:.4s"><div class="em-portrait-wrap"><span class="em-ring em-ring-1"></span><span class="em-ring em-ring-2"></span><div class="em-blob">';
        if ($heroImage !== '') {
            $html .= '<img src="'.$url($hero['hero_image'], '').'" alt="'.$escape($heroName).'">';
        } elseif ($heroFallback !== '') {
            $html .= '<div class="em-hero-fallback"><span aria-hidden="true">🌿</span><span>Emerald Portfolio</span></div>';
        }
        $html .= '</div><span class="em-dot em-dot-1"></span><span class="em-dot em-dot-2"></span><span class="em-dot em-dot-3"></span></div></div></div>
        <a href="#about" class="em-scroll-cue" aria-label="Scroll down"><span><i></i></span></a>
    </header>';

        if ($marqueeItems !== []) {
            $marqueeItems = array_merge($marqueeItems, $marqueeItems);
            $html .= '<div class="em-marquee" aria-hidden="true"><div class="em-marquee-track">';
            foreach ($marqueeItems as $label) {
                $html .= '<span class="display">'.$escape($label).'<b>✦</b></span>';
            }
            $html .= '</div></div>';
        }

        $html .= '<section id="about" class="em-about em-section"><div class="em-orb em-orb-b em-opacity-50"></div><div class="em-hero-grid"></div><div class="em-container em-about-layout">';
        if ($aboutImage !== '') {
            $html .= '<div class="em-reveal em-reveal-left em-about-image"><div class="em-about-glow"></div><div class="em-about-frame"><img src="'.$url($about['about_image'], '').'" alt="'.$escape($about['about_title'] ?? '').'" loading="lazy"></div></div>';
        }
        $html .= '<div class="em-reveal em-about-copy'.($aboutImage === '' ? ' em-about-copy-wide' : '').'"><span class="em-section-tag">About me</span>';
        if (! empty($about['about_title'])) {
            $html .= '<h2 class="display em-section-title">'.$escape($about['about_title']).'</h2>';
        }
        if (! empty($about['about_description'])) {
            $html .= '<p class="em-about-description">'.$escape($about['about_description']).'</p>';
        }
        if (! empty($about['about_cv_file'])) {
            $html .= '<div class="em-about-download"><a href="'.$url($about['about_cv_file']).'" class="em-cta">Download CV / Resume <span aria-hidden="true">↓</span></a></div>';
        }
        $html .= '</div></div></section>';

        if ($journeyItems !== []) {
            $html .= '<section id="journey" class="em-journey em-section"><div class="em-orb em-orb-a em-opacity-30"></div><div class="em-hero-grid"></div><div class="em-container em-journey-content"><div class="em-reveal em-section-intro"><span class="em-section-tag">Experience</span>';
            if (! empty($journey['journey_title'])) {
                $html .= '<h2 class="display em-section-title">'.$escape($journey['journey_title']).'</h2>';
            }
            if (! empty($journey['journey_subtitle'])) {
                $html .= '<p class="em-section-subtitle">'.$escape($journey['journey_subtitle']).'</p>';
            }
            $html .= '</div><div class="em-timeline"><div class="em-spine em-reveal"></div><div class="em-timeline-items">';
            foreach ($journeyItems as $index => $item) {
                $html .= '<article class="em-reveal em-timeline-item" style="--reveal-delay:'.(($index % 3) * 100).'ms"><span class="em-timeline-marker"><i></i></span><div class="em-journey-card">';
                $html .= '<div class="em-journey-meta">';
                if (! empty($item['period'])) {
                    $html .= '<span class="em-period">'.$escape($item['period']).'</span>';
                }
                if (! empty($item['company'])) {
                    $html .= '<span class="em-company">'.$escape($item['company']).'</span>';
                }
                $html .= '</div>';
                if (! empty($item['title'])) {
                    $html .= '<h3 class="display em-journey-title">'.$escape($item['title']).'</h3>';
                }
                if (! empty($item['description'])) {
                    $html .= '<p class="em-journey-description">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></article>';
            }
            $html .= '</div></div></div></section>';
        }

        if ($skillItems !== []) {
            $html .= '<section id="skills" class="em-skills em-section"><div class="em-orb em-orb-b em-opacity-60"></div><div class="em-hero-grid"></div><div class="em-container em-skills-content"><div class="em-reveal em-section-intro"><span class="em-section-tag">Expertise</span>';
            if (! empty($skills['skills_title'])) {
                $html .= '<h2 class="display em-section-title">'.$escape($skills['skills_title']).'</h2>';
            }
            if (! empty($skills['skills_description'])) {
                $html .= '<p class="em-section-subtitle">'.$escape($skills['skills_description']).'</p>';
            }
            $html .= '</div><div class="em-skills-grid">';
            $circumference = 2 * M_PI * 52;
            foreach ($skillItems as $index => $skill) {
                $label = is_string($skill['label'] ?? null) ? $skill['label'] : '';
                if ($label === '') {
                    continue;
                }
                $percent = is_numeric($skill['percent'] ?? null) && (float) $skill['percent'] !== 0.0
                    ? max(0, min(100, (float) $skill['percent']))
                    : 100;
                $dashOffset = $circumference * (1 - $percent / 100);
                $delay = .25 + $index * .11;
                $html .= '<article class="em-reveal em-skill-card" style="--skill-delay:'.$delay.'s"><div class="em-meter"><span class="em-halo"></span><svg viewBox="0 0 120 120" aria-hidden="true"><defs><linearGradient id="em-skill-gradient-'.$index.'" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#00A388"/><stop offset="100%" stop-color="#9BF0D2"/></linearGradient></defs><circle class="em-orbit" cx="60" cy="60" r="58" fill="none" stroke="rgba(155,240,210,.3)" stroke-width=".8" stroke-dasharray="1.5 5"/><g transform="rotate(-90 60 60)"><circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="8"/><circle class="em-arc" cx="60" cy="60" r="52" fill="none" stroke="url(#em-skill-gradient-'.$index.')" stroke-width="8" stroke-linecap="round" stroke-dasharray="'.$circumference.'" style="--circ:'.$circumference.';--offset:'.$dashOffset.';--degrees:'.($percent * 3.6).'deg"/><g class="em-tip" style="--degrees:'.($percent * 3.6).'deg"><circle cx="112" cy="60" r="8" fill="rgba(92,240,189,.25)"/><circle cx="112" cy="60" r="4" fill="#fff"/></g></g></svg><span class="em-skill-count" data-target="'.(int) round($percent).'">0<span>%</span></span></div><span class="display em-skill-label">'.$escape($label).'</span></article>';
            }
            $html .= '</div></div></section>';
        }

        if ($projects !== []) {
            $html .= '<section id="projects" class="em-projects em-section"><div class="em-orb em-orb-a em-opacity-25"></div><div class="em-hero-grid"></div><div class="em-container em-projects-content"><div class="em-reveal em-section-intro"><span class="em-section-tag">Selected projects</span>';
            if (! empty($projectContent['projects_title'])) {
                $html .= '<h2 class="display em-section-title">'.$escape($projectContent['projects_title']).'</h2>';
            }
            if (! empty($projectContent['projects_subtitle'])) {
                $html .= '<p class="em-section-subtitle">'.$escape($projectContent['projects_subtitle']).'</p>';
            }
            $html .= '</div>';
            if ($showFilters) {
                $html .= '<div class="em-reveal em-filters" role="group" aria-label="Filter projects">';
                foreach ($projectFilters as $filter) {
                    $html .= '<button type="button" class="em-filter'.($filter === 'All' ? ' active' : '').'" data-project-filter="'.$escape($filter).'">'.$escape($filter).'</button>';
                }
                $html .= '</div>';
            }
            $html .= '<div class="em-project-grid">';
            foreach ($projects as $project) {
                $category = is_string($project['category'] ?? null) ? $project['category'] : '';
                $image = $assetUrl($project['image'] ?? null);
                $projectUrl = $project['url'] ?? null;
                $html .= '<article class="em-project-card" data-project-category="'.$escape($category).'"><div class="em-project-image">';
                if ($image !== '') {
                    $html .= '<img src="'.$url($project['image'], '').'" alt="'.$escape($project['title'] ?? '').'" loading="lazy">';
                } else {
                    $html .= '<span class="em-project-fallback" aria-hidden="true">📂</span>';
                }
                if (is_string($projectUrl) && $projectUrl !== '') {
                    $html .= '<div class="em-project-overlay"><a href="'.$url($projectUrl).'" target="_blank" rel="noopener noreferrer">View project <span aria-hidden="true">↗</span></a></div>';
                }
                $html .= '</div><div class="em-project-copy">';
                if ($category !== '') {
                    $html .= '<span class="em-project-category">'.$escape($category).'</span>';
                }
                if (! empty($project['title'])) {
                    $html .= '<h3 class="display">'.$escape($project['title']).'</h3>';
                }
                $html .= '</div></article>';
            }
            $html .= '</div></div></section>';
        }

        $html .= '<section id="contact" class="em-contact em-section"><div class="em-orb em-orb-b em-opacity-50"></div><div class="em-hero-grid"></div><div class="em-container em-contact-container"><div class="em-reveal em-contact-card"><div class="em-orb em-orb-a em-opacity-40"></div><div class="em-hero-grid"></div><div class="em-contact-heading"><span class="em-section-tag">Get in touch</span>';
        if (! empty($contact['contact_title'])) {
            $html .= '<h2 class="display em-section-title">'.$escape($contact['contact_title']).'</h2>';
        }
        if (! empty($contact['contact_description'])) {
            $html .= '<p class="em-contact-description">'.$escape($contact['contact_description']).'</p>';
        }
        $html .= '</div>';
        $email = is_string($contact['contact_email'] ?? null) ? $contact['contact_email'] : '';
        if ($email !== '') {
            $html .= '<a href="'.$escape($this->safeUrl('mailto:'.$email)).'" class="em-email-link">'.$escape($email).'<span aria-hidden="true">↗</span></a>';
        }
        $html .= '<div class="em-contact-meta">';
        if (! empty($contact['contact_phone'])) {
            $html .= '<span>Phone: '.$escape($contact['contact_phone']).'</span>';
        }
        if (! empty($contact['contact_location'])) {
            $html .= '<span>Location: '.$escape($contact['contact_location']).'</span>';
        }
        if ($formReady) {
            $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="em-gform-frame" class="em-contact-form" data-google-form>
                <h3 class="display">Send a message</h3>
                <label>Name<input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name"></label>
                <label>Email<input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email"></label>
                <label>Message<textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required></textarea></label>
                <button type="submit" class="em-cta">Send message <span aria-hidden="true">→</span></button>
                <p class="em-form-status" role="status" data-form-status hidden></p>
            </form>';
        }
        $html .= '</div></div></div>';
        if ($formReady) {
            $html .= '<iframe name="em-gform-frame" title="Form submission target" class="em-hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
        }
        $html .= '</section><footer class="em-footer"><div class="em-container">';
        if ($logo !== '') {
            $html .= '<span class="display">'.$escape($logo).'</span>';
        }
        $html .= '<p><img src="Logo.svg" alt="" width="20" height="20"> Built with quickFolio · Greenish Edition</p></div></footer></div>';

        return '<!doctype html>
<html lang="en" data-template="emerald-minimalist">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#06231e">
    <title>'.$escape($title).'</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    private function emeraldMinimalistStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=DM+Sans:wght@400;500;600;700&display=swap');
:root{--em-bg:#06231e;--em-deep:#041a16;--em-section:#05201b;--em-green:#5cf0bd;--em-mint:#9bf0d2;--em-text:#f0fff9;--em-muted:#a9c9bf;--em-ease:cubic-bezier(.22,1,.36,1)}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:88px}
body{margin:0;background:var(--em-bg);color:var(--em-text);font:16px/1.55 "DM Sans",ui-sans-serif,system-ui,sans-serif}
a{color:inherit;text-decoration:none}
button,input,textarea{font:inherit}
img{display:block;max-width:100%}
.folio{min-height:100vh;overflow:hidden;background:var(--em-bg);color:var(--em-text)}
.display{font-family:"Bricolage Grotesque","DM Sans",ui-sans-serif,system-ui,sans-serif}
.em-container{width:min(1280px,100% - 48px);margin-inline:auto}
.em-progress-track{position:fixed;inset:0 0 auto;z-index:100;height:3px}
.em-progress-bar{width:100%;height:100%;transform:scaleX(0);transform-origin:left;background:var(--em-green)}
.em-navbar{position:fixed;inset:0 0 auto;z-index:90;border-bottom:1px solid transparent;transition:background .35s,border-color .35s,backdrop-filter .35s}
.em-navbar.is-scrolled{border-color:rgba(255,255,255,.08);background:rgba(4,26,22,.84);backdrop-filter:blur(18px)}
.em-nav-inner{width:min(1280px,100% - 48px);height:78px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:24px}
.em-brand{display:inline-flex;align-items:center;gap:12px;min-width:150px}
.em-logo-mark{display:grid;width:40px;height:40px;place-items:center;border-radius:12px;background:linear-gradient(135deg,#00a388,#004d40);color:white;font-size:18px;font-weight:700;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:transform .5s}
.em-brand:hover .em-logo-mark{transform:rotate(-8deg) scale(1.05)}
.em-logo-text{font-size:18px;font-weight:700}
.em-desktop-nav{display:flex;align-items:center;gap:30px}
.em-nav-link{padding:8px 16px;border-radius:999px;color:rgba(255,255,255,.75);font-size:14px;font-weight:500;transition:color .2s,background .2s}
.em-nav-link:hover,.em-nav-link.active{background:rgba(255,255,255,.1);color:white}
.em-menu-toggle{display:none;width:44px;height:44px;align-items:center;justify-content:center;border:0;border-radius:12px;background:rgba(255,255,255,.1);color:white;cursor:pointer}
.em-burger{display:grid;gap:4px}
.em-burger i{display:block;width:18px;height:2px;border-radius:3px;background:currentColor;transition:transform .3s,opacity .3s}
.em-menu-toggle[aria-expanded=true] .em-burger i:first-child{transform:translateY(6px) rotate(45deg)}
.em-menu-toggle[aria-expanded=true] .em-burger i:nth-child(2){opacity:0}
.em-menu-toggle[aria-expanded=true] .em-burger i:last-child{transform:translateY(-6px) rotate(-45deg)}
.em-mobile-nav{position:absolute;top:calc(100% + 1px);left:0;right:0;display:grid;gap:4px;padding:12px 24px 20px;border-bottom:1px solid rgba(255,255,255,.1);background:rgba(4,26,22,.97)}
.em-mobile-nav[hidden]{display:none}
.em-mobile-link{padding:12px 8px;color:#bfe0d6;font-weight:600}
.em-mobile-link:hover{color:var(--em-green)}
.em-hero{position:relative;display:flex;min-height:100svh;align-items:center;overflow:hidden;padding:112px 0 80px;background:radial-gradient(ellipse at 70% 50%,rgba(0,105,92,.17),transparent 48%),var(--em-bg)}
.em-hero-bg{position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,163,136,.08),transparent 55%)}
.em-hero-grid,.em-hero-grid:before{position:absolute;inset:0;pointer-events:none}
.em-hero-grid{background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);background-size:56px 56px;mask-image:radial-gradient(ellipse at center,#000 20%,transparent 76%)}
.em-orb{position:absolute;border-radius:50%;filter:blur(90px);pointer-events:none}
.em-orb-a{top:-140px;left:-120px;width:560px;height:560px;background:#00a388;opacity:.35;animation:em-drift 18s ease-in-out infinite}
.em-orb-b{right:-100px;bottom:-120px;width:480px;height:480px;background:#2fbf9b;opacity:.23;animation:em-drift 22s ease-in-out infinite reverse}
.em-orb-c{top:40%;left:45%;width:320px;height:320px;background:#9bf0d2;opacity:.1;animation:em-drift 26s ease-in-out infinite}
.em-opacity-25{opacity:.25}.em-opacity-30{opacity:.3}.em-opacity-40{opacity:.4}.em-opacity-50{opacity:.5}.em-opacity-60{opacity:.6}
.em-hero-layout{position:relative;z-index:1;display:grid;grid-template-columns:7fr 5fr;align-items:center;gap:48px}
.em-hero-copy{max-width:720px}
.em-greeting{display:inline-flex;align-items:center;gap:12px;margin:0 0 24px;padding:9px 15px;border:1px solid rgba(155,240,210,.2);border-radius:999px;background:rgba(255,255,255,.04);color:var(--em-mint);font-size:14px;font-weight:600}
.em-ping{position:relative;width:9px;height:9px;border-radius:50%;background:var(--em-green)}
.em-ping i:first-child{position:absolute;inset:0;border-radius:50%;background:var(--em-green);animation:em-ping 1.8s ease-out infinite}
.em-ping i:last-child{position:absolute;inset:0;border-radius:50%;background:var(--em-green)}
.em-hero-title{margin:0 0 18px;font-size:48px;font-weight:700;line-height:1.02;letter-spacing:-.03em}
.em-word-mask{display:inline-block;overflow:hidden;padding-bottom:.12em;margin-bottom:-.12em;margin-right:.12em;vertical-align:top}
.em-word{display:inline-block;transform:translateY(110%);animation:em-word-up 1s var(--em-ease) forwards;animation-delay:var(--delay,0s)}
.em-hero-role{margin:0 0 18px;background:linear-gradient(100deg,#5cf0bd 20%,#d4fff0 48%,#5cf0bd 74%);background-size:220% 100%;background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:clamp(1.25rem,2vw,2rem);font-weight:600;animation:em-hero-in .9s var(--em-ease) forwards,em-shimmer 5s linear 1.8s infinite;animation-delay:var(--delay,0s),1.8s}
.em-hero-description{max-width:600px;margin:0;color:var(--em-muted);font-size:clamp(1rem,1.5vw,1.15rem);line-height:1.8}
.em-hero-cta{margin-top:28px}
.em-cta{display:inline-flex;align-items:center;justify-content:center;gap:12px;padding:15px 25px;border:0;border-radius:999px;background:var(--em-green);color:#04201a;font-size:14px;font-weight:700;box-shadow:0 10px 30px rgba(92,240,189,.25);transition:transform .3s,box-shadow .3s;cursor:pointer}
.em-cta:hover{transform:translateY(-3px);box-shadow:0 14px 40px rgba(92,240,189,.45)}
.em-cta span{font-size:18px}
.em-hero-in{opacity:0;transform:translateY(22px);animation:em-hero-in .9s var(--em-ease) forwards;animation-delay:var(--delay,0s)}
.em-hero-portrait{display:flex;justify-content:center}
.em-portrait-wrap{position:relative;width:min(380px,75vw);aspect-ratio:1}
.em-ring{position:absolute;border:1px dashed rgba(155,240,210,.35);border-radius:50%}
.em-ring-1{inset:-16px;animation:em-spin 40s linear infinite}
.em-ring-2{inset:-40px;border-color:rgba(155,240,210,.15);animation:em-spin 60s linear infinite reverse}
.em-blob{position:absolute;inset:16px;display:grid;overflow:hidden;place-items:center;border:1px solid rgba(255,255,255,.1);border-radius:58% 42% 60% 40% / 48% 55% 45% 52%;background:linear-gradient(135deg,#0e8f7c,#06352d);box-shadow:0 30px 80px rgba(0,0,0,.45);animation:em-morph 12s ease-in-out infinite}
.em-blob img{position:absolute;width:100%;height:100%;object-fit:cover}
.em-hero-fallback{display:grid;justify-items:center;gap:12px;color:#bdf3e0;font-size:12px;font-weight:700;letter-spacing:.15em}
.em-hero-fallback span:first-child{font-size:60px}
.em-dot{position:absolute;z-index:2;border-radius:50%;background:var(--em-green);box-shadow:0 0 24px rgba(92,240,189,.8);animation:em-float 5s ease-in-out infinite}
.em-dot-1{top:6%;right:8%;width:14px;height:14px}
.em-dot-2{bottom:14%;left:-2%;width:9px;height:9px;animation-delay:1s}
.em-dot-3{right:14%;bottom:2%;width:18px;height:18px;background:#2fbf9b;animation-delay:.5s}
.em-scroll-cue{position:absolute;bottom:32px;left:50%;display:flex;transform:translateX(-50%);color:rgba(255,255,255,.55)}
.em-scroll-cue>span{display:flex;width:24px;height:40px;justify-content:center;padding-top:8px;border:1px solid currentColor;border-radius:99px}
.em-scroll-cue i{width:4px;height:8px;border-radius:99px;background:currentColor;animation:em-wheel 1.8s ease-in-out infinite}
.em-marquee{position:relative;overflow:hidden;border-block:1px solid #0a7f70;background:#00695c;padding:20px 0}
.em-marquee-track{display:flex;width:max-content;align-items:center;gap:48px;white-space:nowrap;animation:em-marquee 32s linear infinite}
.em-marquee:hover .em-marquee-track{animation-play-state:paused}
.em-marquee-track>span{display:flex;align-items:center;gap:48px;font-size:19px;font-weight:600}
.em-marquee-track b{color:var(--em-mint);font-family:Arial,sans-serif;font-size:17px}
.em-section{position:relative;display:flex;min-height:100svh;flex-direction:column;justify-content:center;overflow:hidden;padding:112px 0;border-top:1px solid rgba(255,255,255,.05)}
.em-section>.em-hero-grid{inset:0}
.em-about,.em-skills,.em-contact{background:var(--em-bg)}
.em-journey,.em-projects{background:var(--em-section)}
.em-about-layout{position:relative;z-index:1;display:grid;grid-template-columns:5fr 7fr;align-items:center;gap:64px}
.em-about-image{position:relative;display:flex;justify-content:center}
.em-about-glow{position:absolute;inset:-16px;border-radius:40px;background:linear-gradient(135deg,#00a388,#9bf0d2);opacity:.28;filter:blur(2px);transform:rotate(3deg)}
.em-about-frame{position:relative;width:min(360px,100%);height:480px;overflow:hidden;border:1px solid rgba(255,255,255,.1);border-radius:32px;background:#0b3b33;box-shadow:0 30px 80px rgba(0,0,0,.45)}
.em-about-frame img{width:100%;height:100%;object-fit:cover;transition:transform .7s}
.em-about-frame:hover img{transform:scale(1.05)}
.em-about-copy{position:relative}
.em-about-copy-wide{grid-column:1/-1;max-width:760px;margin:auto;text-align:center}
.em-section-tag{display:inline-flex;align-items:center;gap:8px;padding:7px 16px;border:1px solid rgba(255,255,255,.15);border-radius:999px;background:rgba(255,255,255,.07);color:var(--em-mint);font-size:14px;font-weight:600}
.em-section-tag:before{width:6px;height:6px;border-radius:50%;background:currentColor;content:""}
.em-section-title{margin:18px 0 0;font-size:clamp(2.5rem,6vw,4.5rem);font-weight:700;line-height:1.05;letter-spacing:-.05em}
.em-about-description,.em-section-subtitle{max-width:700px;margin:24px 0 0;color:var(--em-muted);font-size:clamp(1rem,1.5vw,1.125rem);line-height:1.75;white-space:pre-line}
.em-about-copy-wide .em-about-description{margin-inline:auto}
.em-about-download{margin-top:28px}
.em-journey-content,.em-skills-content,.em-projects-content{position:relative;z-index:1;width:min(1152px,100% - 48px);margin-inline:auto}
.em-section-intro{max-width:720px;margin:0 auto 60px;text-align:center}
.em-section-subtitle{margin-inline:auto}
.em-timeline{position:relative;padding-left:56px}
.em-timeline-items{display:grid;gap:32px}
.em-spine{position:absolute;top:8px;bottom:8px;left:19px;width:2px;transform:scaleY(0);transform-origin:top;background:linear-gradient(#5cf0bd,#00a388,transparent);transition:transform 1.6s var(--em-ease)}
.em-spine.is-in{transform:scaleY(1)}
.em-timeline-item{position:relative}
.em-timeline-marker{position:absolute;top:28px;left:-56px;display:grid;width:40px;height:40px;place-items:center}
.em-timeline-marker i{width:16px;height:16px;border:3px solid var(--em-green);border-radius:50%;background:var(--em-bg);animation:em-pulse 2.6s ease-out infinite}
.em-journey-card,.em-skill-card{border:1px solid rgba(255,255,255,.1);border-radius:24px;background:rgba(255,255,255,.05);backdrop-filter:blur(12px);transition:transform .4s,border-color .4s,background .4s,box-shadow .4s}
.em-journey-card{padding:28px}
.em-journey-card:hover,.em-skill-card:hover{transform:translateY(-4px);border-color:rgba(92,240,189,.4);background:rgba(255,255,255,.09);box-shadow:0 24px 50px rgba(0,0,0,.3)}
.em-journey-meta{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px}
.em-period,.em-project-category{padding:5px 14px;border:1px solid rgba(255,255,255,.1);border-radius:999px;background:rgba(255,255,255,.07);color:var(--em-mint);font-size:14px;font-weight:600}
.em-company,.em-journey-description{color:var(--em-muted)}
.em-journey-title{margin:14px 0 0;font-size:24px;font-weight:600;letter-spacing:-.03em}
.em-journey-description{margin:10px 0 0;font-size:15px;line-height:1.7}
.em-skills-grid{display:grid;grid-template-columns:1fr;gap:24px}
.em-skill-card{display:flex;flex-direction:column;align-items:center;gap:24px;padding:32px}
.em-skill-card.is-in{box-shadow:0 10px 35px rgba(0,0,0,.12)}
.em-meter{position:relative;width:176px;height:176px;transition:transform .5s var(--em-ease)}
.em-skill-card:hover .em-meter{transform:scale(1.04)}
.em-meter svg{position:relative;width:100%;height:100%;overflow:visible}
.em-orbit{transform-origin:60px 60px;animation:em-spin 30s linear infinite}
.em-skill-card:hover .em-orbit{animation-duration:8s}
.em-arc{stroke-dashoffset:var(--circ);filter:drop-shadow(0 0 6px rgba(92,240,189,.55));transition:stroke-dashoffset 2s var(--em-ease);transition-delay:var(--skill-delay,0s)}
.em-skill-card.is-in .em-arc{stroke-dashoffset:var(--offset)}
.em-tip{transform-origin:60px 60px;filter:drop-shadow(0 0 6px rgba(92,240,189,.9));transition:transform 2s var(--em-ease);transition-delay:var(--skill-delay,0s)}
.em-skill-card.is-in .em-tip{transform:rotate(var(--degrees))}
.em-skill-count{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;letter-spacing:-.04em;font-variant-numeric:tabular-nums}
.em-skill-count>span{margin-left:2px;color:var(--em-green);font-size:20px}
.em-skill-label{font-size:19px;font-weight:600;text-align:center}
.em-halo{position:absolute;inset:14px;border:1px solid rgba(92,240,189,.35);border-radius:50%;opacity:0}
.em-skill-card.is-in .em-halo{animation:em-halo 3.2s ease-out infinite;animation-delay:calc(var(--skill-delay,0s) + 1.6s)}
.em-projects-content{width:min(1280px,100% - 48px)}
.em-projects .em-section-intro{margin-bottom:52px}
.em-filters{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin:-16px 0 36px}
.em-filter{padding:10px 20px;border:1px solid rgba(255,255,255,.15);border-radius:999px;background:rgba(255,255,255,.05);color:#bdf3e0;font-size:14px;font-weight:600;cursor:pointer;transition:all .3s}
.em-filter:hover{border-color:rgba(92,240,189,.6);color:white}
.em-filter.active{border-color:var(--em-green);background:var(--em-green);color:#04201a;box-shadow:0 10px 30px rgba(92,240,189,.25)}
.em-project-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:32px}
.em-project-card{display:flex;overflow:hidden;flex-direction:column;border:1px solid rgba(255,255,255,.1);border-radius:24px;background:rgba(255,255,255,.05);transition:transform .45s,border-color .45s,box-shadow .45s}
.em-project-card[hidden]{display:none}
.em-project-card:hover{transform:translateY(-8px);border-color:rgba(92,240,189,.4);box-shadow:0 30px 60px rgba(0,0,0,.4)}
.em-project-image{position:relative;display:flex;height:256px;overflow:hidden;align-items:center;justify-content:center;background:linear-gradient(135deg,#0b4a40,#06352d)}
.em-project-image>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .7s}
.em-project-card:hover .em-project-image>img{transform:scale(1.1)}
.em-project-fallback{font-size:50px;transition:transform .5s}
.em-project-card:hover .em-project-fallback{transform:scale(1.2) rotate(6deg)}
.em-project-overlay{position:absolute;inset:0;display:flex;align-items:flex-end;padding:20px;background:linear-gradient(0deg,rgba(4,32,26,.9),rgba(4,32,26,.3),transparent);opacity:0;transition:opacity .5s}
.em-project-card:hover .em-project-overlay{opacity:1}
.em-project-overlay a{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;background:var(--em-green);color:#04201a;font-size:14px;font-weight:700}
.em-project-copy{display:grid;justify-items:start;gap:12px;padding:24px}
.em-project-copy h3{margin:0;font-size:24px;font-weight:600;letter-spacing:-.03em}
.em-contact-container{position:relative;z-index:1;width:min(896px,100% - 48px)}
.em-contact-card{position:relative;display:flex;overflow:hidden;flex-direction:column;align-items:center;gap:32px;padding:80px 64px;border:1px solid rgba(255,255,255,.1);border-radius:40px;background:linear-gradient(135deg,#0b5a4d,#06352d);text-align:center;box-shadow:0 40px 100px rgba(0,0,0,.4)}
.em-contact-card>.em-orb,.em-contact-card>.em-hero-grid{z-index:0}
.em-contact-heading{position:relative;z-index:1}
.em-contact-description{max-width:600px;margin:20px auto 0;color:#bfe0d6;font-size:18px}
.em-contact-form{position:relative;z-index:1;width:100%;max-width:672px;display:grid;gap:18px;padding:28px;border:1px solid rgba(155,240,210,.2);border-radius:24px;background:rgba(4,26,22,.7);text-align:left;box-shadow:0 24px 60px rgba(0,0,0,.3)}
.em-contact-form h3{margin:0;font-size:24px}
.em-contact-form label{display:grid;gap:8px;color:#bfe0d6;font-size:14px;font-weight:600}
.em-contact-form input,.em-contact-form textarea{width:100%;border:1px solid rgba(155,240,210,.2);border-radius:12px;background:#06231e;padding:12px 16px;color:var(--em-text);outline:0}
.em-contact-form input:focus,.em-contact-form textarea:focus{border-color:var(--em-green);box-shadow:0 0 0 3px rgba(92,240,189,.2)}
.em-contact-form textarea{resize:vertical}
.em-contact-form button{justify-self:start}
.em-form-status{margin:0;color:var(--em-mint);font-size:14px;font-weight:600}
.em-email-link{position:relative;z-index:1;display:inline-flex;max-width:100%;align-items:center;gap:12px;overflow-wrap:anywhere;border-radius:999px;background:var(--em-green);padding:16px 28px;color:#04201a;font-weight:700;box-shadow:0 10px 30px rgba(92,240,189,.25);transition:transform .3s}
.em-email-link:hover{transform:translateY(-3px)}
.em-contact-meta{position:relative;z-index:1;display:flex;flex-wrap:wrap;justify-content:center;gap:12px 40px;color:#bfe0d6;font-size:14px;font-weight:500}
.em-hidden-frame{display:none}
.em-footer{border-top:1px solid rgba(255,255,255,.1);background:var(--em-deep);padding:38px 0}
.em-footer>.em-container{display:flex;align-items:center;justify-content:space-between;gap:16px}
.em-footer .display{font-size:18px;font-weight:600}
.em-footer p{margin:0;color:#86a79c;font-size:14px}
.em-reveal{opacity:0;transform:translateY(28px);transition:opacity .8s var(--em-ease),transform .8s var(--em-ease)}
.em-reveal-left{transform:translateX(-36px)}
.em-reveal.is-in{opacity:1;transform:none}
.em-timeline-item{transition-delay:var(--reveal-delay,0ms)}
.folio :where(a,button,input,textarea):focus-visible{outline:3px solid var(--em-green);outline-offset:3px}
@keyframes em-drift{33%{transform:translate(60px,40px) scale(1.1)}66%{transform:translate(-40px,60px) scale(.95)}}
@keyframes em-hero-in{to{opacity:1;transform:none}}
@keyframes em-word-up{to{transform:translateY(0)}}
@keyframes em-shimmer{to{background-position:-220% 0}}
@keyframes em-morph{25%{border-radius:42% 58% 45% 55% / 58% 42% 58% 42%}50%{border-radius:55% 45% 38% 62% / 42% 60% 40% 58%}75%{border-radius:40% 60% 58% 42% / 55% 45% 55% 45%}}
@keyframes em-spin{to{transform:rotate(360deg)}}
@keyframes em-float{50%{transform:translateY(-16px)}}
@keyframes em-wheel{80%{transform:translateY(12px);opacity:0}100%{opacity:0}}
@keyframes em-marquee{to{transform:translateX(-50%)}}
@keyframes em-ping{75%,100%{transform:scale(2.6);opacity:0}}
@keyframes em-pulse{70%,100%{box-shadow:0 0 0 12px rgba(92,240,189,0)}}
@keyframes em-halo{0%{transform:scale(.85);opacity:.7}100%{transform:scale(1.3);opacity:0}}
@media(min-width:640px){.em-hero-title{font-size:72px}.em-skills-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:768px){.em-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.em-hero-layout{grid-template-columns:repeat(12,minmax(0,1fr));gap:56px}.em-hero-copy{grid-column:span 7}.em-hero-portrait{grid-column:span 5}.em-hero-title{font-size:88px}.em-about-layout{grid-template-columns:repeat(12,minmax(0,1fr));gap:64px}.em-about-image{grid-column:span 5}.em-about-copy{grid-column:span 7}.em-about-copy.em-about-copy-wide{grid-column:3/span 8}.em-skills-grid,.em-project-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:1023px){.em-hero-layout{grid-template-columns:1fr;gap:54px}.em-hero-copy{order:2;text-align:center;margin:auto}.em-hero-portrait{order:1}.em-greeting{margin-inline:auto}.em-hero-description{margin-inline:auto}.em-hero-cta{display:flex;justify-content:center}.em-about-layout{grid-template-columns:1fr;gap:48px}.em-about-image{order:1}.em-about-copy{order:2}.em-about-copy-wide{grid-column:auto}.em-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767px){.em-container,.em-journey-content,.em-skills-content,.em-projects-content,.em-contact-container{width:calc(100% - 40px)}.em-nav-inner{width:calc(100% - 40px)}.em-desktop-nav{display:none}.em-menu-toggle{display:flex}.em-hero{min-height:auto;padding:124px 0 96px}.em-portrait-wrap{width:min(380px,75vw)}.em-ring-2{inset:-28px}.em-scroll-cue{display:none}.em-section{min-height:auto;padding:88px 0}.em-about-frame{height:420px}.em-about-copy{text-align:center}.em-about-description{margin-inline:auto}.em-section-intro{margin-bottom:44px}.em-timeline{padding-left:42px}.em-spine{left:10px}.em-timeline-marker{left:-42px;width:22px}.em-journey-card{padding:22px}.em-company{font-size:14px}.em-skills-grid,.em-project-grid{grid-template-columns:1fr;gap:20px}.em-skill-card{padding:28px}.em-contact-card{gap:28px;padding:56px 22px;border-radius:30px}.em-contact-form{padding:20px}.em-contact-meta{gap:10px 20px}.em-footer>.em-container{flex-direction:column;text-align:center}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.folio *,.folio *:before,.folio *:after{animation:none!important;scroll-behavior:auto!important;transition-duration:.01ms!important;transition-delay:0ms!important}.em-reveal{opacity:1;transform:none}.em-arc{stroke-dashoffset:var(--offset)}.em-tip{transform:rotate(var(--degrees))}}
CSS;
    }
}
