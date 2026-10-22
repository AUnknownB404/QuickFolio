<script setup lang="ts">
/**
 * Ultramarine Editorial — quickFolio template
 * Slug suggestion: `ultramarine-editorial`
 *
 * A calm, editorial redesign of the cobalt template: paper-white / deep-navy surfaces,
 * one ultramarine accent, an arched portrait, hairline structure instead of heavy borders,
 * and a single orchestrated load sequence plus restrained hover / scroll motion.
 * Light + dark mode through Tailwind `dark:` (palette lives in CSS variables on the root).
 *
 * Every visible value comes from the seeded field keys. Nothing is hardcoded except
 * small UI labels (nav / button / form chrome) and structural fallbacks.
 */
import { resolvePortfolioMediaUrl } from '@/lib/media';
import {
    ArrowUpRight,
    AtSign,
    Briefcase,
    Camera,
    Check,
    Code,
    Database,
    Dribbble,
    Download,
    Facebook,
    Globe,
    Github,
    Instagram,
    Layers,
    Link2,
    Linkedin,
    Mail,
    MapPin,
    Megaphone,
    Monitor,
    Palette,
    PenTool,
    Phone,
    Rocket,
    Send,
    Server,
    Smartphone,
    Sparkles,
    Terminal,
    Youtube,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, type Component } from 'vue';

type Dict = Record<string, any>;

interface GoogleFormConfig {
    url?: string | null;
    nameEntry?: string | null;
    emailEntry?: string | null;
    messageEntry?: string | null;
}

const props = withDefaults(
    defineProps<{
        /**
         * Content keyed by section key:
         *   { navbar: { logo_text: '…' }, header: { hero_name: '…' }, … }
         * An array of `{ section_key, is_enabled, field_values: [{ field_key | field_definition.field_key, value }] }`
         * is also accepted, so the Inertia payload can be passed through unchanged.
         */
        content?: Dict | any[];
        sections?: Dict | any[];
        designSettings?: Dict | null;
        /** Optional per-section switches, e.g. { skills: false } */
        enabled?: Record<string, boolean>;
        /** Portfolio-level Google Form settings (camelCase) */
        googleForm?: GoogleFormConfig | null;
        /** Alternative: pass the portfolio and the `google_form_*` columns are read from it */
        portfolio?: Dict | null;
    }>(),
    { content: undefined, sections: () => ({}), designSettings: null, enabled: () => ({}), googleForm: null, portfolio: null },
);

/* ------------------------------------------------------------------ */
/* Data access                                                         */
/* ------------------------------------------------------------------ */

const normalized = computed(() => {
    const src: any = props.content ?? props.sections;
    const content: Record<string, Dict> = {};
    const disabled = new Set<string>();

    if (Array.isArray(src)) {
        for (const s of src) {
            const key = s?.section_key ?? s?.key;
            if (!key) continue;
            if (s.is_enabled === false || s.is_enabled === 0) disabled.add(key);
            const bag: Dict = { ...(s.values ?? s.content ?? {}) };
            const rows = s.field_values ?? s.fieldValues ?? s.fields ?? [];
            for (const fv of Array.isArray(rows) ? rows : []) {
                const fk = fv?.field_key ?? fv?.field_definition?.field_key ?? fv?.fieldDefinition?.field_key ?? fv?.key;
                if (fk) bag[fk] = fv.value;
            }
            content[key] = bag;
        }
    } else if (src && typeof src === 'object') {
        for (const key of Object.keys(src)) {
            const s = src[key];
            if (s?.is_enabled === false || s?.is_enabled === 0) disabled.add(key);
            content[key] = s?.values ?? s?.content ?? s ?? {};
        }
    }
    return { content, disabledSections: disabled };
});
const data = computed(() => normalized.value.content);
const disabledSections = computed(() => normalized.value.disabledSections);

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
            if (typeof i === 'string') return { label: hostOf(i), platform: '', url: clean(i) ?? '' };
            const url = clean(i?.url ?? i?.href) ?? '';
            const platform = clean(i?.platform ?? i?.label ?? i?.name) ?? '';
            const platformKey = platform.toLowerCase().replace(/[^a-z]/g, '');
            const label = clean(i?.label ?? i?.name) ?? socialPlatformLabels[platformKey] ?? hostOf(url);
            return { label, platform, url };
        })
        .filter((i) => i.url);

// Navbar
const logoText = computed(() => text('navbar', 'logo_text') ?? text('header', 'hero_name'));
const logoImage = computed(() => (flag('navbar', 'show_logo', true) ? media('navbar', 'logo_image') ?? '/logo.svg' : undefined));
const navLinks = computed(
    () =>
        [
            { id: 'about', label: 'About' },
            { id: 'journey', label: 'Journey' },
            { id: 'skills', label: 'Skills' },
            { id: 'projects', label: 'Work' },
            { id: 'services', label: 'Services' },
        ].filter((l) => has(l.id)),
);

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

// About
const highlights = computed(() =>
    arr('about', 'about_highlights')
        .map((h) =>
            typeof h === 'string'
                ? { title: clean(h) ?? '', description: undefined as string | undefined }
                : { title: clean(h?.title ?? h?.label ?? h?.text ?? h?.name) ?? '', description: clean(h?.description) },
        )
        .filter((h) => h.title),
);

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
            const name = clean(typeof s === 'string' ? s : (s?.name ?? s?.title ?? s?.label));
            const lv = typeof s === 'object' ? clean(s?.level ?? s?.percent) : undefined;
            let pct: number | null = null;
            let label: string | undefined;
            if (lv) {
                if (/^[\d.]+\s*%?$/.test(lv)) {
                    const n = parseFloat(lv);
                    pct = Math.max(0, Math.min(100, n <= 5 ? n * 20 : n));
                } else label = lv;
            }
            return { name: name ?? '', icon: typeof s === 'object' ? clean(s?.icon) : undefined, pct, label };
        })
        .filter((s) => s.name),
);

// Projects
const projects = computed(() =>
    arr('projects', 'projects_list')
        .map((p) => {
            const tagsRaw = p?.tags;
            const tags: string[] = Array.isArray(tagsRaw)
                ? (tagsRaw.map((t: any) => clean(typeof t === 'string' ? t : (t?.name ?? t?.label))).filter(Boolean) as string[])
                : typeof tagsRaw === 'string'
                  ? tagsRaw
                        .split(',')
                        .map((t) => t.trim())
                        .filter(Boolean)
                  : [];
            const imageValue = typeof p?.image === 'string' ? clean(p.image) : clean(p?.image?.url ?? p?.image?.path);
            const category = clean(p?.category);
            return {
                title: clean(p?.title ?? p?.name) ?? '',
                description: clean(p?.description),
                image: imageValue ? resolvePortfolioMediaUrl(imageValue) : undefined,
                repo: clean(p?.repo_url),
                live: clean(p?.live_url ?? p?.url),
                tags: category && !tags.includes(category) ? [...tags, category] : tags,
            };
        })
        .filter((p) => p.title),
);
const featureFirst = computed(() => flag('projects', 'projects_show_featured', false));

// Services
const services = computed(() =>
    arr('services', 'services_list')
        .map((s) => ({
            title: clean(typeof s === 'string' ? s : (s?.title ?? s?.name ?? s?.value)) ?? '',
            description: typeof s === 'object' ? clean(s?.description) : undefined,
            icon: typeof s === 'object' ? clean(s?.icon) : undefined,
        }))
        .filter((s) => s.title),
);

// Contact
const contactSocials = computed(() => {
    const own = normSocials('contact', 'contact_social_links');
    return own.length ? own : heroSocials.value;
});
const email = computed(() => text('contact', 'contact_email'));
const phone = computed(() => text('contact', 'contact_phone'));
const location = computed(() => text('contact', 'contact_location'));

/* ------------------------------------------------------------------ */
/* Icons                                                               */
/* ------------------------------------------------------------------ */

const iconMap: Record<string, Component> = {
    code: Code,
    terminal: Terminal,
    globe: Globe,
    web: Globe,
    layers: Layers,
    stack: Layers,
    palette: Palette,
    design: Palette,
    pen: PenTool,
    pentool: PenTool,
    smartphone: Smartphone,
    mobile: Smartphone,
    server: Server,
    backend: Server,
    database: Database,
    db: Database,
    rocket: Rocket,
    briefcase: Briefcase,
    camera: Camera,
    megaphone: Megaphone,
    marketing: Megaphone,
    monitor: Monitor,
    mail: Mail,
    link: Link2,
    sparkles: Sparkles,
};
const iconFor = (name?: string): Component | null => (name ? (iconMap[name.toLowerCase().replace(/[^a-z]/g, '')] ?? null) : null);
const isGlyph = (name?: string) => !!name && !iconFor(name) && [...name].length <= 3; // emoji / short glyph
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
};
const socialIconMap: Record<string, Component> = {
    atsign: AtSign,
    behance: Briefcase,
    dribbble: Dribbble,
    email: Mail,
    facebook: Facebook,
    github: Github,
    instagram: Instagram,
    linkedin: Linkedin,
    website: Globe,
    x: AtSign,
    youtube: Youtube,
};
const socialIconFor = (platform?: string): Component =>
    (platform ? socialIconMap[platform.toLowerCase().replace(/[^a-z]/g, '')] : undefined) ?? Globe;

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
    () => flag('contact', 'contact_form_enabled', true) && !!(gf.value.url && gf.value.name && gf.value.email && gf.value.message),
);
const formAction = computed(() => (gf.value.url ?? '').replace(/\/viewform.*$/, '/formResponse'));
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
    }, 650);
};

/* ------------------------------------------------------------------ */
/* Behaviour: scroll progress, active section, reveal                  */
/* ------------------------------------------------------------------ */

const rootEl = ref<HTMLElement | null>(null);
const progressEl = ref<HTMLElement | null>(null);
const navEl = ref<HTMLElement | null>(null);
const ready = ref(false);
const activeId = ref('header');
let observer: IntersectionObserver | null = null;
let sectionObserver: IntersectionObserver | null = null;

const sectionIds = ['header', 'about', 'journey', 'skills', 'projects', 'services', 'contact'];

const reduceMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const onNavClick = (e: Event, url?: string) => {
    if (!url || !url.startsWith('#') || url.length < 2) return;
    const el = document.getElementById(url.slice(1));
    if (!el) return;
    e.preventDefault();
    el.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'start' });
};

const onScroll = () => {
    if (scrollFrame !== null) return;
    scrollFrame = window.requestAnimationFrame(() => {
        scrollFrame = null;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        progressEl.value?.style.setProperty('transform', `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`);
        navEl.value?.classList.toggle('is-scrolled', window.scrollY > 16);
    });
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
        { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
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

let scrollFrame: number | null = null;

onMounted(async () => {
    ready.value = true; // reveal styles only apply after mount, so SSR output stays visible
    await nextTick();
    observeReveals();
    observeSections();
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});
onBeforeUnmount(() => {
    observer?.disconnect();
    sectionObserver?.disconnect();
    window.removeEventListener('scroll', onScroll);
    if (scrollFrame !== null) window.cancelAnimationFrame(scrollFrame);
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
        class="nb-root [--paper:#F5F6FA] [--soft:#E9ECF8] [--surface:#FFFFFF] [--ink:#0B1020] [--muted:#0B1020B0] [--line:#0B102021] [--accent:#2B3FF0] [--accent-ink:#FFFFFF] selection:bg-[#FFD84D] selection:text-[#0B1020] dark:[--paper:#0A0E1F] dark:[--soft:#0F1530] dark:[--surface:#131A3A] dark:[--ink:#F1F3FF] dark:[--muted:#F1F3FFB0] dark:[--line:#F1F3FF26] dark:[--accent:#93A4FF] dark:[--accent-ink:#0A0E1F]"
        :class="{ 'nb-js': ready }"
    >
        <!-- ============================ NAVBAR ============================ -->
        <header class="fixed inset-x-0 top-0 z-50 px-3 pt-3 sm:px-6 sm:pt-4">
            <div ref="progressEl" class="nb-progress" aria-hidden="true" />
            <nav ref="navEl" class="nb-nav mx-auto flex h-14 max-w-[1240px] items-center justify-between gap-3 pl-5 pr-2" aria-label="Primary">
                <a v-if="logoImage" href="#header" class="flex min-w-0 items-center gap-2.5" @click="onNavClick($event, '#header')">
                    <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-7 w-auto max-w-[7rem] object-contain" />
                </a>
                <span v-else />

                <ul v-if="navLinks.length" class="hidden items-center gap-1 md:flex">
                    <li v-for="l in navLinks" :key="l.id">
                        <a :href="`#${l.id}`" class="nb-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a>
                    </li>
                </ul>

                <a v-if="has('contact')" href="#contact" class="nb-btn nb-btn-primary !px-5 !py-2.5 !text-sm" @click="onNavClick($event, '#contact')">
                    Get in touch<ArrowUpRight class="nb-arrow h-4 w-4" />
                </a>
                <span v-else />
            </nav>
        </header>

        <!-- ============================== HERO ============================== -->
        <section v-if="has('header')" id="header" class="relative isolate flex min-h-[100svh] items-center overflow-hidden">
            <span id="home" class="absolute top-0" aria-hidden="true" />
            <img
                v-if="media('header', 'hero_background_image')"
                :src="media('header', 'hero_background_image')"
                alt=""
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-10 dark:opacity-15"
            />

            <div class="mx-auto grid w-full max-w-[1240px] items-center gap-16 px-5 pb-20 pt-36 sm:px-8 lg:grid-cols-12 lg:gap-10 lg:pt-32">
                <div class="lg:col-span-7">
                    <p v-if="text('header', 'hero_greeting')" data-reveal class="nb-chip" style="--d: 0ms">
                        <span class="nb-pulse" aria-hidden="true" />{{ text('header', 'hero_greeting') }}
                    </p>

                    <h1 v-if="heroName" class="nb-display nb-hero-name mt-7">
                        <span class="sr-only">{{ heroName }}</span>
                        <span v-for="(w, i) in heroWords" :key="i" class="nb-mask" aria-hidden="true">
                            <span class="nb-word" :style="{ '--i': i }">{{ w }}</span>
                        </span>
                    </h1>

                    <p v-if="text('header', 'hero_role')" data-reveal class="nb-display mt-6 text-2xl text-[var(--accent)] sm:text-3xl" style="--d: 380ms">
                        {{ text('header', 'hero_role') }}
                    </p>

                    <p
                        v-if="text('header', 'hero_description')"
                        data-reveal
                        class="nb-muted mt-6 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7]"
                        style="--d: 460ms"
                    >
                        {{ text('header', 'hero_description') }}
                    </p>

                    <div
                        v-if="
                            (text('header', 'hero_cta_primary_text') && text('header', 'hero_cta_primary_url')) ||
                            (text('header', 'hero_cta_secondary_text') && text('header', 'hero_cta_secondary_url'))
                        "
                        data-reveal
                        class="mt-9 flex flex-wrap gap-3"
                        style="--d: 540ms"
                    >
                        <a
                            v-if="text('header', 'hero_cta_primary_text') && text('header', 'hero_cta_primary_url')"
                            :href="text('header', 'hero_cta_primary_url')"
                            class="nb-btn nb-btn-primary"
                            @click="onNavClick($event, text('header', 'hero_cta_primary_url'))"
                            >{{ text('header', 'hero_cta_primary_text') }}<ArrowUpRight class="nb-arrow h-4 w-4"
                        /></a>
                        <a
                            v-if="text('header', 'hero_cta_secondary_text') && text('header', 'hero_cta_secondary_url')"
                            :href="text('header', 'hero_cta_secondary_url')"
                            class="nb-btn nb-btn-ghost"
                            @click="onNavClick($event, text('header', 'hero_cta_secondary_url'))"
                            >{{ text('header', 'hero_cta_secondary_text') }}</a
                        >
                    </div>

                    <ul v-if="heroSocials.length" data-reveal class="mt-9 flex flex-wrap gap-2" style="--d: 620ms">
                        <li v-for="(s, i) in heroSocials" :key="i">
                            <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="nb-icon-btn">
                                <component :is="socialIconFor(s.platform)" class="h-[18px] w-[18px]" />
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="lg:col-span-5">
                    <figure data-reveal class="nb-portrait relative mx-auto aspect-square w-[74%] max-w-[23rem] sm:w-full" style="--d: 250ms">
                        <div class="nb-ring" aria-hidden="true" />
                        <div class="nb-ring nb-ring-2" aria-hidden="true" />
                        <div class="nb-disc" aria-hidden="true" />
                        <div class="nb-photo group">
                            <img
                                v-if="media('header', 'hero_image')"
                                :src="media('header', 'hero_image')"
                                :alt="heroName ?? ''"
                                class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.06]"
                            />
                            <span v-else-if="heroInitials" class="nb-display flex h-full w-full items-center justify-center bg-[var(--accent)] text-[8rem] text-[var(--accent-ink)]">{{
                                heroInitials
                            }}</span>
                        </div>
                        <span class="nb-orbit nb-orbit-a" aria-hidden="true"><Sparkles class="h-5 w-5" /></span>
                        <span class="nb-orbit nb-orbit-b" aria-hidden="true"><Code class="h-5 w-5" /></span>
                        <span class="nb-dot" aria-hidden="true" />
                        <figcaption v-if="location" class="nb-badge"><MapPin class="h-4 w-4 text-[var(--accent)]" />{{ location }}</figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- ============================== ABOUT ============================== -->
        <section v-if="has('about')" id="about" class="nb-band scroll-mt-4">
            <div class="mx-auto grid w-full max-w-[1240px] items-center gap-14 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-20">
                <div v-if="media('about', 'about_image')" data-reveal class="lg:col-span-5">
                    <div class="nb-frame mx-auto max-w-md">
                        <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="aspect-[4/5] w-full rounded-[1.75rem] object-cover" loading="lazy" />
                    </div>
                </div>

                <div :class="media('about', 'about_image') ? 'lg:col-span-7' : 'lg:col-span-10'">
                    <div data-reveal>
                        <p v-if="text('about', 'about_subtitle')" class="nb-kicker">{{ text('about', 'about_subtitle') }}</p>
                        <h2 v-if="text('about', 'about_title')" class="nb-display nb-h2 mt-4">{{ text('about', 'about_title') }}</h2>
                        <p v-if="text('about', 'about_description')" class="nb-muted mt-7 max-w-[38rem] whitespace-pre-line text-lg leading-[1.75]">
                            {{ text('about', 'about_description') }}
                        </p>
                    </div>

                    <ul v-if="highlights.length" data-reveal class="mt-10 max-w-[38rem] border-t border-[var(--line)]">
                        <li v-for="(h, i) in highlights" :key="i" :style="{ '--k': i }" class="nb-hl flex items-start gap-4 border-b border-[var(--line)] py-4">
                            <span class="nb-tick mt-0.5"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>
                            <span>
                                <span class="block font-semibold">{{ h.title }}</span>
                                <span v-if="h.description" class="nb-muted mt-1 block text-[0.95rem] leading-relaxed">{{ h.description }}</span>
                            </span>
                        </li>
                    </ul>

                    <div v-if="media('about', 'about_cv_file') || (text('about', 'about_cta_text') && text('about', 'about_cta_url'))" data-reveal class="mt-10 flex flex-wrap gap-3">
                        <a v-if="media('about', 'about_cv_file')" :href="media('about', 'about_cv_file')" download target="_blank" rel="noopener" class="nb-btn nb-btn-primary">
                            <Download class="h-4 w-4" />{{ text('about', 'about_cta_text') ?? 'Download CV' }}
                        </a>
                        <a
                            v-if="text('about', 'about_cta_text') && text('about', 'about_cta_url') && media('about', 'about_cv_file')"
                            :href="text('about', 'about_cta_url')"
                            class="nb-btn nb-btn-ghost"
                            @click="onNavClick($event, text('about', 'about_cta_url'))"
                        >
                            {{ text('about', 'about_cta_text') }}
                        </a>
                        <a
                            v-else-if="text('about', 'about_cta_text') && text('about', 'about_cta_url')"
                            :href="text('about', 'about_cta_url')"
                            class="nb-btn nb-btn-primary"
                            @click="onNavClick($event, text('about', 'about_cta_url'))"
                        >
                            {{ text('about', 'about_cta_text') }}<ArrowUpRight class="nb-arrow h-4 w-4" />
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================== JOURNEY ============================== -->
        <section v-if="has('journey')" id="journey" class="scroll-mt-4">
            <div class="mx-auto grid w-full max-w-[1240px] gap-12 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-20">
                <div class="lg:col-span-4">
                    <div data-reveal class="lg:sticky lg:top-28">
                        <p v-if="text('journey', 'journey_subtitle')" class="nb-kicker">{{ text('journey', 'journey_subtitle') }}</p>
                        <h2 v-if="text('journey', 'journey_title')" class="nb-display nb-h2 mt-4">{{ text('journey', 'journey_title') }}</h2>
                    </div>
                </div>

                <ol class="nb-timeline lg:col-span-8">
                    <li v-for="(item, index) in journey" :key="index" data-reveal :style="{ '--d': `${(index % 4) * 70}ms` }" class="nb-tl-item">
                        <p v-if="item.period" class="text-sm font-semibold text-[var(--accent)]">{{ item.period }}</p>
                        <h3 v-if="item.title" class="nb-display mt-1.5 text-2xl">{{ item.title }}</h3>
                        <p v-if="item.company" class="mt-1 font-medium">{{ item.company }}</p>
                        <p v-if="item.description" class="nb-muted mt-3 max-w-[38rem] whitespace-pre-line leading-[1.7]">{{ item.description }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ============================== SKILLS ============================== -->
        <section v-if="has('skills')" id="skills" class="nb-band scroll-mt-4">
            <div class="mx-auto grid w-full max-w-[1240px] gap-12 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-20">
                <div class="lg:col-span-4">
                    <div data-reveal class="lg:sticky lg:top-28">
                        <p v-if="text('skills', 'skills_subtitle')" class="nb-kicker">{{ text('skills', 'skills_subtitle') }}</p>
                        <h2 v-if="text('skills', 'skills_title')" class="nb-display nb-h2 mt-4">{{ text('skills', 'skills_title') }}</h2>
                        <p v-if="text('skills', 'skills_description')" class="nb-muted mt-6 whitespace-pre-line leading-[1.7]">{{ text('skills', 'skills_description') }}</p>
                    </div>
                </div>

                <ul class="grid content-start gap-x-12 sm:grid-cols-2 lg:col-span-8">
                    <li v-for="(s, i) in skills" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 50}ms` }" class="nb-skill">
                        <div class="flex items-center gap-3.5">
                            <span v-if="flag('skills', 'skills_show_icon', true)" class="nb-skill-tile">
                                <component :is="iconFor(s.icon)" v-if="iconFor(s.icon)" class="h-[18px] w-[18px]" />
                                <span v-else-if="isGlyph(s.icon)" class="text-lg leading-none" aria-hidden="true">{{ s.icon }}</span>
                                <span v-else class="nb-display text-base leading-none" aria-hidden="true">{{ s.name.charAt(0).toUpperCase() }}</span>
                            </span>
                            <h3 class="font-semibold">{{ s.name }}</h3>
                            <span v-if="flag('skills', 'skills_show_level', true) && s.pct !== null" class="nb-count nb-muted ml-auto text-sm font-semibold" :style="{ '--p': Math.round(s.pct) }" aria-hidden="true" />
                            <span v-else-if="flag('skills', 'skills_show_level', true) && s.label" class="nb-pill ml-auto">{{ s.label }}</span>
                        </div>
                        <div
                            v-if="flag('skills', 'skills_show_level', true) && s.pct !== null"
                            class="nb-track mt-4"
                            role="progressbar"
                            :aria-valuenow="Math.round(s.pct)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="s.name"
                        >
                            <div class="nb-bar" :style="{ '--w': `${s.pct}%` }"><span class="nb-knob" /></div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== PROJECTS ============================== -->
        <section v-if="has('projects')" id="projects" class="relative scroll-mt-4">
            <div class="mx-auto w-full max-w-[1240px] px-5 py-28 sm:px-8">
                <div data-reveal class="mb-16 grid gap-6 lg:grid-cols-12 lg:items-end">
                    <div class="lg:col-span-7">
                        <p v-if="text('projects', 'projects_subtitle')" class="nb-kicker">{{ text('projects', 'projects_subtitle') }}</p>
                        <h2 v-if="text('projects', 'projects_title')" class="nb-display nb-h2 mt-4">{{ text('projects', 'projects_title') }}</h2>
                    </div>
                    <p v-if="text('projects', 'projects_description')" class="nb-muted whitespace-pre-line leading-[1.7] lg:col-span-5">
                        {{ text('projects', 'projects_description') }}
                    </p>
                </div>

                <ul class="grid gap-x-8 gap-y-16 md:grid-cols-2 xl:grid-cols-3">
                    <li
                        v-for="(p, i) in projects"
                        :key="i"
                        data-reveal
                        :class="featureFirst && i === 0 ? 'md:col-span-2 xl:col-span-2' : ''"
                        :style="{ '--d': `${(i % 3) * 80}ms` }"
                    >
                        <article class="nb-project group flex h-full flex-col">
                            <component
                                :is="p.live ? 'a' : 'div'"
                                :href="p.live || undefined"
                                :target="p.live ? '_blank' : undefined"
                                :rel="p.live ? 'noopener noreferrer' : undefined"
                                :aria-label="p.live ? `Open ${p.title}` : undefined"
                                class="nb-media relative block overflow-hidden"
                                :class="featureFirst && i === 0 ? 'aspect-[16/9]' : 'aspect-[4/3]'"
                            >
                                <img v-if="p.image" :src="p.image" :alt="p.title" class="h-full w-full object-cover" loading="lazy" />
                                <div v-else class="nb-display nb-tone flex h-full w-full items-center justify-center text-8xl" :class="`nb-tone-${i % 3}`">
                                    {{ p.title.charAt(0).toUpperCase() }}
                                </div>
                                <span v-if="featureFirst && i === 0" class="nb-flag">Featured</span>
                                <span v-if="p.live" class="nb-go" aria-hidden="true"><ArrowUpRight class="h-5 w-5" /></span>
                            </component>

                            <div class="flex flex-1 flex-col pt-5">
                                <h3 class="nb-display text-2xl leading-tight"><span class="nb-underline">{{ p.title }}</span></h3>
                                <p v-if="p.description" class="nb-muted mt-3 leading-[1.65]">{{ p.description }}</p>
                                <ul v-if="p.tags.length" class="mt-4 flex flex-wrap gap-2">
                                    <li v-for="(t, ti) in p.tags" :key="ti" class="nb-pill">{{ t }}</li>
                                </ul>
                                <div v-if="p.repo" class="mt-auto pt-5">
                                    <a :href="p.repo" target="_blank" rel="noopener noreferrer" class="nb-textlink"><Code class="h-4 w-4" />Source code</a>
                                </div>
                            </div>
                        </article>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== SERVICES ============================== -->
        <section v-if="has('services')" id="services" class="nb-band scroll-mt-4">
            <div class="mx-auto w-full max-w-[1240px] px-5 py-28 sm:px-8">
                <div data-reveal class="mb-14 max-w-3xl">
                    <p v-if="text('services', 'services_subtitle')" class="nb-kicker">{{ text('services', 'services_subtitle') }}</p>
                    <h2 v-if="text('services', 'services_title')" class="nb-display nb-h2 mt-4">{{ text('services', 'services_title') }}</h2>
                </div>

                <ul class="border-t border-[var(--line)]">
                    <li v-for="(s, i) in services" :key="i" data-reveal :style="{ '--d': `${(i % 5) * 70}ms` }">
                        <div class="nb-row group grid items-center gap-4 border-b border-[var(--line)] md:grid-cols-12">
                            <div class="flex items-center gap-5 md:col-span-5">
                                <span class="nb-skill-tile nb-row-tile">
                                    <component :is="iconFor(s.icon) ?? Sparkles" v-if="!isGlyph(s.icon)" class="h-5 w-5" />
                                    <span v-else class="text-xl leading-none" aria-hidden="true">{{ s.icon }}</span>
                                </span>
                                <h3 class="nb-display nb-row-title text-2xl leading-tight sm:text-[1.75rem]">{{ s.title }}</h3>
                            </div>
                            <p v-if="s.description" class="nb-muted leading-[1.65] md:col-span-6 md:text-[1.05rem]">{{ s.description }}</p>
                            <ArrowUpRight class="nb-row-arrow hidden h-6 w-6 justify-self-end md:block" aria-hidden="true" />
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== CONTACT ============================== -->
        <section v-if="has('contact')" id="contact" class="scroll-mt-4 px-3 pb-6 pt-24 sm:px-6">
            <div data-reveal class="nb-contact mx-auto max-w-[1240px]">
                <div class="relative z-10 grid gap-14 p-7 sm:p-12 lg:grid-cols-12 lg:gap-12 lg:p-16">
                    <div class="lg:col-span-7">
                        <div data-reveal>
                            <p v-if="text('contact', 'contact_subtitle')" class="nb-kicker !text-current">{{ text('contact', 'contact_subtitle') }}</p>
                            <h2 v-if="text('contact', 'contact_title')" class="nb-display mt-4 text-[clamp(2.75rem,7.5vw,6rem)] leading-[0.95] [overflow-wrap:anywhere]">
                                {{ text('contact', 'contact_title') }}
                            </h2>
                            <p v-if="text('contact', 'contact_description')" class="mt-6 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7] opacity-80">
                                {{ text('contact', 'contact_description') }}
                            </p>
                        </div>

                        <div v-if="email || phone || location" data-reveal class="mt-10 space-y-5">
                            <a v-if="email" :href="`mailto:${email}`" class="nb-bigmail nb-display group inline-flex max-w-full items-center gap-3 break-all text-2xl sm:text-4xl">
                                <Mail class="h-7 w-7 shrink-0" /><span class="nb-underline-contact">{{ email }}</span>
                                <ArrowUpRight class="nb-arrow h-7 w-7 shrink-0" />
                            </a>
                            <div class="flex flex-wrap gap-x-8 gap-y-3 font-medium">
                                <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="nb-contact-link"><Phone class="h-4 w-4" />{{ phone }}</a>
                                <span v-if="location" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4" />{{ location }}</span>
                            </div>
                        </div>

                        <ul v-if="contactSocials.length" data-reveal class="mt-8 flex flex-wrap gap-2">
                            <li v-for="(s, i) in contactSocials" :key="i">
                                <a :href="s.url" target="_blank" rel="noopener noreferrer" class="nb-social">{{ s.label }}<ArrowUpRight class="nb-arrow h-3.5 w-3.5" /></a>
                            </li>
                        </ul>
                    </div>

                    <div class="lg:col-span-5">
                        <div data-reveal style="--d: 120ms">
                            <form
                                v-if="formReady"
                                :action="formAction"
                                method="POST"
                                target="nb-gform-frame"
                                class="nb-form space-y-5 p-6 sm:p-8"
                                @submit="onSubmit"
                            >
                                <label class="block">
                                    <span class="mb-2 block text-sm font-semibold">Name</span>
                                    <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="nb-input" />
                                </label>
                                <label class="block">
                                    <span class="mb-2 block text-sm font-semibold">Email</span>
                                    <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="nb-input" />
                                </label>
                                <label class="block">
                                    <span class="mb-2 block text-sm font-semibold">Message</span>
                                    <textarea :name="`entry.${gf.message}`" rows="5" required class="nb-input resize-y" />
                                </label>
                                <button type="submit" class="nb-btn nb-btn-primary w-full justify-center" :disabled="sending">
                                    {{ sending ? 'Sending…' : 'Send message' }}<Send class="nb-arrow h-4 w-4" />
                                </button>
                                <p v-if="sent" role="status" class="nb-sent flex items-center gap-2 text-sm font-semibold">
                                    <span class="nb-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>Your message was submitted.
                                </p>
                            </form>
                            <a v-else-if="email" :href="`mailto:${email}`" class="nb-btn nb-btn-ghost w-full justify-center !py-5 text-lg"><Mail class="h-5 w-5" />{{ email }}</a>
                        </div>
                        <iframe name="nb-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                    </div>
                </div>
            </div>

            <footer v-if="heroName || logoText" class="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-2 px-2 py-8 text-sm">
                <span class="nb-muted">&copy; {{ year }} {{ heroName ?? logoText }}</span>
                <a href="#header" class="nb-textlink" @click="onNavClick($event, '#header')">Back to top<ArrowUpRight class="nb-arrow h-4 w-4 -rotate-45" /></a>
            </footer>
        </section>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Figtree:wght@400;500;600;700&display=swap');

/* ---------- Foundations ---------- */
.nb-root {
    background: var(--paper);
    color: var(--ink);
    font-family: 'Figtree', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    transition:
        background-color 0.4s ease,
        color 0.4s ease;
}
.nb-display {
    font-family: 'Bricolage Grotesque', 'Figtree', ui-sans-serif, system-ui, sans-serif;
    font-weight: 600;
    letter-spacing: -0.035em;
}
.nb-muted {
    color: var(--muted);
}
.nb-h2 {
    font-size: clamp(2.25rem, 5vw, 4rem);
    line-height: 1.02;
    letter-spacing: -0.04em;
    text-wrap: balance;
}
.nb-band {
    background: var(--soft);
    border-block: 1px solid var(--line);
}
.nb-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
    border-radius: 0.5rem;
}

/* Section subtitle: sentence case, with a rule that draws in on reveal */
.nb-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--accent);
}
.nb-kicker::before {
    content: '';
    width: 1.75rem;
    height: 2px;
    background: currentColor;
    transform-origin: left;
    transition: transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) 0.2s;
}
.nb-js [data-reveal]:not(.is-in) .nb-kicker::before {
    transform: scaleX(0);
}

/* ---------- Navbar ---------- */
.nb-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 3px;
    background: var(--accent);
    transform-origin: left;
    pointer-events: none;
    z-index: 60;
}
.nb-nav {
    border: 1px solid transparent;
    border-radius: 999px;
    background: color-mix(in srgb, var(--paper) 55%, transparent);
    backdrop-filter: blur(14px) saturate(1.4);
    -webkit-backdrop-filter: blur(14px) saturate(1.4);
    transition:
        border-color 0.35s ease,
        background-color 0.35s ease,
        box-shadow 0.35s ease;
}
.nb-nav.is-scrolled {
    border-color: var(--line);
    background: color-mix(in srgb, var(--paper) 82%, transparent);
    box-shadow: 0 10px 30px -12px color-mix(in srgb, var(--ink) 28%, transparent);
}
.nb-navlink {
    display: inline-block;
    border-radius: 999px;
    padding: 0.5rem 1rem;
    font-size: 0.92rem;
    font-weight: 500;
    color: var(--muted);
    transition:
        color 0.25s ease,
        background-color 0.25s ease;
}
.nb-navlink:hover {
    color: var(--ink);
}
.nb-navlink[data-active='true'] {
    color: var(--ink);
    background: var(--soft);
}

/* ---------- Buttons & small controls ---------- */
.nb-btn {
    position: relative;
    isolation: isolate;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 999px;
    padding: 0.85rem 1.5rem;
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.2;
    cursor: pointer;
    border: 1px solid transparent;
    background-repeat: no-repeat;
    background-position: left center;
    background-size: 0% 100%;
    transition:
        background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1),
        color 0.3s ease,
        transform 0.25s ease,
        box-shadow 0.3s ease,
        border-color 0.3s ease;
}
.nb-btn:hover {
    background-size: 100% 100%;
    transform: translateY(-2px);
}
.nb-btn:active {
    transform: translateY(0) scale(0.98);
}
.nb-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.nb-btn-primary {
    background-color: var(--accent);
    background-image: linear-gradient(var(--ink), var(--ink));
    color: var(--accent-ink);
    box-shadow: 0 8px 22px -10px var(--accent);
}
.nb-btn-primary:hover {
    color: var(--paper);
    box-shadow: 0 14px 28px -12px var(--accent);
}
.nb-btn-ghost {
    background-color: transparent;
    background-image: linear-gradient(var(--soft), var(--soft));
    border-color: var(--line);
    color: var(--ink);
}
.nb-btn-ghost:hover {
    border-color: var(--ink);
}
.nb-arrow {
    transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-btn:hover .nb-arrow,
.nb-bigmail:hover .nb-arrow,
.nb-social:hover .nb-arrow,
.nb-textlink:hover .nb-arrow {
    transform: translate(3px, -3px);
}
.nb-icon-btn {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    transition:
        transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1),
        background-color 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease;
}
.nb-icon-btn:hover {
    transform: translateY(-3px);
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
}
.nb-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.45rem 1rem 0.45rem 0.85rem;
    font-size: 0.9rem;
    font-weight: 600;
}
.nb-pulse {
    position: relative;
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 999px;
    background: #22c55e;
}
.nb-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: inherit;
    animation: nb-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.nb-pill {
    display: inline-block;
    border-radius: 999px;
    border: 1px solid var(--line);
    padding: 0.2rem 0.7rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--muted);
}
.nb-tick {
    display: inline-flex;
    height: 1.5rem;
    width: 1.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-ink);
}
.nb-textlink {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    background: linear-gradient(currentColor, currentColor) left bottom / 0% 1.5px no-repeat;
    transition: background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 2px;
}
.nb-textlink:hover {
    background-size: 100% 1.5px;
}

/* ---------- Hero ---------- */
.nb-hero-name {
    font-size: clamp(3.25rem, 9.5vw, 8rem);
    line-height: 0.94;
    letter-spacing: -0.05em;
    overflow-wrap: anywhere;
}
.nb-mask {
    display: inline-block;
    overflow: hidden;
    vertical-align: top;
    padding-bottom: 0.12em;
    margin-bottom: -0.12em;
    margin-right: 0.22em;
}
.nb-word {
    display: inline-block;
}
.nb-js .nb-word {
    transform: translateY(110%);
    animation: nb-rise 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: calc(0.15s + var(--i, 0) * 0.1s);
}
.nb-portrait {
    --pad: 0px;
}
.nb-ring {
    position: absolute;
    inset: -1.1rem;
    border-radius: 999px;
    border: 1.5px dashed color-mix(in srgb, var(--accent) 70%, transparent);
    animation: nb-spin 48s linear infinite;
}
.nb-ring-2 {
    inset: -2.4rem;
    border: 1px solid var(--line);
    animation-duration: 30s;
    animation-direction: reverse;
}
.nb-ring-2::after {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    height: 0.9rem;
    width: 0.9rem;
    transform: translate(-50%, -50%);
    border-radius: 999px;
    background: var(--accent);
    box-shadow: 0 0 0 5px color-mix(in srgb, var(--accent) 22%, transparent);
}
.nb-disc {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    background: var(--accent);
    transform: translate(1.1rem, 1.1rem);
    transition: transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-portrait:hover .nb-disc {
    transform: translate(0.4rem, 0.4rem);
}
.nb-photo {
    position: absolute;
    inset: 0;
    overflow: hidden;
    border-radius: 999px;
    border: 6px solid var(--paper);
    background: var(--soft);
    box-shadow: 0 24px 50px -24px color-mix(in srgb, var(--ink) 55%, transparent);
}
.nb-orbit {
    position: absolute;
    display: inline-flex;
    height: 3.5rem;
    width: 3.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    color: var(--accent);
    box-shadow: 0 14px 28px -14px color-mix(in srgb, var(--ink) 45%, transparent);
    animation: nb-float 5.5s ease-in-out infinite;
}
.nb-orbit-a {
    top: 4%;
    right: -3%;
}
.nb-orbit-b {
    bottom: 12%;
    left: -7%;
    height: 3rem;
    width: 3rem;
    color: var(--ink);
    animation-duration: 6.5s;
    animation-delay: -2s;
}
.nb-dot {
    position: absolute;
    top: 14%;
    left: 2%;
    height: 1.1rem;
    width: 1.1rem;
    border-radius: 999px;
    background: #ffd84d;
    animation: nb-float 4.5s ease-in-out infinite -1s;
}
.nb-badge {
    position: absolute;
    bottom: -1.1rem;
    left: 50%;
    transform: translateX(-50%);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.55rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    box-shadow: 0 14px 30px -14px color-mix(in srgb, var(--ink) 40%, transparent);
}

/* ---------- About ---------- */
.nb-frame {
    position: relative;
    border-radius: 1.75rem;
    padding: 0.5rem;
    border: 1px solid var(--line);
    background: var(--surface);
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-frame:hover {
    transform: rotate(-1.2deg) scale(1.01);
}
.nb-hl {
    transition:
        padding-left 0.35s cubic-bezier(0.2, 0.7, 0.2, 1),
        background-color 0.3s ease;
}
.nb-hl:hover {
    padding-left: 0.75rem;
}

/* ---------- Journey ---------- */
.nb-timeline {
    position: relative;
    margin-left: 0.5rem;
    border-left: 1.5px solid var(--line);
    padding-left: 2.25rem;
}
.nb-tl-item {
    position: relative;
    padding-bottom: 3rem;
}
.nb-tl-item:last-child {
    padding-bottom: 0;
}
.nb-tl-item::before {
    content: '';
    position: absolute;
    left: -2.25rem;
    top: 0.35rem;
    height: 0.85rem;
    width: 0.85rem;
    transform: translateX(-50%);
    border-radius: 999px;
    background: var(--paper);
    border: 2px solid var(--accent);
    transition:
        background-color 0.5s ease 0.3s,
        box-shadow 0.4s ease;
}
.nb-js .nb-tl-item.is-in::before {
    background: var(--accent);
}
.nb-tl-item:hover::before {
    box-shadow: 0 0 0 6px color-mix(in srgb, var(--accent) 20%, transparent);
}

/* ---------- Skills ---------- */
.nb-skill {
    padding-block: 1.4rem;
    border-bottom: 1px solid var(--line);
}
.nb-skill-tile {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    background: var(--surface);
    border: 1px solid var(--line);
    color: var(--accent);
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-skill:hover .nb-skill-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.nb-track {
    position: relative;
    height: 6px;
    width: 100%;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ink) 9%, transparent);
}
.nb-bar {
    position: relative;
    height: 100%;
    width: var(--w, 0%);
    border-radius: inherit;
    background: linear-gradient(90deg, color-mix(in srgb, var(--accent) 55%, transparent), var(--accent));
}
.nb-bar::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(100deg, transparent 30%, #ffffff88 50%, transparent 70%) 150% 0 / 250% 100% no-repeat;
}
.nb-knob {
    position: absolute;
    right: 0;
    top: 50%;
    height: 14px;
    width: 14px;
    transform: translate(50%, -50%);
    border-radius: 999px;
    border: 3px solid var(--accent);
    background: var(--paper);
}
.nb-knob::after {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: inherit;
    border: 2px solid var(--accent);
    opacity: 0;
}
.nb-js [data-reveal] .nb-bar {
    width: 0;
    transition: width 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s);
}
.nb-js [data-reveal].is-in .nb-bar {
    width: var(--w, 0%);
}
.nb-js [data-reveal].is-in .nb-bar::before {
    animation: nb-shimmer 3.4s ease-in-out calc(var(--d, 0ms) + 1.9s) infinite;
}
.nb-js [data-reveal].is-in .nb-knob {
    animation: nb-knob 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) calc(var(--d, 0ms) + 1.6s) backwards;
}
.nb-js [data-reveal].is-in .nb-knob::after {
    animation: nb-ping 2.4s cubic-bezier(0, 0, 0.2, 1) calc(var(--d, 0ms) + 2.2s) infinite;
}

/* Percentage counts up in sync with the bar */
@property --n {
    syntax: '<integer>';
    inherits: false;
    initial-value: 0;
}
.nb-count {
    --n: var(--p, 0);
    counter-reset: n var(--n);
    font-variant-numeric: tabular-nums;
}
.nb-count::after {
    content: counter(n) '%';
}
.nb-js [data-reveal] .nb-count {
    --n: 0;
}
.nb-js [data-reveal].is-in .nb-count {
    animation: nb-count 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s) forwards;
}

/* ---------- Projects ---------- */
.nb-media {
    border-radius: 1.75rem;
    background: var(--soft);
    border: 1px solid var(--line);
    isolation: isolate;
}
.nb-media img,
.nb-tone {
    transition: transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-project:hover .nb-media img,
.nb-project:hover .nb-tone {
    transform: scale(1.06);
}
.nb-tone-0 {
    background: var(--accent);
    color: var(--accent-ink);
}
.nb-tone-1 {
    background: #ffd84d;
    color: #0b1020;
}
.nb-tone-2 {
    background: var(--ink);
    color: var(--paper);
}
.nb-flag {
    position: absolute;
    left: 1rem;
    top: 1rem;
    border-radius: 999px;
    background: #ffd84d;
    color: #0b1020;
    padding: 0.25rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 700;
}
.nb-go {
    position: absolute;
    right: 1rem;
    top: 1rem;
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--paper);
    color: var(--ink);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem) scale(0.9);
    transition:
        opacity 0.35s ease,
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-project:hover .nb-go,
.nb-media:focus-visible .nb-go {
    opacity: 1;
    transform: none;
}
.nb-underline {
    background: linear-gradient(currentColor, currentColor) left 96% / 0% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-project:hover .nb-underline {
    background-size: 100% 2px;
}

/* ---------- Services ---------- */
.nb-row {
    position: relative;
    padding: 1.75rem 0.5rem;
    transition:
        background-color 0.35s ease,
        padding 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-row:hover {
    background: var(--surface);
    padding-inline: 1.5rem;
}
.nb-row-title {
    transition: color 0.3s ease;
}
.nb-row:hover .nb-row-title {
    color: var(--accent);
}
.nb-row:hover .nb-skill-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.nb-row-arrow {
    color: var(--accent);
    opacity: 0;
    transform: translate(-0.75rem, 0.75rem);
    transition:
        opacity 0.3s ease,
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-row:hover .nb-row-arrow {
    opacity: 1;
    transform: none;
}

/* ---------- Contact ---------- */
.nb-contact {
    border-radius: 2.5rem;
    background: var(--accent);
    color: var(--accent-ink);
    overflow: hidden;
}
.nb-underline-contact {
    background: linear-gradient(currentColor, currentColor) left bottom / 100% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 4px;
}
.nb-bigmail:hover .nb-underline-contact {
    background-size: 0% 2px;
    background-position: right bottom;
}
.nb-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.9;
    transition: opacity 0.2s ease;
}
.nb-contact-link:hover {
    opacity: 1;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.nb-social {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, currentColor 45%, transparent);
    padding: 0.45rem 1rem;
    font-size: 0.9rem;
    font-weight: 600;
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        border-color 0.3s ease,
        transform 0.3s ease;
}
.nb-social:hover {
    background: var(--accent-ink);
    color: var(--accent);
    border-color: var(--accent-ink);
    transform: translateY(-2px);
}
.nb-form {
    border-radius: 1.75rem;
    background: var(--surface);
    color: var(--ink);
    box-shadow: 0 30px 60px -30px #00000066;
}
.nb-input {
    width: 100%;
    border-radius: 0.85rem;
    border: 1px solid var(--line);
    background: var(--paper);
    padding: 0.8rem 1rem;
    font: inherit;
    font-weight: 500;
    color: var(--ink);
    transition:
        border-color 0.25s ease,
        box-shadow 0.25s ease,
        background-color 0.25s ease;
}
.nb-input::placeholder {
    color: var(--muted);
}
.nb-input:hover {
    border-color: color-mix(in srgb, var(--ink) 35%, transparent);
}
.nb-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 22%, transparent);
}
.nb-sent {
    animation: nb-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.nb-js [data-reveal] {
    opacity: 0;
    transform: translateY(22px);
    transition:
        opacity 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.nb-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}
/* ---------- Section animations ---------- */
/* Headings tighten their tracking as they arrive */
.nb-h2 {
    transition: letter-spacing 1.2s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.nb-js [data-reveal]:not(.is-in) .nb-h2 {
    letter-spacing: 0.01em;
}

/* About: photo frame wipes in, highlights slide in one by one, ticks pop */
.nb-js [data-reveal].is-in .nb-frame {
    animation: nb-frame-in 1.1s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
}
.nb-js [data-reveal]:not(.is-in) .nb-hl {
    opacity: 0;
}
.nb-js [data-reveal].is-in .nb-hl {
    animation: nb-slide-x 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--k, 0) * 90ms + 150ms) backwards;
}
.nb-js [data-reveal].is-in .nb-hl .nb-tick {
    animation: nb-tick 0.6s cubic-bezier(0.3, 1.6, 0.4, 1) calc(var(--k, 0) * 90ms + 450ms) backwards;
}

/* Journey: items slide in from the right while the timeline line draws down */
.nb-js .nb-tl-item[data-reveal] {
    transform: translateX(28px);
}
.nb-js .nb-tl-item[data-reveal].is-in {
    transform: none;
}
.nb-tl-item::after {
    content: '';
    position: absolute;
    left: calc(-2.25rem - 1.5px);
    top: 0;
    bottom: 0;
    width: 1.5px;
    background: var(--accent);
    transform: scaleY(0);
    transform-origin: top;
    transition: transform 1.1s cubic-bezier(0.5, 0, 0.2, 1) 0.2s;
}
.nb-js .nb-tl-item.is-in::after {
    transform: scaleY(1);
}
.nb-tl-item:last-child::after {
    display: none;
}

/* Skills: icon tiles pop in */
.nb-js [data-reveal].is-in .nb-skill-tile {
    animation: nb-tile 0.7s cubic-bezier(0.3, 1.5, 0.4, 1) calc(var(--d, 0ms) + 0.15s) backwards;
}

/* Projects: image uncovers with a wipe while it settles from a slight zoom */
.nb-js [data-reveal].is-in .nb-media {
    animation: nb-wipe 1.2s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}
.nb-js [data-reveal].is-in .nb-media img {
    animation: nb-settle 1.6s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s backwards;
}

/* Contact: slow drifting circles behind the panel content */
.nb-contact {
    position: relative;
}
.nb-contact::before,
.nb-contact::after {
    content: '';
    position: absolute;
    border-radius: 999px;
    background: var(--accent-ink);
    opacity: 0.08;
    pointer-events: none;
}
.nb-contact::before {
    height: 26rem;
    width: 26rem;
    right: -8rem;
    top: -10rem;
    animation: nb-drift 16s ease-in-out infinite;
}
.nb-contact::after {
    height: 16rem;
    width: 16rem;
    left: -5rem;
    bottom: -6rem;
    animation: nb-drift 20s ease-in-out infinite reverse;
}

@keyframes nb-spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes nb-float {
    50% {
        transform: translateY(-12px);
    }
}
@keyframes nb-shimmer {
    to {
        background-position: -150% 0;
    }
}
@keyframes nb-knob {
    from {
        transform: translate(50%, -50%) scale(0);
    }
}
@keyframes nb-count {
    to {
        --n: var(--p);
    }
}
@keyframes nb-frame-in {
    from {
        clip-path: inset(0 0 100% 0 round 1.75rem);
        transform: rotate(4deg) scale(0.95);
    }
}
@keyframes nb-slide-x {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
}
@keyframes nb-tick {
    from {
        transform: scale(0) rotate(-90deg);
    }
}
@keyframes nb-tile {
    from {
        transform: scale(0.4) rotate(-20deg);
        opacity: 0;
    }
}
@keyframes nb-wipe {
    from {
        clip-path: inset(0 0 100% 0 round 1.75rem);
    }
}
@keyframes nb-settle {
    from {
        transform: scale(1.25);
    }
}
@keyframes nb-drift {
    50% {
        transform: translate(-2.5rem, 2rem) scale(1.08);
    }
}

/* Page enter */
.nb-js.nb-root {
    animation: nb-enter 0.7s ease both;
}

@keyframes nb-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes nb-enter {
    from {
        opacity: 0;
    }
}
@keyframes nb-ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}
@keyframes nb-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}

@media (prefers-reduced-motion: reduce) {
    .nb-js.nb-root,
    .nb-js .nb-word,
    .nb-pulse::after,
    .nb-sent {
        animation: none;
    }
    .nb-js .nb-word,
    .nb-js [data-reveal],
    .nb-js [data-reveal] .nb-bar {
        opacity: 1;
        transform: none;
        transition: none;
    }
    .nb-btn,
    .nb-media img,
    .nb-tone,
    .nb-arrow,
    .nb-frame,
    .nb-row,
    .nb-hl {
        transition: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .nb-root *,
    .nb-root *::before,
    .nb-root *::after {
        animation: none !important;
    }
    .nb-js [data-reveal] .nb-count {
        --n: var(--p, 0);
    }
    .nb-js [data-reveal] .nb-bar {
        width: var(--w, 0%);
        transition: none;
    }
    .nb-js .nb-tl-item[data-reveal],
    .nb-js [data-reveal]:not(.is-in) .nb-hl {
        opacity: 1;
        transform: none;
    }
    .nb-tl-item::after {
        transform: scaleY(1);
        transition: none;
    }
}
</style>
