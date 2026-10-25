<script setup lang="ts">
/**
 * Stream Bands — quickFolio template
 * Slug suggestion: `stream-bands`
 *
 * Asymmetric horizontal section stream: ultra-wide 8-column hero with an overlapping floating
 * mask frame, full-bleed bands alternating paper / surface like slides, giant outline watermark
 * titles, staggered 3-column asymmetric cards, offset overlapping project cards with vertical
 * tag ribbons and a horizontal timeline progression. Light + dark via Tailwind `dark:`.
 *
 * Every visible value comes from the seeded field keys. Only small UI chrome labels are hardcoded.
 */
import { resolvePortfolioMediaUrl } from '@/lib/media';
import { handleContactFormFallback } from '@/lib/contact-form-fallback';
import {
    ArrowRight,
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

// Band alternation follows whichever sections are visible after the hero
const orderedIds = ['about', 'journey', 'skills', 'projects', 'services', 'contact'];
const bandOf = (id: string) => {
    const visible = orderedIds.filter((s) => has(s));
    return visible.indexOf(id) % 2 === 0 ? 'is-paper' : 'is-surface';
};
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
        class="sb-root [--paper:#F4F1EA] [--soft:#EAE6DB] [--surface:#FFFFFF] [--ink:#14130F] [--muted:#14130FAD] [--line:#14130F26] [--accent:#D9480F] [--accent-ink:#FFFFFF] selection:bg-[var(--accent)] selection:text-white dark:[--paper:#0E0D0B] dark:[--soft:#15130F] dark:[--surface:#1A1814] dark:[--ink:#F3EFE6] dark:[--muted:#F3EFE6AD] dark:[--line:#F3EFE62B] dark:[--accent:#FF7A3D] dark:[--accent-ink:#0E0D0B]"
        :class="{ 'sb-js': ready }"
    >
        <!-- ============================ NAVBAR ============================ -->
        <header v-if="logoImage || logoText || navLinks.length || has('contact')" class="inset-x-0 top-0 z-50" :class="stickyNav ? 'fixed' : 'absolute'">
            <div ref="progressEl" class="sb-progress" aria-hidden="true" />
            <nav ref="navEl" class="sb-nav" aria-label="Primary">
                <div class="mx-auto flex h-16 w-full max-w-[1320px] items-center justify-between gap-4 px-5 sm:px-8">
                    <a v-if="logoImage || logoText" href="#header" class="flex min-w-0 items-center gap-3" @click="onNavClick($event, '#header')">
                        <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-7 w-auto max-w-[7rem] object-contain" />
                        <span v-if="logoText" class="sb-display truncate text-lg">{{ logoText }}</span>
                    </a>
                    <span v-else />

                    <ul v-if="navLinks.length" class="hidden items-center gap-1 md:flex">
                        <li v-for="l in navLinks" :key="l.id">
                            <a :href="`#${l.id}`" class="sb-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a>
                        </li>
                    </ul>

                    <a v-if="has('contact')" href="#contact" class="sb-btn sb-btn-primary !px-5 !py-2.5 !text-sm" @click="onNavClick($event, '#contact')">Contact me<ArrowUpRight class="sb-arrow h-4 w-4" />
                    </a>
                    <span v-else />
                </div>
            </nav>
        </header>

        <!-- ============================== HERO ============================== -->
        <section v-if="has('header')" id="header" class="sb-hero relative isolate flex min-h-[100svh] items-center overflow-hidden">
            <span id="home" class="absolute top-0" aria-hidden="true" />
            <img
                v-if="media('header', 'hero_background_image')"
                :src="media('header', 'hero_background_image')"
                alt=""
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-[0.1]"
            />
            <span v-if="heroName" class="sb-watermark sb-watermark-hero" aria-hidden="true">{{ heroWords[0] }}</span>

            <div class="mx-auto grid w-full max-w-[1320px] items-center gap-12 px-5 pb-20 pt-36 sm:px-8 lg:grid-cols-12 lg:gap-0 lg:pt-32">
                <div class="relative z-10 lg:col-span-8">
                    <p v-if="text('header', 'hero_greeting')" data-reveal class="sb-label" style="--d: 0ms">
                        <span class="sb-pulse" aria-hidden="true" />{{ text('header', 'hero_greeting') }}
                    </p>

                    <h1 v-if="heroName" class="sb-display sb-hero-name mt-7">
                        <span class="sr-only">{{ heroName }}</span>
                        <span v-for="(w, i) in heroWords" :key="i" class="sb-mask" aria-hidden="true">
                            <span class="sb-word" :class="{ 'sb-outline-text': i % 2 === 1 }" :style="{ '--i': i }">{{ w }}</span>
                        </span>
                    </h1>

                    <div class="mt-8 grid gap-6 md:grid-cols-12 md:gap-8">
                        <p v-if="text('header', 'hero_role')" data-reveal class="sb-mono sb-role md:col-span-4" style="--d: 380ms">
                            <span class="sb-bar-mark" aria-hidden="true" />{{ text('header', 'hero_role') }}
                        </p>
                        <div class="md:col-span-8">
                            <p
                                v-if="text('header', 'hero_description')"
                                data-reveal
                                class="sb-muted max-w-[36rem] whitespace-pre-line text-lg leading-[1.7]"
                                style="--d: 460ms"
                            >
                                {{ text('header', 'hero_description') }}
                            </p>

                            <div v-if="heroPrimary || heroSecondary" data-reveal class="mt-8 flex flex-wrap gap-3" style="--d: 540ms">
                                <a v-if="heroPrimary" :href="heroPrimary.url" class="sb-btn sb-btn-primary" @click="onNavClick($event, heroPrimary.url)"
                                    >{{ heroPrimary.text }}<ArrowRight class="sb-arrow h-4 w-4"
                                /></a>
                                <a v-if="heroSecondary" :href="heroSecondary.url" class="sb-btn sb-btn-ghost" @click="onNavClick($event, heroSecondary.url)">{{
                                    heroSecondary.text
                                }}</a>
                            </div>

                            <ul v-if="heroSocials.length" data-reveal class="mt-8 flex flex-wrap gap-2" style="--d: 620ms">
                                <li v-for="(s, i) in heroSocials" :key="i">
                                    <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="sb-icon-btn">
                                        <component :is="socialIconFor(s.platform)" class="h-[18px] w-[18px]" />
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="relative lg:col-span-4 lg:-ml-24">
                    <figure data-reveal class="sb-float relative mx-auto w-[74%] max-w-[22rem] sm:w-full lg:max-w-none" style="--d: 250ms">
                        <div class="sb-mask-offset" aria-hidden="true" />
                        <div class="sb-mask-frame group">
                            <img
                                v-if="media('header', 'hero_image')"
                                :src="media('header', 'hero_image')"
                                :alt="heroName ?? ''"
                                class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.06]"
                            />
                            <span v-else-if="heroInitials" class="sb-display flex h-full w-full items-center justify-center bg-[var(--accent)] text-[7rem] text-[var(--accent-ink)]">{{
                                heroInitials
                            }}</span>
                        </div>
                        <figcaption v-if="location" class="sb-caption sb-mono"><MapPin class="h-3.5 w-3.5 text-[var(--accent)]" />{{ location }}</figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- ============================== ABOUT ============================== -->
        <section v-if="has('about')" id="about" class="sb-band scroll-mt-0" :class="bandOf('about')">
            <span v-if="text('about', 'about_title')" class="sb-watermark" aria-hidden="true">{{ text('about', 'about_title') }}</span>
            <div class="relative mx-auto grid w-full max-w-[1320px] gap-14 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-10">
                <div v-if="media('about', 'about_image')" data-reveal class="lg:col-span-4">
                    <div class="sb-frame mx-auto max-w-sm">
                        <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="sb-arch aspect-[4/5] w-full object-cover" loading="lazy" />
                    </div>
                </div>

                <div :class="media('about', 'about_image') ? 'lg:col-span-8' : 'lg:col-span-10 lg:col-start-2'">
                    <div data-reveal>
                        <div class="sb-head">
                            <span class="sb-index sb-mono">{{ numberOf('about') }}</span>
                            <p v-if="text('about', 'about_subtitle')" class="sb-kicker">{{ text('about', 'about_subtitle') }}</p>
                        </div>
                        <h2 v-if="text('about', 'about_title')" class="sb-display sb-h2 mt-5">{{ text('about', 'about_title') }}</h2>
                        <p v-if="text('about', 'about_description')" class="sb-muted mt-7 max-w-[40rem] whitespace-pre-line text-lg leading-[1.75]">
                            {{ text('about', 'about_description') }}
                        </p>
                    </div>

                    <ul v-if="highlights.length" data-reveal class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <li v-for="(h, i) in highlights" :key="i" :style="{ '--k': i }" class="sb-hl" :class="`sb-stagger-${i % 3}`">
                            <span class="sb-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>
                            <span class="mt-4 block font-semibold leading-snug">{{ h.title }}</span>
                            <span v-if="h.description" class="sb-muted mt-1.5 block text-[0.92rem] leading-relaxed">{{ h.description }}</span>
                        </li>
                    </ul>

                    <div v-if="cvFile || aboutCta" data-reveal class="mt-10 flex flex-wrap gap-3">

                        <a v-if="aboutCta && cvFile" :href="aboutCta.url" class="sb-btn sb-btn-ghost" @click="onNavClick($event, aboutCta.url)">
                            {{ aboutCta.text }}<ArrowRight class="sb-arrow h-4 w-4" />
                        </a>
                        <a v-else-if="aboutCta" :href="aboutCta.url" class="sb-btn sb-btn-primary" @click="onNavClick($event, aboutCta.url)">
                            {{ aboutCta.text }}<ArrowRight class="sb-arrow h-4 w-4" />
                        </a>
                        <a v-else-if="cvFile" :href="cvFile" download target="_blank" rel="noopener" class="sb-btn sb-btn-primary">
                            <Download class="h-4 w-4" />Download CV</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================== JOURNEY ============================== -->
        <section v-if="has('journey')" id="journey" class="sb-band scroll-mt-0" :class="bandOf('journey')">
            <span v-if="text('journey', 'journey_title')" class="sb-watermark" aria-hidden="true">{{ text('journey', 'journey_title') }}</span>
            <div class="relative mx-auto w-full max-w-[1320px] px-5 py-28 sm:px-8">
                <div data-reveal class="sb-head">
                    <span class="sb-index sb-mono">{{ numberOf('journey') }}</span>
                    <p v-if="text('journey', 'journey_subtitle')" class="sb-kicker">{{ text('journey', 'journey_subtitle') }}</p>
                </div>
                <h2 v-if="text('journey', 'journey_title')" data-reveal class="sb-display sb-h2 mt-5 max-w-4xl">{{ text('journey', 'journey_title') }}</h2>

                <div class="sb-rail mt-16">
                    <ol class="sb-track-h">
                        <li
                            v-for="(item, index) in journey"
                            :key="index"
                            data-reveal
                            :style="{ '--d': `${(index % 4) * 80}ms` }"
                            class="sb-step"
                            :class="`sb-drop-${index % 2}`"
                        >
                            <span class="sb-node" aria-hidden="true"><i /></span>
                            <div class="sb-step-card">
                                <p v-if="item.period" class="sb-mono text-[0.78rem] font-medium uppercase tracking-[0.12em] text-[var(--accent)]">{{ item.period }}</p>
                                <h3 v-if="item.title" class="sb-display mt-2 text-2xl leading-tight">{{ item.title }}</h3>
                                <p v-if="item.company" class="mt-1 font-semibold">{{ item.company }}</p>
                                <p v-if="item.description" class="sb-muted mt-3 whitespace-pre-line text-[0.95rem] leading-[1.65]">{{ item.description }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        <!-- ============================== SKILLS ============================== -->
        <section v-if="has('skills')" id="skills" class="sb-band scroll-mt-0" :class="bandOf('skills')">
            <span v-if="text('skills', 'skills_title')" class="sb-watermark" aria-hidden="true">{{ text('skills', 'skills_title') }}</span>
            <div class="relative mx-auto grid w-full max-w-[1320px] gap-12 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-10">
                <div class="lg:col-span-4">
                    <div data-reveal class="lg:sticky lg:top-28">
                        <div class="sb-head">
                            <span class="sb-index sb-mono">{{ numberOf('skills') }}</span>
                            <p v-if="text('skills', 'skills_subtitle')" class="sb-kicker">{{ text('skills', 'skills_subtitle') }}</p>
                        </div>
                        <h2 v-if="text('skills', 'skills_title')" class="sb-display sb-h2 mt-5">{{ text('skills', 'skills_title') }}</h2>
                        <p v-if="text('skills', 'skills_description')" class="sb-muted mt-6 whitespace-pre-line leading-[1.7]">{{ text('skills', 'skills_description') }}</p>
                    </div>
                </div>

                <ul class="grid content-start gap-4 sm:grid-cols-2 lg:col-span-8 xl:grid-cols-3">
                    <li
                        v-for="(s, i) in skills"
                        :key="i"
                        data-reveal
                        :style="{ '--d': `${(i % 6) * 50}ms` }"
                        class="sb-skill"
                        :class="`sb-stagger-${i % 3}`"
                    >
                        <div class="flex items-center gap-3">
                            <span v-if="showIcon" class="sb-tile">
                                <component :is="iconFor(s.icon)" v-if="iconFor(s.icon)" class="h-[18px] w-[18px]" />
                                <span v-else-if="isGlyph(s.icon)" class="text-lg leading-none" aria-hidden="true">{{ s.icon }}</span>
                                <span v-else class="sb-display text-base leading-none" aria-hidden="true">{{ s.name.charAt(0).toUpperCase() }}</span>
                            </span>
                            <span
                                v-if="showLevel && s.pct !== null"
                                class="sb-count sb-mono sb-muted ml-auto text-[0.8rem] font-medium"
                                :style="{ '--p': Math.round(s.pct) }"
                                aria-hidden="true"
                            />
                            <span v-else-if="showLevel && s.label" class="sb-pill ml-auto">{{ s.label }}</span>
                        </div>
                        <h3 class="mt-5 text-[1.05rem] font-semibold leading-snug">{{ s.name }}</h3>
                        <div
                            v-if="showLevel && s.pct !== null"
                            class="sb-track mt-4"
                            role="progressbar"
                            :aria-valuenow="Math.round(s.pct)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="s.name"
                        >
                            <div class="sb-bar" :style="{ '--w': `${s.pct}%` }" />
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== PROJECTS ============================== -->
        <section v-if="has('projects')" id="projects" class="sb-band scroll-mt-0" :class="bandOf('projects')">
            <span v-if="text('projects', 'projects_title')" class="sb-watermark" aria-hidden="true">{{ text('projects', 'projects_title') }}</span>
            <div class="relative mx-auto w-full max-w-[1320px] px-5 py-28 sm:px-8">
                <div data-reveal class="sb-head">
                    <span class="sb-index sb-mono">{{ numberOf('projects') }}</span>
                    <p v-if="text('projects', 'projects_subtitle')" class="sb-kicker">{{ text('projects', 'projects_subtitle') }}</p>
                </div>
                <div data-reveal class="mb-20 mt-5 grid gap-6 lg:grid-cols-12 lg:items-end">
                    <h2 v-if="text('projects', 'projects_title')" class="sb-display sb-h2 lg:col-span-8">{{ text('projects', 'projects_title') }}</h2>
                    <p v-if="text('projects', 'projects_description')" class="sb-muted whitespace-pre-line leading-[1.7] lg:col-span-4">
                        {{ text('projects', 'projects_description') }}
                    </p>
                </div>

                <ul class="grid gap-x-8 gap-y-20 md:grid-cols-2 xl:grid-cols-3">
                    <li
                        v-for="(p, i) in projects"
                        :key="i"
                        data-reveal
                        :class="[featureFirst && i === 0 ? 'md:col-span-2 xl:col-span-2' : '', `sb-offset-${i % 3}`]"
                        :style="{ '--d': `${(i % 3) * 80}ms` }"
                    >
                        <article class="sb-project group relative flex h-full flex-col">
                            <ul v-if="p.tags.length" class="sb-ribbon" aria-label="Tags">
                                <li v-for="(t, ti) in p.tags.slice(0, 3)" :key="ti" class="sb-mono">{{ t }}</li>
                            </ul>

                            <component
                                :is="p.live ? 'a' : 'div'"
                                :href="p.live || undefined"
                                :target="p.live ? '_blank' : undefined"
                                :rel="p.live ? 'noopener noreferrer' : undefined"
                                :aria-label="p.live ? p.title : undefined"
                                class="sb-media relative block overflow-hidden"
                                :class="featureFirst && i === 0 ? 'aspect-[16/9]' : 'aspect-[4/3]'"
                            >
                                <img v-if="p.image" :src="p.image" :alt="p.title" class="h-full w-full object-cover" loading="lazy" />
                                <div v-else class="sb-display sb-tone flex h-full w-full items-center justify-center text-8xl" :class="`sb-tone-${i % 3}`">
                                    {{ p.title.charAt(0).toUpperCase() }}
                                </div>
                                <span v-if="featureFirst && i === 0" class="sb-flag sb-mono"><Sparkles class="h-3.5 w-3.5" />{{ String(i + 1).padStart(2, '0') }}</span>
                                <span v-if="p.live" class="sb-go" aria-hidden="true"><ArrowUpRight class="h-5 w-5" /></span>
                            </component>

                            <div class="sb-project-body relative flex flex-1 flex-col">
                                <h3 class="sb-display text-2xl leading-tight"><span class="sb-underline">{{ p.title }}</span></h3>
                                <p v-if="p.description" class="sb-muted mt-3 leading-[1.65]">{{ p.description }}</p>
                                <div v-if="p.repo" class="mt-auto pt-5">
                                    <a :href="p.repo" target="_blank" rel="noopener noreferrer" class="sb-textlink sb-mono text-[0.78rem] uppercase tracking-wider"
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
        <section v-if="has('services')" id="services" class="sb-band scroll-mt-0" :class="bandOf('services')">
            <span v-if="text('services', 'services_title')" class="sb-watermark" aria-hidden="true">{{ text('services', 'services_title') }}</span>
            <div class="relative mx-auto w-full max-w-[1320px] px-5 py-28 sm:px-8">
                <div data-reveal class="sb-head">
                    <span class="sb-index sb-mono">{{ numberOf('services') }}</span>
                    <p v-if="text('services', 'services_subtitle')" class="sb-kicker">{{ text('services', 'services_subtitle') }}</p>
                </div>
                <h2 v-if="text('services', 'services_title')" data-reveal class="sb-display sb-h2 mb-16 mt-5 max-w-4xl">{{ text('services', 'services_title') }}</h2>

                <ul class="grid gap-5 md:grid-cols-3">
                    <li v-for="(s, i) in services" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 70}ms` }" :class="`sb-stagger-${i % 3}`">
                        <div class="sb-service group flex h-full flex-col">
                            <span class="sb-mono sb-service-num" aria-hidden="true">{{ String(i + 1).padStart(2, '0') }}</span>
                            <span class="sb-tile sb-tile-lg">
                                <component :is="iconFor(s.icon) ?? Sparkles" v-if="!isGlyph(s.icon)" class="h-6 w-6" />
                                <span v-else class="text-2xl leading-none" aria-hidden="true">{{ s.icon }}</span>
                            </span>
                            <h3 class="sb-display sb-service-title mt-7 text-2xl leading-tight">{{ s.title }}</h3>
                            <p v-if="s.description" class="sb-muted mt-3 leading-[1.65]">{{ s.description }}</p>
                            <ArrowUpRight class="sb-service-arrow mt-auto h-6 w-6 self-end" aria-hidden="true" />
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== CONTACT ============================== -->
        <section v-if="has('contact')" id="contact" class="sb-band sb-contact scroll-mt-0">
            <span v-if="text('contact', 'contact_title')" class="sb-watermark" aria-hidden="true">{{ text('contact', 'contact_title') }}</span>
            <div class="relative z-10 mx-auto grid w-full max-w-[1320px] gap-14 px-5 py-24 sm:px-8 lg:grid-cols-12 lg:gap-12 lg:py-32">
                <div class="lg:col-span-7">
                    <div data-reveal>
                        <div class="sb-head">
                            <span class="sb-index sb-mono">{{ numberOf('contact') }}</span>
                            <p v-if="text('contact', 'contact_subtitle')" class="sb-kicker">{{ text('contact', 'contact_subtitle') }}</p>
                        </div>
                        <h2 v-if="text('contact', 'contact_title')" class="sb-display mt-5 text-[clamp(2.75rem,8vw,6.5rem)] leading-[0.94] [overflow-wrap:anywhere]">
                            {{ text('contact', 'contact_title') }}
                        </h2>
                        <p v-if="text('contact', 'contact_description')" class="sb-soft-text mt-7 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7]">
                            {{ text('contact', 'contact_description') }}
                        </p>
                    </div>

                    <div v-if="email || phone || location" data-reveal class="mt-10 space-y-5">
                        <a v-if="email" :href="`mailto:${email}`" class="sb-bigmail sb-display group inline-flex max-w-full items-center gap-3 break-all text-2xl sm:text-4xl">
                            <Mail class="h-7 w-7 shrink-0 text-[var(--accent)]" /><span class="sb-underline-contact">{{ email }}</span>
                            <ArrowUpRight class="sb-arrow h-7 w-7 shrink-0" />
                        </a>
                        <div class="flex flex-wrap gap-x-8 gap-y-3 font-medium">
                            <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="sb-contact-link"><Phone class="h-4 w-4 text-[var(--accent)]" />{{ phone }}</a>
                            <span v-if="location" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4 text-[var(--accent)]" />{{ location }}</span>
                        </div>
                    </div>

                    <ul v-if="contactSocials.length" data-reveal class="mt-8 flex flex-wrap gap-2">
                        <li v-for="(s, i) in contactSocials" :key="i">
                            <a :href="s.url" target="_blank" rel="noopener noreferrer" class="sb-social">
                                <component :is="socialIconFor(s.platform)" class="h-3.5 w-3.5" />{{ s.label }}<ArrowUpRight class="sb-arrow h-3.5 w-3.5" />
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
                            target="sb-gform-frame"
                            class="sb-form space-y-6 p-6 sm:p-8"
                            @submit="gf.url && gf.name && gf.email && gf.message ? onSubmit($event) : handleContactFormFallback($event, text('contact', 'contact_email'))"
                        >
                            <label class="block">
                                <span class="sb-mono sb-soft-text mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.12em]">Name</span>
                                <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="sb-input" />
                            </label>
                            <label class="block">
                                <span class="sb-mono sb-soft-text mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.12em]">Email</span>
                                <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="sb-input" />
                            </label>
                            <label class="block">
                                <span class="sb-mono sb-soft-text mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.12em]">Message</span>
                                <textarea :name="`entry.${gf.message}`" rows="5" required class="sb-input resize-y" />
                            </label>
                            <button type="submit" class="sb-btn sb-btn-primary w-full justify-center" :disabled="sending">
                                {{ sending ? '…' : 'Send' }}<Send class="sb-arrow h-4 w-4" />
                            </button>
                            <p v-if="sent" role="status" class="sb-sent flex items-center gap-2 text-sm font-semibold">
                                <span class="sb-tick !h-6 !w-6"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>OK
                            </p>
                        </form>
                        <a v-else-if="email" :href="`mailto:${email}`" class="sb-btn sb-btn-ghost w-full justify-center !py-5 text-lg"><Mail class="h-5 w-5" />{{ email }}</a>
                    </div>
                    <iframe name="sb-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                </div>
            </div>

            <footer v-if="heroName || logoText" class="relative z-10 mx-auto flex max-w-[1320px] flex-wrap items-center justify-between gap-2 border-t border-[var(--line)] px-5 py-7 text-sm sm:px-8">
                <span class="sb-soft-text sb-mono text-[0.78rem]">&copy; {{ year }} {{ heroName ?? logoText }}</span>
                <a href="#header" class="sb-textlink" aria-label="Top" @click="onNavClick($event, '#header')"><ArrowUpRight class="sb-arrow h-4 w-4 -rotate-45" /></a>
            </footer>
        </section>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=Instrument+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap');

/* ---------- Foundations ---------- */
.sb-root {
    position: relative;
    background: var(--paper);
    color: var(--ink);
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    overflow-x: clip;
    transition:
        background-color 0.4s ease,
        color 0.4s ease;
}
.sb-display {
    font-family: 'Syne', 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
    font-weight: 700;
    letter-spacing: -0.04em;
}
.sb-mono {
    font-family: 'DM Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
}
.sb-muted {
    color: var(--muted);
}
.sb-soft-text {
    color: var(--muted);
}
.sb-h2 {
    font-size: clamp(2.4rem, 5.4vw, 4.6rem);
    line-height: 1;
    letter-spacing: -0.045em;
    text-wrap: balance;
}
.sb-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
}

/* Full-bleed bands alternating like slides */
.sb-band {
    position: relative;
    overflow: hidden;
    border-block: 1px solid var(--line);
}
.sb-band.is-paper {
    background: var(--paper);
}
.sb-band.is-surface {
    background: var(--surface);
}
.sb-contact {
    background: var(--soft);
}

/* Giant outline watermark titles */
.sb-watermark {
    position: absolute;
    left: 50%;
    top: 50%;
    z-index: 0;
    max-width: 100%;
    transform: translate(-50%, -50%);
    overflow: hidden;
    white-space: nowrap;
    text-overflow: clip;
    font-family: 'Syne', ui-sans-serif, system-ui, sans-serif;
    font-size: clamp(7rem, 22vw, 20rem);
    font-weight: 800;
    line-height: 0.9;
    letter-spacing: -0.05em;
    text-transform: uppercase;
    color: transparent;
    -webkit-text-stroke: 1.5px var(--line);
    opacity: 0.9;
    pointer-events: none;
    user-select: none;
    transition: transform 1.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-watermark-hero {
    top: auto;
    bottom: -1.5rem;
    transform: translate(-50%, 0);
    font-size: clamp(9rem, 28vw, 26rem);
    z-index: -1;
}
.sb-band > .relative {
    position: relative;
    z-index: 1;
}

/* Section header: number + rule + subtitle */
.sb-head {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.sb-index {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--accent);
}
.sb-kicker {
    display: inline-flex;
    align-items: center;
    gap: 1rem;
    font-family: 'DM Mono', ui-monospace, monospace;
    font-size: 0.78rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.sb-kicker::before {
    content: '';
    width: 2.5rem;
    height: 1px;
    background: currentColor;
    transform-origin: left;
    transition: transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) 0.2s;
}
.sb-js [data-reveal]:not(.is-in) .sb-kicker::before {
    transform: scaleX(0);
}

/* Staggered asymmetric offsets (large screens only) */
@media (min-width: 768px) {
    .sb-stagger-1 {
        transform: translateY(1.75rem);
    }
    .sb-stagger-2 {
        transform: translateY(0.75rem);
    }
    .sb-offset-1 {
        margin-top: 3.5rem;
    }
    .sb-offset-2 {
        margin-top: 1.25rem;
    }
}

/* ---------- Navbar ---------- */
.sb-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 3px;
    background: var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
    z-index: 60;
}
.sb-nav {
    border-bottom: 1px solid transparent;
    transition:
        border-color 0.35s ease,
        background-color 0.35s ease;
}
.sb-nav.is-scrolled {
    border-color: var(--line);
    background: color-mix(in srgb, var(--paper) 85%, transparent);
    -webkit-backdrop-filter: blur(14px) saturate(1.3);
    backdrop-filter: blur(14px) saturate(1.3);
}
.sb-navlink {
    display: inline-block;
    padding: 0.5rem 0.95rem;
    font-size: 0.92rem;
    font-weight: 500;
    color: var(--muted);
    background: linear-gradient(var(--accent), var(--accent)) 0.95rem calc(100% - 0.2rem) / 0% 2px no-repeat;
    transition:
        color 0.25s ease,
        background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-navlink:hover,
.sb-navlink[data-active='true'] {
    color: var(--ink);
}
.sb-navlink[data-active='true'] {
    background-size: calc(100% - 1.9rem) 2px;
}

/* ---------- Buttons & small controls ---------- */
.sb-btn {
    position: relative;
    isolation: isolate;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    border-radius: 999px;
    padding: 0.9rem 1.6rem;
    font-size: 0.93rem;
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
        border-color 0.3s ease;
}
.sb-btn:hover {
    background-size: 100% 100%;
    transform: translateY(-2px);
}
.sb-btn:active {
    transform: translateY(0) scale(0.98);
}
.sb-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.sb-btn-primary {
    background-color: var(--accent);
    background-image: linear-gradient(var(--ink), var(--ink));
    color: var(--accent-ink);
}
.sb-btn-primary:hover {
    color: var(--paper);
}
.sb-btn-ghost {
    background-color: transparent;
    background-image: linear-gradient(var(--ink), var(--ink));
    border-color: var(--ink);
    color: var(--ink);
}
.sb-btn-ghost:hover {
    color: var(--paper);
}
.sb-arrow {
    transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-btn:hover .sb-arrow,
.sb-bigmail:hover .sb-arrow,
.sb-social:hover .sb-arrow,
.sb-textlink:hover .sb-arrow {
    transform: translate(3px, -3px);
}
.sb-btn:hover .sb-arrow:is(svg) {
    transform: translateX(3px);
}
.sb-icon-btn {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid var(--ink);
    transition:
        transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1),
        background-color 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease;
}
.sb-icon-btn:hover {
    transform: translateY(-3px);
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
}
.sb-label {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.45rem 1rem 0.45rem 0.85rem;
    font-family: 'DM Mono', ui-monospace, monospace;
    font-size: 0.78rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.sb-pulse {
    position: relative;
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 999px;
    background: var(--accent);
}
.sb-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: inherit;
    animation: sb-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.sb-pill {
    display: inline-block;
    border: 1px solid var(--line);
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    font-family: 'DM Mono', ui-monospace, monospace;
    font-size: 0.72rem;
    font-weight: 500;
    color: var(--muted);
}
.sb-tick {
    display: inline-flex;
    height: 1.7rem;
    width: 1.7rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-ink);
}
.sb-textlink {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    background: linear-gradient(currentColor, currentColor) left bottom / 0% 1.5px no-repeat;
    transition: background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 2px;
}
.sb-textlink:hover {
    background-size: 100% 1.5px;
}

/* ---------- Hero ---------- */
.sb-hero-name {
    font-size: clamp(3.5rem, 11vw, 9.5rem);
    line-height: 0.9;
    letter-spacing: -0.06em;
    overflow-wrap: anywhere;
}
.sb-mask {
    display: inline-block;
    overflow: hidden;
    vertical-align: top;
    padding-bottom: 0.14em;
    margin-bottom: -0.14em;
    margin-right: 0.2em;
}
.sb-word {
    display: inline-block;
}
.sb-outline-text {
    color: transparent;
    -webkit-text-stroke: 2px var(--ink);
}
.sb-js .sb-word {
    transform: translateY(110%);
    animation: sb-rise 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: calc(0.15s + var(--i, 0) * 0.1s);
}
.sb-role {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    font-size: 0.88rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    line-height: 1.5;
}
.sb-bar-mark {
    display: block;
    height: 3px;
    width: 3rem;
    background: var(--accent);
}
.sb-float {
    animation: sb-float 7s ease-in-out infinite;
}
.sb-mask-frame {
    position: relative;
    aspect-ratio: 4 / 5;
    overflow: hidden;
    background: var(--soft);
    border: 1px solid var(--ink);
    border-radius: 55% 45% 12% 12% / 30% 30% 12% 12%;
    box-shadow: 0 30px 60px -30px color-mix(in srgb, var(--ink) 60%, transparent);
    transition: border-radius 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-float:hover .sb-mask-frame {
    border-radius: 12% 12% 55% 45% / 12% 12% 30% 30%;
}
.sb-mask-offset {
    position: absolute;
    inset: 0;
    border: 1.5px solid var(--accent);
    border-radius: 55% 45% 12% 12% / 30% 30% 12% 12%;
    transform: translate(1.1rem, 1.1rem);
    transition:
        transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-radius 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-float:hover .sb-mask-offset {
    transform: translate(0.4rem, 0.4rem);
    border-radius: 12% 12% 55% 45% / 12% 12% 30% 30%;
}
.sb-caption {
    position: absolute;
    left: -1rem;
    bottom: 2rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: var(--ink);
    color: var(--paper);
    padding: 0.5rem 0.9rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.sb-caption :deep(svg) {
    color: var(--accent);
}

/* ---------- About ---------- */
.sb-frame {
    position: relative;
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-frame::before {
    content: '';
    position: absolute;
    inset: 0;
    border: 1.5px solid var(--accent);
    border-radius: 999px 999px 1.25rem 1.25rem;
    transform: translate(-0.9rem, 0.9rem);
    pointer-events: none;
}
.sb-arch {
    border-radius: 999px 999px 1.25rem 1.25rem;
    border: 1px solid var(--ink);
}
.sb-frame:hover {
    transform: rotate(-1.2deg) scale(1.01);
}
.sb-hl {
    position: relative;
    border: 1px solid var(--line);
    border-radius: 1.25rem;
    background: var(--surface);
    padding: 1.4rem 1.4rem 1.5rem;
    transition:
        border-color 0.3s ease,
        box-shadow 0.4s ease;
}
.is-surface .sb-hl {
    background: var(--paper);
}
.sb-hl:hover {
    border-color: var(--accent);
    box-shadow: 8px 8px 0 var(--accent);
}

/* ---------- Journey (horizontal progression) ---------- */
.sb-rail {
    position: relative;
    margin-inline: -1.25rem;
    overflow-x: auto;
    padding: 1rem 1.25rem 2rem;
    scroll-snap-type: x proximity;
    scrollbar-width: thin;
    scrollbar-color: var(--accent) transparent;
}
.sb-track-h {
    position: relative;
    display: flex;
    gap: 1.25rem;
    width: max-content;
    min-width: 100%;
    padding-top: 2.5rem;
}
.sb-track-h::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 0.65rem;
    height: 2px;
    background: var(--line);
}
.sb-track-h::after {
    content: '';
    position: absolute;
    left: 0;
    top: 0.65rem;
    height: 2px;
    width: 100%;
    background: var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 1.6s cubic-bezier(0.5, 0, 0.2, 1) 0.2s;
}
.sb-js .sb-rail:has(.is-in)::after {
    content: none;
}
.sb-step {
    position: relative;
    width: min(21rem, 78vw);
    flex-shrink: 0;
    scroll-snap-align: start;
}
.sb-node {
    position: absolute;
    left: 0;
    top: -2.5rem;
    display: inline-flex;
    height: 1.35rem;
    width: 1.35rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 2px solid var(--accent);
    background: var(--paper);
}
.is-surface .sb-node {
    background: var(--surface);
}
.sb-node i {
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 999px;
    background: var(--accent);
    transform: scale(0);
    transition: transform 0.5s cubic-bezier(0.3, 1.6, 0.4, 1) 0.4s;
}
.sb-js .sb-step.is-in .sb-node i {
    transform: scale(1);
}
.sb-step::before {
    content: '';
    position: absolute;
    left: calc(0.675rem - 1px);
    top: -1.15rem;
    height: 1.15rem;
    width: 2px;
    background: var(--accent);
    transform: scaleY(0);
    transform-origin: top;
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1) 0.3s;
}
.sb-js .sb-step.is-in::before {
    transform: scaleY(1);
}
.sb-step-card {
    height: 100%;
    border: 1px solid var(--line);
    border-radius: 1.25rem;
    background: var(--surface);
    padding: 1.5rem;
    transition:
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease,
        box-shadow 0.45s ease;
}
.is-surface .sb-step-card {
    background: var(--paper);
}
.sb-step-card:hover {
    transform: translateY(-5px);
    border-color: var(--accent);
    box-shadow: 8px 8px 0 var(--accent);
}
@media (min-width: 768px) {
    .sb-drop-1 {
        margin-top: 2rem;
    }
}

/* ---------- Skills ---------- */
.sb-skill {
    border: 1px solid var(--line);
    border-radius: 1.25rem;
    background: var(--surface);
    padding: 1.35rem 1.4rem 1.5rem;
    transition:
        border-color 0.3s ease,
        box-shadow 0.4s ease;
}
.is-surface .sb-skill {
    background: var(--paper);
}
.sb-skill:hover {
    border-color: var(--accent);
    box-shadow: 6px 6px 0 var(--accent);
}
.sb-tile {
    display: inline-flex;
    height: 2.6rem;
    width: 2.6rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 40%, var(--line));
    color: var(--accent);
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-tile-lg {
    height: 3.4rem;
    width: 3.4rem;
    border-radius: 1.1rem;
}
.sb-skill:hover .sb-tile,
.sb-service:hover .sb-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.sb-track {
    position: relative;
    height: 4px;
    width: 100%;
    background: var(--line);
    border-radius: 999px;
}
.sb-bar {
    position: relative;
    height: 100%;
    width: var(--w, 0%);
    border-radius: inherit;
    background: var(--accent);
}
.sb-bar::after {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    height: 12px;
    width: 12px;
    transform: translate(50%, -50%);
    border-radius: 999px;
    background: var(--ink);
}
.sb-js [data-reveal] .sb-bar {
    width: 0;
    transition: width 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s);
}
.sb-js [data-reveal].is-in .sb-bar {
    width: var(--w, 0%);
}

/* Percentage counts up in sync with the bar */
@property --n {
    syntax: '<integer>';
    inherits: false;
    initial-value: 0;
}
.sb-count {
    --n: var(--p, 0);
    counter-reset: n var(--n);
    font-variant-numeric: tabular-nums;
}
.sb-count::after {
    content: counter(n) '%';
}
.sb-js [data-reveal] .sb-count {
    --n: 0;
}
.sb-js [data-reveal].is-in .sb-count {
    animation: sb-count 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s) forwards;
}

/* ---------- Projects ---------- */
.sb-project {
    transition: transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-project:hover {
    transform: translateY(-6px);
}
.sb-media {
    background: var(--soft);
    border: 1px solid var(--ink);
    border-radius: 1.25rem;
    isolation: isolate;
    box-shadow: 0 24px 44px -30px color-mix(in srgb, var(--ink) 65%, transparent);
}
.sb-media img,
.sb-tone {
    transition: transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-project:hover .sb-media img,
.sb-project:hover .sb-tone {
    transform: scale(1.06);
}
.sb-tone-0 {
    background: var(--accent);
    color: var(--accent-ink);
}
.sb-tone-1 {
    background: var(--soft);
    color: var(--ink);
}
.sb-tone-2 {
    background: var(--ink);
    color: var(--paper);
}
/* Offset overlapping body card */
.sb-project-body {
    z-index: 2;
    margin: -2.5rem 1rem 0 2.25rem;
    border: 1px solid var(--line);
    border-radius: 1.1rem;
    background: var(--surface);
    padding: 1.4rem 1.4rem 1.3rem;
    transition:
        border-color 0.3s ease,
        box-shadow 0.45s ease;
}
.is-surface .sb-project-body {
    background: var(--paper);
}
.sb-project:hover .sb-project-body {
    border-color: var(--accent);
    box-shadow: 6px 6px 0 var(--accent);
}
/* Vertical tag ribbons */
.sb-ribbon {
    position: absolute;
    left: -0.2rem;
    top: 1.25rem;
    z-index: 3;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: calc(100% - 7rem);
}
.sb-ribbon li {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    background: var(--ink);
    color: var(--paper);
    padding: 0.7rem 0.4rem;
    font-size: 0.68rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    white-space: nowrap;
    transition:
        background-color 0.3s ease,
        color 0.3s ease;
}
.sb-ribbon li:first-child {
    background: var(--accent);
    color: var(--accent-ink);
}
.sb-project:hover .sb-ribbon li {
    background: var(--accent);
    color: var(--accent-ink);
}
.sb-flag {
    position: absolute;
    right: 0.9rem;
    bottom: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--accent);
    color: var(--accent-ink);
    padding: 0.25rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 500;
}
.sb-go {
    position: absolute;
    right: 0.9rem;
    top: 0.9rem;
    display: inline-flex;
    height: 2.9rem;
    width: 2.9rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-ink);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem) scale(0.9);
    transition:
        opacity 0.35s ease,
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-project:hover .sb-go,
.sb-media:focus-visible .sb-go {
    opacity: 1;
    transform: none;
}
.sb-underline {
    background: linear-gradient(currentColor, currentColor) left 96% / 0% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-project:hover .sb-underline {
    background-size: 100% 2px;
}

/* ---------- Services ---------- */
.sb-service {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 1.25rem;
    background: var(--surface);
    padding: 1.8rem;
    transition:
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease,
        box-shadow 0.45s ease;
}
.is-surface .sb-service {
    background: var(--paper);
}
.sb-service:hover {
    transform: translateY(-5px);
    border-color: var(--accent);
    box-shadow: 8px 8px 0 var(--accent);
}
.sb-service-num {
    position: absolute;
    right: 1.2rem;
    top: 0.9rem;
    font-size: 2.6rem;
    font-weight: 500;
    line-height: 1;
    color: transparent;
    -webkit-text-stroke: 1px var(--line);
    transition: -webkit-text-stroke-color 0.3s ease;
}
.sb-service:hover .sb-service-num {
    -webkit-text-stroke-color: var(--accent);
}
.sb-service-title {
    transition: color 0.3s ease;
}
.sb-service:hover .sb-service-title {
    color: var(--accent);
}
.sb-service-arrow {
    color: var(--accent);
    opacity: 0;
    transform: translate(-0.6rem, 0.6rem);
    transition:
        opacity 0.3s ease,
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-service:hover .sb-service-arrow {
    opacity: 1;
    transform: none;
}

/* ---------- Contact ---------- */
.sb-contact::before {
    content: '';
    position: absolute;
    right: -7rem;
    top: -7rem;
    z-index: 0;
    height: 24rem;
    width: 24rem;
    border-radius: 999px;
    background: var(--accent);
    opacity: 0.14;
    pointer-events: none;
    animation: sb-drift 18s ease-in-out infinite;
}
.sb-underline-contact {
    background: linear-gradient(currentColor, currentColor) left bottom / 100% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 4px;
}
.sb-bigmail:hover .sb-underline-contact {
    background-size: 0% 2px;
    background-position: right bottom;
}
.sb-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.sb-contact-link:hover {
    text-decoration: underline;
    text-underline-offset: 4px;
}
.sb-social {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid var(--ink);
    border-radius: 999px;
    padding: 0.5rem 1rem;
    font-size: 0.88rem;
    font-weight: 600;
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        border-color 0.3s ease,
        transform 0.3s ease;
}
.sb-social:hover {
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
    transform: translateY(-2px);
}
.sb-form {
    border: 1px solid var(--ink);
    border-radius: 1.5rem;
    background: var(--surface);
    box-shadow: 10px 10px 0 var(--accent);
}
.sb-input {
    width: 100%;
    border: 0;
    border-bottom: 1px solid var(--line);
    border-radius: 0;
    background: transparent;
    padding: 0.7rem 0;
    font: inherit;
    font-weight: 500;
    color: var(--ink);
    transition:
        border-color 0.25s ease,
        box-shadow 0.25s ease;
}
.sb-input:hover {
    border-color: var(--muted);
}
.sb-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 1px 0 var(--accent);
}
.sb-sent {
    animation: sb-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.sb-js [data-reveal] {
    opacity: 0;
    transform: translateY(24px);
    transition:
        opacity 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.sb-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}
@media (min-width: 768px) {
    .sb-js [data-reveal].sb-stagger-1.is-in,
    .sb-js li.sb-stagger-1[data-reveal].is-in {
        transform: translateY(1.75rem);
    }
    .sb-js [data-reveal].sb-stagger-2.is-in,
    .sb-js li.sb-stagger-2[data-reveal].is-in {
        transform: translateY(0.75rem);
    }
}

/* ---------- Section animations ---------- */
.sb-h2 {
    transition: letter-spacing 1.2s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.sb-js [data-reveal]:not(.is-in) .sb-h2,
.sb-js [data-reveal]:not(.is-in).sb-h2 {
    letter-spacing: 0.005em;
}
.sb-js [data-reveal].is-in .sb-frame {
    animation: sb-frame-in 1.1s cubic-bezier(0.7, 0, 0.2, 1) backwards;
}
.sb-js [data-reveal]:not(.is-in) .sb-hl {
    opacity: 0;
}
.sb-js [data-reveal].is-in .sb-hl {
    animation: sb-slide-x 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--k, 0) * 90ms + 150ms) backwards;
}
.sb-js [data-reveal].is-in .sb-hl .sb-tick {
    animation: sb-tick 0.6s cubic-bezier(0.3, 1.6, 0.4, 1) calc(var(--k, 0) * 90ms + 450ms) backwards;
}
.sb-js [data-reveal].is-in .sb-tile {
    animation: sb-tile 0.7s cubic-bezier(0.3, 1.5, 0.4, 1) calc(var(--d, 0ms) + 0.15s) backwards;
}
.sb-js [data-reveal].is-in .sb-media {
    animation: sb-wipe 1.2s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}
.sb-js [data-reveal].is-in .sb-media img {
    animation: sb-settle 1.6s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s backwards;
}
.sb-js [data-reveal].is-in .sb-ribbon li {
    animation: sb-ribbon-in 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) 0.4s backwards;
}

@keyframes sb-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes sb-enter {
    from {
        opacity: 0;
    }
}
@keyframes sb-ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}
@keyframes sb-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}
@keyframes sb-float {
    50% {
        transform: translateY(-14px);
    }
}
@keyframes sb-drift {
    50% {
        transform: translate(-2.5rem, 2rem) scale(1.08);
    }
}
@keyframes sb-count {
    to {
        --n: var(--p);
    }
}
@keyframes sb-frame-in {
    from {
        clip-path: inset(0 0 100% 0);
    }
}
@keyframes sb-slide-x {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
}
@keyframes sb-tick {
    from {
        transform: scale(0) rotate(-90deg);
    }
}
@keyframes sb-tile {
    from {
        transform: scale(0.4) rotate(-20deg);
        opacity: 0;
    }
}
@keyframes sb-wipe {
    from {
        clip-path: inset(0 0 100% 0 round 1.25rem);
    }
}
@keyframes sb-settle {
    from {
        transform: scale(1.25);
    }
}
@keyframes sb-ribbon-in {
    from {
        opacity: 0;
        transform: rotate(180deg) translateY(-1.5rem);
    }
}
.sb-js.sb-root {
    animation: sb-enter 0.7s ease both;
}

/* ---------- Reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    .sb-root *,
    .sb-root *::before,
    .sb-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .sb-js .sb-word,
    .sb-js [data-reveal],
    .sb-js [data-reveal]:not(.is-in) .sb-hl {
        opacity: 1;
        transform: none;
    }
    .sb-js [data-reveal] .sb-count {
        --n: var(--p, 0);
    }
    .sb-js [data-reveal] .sb-bar {
        width: var(--w, 0%);
    }
    .sb-js [data-reveal]:not(.is-in) .sb-kicker::before {
        transform: none;
    }
    .sb-js [data-reveal]:not(.is-in) .sb-h2,
    .sb-js [data-reveal]:not(.is-in).sb-h2 {
        letter-spacing: -0.045em;
    }
    .sb-js .sb-node i,
    .sb-js .sb-step::before {
        transform: none;
    }
}
</style>
