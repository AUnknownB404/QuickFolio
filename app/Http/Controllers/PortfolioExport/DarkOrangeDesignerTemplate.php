<?php

namespace App\Http\Controllers\PortfolioExport;

trait DarkOrangeDesignerTemplate
{
    private function renderDarkOrangeDesignerPage(string $title, array $content, array $assets, ?array $googleForm): string
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
        $links = [['id' => 'about', 'label' => 'About']];
        if ($journeyItems !== []) {
            $links[] = ['id' => 'journey', 'label' => 'Journey'];
        }
        if ($skillItems !== []) {
            $links[] = ['id' => 'skills', 'label' => 'Skills'];
        }
        if ($projects !== []) {
            $links[] = ['id' => 'projects', 'label' => 'Work'];
        }
        $links[] = ['id' => 'contact', 'label' => 'Contact'];

        $navigation = '';
        foreach ($links as $link) {
            $navigation .= '<li><a href="#'.$escape($link['id']).'" class="nav-link" data-section-link="'.$escape($link['id']).'">'.$escape($link['label']).'</a></li>';
        }

        $brand = is_string($navbar['logo_text'] ?? null) ? $navbar['logo_text'] : '';
        $showLogo = ! in_array($navbar['show_logo'] ?? true, [false, 0, '0', 'false'], true);
        $logoImage = $url($navbar['logo_image'] ?? null, 'Logo.svg');
        $heroName = is_string($hero['hero_name'] ?? null) ? trim($hero['hero_name']) : '';
        $heroWords = $heroName !== '' ? preg_split('/\s+/', $heroName, -1, PREG_SPLIT_NO_EMPTY) : [];
        $heroImage = $assetUrl($hero['hero_image'] ?? null);
        $html = '<div class="theme min-h-screen overflow-x-hidden font-body">
    <div class="progress-track"><div class="progress-bar"></div></div>
    <nav class="nav" aria-label="Main navigation">
        <div class="nav-inner"><a href="#home" class="brand" aria-label="Back to top">';
        if ($showLogo) {
            $html .= '<img src="'.$logoImage.'" alt="'.$escape($brand).'" width="36" height="36" class="brand-mark">';
        }
        $html .= '</a><ul class="nav-links">'.$navigation.'</ul>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-navigation" aria-label="Toggle menu"><span class="burger"><i></i><i></i></span></button>
        </div>
        <ul class="mobile-navigation" id="mobile-navigation" hidden>'.$navigation.'</ul>
    </nav>
    <header id="home" data-section class="hero">
        <div class="aurora" aria-hidden="true"></div>
        <div class="hero-inner"><div class="hero-copy">';
        if (! empty($hero['hero_greeting'])) {
            $html .= '<p class="hero-line greeting" style="--i:0"><span class="pulse-dot"></span>'.$escape($hero['hero_greeting']).'</p>';
        }
        if ($heroWords !== []) {
            $html .= '<h1 class="font-display hero-title">';
            foreach ($heroWords as $index => $word) {
                $html .= '<span class="mask"><span class="mask-inner" style="--i:'.$index.'">'.$escape($word).'&nbsp;</span></span>';
            }
            $html .= '</h1>';
        }
        if (! empty($hero['hero_role'])) {
            $html .= '<p class="hero-line hero-role font-display" style="--i:'.(count($heroWords) + 2).'">'.$escape($hero['hero_role']).'</p>';
        }
        if (! empty($hero['hero_description'])) {
            $html .= '<p class="hero-line hero-description" style="--i:'.(count($heroWords) + 3).'">'.$escape($hero['hero_description']).'</p>';
        }
        if (! empty($hero['hero_cta_primary_text'])) {
            $html .= '<div class="hero-line hero-cta" style="--i:'.(count($heroWords) + 4).'"><a href="'.$url($hero['hero_cta_primary_url'] ?? null, '#projects').'" class="btn-primary">'.$escape($hero['hero_cta_primary_text']).'<span class="btn-arrow" aria-hidden="true">→</span></a></div>';
        }
        $heroFallback = $heroName !== '' ? mb_substr($heroName, 0, 1) : ($brand !== '' ? mb_substr($brand, 0, 1) : '•');
        $html .= '</div><div class="hero-portrait hero-line" style="--i:3"><div class="portrait-wrap"><div class="portrait">';
        if ($heroImage !== '') {
            $html .= '<img src="'.$url($hero['hero_image'], '').'" alt="'.$escape($heroName).'" class="portrait-image">';
        } else {
            $html .= '<span class="portrait-fallback font-display">'.$escape($heroFallback).'</span>';
        }
        $html .= '</div></div></div></div></header>';

        $aboutImage = $assetUrl($about['about_image'] ?? null);
        $html .= '<section id="about" data-section class="section"><div class="content-width about-grid">';
        if ($aboutImage !== '') {
            $html .= '<div class="about-media reveal"><div class="img-frame"><img src="'.$url($about['about_image'], '').'" alt="'.$escape($about['about_title'] ?? '').'" loading="lazy"></div></div>';
        }
        $html .= '<div class="about-copy'.($aboutImage === '' ? ' about-copy-wide' : '').'">';
        if (! empty($about['about_title'])) {
            $html .= '<h2 class="h2 reveal">'.$escape($about['about_title']).'</h2>';
        }
        if (! empty($about['about_description'])) {
            $html .= '<p class="about-description reveal" style="--d:100ms">'.$escape($about['about_description']).'</p>';
        }
        if (! empty($about['about_cv_file'])) {
            $html .= '<div class="reveal" style="--d:200ms"><a href="'.$url($about['about_cv_file']).'" class="btn-ghost">↓ <span>Download my CV</span></a></div>';
        }
        $html .= '</div></div></section>';

        if ($journeyItems !== []) {
            $html .= '<section id="journey" data-section class="section"><div class="content-width journey-content"><div class="section-intro">';
            if (! empty($journey['journey_title'])) {
                $html .= '<h2 class="h2 reveal">'.$escape($journey['journey_title']).'</h2>';
            }
            if (! empty($journey['journey_subtitle'])) {
                $html .= '<p class="section-description reveal" style="--d:100ms">'.$escape($journey['journey_subtitle']).'</p>';
            }
            $html .= '</div><ul class="journey-list">';
            foreach ($journeyItems as $index => $item) {
                $html .= '<li class="jr reveal'.($index === 0 ? ' open' : '').'" data-journey-item><button class="jr-head" type="button" aria-expanded="'.($index === 0 ? 'true' : 'false').'"><span class="jr-period">'.$escape($item['period'] ?? '').'</span><span class="jr-main"><span class="jr-title font-display">'.$escape($item['title'] ?? '').'</span>';
                if (! empty($item['company'])) {
                    $html .= '<span class="jr-company">'.$escape($item['company']).'</span>';
                }
                $html .= '</span><span class="jr-icon" aria-hidden="true"></span></button><div class="jr-body"><div>';
                if (! empty($item['description'])) {
                    $html .= '<p class="jr-desc">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></div></li>';
            }
            $html .= '</ul></div></section>';
        }

        if ($skillItems !== []) {
            $html .= '<section id="skills" data-section class="section"><div class="content-width"><div class="section-intro">';
            if (! empty($skills['skills_title'])) {
                $html .= '<h2 class="h2 reveal">'.$escape($skills['skills_title']).'</h2>';
            }
            if (! empty($skills['skills_description'])) {
                $html .= '<p class="section-description reveal" style="--d:100ms">'.$escape($skills['skills_description']).'</p>';
            }
            $html .= '</div><ul class="skills-grid">';
            foreach ($skillItems as $index => $skill) {
                $label = $skill['label'] ?? $skill['name'] ?? $skill['title'] ?? '';
                if (! is_scalar($label) || trim((string) $label) === '') {
                    continue;
                }
                $skillPercent = $skill['percent'] ?? null;
                $percent = is_numeric($skillPercent) && (float) $skillPercent !== 0.0 ? max(0, min(100, (float) $skillPercent)) : 100;
                $html .= '<li class="skill reveal" style="--d:'.(($index % 6) * 60).'ms"><div class="skill-head"><span class="font-display">'.$escape($label).'</span><span class="skill-percent">'.$escape((string) ($percent === 100 ? 100 : round($percent))).'%</span></div><div class="skill-track"><span class="skill-bar" style="--w:'.$percent.'%"></span></div></li>';
            }
            $html .= '</ul></div></section>';
        }

        if ($projects !== []) {
            $categories = array_values(array_unique(array_filter(array_map(
                static fn (array $project): string => is_string($project['category'] ?? null) ? $project['category'] : '',
                $projects
            ))));
            $html .= '<section id="projects" data-section class="section"><div class="content-width"><div class="work-heading"><div>';
            if (! empty($projectContent['projects_title'])) {
                $html .= '<h2 class="h2 reveal">'.$escape($projectContent['projects_title']).'</h2>';
            }
            if (! empty($projectContent['projects_subtitle'])) {
                $html .= '<p class="section-description reveal" style="--d:100ms">'.$escape($projectContent['projects_subtitle']).'</p>';
            }
            $html .= '</div>';
            if (count($categories) > 1) {
                $html .= '<div class="filters reveal" role="tablist" aria-label="Filter projects"><button type="button" class="chip active" data-project-filter="all" aria-selected="true">All</button>';
                foreach ($categories as $category) {
                    $html .= '<button type="button" class="chip" data-project-filter="'.$escape($category).'" aria-selected="false">'.$escape($category).'</button>';
                }
                $html .= '</div>';
            }
            $html .= '</div><div class="work-list" data-work-list>';
            foreach ($projects as $projectIndex => $project) {
                $category = is_string($project['category'] ?? null) ? $project['category'] : '';
                $projectUrl = $project['url'] ?? null;
                $image = $assetUrl($project['image'] ?? null);
                $html .= '<article class="work-row" data-project-index="'.$projectIndex.'" data-project-category="'.$escape($category).'"><'.(is_string($projectUrl) && $projectUrl !== '' ? 'a href="'.$url($projectUrl).'" target="_blank" rel="noopener noreferrer"' : 'div').' class="work-link">';
                if ($image !== '') {
                    $html .= '<img src="'.$url($project['image'], '').'" alt="'.$escape($project['title'] ?? '').'" class="work-thumb" loading="lazy">';
                }
                $html .= '<span class="work-title font-display">'.$escape($project['title'] ?? '').'</span><span class="work-meta">';
                if ($category !== '') {
                    $html .= '<span>'.$escape($category).'</span>';
                }
                if (is_string($projectUrl) && $projectUrl !== '') {
                    $html .= '<span class="work-arrow" aria-hidden="true">↗</span>';
                }
                $html .= '</span></'.(is_string($projectUrl) && $projectUrl !== '' ? 'a' : 'div').'></article>';
            }
            $html .= '</div><div class="work-preview" aria-hidden="true">';
            foreach ($projects as $projectIndex => $project) {
                $image = $assetUrl($project['image'] ?? null);
                if ($image !== '') {
                    $html .= '<img src="'.$url($project['image'], '').'" alt="" data-preview-index="'.$projectIndex.'">';
                }
            }
            $html .= '</div></div></section>';
        }

        $formEnabled = ($contact['contact_form_enabled'] ?? true) !== false;
        $formReady = $formEnabled && $googleForm !== null;
        $email = is_string($contact['contact_email'] ?? null) ? $contact['contact_email'] : '';
        $html .= '<section id="contact" data-section class="section"><div class="content-width contact-content">';
        if (! empty($contact['contact_title'])) {
            $html .= '<h2 class="h2 reveal">'.$escape($contact['contact_title']).'</h2>';
        }
        if (! empty($contact['contact_description'])) {
            $html .= '<p class="section-description reveal" style="--d:100ms">'.$escape($contact['contact_description']).'</p>';
        }
        if ($email !== '' || ! empty($contact['contact_phone']) || ! empty($contact['contact_location'])) {
            $html .= '<div class="contact-details reveal" style="--d:150ms">';
            if ($email !== '') {
                $html .= '<a href="'.$url('mailto:'.$email).'" class="contact-link">'.$escape($email).'</a>';
            }
            if (! empty($contact['contact_phone'])) {
                $phone = (string) $contact['contact_phone'];
                $html .= '<span>'.$escape($phone).'</span>';
            }
            if (! empty($contact['contact_location'])) {
                $html .= '<span>'.$escape($contact['contact_location']).'</span>';
            }
            $html .= '</div>';
        }
        if ($formReady) {
            $html .= '<form action="'.$escape($googleForm['action']).'" method="POST" target="do-gform-frame" class="contact-form reveal" data-google-form style="--d:200ms">
                    <h3 class="font-display">Send a message</h3>
                    <label>Name<input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name"></label>
                    <label>Email<input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email"></label>
                    <label>Message<textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required></textarea></label>
                    <button type="submit" class="btn-primary">Send message <span class="btn-arrow" aria-hidden="true">→</span></button>
                    <p class="form-status" role="status" data-form-status hidden></p>
                </form><iframe name="do-gform-frame" title="Form submission target" class="hidden-frame" tabindex="-1" aria-hidden="true"></iframe>';
        }
        $html .= '</div></section>';
        $html .= '<footer class="footer"><div class="content-width footer-inner">';
        if ($brand !== '') {
            $html .= '<img src="Logo.svg" alt="'.$escape($brand).'" width="28" height="28" class="footer-brand">';
        }
        $html .= '<span><img src="Logo.svg" alt="" width="20" height="20"> Made with quickFolio</span><a href="#home">Back to top ↑</a></div></footer></div>';

        return '<!doctype html>
<html lang="en" data-template="dark-orange-designer">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f0e0d">
    <title>'.$escape($title).'</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    private function darkOrangeDesignerStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Instrument+Sans:wght@400;500;600&display=swap');
html{scroll-behavior:smooth;scroll-padding-top:80px}
:root{--ink:#0f0e0d;--surface:#181614;--paper:#f3efe9;--muted:#a39d94;--ember:#ff5a1f;--line:rgba(243,239,233,.1);--ease:cubic-bezier(.22,1,.36,1)}
*{box-sizing:border-box}
body{margin:0;background:var(--ink);color:var(--paper);font:16px/1.55 "Instrument Sans",ui-sans-serif,system-ui,sans-serif}
a{color:inherit;text-decoration:none}
img{display:block;max-width:100%}
.theme{min-height:100vh;overflow-x:hidden;background:var(--ink);color:var(--paper)}
.font-display{font-family:"Bricolage Grotesque",ui-sans-serif,system-ui,sans-serif}
.content-width{width:min(1280px,100%);margin-inline:auto}
.progress-track{position:fixed;inset:0 0 auto;z-index:60;height:2px}
.progress-bar{width:100%;height:100%;transform:scaleX(0);transform-origin:left;background:var(--ember)}
.nav{position:fixed;inset:0 0 auto;z-index:50;transform:translateY(-100%);border-bottom:1px solid var(--line);background:rgba(15,14,13,.72);backdrop-filter:blur(16px) saturate(1.4);transition:transform .8s var(--ease) .1s}
.nav.nav-in{transform:none}
.nav-inner{display:flex;height:72px;max-width:1280px;align-items:center;justify-content:space-between;gap:24px;margin:auto;padding:0 24px}
.brand{display:flex;align-items:center;gap:12px;border-radius:8px;font-size:18px;font-weight:600}
.brand-mark{display:grid;width:36px;height:36px;place-items:center;border-radius:999px;background:var(--ember);color:var(--ink);font-family:"Bricolage Grotesque",sans-serif;font-size:18px;font-weight:700}
.nav-links{display:none;align-items:center;gap:4px;margin:0;padding:0;list-style:none}
.nav-link{display:block;border-radius:999px;padding:8px 14px;color:var(--muted);transition:color .25s,background .25s}
.nav-link:hover{background:#ffffff0d;color:var(--paper)}
.nav-link.active{background:#ffffff14;color:var(--paper)}
.menu-toggle{display:grid;width:40px;height:40px;place-items:center;border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--paper);cursor:pointer}
.burger{position:relative;display:block;width:16px;height:10px}
.burger i{position:absolute;left:0;width:100%;height:2px;border-radius:2px;background:currentColor;transition:transform .35s var(--ease),top .35s var(--ease)}
.burger i:first-child{top:0}
.burger i:last-child{top:8px}
.burger.open i:first-child{top:4px;transform:rotate(45deg)}
.burger.open i:last-child{top:4px;transform:rotate(-45deg)}
.mobile-navigation{position:absolute;top:100%;right:16px;left:16px;margin:0 0 16px;padding:8px;border:1px solid var(--line);border-radius:16px;background:var(--surface);list-style:none}
.mobile-navigation[hidden]{display:none}
.mobile-navigation a{display:block;border-radius:12px;padding:12px 16px;font-size:18px}
.mobile-navigation a:hover{background:#ffffff0d}
.hero{position:relative;display:flex;min-height:100svh;align-items:center;padding:112px 24px 64px}
.aurora{position:absolute;inset:0;overflow:hidden;pointer-events:none}
.aurora::before{position:absolute;top:5%;right:-10%;width:60vw;height:60vw;max-width:760px;max-height:760px;filter:blur(40px);background:radial-gradient(closest-side,rgba(255,90,31,.22),transparent);content:"";animation:drift 16s ease-in-out infinite alternate}
.hero-inner{position:relative;z-index:1;display:grid;width:min(1280px,100%);align-items:center;gap:56px;margin:auto}
.hero-copy{min-width:0}
.greeting{display:inline-flex;align-items:center;gap:8px;margin:0 0 24px;color:var(--muted);font-size:16px}
.pulse-dot{width:8px;height:8px;border-radius:50%;background:var(--ember);box-shadow:0 0 0 0 rgba(255,90,31,.6);animation:ping 2.2s infinite}
.hero-title{margin:0;font-size:clamp(3.25rem,10vw,8rem);font-weight:600;line-height:.92;letter-spacing:-.04em}
.mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.08em}
.mask-inner{display:inline-block;transform:translateY(110%);transition:transform 1s var(--ease);transition-delay:calc(var(--i)*90ms + 250ms)}
.is-ready .mask-inner{transform:none}
.hero-role{margin:24px 0 0;color:var(--ember);font-size:clamp(1.5rem,3vw,1.875rem);font-weight:500;letter-spacing:-.025em}
.hero-description{max-width:544px;margin:24px 0 0;color:var(--muted);font-size:18px;line-height:1.65}
.hero-cta{margin-top:40px}
.hero-line{opacity:0;transform:translateY(18px);transition:opacity .9s var(--ease),transform .9s var(--ease);transition-delay:calc(var(--i)*90ms + 250ms)}
.is-ready .hero-line{opacity:1;transform:none}
.btn-primary{display:inline-flex;align-items:center;gap:14px;border:0;border-radius:999px;background:var(--ember);padding:9px 9px 9px 26px;color:var(--ink);font-weight:600;cursor:pointer;transition:transform .35s var(--ease),box-shadow .35s}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 14px 34px -10px rgba(255,90,31,.6)}
.btn-arrow{display:grid;width:40px;height:40px;flex:none;place-items:center;border-radius:50%;background:var(--ink);color:var(--paper);transition:transform .35s var(--ease)}
.btn-primary:hover .btn-arrow{transform:translateX(3px) rotate(-45deg)}
.btn-ghost{display:inline-flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:999px;padding:14px 24px;font-weight:500;transition:border-color .3s,background .3s}
.btn-ghost:hover{border-color:var(--ember);background:rgba(255,90,31,.08)}
.hero-portrait{display:flex;justify-content:center}
.portrait-wrap{width:min(100%,380px);perspective:900px}
.portrait{position:relative;overflow:hidden;aspect-ratio:4/5;border:1px solid var(--line);border-radius:999px 999px 28px 28px;background:var(--surface);box-shadow:0 40px 80px -30px rgba(255,90,31,.3);transition:transform .25s ease-out}
.portrait-image{width:100%;height:100%;object-fit:cover}
.portrait-fallback{display:grid;width:100%;height:100%;place-items:center;color:var(--ember);font-size:8rem;font-weight:600}
.section{position:relative;border-top:1px solid var(--line);padding:clamp(5rem,12vw,9rem) 24px}
.h2{margin:0;font-family:"Bricolage Grotesque",sans-serif;font-size:clamp(2.5rem,6vw,4.5rem);font-weight:600;line-height:1;letter-spacing:-.03em}
.about-grid{display:grid;align-items:center;gap:48px}
.about-media{min-width:0}
.img-frame{position:relative;width:100%;max-width:448px;overflow:hidden;border:1px solid var(--line);border-radius:24px;background:var(--surface)}
.img-frame img{width:100%;aspect-ratio:4/5;object-fit:cover}
.about-copy{min-width:0}
.about-copy-wide{max-width:768px;margin-inline:auto}
.about-description{max-width:608px;margin:32px 0 0;white-space:pre-line;color:var(--muted);font-size:18px;line-height:1.75}
.about-copy .btn-ghost{margin-top:40px}
.section-intro{max-width:672px}
.section-description{margin:20px 0 0;color:var(--muted);font-size:18px;line-height:1.6}
.journey-list{margin:64px 0 0;padding:0;border-top:1px solid var(--line);list-style:none}
.jr{border-bottom:1px solid var(--line)}
.jr-head{display:grid;width:100%;align-items:center;grid-template-columns:1fr auto;gap:4px 24px;padding:28px 0;border:0;background:transparent;color:var(--paper);text-align:left;cursor:pointer;transition:padding .4s var(--ease)}
.jr-period{grid-column:1/-1;color:var(--muted);font-size:15px;transition:color .3s}
.jr-main{display:flex;flex-direction:column;gap:4px}
.jr-title{font-size:clamp(1.5rem,3.4vw,2.5rem);font-weight:600;line-height:1.1;letter-spacing:-.025em;transition:transform .5s var(--ease),color .3s}
.jr-company{color:var(--muted)}
.jr:hover .jr-title{transform:translateX(10px)}
.jr:hover .jr-period,.jr.open .jr-period{color:var(--ember)}
.jr-icon{position:relative;width:44px;height:44px;border:1px solid var(--line);border-radius:50%;transition:background .3s,border-color .3s,transform .5s var(--ease)}
.jr-icon::before,.jr-icon::after{position:absolute;top:50%;left:50%;width:14px;height:2px;margin:-1px 0 0 -7px;background:var(--paper);content:"";transition:transform .4s var(--ease),background .3s}
.jr-icon::after{transform:rotate(90deg)}
.jr.open .jr-icon{transform:rotate(180deg);border-color:var(--ember);background:var(--ember)}
.jr.open .jr-icon::before,.jr.open .jr-icon::after{background:var(--ink)}
.jr.open .jr-icon::after{transform:none}
.jr-body{display:grid;grid-template-rows:0fr;transition:grid-template-rows .55s var(--ease)}
.jr-body>div{overflow:hidden}
.jr.open .jr-body{grid-template-rows:1fr}
.jr-desc{max-width:640px;margin:0;padding:0 0 36px;color:var(--muted);font-size:17px;line-height:1.75;opacity:0;transform:translateY(8px);transition:opacity .5s .1s,transform .5s var(--ease) .1s}
.jr.open .jr-desc{opacity:1;transform:none}
.skills-grid{display:grid;gap:32px 64px;margin:56px 0 0;padding:0;list-style:none}
.skill-head{display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin-bottom:12px}
.skill-head>span:first-child{font-size:18px;font-weight:500}
.skill-percent{color:var(--muted);font-size:14px;font-variant-numeric:tabular-nums}
.skill-track{height:6px;overflow:hidden;border-radius:999px;background:rgba(255,255,255,.07)}
.skill-bar{display:block;width:var(--w);height:100%;transform:scaleX(0);transform-origin:left;border-radius:inherit;background:linear-gradient(90deg,#ff8a3d,var(--ember));transition:transform 1.3s var(--ease) .2s}
.skill.is-in .skill-bar{transform:none}
.work-heading{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:32px}
.filters{display:flex;flex-wrap:wrap;gap:8px}
.chip{border:1px solid var(--line);border-radius:999px;background:transparent;padding:8px 18px;color:var(--muted);font:inherit;font-size:15px;cursor:pointer;transition:all .3s var(--ease)}
.chip:hover{border-color:#f3efe94d;color:var(--paper)}
.chip.active{border-color:var(--paper);background:var(--paper);color:var(--ink)}
.work-list{position:relative;margin-top:56px;border-top:1px solid var(--line)}
.work-row{border-bottom:1px solid var(--line)}
.work-row[hidden]{display:none}
.work-link{display:flex;flex-direction:column;gap:16px;border-radius:8px;padding:24px 0}
.work-thumb{width:100%;aspect-ratio:16/10;border:1px solid var(--line);border-radius:20px;object-fit:cover}
.work-title{color:var(--paper);font-size:clamp(1.6rem,5.5vw,4rem);font-weight:600;line-height:1;letter-spacing:-.03em;transition:color .35s,transform .6s var(--ease)}
.work-meta{display:inline-flex;align-items:center;gap:20px;color:var(--muted)}
.work-arrow{color:var(--ember);font-size:24px;transition:transform .5s var(--ease)}
.work-preview{position:fixed;top:0;left:0;z-index:40;display:none;width:340px;transform:translate3d(-1000px,-1000px,0);pointer-events:none;opacity:0;transition:opacity .35s,transform .28s var(--ease)}
.work-preview.show{opacity:1}
.work-preview img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .35s}
.work-preview img.on{opacity:1}
.work-preview::before{display:block;aspect-ratio:4/3;border:1px solid var(--line);border-radius:20px;background:var(--surface);box-shadow:0 30px 60px -20px #000b;content:""}
.contact-content{max-width:896px}
.contact-details{display:flex;flex-wrap:wrap;gap:12px 40px;margin-top:40px;color:var(--muted)}
.contact-link{color:var(--paper);background:linear-gradient(var(--ember),var(--ember)) no-repeat 0 100%/100% 1px;transition:background-size .35s var(--ease),color .25s}
.contact-link:hover{color:var(--ember);background-size:100% 100%}
.contact-form{margin-top:48px;border:1px solid var(--line);border-radius:24px;background:var(--surface);padding:24px;text-align:left}
.contact-form h3{margin:0 0 24px;color:var(--paper);font-size:24px}
.contact-form label{display:block;margin-top:20px;color:var(--muted);font-size:14px;font-weight:500}
.contact-form input,.contact-form textarea{display:block;width:100%;margin-top:8px;border:1px solid var(--line);border-radius:12px;background:var(--ink);padding:12px 16px;color:var(--paper);font:inherit}
.contact-form input:focus,.contact-form textarea:focus{outline:2px solid rgba(255,90,31,.25);border-color:var(--ember)}
.contact-form button{margin-top:24px}
.form-status{color:var(--ember);font-size:14px}
.hidden-frame{display:none}
.footer{border-top:1px solid var(--line);padding:48px 24px}
.footer-inner{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;color:var(--muted);font-size:14px}
.footer-brand{color:var(--paper);font-size:16px;font-weight:600}
.footer a:hover{color:var(--paper)}
.reveal{opacity:0;transform:translateY(24px);transition:opacity .8s var(--ease),transform .8s var(--ease);transition-delay:var(--d,0ms)}
.reveal.is-in{opacity:1;transform:none}
.nav-link:focus-visible,.menu-toggle:focus-visible,.jr-head:focus-visible,.a:focus-visible{outline:2px solid var(--ember);outline-offset:3px}
@keyframes drift{to{transform:translate(-6%,8%) scale(1.12)}}
@keyframes ping{70%{box-shadow:0 0 0 10px rgba(255,90,31,0)}100%{box-shadow:0 0 0 0 rgba(255,90,31,0)}}
@media(min-width:768px){.nav-links{display:flex}.menu-toggle{display:none}.mobile-navigation{display:none}.jr-head{grid-template-columns:220px 1fr auto;gap:32px;padding:36px 0}.jr-period{grid-column:auto}.jr-desc{margin-left:calc(220px + 2rem)}.skills-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.hero-inner{grid-template-columns:7fr 5fr;gap:56px}.hero-portrait{justify-content:flex-end}.about-grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:64px}.about-media{grid-column:span 5}.about-copy{grid-column:span 7}.about-copy-wide{grid-column:3/span 8}.work-link{flex-direction:row;align-items:center;justify-content:space-between;gap:32px;padding:40px 0;transition:padding .5s var(--ease)}.work-row:hover .work-link{padding-inline:20px}.work-row:hover .work-title{color:var(--ember)}.work-row:hover .work-arrow{transform:translate(4px,-4px)}.work-row:hover .work-meta{color:var(--paper)}.work-thumb{display:none}.work-preview{display:block}.work-preview.show{opacity:1}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.theme *, .theme *::before,.theme *::after{animation:none!important;transition-duration:.01ms!important;transition-delay:0ms!important}.reveal,.hero-line,.mask-inner,.nav{opacity:1!important;transform:none!important}.skill-bar{transform:none!important}}
CSS;
    }
}
