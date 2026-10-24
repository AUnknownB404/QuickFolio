<?php

namespace App\Http\Controllers\PortfolioExport;

trait BentoGridTemplate
{
    private function renderBentoGridPage(string $title, array $content, array $assets, ?array $googleForm): string
    {
        $escape = fn (mixed $value): string => e(is_scalar($value) ? (string) $value : '');
        $url = fn (mixed $value, string $fallback = '#'): string => $escape($this->safeUrl($this->assetUrl($value, $assets), $fallback));
        $navbar = $this->sectionData($content['navbar'] ?? []);
        $hero = $this->sectionData($content['header'] ?? []);
        $about = $this->sectionData($content['about'] ?? []);
        $journey = $this->sectionData($content['journey'] ?? []);
        $skills = $this->sectionData($content['skills'] ?? []);
        $projectContent = $this->sectionData($content['projects'] ?? []);
        $contact = $this->sectionData($content['contact'] ?? []);
        $projects = $this->items($projectContent['projects_list'] ?? $projectContent['projects'] ?? []);
        $skillItems = $this->items($skills['skills_list'] ?? []);
        $journeyItems = $this->items($journey['journey_list'] ?? []);

        $hasAbout = ! empty($about['about_title'])
            || ! empty($about['about_description'])
            || ! empty($about['about_image'])
            || ! empty($about['about_cv_file']);
        $formEnabled = ! in_array($contact['contact_form_enabled'] ?? true, [false, 0, '0', 'false'], true);
        $hasContact = ($formEnabled && $googleForm !== null)
            || ! empty($contact['contact_title'])
            || ! empty($contact['contact_description'])
            || ! empty($contact['contact_email'])
            || ! empty($contact['contact_phone'])
            || ! empty($contact['contact_location']);
        $socialLinks = $this->items($hero['hero_social_links'] ?? $contact['contact_social_links'] ?? []);

        $links = [['id' => 'home', 'label' => 'Intro']];
        if ($hasAbout) {
            $links[] = ['id' => 'about', 'label' => 'About'];
        }
        if ($projects !== []) {
            $links[] = ['id' => 'projects', 'label' => 'Work'];
        }
        if ($skillItems !== []) {
            $links[] = ['id' => 'skills', 'label' => 'Skills'];
        }
        if ($journeyItems !== []) {
            $links[] = ['id' => 'journey', 'label' => 'Journey'];
        }
        if ($hasContact) {
            $links[] = ['id' => 'contact', 'label' => 'Contact'];
        }

        $mobileLinks = '';
        $desktopLinks = '';
        foreach ($links as $index => $link) {
            $mobileLinks .= '<li class="shrink-0"><a href="#'.$escape($link['id']).'" class="block rounded-full px-3 py-1.5 text-sm font-medium'.($link['id'] === 'home' ? ' mobile-active' : '').'" data-nav-link="'.$escape($link['id']).'">'.$escape($link['label']).'</a></li>';
            $desktopLinks .= '<li class="rail-in" style="--i: '.($index + 1).'"><a href="#'.$escape($link['id']).'" class="rail-link" data-nav-link="'.$escape($link['id']).'"><span class="rail-bar"></span>'.$escape($link['label']).'</a></li>';
        }

        $logo = is_string($navbar['logo_text'] ?? null) ? $navbar['logo_text'] : '';
        $showLogo = ! in_array($navbar['show_logo'] ?? true, [false, 0, '0', 'false'], true);
        $logoImage = $url($navbar['logo_image'] ?? null, 'Logo.svg');
        $html = '<div class="rail relative min-h-screen overflow-x-clip font-body">
    <div class="progress" aria-hidden="true"></div>
    <nav class="sticky top-0 z-40 flex items-center gap-4 border-b border-[var(--line)] bg-[#e3cfe6]/85 px-4 py-3 backdrop-blur lg:hidden">';
        if ($showLogo) {
            $html .= '<img src="'.$logoImage.'" alt="'.$escape($logo).'" width="32" height="32" class="shrink-0">';
        }
        $html .= '<ul class="no-scrollbar flex gap-1 overflow-x-auto">'.$mobileLinks.'</ul>
    </nav>
    <div class="relative z-[1] grid items-start lg:grid-cols-[264px_minmax(0,1fr)]">
        <aside class="rail-panel sticky top-4 hidden h-[calc(100svh-2rem)] flex-col justify-between p-6 lg:ml-4 lg:mt-4 lg:flex">
            <a href="#home" class="rail-in flex items-center gap-3 rounded-lg" style="--i: 0" aria-label="Back to top">';
        if ($showLogo) {
            $html .= '<img src="'.$logoImage.'" alt="'.$escape($logo).'" width="40" height="40">';
        }
        $html .= '</a>
            <ul class="space-y-1">'.$desktopLinks.'</ul>
            <div class="rail-in space-y-3 text-sm text-[var(--muted)]" style="--i: '.(count($links) + 1).'">';
        if (! empty($contact['contact_email'])) {
            $email = (string) $contact['contact_email'];
            $html .= '<a href="'.$url('mailto:'.$email).'" class="sweep block break-all font-semibold text-[var(--ink)]">'.$escape($email).'</a>';
        }
        if ($socialLinks !== []) {
            $html .= '<div class="flex flex-wrap gap-x-4 gap-y-1">';
            foreach ($socialLinks as $socialLink) {
                $html .= '<a href="'.$url($socialLink['url'] ?? null).'" target="_blank" rel="noreferrer" class="transition-colors hover:text-[var(--accent)]">'.$escape($socialLink['label'] ?? '').'</a>';
            }
            $html .= '</div>';
        }
        $html .= '</div>
        </aside>
        <main class="min-w-0 px-5 pb-10 sm:px-10 lg:px-16 xl:px-24">
            <header id="home" data-section class="grid min-h-[calc(100svh-60px)] items-center gap-12 py-16 lg:min-h-screen lg:grid-cols-12 lg:py-24">
                <div class="lg:col-span-8">';
        if (! empty($hero['hero_greeting'])) {
            $html .= '<p class="rail-fade mb-6 flex items-center gap-2 text-base font-medium text-[var(--muted)]" style="--i: 3"><span class="dot"></span>'.$escape($hero['hero_greeting']).'</p>';
        }
        $heroWords = is_string($hero['hero_name'] ?? null) ? preg_split('/\s+/', trim($hero['hero_name']), -1, PREG_SPLIT_NO_EMPTY) : [];
        if ($heroWords !== []) {
            $html .= '<h1 class="font-display text-[clamp(3.25rem,8vw,7.5rem)] leading-[0.95] tracking-[-0.025em]">';
            foreach ($heroWords as $index => $word) {
                $html .= '<span class="mask"><span class="mask-inner" style="--i: '.$index.'">'.$escape($word).'&nbsp;</span></span>';
            }
            $html .= '</h1>';
        }
        if (! empty($hero['hero_role'])) {
            $html .= '<p class="mt-6 text-2xl font-semibold sm:text-3xl"><span class="mark" data-reveal>'.$escape($hero['hero_role']).'</span></p>';
        }
        if (! empty($hero['hero_description'])) {
            $html .= '<p class="rail-fade mt-6 max-w-xl text-lg leading-relaxed text-[var(--muted)] sm:text-xl" style="--i: 6">'.$escape($hero['hero_description']).'</p>';
        }
        $html .= '<div class="rail-fade mt-10 flex flex-wrap items-center gap-x-8 gap-y-4" style="--i: 7">';
        if (! empty($hero['hero_cta_primary_text'])) {
            $html .= '<a href="'.$url($hero['hero_cta_primary_url'] ?? null, '#projects').'" class="btn-ink group">'.$escape($hero['hero_cta_primary_text']).'<span class="btn-ink-icon" aria-hidden="true">↓</span></a>';
        }
        foreach ($this->items($hero['hero_social_links'] ?? []) as $socialLink) {
            $html .= '<a href="'.$url($socialLink['url'] ?? null).'" target="_blank" rel="noreferrer" class="sweep font-semibold lg:hidden">'.$escape($socialLink['label'] ?? '').'</a>';
        }
        $heroImage = $this->assetUrl($hero['hero_image'] ?? null, $assets);
        $heroFallback = ($hero['hero_name'] ?? '') ?: ($logo ?: '•');
        $heroInitial = is_string($heroFallback) && $heroFallback !== '' ? mb_substr($heroFallback, 0, 1) : '•';
        $html .= '</div>
                </div>
                <figure class="rail-fade lg:col-span-4" style="--i: 5">
                    <div class="portrait group">';
        if ($heroImage !== '') {
            $html .= '<img src="'.$escape($this->safeUrl($heroImage, '')).'" alt="'.$escape($hero['hero_name'] ?? '').'" class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.05]" />';
        } else {
            $html .= '<span class="grid h-full w-full place-items-center font-display text-[9rem] text-[#2f1a7a]/30" aria-hidden="true">'.$escape($heroInitial).'</span>';
        }
        $html .= '</div></figure></header>';

        if ($hasAbout) {
            $aboutImage = $this->assetUrl($about['about_image'] ?? null, $assets);
            $html .= '<section id="about" data-section class="section grid gap-12 lg:grid-cols-5">';
            if ($aboutImage !== '') {
                $html .= '<div class="lg:col-span-2"><div class="portrait portrait-alt group"><img src="'.$escape($this->safeUrl($aboutImage, '')).'" alt="'.$escape($about['about_title'] ?? '').'" class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.05]" /></div></div>';
            }
            $html .= '<div class="'.($aboutImage !== '' ? 'lg:col-span-3' : 'lg:col-span-5 max-w-3xl').'">';
            if (! empty($about['about_title'])) {
                $html .= '<h2 class="h2"><span class="mark" data-reveal>'.$escape($about['about_title']).'</span></h2>';
            }
            if (! empty($about['about_description'])) {
                $html .= '<p class="mt-8 max-w-xl whitespace-pre-line text-lg leading-[1.8] text-[var(--muted)]">'.$escape($about['about_description']).'</p>';
            }
            if (! empty($about['about_cv_file'])) {
                $html .= '<a href="'.$url($about['about_cv_file']).'" class="btn-ink group mt-10">Download CV <span class="btn-ink-icon" aria-hidden="true">↓</span></a>';
            }
            $html .= '</div></section>';
        }

        if ($projects !== []) {
            $html .= '<section id="projects" data-section class="section">
                <div class="mb-12 flex flex-wrap items-end justify-between gap-4"><div>';
            if (! empty($projectContent['projects_title'])) {
                $html .= '<h2 class="h2">'.$escape($projectContent['projects_title']).'</h2>';
            }
            if (! empty($projectContent['projects_subtitle'])) {
                $html .= '<p class="mt-4 max-w-lg text-lg text-[var(--muted)]">'.$escape($projectContent['projects_subtitle']).'</p>';
            }
            $projectCount = count($projects);
            $html .= '</div><p class="text-[var(--muted)]">'.$projectCount.' '.($projectCount === 1 ? 'project' : 'projects').'</p></div><div>';
            foreach ($projects as $index => $project) {
                $projectUrl = ! empty($project['url']) ? $url($project['url']) : null;
                $html .= '<article class="project group mb-14 lg:sticky lg:mb-[34vh]'.($index === $projectCount - 1 ? ' lg:!mb-0' : '').'" style="top: calc(1.5rem + '.(min($index, 5) * 14).'px)"><'.($projectUrl !== null ? 'a href="'.$projectUrl.'" target="_blank" rel="noreferrer"' : 'div').' class="block">
                    <div class="relative h-[70vh] w-full aspect-[16/9] overflow-hidden bg-gradient-to-br from-[#f1e6f8] to-[#e07ad8]">';
                $projectImage = $this->assetUrl($project['image'] ?? null, $assets);
                if ($projectImage !== '') {
                    $html .= '<img src="'.$escape($this->safeUrl($projectImage, '')).'" alt="'.$escape($project['title'] ?? '').'" class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.04]" loading="lazy" />';
                } else {
                    $projectInitial = is_string($project['title'] ?? null) && $project['title'] !== '' ? mb_substr($project['title'], 0, 1) : '•';
                    $html .= '<span class="grid h-full w-full place-items-center font-display text-9xl text-[#2f1a7a]/30" aria-hidden="true">'.$escape($projectInitial).'</span>';
                }
                $html .= '</div><div class="flex items-center justify-between gap-6 p-6 sm:p-8"><div class="min-w-0"><p class="mb-2 text-sm font-semibold tabular-nums text-[var(--muted)]">'.($index + 1).' / '.$projectCount.'</p>';
                if (! empty($project['title'])) {
                    $html .= '<h3 class="font-display text-2xl sm:text-4xl">'.$escape($project['title']).'</h3>';
                }
                $html .= '<div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-[var(--muted)]">';
                if (! empty($project['category'])) {
                    $html .= '<span class="rounded-full bg-[var(--lilac)] px-3 py-1 font-semibold text-[var(--ink)]">'.$escape($project['category']).'</span>';
                }
                if (! empty($project['period'])) {
                    $html .= '<span>'.$escape($project['period']).'</span>';
                }
                $html .= '</div></div>';
                if ($projectUrl !== null) {
                    $html .= '<span class="project-arrow" aria-hidden="true">↗</span>';
                }
                $html .= '</div></'.($projectUrl !== null ? 'a' : 'div').'></article>';
            }
            $html .= '</div></section>';
        }

        if ($skillItems !== []) {
            $html .= '<section id="skills" data-section class="section">';
            if (! empty($skills['skills_title'])) {
                $html .= '<h2 class="h2">'.$escape($skills['skills_title']).'</h2>';
            }
            if (! empty($skills['skills_description'])) {
                $html .= '<p class="mt-4 max-w-lg text-lg text-[var(--muted)]">'.$escape($skills['skills_description']).'</p>';
            }
            $html .= '<ul class="mt-12 flex flex-wrap gap-3" data-reveal>';
            foreach ($skillItems as $index => $skill) {
                $html .= '<li class="pill" style="--d: '.($index * 70).'ms">';
                if (! empty($skill['percent'])) {
                    $percent = max(0, min(100, (float) $skill['percent']));
                    $html .= '<span class="pill-fill" style="width: '.$percent.'%"></span>';
                }
                $html .= '<span class="relative z-10 font-semibold">'.$escape($skill['label'] ?? '').'</span>';
                if (! empty($skill['percent'])) {
                    $html .= '<span class="relative z-10 text-sm tabular-nums text-[var(--muted)]">'.$escape($skill['percent']).'%</span>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></section>';
        }

        if ($journeyItems !== []) {
            $html .= '<section id="journey" data-section class="section"><div class="journey-panel">';
            if (! empty($journey['journey_title'])) {
                $html .= '<h2 class="h2 text-white">'.$escape($journey['journey_title']).'</h2>';
            }
            if (! empty($journey['journey_subtitle'])) {
                $html .= '<p class="mt-4 max-w-lg text-lg text-white/85">'.$escape($journey['journey_subtitle']).'</p>';
            }
            $html .= '<div class="mt-14">';
            foreach ($journeyItems as $item) {
                $html .= '<article class="jrow" data-reveal>';
                if (! empty($item['period'])) {
                    $html .= '<p class="jperiod">'.$escape($item['period']).'</p>';
                }
                $html .= '<div class="md:col-start-2">';
                if (! empty($item['title'])) {
                    $html .= '<h3 class="font-display text-2xl leading-tight text-white sm:text-4xl">'.$escape($item['title']).'</h3>';
                }
                if (! empty($item['company'])) {
                    $html .= '<p class="mt-2 text-lg font-semibold text-[#fff0fb]">'.$escape($item['company']).'</p>';
                }
                if (! empty($item['description'])) {
                    $html .= '<p class="mt-4 max-w-xl leading-relaxed text-white/85">'.$escape($item['description']).'</p>';
                }
                $html .= '</div></article>';
            }
            $html .= '</div></div></section>';
        }

        if ($hasContact) {
            $html .= '<section id="contact" data-section class="section">';
            if (! empty($contact['contact_title'])) {
                $html .= '<h2 class="font-display text-[clamp(2.75rem,7vw,6rem)] leading-[0.98] tracking-[-0.025em]">'.$escape($contact['contact_title']).'</h2>';
            }
            if (! empty($contact['contact_description'])) {
                $html .= '<p class="mt-6 max-w-xl text-lg text-[var(--muted)] sm:text-xl">'.$escape($contact['contact_description']).'</p>';
            }
            if (! empty($contact['contact_email'])) {
                $email = (string) $contact['contact_email'];
                $html .= '<a href="'.$url('mailto:'.$email).'" class="sweep mt-10 inline-block break-all font-display text-2xl text-[var(--accent)] sm:text-4xl">'.$escape($email).'</a>';
            }
            $html .= '<div class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-[var(--muted)]">';
            if (! empty($contact['contact_phone'])) {
                $phone = (string) $contact['contact_phone'];
                $html .= '<a href="'.$url('tel:'.$phone).'" class="hover:text-[var(--ink)]">'.$escape($phone).'</a>';
            }
            if (! empty($contact['contact_location'])) {
                $html .= '<span>'.$escape($contact['contact_location']).'</span>';
            }
            foreach ($this->items($contact['contact_social_links'] ?? []) as $socialLink) {
                $html .= '<a href="'.$url($socialLink['url'] ?? null).'" target="_blank" rel="noreferrer" class="hover:text-[var(--ink)]">'.$escape($socialLink['label'] ?? '').'</a>';
            }
            $html .= '</div>';
            if ($formEnabled && $googleForm !== null) {
                $html .= '<div class="mt-14 rounded-[28px] border border-white/60 bg-white/75 p-6 shadow-[0_30px_60px_-40px_rgba(91,33,182,0.5)] backdrop-blur-md sm:p-10">
                    <form action="'.$escape($googleForm['action']).'" method="POST" target="bg-gform-frame" class="space-y-5 text-left" data-google-form>
                        <h3 class="font-display text-2xl font-semibold text-[var(--ink)]">Send a message</h3>
                        <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">Name
                            <input name="entry.'.$escape($googleForm['name_entry']).'" type="text" required autocomplete="name" class="w-full rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-[var(--ink)] focus:border-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/20" />
                        </label>
                        <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">Email
                            <input name="entry.'.$escape($googleForm['email_entry']).'" type="email" required autocomplete="email" class="w-full rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-[var(--ink)] focus:border-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/20" />
                        </label>
                        <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">Message
                            <textarea name="entry.'.$escape($googleForm['message_entry']).'" rows="5" required class="w-full resize-y rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-[var(--ink)] focus:border-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/20"></textarea>
                        </label>
                        <button type="submit" class="btn-ink disabled:cursor-not-allowed disabled:opacity-60">Send message <span class="btn-ink-icon" aria-hidden="true">→</span></button>
                        <p class="hidden text-sm font-semibold text-[var(--accent)]" role="status" data-form-status></p>
                    </form>
                </div>
                <iframe name="bg-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true"></iframe>';
            }
            $html .= '</section>';
        }

        if ($logo !== '') {
            $html .= '<footer class="mt-24 flex items-center justify-between border-t border-[var(--line)] pt-6 text-sm text-[var(--muted)]"><span>'.$escape($logo).'</span><a href="#home" class="sweep font-semibold text-[var(--ink)]">Back to top ↑</a></footer>';
        }

        return '<!doctype html>
<html lang="en" data-template="bento-grid">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e3cfe6">
    <title>'.$escape($title).'</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>'.$html.'
    <script src="script.js" defer></script>
</body>
</html>';
    }

    private function bentoGridStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Young+Serif&family=Hanken+Grotesk:wght@400;500;600;700&display=swap');
html{scroll-behavior:smooth;scroll-padding-top:70px}
.rail{--ink:#2a0f2d;--paper:#e3cfe6;--muted:#4a2650;--accent:#5b21b6;--orchid:#e07ad8;--orchid-soft:#f7c6f1;--lilac:#f1e6f8;--mark:rgba(255,255,255,.85);--line:rgba(42,15,45,.16);--grad:linear-gradient(180deg,#ddd5e3 0%,#dfcfe2 24%,#e4badf 44%,#e8a6dd 62%,#e484d6 82%,#d676d6 100%);--grain:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='220' height='220'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2' stitchTiles='stitch'/><feColorMatrix type='saturate' values='0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");--ease:cubic-bezier(.22,1,.36,1);background:var(--grad);color:var(--ink)}
.rail::before{content:'';position:absolute;inset:0;z-index:0;pointer-events:none;background-image:var(--grain);opacity:.55;mix-blend-mode:soft-light}
.font-display{font-family:'Young Serif',ui-serif,Georgia,serif;font-weight:400}
.font-body{font-family:'Hanken Grotesk',ui-sans-serif,system-ui,sans-serif}
.rail a:focus-visible,.rail button:focus-visible{outline:2px solid var(--accent);outline-offset:4px}
.rail-panel{position:sticky;border-radius:28px;background:var(--grad);border:1px solid rgba(255,255,255,.6);box-shadow:0 30px 60px -30px rgba(130,40,150,.55);overflow:hidden;isolation:isolate}
.rail-panel::before{content:'';position:absolute;inset:0;z-index:-1;pointer-events:none;background-image:var(--grain);opacity:.55;mix-blend-mode:soft-light}
.rail ::selection{background:var(--ink);color:var(--orchid-soft)}
.rail h1,.rail h2,.rail h3{text-wrap:balance}
.rail p{text-wrap:pretty}
.progress{position:fixed;inset:0 0 auto 0;z-index:60;height:3px;transform:scaleX(0);transform-origin:left;background:linear-gradient(90deg,var(--accent),var(--orchid))}
.no-scrollbar{scrollbar-width:none}
.no-scrollbar::-webkit-scrollbar{display:none}
.section{padding-top:clamp(5rem,12vw,9rem)}
.h2{font-family:'Young Serif',serif;font-size:clamp(2.5rem,5.5vw,4.5rem);line-height:1.02;letter-spacing:-.02em}
.rail-link{display:flex;align-items:center;gap:.8rem;padding:.5rem 0;font-size:1rem;font-weight:500;color:rgba(42,15,45,.55);transition:color .3s,transform .4s var(--ease)}
.rail-link:hover{color:var(--ink);transform:translateX(4px)}
.rail-bar{height:2px;width:14px;border-radius:2px;background:currentColor;transition:width .5s var(--ease),background .3s}
.rail-link.active{color:var(--ink)}
.rail-link.active .rail-bar{width:40px;background:var(--ink)}
.mobile-active{background:var(--ink);color:#fff}
.mask{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.12em;margin-bottom:-.12em}
.mask-inner{display:inline-block}
.dot{width:8px;height:8px;border-radius:50%;background:var(--accent);animation:ping 2.4s infinite}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(91,33,182,.45)}70%,100%{box-shadow:0 0 0 10px rgba(91,33,182,0)}}
.rail.motion-ready .mask-inner{transform:translateY(115%);transition:transform 1.1s var(--ease) calc(var(--i)*100ms + 250ms)}
.rail.motion-ready.is-ready .mask-inner{transform:none}
.rail.motion-ready .rail-fade{opacity:0;transform:translateY(18px);transition:opacity .9s var(--ease) calc(var(--i)*100ms + 250ms),transform .9s var(--ease) calc(var(--i)*100ms + 250ms)}
.rail.motion-ready.is-ready .rail-fade{opacity:1;transform:none}
.rail.motion-ready .rail-in{opacity:0;transform:translateX(-16px);transition:opacity .8s var(--ease) calc(var(--i)*70ms + 150ms),transform .8s var(--ease) calc(var(--i)*70ms + 150ms)}
.rail.motion-ready.is-ready .rail-in{opacity:1;transform:none}
.mark{background:linear-gradient(transparent 62%,var(--mark) 62%) no-repeat left / 0% 100%;box-decoration-break:clone;-webkit-box-decoration-break:clone;padding:0 .12em;margin:0 -.12em;transition:background-size 1.2s var(--ease) .5s}
.rail:not(.motion-ready) .mark,.mark.is-visible{background-size:100% 100%}
.portrait{aspect-ratio:4/5;overflow:hidden;background:rgba(255,255,255,.55);border-radius:140px 28px 140px 28px}
.portrait-alt{border-radius:28px 140px 28px 140px}
.btn-ink{display:inline-flex;align-items:center;gap:.9rem;padding:.5rem .5rem .5rem 1.6rem;border-radius:999px;background:var(--ink);color:#fff;font-weight:600;transition:background .3s,transform .35s var(--ease)}
.btn-ink:hover{background:var(--accent);transform:translateY(-2px)}
.btn-ink-icon{display:grid;place-items:center;width:40px;height:40px;border-radius:50%;background:var(--orchid-soft);color:var(--ink);transition:transform .45s var(--ease)}
.btn-ink:hover .btn-ink-icon{transform:translateY(3px)}
.sweep{background:linear-gradient(currentColor,currentColor) no-repeat 100% 100% / 0 2px;padding-bottom:2px;transition:background-size .45s var(--ease)}
.sweep:hover{background-position:0 100%;background-size:100% 2px}
.project{overflow:hidden;border-radius:28px;background:#fff;border:1px solid var(--line);box-shadow:0 30px 60px -36px rgba(91,33,182,.55)}
.project-arrow{display:grid;flex-shrink:0;place-items:center;width:52px;height:52px;border-radius:50%;background:var(--ink);color:#fff;font-size:1.3rem;transition:background .3s,color .3s,transform .5s var(--ease)}
.project:hover .project-arrow{background:var(--orchid);color:var(--ink);transform:rotate(45deg)}
.pill{position:relative;display:inline-flex;align-items:center;gap:.9rem;overflow:hidden;padding:.8rem 1.4rem;border-radius:999px;border:1px solid rgba(255,255,255,.7);background:rgba(255,255,255,.78);backdrop-filter:blur(10px);transition:border-color .3s,transform .4s var(--ease)}
.pill:hover{border-color:var(--accent);transform:translateY(-2px)}
.pill-fill{position:absolute;inset:0 auto 0 0;background:var(--orchid-soft);transform-origin:left}
.rail.motion-ready .pill-fill{transform:scaleX(0);transition:transform 1.2s var(--ease) calc(var(--d,0ms) + 200ms)}
.rail.motion-ready .is-visible .pill-fill{transform:none}
.journey-panel{padding:clamp(1.75rem,5vw,4.5rem);border-radius:36px;background:linear-gradient(155deg,#ea74ca 0%,#d04aa8 50%,#a82a86 100%);box-shadow:0 40px 80px -40px rgba(168,42,134,.7)}
.jrow{position:relative;display:grid;gap:1rem 3rem;padding:2.5rem 0}
@media(min-width:768px){.jrow{grid-template-columns:minmax(170px,.7fr) 2fr;padding:3.25rem 0}}
.jrow::before,.jrow::after{content:'';position:absolute;inset:0 0 auto 0;height:2px}
.jrow::before{background:rgba(255,255,255,.28)}
.jrow::after{background:#fff;transform-origin:left}
.rail.motion-ready .jrow::after{transform:scaleX(0);transition:transform 1.3s var(--ease)}
.rail.motion-ready .jrow.is-visible::after{transform:none}
.jperiod{font-family:'Young Serif',serif;font-size:clamp(2rem,4.2vw,3.5rem);line-height:1;color:#fff;transition:transform .5s var(--ease)}
.jrow:hover .jperiod{transform:translateX(10px)}
@media(prefers-reduced-motion:reduce){.rail .mask-inner,.rail .rail-fade,.rail .rail-in,.rail .pill-fill{opacity:1!important;transform:none!important;transition:none!important}.mark{background-size:100% 100%!important;transition:none!important}.dot{animation:none!important}.jrow::after{transform:none!important;transition:none!important}}
CSS;
    }
}
