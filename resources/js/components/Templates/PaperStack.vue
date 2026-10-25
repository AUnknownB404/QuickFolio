<script setup lang="ts">
/**
 * Paper Stack — quickFolio template
 * Slug suggestion: `paper-stack`
 *
 * Overlapping card stack: every section is an elevated floating paper sheet (rounded-[2.5rem],
 * shadow-2xl, hairline border) that slightly overlaps the sheet above it while scrolling.
 * Minimalist centered hero with floating orbital badge pills and ambient gradient light spots.
 * Project and service cards lift into a 3D scale elevation on hover. Light + dark via Tailwind `dark:`.
 *
 * Every visible value comes from the seeded field keys. Only small UI chrome labels are hardcoded.
 */
import { resolvePortfolioMediaUrl } from '@/lib/media';
import { handleContactFormFallback } from '@/lib/contact-form-fallback';
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

// Orbital badge pills around the hero (all derived from seeded content)
const orbitBadges = computed(() => {
    const list: { key: string; label: string; icon: Component; href?: string }[] = [];
    const role = text('header', 'hero_role');
    if (role) list.push({ key: 'role', label: role, icon: Sparkles });
    if (location.value) list.push({ key: 'loc', label: location.value, icon: MapPin });
    heroSocials.value.slice(0, 2).forEach((s, i) => list.push({ key: `s${i}`, label: s.label, icon: socialIconFor(s.platform), href: s.url }));
    return list.slice(0, 4);
});

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
function socialIconFor(platform?: string): Component {
    return (platform ? socialIconMap[platform.toLowerCase().replace(/[^a-z]/g, '')] : undefined) ?? Globe;
}

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
        rootEl.value?.style.setProperty('--sy', String(Math.min(window.scrollY, 900)));
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
        { threshold: 0.1, rootMargin: '0px 0px -6% 0px' },
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
        class="ps-root [--paper:#E7E4DD] [--soft:#EFECE5] [--surface:#FFFFFF] [--ink:#16151A] [--muted:#16151AB0] [--line:#16151A1F] [--accent:#7C3AED] [--accent-2:#F59E0B] [--accent-ink:#FFFFFF] selection:bg-[var(--accent)] selection:text-white dark:[--paper:#0B0A10] dark:[--soft:#14121C] dark:[--surface:#1A1824] dark:[--ink:#F2F0F8] dark:[--muted:#F2F0F8B0] dark:[--line:#F2F0F82B] dark:[--accent:#A78BFA] dark:[--accent-2:#FBBF24] dark:[--accent-ink:#0B0A10]"
        :class="{ 'ps-js': ready }"
    >
        <!-- Ambient light spots -->
        <div class="ps-ambient" aria-hidden="true">
            <span class="ps-spot ps-spot-a" />
            <span class="ps-spot ps-spot-b" />
            <span class="ps-spot ps-spot-c" />
        </div>

        <!-- ============================ NAVBAR ============================ -->
        <header v-if="logoImage || logoText || navLinks.length || has('contact')" class="inset-x-0 top-0 z-50 px-3 pt-3 sm:px-6 sm:pt-4" :class="stickyNav ? 'fixed' : 'absolute'">
            <div ref="progressEl" class="ps-progress" aria-hidden="true" />
            <nav ref="navEl" class="ps-nav mx-auto flex h-14 max-w-[1180px] items-center justify-between gap-3 pl-5 pr-2" aria-label="Primary">
                <a v-if="logoImage || logoText" href="#header" class="flex min-w-0 items-center gap-2.5" @click="onNavClick($event, '#header')">
                    <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-7 w-auto max-w-[7rem] object-contain" />
                    <span v-if="logoText" class="ps-display truncate text-base">{{ logoText }}</span>
                </a>
                <span v-else />

                <ul v-if="navLinks.length" class="hidden items-center gap-1 md:flex">
                    <li v-for="l in navLinks" :key="l.id">
                        <a :href="`#${l.id}`" class="ps-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a>
                    </li>
                </ul>

                <a v-if="has('contact')" href="#contact" class="ps-btn ps-btn-primary !px-5 !py-2.5 !text-sm" @click="onNavClick($event, '#contact')">Contact me<ArrowUpRight class="ps-arrow h-4 w-4" />
                </a>
                <span v-else />
            </nav>
        </header>

        <!-- ============================== HERO ============================== -->
        <section v-if="has('header')" id="header" class="ps-hero relative isolate flex min-h-[100svh] items-center justify-center overflow-hidden">
            <span id="home" class="absolute top-0" aria-hidden="true" />
            <img
                v-if="media('header', 'hero_background_image')"
                :src="media('header', 'hero_background_image')"
                alt=""
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-[0.14]"
            />

            <div class="relative mx-auto flex w-full max-w-[980px] flex-col items-center px-5 pb-40 pt-32 text-center sm:px-8">
                <p v-if="text('header', 'hero_greeting')" data-reveal class="ps-chip" style="--d: 0ms">
                    <span class="ps-pulse" aria-hidden="true" />{{ text('header', 'hero_greeting') }}
                </p>

                <div class="ps-orbit-stage relative mt-8">
                    <div class="ps-avatar-wrap" data-reveal style="--d: 150ms">
                        <span class="ps-ring" aria-hidden="true" />
                        <span class="ps-ring ps-ring-2" aria-hidden="true" />
                        <div class="ps-avatar group">
                            <img
                                v-if="media('header', 'hero_image')"
                                :src="media('header', 'hero_image')"
                                :alt="heroName ?? ''"
                                class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.07]"
                            />
                            <span v-else-if="heroInitials" class="ps-display flex h-full w-full items-center justify-center bg-[var(--accent)] text-5xl text-[var(--accent-ink)]">{{
                                heroInitials
                            }}</span>
                        </div>
                    </div>

                    <template v-for="(b, i) in orbitBadges" :key="b.key">
                        <component
                            :is="b.href ? 'a' : 'span'"
                            :href="b.href || undefined"
                            :target="b.href ? '_blank' : undefined"
                            :rel="b.href ? 'noopener noreferrer' : undefined"
                            class="ps-badge"
                            :class="`ps-badge-${i}`"
                        >
                            <component :is="b.icon" class="h-3.5 w-3.5 text-[var(--accent)]" />{{ b.label }}
                        </component>
                    </template>
                </div>

                <h1 v-if="heroName" class="ps-display ps-hero-name mt-10">
                    <span class="sr-only">{{ heroName }}</span>
                    <span v-for="(w, i) in heroWords" :key="i" class="ps-mask" aria-hidden="true">
                        <span class="ps-word" :style="{ '--i': i }">{{ w }}</span>
                    </span>
                </h1>

                <p
                    v-if="text('header', 'hero_description')"
                    data-reveal
                    class="ps-muted mt-6 max-w-[38rem] whitespace-pre-line text-lg leading-[1.7]"
                    style="--d: 460ms"
                >
                    {{ text('header', 'hero_description') }}
                </p>

                <div v-if="heroPrimary || heroSecondary" data-reveal class="mt-9 flex flex-wrap justify-center gap-3" style="--d: 540ms">
                    <a v-if="heroPrimary" :href="heroPrimary.url" class="ps-btn ps-btn-primary" @click="onNavClick($event, heroPrimary.url)"
                        >{{ heroPrimary.text }}<ArrowUpRight class="ps-arrow h-4 w-4"
                    /></a>
                    <a v-if="heroSecondary" :href="heroSecondary.url" class="ps-btn ps-btn-ghost" @click="onNavClick($event, heroSecondary.url)">{{
                        heroSecondary.text
                    }}</a>
                </div>

                <ul v-if="heroSocials.length" data-reveal class="mt-9 flex flex-wrap justify-center gap-2" style="--d: 620ms">
                    <li v-for="(s, i) in heroSocials" :key="i">
                        <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="ps-icon-btn">
                            <component :is="socialIconFor(s.platform)" class="h-[18px] w-[18px]" />
                        </a>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== STACK ============================== -->
        <main class="ps-stack">
            <!-- ============================== ABOUT ============================== -->
            <section v-if="has('about')" id="about" class="ps-sheet" style="--z: 1">
                <div class="ps-sheet-in">
                    <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-14">
                        <div v-if="media('about', 'about_image')" data-reveal class="lg:col-span-5">
                            <div class="ps-frame mx-auto max-w-md">
                                <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="aspect-[4/5] w-full rounded-[1.6rem] object-cover" loading="lazy" />
                            </div>
                        </div>

                        <div :class="media('about', 'about_image') ? 'lg:col-span-7' : 'lg:col-span-12'">
                            <div data-reveal>
                                <p v-if="text('about', 'about_subtitle')" class="ps-kicker">{{ text('about', 'about_subtitle') }}</p>
                                <h2 v-if="text('about', 'about_title')" class="ps-display ps-h2 mt-4">{{ text('about', 'about_title') }}</h2>
                                <p v-if="text('about', 'about_description')" class="ps-muted mt-7 max-w-[38rem] whitespace-pre-line text-lg leading-[1.75]">
                                    {{ text('about', 'about_description') }}
                                </p>
                            </div>

                            <ul v-if="highlights.length" data-reveal class="mt-10 grid gap-3 sm:grid-cols-2">
                                <li v-for="(h, i) in highlights" :key="i" :style="{ '--k': i }" class="ps-hl ps-pop">
                                    <span class="ps-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>
                                    <span>
                                        <span class="block font-semibold leading-snug">{{ h.title }}</span>
                                        <span v-if="h.description" class="ps-muted mt-1 block text-[0.92rem] leading-relaxed">{{ h.description }}</span>
                                    </span>
                                </li>
                            </ul>

                            <div v-if="cvFile || aboutCta" data-reveal class="mt-10 flex flex-wrap gap-3">
                                <a v-if="aboutCta && cvFile" :href="aboutCta.url" class="ps-btn ps-btn-ghost" @click="onNavClick($event, aboutCta.url)">
                                    {{ aboutCta.text }}<ArrowUpRight class="ps-arrow h-4 w-4" />
                                </a>
                                <a v-else-if="aboutCta" :href="aboutCta.url" class="ps-btn ps-btn-primary" @click="onNavClick($event, aboutCta.url)">
                                    {{ aboutCta.text }}<ArrowUpRight class="ps-arrow h-4 w-4" />
                                </a>
                                <a v-else-if="cvFile" :href="cvFile" download target="_blank" rel="noopener" class="ps-btn ps-btn-primary">
                                    <Download class="h-4 w-4" />Download CV</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================== JOURNEY ============================== -->
            <section v-if="has('journey')" id="journey" class="ps-sheet" style="--z: 2">
                <div class="ps-sheet-in">
                    <div class="grid gap-12 lg:grid-cols-12 lg:gap-14">
                        <div class="lg:col-span-4">
                            <div data-reveal class="lg:sticky lg:top-28">
                                <p v-if="text('journey', 'journey_subtitle')" class="ps-kicker">{{ text('journey', 'journey_subtitle') }}</p>
                                <h2 v-if="text('journey', 'journey_title')" class="ps-display ps-h2 mt-4">{{ text('journey', 'journey_title') }}</h2>
                            </div>
                        </div>

                        <ol class="ps-timeline lg:col-span-8">
                            <li v-for="(item, index) in journey" :key="index" data-reveal :style="{ '--d': `${(index % 4) * 70}ms` }" class="ps-tl-item">
                                <div class="ps-tl-card">
                                    <p v-if="item.period" class="ps-mono text-[0.78rem] font-medium uppercase tracking-[0.12em] text-[var(--accent)]">{{ item.period }}</p>
                                    <h3 v-if="item.title" class="ps-display mt-2 text-2xl leading-tight">{{ item.title }}</h3>
                                    <p v-if="item.company" class="mt-1 font-semibold">{{ item.company }}</p>
                                    <p v-if="item.description" class="ps-muted mt-3 max-w-[38rem] whitespace-pre-line leading-[1.7]">{{ item.description }}</p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <!-- ============================== SKILLS ============================== -->
            <section v-if="has('skills')" id="skills" class="ps-sheet" style="--z: 3">
                <div class="ps-sheet-in">
                    <div class="grid gap-12 lg:grid-cols-12 lg:gap-14">
                        <div class="lg:col-span-4">
                            <div data-reveal class="lg:sticky lg:top-28">
                                <p v-if="text('skills', 'skills_subtitle')" class="ps-kicker">{{ text('skills', 'skills_subtitle') }}</p>
                                <h2 v-if="text('skills', 'skills_title')" class="ps-display ps-h2 mt-4">{{ text('skills', 'skills_title') }}</h2>
                                <p v-if="text('skills', 'skills_description')" class="ps-muted mt-6 whitespace-pre-line leading-[1.7]">{{ text('skills', 'skills_description') }}</p>
                            </div>
                        </div>

                        <ul class="grid content-start gap-4 sm:grid-cols-2 lg:col-span-8">
                            <li v-for="(s, i) in skills" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 50}ms` }" class="ps-skill">
                                <div class="flex items-center gap-3.5">
                                    <span v-if="showIcon" class="ps-tile">
                                        <component :is="iconFor(s.icon)" v-if="iconFor(s.icon)" class="h-[18px] w-[18px]" />
                                        <span v-else-if="isGlyph(s.icon)" class="text-lg leading-none" aria-hidden="true">{{ s.icon }}</span>
                                        <span v-else class="ps-display text-base leading-none" aria-hidden="true">{{ s.name.charAt(0).toUpperCase() }}</span>
                                    </span>
                                    <h3 class="font-semibold">{{ s.name }}</h3>
                                    <span
                                        v-if="showLevel && s.pct !== null"
                                        class="ps-count ps-mono ps-muted ml-auto text-[0.8rem] font-medium"
                                        :style="{ '--p': Math.round(s.pct) }"
                                        aria-hidden="true"
                                    />
                                    <span v-else-if="showLevel && s.label" class="ps-pill ml-auto">{{ s.label }}</span>
                                </div>
                                <div
                                    v-if="showLevel && s.pct !== null"
                                    class="ps-track mt-4"
                                    role="progressbar"
                                    :aria-valuenow="Math.round(s.pct)"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    :aria-label="s.name"
                                >
                                    <div class="ps-bar" :style="{ '--w': `${s.pct}%` }"><span class="ps-knob" /></div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- ============================== PROJECTS ============================== -->
            <section v-if="has('projects')" id="projects" class="ps-sheet" style="--z: 4">
                <div class="ps-sheet-in">
                    <div data-reveal class="mb-14 grid gap-6 lg:grid-cols-12 lg:items-end">
                        <div class="lg:col-span-7">
                            <p v-if="text('projects', 'projects_subtitle')" class="ps-kicker">{{ text('projects', 'projects_subtitle') }}</p>
                            <h2 v-if="text('projects', 'projects_title')" class="ps-display ps-h2 mt-4">{{ text('projects', 'projects_title') }}</h2>
                        </div>
                        <p v-if="text('projects', 'projects_description')" class="ps-muted whitespace-pre-line leading-[1.7] lg:col-span-5">
                            {{ text('projects', 'projects_description') }}
                        </p>
                    </div>

                    <ul class="grid gap-7 md:grid-cols-2 xl:grid-cols-3">
                        <li
                            v-for="(p, i) in projects"
                            :key="i"
                            data-reveal
                            :class="featureFirst && i === 0 ? 'md:col-span-2 xl:col-span-2' : ''"
                            :style="{ '--d': `${(i % 3) * 80}ms` }"
                        >
                            <article class="ps-project ps-elevate group flex h-full flex-col">
                                <component
                                    :is="p.live ? 'a' : 'div'"
                                    :href="p.live || undefined"
                                    :target="p.live ? '_blank' : undefined"
                                    :rel="p.live ? 'noopener noreferrer' : undefined"
                                    :aria-label="p.live ? p.title : undefined"
                                    class="ps-media relative block overflow-hidden"
                                    :class="featureFirst && i === 0 ? 'aspect-[16/9]' : 'aspect-[4/3]'"
                                >
                                    <img v-if="p.image" :src="p.image" :alt="p.title" class="h-full w-full object-cover" loading="lazy" />
                                    <div v-else class="ps-display ps-tone flex h-full w-full items-center justify-center text-8xl" :class="`ps-tone-${i % 3}`">
                                        {{ p.title.charAt(0).toUpperCase() }}
                                    </div>
                                    <span v-if="featureFirst && i === 0" class="ps-flag ps-mono"><Sparkles class="h-3.5 w-3.5" />{{ String(i + 1).padStart(2, '0') }}</span>
                                    <span v-if="p.live" class="ps-go" aria-hidden="true"><ArrowUpRight class="h-5 w-5" /></span>
                                </component>

                                <div class="flex flex-1 flex-col px-2 pb-2 pt-5">
                                    <h3 class="ps-display text-2xl leading-tight"><span class="ps-underline">{{ p.title }}</span></h3>
                                    <p v-if="p.description" class="ps-muted mt-3 leading-[1.65]">{{ p.description }}</p>
                                    <ul v-if="p.tags.length" class="mt-4 flex flex-wrap gap-2">
                                        <li v-for="(t, ti) in p.tags" :key="ti" class="ps-pill">{{ t }}</li>
                                    </ul>
                                    <div v-if="p.repo" class="mt-auto pt-5">
                                        <a :href="p.repo" target="_blank" rel="noopener noreferrer" class="ps-textlink ps-mono text-[0.8rem]"
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
            <section v-if="has('services')" id="services" class="ps-sheet" style="--z: 5">
                <div class="ps-sheet-in">
                    <div data-reveal class="mb-14 max-w-3xl">
                        <p v-if="text('services', 'services_subtitle')" class="ps-kicker">{{ text('services', 'services_subtitle') }}</p>
                        <h2 v-if="text('services', 'services_title')" class="ps-display ps-h2 mt-4">{{ text('services', 'services_title') }}</h2>
                    </div>

                    <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <li v-for="(s, i) in services" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 70}ms` }">
                            <div class="ps-service ps-elevate group flex h-full flex-col">
                                <span class="ps-tile ps-tile-lg">
                                    <component :is="iconFor(s.icon) ?? Sparkles" v-if="!isGlyph(s.icon)" class="h-6 w-6" />
                                    <span v-else class="text-2xl leading-none" aria-hidden="true">{{ s.icon }}</span>
                                </span>
                                <h3 class="ps-display ps-service-title mt-7 text-2xl leading-tight">{{ s.title }}</h3>
                                <p v-if="s.description" class="ps-muted mt-3 leading-[1.65]">{{ s.description }}</p>
                                <ArrowUpRight class="ps-service-arrow mt-auto h-6 w-6 self-end" aria-hidden="true" />
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- ============================== CONTACT ============================== -->
            <section v-if="has('contact')" id="contact" class="ps-sheet ps-sheet-contact" style="--z: 6">
                <div class="ps-sheet-in relative z-10">
                    <div class="grid gap-14 lg:grid-cols-12 lg:gap-12">
                        <div class="lg:col-span-7">
                            <div data-reveal>
                                <p v-if="text('contact', 'contact_subtitle')" class="ps-kicker !text-current">{{ text('contact', 'contact_subtitle') }}</p>
                                <h2 v-if="text('contact', 'contact_title')" class="ps-display mt-4 text-[clamp(2.75rem,7.5vw,6rem)] leading-[0.95] [overflow-wrap:anywhere]">
                                    {{ text('contact', 'contact_title') }}
                                </h2>
                                <p v-if="text('contact', 'contact_description')" class="mt-6 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7] opacity-80">
                                    {{ text('contact', 'contact_description') }}
                                </p>
                            </div>

                            <div v-if="email || phone || location" data-reveal class="mt-10 space-y-5">
                                <a v-if="email" :href="`mailto:${email}`" class="ps-bigmail ps-display group inline-flex max-w-full items-center gap-3 break-all text-2xl sm:text-4xl">
                                    <Mail class="h-7 w-7 shrink-0" /><span class="ps-underline-contact">{{ email }}</span>
                                    <ArrowUpRight class="ps-arrow h-7 w-7 shrink-0" />
                                </a>
                                <div class="flex flex-wrap gap-x-8 gap-y-3 font-medium">
                                    <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="ps-contact-link"><Phone class="h-4 w-4" />{{ phone }}</a>
                                    <span v-if="location" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4" />{{ location }}</span>
                                </div>
                            </div>

                            <ul v-if="contactSocials.length" data-reveal class="mt-8 flex flex-wrap gap-2">
                                <li v-for="(s, i) in contactSocials" :key="i">
                                    <a :href="s.url" target="_blank" rel="noopener noreferrer" class="ps-social">
                                        <component :is="socialIconFor(s.platform)" class="h-3.5 w-3.5" />{{ s.label }}<ArrowUpRight class="ps-arrow h-3.5 w-3.5" />
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
                                    target="ps-gform-frame"
                                    class="ps-form space-y-5 p-6 sm:p-8"
                                    @submit="gf.url && gf.name && gf.email && gf.message ? onSubmit($event) : handleContactFormFallback($event, text('contact', 'contact_email'))"
                                >
                                    <label class="block">
                                        <span class="ps-mono mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.12em] opacity-70">Name</span>
                                        <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="ps-input" />
                                    </label>
                                    <label class="block">
                                        <span class="ps-mono mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.12em] opacity-70">Email</span>
                                        <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="ps-input" />
                                    </label>
                                    <label class="block">
                                        <span class="ps-mono mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.12em] opacity-70">Message</span>
                                        <textarea :name="`entry.${gf.message}`" rows="5" required class="ps-input resize-y" />
                                    </label>
                                    <button type="submit" class="ps-btn ps-btn-primary w-full justify-center" :disabled="sending">
                                        {{ sending ? '…' : 'Send' }}<Send class="ps-arrow h-4 w-4" />
                                    </button>
                                    <p v-if="sent" role="status" class="ps-sent flex items-center gap-2 text-sm font-semibold">
                                        <span class="ps-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>OK
                                    </p>
                                </form>
                                <a v-else-if="email" :href="`mailto:${email}`" class="ps-btn ps-btn-ghost w-full justify-center !py-5 text-lg"><Mail class="h-5 w-5" />{{ email }}</a>
                            </div>
                            <iframe name="ps-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                        </div>
                    </div>
                </div>
            </section>

            <footer v-if="heroName || logoText" class="mx-auto flex max-w-[1180px] flex-wrap items-center justify-between gap-2 px-5 py-10 text-sm sm:px-8">
                <span class="ps-muted ps-mono text-[0.78rem]">&copy; {{ year }} {{ heroName ?? logoText }}</span>
                <a href="#header" class="ps-textlink" aria-label="Top" @click="onNavClick($event, '#header')"><ArrowUpRight class="ps-arrow h-4 w-4 -rotate-45" /></a>
            </footer>
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500..700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

/* ---------- Foundations ---------- */
.ps-root {
    position: relative;
    background: var(--paper);
    color: var(--ink);
    font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    overflow-x: clip;
    transition:
        background-color 0.4s ease,
        color 0.4s ease;
}
.ps-display {
    font-family: 'Fraunces', 'Plus Jakarta Sans', ui-serif, Georgia, serif;
    font-weight: 600;
    letter-spacing: -0.03em;
}
.ps-mono {
    font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
}
.ps-muted {
    color: var(--muted);
}
.ps-h2 {
    font-size: clamp(2.2rem, 4.8vw, 3.9rem);
    line-height: 1.04;
    letter-spacing: -0.035em;
    text-wrap: balance;
}
.ps-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
    border-radius: 0.5rem;
}
.ps-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--accent);
}
.ps-kicker::before {
    content: '';
    width: 1.75rem;
    height: 2px;
    background: currentColor;
    transform-origin: left;
    transition: transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) 0.2s;
}
.ps-js [data-reveal]:not(.is-in) .ps-kicker::before {
    transform: scaleX(0);
}

/* ---------- Ambient light spots ---------- */
.ps-ambient {
    position: fixed;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    pointer-events: none;
}
.ps-spot {
    position: absolute;
    border-radius: 999px;
    filter: blur(90px);
}
.ps-spot-a {
    height: 30rem;
    width: 30rem;
    left: -8rem;
    top: -6rem;
    background: var(--accent);
    opacity: 0.28;
    animation: ps-drift 22s ease-in-out infinite;
}
.ps-spot-b {
    height: 24rem;
    width: 24rem;
    right: -6rem;
    top: 20%;
    background: var(--accent-2);
    opacity: 0.2;
    animation: ps-drift 28s ease-in-out infinite reverse;
}
.ps-spot-c {
    height: 26rem;
    width: 26rem;
    left: 30%;
    bottom: -12rem;
    background: var(--accent);
    opacity: 0.18;
    animation: ps-drift 34s ease-in-out infinite;
}
.ps-root > header,
.ps-root > section,
.ps-root > main {
    position: relative;
}
.ps-root > header {
    position: fixed;
}
.ps-root > header.absolute {
    position: absolute;
}

/* ---------- Navbar ---------- */
.ps-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
    z-index: 60;
}
.ps-nav {
    border: 1px solid transparent;
    border-radius: 999px;
    background: color-mix(in srgb, var(--surface) 55%, transparent);
    -webkit-backdrop-filter: blur(14px) saturate(1.4);
    backdrop-filter: blur(14px) saturate(1.4);
    transition:
        border-color 0.35s ease,
        background-color 0.35s ease,
        box-shadow 0.35s ease;
}
.ps-nav.is-scrolled {
    border-color: var(--line);
    background: color-mix(in srgb, var(--surface) 85%, transparent);
    box-shadow: 0 16px 36px -16px color-mix(in srgb, var(--ink) 40%, transparent);
}
.ps-navlink {
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
.ps-navlink:hover {
    color: var(--ink);
}
.ps-navlink[data-active='true'] {
    color: var(--ink);
    background: var(--soft);
}

/* ---------- Buttons & small controls ---------- */
.ps-btn {
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
.ps-btn:hover {
    background-size: 100% 100%;
    transform: translateY(-2px);
}
.ps-btn:active {
    transform: translateY(0) scale(0.98);
}
.ps-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.ps-btn-primary {
    background-color: var(--accent);
    background-image: linear-gradient(var(--ink), var(--ink));
    color: var(--accent-ink);
    box-shadow: 0 10px 24px -12px var(--accent);
}
.ps-btn-primary:hover {
    color: var(--surface);
}
.ps-btn-ghost {
    background-color: transparent;
    background-image: linear-gradient(var(--ink), var(--ink));
    border-color: var(--line);
    color: var(--ink);
}
.ps-btn-ghost:hover {
    color: var(--surface);
    border-color: var(--ink);
}
.ps-arrow {
    transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ps-btn:hover .ps-arrow,
.ps-bigmail:hover .ps-arrow,
.ps-social:hover .ps-arrow,
.ps-textlink:hover .ps-arrow {
    transform: translate(3px, -3px);
}
.ps-icon-btn {
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
.ps-icon-btn:hover {
    transform: translateY(-3px);
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
}
.ps-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.45rem 1rem 0.45rem 0.85rem;
    font-size: 0.9rem;
    font-weight: 600;
    box-shadow: 0 10px 24px -16px color-mix(in srgb, var(--ink) 50%, transparent);
}
.ps-pulse {
    position: relative;
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 999px;
    background: #22c55e;
}
.ps-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: inherit;
    animation: ps-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.ps-pill {
    display: inline-block;
    border-radius: 999px;
    border: 1px solid var(--line);
    padding: 0.2rem 0.7rem;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--muted);
}
.ps-tick {
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
.ps-textlink {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    background: linear-gradient(currentColor, currentColor) left bottom / 0% 1.5px no-repeat;
    transition: background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 2px;
}
.ps-textlink:hover {
    background-size: 100% 1.5px;
}

/* ---------- Hero ---------- */
.ps-hero-name {
    font-size: clamp(3rem, 9vw, 7.5rem);
    line-height: 0.96;
    letter-spacing: -0.045em;
    overflow-wrap: anywhere;
}
.ps-mask {
    display: inline-block;
    overflow: hidden;
    vertical-align: top;
    padding-bottom: 0.14em;
    margin-bottom: -0.14em;
    margin-right: 0.22em;
}
.ps-word {
    display: inline-block;
}
.ps-js .ps-word {
    transform: translateY(110%);
    animation: ps-rise 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: calc(0.15s + var(--i, 0) * 0.1s);
}
.ps-orbit-stage {
    display: flex;
    height: 11rem;
    width: min(100%, 34rem);
    align-items: center;
    justify-content: center;
}
.ps-avatar-wrap {
    position: relative;
    height: 9.5rem;
    width: 9.5rem;
}
.ps-avatar {
    position: absolute;
    inset: 0;
    overflow: hidden;
    border-radius: 999px;
    border: 5px solid var(--surface);
    background: var(--soft);
    box-shadow: 0 26px 50px -22px color-mix(in srgb, var(--ink) 60%, transparent);
}
.ps-ring {
    position: absolute;
    inset: -0.9rem;
    border-radius: 999px;
    border: 1.5px dashed color-mix(in srgb, var(--accent) 65%, transparent);
    animation: ps-spin 40s linear infinite;
}
.ps-ring-2 {
    inset: -1.9rem;
    border: 1px solid var(--line);
    animation-duration: 26s;
    animation-direction: reverse;
}
.ps-ring-2::after {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    height: 0.7rem;
    width: 0.7rem;
    transform: translate(-50%, -50%);
    border-radius: 999px;
    background: var(--accent-2);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent-2) 25%, transparent);
}
.ps-badge {
    position: absolute;
    display: inline-flex;
    max-width: 14rem;
    align-items: center;
    gap: 0.45rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: color-mix(in srgb, var(--surface) 85%, transparent);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    padding: 0.45rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 600;
    box-shadow: 0 14px 28px -16px color-mix(in srgb, var(--ink) 50%, transparent);
    animation: ps-float 6s ease-in-out infinite;
    transition:
        border-color 0.3s ease,
        transform 0.3s ease;
}
a.ps-badge:hover {
    border-color: var(--accent);
}
.ps-badge-0 {
    left: 2%;
    top: 4%;
}
.ps-badge-1 {
    right: 2%;
    top: 12%;
    animation-delay: -1.5s;
    animation-duration: 7s;
}
.ps-badge-2 {
    left: 6%;
    bottom: 2%;
    animation-delay: -3s;
}
.ps-badge-3 {
    right: 6%;
    bottom: 6%;
    animation-delay: -4.5s;
    animation-duration: 7.5s;
}
@media (max-width: 639px) {
    .ps-orbit-stage {
        height: 14rem;
    }
    .ps-badge {
        font-size: 0.72rem;
        max-width: 9rem;
    }
}

/* ---------- Overlapping paper sheets ---------- */
.ps-stack {
    position: relative;
    z-index: 2;
    margin-top: -6rem;
    padding-inline: 0.5rem;
}
@media (min-width: 640px) {
    .ps-stack {
        padding-inline: 1.5rem;
    }
}
.ps-sheet {
    position: relative;
    z-index: var(--z, 1);
    margin: 0 auto;
    max-width: 1180px;
    border: 1px solid var(--line);
    border-radius: 2.5rem;
    background: var(--surface);
    box-shadow:
        0 -22px 60px -30px color-mix(in srgb, var(--ink) 45%, transparent),
        0 40px 90px -40px color-mix(in srgb, var(--ink) 55%, transparent);
    scroll-margin-top: 3rem;
}
.ps-sheet + .ps-sheet {
    margin-top: -3.5rem;
    padding-top: 3.5rem;
}
.ps-sheet:first-child {
    transform: translateY(calc(var(--sy, 0) * -0.02px));
}
.ps-sheet:nth-child(even) {
    background: var(--soft);
}
.ps-sheet-in {
    padding: 4rem 1.4rem 5rem;
}
@media (min-width: 640px) {
    .ps-sheet-in {
        padding: 5rem 2.75rem 6rem;
    }
}
@media (min-width: 1024px) {
    .ps-sheet-in {
        padding: 6rem 4.25rem 7rem;
    }
}
.ps-sheet-contact {
    overflow: hidden;
    background: var(--ink);
    color: var(--surface);
    border-color: transparent;
    margin-bottom: 0;
}
.ps-sheet-contact.ps-sheet:nth-child(even) {
    background: var(--ink);
}
.ps-sheet-contact::before,
.ps-sheet-contact::after {
    content: '';
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    filter: blur(70px);
}
.ps-sheet-contact::before {
    height: 24rem;
    width: 24rem;
    right: -8rem;
    top: -9rem;
    background: var(--accent);
    opacity: 0.55;
    animation: ps-drift 16s ease-in-out infinite;
}
.ps-sheet-contact::after {
    height: 16rem;
    width: 16rem;
    left: -5rem;
    bottom: -6rem;
    background: var(--accent-2);
    opacity: 0.35;
    animation: ps-drift 20s ease-in-out infinite reverse;
}
.ps-sheet-contact .ps-kicker {
    color: var(--accent-2);
}
.ps-sheet-contact .ps-ink-soft {
    opacity: 0.8;
}

/* ---------- About ---------- */
.ps-frame {
    border-radius: 2rem;
    border: 1px solid var(--line);
    background: var(--paper);
    padding: 0.55rem;
    box-shadow: 0 28px 50px -30px color-mix(in srgb, var(--ink) 60%, transparent);
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ps-frame:hover {
    transform: rotate(-1.4deg) scale(1.02);
}
.ps-hl {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    border: 1px solid var(--line);
    border-radius: 1.25rem;
    background: var(--paper);
    padding: 1.1rem 1.2rem;
    transition:
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1),
        box-shadow 0.4s ease,
        border-color 0.3s ease;
}
.ps-hl:hover {
    transform: translateY(-4px) scale(1.02);
    border-color: var(--accent);
    box-shadow: 0 20px 36px -22px var(--accent);
}

/* ---------- Journey ---------- */
.ps-timeline {
    position: relative;
    margin-left: 0.5rem;
    border-left: 1.5px solid var(--line);
    padding-left: 2.25rem;
}
.ps-tl-item {
    position: relative;
    padding-bottom: 1.5rem;
}
.ps-tl-item:last-child {
    padding-bottom: 0;
}
.ps-tl-item::before {
    content: '';
    position: absolute;
    left: -2.25rem;
    top: 1.7rem;
    height: 0.85rem;
    width: 0.85rem;
    transform: translateX(-50%);
    border-radius: 999px;
    background: var(--surface);
    border: 2px solid var(--accent);
    transition:
        background-color 0.5s ease 0.3s,
        box-shadow 0.4s ease;
}
.ps-js .ps-tl-item.is-in::before {
    background: var(--accent);
}
.ps-tl-item:hover::before {
    box-shadow: 0 0 0 6px color-mix(in srgb, var(--accent) 20%, transparent);
}
.ps-tl-item::after {
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
.ps-js .ps-tl-item.is-in::after {
    transform: scaleY(1);
}
.ps-tl-item:last-child::after {
    display: none;
}
.ps-tl-card {
    border: 1px solid var(--line);
    border-radius: 1.5rem;
    background: var(--paper);
    padding: 1.4rem 1.6rem 1.5rem;
    transition:
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1),
        box-shadow 0.45s ease,
        border-color 0.3s ease;
}
.ps-tl-card:hover {
    transform: translateX(6px);
    border-color: color-mix(in srgb, var(--accent) 55%, var(--line));
    box-shadow: 0 22px 40px -26px var(--accent);
}

/* ---------- Skills ---------- */
.ps-skill {
    border: 1px solid var(--line);
    border-radius: 1.4rem;
    background: var(--paper);
    padding: 1.2rem 1.3rem 1.3rem;
    transition:
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease,
        box-shadow 0.4s ease;
}
.ps-skill:hover {
    transform: translateY(-3px);
    border-color: var(--accent);
    box-shadow: 0 18px 32px -22px var(--accent);
}
.ps-tile {
    display: inline-flex;
    height: 2.6rem;
    width: 2.6rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.95rem;
    background: color-mix(in srgb, var(--accent) 13%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 35%, var(--line));
    color: var(--accent);
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ps-tile-lg {
    height: 3.5rem;
    width: 3.5rem;
    border-radius: 1.15rem;
}
.ps-skill:hover .ps-tile,
.ps-service:hover .ps-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.ps-track {
    position: relative;
    height: 6px;
    width: 100%;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ink) 10%, transparent);
}
.ps-bar {
    position: relative;
    height: 100%;
    width: var(--w, 0%);
    border-radius: inherit;
    background: linear-gradient(90deg, color-mix(in srgb, var(--accent) 55%, transparent), var(--accent));
}
.ps-knob {
    position: absolute;
    right: 0;
    top: 50%;
    height: 14px;
    width: 14px;
    transform: translate(50%, -50%);
    border-radius: 999px;
    border: 3px solid var(--accent);
    background: var(--surface);
}
.ps-js [data-reveal] .ps-bar {
    width: 0;
    transition: width 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s);
}
.ps-js [data-reveal].is-in .ps-bar {
    width: var(--w, 0%);
}
.ps-js [data-reveal].is-in .ps-knob {
    animation: ps-knob 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) calc(var(--d, 0ms) + 1.6s) backwards;
}

/* Percentage counts up in sync with the bar */
@property --n {
    syntax: '<integer>';
    inherits: false;
    initial-value: 0;
}
.ps-count {
    --n: var(--p, 0);
    counter-reset: n var(--n);
    font-variant-numeric: tabular-nums;
}
.ps-count::after {
    content: counter(n) '%';
}
.ps-js [data-reveal] .ps-count {
    --n: 0;
}
.ps-js [data-reveal].is-in .ps-count {
    animation: ps-count 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s) forwards;
}

/* ---------- 3D scale elevation (projects + services) ---------- */
.ps-elevate {
    position: relative;
    border: 1px solid var(--line);
    border-radius: 1.9rem;
    background: var(--paper);
    transform: perspective(1100px) translateZ(0) scale(1);
    transform-style: preserve-3d;
    transition:
        transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1),
        box-shadow 0.5s ease,
        border-color 0.3s ease;
    will-change: transform;
}
.ps-elevate:hover {
    z-index: 5;
    transform: perspective(1100px) translateY(-10px) translateZ(40px) scale(1.045) rotateX(1.5deg);
    border-color: color-mix(in srgb, var(--accent) 60%, var(--line));
    box-shadow:
        0 50px 90px -34px color-mix(in srgb, var(--ink) 65%, transparent),
        0 24px 50px -30px var(--accent);
}

/* ---------- Projects ---------- */
.ps-project {
    padding: 0.75rem;
}
.ps-media {
    border-radius: 1.4rem;
    background: var(--soft);
    border: 1px solid var(--line);
    isolation: isolate;
}
.ps-media img,
.ps-tone {
    transition: transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ps-project:hover .ps-media img,
.ps-project:hover .ps-tone {
    transform: scale(1.07);
}
.ps-tone-0 {
    background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 45%, #000));
    color: var(--accent-ink);
}
.ps-tone-1 {
    background: var(--accent-2);
    color: #16151a;
}
.ps-tone-2 {
    background: var(--ink);
    color: var(--surface);
}
.ps-flag {
    position: absolute;
    left: 0.9rem;
    top: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    background: var(--accent-2);
    color: #16151a;
    padding: 0.25rem 0.75rem;
    font-size: 0.74rem;
    font-weight: 500;
}
.ps-go {
    position: absolute;
    right: 0.9rem;
    top: 0.9rem;
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--surface);
    color: var(--ink);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem) scale(0.9);
    transition:
        opacity 0.35s ease,
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ps-project:hover .ps-go,
.ps-media:focus-visible .ps-go {
    opacity: 1;
    transform: none;
}
.ps-underline {
    background: linear-gradient(currentColor, currentColor) left 96% / 0% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ps-project:hover .ps-underline {
    background-size: 100% 2px;
}

/* ---------- Services ---------- */
.ps-service {
    padding: 1.9rem;
}
.ps-service-title {
    transition: color 0.3s ease;
}
.ps-service:hover .ps-service-title {
    color: var(--accent);
}
.ps-service-arrow {
    color: var(--accent);
    opacity: 0;
    transform: translate(-0.6rem, 0.6rem);
    transition:
        opacity 0.3s ease,
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ps-service:hover .ps-service-arrow {
    opacity: 1;
    transform: none;
}

/* ---------- Contact ---------- */
.ps-underline-contact {
    background: linear-gradient(currentColor, currentColor) left bottom / 100% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 4px;
}
.ps-bigmail:hover .ps-underline-contact {
    background-size: 0% 2px;
    background-position: right bottom;
}
.ps-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.9;
}
.ps-contact-link:hover {
    opacity: 1;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.ps-social {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, currentColor 40%, transparent);
    padding: 0.5rem 1rem;
    font-size: 0.88rem;
    font-weight: 600;
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        border-color 0.3s ease,
        transform 0.3s ease;
}
.ps-social:hover {
    background: var(--surface);
    color: var(--ink);
    border-color: var(--surface);
    transform: translateY(-2px);
}
.ps-form {
    border-radius: 1.9rem;
    border: 1px solid color-mix(in srgb, var(--surface) 22%, transparent);
    background: color-mix(in srgb, var(--surface) 8%, transparent);
    -webkit-backdrop-filter: blur(16px);
    backdrop-filter: blur(16px);
}
.ps-input {
    width: 100%;
    border-radius: 0.95rem;
    border: 1px solid color-mix(in srgb, var(--surface) 28%, transparent);
    background: color-mix(in srgb, var(--surface) 6%, transparent);
    padding: 0.8rem 1rem;
    font: inherit;
    font-weight: 500;
    color: var(--surface);
    transition:
        border-color 0.25s ease,
        box-shadow 0.25s ease,
        background-color 0.25s ease;
}
.ps-input:hover {
    border-color: color-mix(in srgb, var(--surface) 55%, transparent);
}
.ps-input:focus {
    outline: none;
    border-color: var(--accent-2);
    background: color-mix(in srgb, var(--surface) 10%, transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent-2) 25%, transparent);
}
.ps-sent {
    animation: ps-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
.ps-sheet-contact .ps-btn-primary {
    background-color: var(--accent-2);
    color: #16151a;
    background-image: linear-gradient(var(--surface), var(--surface));
}
.ps-sheet-contact .ps-btn-primary:hover {
    color: var(--ink);
}
.ps-sheet-contact .ps-btn-ghost {
    background-image: linear-gradient(var(--surface), var(--surface));
    border-color: color-mix(in srgb, var(--surface) 45%, transparent);
    color: var(--surface);
}
.ps-sheet-contact .ps-btn-ghost:hover {
    color: var(--ink);
}
.ps-sheet-contact .ps-tick {
    background: var(--accent-2);
    color: #16151a;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.ps-js [data-reveal] {
    opacity: 0;
    transform: translateY(24px);
    transition:
        opacity 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.ps-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}
.ps-js .ps-tl-item[data-reveal] {
    transform: translateX(26px);
}
.ps-js .ps-tl-item[data-reveal].is-in {
    transform: none;
}
/* Once revealed, cards hand transform back to the 3D hover rule */
.ps-js [data-reveal].is-in .ps-elevate {
    animation: ps-card-in 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
}

/* ---------- Section animations ---------- */
.ps-h2 {
    transition: letter-spacing 1.2s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ps-js [data-reveal]:not(.is-in) .ps-h2 {
    letter-spacing: 0.01em;
}
.ps-js [data-reveal].is-in .ps-frame {
    animation: ps-frame-in 1.1s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
}
.ps-js [data-reveal]:not(.is-in) .ps-hl {
    opacity: 0;
}
.ps-js [data-reveal].is-in .ps-hl {
    animation: ps-slide-x 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--k, 0) * 90ms + 150ms) backwards;
}
.ps-js [data-reveal].is-in .ps-hl .ps-tick {
    animation: ps-tick 0.6s cubic-bezier(0.3, 1.6, 0.4, 1) calc(var(--k, 0) * 90ms + 450ms) backwards;
}
.ps-js [data-reveal].is-in .ps-tile {
    animation: ps-tile 0.7s cubic-bezier(0.3, 1.5, 0.4, 1) calc(var(--d, 0ms) + 0.15s) backwards;
}
.ps-js [data-reveal].is-in .ps-media {
    animation: ps-wipe 1.2s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}
.ps-js [data-reveal].is-in .ps-media img {
    animation: ps-settle 1.6s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s backwards;
}

@keyframes ps-spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes ps-float {
    50% {
        transform: translateY(-10px);
    }
}
@keyframes ps-drift {
    50% {
        transform: translate(-2.5rem, 2rem) scale(1.08);
    }
}
@keyframes ps-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes ps-enter {
    from {
        opacity: 0;
    }
}
@keyframes ps-ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}
@keyframes ps-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}
@keyframes ps-knob {
    from {
        transform: translate(50%, -50%) scale(0);
    }
}
@keyframes ps-count {
    to {
        --n: var(--p);
    }
}
@keyframes ps-frame-in {
    from {
        clip-path: inset(0 0 100% 0 round 2rem);
        transform: rotate(4deg) scale(0.95);
    }
}
@keyframes ps-slide-x {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
}
@keyframes ps-tick {
    from {
        transform: scale(0) rotate(-90deg);
    }
}
@keyframes ps-tile {
    from {
        transform: scale(0.4) rotate(-20deg);
        opacity: 0;
    }
}
@keyframes ps-wipe {
    from {
        clip-path: inset(0 0 100% 0 round 1.4rem);
    }
}
@keyframes ps-settle {
    from {
        transform: scale(1.25);
    }
}
@keyframes ps-card-in {
    from {
        transform: perspective(1100px) translateY(30px) rotateX(8deg) scale(0.96);
    }
}
.ps-js.ps-root {
    animation: ps-enter 0.7s ease both;
}

/* ---------- Reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    .ps-root *,
    .ps-root *::before,
    .ps-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .ps-js .ps-word,
    .ps-js [data-reveal],
    .ps-js .ps-tl-item[data-reveal],
    .ps-js [data-reveal]:not(.is-in) .ps-hl {
        opacity: 1;
        transform: none;
    }
    .ps-js [data-reveal] .ps-count {
        --n: var(--p, 0);
    }
    .ps-js [data-reveal] .ps-bar {
        width: var(--w, 0%);
    }
    .ps-js [data-reveal]:not(.is-in) .ps-kicker::before {
        transform: none;
    }
    .ps-js [data-reveal]:not(.is-in) .ps-h2 {
        letter-spacing: -0.035em;
    }
    .ps-tl-item::after {
        transform: scaleY(1);
    }
    .ps-sheet:first-child {
        transform: none;
    }
    .ps-elevate:hover {
        transform: none;
    }
}
</style>
