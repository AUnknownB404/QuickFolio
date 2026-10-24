<script setup lang="ts">
/**
 * Nebula Glass — quickFolio template
 * Slug suggestion: `nebula-glass`
 *
 * Dark glassmorphic / cyber-tech: deep-space canvas, ambient radial gradients, floating
 * frosted-glass cards with backdrop blur, vibrant indigo glow accents and hairline borders.
 * Light mode is supported through Tailwind `dark:` (the root carries the dark palette by default
 * and swaps to a frosted light palette when a `light` ancestor / OS light preference applies).
 *
 * Every visible value comes from the seeded field keys. Only small UI chrome labels are hardcoded.
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
    Github,
    Globe,
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

const isOn = (section: string) => props.enabled[section] !== false && !disabledSections.value.has(section);
const hasContent = (value: unknown): boolean => {
    if (typeof value === 'string') return value.trim().length > 0;
    if (Array.isArray(value)) return value.some(hasContent);
    if (value && typeof value === 'object') return Object.values(value).some(hasContent);
    return false;
};
const has = (section: string) => isOn(section) && Object.values(sectionData(section)).some(hasContent);

/* Navbar-level switches may be seeded under `navbar`, `sticky` or `show` */
const navFlag = (key: string, fallback: boolean) => {
    for (const s of ['navbar', 'sticky', 'show']) {
        const r = raw(s, key);
        if (r !== undefined && r !== null && r !== '') return flag(s, key, fallback);
    }
    return fallback;
};
const navMedia = (key: string) => media('navbar', key) ?? media('sticky', key) ?? media('show', key);
const navText = (key: string) => text('navbar', key) ?? text('sticky', key) ?? text('show', key);

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
            if (typeof i === 'string') return { label: hostOf(i), platform: '', url: clean(i) ?? '' };
            const url = clean(i?.url ?? i?.href) ?? '';
            const platform = clean(i?.platform ?? i?.label ?? i?.name) ?? '';
            const platformKey = platform.toLowerCase().replace(/[^a-z]/g, '');
            const label = clean(i?.label ?? i?.name) ?? socialPlatformLabels[platformKey] ?? hostOf(url);
            return { label, platform, url };
        })
        .filter((i) => i.url);

// Navbar
const logoText = computed(() => navText('logo_text') ?? text('header', 'hero_name'));
const logoImage = computed(() => (navFlag('show_logo', true) ? navMedia('logo_image') : undefined));
const stickyNav = computed(() => navFlag('sticky_nav', true));
const navLinks = computed(() =>
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
const heroPrimary = computed(() => {
    const t = text('header', 'hero_cta_primary_text');
    const u = text('header', 'hero_cta_primary_url');
    return t && u ? { text: t, url: u } : null;
});
const heroSecondary = computed(() => {
    const t = text('header', 'hero_cta_secondary_text');
    const u = text('header', 'hero_cta_secondary_url');
    return t && u ? { text: t, url: u } : null;
});

// About
const highlights = computed(() =>
    arr('about', 'about_highlights')
        .map((h) =>
            typeof h === 'string'
                ? { title: clean(h) ?? '', description: undefined as string | undefined }
                : { title: clean(h?.title ?? h?.label ?? h?.text ?? h?.name ?? h?.value) ?? '', description: clean(h?.description) },
        )
        .filter((h) => h.title),
);
const aboutCta = computed(() => {
    const t = text('about', 'about_cta_text');
    const u = text('about', 'about_cta_url');
    return t && u ? { text: t, url: u } : null;
});
const cvFile = computed(() => media('about', 'about_cv_file'));

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
const showLevel = computed(() => flag('skills', 'skills_show_level', true));
const showIcon = computed(() => flag('skills', 'skills_show_icon', true));

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
    twitter: AtSign,
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
let scrollFrame: number | null = null;

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
        class="ng-root [--paper:#05070E] [--soft:#080B16] [--surface:#0D111D80] [--ink:#F0F4FF] [--muted:#94A3B8] [--line:#1E293B] [--accent:#6366F1] [--accent-2:#22D3EE] [--accent-ink:#FFFFFF] selection:bg-[var(--accent)] selection:text-white dark:[--paper:#05070E] dark:[--soft:#080B16] dark:[--surface:#0D111D80] dark:[--ink:#F0F4FF] dark:[--muted:#94A3B8] dark:[--line:#1E293B] dark:[--accent:#6366F1]"
        :class="{ 'ng-js': ready }"
    >
        <!-- Ambient background -->
        <div class="ng-ambient" aria-hidden="true">
            <span class="ng-orb ng-orb-a" />
            <span class="ng-orb ng-orb-b" />
            <span class="ng-orb ng-orb-c" />
            <span class="ng-grain" />
        </div>

        <!-- ============================ NAVBAR ============================ -->
        <header v-if="logoImage || logoText || navLinks.length || has('contact')" class="inset-x-0 top-0 z-[500] px-3 pt-3 sm:px-6 sm:pt-4" :class="stickyNav ? 'fixed' : 'absolute'">
            <div ref="progressEl" class="ng-progress" aria-hidden="true" />
            <nav ref="navEl" class="ng-nav z-50 mx-auto flex h-14 max-w-[1240px] items-center justify-between gap-3 pl-5 pr-2" aria-label="Primary">
                <a v-if="logoImage || logoText" href="#header" class="flex min-w-0 items-center gap-2.5" @click="onNavClick($event, '#header')">
                    <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-7 w-auto max-w-[7rem] object-contain" />
                    <span v-if="logoText" class="ng-display truncate text-base">{{ logoText }}</span>
                </a>
                <span v-else />

                <ul v-if="navLinks.length" class="hidden items-center gap-1 md:flex">
                    <li v-for="l in navLinks" :key="l.id">
                        <a :href="`#${l.id}`" class="ng-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a>
                    </li>
                </ul>

                <a v-if="has('contact')" href="#contact" class="ng-btn ng-btn-primary !px-5 !py-2.5 !text-sm" @click="onNavClick($event, '#contact')">
                    {{ text('contact', 'contact_subtitle') ?? text('contact', 'contact_title') ?? email }}<ArrowUpRight class="ng-arrow h-4 w-4" />
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
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-20"
            />
            <div class="ng-vignette absolute inset-0 -z-10" aria-hidden="true" />

            <div class="mx-auto grid w-full max-w-[1240px] items-center gap-16 px-5 pb-20 pt-36 sm:px-8 lg:grid-cols-12 lg:gap-10 lg:pt-32">
                <div class="lg:col-span-7">
                    <p v-if="text('header', 'hero_greeting')" data-reveal class="ng-chip" style="--d: 0ms">
                        <span class="ng-pulse" aria-hidden="true" />{{ text('header', 'hero_greeting') }}
                    </p>

                    <h1 v-if="heroName" class="ng-display ng-hero-name mt-7">
                        <span class="sr-only">{{ heroName }}</span>
                        <span v-for="(w, i) in heroWords" :key="i" class="ng-mask" aria-hidden="true">
                            <span class="ng-word ng-gradient-text" :style="{ '--i': i }">{{ w }}</span>
                        </span>
                    </h1>

                    <p v-if="text('header', 'hero_role')" data-reveal class="ng-mono mt-6 text-sm uppercase tracking-[0.18em] text-[var(--accent-2)] sm:text-base" style="--d: 380ms">
                        {{ text('header', 'hero_role') }}
                    </p>

                    <p
                        v-if="text('header', 'hero_description')"
                        data-reveal
                        class="ng-muted mt-6 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7]"
                        style="--d: 460ms"
                    >
                        {{ text('header', 'hero_description') }}
                    </p>

                    <div v-if="heroPrimary || heroSecondary" data-reveal class="mt-9 flex flex-wrap gap-3" style="--d: 540ms">
                        <a v-if="heroPrimary" :href="heroPrimary.url" class="ng-btn ng-btn-primary" @click="onNavClick($event, heroPrimary.url)"
                            >{{ heroPrimary.text }}<ArrowUpRight class="ng-arrow h-4 w-4"
                        /></a>
                        <a v-if="heroSecondary" :href="heroSecondary.url" class="ng-btn ng-btn-ghost" @click="onNavClick($event, heroSecondary.url)">{{
                            heroSecondary.text
                        }}</a>
                    </div>

                    <ul v-if="heroSocials.length" data-reveal class="mt-9 flex flex-wrap gap-2" style="--d: 620ms">
                        <li v-for="(s, i) in heroSocials" :key="i">
                            <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="ng-icon-btn">
                                <component :is="socialIconFor(s.platform)" class="h-[18px] w-[18px]" />
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="lg:col-span-5">
                    <figure data-reveal class="ng-portrait relative mx-auto aspect-square w-[74%] max-w-[23rem] sm:w-full" style="--d: 250ms">
                        <div class="ng-halo" aria-hidden="true" />
                        <div class="ng-ring" aria-hidden="true" />
                        <div class="ng-ring ng-ring-2" aria-hidden="true" />
                        <div class="ng-photo group">
                            <img
                                v-if="media('header', 'hero_image')"
                                :src="media('header', 'hero_image')"
                                :alt="heroName ?? ''"
                                class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.06]"
                            />
                            <span v-else-if="heroInitials" class="ng-display ng-gradient-bg flex h-full w-full items-center justify-center text-[7rem] text-white">{{
                                heroInitials
                            }}</span>
                        </div>
                        <span class="ng-orbit ng-orbit-a" aria-hidden="true"><Sparkles class="h-5 w-5" /></span>
                        <span class="ng-orbit ng-orbit-b" aria-hidden="true"><Code class="h-5 w-5" /></span>
                        <figcaption v-if="location" class="ng-badge ng-glass"><MapPin class="h-4 w-4 text-[var(--accent-2)]" />{{ location }}</figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- ============================== ABOUT ============================== -->
        <section v-if="has('about')" id="about" class="relative scroll-mt-4">
            <div class="mx-auto grid w-full max-w-[1240px] items-center gap-14 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-16">
                <div v-if="media('about', 'about_image')" data-reveal class="lg:col-span-5">
                    <div class="ng-frame ng-glass mx-auto max-w-md">
                        <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="aspect-[4/5] w-full rounded-[1.1rem] object-cover" loading="lazy" />
                    </div>
                </div>

                <div :class="media('about', 'about_image') ? 'lg:col-span-7' : 'lg:col-span-10'">
                    <div data-reveal>
                        <p v-if="text('about', 'about_subtitle')" class="ng-kicker">{{ text('about', 'about_subtitle') }}</p>
                        <h2 v-if="text('about', 'about_title')" class="ng-display ng-h2 mt-4">{{ text('about', 'about_title') }}</h2>
                        <p v-if="text('about', 'about_description')" class="ng-muted mt-7 max-w-[38rem] whitespace-pre-line text-lg leading-[1.75]">
                            {{ text('about', 'about_description') }}
                        </p>
                    </div>

                    <ul v-if="highlights.length" data-reveal class="mt-10 grid gap-3 sm:grid-cols-2">
                        <li v-for="(h, i) in highlights" :key="i" :style="{ '--k': i }" class="ng-hl ng-glass ng-lift">
                            <span class="ng-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>
                            <span>
                                <span class="block font-semibold leading-snug">{{ h.title }}</span>
                                <span v-if="h.description" class="ng-muted mt-1 block text-[0.92rem] leading-relaxed">{{ h.description }}</span>
                            </span>
                        </li>
                    </ul>

                    <div v-if="cvFile || aboutCta" data-reveal class="mt-10 flex flex-wrap gap-3">
                        <a v-if="cvFile" :href="cvFile" download target="_blank" rel="noopener" class="ng-btn ng-btn-primary">
                            <Download class="h-4 w-4" />Download CV</a>
                        <a v-if="aboutCta && cvFile" :href="aboutCta.url" class="ng-btn ng-btn-ghost" @click="onNavClick($event, aboutCta.url)">
                            {{ aboutCta.text }}<ArrowUpRight class="ng-arrow h-4 w-4" />
                        </a>
                        <a v-else-if="aboutCta" :href="aboutCta.url" class="ng-btn ng-btn-primary" @click="onNavClick($event, aboutCta.url)">
                            {{ aboutCta.text }}<ArrowUpRight class="ng-arrow h-4 w-4" />
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================== JOURNEY ============================== -->
        <section v-if="has('journey')" id="journey" class="relative scroll-mt-4">
            <div class="mx-auto grid w-full max-w-[1240px] gap-12 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-16">
                <div class="lg:col-span-4">
                    <div data-reveal class="lg:sticky lg:top-28">
                        <p v-if="text('journey', 'journey_subtitle')" class="ng-kicker">{{ text('journey', 'journey_subtitle') }}</p>
                        <h2 v-if="text('journey', 'journey_title')" class="ng-display ng-h2 mt-4">{{ text('journey', 'journey_title') }}</h2>
                    </div>
                </div>

                <ol class="ng-timeline lg:col-span-8">
                    <li v-for="(item, index) in journey" :key="index" data-reveal :style="{ '--d': `${(index % 4) * 70}ms` }" class="ng-tl-item">
                        <div class="ng-glass ng-lift p-6 sm:p-7">
                            <p v-if="item.period" class="ng-mono text-[0.78rem] font-medium uppercase tracking-[0.14em] text-[var(--accent-2)]">{{ item.period }}</p>
                            <h3 v-if="item.title" class="ng-display mt-2 text-2xl leading-tight">{{ item.title }}</h3>
                            <p v-if="item.company" class="mt-1 font-medium text-[var(--accent)]">{{ item.company }}</p>
                            <p v-if="item.description" class="ng-muted mt-3 max-w-[38rem] whitespace-pre-line leading-[1.7]">{{ item.description }}</p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ============================== SKILLS ============================== -->
        <section v-if="has('skills')" id="skills" class="relative scroll-mt-4">
            <div class="mx-auto grid w-full max-w-[1240px] gap-12 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-16">
                <div class="lg:col-span-4">
                    <div data-reveal class="lg:sticky lg:top-28">
                        <p v-if="text('skills', 'skills_subtitle')" class="ng-kicker">{{ text('skills', 'skills_subtitle') }}</p>
                        <h2 v-if="text('skills', 'skills_title')" class="ng-display ng-h2 mt-4">{{ text('skills', 'skills_title') }}</h2>
                        <p v-if="text('skills', 'skills_description')" class="ng-muted mt-6 whitespace-pre-line leading-[1.7]">{{ text('skills', 'skills_description') }}</p>
                    </div>
                </div>

                <ul class="grid content-start gap-4 sm:grid-cols-2 lg:col-span-8">
                    <li v-for="(s, i) in skills" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 50}ms` }" class="ng-skill ng-glass ng-lift">
                        <div class="flex items-center gap-3.5">
                            <span v-if="showIcon" class="ng-tile">
                                <component :is="iconFor(s.icon)" v-if="iconFor(s.icon)" class="h-[18px] w-[18px]" />
                                <span v-else-if="isGlyph(s.icon)" class="text-lg leading-none" aria-hidden="true">{{ s.icon }}</span>
                                <span v-else class="ng-display text-base leading-none" aria-hidden="true">{{ s.name.charAt(0).toUpperCase() }}</span>
                            </span>
                            <h3 class="font-semibold">{{ s.name }}</h3>
                            <span
                                v-if="showLevel && s.pct !== null"
                                class="ng-count ng-mono ng-muted ml-auto text-[0.8rem] font-medium"
                                :style="{ '--p': Math.round(s.pct) }"
                                aria-hidden="true"
                            />
                            <span v-else-if="showLevel && s.label" class="ng-pill ml-auto">{{ s.label }}</span>
                        </div>
                        <div
                            v-if="showLevel && s.pct !== null"
                            class="ng-track mt-5"
                            role="progressbar"
                            :aria-valuenow="Math.round(s.pct)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="s.name"
                        >
                            <div class="ng-bar" :style="{ '--w': `${s.pct}%` }"><span class="ng-knob" /></div>
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
                        <p v-if="text('projects', 'projects_subtitle')" class="ng-kicker">{{ text('projects', 'projects_subtitle') }}</p>
                        <h2 v-if="text('projects', 'projects_title')" class="ng-display ng-h2 mt-4">{{ text('projects', 'projects_title') }}</h2>
                    </div>
                    <p v-if="text('projects', 'projects_description')" class="ng-muted whitespace-pre-line leading-[1.7] lg:col-span-5">
                        {{ text('projects', 'projects_description') }}
                    </p>
                </div>

                <ul class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <li
                        v-for="(p, i) in projects"
                        :key="i"
                        data-reveal
                        :class="featureFirst && i === 0 ? 'md:col-span-2 xl:col-span-2' : ''"
                        :style="{ '--d': `${(i % 3) * 80}ms` }"
                    >
                        <article class="ng-project ng-glass group flex h-full flex-col p-3">
                            <component
                                :is="p.live ? 'a' : 'div'"
                                :href="p.live || undefined"
                                :target="p.live ? '_blank' : undefined"
                                :rel="p.live ? 'noopener noreferrer' : undefined"
                                :aria-label="p.live ? p.title : undefined"
                                class="ng-media relative block overflow-hidden"
                                :class="featureFirst && i === 0 ? 'aspect-[16/9]' : 'aspect-[4/3]'"
                            >
                                <img v-if="p.image" :src="p.image" :alt="p.title" class="h-full w-full object-cover" loading="lazy" />
                                <div v-else class="ng-display ng-tone flex h-full w-full items-center justify-center text-8xl" :class="`ng-tone-${i % 3}`">
                                    {{ p.title.charAt(0).toUpperCase() }}
                                </div>
                                <span v-if="featureFirst && i === 0" class="ng-flag ng-mono"><Sparkles class="h-3.5 w-3.5" />{{ String(i + 1).padStart(2, '0') }}</span>
                                <span v-if="p.live" class="ng-go" aria-hidden="true"><ArrowUpRight class="h-5 w-5" /></span>
                            </component>

                            <div class="flex flex-1 flex-col px-3 pb-3 pt-5">
                                <h3 class="ng-display text-2xl leading-tight"><span class="ng-underline">{{ p.title }}</span></h3>
                                <p v-if="p.description" class="ng-muted mt-3 leading-[1.65]">{{ p.description }}</p>
                                <ul v-if="p.tags.length" class="mt-4 flex flex-wrap gap-2">
                                    <li v-for="(t, ti) in p.tags" :key="ti" class="ng-pill">{{ t }}</li>
                                </ul>
                                <div v-if="p.repo" class="mt-auto pt-5">
                                    <a :href="p.repo" target="_blank" rel="noopener noreferrer" class="ng-textlink ng-mono text-[0.8rem]"
                                        ><Code class="h-4 w-4" />{{ p.repo.replace(/^https?:\/\/(www\.)?/, '').split('/').slice(0, 2).join('/') }}</a
                                    >
                                </div>
                            </div>
                        </article>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== SERVICES ============================== -->
        <section v-if="has('services')" id="services" class="relative scroll-mt-4">
            <div class="mx-auto w-full max-w-[1240px] px-5 py-28 sm:px-8">
                <div data-reveal class="mb-14 max-w-3xl">
                    <p v-if="text('services', 'services_subtitle')" class="ng-kicker">{{ text('services', 'services_subtitle') }}</p>
                    <h2 v-if="text('services', 'services_title')" class="ng-display ng-h2 mt-4">{{ text('services', 'services_title') }}</h2>
                </div>

                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="(s, i) in services" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 70}ms` }">
                        <div class="ng-service ng-glass ng-lift group flex h-full flex-col p-7">
                            <span class="ng-tile ng-tile-lg">
                                <component :is="iconFor(s.icon) ?? Sparkles" v-if="!isGlyph(s.icon)" class="h-6 w-6" />
                                <span v-else class="text-2xl leading-none" aria-hidden="true">{{ s.icon }}</span>
                            </span>
                            <h3 class="ng-display ng-service-title mt-6 text-2xl leading-tight">{{ s.title }}</h3>
                            <p v-if="s.description" class="ng-muted mt-3 leading-[1.65]">{{ s.description }}</p>
                            <ArrowUpRight class="ng-service-arrow mt-auto h-5 w-5 self-end pt-0" aria-hidden="true" />
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== CONTACT ============================== -->
        <section v-if="has('contact')" id="contact" class="relative scroll-mt-4 px-3 pb-6 pt-24 sm:px-6">
            <div data-reveal class="ng-contact ng-glass mx-auto max-w-[1240px]">
                <div class="relative z-10 grid gap-14 p-7 sm:p-12 lg:grid-cols-12 lg:gap-12 lg:p-16">
                    <div class="lg:col-span-7">
                        <div data-reveal>
                            <p v-if="text('contact', 'contact_subtitle')" class="ng-kicker">{{ text('contact', 'contact_subtitle') }}</p>
                            <h2 v-if="text('contact', 'contact_title')" class="ng-display ng-gradient-text mt-4 text-[clamp(2.75rem,7.5vw,6rem)] leading-[0.95] [overflow-wrap:anywhere]">
                                {{ text('contact', 'contact_title') }}
                            </h2>
                            <p v-if="text('contact', 'contact_description')" class="ng-muted mt-6 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7]">
                                {{ text('contact', 'contact_description') }}
                            </p>
                        </div>

                        <div v-if="email || phone || location" data-reveal class="mt-10 space-y-5">
                            <a v-if="email" :href="`mailto:${email}`" class="ng-bigmail ng-display group inline-flex max-w-full items-center gap-3 break-all text-2xl sm:text-4xl">
                                <Mail class="h-7 w-7 shrink-0 text-[var(--accent-2)]" /><span class="ng-underline-contact">{{ email }}</span>
                                <ArrowUpRight class="ng-arrow h-7 w-7 shrink-0" />
                            </a>
                            <div class="flex flex-wrap gap-x-8 gap-y-3 font-medium">
                                <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="ng-contact-link"><Phone class="h-4 w-4 text-[var(--accent-2)]" />{{ phone }}</a>
                                <span v-if="location" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4 text-[var(--accent-2)]" />{{ location }}</span>
                            </div>
                        </div>

                        <ul v-if="contactSocials.length" data-reveal class="mt-8 flex flex-wrap gap-2">
                            <li v-for="(s, i) in contactSocials" :key="i">
                                <a :href="s.url" target="_blank" rel="noopener noreferrer" class="ng-social">
                                    <component :is="socialIconFor(s.platform)" class="h-3.5 w-3.5" />{{ s.label }}<ArrowUpRight class="ng-arrow h-3.5 w-3.5" />
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="lg:col-span-5">
                        <div data-reveal style="--d: 120ms">
                            <form
                                v-if="formReady"
                                :action="formAction"
                                method="POST"
                                target="ng-gform-frame"
                                class="ng-form space-y-5 p-6 sm:p-8"
                                @submit="onSubmit"
                            >
                                <label class="block">
                                    <span class="ng-mono ng-muted mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.14em]">Name</span>
                                    <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="ng-input" />
                                </label>
                                <label class="block">
                                    <span class="ng-mono ng-muted mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.14em]">Email</span>
                                    <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="ng-input" />
                                </label>
                                <label class="block">
                                    <span class="ng-mono ng-muted mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.14em]">Message</span>
                                    <textarea :name="`entry.${gf.message}`" rows="5" required class="ng-input resize-y" />
                                </label>
                                <button type="submit" class="ng-btn ng-btn-primary w-full justify-center" :disabled="sending">
                                    {{ sending ? '…' : 'Send' }}<Send class="ng-arrow h-4 w-4" />
                                </button>
                                <p v-if="sent" role="status" class="ng-sent flex items-center gap-2 text-sm font-semibold">
                                    <span class="ng-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>OK
                                </p>
                            </form>
                            <a v-else-if="email" :href="`mailto:${email}`" class="ng-btn ng-btn-ghost w-full justify-center !py-5 text-lg"><Mail class="h-5 w-5" />{{ email }}</a>
                        </div>
                        <iframe name="ng-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                    </div>
                </div>
            </div>

            <footer v-if="heroName || logoText" class="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-2 px-2 py-8 text-sm">
                <span class="ng-muted ng-mono text-[0.78rem]">&copy; {{ year }} {{ heroName ?? logoText }}</span>
                <a href="#header" class="ng-textlink" @click="onNavClick($event, '#header')"><ArrowUpRight class="ng-arrow h-4 w-4 -rotate-45" /></a>
            </footer>
        </section>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');

/* ---------- Foundations ---------- */
.ng-root {
    position: relative;
    background: var(--paper);
    color: var(--ink);
    font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    overflow-x: clip;
    transition:
        background-color 0.4s ease,
        color 0.4s ease;
}
/* Light palette (OS preference or explicit light class on an ancestor) */
@media (prefers-color-scheme: light) {
    .ng-root:not(.dark) {
        --paper: #eef1fb;
        --soft: #e6eaf8;
        --surface: #ffffffb3;
        --ink: #0b1020;
        --muted: #4b5675;
        --line: #c9d0e8;
        --accent: #4f46e5;
        --accent-2: #0891b2;
    }
}
:global(.light) .ng-root {
    --paper: #eef1fb;
    --soft: #e6eaf8;
    --surface: #ffffffb3;
    --ink: #0b1020;
    --muted: #4b5675;
    --line: #c9d0e8;
    --accent: #4f46e5;
    --accent-2: #0891b2;
}
:global(.dark) .ng-root {
    --paper: #05070e;
    --soft: #080b16;
    --surface: #0d111d80;
    --ink: #f0f4ff;
    --muted: #94a3b8;
    --line: #1e293b;
    --accent: #6366f1;
    --accent-2: #22d3ee;
}
.ng-display {
    font-family: 'Space Grotesk', 'Inter', ui-sans-serif, system-ui, sans-serif;
    font-weight: 600;
    letter-spacing: -0.035em;
}
.ng-mono {
    font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
}
.ng-muted {
    color: var(--muted);
}
.ng-h2 {
    font-size: clamp(2.25rem, 5vw, 4rem);
    line-height: 1.04;
    letter-spacing: -0.04em;
    text-wrap: balance;
}
.ng-gradient-text {
    background: linear-gradient(100deg, var(--ink) 10%, var(--accent) 55%, var(--accent-2) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}
.ng-gradient-bg {
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
}
.ng-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent-2);
    outline-offset: 3px;
    border-radius: 0.5rem;
}

/* Frosted glass surface */
.ng-glass {
    position: relative;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 1.5rem;
    -webkit-backdrop-filter: blur(14px) saturate(1.4);
    backdrop-filter: blur(14px) saturate(1.4);
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--ink) 8%, transparent),
        0 24px 48px -28px #000000b3;
}
.ng-lift {
    transition:
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.35s ease,
        box-shadow 0.45s ease;
}
.ng-lift:hover {
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--accent) 65%, var(--line));
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--ink) 12%, transparent),
        0 0 0 1px color-mix(in srgb, var(--accent) 30%, transparent),
        0 28px 60px -24px color-mix(in srgb, var(--accent) 55%, transparent);
}

/* Ambient background */
.ng-ambient {
    position: fixed;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    pointer-events: none;
    background:
        radial-gradient(60% 50% at 15% 0%, color-mix(in srgb, var(--accent) 22%, transparent), transparent 70%),
        radial-gradient(50% 45% at 100% 30%, color-mix(in srgb, var(--accent-2) 14%, transparent), transparent 70%),
        radial-gradient(70% 60% at 50% 110%, color-mix(in srgb, var(--accent) 16%, transparent), transparent 70%);
}
.ng-orb {
    position: absolute;
    border-radius: 999px;
    filter: blur(80px);
    opacity: 0.45;
}
.ng-orb-a {
    height: 28rem;
    width: 28rem;
    left: -8rem;
    top: 18%;
    background: var(--accent);
    animation: ng-drift 22s ease-in-out infinite;
}
.ng-orb-b {
    height: 22rem;
    width: 22rem;
    right: -6rem;
    top: 45%;
    background: var(--accent-2);
    opacity: 0.25;
    animation: ng-drift 28s ease-in-out infinite reverse;
}
.ng-orb-c {
    height: 26rem;
    width: 26rem;
    left: 35%;
    bottom: -10rem;
    background: var(--accent);
    opacity: 0.3;
    animation: ng-drift 34s ease-in-out infinite;
}
.ng-grain {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px);
    background-size: 56px 56px;
    opacity: 0.18;
    mask-image: radial-gradient(70% 60% at 50% 30%, #000, transparent 80%);
    -webkit-mask-image: radial-gradient(70% 60% at 50% 30%, #000, transparent 80%);
}
.ng-root > section,
.ng-root > header {
    position: relative;
}
.ng-root > header {
    position: fixed;
}
.ng-root > header.absolute {
    position: absolute;
}
.ng-vignette {
    background: radial-gradient(60% 60% at 70% 40%, transparent, color-mix(in srgb, var(--paper) 70%, transparent));
}

/* Section subtitle */
.ng-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.78rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: var(--accent-2);
}
.ng-kicker::before {
    content: '';
    width: 1.75rem;
    height: 1px;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    box-shadow: 0 0 10px var(--accent-2);
    transform-origin: left;
    transition: transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) 0.2s;
}
.ng-js [data-reveal]:not(.is-in) .ng-kicker::before {
    transform: scaleX(0);
}

/* ---------- Navbar ---------- */
.ng-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 2px;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    box-shadow: 0 0 12px var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
    z-index: 60;
}
.ng-nav {
    border: 1px solid var(--line);
    border-radius: 999px;
    background: color-mix(in srgb, var(--paper) 45%, transparent);
    -webkit-backdrop-filter: blur(16px) saturate(1.5);
    backdrop-filter: blur(16px) saturate(1.5);
    transition:
        border-color 0.35s ease,
        background-color 0.35s ease,
        box-shadow 0.35s ease;
}
.ng-nav.is-scrolled {
    background: color-mix(in srgb, var(--paper) 75%, transparent);
    box-shadow: 0 14px 40px -16px color-mix(in srgb, var(--accent) 45%, #000);
}
.ng-navlink {
    display: inline-block;
    border-radius: 999px;
    padding: 0.5rem 1rem;
    font-size: 0.92rem;
    font-weight: 500;
    color: var(--muted);
    transition:
        color 0.25s ease,
        background-color 0.25s ease,
        box-shadow 0.25s ease;
}
.ng-navlink:hover {
    color: var(--ink);
}
.ng-navlink[data-active='true'] {
    color: var(--ink);
    background: color-mix(in srgb, var(--accent) 20%, transparent);
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent) 45%, transparent);
}

/* ---------- Buttons & small controls ---------- */
.ng-btn {
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
    transition:
        transform 0.25s ease,
        box-shadow 0.35s ease,
        border-color 0.3s ease,
        background-color 0.3s ease;
}
.ng-btn:hover {
    transform: translateY(-2px);
}
.ng-btn:active {
    transform: translateY(0) scale(0.98);
}
.ng-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.ng-btn-primary {
    background: linear-gradient(120deg, var(--accent), color-mix(in srgb, var(--accent) 55%, var(--accent-2)));
    color: var(--accent-ink);
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--accent) 60%, transparent),
        0 10px 28px -10px var(--accent);
}
.ng-btn-primary:hover {
    box-shadow:
        0 0 0 1px var(--accent-2),
        0 14px 36px -8px var(--accent),
        0 0 28px -4px color-mix(in srgb, var(--accent-2) 70%, transparent);
}
.ng-btn-ghost {
    background: var(--surface);
    border-color: var(--line);
    color: var(--ink);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
}
.ng-btn-ghost:hover {
    border-color: var(--accent);
    box-shadow: 0 0 24px -6px color-mix(in srgb, var(--accent) 70%, transparent);
}
.ng-arrow {
    transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ng-btn:hover .ng-arrow,
.ng-bigmail:hover .ng-arrow,
.ng-social:hover .ng-arrow,
.ng-textlink:hover .ng-arrow {
    transform: translate(3px, -3px);
}
.ng-icon-btn {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
    transition:
        transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1),
        background-color 0.25s ease,
        border-color 0.25s ease,
        box-shadow 0.3s ease;
}
.ng-icon-btn:hover {
    transform: translateY(-3px);
    border-color: var(--accent);
    background: color-mix(in srgb, var(--accent) 25%, var(--surface));
    box-shadow: 0 0 22px -4px var(--accent);
}
.ng-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
    padding: 0.45rem 1rem 0.45rem 0.85rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.8rem;
    font-weight: 500;
}
.ng-pulse {
    position: relative;
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 999px;
    background: var(--accent-2);
    box-shadow: 0 0 10px var(--accent-2);
}
.ng-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: inherit;
    animation: ng-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.ng-pill {
    display: inline-block;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--accent) 40%, var(--line));
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    padding: 0.2rem 0.7rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.72rem;
    font-weight: 500;
    color: color-mix(in srgb, var(--ink) 80%, var(--accent));
}
.ng-tick {
    display: inline-flex;
    height: 1.5rem;
    width: 1.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    color: #fff;
    box-shadow: 0 0 14px -2px var(--accent);
}
.ng-textlink {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    background: linear-gradient(var(--accent-2), var(--accent-2)) left bottom / 0% 1.5px no-repeat;
    transition: background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 2px;
}
.ng-textlink:hover {
    background-size: 100% 1.5px;
}

/* ---------- Hero ---------- */
.ng-hero-name {
    font-size: clamp(3.25rem, 9.5vw, 8rem);
    line-height: 0.96;
    letter-spacing: -0.05em;
    overflow-wrap: anywhere;
}
.ng-mask {
    display: inline-block;
    overflow: hidden;
    vertical-align: top;
    padding-bottom: 0.14em;
    margin-bottom: -0.14em;
    margin-right: 0.22em;
}
.ng-word {
    display: inline-block;
}
.ng-js .ng-word {
    transform: translateY(110%);
    animation: ng-rise 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: calc(0.15s + var(--i, 0) * 0.1s);
}
.ng-halo {
    position: absolute;
    inset: -12%;
    border-radius: 999px;
    background: radial-gradient(closest-side, color-mix(in srgb, var(--accent) 55%, transparent), transparent 72%);
    filter: blur(28px);
    animation: ng-breathe 6s ease-in-out infinite;
}
.ng-ring {
    position: absolute;
    inset: -1.1rem;
    border-radius: 999px;
    border: 1.5px dashed color-mix(in srgb, var(--accent-2) 60%, transparent);
    animation: ng-spin 48s linear infinite;
}
.ng-ring-2 {
    inset: -2.3rem;
    border: 1px solid var(--line);
    animation-duration: 30s;
    animation-direction: reverse;
}
.ng-ring-2::after {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    height: 0.8rem;
    width: 0.8rem;
    transform: translate(-50%, -50%);
    border-radius: 999px;
    background: var(--accent-2);
    box-shadow:
        0 0 0 5px color-mix(in srgb, var(--accent-2) 22%, transparent),
        0 0 18px var(--accent-2);
}
.ng-photo {
    position: absolute;
    inset: 0;
    overflow: hidden;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--ink) 25%, transparent);
    padding: 0;
    background: var(--soft);
    box-shadow:
        0 0 0 6px color-mix(in srgb, var(--paper) 60%, transparent),
        0 30px 60px -24px #000000cc;
}
.ng-orbit {
    position: absolute;
    display: inline-flex;
    height: 3.25rem;
    width: 3.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    border: 1px solid var(--line);
    background: var(--surface);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
    color: var(--accent-2);
    box-shadow: 0 0 24px -6px var(--accent);
    animation: ng-float 5.5s ease-in-out infinite;
}
.ng-orbit-a {
    top: 4%;
    right: -3%;
}
.ng-orbit-b {
    bottom: 14%;
    left: -7%;
    color: var(--accent);
    animation-duration: 6.5s;
    animation-delay: -2s;
}
.ng-badge {
    position: absolute;
    bottom: -1.1rem;
    left: 50%;
    transform: translateX(-50%);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    border-radius: 999px;
    padding: 0.55rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
}

/* ---------- About ---------- */
.ng-frame {
    padding: 0.5rem;
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ng-frame::before {
    content: '';
    position: absolute;
    inset: -1px;
    z-index: -1;
    border-radius: inherit;
    background: linear-gradient(135deg, var(--accent), transparent 40%, var(--accent-2));
    opacity: 0.55;
    filter: blur(14px);
}
.ng-frame:hover {
    transform: rotate(-1deg) scale(1.01);
}
.ng-hl {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 1.15rem 1.25rem;
    border-radius: 1.1rem;
}

/* ---------- Journey ---------- */
.ng-timeline {
    position: relative;
    margin-left: 0.5rem;
    border-left: 1px solid var(--line);
    padding-left: 2.25rem;
}
.ng-tl-item {
    position: relative;
    padding-bottom: 1.5rem;
}
.ng-tl-item:last-child {
    padding-bottom: 0;
}
.ng-tl-item::before {
    content: '';
    position: absolute;
    left: -2.25rem;
    top: 1.7rem;
    height: 0.8rem;
    width: 0.8rem;
    transform: translateX(-50%);
    border-radius: 999px;
    background: var(--paper);
    border: 2px solid var(--accent);
    transition:
        background-color 0.5s ease 0.3s,
        box-shadow 0.4s ease;
}
.ng-js .ng-tl-item.is-in::before {
    background: var(--accent-2);
    border-color: var(--accent-2);
    box-shadow: 0 0 14px var(--accent-2);
}
.ng-tl-item::after {
    content: '';
    position: absolute;
    left: calc(-2.25rem - 1px);
    top: 0;
    bottom: 0;
    width: 1px;
    background: linear-gradient(var(--accent), var(--accent-2));
    box-shadow: 0 0 8px var(--accent);
    transform: scaleY(0);
    transform-origin: top;
    transition: transform 1.1s cubic-bezier(0.5, 0, 0.2, 1) 0.2s;
}
.ng-js .ng-tl-item.is-in::after {
    transform: scaleY(1);
}
.ng-tl-item:last-child::after {
    display: none;
}
.ng-js .ng-tl-item[data-reveal] {
    transform: translateX(28px);
}
.ng-js .ng-tl-item[data-reveal].is-in {
    transform: none;
}

/* ---------- Skills ---------- */
.ng-skill {
    padding: 1.4rem 1.4rem 1.5rem;
}
.ng-tile {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    background: color-mix(in srgb, var(--accent) 16%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 40%, var(--line));
    color: var(--accent-2);
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        box-shadow 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ng-tile-lg {
    height: 3.25rem;
    width: 3.25rem;
    border-radius: 1rem;
}
.ng-skill:hover .ng-tile,
.ng-service:hover .ng-tile {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 0 22px -2px var(--accent);
    transform: rotate(-6deg) scale(1.06);
}
.ng-track {
    position: relative;
    height: 6px;
    width: 100%;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ink) 10%, transparent);
}
.ng-bar {
    position: relative;
    height: 100%;
    width: var(--w, 0%);
    border-radius: inherit;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    box-shadow: 0 0 14px -2px var(--accent);
}
.ng-knob {
    position: absolute;
    right: 0;
    top: 50%;
    height: 14px;
    width: 14px;
    transform: translate(50%, -50%);
    border-radius: 999px;
    border: 3px solid var(--accent-2);
    background: var(--paper);
    box-shadow: 0 0 12px var(--accent-2);
}
.ng-js [data-reveal] .ng-bar {
    width: 0;
    transition: width 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s);
}
.ng-js [data-reveal].is-in .ng-bar {
    width: var(--w, 0%);
}
.ng-js [data-reveal].is-in .ng-knob {
    animation: ng-knob 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) calc(var(--d, 0ms) + 1.6s) backwards;
}

/* Percentage counts up in sync with the bar */
@property --n {
    syntax: '<integer>';
    inherits: false;
    initial-value: 0;
}
.ng-count {
    --n: var(--p, 0);
    counter-reset: n var(--n);
    font-variant-numeric: tabular-nums;
}
.ng-count::after {
    content: counter(n) '%';
}
.ng-js [data-reveal] .ng-count {
    --n: 0;
}
.ng-js [data-reveal].is-in .ng-count {
    animation: ng-count 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s) forwards;
}

/* ---------- Projects ---------- */
.ng-project {
    transition:
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.35s ease,
        box-shadow 0.45s ease;
}
.ng-project:hover {
    transform: translateY(-5px);
    border-color: color-mix(in srgb, var(--accent) 65%, var(--line));
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--accent) 30%, transparent),
        0 30px 64px -24px color-mix(in srgb, var(--accent) 60%, transparent);
}
.ng-media {
    border-radius: 1.1rem;
    background: var(--soft);
    border: 1px solid var(--line);
    isolation: isolate;
}
.ng-media img,
.ng-tone {
    transition: transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ng-project:hover .ng-media img,
.ng-project:hover .ng-tone {
    transform: scale(1.06);
}
.ng-tone-0 {
    background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 40%, #000));
    color: #fff;
}
.ng-tone-1 {
    background: linear-gradient(135deg, var(--accent-2), color-mix(in srgb, var(--accent-2) 40%, #000));
    color: #fff;
}
.ng-tone-2 {
    background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 60%, var(--accent-2)), var(--soft));
    color: #fff;
}
.ng-flag {
    position: absolute;
    left: 0.8rem;
    top: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--paper) 70%, transparent);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    border: 1px solid var(--accent-2);
    color: var(--accent-2);
    padding: 0.25rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 500;
    box-shadow: 0 0 16px -4px var(--accent-2);
}
.ng-go {
    position: absolute;
    right: 0.8rem;
    top: 0.8rem;
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: color-mix(in srgb, var(--paper) 70%, transparent);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    border: 1px solid var(--line);
    color: var(--ink);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem) scale(0.9);
    transition:
        opacity 0.35s ease,
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ng-project:hover .ng-go,
.ng-media:focus-visible .ng-go {
    opacity: 1;
    transform: none;
}
.ng-underline {
    background: linear-gradient(90deg, var(--accent), var(--accent-2)) left 96% / 0% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ng-project:hover .ng-underline {
    background-size: 100% 2px;
}

/* ---------- Services ---------- */
.ng-service-title {
    transition: color 0.3s ease;
}
.ng-service:hover .ng-service-title {
    color: var(--accent-2);
}
.ng-service-arrow {
    color: var(--accent-2);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem);
    transition:
        opacity 0.3s ease,
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ng-service:hover .ng-service-arrow {
    opacity: 1;
    transform: none;
}

/* ---------- Contact ---------- */
.ng-contact {
    border-radius: 2.25rem;
    overflow: hidden;
    border-color: color-mix(in srgb, var(--accent) 40%, var(--line));
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--ink) 10%, transparent),
        0 0 80px -30px var(--accent);
}
.ng-contact::before,
.ng-contact::after {
    content: '';
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    filter: blur(60px);
}
.ng-contact::before {
    height: 24rem;
    width: 24rem;
    right: -8rem;
    top: -10rem;
    background: var(--accent);
    opacity: 0.35;
    animation: ng-drift 16s ease-in-out infinite;
}
.ng-contact::after {
    height: 16rem;
    width: 16rem;
    left: -5rem;
    bottom: -6rem;
    background: var(--accent-2);
    opacity: 0.22;
    animation: ng-drift 20s ease-in-out infinite reverse;
}
.ng-underline-contact {
    background: linear-gradient(90deg, var(--accent), var(--accent-2)) left bottom / 100% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 4px;
}
.ng-bigmail:hover .ng-underline-contact {
    background-size: 0% 2px;
    background-position: right bottom;
}
.ng-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.ng-contact-link:hover {
    text-decoration: underline;
    text-underline-offset: 4px;
}
.ng-social {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: color-mix(in srgb, var(--ink) 5%, transparent);
    padding: 0.45rem 1rem;
    font-size: 0.88rem;
    font-weight: 600;
    transition:
        background-color 0.3s ease,
        border-color 0.3s ease,
        box-shadow 0.3s ease,
        transform 0.3s ease;
}
.ng-social:hover {
    border-color: var(--accent);
    background: color-mix(in srgb, var(--accent) 22%, transparent);
    box-shadow: 0 0 20px -4px var(--accent);
    transform: translateY(-2px);
}
.ng-form {
    border-radius: 1.5rem;
    border: 1px solid var(--line);
    background: color-mix(in srgb, var(--paper) 55%, transparent);
    -webkit-backdrop-filter: blur(16px);
    backdrop-filter: blur(16px);
    box-shadow: inset 0 1px 0 color-mix(in srgb, var(--ink) 8%, transparent);
}
.ng-input {
    width: 100%;
    border-radius: 0.85rem;
    border: 1px solid var(--line);
    background: color-mix(in srgb, var(--ink) 4%, transparent);
    padding: 0.8rem 1rem;
    font: inherit;
    font-weight: 500;
    color: var(--ink);
    transition:
        border-color 0.25s ease,
        box-shadow 0.25s ease,
        background-color 0.25s ease;
}
.ng-input:hover {
    border-color: color-mix(in srgb, var(--accent) 50%, var(--line));
}
.ng-input:focus {
    outline: none;
    border-color: var(--accent);
    background: color-mix(in srgb, var(--accent) 8%, transparent);
    box-shadow:
        0 0 0 4px color-mix(in srgb, var(--accent) 22%, transparent),
        0 0 24px -6px var(--accent);
}
.ng-sent {
    animation: ng-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.ng-js [data-reveal] {
    opacity: 0;
    transform: translateY(22px);
    transition:
        opacity 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.ng-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}
.ng-js .ng-tl-item[data-reveal] {
    transform: translateX(28px);
}
.ng-js .ng-tl-item[data-reveal].is-in {
    transform: none;
}

/* ---------- Section animations ---------- */
.ng-h2 {
    transition: letter-spacing 1.2s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ng-js [data-reveal]:not(.is-in) .ng-h2 {
    letter-spacing: 0.01em;
}
.ng-js [data-reveal].is-in .ng-frame {
    animation: ng-frame-in 1.1s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
}
.ng-js [data-reveal]:not(.is-in) .ng-hl {
    opacity: 0;
}
.ng-js [data-reveal].is-in .ng-hl {
    animation: ng-slide-x 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--k, 0) * 90ms + 150ms) backwards;
}
.ng-js [data-reveal].is-in .ng-hl .ng-tick {
    animation: ng-tick 0.6s cubic-bezier(0.3, 1.6, 0.4, 1) calc(var(--k, 0) * 90ms + 450ms) backwards;
}
.ng-js [data-reveal].is-in .ng-tile {
    animation: ng-tile 0.7s cubic-bezier(0.3, 1.5, 0.4, 1) calc(var(--d, 0ms) + 0.15s) backwards;
}
.ng-js [data-reveal].is-in .ng-media {
    animation: ng-wipe 1.2s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}
.ng-js [data-reveal].is-in .ng-media img {
    animation: ng-settle 1.6s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s backwards;
}

@keyframes ng-spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes ng-float {
    50% {
        transform: translateY(-12px);
    }
}
@keyframes ng-breathe {
    50% {
        transform: scale(1.08);
        opacity: 0.8;
    }
}
@keyframes ng-drift {
    50% {
        transform: translate(-2.5rem, 2rem) scale(1.08);
    }
}
@keyframes ng-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes ng-enter {
    from {
        opacity: 0;
    }
}
@keyframes ng-ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}
@keyframes ng-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}
@keyframes ng-knob {
    from {
        transform: translate(50%, -50%) scale(0);
    }
}
@keyframes ng-count {
    to {
        --n: var(--p);
    }
}
@keyframes ng-frame-in {
    from {
        clip-path: inset(0 0 100% 0 round 1.5rem);
        transform: rotate(4deg) scale(0.95);
    }
}
@keyframes ng-slide-x {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
}
@keyframes ng-tick {
    from {
        transform: scale(0) rotate(-90deg);
    }
}
@keyframes ng-tile {
    from {
        transform: scale(0.4) rotate(-20deg);
        opacity: 0;
    }
}
@keyframes ng-wipe {
    from {
        clip-path: inset(0 0 100% 0 round 1.1rem);
    }
}
@keyframes ng-settle {
    from {
        transform: scale(1.25);
    }
}
.ng-js.ng-root {
    animation: ng-enter 0.7s ease both;
}

/* ---------- Reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    .ng-root *,
    .ng-root *::before,
    .ng-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .ng-js .ng-word,
    .ng-js [data-reveal],
    .ng-js .ng-tl-item[data-reveal],
    .ng-js [data-reveal]:not(.is-in) .ng-hl {
        opacity: 1;
        transform: none;
    }
    .ng-js [data-reveal] .ng-count {
        --n: var(--p, 0);
    }
    .ng-js [data-reveal] .ng-bar {
        width: var(--w, 0%);
    }
    .ng-js [data-reveal]:not(.is-in) .ng-kicker::before {
        transform: none;
    }
    .ng-js [data-reveal]:not(.is-in) .ng-h2 {
        letter-spacing: -0.04em;
    }
    .ng-tl-item::after {
        transform: scaleY(1);
    }
    .ng-ring,
    .ng-orbit,
    .ng-halo,
    .ng-orb {
        animation: none !important;
    }
}
</style>
