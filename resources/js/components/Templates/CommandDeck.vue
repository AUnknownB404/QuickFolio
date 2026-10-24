<script setup lang="ts">
/**
 * Command Deck — quickFolio template
 * Slug suggestion: `command-deck`
 *
 * Interactive web-app dashboard: persistent left icon rail, top system bar with live section tabs,
 * and a single workspace of window-framed widgets. Tabs focus a view (overview shows everything);
 * every widget reads strictly from the seeded field keys. Light + dark via Tailwind `dark:`.
 *
 * Every visible value comes from the seeded field keys. Only small UI chrome labels are hardcoded.
 */
import { resolvePortfolioMediaUrl } from '@/lib/media';
import {
    Activity,
    ArrowUpRight,
    AtSign,
    Briefcase,
    Camera,
    Check,
    ChevronRight,
    Code,
    Database,
    Dribbble,
    Download,
    Facebook,
    Github,
    Globe,
    Instagram,
    LayoutGrid,
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
    User,
    Wrench,
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

const navDefs: { id: string; label: string; icon: Component }[] = [
    { id: 'about', label: 'About', icon: User },
    { id: 'journey', label: 'Journey', icon: Activity },
    { id: 'skills', label: 'Skills', icon: Wrench },
    { id: 'projects', label: 'Work', icon: LayoutGrid },
    { id: 'services', label: 'Services', icon: Briefcase },
];
const navLinks = computed(() => navDefs.filter((l) => has(l.id)));

// Hero
const heroName = computed(() => text('header', 'hero_name'));
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
                category,
                tags: category && !tags.includes(category) ? [...tags, category] : tags,
            };
        })
        .filter((p) => p.title),
);
const featureFirst = computed(() => flag('projects', 'projects_show_featured', false));

// Project tag filters (tab filters)
const projectFilter = ref<string>('');
const projectFilters = computed(() => {
    const set = new Set<string>();
    projects.value.forEach((p) => p.tags.forEach((t) => set.add(t)));
    return [...set].slice(0, 8);
});
const filteredProjects = computed(() => {
    const list = projectFilter.value ? projects.value.filter((p) => p.tags.includes(projectFilter.value)) : projects.value;
    return list;
});

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
/* Views, scroll progress, reveal                                      */
/* ------------------------------------------------------------------ */

const rootEl = ref<HTMLElement | null>(null);
const mainEl = ref<HTMLElement | null>(null);
const progressEl = ref<HTMLElement | null>(null);
const topEl = ref<HTMLElement | null>(null);
const ready = ref(false);
const view = ref<string>('overview'); // 'overview' shows every widget; a section id focuses that widget
const scrollPct = ref(0);
let observer: IntersectionObserver | null = null;
let scrollFrame: number | null = null;

const reduceMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const show = (id: string) => has(id) && (view.value === 'overview' || view.value === id);
const showHero = computed(() => has('header') && (view.value === 'overview' || view.value === 'header'));

const setView = async (id: string) => {
    view.value = id;
    await nextTick();
    observeReveals();
    window.scrollTo({ top: 0, behavior: reduceMotion() ? 'auto' : 'smooth' });
};

const jump = async (url?: string) => {
    if (!url) return;
    if (!url.startsWith('#') || url.length < 2) return;
    const id = url.slice(1);
    if (navLinks.value.some((l) => l.id === id) || id === 'contact' || id === 'header') {
        await setView(id === 'header' ? 'overview' : id);
        await nextTick();
        const el = document.getElementById(id);
        if (el && view.value === 'overview') el.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'start' });
    }
};
const onLink = (e: Event, url?: string) => {
    if (!url || !url.startsWith('#') || url.length < 2) return;
    e.preventDefault();
    jump(url);
};

const onScroll = () => {
    if (scrollFrame !== null) return;
    scrollFrame = window.requestAnimationFrame(() => {
        scrollFrame = null;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const p = max > 0 ? Math.min(1, window.scrollY / max) : 0;
        scrollPct.value = Math.round(p * 100);
        progressEl.value?.style.setProperty('transform', `scaleX(${p})`);
        topEl.value?.classList.toggle('is-scrolled', window.scrollY > 8);
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
        { threshold: 0.1, rootMargin: '0px 0px -5% 0px' },
    );
    els.forEach((el) => observer!.observe(el));
};

onMounted(async () => {
    ready.value = true; // reveal styles only apply after mount, so SSR output stays visible
    await nextTick();
    observeReveals();
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});
onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener('scroll', onScroll);
    if (scrollFrame !== null) window.cancelAnimationFrame(scrollFrame);
});
// Re-scan when content changes (editor live preview)
watch(data, async () => {
    if (!ready.value) return;
    if (view.value !== 'overview' && !has(view.value)) view.value = 'overview';
    await nextTick();
    observeReveals();
});
watch(projectFilter, async () => {
    await nextTick();
    observeReveals();
});

const year = new Date().getFullYear();
</script>

<template>
    <div
        ref="rootEl"
        class="cd-root [--paper:#F3F4F8] [--soft:#E9EBF2] [--surface:#FFFFFF] [--ink:#0E1220] [--muted:#0E1220A8] [--line:#0E122021] [--accent:#3B5BFF] [--accent-2:#12B981] [--accent-ink:#FFFFFF] selection:bg-[var(--accent)] selection:text-white dark:[--paper:#0B0E17] dark:[--soft:#10141F] dark:[--surface:#151A28] dark:[--ink:#EEF1FB] dark:[--muted:#EEF1FBA8] dark:[--line:#EEF1FB1F] dark:[--accent:#7C93FF] dark:[--accent-2:#34D399] dark:[--accent-ink:#0B0E17]"
        :class="{ 'cd-js': ready }"
    >
        <!-- ======================= LEFT ICON RAIL ======================= -->
        <aside class="cd-rail" :class="stickyNav ? 'is-sticky' : 'is-static'" aria-label="Workspace">
            <a
                v-if="logoImage || logoText"
                href="#header"
                class="cd-logo"
                :title="logoText"
                @click.prevent="setView('overview')"
            >
                <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-full w-full object-contain" />
                <span v-else class="cd-display text-sm">{{ logoText?.charAt(0).toUpperCase() }}</span>
            </a>

            <nav class="cd-rail-nav" aria-label="Sections">
                <button
                    v-if="has('header')"
                    type="button"
                    class="cd-rail-btn"
                    :data-active="view === 'overview' || view === 'header'"
                    :title="heroName ?? logoText"
                    :aria-label="heroName ?? logoText ?? 'Overview'"
                    @click="setView('overview')"
                >
                    <LayoutGrid class="h-[18px] w-[18px]" />
                </button>
                <button
                    v-for="l in navLinks"
                    :key="l.id"
                    type="button"
                    class="cd-rail-btn"
                    :data-active="view === l.id"
                    :title="l.label"
                    :aria-label="l.label"
                    @click="setView(l.id)"
                >
                    <component :is="l.icon" class="h-[18px] w-[18px]" />
                </button>
                <button
                    v-if="has('contact')"
                    type="button"
                    class="cd-rail-btn"
                    :data-active="view === 'contact'"
                    :title="text('contact', 'contact_title') ?? email"
                    :aria-label="text('contact', 'contact_title') ?? email ?? 'Contact'"
                    @click="setView('contact')"
                >
                    <Mail class="h-[18px] w-[18px]" />
                </button>
            </nav>

            <ul v-if="heroSocials.length" class="cd-rail-foot">
                <li v-for="(s, i) in heroSocials.slice(0, 4)" :key="i">
                    <a :href="s.url" target="_blank" rel="noopener noreferrer" class="cd-rail-btn" :title="s.label" :aria-label="s.label">
                        <component :is="socialIconFor(s.platform)" class="h-[17px] w-[17px]" />
                    </a>
                </li>
            </ul>
        </aside>

        <div class="cd-shell">
            <!-- ======================= TOP SYSTEM BAR ======================= -->
            <header class="cd-top" :class="stickyNav ? 'is-sticky' : ''">
                <div ref="progressEl" class="cd-progress" aria-hidden="true" />
                <div ref="topEl" class="cd-top-inner">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="cd-dot" aria-hidden="true" />
                        <p v-if="logoText || heroName" class="cd-mono truncate text-[0.78rem] font-medium uppercase tracking-[0.1em]">{{ logoText ?? heroName }}</p>
                        <ChevronRight v-if="view !== 'overview' && (logoText || heroName)" class="h-3.5 w-3.5 shrink-0 text-[var(--muted)]" aria-hidden="true" />
                        <p v-if="view !== 'overview'" class="cd-mono truncate text-[0.78rem] uppercase tracking-[0.1em] text-[var(--accent)]">
                            {{ navLinks.find((l) => l.id === view)?.label ?? text('contact', 'contact_subtitle') ?? text('contact', 'contact_title') ?? '' }}
                        </p>
                    </div>

                    <div class="cd-tabs" role="tablist" aria-label="Views">
                        <button v-if="has('header')" type="button" role="tab" class="cd-tab" :aria-selected="view === 'overview' || view === 'header'" @click="setView('overview')">
                            <LayoutGrid class="h-3.5 w-3.5" />{{ text('header', 'hero_greeting') ?? heroName }}
                        </button>
                        <button
                            v-for="l in navLinks"
                            :key="l.id"
                            type="button"
                            role="tab"
                            class="cd-tab"
                            :aria-selected="view === l.id"
                            @click="setView(l.id)"
                        >
                            <component :is="l.icon" class="h-3.5 w-3.5" />{{ l.label }}
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="cd-mono hidden text-[0.72rem] tabular-nums text-[var(--muted)] sm:inline" aria-hidden="true">{{ String(scrollPct).padStart(3, '0') }}%</span>
                        <a v-if="has('contact')" href="#contact" class="cd-btn cd-btn-primary !px-4 !py-2 !text-[0.8rem]" @click.prevent="setView('contact')">
                            {{ text('contact', 'contact_subtitle') ?? text('contact', 'contact_title') ?? email }}<ArrowUpRight class="cd-arrow h-3.5 w-3.5" />
                        </a>
                    </div>
                </div>

                <!-- mobile tab strip -->
                <div class="cd-tabs-mobile" role="tablist" aria-label="Views">
                    <button v-if="has('header')" type="button" role="tab" class="cd-tab" :aria-selected="view === 'overview' || view === 'header'" @click="setView('overview')">
                        <LayoutGrid class="h-3.5 w-3.5" />{{ text('header', 'hero_greeting') ?? heroName }}
                    </button>
                    <button v-for="l in navLinks" :key="l.id" type="button" role="tab" class="cd-tab" :aria-selected="view === l.id" @click="setView(l.id)">
                        <component :is="l.icon" class="h-3.5 w-3.5" />{{ l.label }}
                    </button>
                </div>
            </header>

            <!-- ============================ WORKSPACE ============================ -->
            <main ref="mainEl" class="cd-main">
                <!-- ============================== HERO / OVERVIEW ============================== -->
                <section v-if="showHero" id="header" class="cd-grid">
                    <article data-reveal class="cd-win cd-span-12" style="--d: 0ms">
                        <div class="cd-win-bar">
                            <span class="cd-lights" aria-hidden="true"><i /><i /><i /></span>
                            <span v-if="text('header', 'hero_role')" class="cd-mono cd-win-title">{{ text('header', 'hero_role') }}</span>
                            <span v-if="text('header', 'hero_greeting')" class="cd-live"><span class="cd-pulse" aria-hidden="true" />{{ text('header', 'hero_greeting') }}</span>
                        </div>

                        <div class="cd-hero">
                            <img
                                v-if="media('header', 'hero_background_image')"
                                :src="media('header', 'hero_background_image')"
                                alt=""
                                class="cd-hero-bg"
                            />
                            <div class="relative z-10 grid items-center gap-8 sm:grid-cols-[auto_1fr]">
                                <div v-if="media('header', 'hero_image') || heroInitials" class="cd-avatar">
                                    <img v-if="media('header', 'hero_image')" :src="media('header', 'hero_image')" :alt="heroName ?? ''" class="h-full w-full object-cover" />
                                    <span v-else class="cd-display flex h-full w-full items-center justify-center text-5xl text-[var(--accent-ink)]">{{ heroInitials }}</span>
                                </div>
                                <div class="min-w-0">
                                    <h1 v-if="heroName" class="cd-display cd-hero-name"><span class="cd-name-line">{{ heroName }}</span></h1>
                                    <p v-if="text('header', 'hero_description')" class="cd-muted mt-4 max-w-[36rem] whitespace-pre-line leading-[1.7]">
                                        {{ text('header', 'hero_description') }}
                                    </p>
                                    <div v-if="heroPrimary || heroSecondary" class="mt-6 flex flex-wrap gap-3">
                                        <a v-if="heroPrimary" :href="heroPrimary.url" class="cd-btn cd-btn-primary" @click="onLink($event, heroPrimary.url)"
                                            >{{ heroPrimary.text }}<ArrowUpRight class="cd-arrow h-4 w-4"
                                        /></a>
                                        <a v-if="heroSecondary" :href="heroSecondary.url" class="cd-btn cd-btn-ghost" @click="onLink($event, heroSecondary.url)">{{
                                            heroSecondary.text
                                        }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="heroSocials.length || location" class="cd-win-foot">
                            <ul v-if="heroSocials.length" class="flex flex-wrap gap-2">
                                <li v-for="(s, i) in heroSocials" :key="i">
                                    <a :href="s.url" target="_blank" rel="noopener noreferrer" class="cd-chip-link" :title="s.label">
                                        <component :is="socialIconFor(s.platform)" class="h-3.5 w-3.5" />{{ s.label }}
                                    </a>
                                </li>
                            </ul>
                            <span v-if="location" class="cd-mono inline-flex items-center gap-2 text-[0.75rem] text-[var(--muted)]"><MapPin class="h-3.5 w-3.5 text-[var(--accent)]" />{{ location }}</span>
                        </div>
                    </article>

                    <!-- Stats widgets from about_highlights -->
                    <aside v-if="highlights.length" class="cd-span-4 grid content-start gap-4 sm:grid-cols-2 lg:grid-cols-1">
                        <article
                            v-for="(h, i) in highlights.slice(0, 3)"
                            :key="i"
                            data-reveal
                            :style="{ '--d': `${(i + 1) * 90}ms` }"
                            class="cd-win cd-stat"
                        >
                            <div class="cd-stat-head">
                                <span class="cd-mono text-[0.7rem] uppercase tracking-[0.12em] text-[var(--muted)]">{{ String(i + 1).padStart(2, '0') }}</span>
                                <Activity class="h-4 w-4 text-[var(--accent)]" aria-hidden="true" />
                            </div>
                            <p class="cd-display cd-stat-value">{{ h.title }}</p>
                            <p v-if="h.description" class="cd-muted mt-2 text-[0.9rem] leading-snug">{{ h.description }}</p>
                            <span class="cd-spark" aria-hidden="true"><i /></span>
                        </article>
                    </aside>
                </section>

                <!-- ============================== ABOUT ============================== -->
                <section v-if="show('about')" id="about" class="cd-grid">
                    <article data-reveal class="cd-win cd-span-12">
                        <div class="cd-win-bar">
                            <span class="cd-lights" aria-hidden="true"><i /><i /><i /></span>
                            <span v-if="text('about', 'about_subtitle')" class="cd-mono cd-win-title">{{ text('about', 'about_subtitle') }}</span>
                        </div>
                        <div class="grid gap-10 p-6 sm:p-9" :class="media('about', 'about_image') ? 'lg:grid-cols-12' : ''">
                            <div v-if="media('about', 'about_image')" class="lg:col-span-4">
                                <div class="cd-shot">
                                    <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="aspect-[4/5] w-full object-cover" loading="lazy" />
                                </div>
                            </div>
                            <div :class="media('about', 'about_image') ? 'lg:col-span-8' : ''">
                                <h2 v-if="text('about', 'about_title')" class="cd-display cd-h2">{{ text('about', 'about_title') }}</h2>
                                <p v-if="text('about', 'about_description')" class="cd-muted mt-6 max-w-[40rem] whitespace-pre-line text-[1.05rem] leading-[1.75]">
                                    {{ text('about', 'about_description') }}
                                </p>

                                <ul v-if="highlights.length" class="mt-8 grid gap-3 sm:grid-cols-2">
                                    <li v-for="(h, i) in highlights" :key="i" :style="{ '--k': i }" class="cd-hl">
                                        <span class="cd-tick"><Check class="h-3 w-3" stroke-width="3" /></span>
                                        <span>
                                            <span class="block font-semibold leading-snug">{{ h.title }}</span>
                                            <span v-if="h.description" class="cd-muted mt-1 block text-[0.88rem] leading-relaxed">{{ h.description }}</span>
                                        </span>
                                    </li>
                                </ul>

                                <div v-if="cvFile || aboutCta" class="mt-8 flex flex-wrap gap-3">
                                    <a v-if="cvFile" :href="cvFile" download target="_blank" rel="noopener" class="cd-btn cd-btn-primary">
                                        <Download class="h-4 w-4" />Download CV</a>
                                    <a v-if="aboutCta && cvFile" :href="aboutCta.url" class="cd-btn cd-btn-ghost" @click="onLink($event, aboutCta.url)">
                                        {{ aboutCta.text }}<ArrowUpRight class="cd-arrow h-4 w-4" />
                                    </a>
                                    <a v-else-if="aboutCta" :href="aboutCta.url" class="cd-btn cd-btn-primary" @click="onLink($event, aboutCta.url)">
                                        {{ aboutCta.text }}<ArrowUpRight class="cd-arrow h-4 w-4" />
                                    </a>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- ============================== JOURNEY + SKILLS ============================== -->
                <section v-if="show('journey') || show('skills')" class="cd-grid">
                    <article
                        v-if="show('journey')"
                        id="journey"
                        data-reveal
                        class="cd-win"
                        :class="show('skills') && view === 'overview' ? 'cd-span-7' : 'cd-span-12'"
                    >
                        <div class="cd-win-bar">
                            <span class="cd-lights" aria-hidden="true"><i /><i /><i /></span>
                            <span v-if="text('journey', 'journey_subtitle')" class="cd-mono cd-win-title">{{ text('journey', 'journey_subtitle') }}</span>
                            <span class="cd-count-pill cd-mono">{{ String(journey.length).padStart(2, '0') }}</span>
                        </div>
                        <div class="p-6 sm:p-9">
                            <h2 v-if="text('journey', 'journey_title')" class="cd-display cd-h3">{{ text('journey', 'journey_title') }}</h2>
                            <ol class="cd-timeline mt-8">
                                <li v-for="(item, index) in journey" :key="index" data-reveal :style="{ '--d': `${(index % 4) * 70}ms` }" class="cd-tl-item group">
                                    <div class="cd-card">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p v-if="item.company" class="cd-mono text-[0.75rem] font-medium uppercase tracking-[0.1em] text-[var(--accent)]">{{ item.company }}</p>
                                            <span v-if="item.period" class="cd-pill">{{ item.period }}</span>
                                        </div>
                                        <h3 v-if="item.title" class="cd-display mt-2 text-xl leading-tight">{{ item.title }}</h3>
                                        <p v-if="item.description" class="cd-muted mt-2 whitespace-pre-line text-[0.95rem] leading-[1.65]">{{ item.description }}</p>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </article>

                    <article
                        v-if="show('skills')"
                        id="skills"
                        data-reveal
                        class="cd-win"
                        :class="show('journey') && view === 'overview' ? 'cd-span-5' : 'cd-span-12'"
                        style="--d: 90ms"
                    >
                        <div class="cd-win-bar">
                            <span class="cd-lights" aria-hidden="true"><i /><i /><i /></span>
                            <span v-if="text('skills', 'skills_subtitle')" class="cd-mono cd-win-title">{{ text('skills', 'skills_subtitle') }}</span>
                            <span class="cd-count-pill cd-mono">{{ String(skills.length).padStart(2, '0') }}</span>
                        </div>
                        <div class="p-6 sm:p-9">
                            <h2 v-if="text('skills', 'skills_title')" class="cd-display cd-h3">{{ text('skills', 'skills_title') }}</h2>
                            <p v-if="text('skills', 'skills_description')" class="cd-muted mt-4 whitespace-pre-line leading-[1.7]">{{ text('skills', 'skills_description') }}</p>

                            <ul class="mt-7 grid gap-3" :class="view === 'skills' ? 'sm:grid-cols-2 lg:grid-cols-3' : ''">
                                <li v-for="(s, i) in skills" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 50}ms` }" class="cd-skill">
                                    <div class="flex items-center gap-3">
                                        <span v-if="showIcon" class="cd-tile">
                                            <component :is="iconFor(s.icon)" v-if="iconFor(s.icon)" class="h-4 w-4" />
                                            <span v-else-if="isGlyph(s.icon)" class="text-base leading-none" aria-hidden="true">{{ s.icon }}</span>
                                            <span v-else class="cd-display text-sm leading-none" aria-hidden="true">{{ s.name.charAt(0).toUpperCase() }}</span>
                                        </span>
                                        <h3 class="text-[0.95rem] font-semibold">{{ s.name }}</h3>
                                        <span
                                            v-if="showLevel && s.pct !== null"
                                            class="cd-count cd-mono ml-auto text-[0.78rem] font-medium text-[var(--muted)]"
                                            :style="{ '--p': Math.round(s.pct) }"
                                            aria-hidden="true"
                                        />
                                        <span v-else-if="showLevel && s.label" class="cd-pill ml-auto">{{ s.label }}</span>
                                    </div>
                                    <div
                                        v-if="showLevel && s.pct !== null"
                                        class="cd-track mt-3"
                                        role="progressbar"
                                        :aria-valuenow="Math.round(s.pct)"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        :aria-label="s.name"
                                    >
                                        <div class="cd-bar" :style="{ '--w': `${s.pct}%` }" />
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </article>
                </section>

                <!-- ============================== PROJECTS ============================== -->
                <section v-if="show('projects')" id="projects" class="cd-grid">
                    <article data-reveal class="cd-win cd-span-12">
                        <div class="cd-win-bar">
                            <span class="cd-lights" aria-hidden="true"><i /><i /><i /></span>
                            <span v-if="text('projects', 'projects_subtitle')" class="cd-mono cd-win-title">{{ text('projects', 'projects_subtitle') }}</span>
                            <span class="cd-count-pill cd-mono">{{ String(filteredProjects.length).padStart(2, '0') }}/{{ String(projects.length).padStart(2, '0') }}</span>
                        </div>
                        <div class="p-6 sm:p-9">
                            <div class="grid gap-5 lg:grid-cols-12 lg:items-end">
                                <h2 v-if="text('projects', 'projects_title')" class="cd-display cd-h3 lg:col-span-7">{{ text('projects', 'projects_title') }}</h2>
                                <p v-if="text('projects', 'projects_description')" class="cd-muted whitespace-pre-line leading-[1.7] lg:col-span-5">
                                    {{ text('projects', 'projects_description') }}
                                </p>
                            </div>

                            <div v-if="projectFilters.length > 1" class="cd-filters" role="tablist" aria-label="Filter">
                                <button type="button" role="tab" class="cd-filter" :aria-selected="projectFilter === ''" @click="projectFilter = ''">
                                    <Layers class="h-3.5 w-3.5" />{{ projects.length }}
                                </button>
                                <button
                                    v-for="t in projectFilters"
                                    :key="t"
                                    type="button"
                                    role="tab"
                                    class="cd-filter"
                                    :aria-selected="projectFilter === t"
                                    @click="projectFilter = projectFilter === t ? '' : t"
                                >
                                    {{ t }}
                                </button>
                            </div>

                            <ul class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                <li
                                    v-for="(p, i) in filteredProjects"
                                    :key="p.title + i"
                                    data-reveal
                                    :class="featureFirst && i === 0 && !projectFilter ? 'md:col-span-2 xl:col-span-2' : ''"
                                    :style="{ '--d': `${(i % 3) * 80}ms` }"
                                >
                                    <article class="cd-project group flex h-full flex-col">
                                        <component
                                            :is="p.live ? 'a' : 'div'"
                                            :href="p.live || undefined"
                                            :target="p.live ? '_blank' : undefined"
                                            :rel="p.live ? 'noopener noreferrer' : undefined"
                                            :aria-label="p.live ? p.title : undefined"
                                            class="cd-media relative block overflow-hidden"
                                            :class="featureFirst && i === 0 && !projectFilter ? 'aspect-[16/8]' : 'aspect-[16/10]'"
                                        >
                                            <img v-if="p.image" :src="p.image" :alt="p.title" class="h-full w-full object-cover" loading="lazy" />
                                            <div v-else class="cd-display cd-tone flex h-full w-full items-center justify-center text-7xl" :class="`cd-tone-${i % 3}`">
                                                {{ p.title.charAt(0).toUpperCase() }}
                                            </div>
                                            <span v-if="featureFirst && i === 0 && !projectFilter" class="cd-flag cd-mono"><Sparkles class="h-3 w-3" />{{ String(i + 1).padStart(2, '0') }}</span>
                                            <span v-if="p.live" class="cd-go" aria-hidden="true"><ArrowUpRight class="h-4 w-4" /></span>
                                        </component>

                                        <div class="flex flex-1 flex-col p-5">
                                            <h3 class="cd-display text-xl leading-tight"><span class="cd-underline">{{ p.title }}</span></h3>
                                            <p v-if="p.description" class="cd-muted mt-2 text-[0.95rem] leading-[1.6]">{{ p.description }}</p>
                                            <ul v-if="p.tags.length" class="mt-4 flex flex-wrap gap-2">
                                                <li v-for="(t, ti) in p.tags" :key="ti">
                                                    <button type="button" class="cd-pill cd-pill-btn" @click="projectFilter = t">{{ t }}</button>
                                                </li>
                                            </ul>
                                            <div v-if="p.repo" class="mt-auto pt-4">
                                                <a :href="p.repo" target="_blank" rel="noopener noreferrer" class="cd-textlink cd-mono text-[0.78rem]"
                                                    ><Code class="h-4 w-4" />{{ p.repo.replace(/^https?:\/\/(www\.)?/, '').split('/').slice(0, 2).join('/') }}</a
                                                >
                                            </div>
                                        </div>
                                    </article>
                                </li>
                            </ul>
                        </div>
                    </article>
                </section>

                <!-- ============================== SERVICES ============================== -->
                <section v-if="show('services')" id="services" class="cd-grid">
                    <article data-reveal class="cd-win cd-span-12">
                        <div class="cd-win-bar">
                            <span class="cd-lights" aria-hidden="true"><i /><i /><i /></span>
                            <span v-if="text('services', 'services_subtitle')" class="cd-mono cd-win-title">{{ text('services', 'services_subtitle') }}</span>
                            <span class="cd-count-pill cd-mono">{{ String(services.length).padStart(2, '0') }}</span>
                        </div>
                        <div class="p-6 sm:p-9">
                            <h2 v-if="text('services', 'services_title')" class="cd-display cd-h3">{{ text('services', 'services_title') }}</h2>
                            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <li v-for="(s, i) in services" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 70}ms` }">
                                    <div class="cd-service group flex h-full flex-col">
                                        <span class="cd-tile cd-tile-lg">
                                            <component :is="iconFor(s.icon) ?? Sparkles" v-if="!isGlyph(s.icon)" class="h-5 w-5" />
                                            <span v-else class="text-xl leading-none" aria-hidden="true">{{ s.icon }}</span>
                                        </span>
                                        <h3 class="cd-display cd-service-title mt-5 text-xl leading-tight">{{ s.title }}</h3>
                                        <p v-if="s.description" class="cd-muted mt-2 text-[0.95rem] leading-[1.6]">{{ s.description }}</p>
                                        <ArrowUpRight class="cd-service-arrow mt-auto h-5 w-5 self-end" aria-hidden="true" />
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </article>
                </section>

                <!-- ============================== CONTACT ============================== -->
                <section v-if="show('contact')" id="contact" class="cd-grid">
                    <article data-reveal class="cd-win cd-span-12">
                        <div class="cd-win-bar">
                            <span class="cd-lights" aria-hidden="true"><i /><i /><i /></span>
                            <span v-if="text('contact', 'contact_subtitle')" class="cd-mono cd-win-title">{{ text('contact', 'contact_subtitle') }}</span>
                        </div>
                        <div class="grid gap-12 p-6 sm:p-9 lg:grid-cols-12">
                            <div class="lg:col-span-7">
                                <h2 v-if="text('contact', 'contact_title')" class="cd-display cd-contact-title [overflow-wrap:anywhere]">{{ text('contact', 'contact_title') }}</h2>
                                <p v-if="text('contact', 'contact_description')" class="cd-muted mt-5 max-w-[34rem] whitespace-pre-line text-[1.05rem] leading-[1.7]">
                                    {{ text('contact', 'contact_description') }}
                                </p>

                                <div v-if="email || phone || location" class="mt-8 space-y-4">
                                    <a v-if="email" :href="`mailto:${email}`" class="cd-bigmail cd-display group inline-flex max-w-full items-center gap-3 break-all text-xl sm:text-3xl">
                                        <Mail class="h-6 w-6 shrink-0 text-[var(--accent)]" /><span class="cd-underline-contact">{{ email }}</span>
                                        <ArrowUpRight class="cd-arrow h-6 w-6 shrink-0" />
                                    </a>
                                    <div class="cd-mono flex flex-wrap gap-x-8 gap-y-3 text-[0.85rem]">
                                        <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="cd-contact-link"><Phone class="h-4 w-4 text-[var(--accent)]" />{{ phone }}</a>
                                        <span v-if="location" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4 text-[var(--accent)]" />{{ location }}</span>
                                    </div>
                                </div>

                                <ul v-if="contactSocials.length" class="mt-7 flex flex-wrap gap-2">
                                    <li v-for="(s, i) in contactSocials" :key="i">
                                        <a :href="s.url" target="_blank" rel="noopener noreferrer" class="cd-chip-link">
                                            <component :is="socialIconFor(s.platform)" class="h-3.5 w-3.5" />{{ s.label }}<ArrowUpRight class="cd-arrow h-3.5 w-3.5" />
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <div class="lg:col-span-5">
                                <form
                                    v-if="formReady"
                                    :action="formAction"
                                    method="POST"
                                    target="cd-gform-frame"
                                    class="cd-form space-y-5 p-6"
                                    @submit="onSubmit"
                                >
                                    <label class="block">
                                        <span class="cd-mono mb-2 block text-[0.7rem] font-medium uppercase tracking-[0.12em] text-[var(--muted)]">Name</span>
                                        <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="cd-input" />
                                    </label>
                                    <label class="block">
                                        <span class="cd-mono mb-2 block text-[0.7rem] font-medium uppercase tracking-[0.12em] text-[var(--muted)]">Email</span>
                                        <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="cd-input" />
                                    </label>
                                    <label class="block">
                                        <span class="cd-mono mb-2 block text-[0.7rem] font-medium uppercase tracking-[0.12em] text-[var(--muted)]">Message</span>
                                        <textarea :name="`entry.${gf.message}`" rows="5" required class="cd-input resize-y" />
                                    </label>
                                    <button type="submit" class="cd-btn cd-btn-primary w-full justify-center" :disabled="sending">
                                        {{ sending ? '…' : 'Send' }}<Send class="cd-arrow h-4 w-4" />
                                    </button>
                                    <p v-if="sent" role="status" class="cd-sent flex items-center gap-2 text-sm font-semibold">
                                        <span class="cd-tick"><Check class="h-3 w-3" stroke-width="3" /></span>OK
                                    </p>
                                </form>
                                <a v-else-if="email" :href="`mailto:${email}`" class="cd-btn cd-btn-ghost w-full justify-center !py-5 text-base"><Mail class="h-5 w-5" />{{ email }}</a>
                                <iframe name="cd-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                            </div>
                        </div>
                    </article>
                </section>

                <footer v-if="heroName || logoText" class="cd-foot">
                    <span class="cd-mono text-[0.75rem] text-[var(--muted)]">&copy; {{ year }} {{ heroName ?? logoText }}</span>
                    <button type="button" class="cd-textlink" aria-label="Top" @click="setView('overview')"><ArrowUpRight class="cd-arrow h-4 w-4 -rotate-45" /></button>
                </footer>
            </main>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

/* ---------- Foundations ---------- */
.cd-root {
    --rail: 4.25rem;
    min-height: 100vh;
    background: var(--paper);
    color: var(--ink);
    font-family: 'Manrope', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    background-image: radial-gradient(color-mix(in srgb, var(--ink) 9%, transparent) 1px, transparent 1px);
    background-size: 22px 22px;
    transition:
        background-color 0.4s ease,
        color 0.4s ease;
}
.cd-display {
    font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif;
    font-weight: 700;
    letter-spacing: -0.03em;
}
.cd-mono {
    font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
}
.cd-muted {
    color: var(--muted);
}
.cd-h2 {
    font-size: clamp(2rem, 4.4vw, 3.25rem);
    line-height: 1.05;
    letter-spacing: -0.04em;
    text-wrap: balance;
}
.cd-h3 {
    font-size: clamp(1.6rem, 3vw, 2.35rem);
    line-height: 1.08;
    letter-spacing: -0.035em;
    text-wrap: balance;
}
.cd-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
    border-radius: 0.5rem;
}

/* ---------- Left rail ---------- */
.cd-rail {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 55;
    display: flex;
    width: var(--rail);
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
    border-right: 1px solid var(--line);
    background: color-mix(in srgb, var(--surface) 88%, transparent);
    -webkit-backdrop-filter: blur(14px);
    backdrop-filter: blur(14px);
    padding: 1rem 0;
}
.cd-rail.is-static {
    position: absolute;
    min-height: 100%;
}
.cd-logo {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 0.8rem;
    background: var(--accent);
    color: var(--accent-ink);
    transition: transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-logo:hover {
    transform: rotate(-6deg) scale(1.06);
}
.cd-rail-nav {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--line);
    width: 100%;
    padding-inline: 0.6rem;
}
.cd-rail-foot {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
}
.cd-rail-btn {
    position: relative;
    display: inline-flex;
    height: 2.6rem;
    width: 2.6rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    color: var(--muted);
    cursor: pointer;
    transition:
        background-color 0.25s ease,
        color 0.25s ease,
        transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-rail-btn:hover {
    background: var(--soft);
    color: var(--ink);
    transform: translateX(2px);
}
.cd-rail-btn[data-active='true'] {
    background: color-mix(in srgb, var(--accent) 16%, transparent);
    color: var(--accent);
}
.cd-rail-btn[data-active='true']::before {
    content: '';
    position: absolute;
    left: -0.6rem;
    top: 22%;
    bottom: 22%;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: var(--accent);
}

/* ---------- Shell / top bar ---------- */
.cd-shell {
    position: relative;
    margin-left: var(--rail);
    min-width: 0;
}
.cd-top {
    z-index: 50;
    border-bottom: 1px solid var(--line);
    background: color-mix(in srgb, var(--paper) 78%, transparent);
    -webkit-backdrop-filter: blur(14px) saturate(1.3);
    backdrop-filter: blur(14px) saturate(1.3);
}
.cd-top.is-sticky {
    position: sticky;
    top: 0;
}
.cd-top-inner {
    display: flex;
    height: 3.5rem;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-inline: 1.25rem;
    transition: box-shadow 0.3s ease;
}
.cd-top-inner.is-scrolled {
    box-shadow: 0 12px 30px -20px color-mix(in srgb, var(--ink) 55%, transparent);
}
.cd-progress {
    position: absolute;
    inset: auto 0 -1px 0;
    height: 2px;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
}
.cd-dot {
    height: 0.55rem;
    width: 0.55rem;
    flex-shrink: 0;
    border-radius: 999px;
    background: var(--accent-2);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-2) 25%, transparent);
}
.cd-tabs {
    display: none;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid var(--line);
    border-radius: 0.95rem;
    background: var(--surface);
    padding: 0.25rem;
}
.cd-tabs-mobile {
    display: flex;
    gap: 0.25rem;
    overflow-x: auto;
    border-top: 1px solid var(--line);
    padding: 0.5rem 1rem;
    scrollbar-width: none;
}
.cd-tabs-mobile::-webkit-scrollbar {
    display: none;
}
@media (min-width: 1024px) {
    .cd-tabs {
        display: inline-flex;
    }
    .cd-tabs-mobile {
        display: none;
    }
}
.cd-tab {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.45rem;
    border-radius: 0.7rem;
    padding: 0.45rem 0.85rem;
    font-size: 0.84rem;
    font-weight: 600;
    color: var(--muted);
    cursor: pointer;
    transition:
        color 0.25s ease,
        background-color 0.25s ease,
        box-shadow 0.25s ease;
}
.cd-tab:hover {
    color: var(--ink);
}
.cd-tab[aria-selected='true'] {
    background: var(--accent);
    color: var(--accent-ink);
    box-shadow: 0 6px 16px -8px var(--accent);
}

/* ---------- Workspace ---------- */
.cd-main {
    display: grid;
    gap: 1.25rem;
    padding: 1.25rem;
    max-width: 1240px;
    margin-inline: auto;
}
@media (min-width: 640px) {
    .cd-main {
        padding: 1.75rem;
        gap: 1.75rem;
    }
}
.cd-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 1.25rem;
    scroll-margin-top: 5rem;
}
.cd-grid > * {
    grid-column: span 12 / span 12;
    min-width: 0;
}
@media (min-width: 1024px) {
    .cd-span-4 {
        grid-column: span 4 / span 4 !important;
    }
    .cd-span-5 {
        grid-column: span 5 / span 5 !important;
    }
    .cd-span-7 {
        grid-column: span 7 / span 7 !important;
    }
    .cd-span-8 {
        grid-column: span 8 / span 8 !important;
    }
}

/* Window frame widget */
.cd-win {
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 1.25rem;
    background: var(--surface);
    box-shadow: 0 18px 40px -30px color-mix(in srgb, var(--ink) 60%, transparent);
    transition:
        border-color 0.3s ease,
        box-shadow 0.4s ease;
}
.cd-win:hover {
    border-color: color-mix(in srgb, var(--accent) 40%, var(--line));
}
.cd-win-bar {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    border-bottom: 1px solid var(--line);
    background: var(--soft);
    padding: 0.65rem 1rem;
}
.cd-lights {
    display: inline-flex;
    gap: 0.35rem;
}
.cd-lights i {
    height: 0.6rem;
    width: 0.6rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ink) 22%, transparent);
    transition: background-color 0.3s ease;
}
.cd-win:hover .cd-lights i:nth-child(1) {
    background: #ff5f57;
}
.cd-win:hover .cd-lights i:nth-child(2) {
    background: #febc2e;
}
.cd-win:hover .cd-lights i:nth-child(3) {
    background: #28c840;
}
.cd-win-title {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.74rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--muted);
}
.cd-win-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border-top: 1px solid var(--line);
    background: var(--soft);
    padding: 0.8rem 1rem;
}
.cd-count-pill {
    margin-left: auto;
    border-radius: 999px;
    background: color-mix(in srgb, var(--accent) 14%, transparent);
    padding: 0.1rem 0.6rem;
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--accent);
}
.cd-live {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.15rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.cd-pulse {
    position: relative;
    height: 0.45rem;
    width: 0.45rem;
    border-radius: 999px;
    background: var(--accent-2);
}
.cd-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: inherit;
    animation: cd-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

/* ---------- Buttons & small controls ---------- */
.cd-btn {
    position: relative;
    isolation: isolate;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.8rem;
    padding: 0.8rem 1.35rem;
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.2;
    cursor: pointer;
    border: 1px solid transparent;
    transition:
        transform 0.25s ease,
        box-shadow 0.3s ease,
        background-color 0.3s ease,
        border-color 0.3s ease;
}
.cd-btn:hover {
    transform: translateY(-2px);
}
.cd-btn:active {
    transform: translateY(0) scale(0.98);
}
.cd-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.cd-btn-primary {
    background: var(--accent);
    color: var(--accent-ink);
    box-shadow: 0 10px 22px -12px var(--accent);
}
.cd-btn-primary:hover {
    box-shadow: 0 16px 28px -12px var(--accent);
}
.cd-btn-ghost {
    background: var(--surface);
    border-color: var(--line);
    color: var(--ink);
}
.cd-btn-ghost:hover {
    border-color: var(--accent);
}
.cd-arrow {
    transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-btn:hover .cd-arrow,
.cd-bigmail:hover .cd-arrow,
.cd-chip-link:hover .cd-arrow,
.cd-textlink:hover .cd-arrow {
    transform: translate(3px, -3px);
}
.cd-chip-link {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 0.65rem;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.4rem 0.8rem;
    font-size: 0.82rem;
    font-weight: 600;
    transition:
        background-color 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease,
        transform 0.3s ease;
}
.cd-chip-link:hover {
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
    transform: translateY(-2px);
}
.cd-pill {
    display: inline-block;
    border-radius: 0.5rem;
    border: 1px solid var(--line);
    background: var(--soft);
    padding: 0.15rem 0.6rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--muted);
}
.cd-pill-btn {
    cursor: pointer;
    transition:
        background-color 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease;
}
.cd-pill-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
}
.cd-tick {
    display: inline-flex;
    height: 1.3rem;
    width: 1.3rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.4rem;
    background: var(--accent);
    color: var(--accent-ink);
}
.cd-textlink {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    cursor: pointer;
    background: linear-gradient(currentColor, currentColor) left bottom / 0% 1.5px no-repeat;
    transition: background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 2px;
}
.cd-textlink:hover {
    background-size: 100% 1.5px;
}

/* ---------- Hero ---------- */
.cd-hero {
    position: relative;
    overflow: hidden;
    padding: 2rem 1.5rem;
}
@media (min-width: 640px) {
    .cd-hero {
        padding: 2.75rem 2.5rem;
    }
}
.cd-hero-bg {
    position: absolute;
    inset: 0;
    height: 100%;
    width: 100%;
    object-fit: cover;
    opacity: 0.12;
}
.cd-hero-name {
    font-size: clamp(2.5rem, 6.4vw, 5rem);
    line-height: 0.98;
    letter-spacing: -0.05em;
    overflow-wrap: anywhere;
}
.cd-name-line {
    display: inline-block;
    background: linear-gradient(100deg, var(--ink) 30%, var(--accent) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}
.cd-avatar {
    height: 8.5rem;
    width: 8.5rem;
    overflow: hidden;
    border-radius: 1.75rem;
    background: var(--accent);
    border: 1px solid var(--line);
    box-shadow:
        0 0 0 6px color-mix(in srgb, var(--accent) 14%, transparent),
        0 24px 40px -22px color-mix(in srgb, var(--ink) 60%, transparent);
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
@media (min-width: 640px) {
    .cd-avatar {
        height: 11rem;
        width: 11rem;
    }
}
.cd-avatar:hover {
    transform: rotate(-3deg) scale(1.03);
}

/* Stat widgets */
.cd-stat {
    position: relative;
    padding: 1.2rem 1.3rem 1.35rem;
    transition:
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease;
}
.cd-stat:hover {
    transform: translateY(-3px);
}
.cd-stat-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.cd-stat-value {
    margin-top: 0.7rem;
    font-size: clamp(1.4rem, 2.6vw, 2rem);
    line-height: 1.1;
    letter-spacing: -0.04em;
    overflow-wrap: anywhere;
}
.cd-spark {
    display: block;
    margin-top: 1rem;
    height: 4px;
    overflow: hidden;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ink) 9%, transparent);
}
.cd-spark i {
    display: block;
    height: 100%;
    width: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    transform-origin: left;
}
.cd-js [data-reveal]:not(.is-in) .cd-spark i {
    transform: scaleX(0);
}
.cd-spark i {
    transition: transform 1.4s cubic-bezier(0.2, 0.7, 0.2, 1) 0.4s;
}

/* ---------- About ---------- */
.cd-shot {
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 1.1rem;
    background: var(--soft);
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-shot:hover {
    transform: rotate(-1deg) scale(1.01);
}
.cd-hl {
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    border: 1px solid var(--line);
    border-radius: 0.95rem;
    background: var(--paper);
    padding: 0.95rem 1rem;
    transition:
        border-color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-hl:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

/* ---------- Journey ---------- */
.cd-timeline {
    position: relative;
    margin-left: 0.4rem;
    border-left: 1.5px solid var(--line);
    padding-left: 1.75rem;
}
.cd-tl-item {
    position: relative;
    padding-bottom: 1rem;
}
.cd-tl-item:last-child {
    padding-bottom: 0;
}
.cd-tl-item::before {
    content: '';
    position: absolute;
    left: -1.75rem;
    top: 1.35rem;
    height: 0.75rem;
    width: 0.75rem;
    transform: translateX(-50%);
    border-radius: 999px;
    background: var(--surface);
    border: 2px solid var(--accent);
    transition:
        background-color 0.5s ease 0.3s,
        box-shadow 0.4s ease;
}
.cd-js .cd-tl-item.is-in::before {
    background: var(--accent);
}
.cd-tl-item:hover::before {
    box-shadow: 0 0 0 6px color-mix(in srgb, var(--accent) 20%, transparent);
}
.cd-card {
    border: 1px solid var(--line);
    border-radius: 1rem;
    background: var(--paper);
    padding: 1.1rem 1.25rem 1.2rem;
    transition:
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease,
        box-shadow 0.4s ease;
}
.cd-card:hover {
    transform: translateX(4px);
    border-color: color-mix(in srgb, var(--accent) 55%, var(--line));
    box-shadow: 0 16px 30px -22px var(--accent);
}

/* ---------- Skills ---------- */
.cd-skill {
    border: 1px solid var(--line);
    border-radius: 0.95rem;
    background: var(--paper);
    padding: 0.9rem 1rem 1rem;
    transition:
        border-color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-skill:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}
.cd-tile {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.65rem;
    background: color-mix(in srgb, var(--accent) 14%, transparent);
    color: var(--accent);
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-tile-lg {
    height: 2.9rem;
    width: 2.9rem;
    border-radius: 0.9rem;
}
.cd-skill:hover .cd-tile,
.cd-service:hover .cd-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.cd-track {
    position: relative;
    height: 6px;
    width: 100%;
    overflow: hidden;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ink) 9%, transparent);
}
.cd-bar {
    position: relative;
    height: 100%;
    width: var(--w, 0%);
    border-radius: inherit;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
}
.cd-js [data-reveal] .cd-bar {
    width: 0;
    transition: width 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s);
}
.cd-js [data-reveal].is-in .cd-bar {
    width: var(--w, 0%);
}

/* Percentage counts up in sync with the bar */
@property --n {
    syntax: '<integer>';
    inherits: false;
    initial-value: 0;
}
.cd-count {
    --n: var(--p, 0);
    counter-reset: n var(--n);
    font-variant-numeric: tabular-nums;
}
.cd-count::after {
    content: counter(n) '%';
}
.cd-js [data-reveal] .cd-count {
    --n: 0;
}
.cd-js [data-reveal].is-in .cd-count {
    animation: cd-count 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s) forwards;
}

/* ---------- Projects ---------- */
.cd-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 1.75rem;
    border-bottom: 1px solid var(--line);
    padding-bottom: 1rem;
}
.cd-filter {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.65rem;
    border: 1px solid var(--line);
    background: var(--paper);
    padding: 0.38rem 0.8rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.74rem;
    font-weight: 500;
    color: var(--muted);
    cursor: pointer;
    transition:
        background-color 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease;
}
.cd-filter:hover {
    color: var(--ink);
    border-color: var(--accent);
}
.cd-filter[aria-selected='true'] {
    background: var(--accent);
    border-color: var(--accent);
    color: var(--accent-ink);
}
.cd-project {
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 1.1rem;
    background: var(--paper);
    transition:
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease,
        box-shadow 0.45s ease;
}
.cd-project:hover {
    transform: translateY(-5px);
    border-color: color-mix(in srgb, var(--accent) 60%, var(--line));
    box-shadow: 0 26px 44px -26px color-mix(in srgb, var(--accent) 55%, transparent);
}
.cd-media {
    background: var(--soft);
    border-bottom: 1px solid var(--line);
    isolation: isolate;
}
.cd-media img,
.cd-tone {
    transition: transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-project:hover .cd-media img,
.cd-project:hover .cd-tone {
    transform: scale(1.06);
}
.cd-tone-0 {
    background: var(--accent);
    color: var(--accent-ink);
}
.cd-tone-1 {
    background: var(--accent-2);
    color: var(--accent-ink);
}
.cd-tone-2 {
    background: var(--ink);
    color: var(--paper);
}
.cd-flag {
    position: absolute;
    left: 0.8rem;
    top: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 0.55rem;
    background: var(--accent-2);
    color: var(--accent-ink);
    padding: 0.2rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 500;
}
.cd-go {
    position: absolute;
    right: 0.8rem;
    top: 0.8rem;
    display: inline-flex;
    height: 2.4rem;
    width: 2.4rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    background: var(--paper);
    color: var(--ink);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem) scale(0.9);
    transition:
        opacity 0.35s ease,
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-project:hover .cd-go,
.cd-media:focus-visible .cd-go {
    opacity: 1;
    transform: none;
}
.cd-underline {
    background: linear-gradient(currentColor, currentColor) left 96% / 0% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-project:hover .cd-underline {
    background-size: 100% 2px;
}

/* ---------- Services ---------- */
.cd-service {
    border: 1px solid var(--line);
    border-radius: 1.1rem;
    background: var(--paper);
    padding: 1.4rem;
    transition:
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease,
        box-shadow 0.4s ease;
}
.cd-service:hover {
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--accent) 60%, var(--line));
    box-shadow: 0 22px 38px -26px var(--accent);
}
.cd-service-title {
    transition: color 0.3s ease;
}
.cd-service:hover .cd-service-title {
    color: var(--accent);
}
.cd-service-arrow {
    color: var(--accent);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem);
    transition:
        opacity 0.3s ease,
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.cd-service:hover .cd-service-arrow {
    opacity: 1;
    transform: none;
}

/* ---------- Contact ---------- */
.cd-contact-title {
    font-size: clamp(2.25rem, 5.4vw, 4.25rem);
    line-height: 1;
    letter-spacing: -0.045em;
}
.cd-underline-contact {
    background: linear-gradient(currentColor, currentColor) left bottom / 100% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 4px;
}
.cd-bigmail:hover .cd-underline-contact {
    background-size: 0% 2px;
    background-position: right bottom;
}
.cd-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.cd-contact-link:hover {
    text-decoration: underline;
    text-underline-offset: 4px;
}
.cd-form {
    border: 1px solid var(--line);
    border-radius: 1.1rem;
    background: var(--paper);
}
.cd-input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.75rem 0.95rem;
    font: inherit;
    font-weight: 500;
    color: var(--ink);
    transition:
        border-color 0.25s ease,
        box-shadow 0.25s ease;
}
.cd-input:hover {
    border-color: color-mix(in srgb, var(--ink) 35%, transparent);
}
.cd-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 22%, transparent);
}
.cd-sent {
    animation: cd-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
.cd-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.5rem 0.25rem 1rem;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.cd-js [data-reveal] {
    opacity: 0;
    transform: translateY(20px);
    transition:
        opacity 0.75s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.75s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.cd-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}

/* ---------- Section animations ---------- */
.cd-js [data-reveal].is-in .cd-hl {
    animation: cd-slide-x 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--k, 0) * 80ms + 150ms) backwards;
}
.cd-js [data-reveal].is-in .cd-hl .cd-tick {
    animation: cd-tick 0.6s cubic-bezier(0.3, 1.6, 0.4, 1) calc(var(--k, 0) * 80ms + 400ms) backwards;
}
.cd-js [data-reveal].is-in .cd-tile {
    animation: cd-tile 0.7s cubic-bezier(0.3, 1.5, 0.4, 1) calc(var(--d, 0ms) + 0.15s) backwards;
}
.cd-js [data-reveal].is-in .cd-media {
    animation: cd-wipe 1.1s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}
.cd-js [data-reveal].is-in .cd-media img {
    animation: cd-settle 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s backwards;
}
.cd-js [data-reveal].is-in .cd-avatar {
    animation: cd-pop 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) 0.2s backwards;
}

@keyframes cd-ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}
@keyframes cd-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}
@keyframes cd-count {
    to {
        --n: var(--p);
    }
}
@keyframes cd-slide-x {
    from {
        opacity: 0;
        transform: translateX(-16px);
    }
}
@keyframes cd-tick {
    from {
        transform: scale(0) rotate(-90deg);
    }
}
@keyframes cd-tile {
    from {
        transform: scale(0.4) rotate(-20deg);
        opacity: 0;
    }
}
@keyframes cd-wipe {
    from {
        clip-path: inset(0 0 100% 0);
    }
}
@keyframes cd-settle {
    from {
        transform: scale(1.2);
    }
}
@keyframes cd-enter {
    from {
        opacity: 0;
    }
}
.cd-js.cd-root {
    animation: cd-enter 0.6s ease both;
}

/* ---------- Reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    .cd-root *,
    .cd-root *::before,
    .cd-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .cd-js [data-reveal],
    .cd-js [data-reveal]:not(.is-in) .cd-hl {
        opacity: 1;
        transform: none;
    }
    .cd-js [data-reveal] .cd-count {
        --n: var(--p, 0);
    }
    .cd-js [data-reveal] .cd-bar {
        width: var(--w, 0%);
    }
    .cd-js [data-reveal]:not(.is-in) .cd-spark i {
        transform: none;
    }
}
</style>
