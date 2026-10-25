<script setup lang="ts">
/**
 * Grid Index — quickFolio template
 * Slug suggestion: `grid-index`
 *
 * Swiss grid / high-contrast editorial: asymmetric 12-column layouts, crisp hairline borders,
 * dense typography, mono meta labels and subtly elevated monochrome card surfaces.
 * One signal-red accent. Dark by default, light via the root class / OS preference through Tailwind `dark:`.
 *
 * Every visible value comes from the seeded field keys. Only small UI chrome labels are hardcoded.
 */
import { resolvePortfolioMediaUrl } from '@/lib/media';
import { handleContactFormFallback } from '@/lib/contact-form-fallback';
import {
    ArrowDownRight,
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

// Section numbering follows whichever sections are visible
const orderedIds = ['about', 'journey', 'skills', 'projects', 'services', 'contact'];
const numberOf = (id: string) => {
    const visible = orderedIds.filter((s) => has(s));
    const n = visible.indexOf(id) + 1;
    return String(n > 0 ? n : 1).padStart(2, '0');
};

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
    () => flag('contact', 'contact_form_enabled', true),
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
        class="gi-root [--paper:#0F0F11] [--soft:#131316] [--surface:#18181B] [--ink:#FAFAFA] [--muted:#A1A1AA] [--line:#27272A] [--accent:#E05252] [--accent-ink:#0F0F11] selection:bg-[var(--accent)] selection:text-[#0F0F11] light:[--paper:#FAFAFA] dark:[--paper:#0F0F11]"
        :class="[{ 'gi-js': ready }, 'gi-theme']"
    >
        <!-- ============================ NAVBAR ============================ -->
        <header v-if="has('navbar') || navLinks.length || has('contact') || logoText" class="inset-x-0 top-0 z-50" :class="stickyNav ? 'fixed' : 'absolute'">
            <div ref="progressEl" class="gi-progress" aria-hidden="true" />
            <nav ref="navEl" class="gi-nav" aria-label="Primary">
                <div class="mx-auto flex h-16 w-full max-w-[1240px] items-center justify-between gap-4 px-5 sm:px-8">
                    <a v-if="logoImage || logoText" href="#header" class="flex min-w-0 items-center gap-3" @click="onNavClick($event, '#header')">
                        <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-7 w-auto max-w-[7rem] object-contain" />
                        <span v-if="logoText" class="gi-mono truncate text-[0.8rem] font-medium uppercase tracking-[0.08em]">{{ logoText }}</span>
                    </a>
                    <span v-else />

                    <ul v-if="navLinks.length" class="hidden items-center gap-1 md:flex">
                        <li v-for="l in navLinks" :key="l.id">
                            <a :href="`#${l.id}`" class="gi-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">
                                <span class="gi-mono">{{ numberOf(l.id) }}</span>{{ l.label }}
                            </a>
                        </li>
                    </ul>

                    <a v-if="has('contact')" href="#contact" class="gi-btn gi-btn-primary !px-4 !py-2.5 !text-[0.78rem]" @click="onNavClick($event, '#contact')">
                        {{ text('contact', 'contact_subtitle') ?? text('contact', 'contact_title') }}<ArrowUpRight class="gi-arrow h-4 w-4" />
                    </a>
                    <span v-else />
                </div>
            </nav>
        </header>

        <!-- ============================== HERO ============================== -->
        <section v-if="has('header')" id="header" class="relative isolate flex min-h-[100svh] items-center overflow-hidden">
            <span id="home" class="absolute top-0" aria-hidden="true" />
            <img
                v-if="media('header', 'hero_background_image')"
                :src="media('header', 'hero_background_image')"
                alt=""
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-[0.12] grayscale"
            />
            <div class="gi-columns absolute inset-0 -z-10" aria-hidden="true" />

            <div class="mx-auto w-full max-w-[1240px] px-5 pb-16 pt-32 sm:px-8">
                <div class="gi-meta-row" data-reveal style="--d: 0ms">
                    <p v-if="text('header', 'hero_greeting')" class="gi-label">
                        <span class="gi-pulse" aria-hidden="true" />{{ text('header', 'hero_greeting') }}
                    </p>
                    <span v-if="location" class="gi-mono gi-muted inline-flex items-center gap-2 text-[0.75rem] uppercase tracking-[0.08em]">
                        <MapPin class="h-3.5 w-3.5 text-[var(--accent)]" />{{ location }}
                    </span>
                </div>

                <div class="mt-10 grid items-end gap-12 lg:grid-cols-12 lg:gap-8">
                    <div class="lg:col-span-8">
                        <h1 v-if="heroName" class="gi-display gi-hero-name">
                            <span class="sr-only">{{ heroName }}</span>
                            <span v-for="(w, i) in heroWords" :key="i" class="gi-mask" aria-hidden="true">
                                <span class="gi-word" :style="{ '--i': i }">{{ w }}</span>
                            </span>
                        </h1>
                    </div>

                    <div v-if="media('header', 'hero_image') || heroInitials" class="lg:col-span-4">
                        <figure data-reveal class="gi-portrait relative mx-auto w-[72%] max-w-[20rem] sm:w-full lg:ml-auto lg:max-w-[18rem]" style="--d: 250ms">
                            <div class="gi-photo group">
                                <img
                                    v-if="media('header', 'hero_image')"
                                    :src="media('header', 'hero_image')"
                                    :alt="heroName ?? ''"
                                    class="h-full w-full object-cover grayscale transition duration-[900ms] ease-out group-hover:scale-[1.05] group-hover:grayscale-0"
                                />
                                <span v-else class="gi-display flex h-full w-full items-center justify-center bg-[var(--surface)] text-[6rem] text-[var(--accent)]">{{
                                    heroInitials
                                }}</span>
                            </div>
                            <span class="gi-corner gi-corner-tl" aria-hidden="true" />
                            <span class="gi-corner gi-corner-br" aria-hidden="true" />
                        </figure>
                    </div>
                </div>

                <div class="gi-hairline mt-14" aria-hidden="true" />

                <div class="mt-8 grid gap-8 md:grid-cols-12 md:gap-8">
                    <p v-if="text('header', 'hero_role')" data-reveal class="gi-mono gi-role md:col-span-4" style="--d: 380ms">
                        <ArrowDownRight class="mb-3 h-6 w-6 text-[var(--accent)]" />{{ text('header', 'hero_role') }}
                    </p>
                    <div class="md:col-span-8 lg:col-span-6">
                        <p
                            v-if="text('header', 'hero_description')"
                            data-reveal
                            class="gi-muted max-w-[34rem] whitespace-pre-line text-[1.05rem] leading-[1.65]"
                            style="--d: 460ms"
                        >
                            {{ text('header', 'hero_description') }}
                        </p>

                        <div v-if="heroPrimary || heroSecondary" data-reveal class="mt-8 flex flex-wrap gap-3" style="--d: 540ms">
                            <a v-if="heroPrimary" :href="heroPrimary.url" class="gi-btn gi-btn-primary" @click="onNavClick($event, heroPrimary.url)"
                                >{{ heroPrimary.text }}<ArrowUpRight class="gi-arrow h-4 w-4"
                            /></a>
                            <a v-if="heroSecondary" :href="heroSecondary.url" class="gi-btn gi-btn-ghost" @click="onNavClick($event, heroSecondary.url)">{{
                                heroSecondary.text
                            }}</a>
                        </div>
                    </div>

                    <ul v-if="heroSocials.length" data-reveal class="flex flex-wrap content-start gap-2 md:col-span-12 lg:col-span-2 lg:justify-end" style="--d: 620ms">
                        <li v-for="(s, i) in heroSocials" :key="i">
                            <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="gi-icon-btn">
                                <component :is="socialIconFor(s.platform)" class="h-[18px] w-[18px]" />
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ============================== ABOUT ============================== -->
        <section v-if="has('about')" id="about" class="gi-band scroll-mt-0">
            <div class="mx-auto w-full max-w-[1240px] px-5 py-28 sm:px-8">
                <div data-reveal class="gi-head">
                    <span class="gi-index gi-mono">{{ numberOf('about') }}</span>
                    <p v-if="text('about', 'about_subtitle')" class="gi-kicker">{{ text('about', 'about_subtitle') }}</p>
                </div>

                <div class="mt-10 grid items-start gap-12 lg:grid-cols-12 lg:gap-10">
                    <div v-if="text('about', 'about_title')" class="lg:col-span-7">
                        <h2 data-reveal class="gi-display gi-h2">{{ text('about', 'about_title') }}</h2>
                    </div>

                    <div v-if="media('about', 'about_image')" data-reveal class="lg:col-span-4 lg:col-start-9 lg:row-span-2">
                        <div class="gi-frame mx-auto max-w-md">
                            <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="aspect-[4/5] w-full object-cover grayscale" loading="lazy" />
                        </div>
                    </div>

                    <div class="lg:col-span-7">
                        <div data-reveal>
                            <p v-if="text('about', 'about_description')" class="gi-muted max-w-[38rem] whitespace-pre-line text-[1.05rem] leading-[1.75]">
                                {{ text('about', 'about_description') }}
                            </p>
                        </div>

                        <ul v-if="highlights.length" data-reveal class="gi-cards mt-12 grid sm:grid-cols-2">
                            <li v-for="(h, i) in highlights" :key="i" :style="{ '--k': i }" class="gi-hl">
                                <span class="gi-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>
                                <span>
                                    <span class="block font-semibold leading-snug">{{ h.title }}</span>
                                    <span v-if="h.description" class="gi-muted mt-1 block text-[0.9rem] leading-relaxed">{{ h.description }}</span>
                                </span>
                            </li>
                        </ul>

                        <div v-if="cvFile || aboutCta" data-reveal class="mt-10 flex flex-wrap gap-3">
                            <a v-if="cvFile" :href="cvFile" download target="_blank" rel="noopener" class="gi-btn gi-btn-primary">
                                <Download class="h-4 w-4" />Download CV</a>
                            <a
                                v-if="aboutCta && cvFile"
                                :href="aboutCta.url"
                                class="gi-btn gi-btn-ghost"
                                @click="onNavClick($event, aboutCta.url)"
                            >
                                {{ aboutCta.text }}<ArrowUpRight class="gi-arrow h-4 w-4" />
                            </a>
                            <a v-else-if="aboutCta" :href="aboutCta.url" class="gi-btn gi-btn-primary" @click="onNavClick($event, aboutCta.url)">
                                {{ aboutCta.text }}<ArrowUpRight class="gi-arrow h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================== JOURNEY ============================== -->
        <section v-if="has('journey')" id="journey" class="scroll-mt-0">
            <div class="mx-auto w-full max-w-[1240px] px-5 py-28 sm:px-8">
                <div data-reveal class="gi-head">
                    <span class="gi-index gi-mono">{{ numberOf('journey') }}</span>
                    <p v-if="text('journey', 'journey_subtitle')" class="gi-kicker">{{ text('journey', 'journey_subtitle') }}</p>
                </div>
                <h2 v-if="text('journey', 'journey_title')" data-reveal class="gi-display gi-h2 mt-6 max-w-4xl">{{ text('journey', 'journey_title') }}</h2>

                <ol class="mt-14 border-t border-[var(--ink)]">
                    <li
                        v-for="(item, index) in journey"
                        :key="index"
                        data-reveal
                        :style="{ '--d': `${(index % 4) * 70}ms` }"
                        class="gi-tl grid gap-3 border-b border-[var(--line)] py-8 md:grid-cols-12 md:gap-8"
                    >
                        <p v-if="item.period" class="gi-mono text-[0.78rem] font-medium uppercase tracking-wider text-[var(--accent)] md:col-span-3">{{ item.period }}</p>
                        <div class="md:col-span-4">
                            <h3 v-if="item.title" class="gi-display text-2xl leading-tight">{{ item.title }}</h3>
                            <p v-if="item.company" class="gi-mono gi-muted mt-2 text-[0.8rem] uppercase tracking-wider">{{ item.company }}</p>
                        </div>
                        <p v-if="item.description" class="gi-muted whitespace-pre-line leading-[1.7] md:col-span-5">{{ item.description }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ============================== SKILLS ============================== -->
        <section v-if="has('skills')" id="skills" class="gi-band scroll-mt-0">
            <div class="mx-auto grid w-full max-w-[1240px] gap-12 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-10">
                <div class="lg:col-span-4">
                    <div data-reveal class="lg:sticky lg:top-28">
                        <div class="gi-head">
                            <span class="gi-index gi-mono">{{ numberOf('skills') }}</span>
                            <p v-if="text('skills', 'skills_subtitle')" class="gi-kicker">{{ text('skills', 'skills_subtitle') }}</p>
                        </div>
                        <h2 v-if="text('skills', 'skills_title')" class="gi-display gi-h2 mt-6">{{ text('skills', 'skills_title') }}</h2>
                        <p v-if="text('skills', 'skills_description')" class="gi-muted mt-6 whitespace-pre-line leading-[1.7]">{{ text('skills', 'skills_description') }}</p>
                    </div>
                </div>

                <ul class="gi-cards grid content-start sm:grid-cols-2 lg:col-span-8">
                    <li v-for="(s, i) in skills" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 50}ms` }" class="gi-skill">
                        <div class="flex items-center gap-3.5">
                            <span v-if="showIcon" class="gi-tile">
                                <component :is="iconFor(s.icon)" v-if="iconFor(s.icon)" class="h-[18px] w-[18px]" />
                                <span v-else-if="isGlyph(s.icon)" class="text-lg leading-none" aria-hidden="true">{{ s.icon }}</span>
                                <span v-else class="gi-display text-base leading-none" aria-hidden="true">{{ s.name.charAt(0).toUpperCase() }}</span>
                            </span>
                            <h3 class="font-semibold">{{ s.name }}</h3>
                            <span
                                v-if="showLevel && s.pct !== null"
                                class="gi-count gi-mono gi-muted ml-auto text-[0.8rem] font-medium"
                                :style="{ '--p': Math.round(s.pct) }"
                                aria-hidden="true"
                            />
                            <span v-else-if="showLevel && s.label" class="gi-pill ml-auto">{{ s.label }}</span>
                        </div>
                        <div
                            v-if="showLevel && s.pct !== null"
                            class="gi-track mt-5"
                            role="progressbar"
                            :aria-valuenow="Math.round(s.pct)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="s.name"
                        >
                            <div class="gi-bar" :style="{ '--w': `${s.pct}%` }" />
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== PROJECTS ============================== -->
        <section v-if="has('projects')" id="projects" class="relative scroll-mt-0">
            <div class="mx-auto w-full max-w-[1240px] px-5 py-28 sm:px-8">
                <div data-reveal class="gi-head">
                    <span class="gi-index gi-mono">{{ numberOf('projects') }}</span>
                    <p v-if="text('projects', 'projects_subtitle')" class="gi-kicker">{{ text('projects', 'projects_subtitle') }}</p>
                </div>
                <div data-reveal class="mb-16 mt-6 grid gap-6 lg:grid-cols-12 lg:items-end">
                    <h2 v-if="text('projects', 'projects_title')" class="gi-display gi-h2 lg:col-span-8">{{ text('projects', 'projects_title') }}</h2>
                    <p v-if="text('projects', 'projects_description')" class="gi-muted whitespace-pre-line leading-[1.7] lg:col-span-4">
                        {{ text('projects', 'projects_description') }}
                    </p>
                </div>

                <ul class="grid gap-x-6 gap-y-14 md:grid-cols-2 xl:grid-cols-3">
                    <li
                        v-for="(p, i) in projects"
                        :key="i"
                        data-reveal
                        :class="featureFirst && i === 0 ? 'md:col-span-2 xl:col-span-2' : ''"
                        :style="{ '--d': `${(i % 3) * 80}ms` }"
                    >
                        <article class="gi-project group flex h-full flex-col">
                            <div class="gi-mono mb-3 flex items-center justify-between border-t border-[var(--ink)] pt-3 text-[0.72rem] uppercase tracking-wider">
                                <span>{{ String(i + 1).padStart(2, '0') }}</span>
                                <span v-if="featureFirst && i === 0" class="gi-flag">{{ numberOf('projects') }}&nbsp;/&nbsp;★</span>
                            </div>
                            <component
                                :is="p.live ? 'a' : 'div'"
                                :href="p.live || undefined"
                                :target="p.live ? '_blank' : undefined"
                                :rel="p.live ? 'noopener noreferrer' : undefined"
                                :aria-label="p.live ? p.title : undefined"
                                class="gi-media relative block overflow-hidden"
                                :class="featureFirst && i === 0 ? 'aspect-[16/9]' : 'aspect-[4/3]'"
                            >
                                <img v-if="p.image" :src="p.image" :alt="p.title" class="h-full w-full object-cover" loading="lazy" />
                                <div v-else class="gi-display gi-tone flex h-full w-full items-center justify-center text-8xl" :class="`gi-tone-${i % 3}`">
                                    {{ p.title.charAt(0).toUpperCase() }}
                                </div>
                                <span v-if="p.live" class="gi-go" aria-hidden="true"><ArrowUpRight class="h-5 w-5" /></span>
                            </component>

                            <div class="flex flex-1 flex-col pt-5">
                                <h3 class="gi-display text-2xl leading-tight"><span class="gi-underline">{{ p.title }}</span></h3>
                                <p v-if="p.description" class="gi-muted mt-3 leading-[1.65]">{{ p.description }}</p>
                                <ul v-if="p.tags.length" class="mt-4 flex flex-wrap gap-2">
                                    <li v-for="(t, ti) in p.tags" :key="ti" class="gi-pill">{{ t }}</li>
                                </ul>
                                <div v-if="p.repo" class="mt-auto pt-5">
                                    <a :href="p.repo" target="_blank" rel="noopener noreferrer" class="gi-textlink gi-mono text-[0.8rem] uppercase tracking-wider"
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
        <section v-if="has('services')" id="services" class="gi-band scroll-mt-0">
            <div class="mx-auto w-full max-w-[1240px] px-5 py-28 sm:px-8">
                <div data-reveal class="gi-head">
                    <span class="gi-index gi-mono">{{ numberOf('services') }}</span>
                    <p v-if="text('services', 'services_subtitle')" class="gi-kicker">{{ text('services', 'services_subtitle') }}</p>
                </div>
                <h2 v-if="text('services', 'services_title')" data-reveal class="gi-display gi-h2 mb-14 mt-6 max-w-4xl">{{ text('services', 'services_title') }}</h2>

                <ul class="border-t border-[var(--ink)]">
                    <li v-for="(s, i) in services" :key="i" data-reveal :style="{ '--d': `${(i % 5) * 70}ms` }">
                        <div class="gi-row group grid items-center gap-4 border-b border-[var(--line)] md:grid-cols-12">
                            <span class="gi-mono hidden text-sm text-[var(--accent)] md:col-span-1 md:block">{{ String(i + 1).padStart(2, '0') }}</span>
                            <div class="flex items-center gap-5 md:col-span-5">
                                <span class="gi-tile gi-row-tile">
                                    <component :is="iconFor(s.icon) ?? Sparkles" v-if="!isGlyph(s.icon)" class="h-5 w-5" />
                                    <span v-else class="text-xl leading-none" aria-hidden="true">{{ s.icon }}</span>
                                </span>
                                <h3 class="gi-display gi-row-title text-2xl leading-tight sm:text-[1.75rem]">{{ s.title }}</h3>
                            </div>
                            <p v-if="s.description" class="gi-muted leading-[1.65] md:col-span-5 md:text-[1rem]">{{ s.description }}</p>
                            <ArrowUpRight class="gi-row-arrow hidden h-6 w-6 justify-self-end md:block" aria-hidden="true" />
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== CONTACT ============================== -->
        <section v-if="has('contact')" id="contact" class="scroll-mt-0 pt-24">
            <div data-reveal class="gi-contact">
                <div class="relative z-10 mx-auto grid w-full max-w-[1240px] gap-14 px-5 py-20 sm:px-8 lg:grid-cols-12 lg:gap-10 lg:py-28">
                    <div class="lg:col-span-7">
                        <div data-reveal>
                            <div class="gi-head">
                                <span class="gi-index gi-mono">{{ numberOf('contact') }}</span>
                                <p v-if="text('contact', 'contact_subtitle')" class="gi-kicker">{{ text('contact', 'contact_subtitle') }}</p>
                            </div>
                            <h2 v-if="text('contact', 'contact_title')" class="gi-display mt-6 text-[clamp(2.75rem,8vw,6.5rem)] leading-[0.93] [overflow-wrap:anywhere]">
                                {{ text('contact', 'contact_title') }}
                            </h2>
                            <p v-if="text('contact', 'contact_description')" class="gi-muted mt-7 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7]">
                                {{ text('contact', 'contact_description') }}
                            </p>
                        </div>

                        <div v-if="email || phone || location" data-reveal class="mt-10 space-y-5">
                            <a v-if="email" :href="`mailto:${email}`" class="gi-bigmail gi-display group inline-flex max-w-full items-center gap-3 break-all text-2xl sm:text-4xl">
                                <Mail class="h-7 w-7 shrink-0 text-[var(--accent)]" /><span class="gi-underline-contact">{{ email }}</span>
                                <ArrowUpRight class="gi-arrow h-7 w-7 shrink-0" />
                            </a>
                            <div class="gi-mono flex flex-wrap gap-x-8 gap-y-3 text-[0.85rem] uppercase tracking-wider">
                                <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="gi-contact-link"><Phone class="h-4 w-4 text-[var(--accent)]" />{{ phone }}</a>
                                <span v-if="location" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4 text-[var(--accent)]" />{{ location }}</span>
                            </div>
                        </div>

                        <ul v-if="contactSocials.length" data-reveal class="mt-8 flex flex-wrap gap-2">
                            <li v-for="(s, i) in contactSocials" :key="i">
                                <a :href="s.url" target="_blank" rel="noopener noreferrer" class="gi-social">
                                    <component :is="socialIconFor(s.platform)" class="h-3.5 w-3.5" />{{ s.label }}<ArrowUpRight class="gi-arrow h-3.5 w-3.5" />
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
                                target="gi-gform-frame"
                                class="gi-form space-y-6 p-6 sm:p-8"
                                @submit="gf.url && gf.name && gf.email && gf.message ? onSubmit($event) : handleContactFormFallback($event, text('contact', 'contact_email'))"
                            >
                                <label class="block">
                                    <span class="gi-mono gi-muted mb-2 block text-[0.72rem] font-medium uppercase tracking-wider">Name</span>
                                    <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="gi-input" />
                                </label>
                                <label class="block">
                                    <span class="gi-mono gi-muted mb-2 block text-[0.72rem] font-medium uppercase tracking-wider">Email</span>
                                    <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="gi-input" />
                                </label>
                                <label class="block">
                                    <span class="gi-mono gi-muted mb-2 block text-[0.72rem] font-medium uppercase tracking-wider">Message</span>
                                    <textarea :name="`entry.${gf.message}`" rows="5" required class="gi-input resize-y" />
                                </label>
                                <button type="submit" class="gi-btn gi-btn-primary w-full justify-center" :disabled="sending">
                                    {{ sending ? '…' : 'Send' }}<Send class="gi-arrow h-4 w-4" />
                                </button>
                                <p v-if="sent" role="status" class="gi-sent flex items-center gap-2 text-sm font-semibold">
                                    <span class="gi-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>OK
                                </p>
                            </form>
                            <a v-else-if="email" :href="`mailto:${email}`" class="gi-btn gi-btn-ghost w-full justify-center !py-5 text-lg"><Mail class="h-5 w-5" />{{ email }}</a>
                        </div>
                        <iframe name="gi-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                    </div>
                </div>
            </div>

            <footer v-if="heroName || logoText" class="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-2 px-5 py-8 text-sm sm:px-8">
                <span class="gi-muted gi-mono text-[0.78rem] uppercase tracking-wider">&copy; {{ year }} {{ heroName ?? logoText }}</span>
                <a href="#header" class="gi-textlink" @click="onNavClick($event, '#header')"><ArrowUpRight class="gi-arrow h-4 w-4 -rotate-45" /></a>
            </footer>
        </section>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

/* ---------- Foundations ---------- */
.gi-root {
    background: var(--paper);
    color: var(--ink);
    font-family: 'Inter Tight', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    transition:
        background-color 0.4s ease,
        color 0.4s ease;
}
/* Light palette (OS preference or explicit light class on an ancestor) */
@media (prefers-color-scheme: light) {
    .gi-theme:not(.dark) {
        --paper: #fafafa;
        --soft: #f1f1f3;
        --surface: #ffffff;
        --ink: #0f0f11;
        --muted: #52525b;
        --line: #d4d4d8;
        --accent: #d13a3a;
        --accent-ink: #ffffff;
    }
}
:global(.dark) .gi-theme {
    --paper: #0f0f11;
    --soft: #131316;
    --surface: #18181b;
    --ink: #fafafa;
    --muted: #a1a1aa;
    --line: #27272a;
    --accent: #e05252;
    --accent-ink: #0f0f11;
}
:global(.light) .gi-theme {
    --paper: #fafafa;
    --soft: #f1f1f3;
    --surface: #ffffff;
    --ink: #0f0f11;
    --muted: #52525b;
    --line: #d4d4d8;
    --accent: #d13a3a;
    --accent-ink: #ffffff;
}
.gi-display {
    font-family: 'Inter Tight', ui-sans-serif, system-ui, sans-serif;
    font-weight: 700;
    letter-spacing: -0.045em;
}
.gi-mono {
    font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
    letter-spacing: 0;
}
.gi-muted {
    color: var(--muted);
}
.gi-h2 {
    font-size: clamp(2.25rem, 5.2vw, 4.5rem);
    line-height: 0.98;
    letter-spacing: -0.05em;
    text-wrap: balance;
}
.gi-band {
    background: var(--soft);
    border-block: 1px solid var(--line);
}
.gi-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
}
.gi-columns {
    background-image: linear-gradient(to right, var(--line) 1px, transparent 1px);
    background-size: calc(100% / 12) 100%;
    opacity: 0.4;
    mask-image: linear-gradient(to bottom, transparent, #000 20%, #000 80%, transparent);
    -webkit-mask-image: linear-gradient(to bottom, transparent, #000 20%, #000 80%, transparent);
}
.gi-hairline {
    height: 1px;
    background: var(--ink);
    transform-origin: left;
}
.gi-meta-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 1px solid var(--line);
    padding-bottom: 1rem;
}

/* Section header: number + rule + subtitle */
.gi-head {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.gi-index {
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--accent);
}
.gi-kicker {
    display: inline-flex;
    align-items: center;
    gap: 1rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.gi-kicker::before {
    content: '';
    width: 2.5rem;
    height: 1px;
    background: currentColor;
    transform-origin: left;
    transition: transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) 0.2s;
}
.gi-js [data-reveal]:not(.is-in) .gi-kicker::before {
    transform: scaleX(0);
}

/* Elevated monochrome card grid with hairline gutters */
.gi-cards {
    gap: 1px;
    background: var(--line);
    border: 1px solid var(--line);
}
.gi-cards > li {
    background: var(--surface);
}

/* ---------- Navbar ---------- */
.gi-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 2px;
    background: var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
    z-index: 60;
}
.gi-nav {
    border-bottom: 1px solid transparent;
    transition:
        border-color 0.35s ease,
        background-color 0.35s ease;
}
.gi-nav.is-scrolled {
    border-color: var(--line);
    background: color-mix(in srgb, var(--paper) 88%, transparent);
    backdrop-filter: blur(14px) saturate(1.3);
    -webkit-backdrop-filter: blur(14px) saturate(1.3);
}
.gi-navlink {
    display: inline-flex;
    align-items: baseline;
    gap: 0.45rem;
    padding: 0.5rem 0.9rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--muted);
    border: 1px solid transparent;
    transition:
        color 0.25s ease,
        border-color 0.25s ease,
        background-color 0.25s ease;
}
.gi-navlink .gi-mono {
    font-size: 0.66rem;
    color: var(--accent);
}
.gi-navlink:hover {
    color: var(--ink);
}
.gi-navlink[data-active='true'] {
    color: var(--ink);
    border-color: var(--line);
    background: var(--surface);
}

/* ---------- Buttons & small controls ---------- */
.gi-btn {
    position: relative;
    isolation: isolate;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0;
    padding: 0.95rem 1.5rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.78rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.06em;
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
        border-color 0.3s ease;
}
.gi-btn:hover {
    background-size: 100% 100%;
    transform: translate(-2px, -2px);
}
.gi-btn:active {
    transform: translate(0, 0) scale(0.98);
}
.gi-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.gi-btn-primary {
    background-color: var(--accent);
    background-image: linear-gradient(var(--ink), var(--ink));
    color: var(--accent-ink);
}
.gi-btn-primary:hover {
    color: var(--paper);
}
.gi-btn-ghost {
    background-color: transparent;
    background-image: linear-gradient(var(--ink), var(--ink));
    border-color: var(--ink);
    color: var(--ink);
}
.gi-btn-ghost:hover {
    color: var(--paper);
}
.gi-arrow {
    transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-btn:hover .gi-arrow,
.gi-bigmail:hover .gi-arrow,
.gi-social:hover .gi-arrow,
.gi-textlink:hover .gi-arrow {
    transform: translate(3px, -3px);
}
.gi-icon-btn {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--line);
    background: var(--surface);
    transition:
        transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1),
        background-color 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease;
}
.gi-icon-btn:hover {
    transform: translateY(-3px);
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
}
.gi-label {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.gi-pulse {
    position: relative;
    height: 0.5rem;
    width: 0.5rem;
    background: var(--accent);
}
.gi-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    background: inherit;
    animation: gi-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.gi-pill {
    display: inline-block;
    border: 1px solid var(--line);
    padding: 0.2rem 0.6rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--muted);
}
.gi-tick {
    display: inline-flex;
    height: 1.35rem;
    width: 1.35rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    background: var(--accent);
    color: var(--accent-ink);
}
.gi-textlink {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    background: linear-gradient(currentColor, currentColor) left bottom / 0% 1.5px no-repeat;
    transition: background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 2px;
}
.gi-textlink:hover {
    background-size: 100% 1.5px;
}

/* ---------- Hero ---------- */
.gi-hero-name {
    font-size: clamp(3.5rem, 12vw, 10.5rem);
    line-height: 0.86;
    letter-spacing: -0.065em;
    overflow-wrap: anywhere;
}
.gi-mask {
    display: inline-block;
    overflow: hidden;
    vertical-align: top;
    padding-bottom: 0.12em;
    margin-bottom: -0.12em;
    margin-right: 0.2em;
}
.gi-word {
    display: inline-block;
}
.gi-js .gi-word {
    transform: translateY(110%);
    animation: gi-rise 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: calc(0.15s + var(--i, 0) * 0.1s);
}
.gi-role {
    font-size: 0.85rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    line-height: 1.55;
}
.gi-photo {
    position: relative;
    aspect-ratio: 4 / 5;
    overflow: hidden;
    background: var(--surface);
    border: 1px solid var(--line);
}
.gi-corner {
    position: absolute;
    height: 1.25rem;
    width: 1.25rem;
    border: 2px solid var(--accent);
    pointer-events: none;
    transition: transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-corner-tl {
    left: -0.6rem;
    top: -0.6rem;
    border-right: 0;
    border-bottom: 0;
}
.gi-corner-br {
    right: -0.6rem;
    bottom: -0.6rem;
    border-left: 0;
    border-top: 0;
}
.gi-portrait:hover .gi-corner-tl {
    transform: translate(-0.3rem, -0.3rem);
}
.gi-portrait:hover .gi-corner-br {
    transform: translate(0.3rem, 0.3rem);
}

/* ---------- About ---------- */
.gi-frame {
    position: relative;
    padding: 0.5rem;
    border: 1px solid var(--line);
    background: var(--surface);
    transition:
        transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1),
        box-shadow 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-frame:hover {
    transform: translate(-4px, -4px);
    box-shadow: 8px 8px 0 var(--accent);
}
.gi-frame img {
    transition: filter 0.6s ease;
}
.gi-frame:hover img {
    filter: grayscale(0);
}
.gi-hl {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 1.4rem 1.25rem;
    transition:
        background-color 0.3s ease,
        padding-left 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-hl:hover {
    background: var(--soft);
    padding-left: 1.75rem;
}

/* ---------- Journey ---------- */
.gi-tl {
    position: relative;
}
.gi-tl::before {
    content: '';
    position: absolute;
    left: 0;
    top: -1px;
    height: 2px;
    width: 100%;
    background: var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-tl:hover::before {
    transform: scaleX(1);
}

/* ---------- Skills ---------- */
.gi-skill {
    padding: 1.5rem 1.25rem;
    transition: background-color 0.3s ease;
}
.gi-skill:hover {
    background: var(--soft);
}
.gi-tile {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    background: var(--paper);
    border: 1px solid var(--line);
    color: var(--accent);
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-skill:hover .gi-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.gi-track {
    position: relative;
    height: 3px;
    width: 100%;
    background: var(--line);
}
.gi-bar {
    position: relative;
    height: 100%;
    width: var(--w, 0%);
    background: var(--accent);
}
.gi-bar::after {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    height: 11px;
    width: 11px;
    transform: translate(50%, -50%);
    background: var(--ink);
}
.gi-js [data-reveal] .gi-bar {
    width: 0;
    transition: width 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s);
}
.gi-js [data-reveal].is-in .gi-bar {
    width: var(--w, 0%);
}

/* Percentage counts up in sync with the bar */
@property --n {
    syntax: '<integer>';
    inherits: false;
    initial-value: 0;
}
.gi-count {
    --n: var(--p, 0);
    counter-reset: n var(--n);
    font-variant-numeric: tabular-nums;
}
.gi-count::after {
    content: counter(n) '%';
}
.gi-js [data-reveal] .gi-count {
    --n: 0;
}
.gi-js [data-reveal].is-in .gi-count {
    animation: gi-count 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s) forwards;
}

/* ---------- Projects ---------- */
.gi-media {
    background: var(--surface);
    border: 1px solid var(--line);
    isolation: isolate;
    transition: border-color 0.3s ease;
}
.gi-project:hover .gi-media {
    border-color: var(--accent);
}
.gi-media img,
.gi-tone {
    transition: transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-project:hover .gi-media img,
.gi-project:hover .gi-tone {
    transform: scale(1.05);
}
.gi-tone-0 {
    background: var(--accent);
    color: var(--accent-ink);
}
.gi-tone-1 {
    background: var(--surface);
    color: var(--ink);
}
.gi-tone-2 {
    background: var(--ink);
    color: var(--paper);
}
.gi-flag {
    background: var(--accent);
    color: var(--accent-ink);
    padding: 0.15rem 0.6rem;
    font-weight: 500;
}
.gi-go {
    position: absolute;
    right: 0;
    top: 0;
    display: inline-flex;
    height: 3rem;
    width: 3rem;
    align-items: center;
    justify-content: center;
    background: var(--accent);
    color: var(--accent-ink);
    opacity: 0;
    transform: translate(0.5rem, -0.5rem);
    transition:
        opacity 0.35s ease,
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-project:hover .gi-go,
.gi-media:focus-visible .gi-go {
    opacity: 1;
    transform: none;
}
.gi-underline {
    background: linear-gradient(currentColor, currentColor) left 96% / 0% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-project:hover .gi-underline {
    background-size: 100% 2px;
}

/* ---------- Services ---------- */
.gi-row {
    position: relative;
    padding: 1.75rem 0.5rem;
    transition:
        background-color 0.35s ease,
        padding 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-row:hover {
    background: var(--surface);
    padding-inline: 1.25rem;
}
.gi-row-title {
    transition: color 0.3s ease;
}
.gi-row:hover .gi-row-title {
    color: var(--accent);
}
.gi-row:hover .gi-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.gi-row-arrow {
    color: var(--accent);
    opacity: 0;
    transform: translate(-0.75rem, 0.75rem);
    transition:
        opacity 0.3s ease,
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-row:hover .gi-row-arrow {
    opacity: 1;
    transform: none;
}

/* ---------- Contact ---------- */
.gi-contact {
    position: relative;
    overflow: hidden;
    background: var(--surface);
    border-top: 1px solid var(--ink);
    border-bottom: 1px solid var(--line);
}
.gi-contact::before {
    content: '';
    position: absolute;
    right: -6rem;
    top: -6rem;
    height: 22rem;
    width: 22rem;
    border: 1px solid var(--accent);
    opacity: 0.35;
    transform: rotate(18deg);
    pointer-events: none;
    animation: gi-drift 18s ease-in-out infinite;
}
.gi-contact::after {
    content: '';
    position: absolute;
    right: -2rem;
    top: -2rem;
    height: 12rem;
    width: 12rem;
    background: var(--accent);
    opacity: 0.1;
    transform: rotate(18deg);
    pointer-events: none;
    animation: gi-drift 24s ease-in-out infinite reverse;
}
.gi-underline-contact {
    background: linear-gradient(currentColor, currentColor) left bottom / 100% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 4px;
}
.gi-bigmail:hover .gi-underline-contact {
    background-size: 0% 2px;
    background-position: right bottom;
}
.gi-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.gi-contact-link:hover {
    text-decoration: underline;
    text-underline-offset: 4px;
}
.gi-social {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid var(--line);
    background: var(--paper);
    padding: 0.5rem 0.9rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        border-color 0.3s ease,
        transform 0.3s ease;
}
.gi-social:hover {
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
    transform: translateY(-2px);
}
.gi-form {
    border: 1px solid var(--line);
    background: var(--paper);
    box-shadow: 0 30px 60px -36px #000000aa;
}
.gi-input {
    width: 100%;
    border: 0;
    border-bottom: 1px solid var(--line);
    background: transparent;
    padding: 0.7rem 0;
    font: inherit;
    font-weight: 500;
    color: var(--ink);
    border-radius: 0;
    transition:
        border-color 0.25s ease,
        box-shadow 0.25s ease;
}
.gi-input:hover {
    border-color: var(--muted);
}
.gi-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 1px 0 var(--accent);
}
.gi-sent {
    animation: gi-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.gi-js [data-reveal] {
    opacity: 0;
    transform: translateY(22px);
    transition:
        opacity 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.gi-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}

/* ---------- Section animations ---------- */
.gi-h2 {
    transition: letter-spacing 1.2s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gi-js [data-reveal]:not(.is-in) .gi-h2,
.gi-js [data-reveal]:not(.is-in).gi-h2 {
    letter-spacing: 0.005em;
}
.gi-js [data-reveal].is-in .gi-frame {
    animation: gi-wipe 1.1s cubic-bezier(0.7, 0, 0.2, 1) backwards;
}
.gi-js [data-reveal]:not(.is-in) .gi-hl {
    opacity: 0;
}
.gi-js [data-reveal].is-in .gi-hl {
    animation: gi-slide-x 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--k, 0) * 90ms + 150ms) backwards;
}
.gi-js [data-reveal].is-in .gi-hl .gi-tick {
    animation: gi-tick 0.6s cubic-bezier(0.3, 1.6, 0.4, 1) calc(var(--k, 0) * 90ms + 450ms) backwards;
}
.gi-js [data-reveal].is-in .gi-tile {
    animation: gi-tile 0.7s cubic-bezier(0.3, 1.5, 0.4, 1) calc(var(--d, 0ms) + 0.15s) backwards;
}
.gi-js [data-reveal].is-in .gi-media {
    animation: gi-wipe 1.2s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}
.gi-js [data-reveal].is-in .gi-media img {
    animation: gi-settle 1.6s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s backwards;
}

@keyframes gi-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes gi-enter {
    from {
        opacity: 0;
    }
}
@keyframes gi-ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}
@keyframes gi-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}
@keyframes gi-count {
    to {
        --n: var(--p);
    }
}
@keyframes gi-wipe {
    from {
        clip-path: inset(0 0 100% 0);
    }
}
@keyframes gi-slide-x {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
}
@keyframes gi-tick {
    from {
        transform: scale(0) rotate(-90deg);
    }
}
@keyframes gi-tile {
    from {
        transform: scale(0.4) rotate(-20deg);
        opacity: 0;
    }
}
@keyframes gi-settle {
    from {
        transform: scale(1.25);
    }
}
@keyframes gi-drift {
    50% {
        transform: translate(-2.5rem, 2rem) rotate(28deg);
    }
}
.gi-js.gi-root {
    animation: gi-enter 0.7s ease both;
}

/* ---------- Reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    .gi-root *,
    .gi-root *::before,
    .gi-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .gi-js .gi-word,
    .gi-js [data-reveal],
    .gi-js [data-reveal]:not(.is-in) .gi-hl {
        opacity: 1;
        transform: none;
    }
    .gi-js [data-reveal] .gi-count {
        --n: var(--p, 0);
    }
    .gi-js [data-reveal] .gi-bar {
        width: var(--w, 0%);
    }
    .gi-js [data-reveal]:not(.is-in) .gi-kicker::before {
        transform: none;
    }
    .gi-js [data-reveal]:not(.is-in) .gi-h2,
    .gi-js [data-reveal]:not(.is-in).gi-h2 {
        letter-spacing: -0.05em;
    }
}
</style>
