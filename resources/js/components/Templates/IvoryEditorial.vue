<script setup lang="ts">
/**
 * Ivory Editorial (elevated) — quickFolio template
 * Slug suggestion: `ivory-editorial`
 *
 * Same ivory + cobalt identity, refined: Instrument Serif display type, hairline structure,
 * a floating nav that hides on scroll, a masked hero reveal with a parallax portrait,
 * word-by-word about text, animated skill bars, and a Google Form contact form.
 *
 * `content` is keyed by section key (navbar, header, about, journey, skills, projects, contact).
 * An array of `{ section_key, is_enabled, field_values: [...] }` is also accepted.
 */
import { resolvePortfolioMediaUrl } from '@/lib/media';
import { handleContactFormFallback } from '@/lib/contact-form-fallback';
import { ArrowDown, ArrowUpRight, Check, Download, MapPin, Phone, Send } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type Dict = Record<string, any>;

interface GoogleFormConfig {
    url?: string | null;
    nameEntry?: string | null;
    emailEntry?: string | null;
    messageEntry?: string | null;
}

const props = withDefaults(
    defineProps<{
        content?: Dict | any[];
        designSettings?: Dict | null;
        /** Optional per-section switches, e.g. { skills: false } */
        enabled?: Record<string, boolean>;
        /** Portfolio-level Google Form settings (camelCase) */
        googleForm?: GoogleFormConfig | null;
        /** Alternative: pass the portfolio and the `google_form_*` columns are read from it */
        portfolio?: Dict | null;
    }>(),
    { content: undefined, designSettings: null, enabled: () => ({}), googleForm: null, portfolio: null },
);

/* ------------------------------------------------------------------ */
/* Data access                                                         */
/* ------------------------------------------------------------------ */

const disabledSections = ref<Set<string>>(new Set());

const data = computed<Record<string, Dict>>(() => {
    const src: any = props.content ?? {};
    const out: Record<string, Dict> = {};
    const off = new Set<string>();

    if (Array.isArray(src)) {
        for (const s of src) {
            const key = s?.section_key ?? s?.key;
            if (!key) continue;
            if (s.is_enabled === false || s.is_enabled === 0) off.add(key);
            const bag: Dict = { ...(s.values ?? s.content ?? {}) };
            const rows = s.field_values ?? s.fieldValues ?? s.fields ?? [];
            for (const fv of Array.isArray(rows) ? rows : []) {
                const fk = fv?.field_key ?? fv?.field_definition?.field_key ?? fv?.fieldDefinition?.field_key ?? fv?.key;
                if (fk) bag[fk] = fv.value;
            }
            out[key] = bag;
        }
    } else if (src && typeof src === 'object') {
        for (const key of Object.keys(src)) {
            const s = src[key];
            if (s?.is_enabled === false || s?.is_enabled === 0) off.add(key);
            out[key] = s?.values ?? s?.content ?? s ?? {};
        }
    }
    disabledSections.value = off;
    return out;
});

const clean = (x: unknown): string | undefined => {
    if (x === null || x === undefined || typeof x === 'object') return undefined;
    const s = String(x).trim();
    return s.length ? s : undefined;
};
const sectionData = (section: string): Dict => data.value[section] ?? {};
const raw = (section: string, key: string): any => sectionData(section)[key];
const text = (section: string, key: string) => clean(raw(section, key));
const media = (section: string, key: string): string | undefined => {
    const r = raw(section, key);
    const value = typeof r === 'string' ? clean(r) : r && typeof r === 'object' ? clean(r.url ?? r.path ?? r.src) : undefined;
    return value ? resolvePortfolioMediaUrl(value) : undefined;
};
const flag = (section: string, key: string, fallback: boolean) => {
    const r = raw(section, key);
    if (r === undefined || r === null || r === '') return fallback;
    return r === true || r === 1 || r === '1' || r === 'true';
};
const arr = (section: string, key: string): any[] => {
    let r = raw(section, key);
    if (typeof r === 'string') {
        try {
            const parsed = JSON.parse(r);
            if (Array.isArray(parsed)) r = parsed;
        } catch {
            /* plain string, ignore */
        }
    }
    return Array.isArray(r) ? r : [];
};

const isOn = (section: string) =>
    props.enabled[section] !== false && !disabledSections.value.has(section);
const hasContent = (value: unknown): boolean => {
    if (typeof value === 'string') return value.trim().length > 0;
    if (Array.isArray(value)) return value.some(hasContent);
    if (value && typeof value === 'object') return Object.values(value).some(hasContent);
    return false;
};
const has = (section: string) => isOn(section) && Object.values(sectionData(section)).some(hasContent);

/* ------------------------------------------------------------------ */
/* Normalised content                                                  */
/* ------------------------------------------------------------------ */

const socialPlatformLabels: Record<string, string> = {
    behance: 'Behance',
    dribbble: 'Dribbble',
    email: 'Email',
    facebook: 'Facebook',
    github: 'GitHub',
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    website: 'Website',
    x: 'X',
    twitter: 'X',
    youtube: 'YouTube',
};
const hostOf = (url: string) => {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
};
const normSocials = (section: string, key: string) =>
    arr(section, key)
        .map((i) => {
            if (typeof i === 'string') return { label: hostOf(i), url: clean(i) ?? '' };
            const url = clean(i?.url ?? i?.href) ?? '';
            const platform = clean(i?.platform ?? i?.label ?? i?.name) ?? '';
            const platformKey = platform.toLowerCase().replace(/[^a-z]/g, '');
            return { label: clean(i?.label ?? i?.name) ?? socialPlatformLabels[platformKey] ?? hostOf(url), url };
        })
        .filter((i) => i.url);

// Navbar
const logoText = computed(() => text('navbar', 'logo_text') ?? text('header', 'hero_name'));
const logoImage = computed(() => (flag('navbar', 'show_logo', true) ? media('navbar', 'logo_image') ?? '/logo.svg' : undefined));

// Hero
const heroName = computed(() => text('header', 'hero_name'));
const heroWords = computed(() => (heroName.value ?? '').split(/\s+/).filter(Boolean));
const heroInitials = computed(() =>
    (heroName.value ?? logoText.value ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);
const heroSocials = computed(() => normSocials('header', 'hero_social_links'));
const hasHeroCtas = computed(
    () =>
        !!(text('header', 'hero_cta_primary_text') && text('header', 'hero_cta_primary_url')) ||
        !!(text('header', 'hero_cta_secondary_text') && text('header', 'hero_cta_secondary_url')),
);

// About
const aboutWords = computed(() => (text('about', 'about_description') ?? '').split(/\s+/).filter(Boolean));
const highlights = computed(() =>
    arr('about', 'about_highlights')
        .map((h) => (typeof h === 'string' ? clean(h) : clean(h?.title ?? h?.label ?? h?.text ?? h?.name)) ?? '')
        .filter(Boolean),
);

// Journey
const journey = computed(() =>
    arr('journey', 'journey_list')
        .map((item) => ({
            period: clean(item?.period),
            title: clean(item?.title ?? item?.role),
            company: clean(item?.company ?? item?.institution),
            description: clean(item?.description),
        }))
        .filter((item) => item.title || item.company || item.period || item.description),
);

// Skills — numbers up to 5 are read as a 5-point scale, larger numbers as a percentage; anything else is shown as text.
const skills = computed(() =>
    arr('skills', 'skills_list')
        .map((s) => {
            const name = clean(typeof s === 'string' ? s : (s?.label ?? s?.name ?? s?.title));
            const lv = typeof s === 'object' ? clean(s?.percent ?? s?.level) : undefined;
            let pct: number | null = null;
            let label: string | undefined;
            if (lv) {
                if (/^[\d.]+\s*%?$/.test(lv)) {
                    const n = parseFloat(lv);
                    pct = Math.max(0, Math.min(100, n <= 5 ? n * 20 : n));
                } else label = lv;
            }
            return { name: name ?? '', pct, label };
        })
        .filter((s) => s.name),
);

const TICKS = 28;
const tickOn = (pct: number | null) => Math.round(((pct ?? 0) / 100) * TICKS);

// Projects
const projects = computed(() => {
    const list = arr('projects', 'projects_list');
    return (list.length ? list : arr('projects', 'projects'))
        .map((p) => {
            const imageValue = typeof p?.image === 'string' ? clean(p.image) : clean(p?.image?.url ?? p?.image?.path);
            return {
                title: clean(p?.title ?? p?.name) ?? '',
                category: clean(p?.category),
                description: clean(p?.description),
                image: imageValue ? resolvePortfolioMediaUrl(imageValue) : undefined,
                url: clean(p?.live_url ?? p?.url),
                repo: clean(p?.repo_url),
            };
        })
        .filter((p) => p.title);
});

// Contact
const contactSocials = computed(() => {
    const own = normSocials('contact', 'contact_social_links');
    return own.length ? own : heroSocials.value;
});
const email = computed(() => text('contact', 'contact_email'));
const phone = computed(() => text('contact', 'contact_phone'));
const location = computed(() => text('contact', 'contact_location'));

/* ------------------------------------------------------------------ */
/* Google Form contact (browser posts straight to Google)             */
/* ------------------------------------------------------------------ */

const gf = computed(() => {
    const a: Dict = props.googleForm ?? {};
    const p: Dict = props.portfolio ?? {};
    const id = (v: unknown) => clean(v)?.replace(/^entry\./, '');
    return {
        url: clean(a.url ?? p.google_form_url),
        name: id(a.nameEntry ?? p.google_form_name_entry),
        email: id(a.emailEntry ?? p.google_form_email_entry),
        message: id(a.messageEntry ?? p.google_form_message_entry),
    };
});
const formReady = computed(
    () => flag('contact', 'contact_form_enabled', true),
);
const formAction = computed(() => (gf.value.url ?? '').replace(/\/viewform.*$/, '/formResponse'));
const showContact = computed(() => isOn('contact') && (has('contact') || formReady.value));

const sent = ref(false);
const sending = ref(false);
const onSubmit = (e: Event) => {
    const form = e.target as HTMLFormElement;
    sent.value = false;
    sending.value = true;
    // Google answers inside the hidden iframe; its result can't be read, so confirm optimistically.
    window.setTimeout(() => {
        sending.value = false;
        sent.value = true;
        form.reset();
    }, 700);
};

/* ------------------------------------------------------------------ */
/* Navigation                                                          */
/* ------------------------------------------------------------------ */

const navLinks = computed(
    () =>
        [
            { id: 'about', label: 'About' },
            { id: 'journey', label: 'Journey' },
            { id: 'skills', label: 'Skills' },
            { id: 'projects', label: 'Work' },
        ].filter((l) => has(l.id)),
);

/* ------------------------------------------------------------------ */
/* Behaviour: scroll state, reveal, active section, pointer effects    */
/* ------------------------------------------------------------------ */

const rootEl = ref<HTMLElement | null>(null);
const ready = ref(false);
const progress = ref(0);
const scrolled = ref(false);
const navHidden = ref(false);
const activeId = ref('home');
let lastY = 0;
let observer: IntersectionObserver | null = null;
let sectionObserver: IntersectionObserver | null = null;

const sectionIds = ['home', 'about', 'journey', 'skills', 'projects', 'contact'];
const reduceMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const onNavClick = (e: Event, url?: string) => {
    if (!url || !url.startsWith('#') || url.length < 2) return;
    const el = document.getElementById(url.slice(1));
    if (!el) return;
    e.preventDefault();
    navHidden.value = false;
    el.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'start' });
};

const onScroll = () => {
    const y = window.scrollY;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    progress.value = max > 0 ? Math.min(1, y / max) : 0;
    scrolled.value = y > 16;
    if (Math.abs(y - lastY) > 6) {
        navHidden.value = y > 160 && y > lastY;
        lastY = y;
    }
    rootEl.value?.style.setProperty('--sy', String(Math.min(y, 900)));
};

const observeReveals = () => {
    const els = rootEl.value?.querySelectorAll<HTMLElement>('[data-reveal]:not(.is-in)') ?? [];
    if (!('IntersectionObserver' in window)) {
        els.forEach((el) => el.classList.add('is-in'));
        return;
    }
    observer ??= new IntersectionObserver(
        (entries) =>
            entries.forEach((en) => {
                if (en.isIntersecting) {
                    en.target.classList.add('is-in');
                    observer?.unobserve(en.target);
                }
            }),
        { threshold: 0.14, rootMargin: '0px 0px -5% 0px' },
    );
    els.forEach((el) => observer!.observe(el));
};

const observeSections = () => {
    sectionObserver?.disconnect();
    if (!('IntersectionObserver' in window)) return;
    sectionObserver = new IntersectionObserver(
        (entries) =>
            entries.forEach((en) => {
                if (en.isIntersecting) activeId.value = en.target.id;
            }),
        { rootMargin: '-45% 0px -50% 0px' },
    );
    sectionIds.forEach((id) => {
        const el = document.getElementById(id);
        if (el) sectionObserver!.observe(el);
    });
};

// Buttons drift slightly toward the pointer
const onMagnet = (e: MouseEvent) => {
    if (reduceMotion()) return;
    const el = e.currentTarget as HTMLElement;
    const r = el.getBoundingClientRect();
    el.style.transform = `translate(${(e.clientX - (r.left + r.width / 2)) * 0.2}px, ${(e.clientY - (r.top + r.height / 2)) * 0.28}px)`;
};
let glowRaf = 0;
const onPointer = (e: PointerEvent) => {
    if (glowRaf) return;
    glowRaf = requestAnimationFrame(() => {
        glowRaf = 0;
        rootEl.value?.style.setProperty('--gx', `${e.clientX}px`);
        rootEl.value?.style.setProperty('--gy', `${e.clientY}px`);
    });
};
const onMagnetOut = (e: MouseEvent) => {
    (e.currentTarget as HTMLElement).style.transform = '';
};
onMounted(async () => {
    ready.value = true; // reveal styles only apply after mount, so SSR output stays visible
    await nextTick();
    observeReveals();
    observeSections();
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    if (!reduceMotion() && window.matchMedia('(pointer: fine)').matches) window.addEventListener('pointermove', onPointer, { passive: true });
});
onBeforeUnmount(() => {
    observer?.disconnect();
    sectionObserver?.disconnect();
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('pointermove', onPointer);
    cancelAnimationFrame(glowRaf);
});
// Re-scan when content changes (editor live preview)
watch(data, async () => {
    if (!ready.value) return;
    await nextTick();
    observeReveals();
    observeSections();
});

const year = new Date().getFullYear();
</script>

<template>
    <div
        ref="rootEl"
        class="ie-root min-h-screen overflow-x-hidden selection:bg-[#244FCE] selection:text-white"
        :class="{ 'ie-js': ready }"
    >
        <div class="ie-glow" aria-hidden="true" />

        <!-- ============================ NAVBAR ============================ -->
        <header class="ie-navwrap fixed inset-x-0 top-0 z-50 px-3 pt-3 sm:px-6 sm:pt-4" :class="{ 'is-hidden': navHidden }">
            <div class="ie-progress" :style="{ transform: `scaleX(${progress})` }" aria-hidden="true" />
            <nav class="ie-nav mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 pl-3 pr-2" :class="{ 'is-scrolled': scrolled }" aria-label="Primary">
                <a v-if="logoImage" href="#home" class="flex min-w-0 items-center gap-3" @click="onNavClick($event, '#home')">
                    <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-8 w-auto max-w-[7rem] object-contain" />
                </a>
                <span v-else />

                <ul v-if="navLinks.length" class="hidden items-center gap-1 md:flex">
                    <li v-for="l in navLinks" :key="l.id">
                        <a :href="`#${l.id}`" class="ie-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a>
                    </li>
                </ul>

                <a
                    v-if="showContact"
                    href="#contact"
                    class="ie-btn ie-btn-ink ie-magnet !px-5 !py-2.5 !text-[0.85rem]"
                    @click="onNavClick($event, '#contact')"
                    @mousemove="onMagnet"
                    @mouseleave="onMagnetOut"
                    >Let's talk<ArrowUpRight class="ie-arrow h-4 w-4"
                /></a>
                <span v-else />
            </nav>
        </header>

        <main>
            <!-- ============================== HERO ============================== -->
            <section v-if="has('header')" id="home" class="relative isolate mx-auto grid min-h-[100svh] max-w-7xl items-center gap-14 px-6 pb-24 pt-32 lg:grid-cols-[1.1fr_.9fr] lg:px-10">
                <div class="ie-rings" aria-hidden="true"><span /><span /><span /></div>
                <div class="relative z-10">
                    <p v-if="text('header', 'hero_greeting')" data-reveal class="ie-chip" style="--d: 0ms">
                        <span class="ie-pulse" aria-hidden="true" />{{ text('header', 'hero_greeting') }}
                    </p>

                    <h1 v-if="heroName" class="ie-serif ie-hero-name mt-7">
                        <span class="sr-only">{{ heroName }}</span>
                        <span v-for="(w, i) in heroWords" :key="i" class="ie-mask" aria-hidden="true"
                            ><span class="ie-word" :style="{ '--i': i }">{{ w }}</span></span
                        >
                    </h1>

                    <p v-if="text('header', 'hero_role')" data-reveal class="ie-serif mt-6 text-3xl italic text-[var(--accent)] sm:text-4xl" style="--d: 420ms">
                        {{ text('header', 'hero_role') }}
                    </p>
                    <p v-if="text('header', 'hero_description')" data-reveal class="mt-6 max-w-[34rem] whitespace-pre-line text-lg leading-[1.75] text-[var(--muted)]" style="--d: 500ms">
                        {{ text('header', 'hero_description') }}
                    </p>

                    <div v-if="hasHeroCtas" data-reveal class="mt-10 flex flex-wrap items-center gap-3" style="--d: 580ms">
                        <a
                            v-if="text('header', 'hero_cta_primary_text') && text('header', 'hero_cta_primary_url')"
                            :href="text('header', 'hero_cta_primary_url')"
                            class="ie-btn ie-btn-accent ie-magnet"
                            @click="onNavClick($event, text('header', 'hero_cta_primary_url'))"
                            @mousemove="onMagnet"
                            @mouseleave="onMagnetOut"
                            >{{ text('header', 'hero_cta_primary_text') }}<ArrowUpRight class="ie-arrow h-4 w-4"
                        /></a>
                        <a
                            v-if="text('header', 'hero_cta_secondary_text') && text('header', 'hero_cta_secondary_url')"
                            :href="text('header', 'hero_cta_secondary_url')"
                            class="ie-btn ie-btn-ghost"
                            @click="onNavClick($event, text('header', 'hero_cta_secondary_url'))"
                            >{{ text('header', 'hero_cta_secondary_text') }}</a
                        >
                    </div>

                    <ul v-if="heroSocials.length" data-reveal class="mt-9 flex flex-wrap gap-x-6 gap-y-3" style="--d: 660ms">
                        <li v-for="(s, i) in heroSocials" :key="i">
                            <a :href="s.url" target="_blank" rel="noopener noreferrer" class="ie-ulink text-sm font-semibold text-[var(--muted)] hover:text-[var(--accent)]">{{ s.label }}</a>
                        </li>
                    </ul>
                </div>

                <figure data-reveal class="ie-portrait relative mx-auto w-full max-w-[460px]" style="--d: 200ms">
                    <div class="ie-outline" aria-hidden="true" />
                    <div class="ie-portrait-img relative aspect-[4/5] overflow-hidden bg-[var(--paper-3)]">
                        <img v-if="media('header', 'hero_image')" :src="media('header', 'hero_image')" :alt="heroName ?? ''" class="ie-hero-img h-full w-full object-cover" />
                        <span v-else-if="heroInitials" class="ie-serif flex h-full w-full items-center justify-center text-[9rem] text-[var(--accent)]">{{ heroInitials }}</span>
                    </div>
                </figure>

                <a v-if="has('about')" href="#about" class="ie-cue absolute bottom-8 left-6 hidden items-center gap-3 text-xs font-semibold text-[var(--muted)] sm:flex lg:left-10" @click="onNavClick($event, '#about')">
                    <span class="ie-cue-line" aria-hidden="true" /><span>Scroll</span><ArrowDown class="h-3.5 w-3.5" />
                </a>
            </section>

            <!-- ============================== ABOUT ============================== -->
            <section v-if="has('about')" id="about" class="scroll-mt-4 border-t border-[var(--line)] bg-[var(--paper-2)] px-6 py-28 sm:py-36 lg:px-10">
                <div class="mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-[.8fr_1.2fr] lg:gap-24">
                    <div v-if="media('about', 'about_image')" data-reveal class="ie-portrait relative mx-auto w-full max-w-md">
                        <div class="ie-outline" aria-hidden="true" />
                        <div class="ie-media relative aspect-[4/5] overflow-hidden bg-[var(--paper-3)]">
                            <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="h-full w-full object-cover" loading="lazy" />
                        </div>
                    </div>

                    <div>
                        <div data-reveal>
                            <p v-if="text('about', 'about_title') || text('about', 'about_subtitle')" class="ie-kicker">
                                {{ text('about', 'about_title') ?? text('about', 'about_subtitle') }}
                            </p>
                            <p v-if="text('about', 'about_description')" class="ie-serif ie-lead mt-7 max-w-3xl">
                                {{ text('about', 'about_description') }}
                            </p>
                        </div>

                        <ul v-if="highlights.length" data-reveal class="mt-10 max-w-xl border-t border-[var(--line)]">
                            <li v-for="(h, i) in highlights" :key="i" :style="{ '--k': i }" class="ie-hl flex items-center gap-4 border-b border-[var(--line)] py-4 font-medium">
                                <span class="ie-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>{{ h }}
                            </li>
                        </ul>

                        <div v-if="media('about', 'about_cv_file') || (text('about', 'about_cta_text') && text('about', 'about_cta_url'))" data-reveal class="mt-10 flex flex-wrap items-center gap-3">
                            <a v-if="media('about', 'about_cv_file')" :href="media('about', 'about_cv_file')" download target="_blank" rel="noopener" class="ie-btn ie-btn-accent">
                                <Download class="h-4 w-4" />{{ text('about', 'about_cta_text') ?? 'Download CV' }}
                            </a>
                            <a
                                v-if="text('about', 'about_cta_text') && text('about', 'about_cta_url') && media('about', 'about_cv_file')"
                                :href="text('about', 'about_cta_url')"
                                class="ie-btn ie-btn-ghost"
                                @click="onNavClick($event, text('about', 'about_cta_url'))"
                                >{{ text('about', 'about_cta_text') }}</a
                            >
                            <a
                                v-else-if="text('about', 'about_cta_text') && text('about', 'about_cta_url')"
                                :href="text('about', 'about_cta_url')"
                                class="ie-btn ie-btn-accent"
                                @click="onNavClick($event, text('about', 'about_cta_url'))"
                                >{{ text('about', 'about_cta_text') }}<ArrowUpRight class="ie-arrow h-4 w-4"
                            /></a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================== JOURNEY ============================== -->
            <section v-if="has('journey')" id="journey" class="scroll-mt-4 px-6 py-28 sm:py-36 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <div data-reveal class="mb-14 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <h2 v-if="text('journey', 'journey_title')" class="ie-serif ie-h2 max-w-2xl">{{ text('journey', 'journey_title') }}</h2>
                        <p v-if="text('journey', 'journey_subtitle')" class="max-w-sm leading-7 text-[var(--muted)]">{{ text('journey', 'journey_subtitle') }}</p>
                    </div>

                    <div class="ie-rule" data-reveal aria-hidden="true" />
                    <ol>
                        <li v-for="(item, index) in journey" :key="index" data-reveal :style="{ '--d': `${(index % 3) * 60}ms` }" class="ie-rowwrap">
                            <div class="ie-row grid gap-3 py-9 sm:grid-cols-[170px_1fr] sm:gap-12">
                                <p v-if="item.period" class="ie-period pt-1.5 text-sm font-semibold text-[var(--accent)]">{{ item.period }}</p>
                                <div>
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                                        <h3 v-if="item.title" class="ie-serif ie-row-title text-3xl sm:text-4xl">{{ item.title }}</h3>
                                        <p v-if="item.company" class="text-sm font-semibold text-[var(--muted)]">{{ item.company }}</p>
                                    </div>
                                    <p v-if="item.description" class="mt-3 max-w-3xl whitespace-pre-line leading-[1.75] text-[var(--muted)]">{{ item.description }}</p>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- ============================== SKILLS ============================== -->
            <section v-if="has('skills')" id="skills" class="scroll-mt-4 border-y border-[var(--line)] bg-[var(--tint)] px-6 py-28 sm:py-36 lg:px-10">
                <div class="mx-auto grid max-w-7xl gap-14 lg:grid-cols-12 lg:gap-20">
                    <div class="lg:col-span-4">
                        <div data-reveal class="lg:sticky lg:top-28">
                            <p v-if="text('skills', 'skills_subtitle')" class="ie-kicker">{{ text('skills', 'skills_subtitle') }}</p>
                            <h2 v-if="text('skills', 'skills_title')" class="ie-serif ie-h2 mt-5">{{ text('skills', 'skills_title') }}</h2>
                            <p v-if="text('skills', 'skills_description')" class="mt-6 max-w-sm whitespace-pre-line leading-[1.75] text-[var(--muted)]">{{ text('skills', 'skills_description') }}</p>
                        </div>
                    </div>

                    <ul class="border-t border-[var(--line)] lg:col-span-8">
                        <li v-for="(s, i) in skills" :key="i" data-reveal :style="{ '--d': `${(i % 5) * 60}ms` }">
                            <div class="ie-skill">
                                <div class="flex items-baseline justify-between gap-6">
                                    <h3 class="ie-serif ie-skill-name text-3xl leading-tight sm:text-[2.6rem]">{{ s.name }}</h3>
                                    <span v-if="flag('skills', 'skills_show_level', true) && s.pct !== null" class="ie-count text-lg font-semibold text-[var(--accent)]" :style="{ '--p': Math.round(s.pct) }" aria-hidden="true" />
                                    <span v-else-if="flag('skills', 'skills_show_level', true) && s.label" class="text-sm font-semibold text-[var(--accent)]">{{ s.label }}</span>
                                </div>
                                <div
                                    v-if="flag('skills', 'skills_show_level', true) && s.pct !== null"
                                    class="ie-ticks"
                                    role="progressbar"
                                    :aria-valuenow="Math.round(s.pct)"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    :aria-label="s.name"
                                >
                                    <span v-for="n in TICKS" :key="n" class="ie-tk" :class="{ on: n <= tickOn(s.pct), end: n === tickOn(s.pct) }" :style="{ '--t': n }" />
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- ============================== PROJECTS ============================== -->
            <section v-if="has('projects')" id="projects" class="scroll-mt-4 px-6 py-28 sm:py-36 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <div data-reveal class="mb-14 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <h2 v-if="text('projects', 'projects_title')" class="ie-serif ie-h2 max-w-2xl">{{ text('projects', 'projects_title') }}</h2>
                        <p v-if="text('projects', 'projects_subtitle') || text('projects', 'projects_description')" class="max-w-sm whitespace-pre-line leading-7 text-[var(--muted)]">
                            {{ text('projects', 'projects_subtitle') ?? text('projects', 'projects_description') }}
                        </p>
                    </div>
                    <div class="ie-rule mb-14" data-reveal aria-hidden="true" />

                    <ul class="grid gap-x-10 gap-y-16 md:grid-cols-2">
                        <li v-for="(p, i) in projects" :key="i" data-reveal :class="i % 2 === 1 ? 'md:mt-24' : ''" :style="{ '--d': `${(i % 2) * 80}ms` }">
                            <article class="ie-project group">
                                <component
                                    :is="p.url ? 'a' : 'div'"
                                    :href="p.url || undefined"
                                    :target="p.url ? '_blank' : undefined"
                                    :rel="p.url ? 'noopener noreferrer' : undefined"
                                    :aria-label="p.url ? `Open ${p.title}` : undefined"
                                    class="ie-media relative block aspect-[1.35] overflow-hidden bg-[var(--paper-3)]"
                                >
                                    <img v-if="p.image" :src="p.image" :alt="p.title" class="h-full w-full object-cover" loading="lazy" />
                                    <div v-else class="ie-serif flex h-full w-full items-end p-8 text-7xl text-[var(--accent)]">{{ p.title.charAt(0) }}</div>
                                    <span v-if="p.url" class="ie-go" aria-hidden="true"><ArrowUpRight class="h-5 w-5" /></span>
                                </component>

                                <div class="mt-6">
                                    <p v-if="p.category" class="text-sm font-semibold text-[var(--accent)]">{{ p.category }}</p>
                                    <h3 class="ie-serif mt-1 text-3xl leading-tight sm:text-4xl"><span class="ie-underline">{{ p.title }}</span></h3>
                                    <p v-if="p.description" class="mt-3 max-w-md leading-[1.7] text-[var(--muted)]">{{ p.description }}</p>
                                    <a v-if="p.repo" :href="p.repo" target="_blank" rel="noopener noreferrer" class="ie-ulink mt-4 inline-block text-sm font-semibold">Source code</a>
                                </div>
                            </article>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- ============================== CONTACT ============================== -->
            <section v-if="showContact" id="contact" class="scroll-mt-4 border-t border-[var(--line)] bg-[var(--paper-2)] px-6 py-28 sm:py-36 lg:px-10">
                <div class="mx-auto grid max-w-7xl gap-16 lg:grid-cols-12 lg:gap-14">
                    <div class="lg:col-span-6">
                        <div data-reveal>
                            <p v-if="text('contact', 'contact_subtitle')" class="ie-kicker">{{ text('contact', 'contact_subtitle') }}</p>
                            <h2 v-if="text('contact', 'contact_title')" class="ie-serif ie-contact-title mt-5 [overflow-wrap:anywhere]">{{ text('contact', 'contact_title') }}</h2>
                            <p v-if="text('contact', 'contact_description')" class="mt-6 max-w-lg whitespace-pre-line text-lg leading-[1.75] text-[var(--muted)]">
                                {{ text('contact', 'contact_description') }}
                            </p>
                        </div>

                        <div v-if="email || phone || location" data-reveal class="mt-10 space-y-5 border-t border-[var(--line)] pt-8">
                            <a v-if="email" :href="`mailto:${email}`" class="ie-bigmail ie-serif group inline-flex max-w-full items-center gap-3 break-all text-3xl text-[var(--accent)] sm:text-4xl">
                                <span class="ie-ulink-flip">{{ email }}</span><ArrowUpRight class="ie-arrow h-6 w-6 shrink-0" />
                            </a>
                            <div class="flex flex-wrap gap-x-8 gap-y-3 text-[var(--muted)]">
                                <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="ie-ulink inline-flex items-center gap-2 font-medium hover:text-[var(--accent)]"><Phone class="h-4 w-4" />{{ phone }}</a>
                                <span v-if="location" class="inline-flex items-center gap-2 font-medium"><MapPin class="h-4 w-4" />{{ location }}</span>
                            </div>
                        </div>

                        <ul v-if="contactSocials.length" data-reveal class="mt-8 flex flex-wrap gap-x-6 gap-y-3">
                            <li v-for="(s, i) in contactSocials" :key="i">
                                <a :href="s.url" target="_blank" rel="noopener noreferrer" class="ie-ulink text-sm font-semibold text-[var(--muted)] hover:text-[var(--accent)]">{{ s.label }}</a>
                            </li>
                        </ul>
                    </div>

                    <div v-if="formReady" class="lg:col-span-6">
                        <div data-reveal style="--d: 120ms">
                            <form :action="gf.url && gf.name && gf.email && gf.message ? formAction : undefined" method="POST" target="ie-gform-frame" class="ie-form p-7 sm:p-10" @submit="gf.url && gf.name && gf.email && gf.message ? onSubmit($event) : handleContactFormFallback($event, text('contact', 'contact_email'))">
                                <p class="ie-serif text-3xl">Send a message</p>
                                <div class="mt-8 space-y-7">
                                    <label class="block">
                                        <span class="ie-label">Name</span>
                                        <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="ie-input" />
                                    </label>
                                    <label class="block">
                                        <span class="ie-label">Email</span>
                                        <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="ie-input" />
                                    </label>
                                    <label class="block">
                                        <span class="ie-label">Message</span>
                                        <textarea :name="`entry.${gf.message}`" rows="4" required class="ie-input resize-y" />
                                    </label>
                                </div>
                                <button type="submit" class="ie-btn ie-btn-accent ie-magnet mt-9 w-full justify-center" :disabled="sending" @mousemove="onMagnet" @mouseleave="onMagnetOut">
                                    {{ sending ? 'Sending…' : 'Send message' }}<Send class="ie-arrow h-4 w-4" />
                                </button>
                                <p v-if="sent" role="status" class="ie-sent mt-5 flex items-center gap-3 text-sm font-semibold">
                                    <span class="ie-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>Thanks — your message was submitted.
                                </p>
                            </form>
                            <iframe name="ie-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer v-if="logoText" class="flex flex-col justify-between gap-3 px-6 py-8 text-sm text-[var(--muted)] sm:flex-row lg:px-10">
            <span>&copy; {{ year }} {{ logoText }}</span>
            <a href="#home" class="ie-ulink inline-flex items-center gap-1 font-semibold hover:text-[var(--accent)]" @click="onNavClick($event, '#home')">Back to top<ArrowUpRight class="h-4 w-4 -rotate-45" /></a>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap');

/* ---------- Foundations ---------- */
.ie-root {
    --paper: #fbfaf7;
    --paper-2: #f3f1ea;
    --paper-3: #e9e6dc;
    --tint: #edf0fc;
    --ink: #20211f;
    --muted: #62635d;
    --line: #20211f24;
    --accent: #244fce;
    background: var(--paper);
    color: var(--ink);
    font-family: 'Hanken Grotesk', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
}
.ie-serif {
    font-family: 'Instrument Serif', 'Iowan Old Style', Georgia, 'Times New Roman', serif;
    font-weight: 400;
    letter-spacing: -0.015em;
}
.ie-h2 {
    font-size: clamp(2.75rem, 6.5vw, 5rem);
    line-height: 1;
    letter-spacing: -0.025em;
    text-wrap: balance;
}
.ie-contact-title {
    font-size: clamp(3rem, 7.5vw, 6rem);
    line-height: 0.96;
    letter-spacing: -0.03em;
}
.ie-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
}
/* Soft light that trails the pointer (fine pointers only) */
.ie-glow {
    pointer-events: none;
    position: fixed;
    inset: 0;
    z-index: 5;
    background: radial-gradient(560px circle at var(--gx, 72%) var(--gy, 18%), #244fce12, transparent 65%);
}
.ie-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--accent);
}
.ie-kicker::before {
    content: '';
    width: 2rem;
    height: 1.5px;
    background: currentColor;
    transform-origin: left;
    transition: transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) 0.2s;
}
.ie-js [data-reveal]:not(.is-in) .ie-kicker::before {
    transform: scaleX(0);
}
.ie-rule {
    height: 1px;
    background: var(--line);
    transform-origin: left;
    transition: transform 1.2s cubic-bezier(0.6, 0, 0.2, 1);
}
.ie-js .ie-rule[data-reveal] {
    opacity: 1;
    transform: scaleX(0);
}
.ie-js .ie-rule[data-reveal].is-in {
    transform: scaleX(1);
}

/* ---------- Navbar ---------- */
.ie-navwrap {
    transition: transform 0.55s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ie-navwrap.is-hidden {
    transform: translateY(-130%);
}
.ie-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 2px;
    background: var(--accent);
    transform-origin: left;
    pointer-events: none;
    z-index: 80;
}
.ie-nav {
    border: 1px solid transparent;
    border-radius: 999px;
    background: color-mix(in srgb, var(--paper) 55%, transparent);
    backdrop-filter: blur(16px) saturate(1.3);
    -webkit-backdrop-filter: blur(16px) saturate(1.3);
    transition:
        border-color 0.35s ease,
        background-color 0.35s ease,
        box-shadow 0.35s ease;
}
.ie-nav.is-scrolled {
    border-color: var(--line);
    background: color-mix(in srgb, var(--paper) 85%, transparent);
    box-shadow: 0 14px 34px -18px color-mix(in srgb, var(--ink) 45%, transparent);
}
.ie-mark {
    display: inline-flex;
    height: 2.1rem;
    width: 2.1rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--accent);
    color: #fff;
    font-family: 'Instrument Serif', Georgia, serif;
    font-size: 1.15rem;
    transition: transform 0.5s cubic-bezier(0.3, 1.5, 0.4, 1);
}
a:hover > .ie-mark {
    transform: rotate(-12deg) scale(1.08);
}
.ie-navlink {
    display: inline-block;
    border-radius: 999px;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--muted);
    transition:
        color 0.25s ease,
        background-color 0.25s ease;
}
.ie-navlink:hover {
    color: var(--ink);
}
.ie-navlink[data-active='true'] {
    color: var(--ink);
    background: var(--paper-2);
}

/* ---------- Buttons & links ---------- */
.ie-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    border-radius: 999px;
    border: 1px solid transparent;
    padding: 0.95rem 1.6rem;
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.2;
    cursor: pointer;
    background-repeat: no-repeat;
    background-position: left center;
    background-size: 0% 100%;
    transition:
        background-size 0.55s cubic-bezier(0.2, 0.7, 0.2, 1),
        color 0.3s ease,
        box-shadow 0.35s ease,
        border-color 0.3s ease,
        transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ie-btn:hover {
    background-size: 100% 100%;
}
.ie-btn:active {
    scale: 0.98;
}
.ie-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.ie-btn-accent {
    background-color: var(--accent);
    background-image: linear-gradient(var(--ink), var(--ink));
    color: #fff;
    box-shadow: 0 10px 24px -12px var(--accent);
}
.ie-btn-ink {
    background-color: var(--ink);
    background-image: linear-gradient(var(--accent), var(--accent));
    color: var(--paper);
}
.ie-btn-ghost {
    background-color: transparent;
    background-image: linear-gradient(var(--paper-2), var(--paper-2));
    border-color: var(--line);
    color: var(--ink);
}
.ie-btn-ghost:hover {
    border-color: var(--ink);
}
.ie-arrow {
    transition: transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ie-btn:hover .ie-arrow,
.ie-bigmail:hover .ie-arrow {
    transform: translate(3px, -3px);
}
.ie-ulink {
    background: linear-gradient(currentColor, currentColor) left 100% / 0% 1.5px no-repeat;
    padding-bottom: 3px;
    transition:
        background-size 0.45s cubic-bezier(0.2, 0.7, 0.2, 1),
        color 0.25s ease;
}
.ie-ulink:hover {
    background-size: 100% 1.5px;
}
.ie-ulink-flip {
    background: linear-gradient(currentColor, currentColor) left 100% / 100% 1.5px no-repeat;
    padding-bottom: 4px;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ie-bigmail:hover .ie-ulink-flip {
    background-size: 0% 1.5px;
    background-position: right 100%;
}
.ie-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: color-mix(in srgb, var(--paper) 60%, white);
    padding: 0.45rem 1rem 0.45rem 0.85rem;
    font-size: 0.9rem;
    font-weight: 600;
}
.ie-pulse {
    position: relative;
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 999px;
    background: #22a55a;
}
.ie-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: inherit;
    animation: ie-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.ie-tick {
    display: inline-flex;
    height: 1.5rem;
    width: 1.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--accent);
    color: #fff;
}

/* ---------- Hero ---------- */
.ie-hero-name {
    font-size: clamp(3.75rem, 11vw, 9.25rem);
    line-height: 0.9;
    letter-spacing: -0.03em;
    overflow-wrap: anywhere;
}
.ie-mask {
    display: inline-block;
    overflow: hidden;
    vertical-align: top;
    padding-bottom: 0.14em;
    margin-bottom: -0.14em;
    margin-right: 0.2em;
}
.ie-word {
    display: inline-block;
}
.ie-js .ie-word {
    transform: translateY(112%);
    animation: ie-rise 1.1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: calc(0.15s + var(--i, 0) * 0.11s);
}
.ie-outline {
    position: absolute;
    inset: 0;
    border: 1px solid color-mix(in srgb, var(--accent) 55%, transparent);
    transform: translate(1.1rem, -1.1rem);
    transition: transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ie-portrait:hover .ie-outline {
    transform: translate(0.4rem, -0.4rem);
}
.ie-js .ie-portrait-img {
    animation: ie-wipe-up 1.4s cubic-bezier(0.7, 0, 0.2, 1) 0.25s backwards;
}
.ie-hero-img {
    transform: translate3d(0, calc(var(--sy, 0) * 0.04px), 0) scale(1.1);
    transition: filter 0.6s ease;
    will-change: transform;
}
.ie-portrait-img:hover .ie-hero-img {
    filter: saturate(1.08) contrast(1.03);
}
.ie-cue-line {
    position: relative;
    display: block;
    height: 2.5rem;
    width: 1px;
    overflow: hidden;
    background: var(--line);
}
.ie-cue-line::after {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--accent);
    animation: ie-cue 2.4s cubic-bezier(0.6, 0, 0.2, 1) infinite;
}

/* ---------- About ---------- */
.ie-lead {
    font-size: clamp(1.9rem, 3.6vw, 3.4rem);
    line-height: 1.22;
    letter-spacing: -0.02em;
}
.ie-media {
    isolation: isolate;
}
.ie-media img {
    transition: transform 1s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ie-media:hover img,
.ie-project:hover .ie-media img {
    transform: scale(1.05);
}
.ie-js [data-reveal].is-in .ie-media {
    animation: ie-wipe-up 1.3s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}
.ie-js [data-reveal].is-in .ie-media img {
    animation: ie-settle 1.7s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s backwards;
}
.ie-hl {
    transition: padding-left 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ie-hl:hover {
    padding-left: 0.6rem;
}
.ie-js [data-reveal]:not(.is-in) .ie-hl {
    opacity: 0;
}
.ie-js [data-reveal].is-in .ie-hl {
    animation: ie-slide-x 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--k, 0) * 90ms + 150ms) backwards;
}
.ie-js [data-reveal].is-in .ie-hl .ie-tick {
    animation: ie-tick 0.6s cubic-bezier(0.3, 1.6, 0.4, 1) calc(var(--k, 0) * 90ms + 450ms) backwards;
}

/* ---------- Journey ---------- */
.ie-rowwrap {
    position: relative;
}
.ie-rowwrap::after {
    content: '';
    position: absolute;
    inset: auto 0 0 0;
    height: 1px;
    background: var(--line);
    transform-origin: left;
    transform: scaleX(0);
    transition: transform 1.1s cubic-bezier(0.6, 0, 0.2, 1) calc(var(--d, 0ms) + 0.15s);
}
.ie-rowwrap.is-in::after {
    transform: scaleX(1);
}
.ie-row {
    position: relative;
    transition:
        padding-inline 0.45s cubic-bezier(0.2, 0.7, 0.2, 1),
        background-color 0.4s ease;
}
.ie-row:hover {
    padding-inline: 1.25rem;
    background: color-mix(in srgb, var(--paper-2) 70%, transparent);
}
.ie-row-title {
    transition:
        font-style 0.2s ease,
        color 0.3s ease;
}
.ie-row:hover .ie-row-title {
    font-style: italic;
    color: var(--accent);
}

/* ---------- Skills ---------- */
.ie-skill {
    padding-block: 2rem;
    border-bottom: 1px solid var(--line);
    transition: padding-left 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ie-skill:hover {
    padding-left: 1rem;
}
.ie-skill-name {
    transition: color 0.3s ease;
}
.ie-skill:hover .ie-skill-name {
    color: var(--accent);
    font-style: italic;
}
.ie-ticks {
    display: flex;
    align-items: flex-end;
    gap: 5px;
    height: 36px;
    margin-top: 1.5rem;
}
.ie-tk {
    flex: 1;
    min-width: 2px;
    max-width: 11px;
    height: 22px;
    border-radius: 2px;
    background: color-mix(in srgb, var(--ink) 16%, transparent);
    transform-origin: bottom;
    transition:
        background-color 0.5s ease calc(var(--d, 0ms) + 0.3s + var(--t, 0) * 32ms),
        transform 0.3s cubic-bezier(0.3, 1.4, 0.4, 1);
}
.ie-tk.on {
    background: var(--accent);
}
.ie-tk.end {
    transform: scaleY(1.55);
}
/* hover wave: the tick under the pointer rises, its neighbours follow */
.ie-tk:hover {
    transform: scaleY(1.65);
}
.ie-tk:hover + .ie-tk,
.ie-tk:has(+ .ie-tk:hover) {
    transform: scaleY(1.3);
}
.ie-js [data-reveal]:not(.is-in) .ie-tk.on {
    background: color-mix(in srgb, var(--ink) 16%, transparent);
}
.ie-js [data-reveal].is-in .ie-tk {
    animation: ie-tk-in 0.6s cubic-bezier(0.3, 1.5, 0.4, 1) calc(var(--d, 0ms) + 0.2s + var(--t, 0) * 32ms) backwards;
}

@property --n {
    syntax: '<integer>';
    inherits: false;
    initial-value: 0;
}
.ie-count {
    --n: var(--p, 0);
    counter-reset: n var(--n);
    font-variant-numeric: tabular-nums;
}
.ie-count::after {
    content: counter(n) '%';
}
.ie-js [data-reveal] .ie-count {
    --n: 0;
}
.ie-js [data-reveal].is-in .ie-count {
    animation: ie-count 1.6s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s) forwards;
}

/* ---------- Hero backdrop: concentric hairlines that slowly breathe ---------- */
.ie-rings {
    position: absolute;
    right: -14rem;
    top: 50%;
    z-index: -1;
    height: 52rem;
    width: 52rem;
    transform: translateY(-50%);
    pointer-events: none;
}
.ie-rings span {
    position: absolute;
    border-radius: 999px;
    border: 1px solid var(--line);
    animation: ie-breathe 9s ease-in-out infinite;
}
.ie-rings span:nth-child(1) {
    inset: 0;
}
.ie-rings span:nth-child(2) {
    inset: 14%;
    animation-delay: -3s;
}
.ie-rings span:nth-child(3) {
    inset: 28%;
    border-color: color-mix(in srgb, var(--accent) 35%, transparent);
    animation-delay: -6s;
}

/* ---------- Projects ---------- */
.ie-go {
    position: absolute;
    right: 1rem;
    top: 1rem;
    display: inline-flex;
    height: 3rem;
    width: 3rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--paper);
    color: var(--ink);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem) scale(0.9);
    transition:
        opacity 0.35s ease,
        transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1),
        background-color 0.3s ease,
        color 0.3s ease;
}
.ie-project:hover .ie-go,
.ie-media:focus-visible .ie-go {
    opacity: 1;
    transform: none;
}
.ie-go:hover {
    background: var(--accent);
    color: #fff;
}
.ie-underline {
    background: linear-gradient(currentColor, currentColor) left 94% / 0% 1.5px no-repeat;
    transition: background-size 0.55s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ie-project:hover .ie-underline {
    background-size: 100% 1.5px;
}

/* ---------- Contact form ---------- */
.ie-form {
    border-radius: 1.75rem;
    border: 1px solid var(--line);
    background: color-mix(in srgb, var(--paper) 40%, white);
    box-shadow: 0 40px 70px -48px color-mix(in srgb, var(--ink) 60%, transparent);
}
.ie-label {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--muted);
    transition: color 0.25s ease;
}
label:focus-within .ie-label {
    color: var(--accent);
}
.ie-input {
    width: 100%;
    border: 0;
    border-bottom: 1px solid var(--line);
    background: linear-gradient(var(--accent), var(--accent)) left 100% / 0% 2px no-repeat;
    padding: 0.55rem 0;
    font: inherit;
    font-size: 1.05rem;
    color: var(--ink);
    transition:
        background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.25s ease;
}
.ie-input:hover {
    border-bottom-color: color-mix(in srgb, var(--ink) 45%, transparent);
}
.ie-input:focus {
    outline: none;
    background-size: 100% 2px;
}
.ie-sent {
    animation: ie-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.ie-js [data-reveal] {
    opacity: 0;
    transform: translateY(24px);
    transition:
        opacity 0.9s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.ie-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}
.ie-js.ie-root {
    animation: ie-enter 0.8s ease both;
}

@keyframes ie-enter {
    from {
        opacity: 0;
    }
}
@keyframes ie-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes ie-wipe-up {
    from {
        clip-path: inset(100% 0 0 0);
    }
}
@keyframes ie-settle {
    from {
        transform: scale(1.25);
    }
}
@keyframes ie-ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}
@keyframes ie-cue {
    0% {
        transform: translateY(-100%);
    }
    60%,
    100% {
        transform: translateY(100%);
    }
}
@keyframes ie-shimmer {
    to {
        background-position: -150% 0;
    }
}
@keyframes ie-knob {
    from {
        transform: translate(50%, -50%) scale(0);
    }
}
@keyframes ie-count {
    to {
        --n: var(--p);
    }
}
@keyframes ie-slide-x {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
}
@keyframes ie-tick {
    from {
        transform: scale(0) rotate(-90deg);
    }
}
@keyframes ie-tk-in {
    from {
        transform: scaleY(0);
    }
}
@keyframes ie-breathe {
    50% {
        transform: scale(1.04);
    }
}
@keyframes ie-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}

@media (prefers-reduced-motion: reduce) {
    .ie-root *,
    .ie-root *::before,
    .ie-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .ie-js .ie-word,
    .ie-js [data-reveal],
    .ie-js [data-reveal]:not(.is-in) .ie-hl,
    .ie-js .ie-rule[data-reveal],
    .ie-rowwrap::after {
        transform: scaleX(1);
    }
    .ie-js [data-reveal]:not(.is-in) .ie-tk.on {
        background: var(--accent);
    }
    .ie-js [data-reveal] .ie-count {
        --n: var(--p, 0);
    }
    .ie-hero-img {
        transform: none;
    }
    .ie-navwrap.is-hidden {
        transform: none;
    }
}
</style>
