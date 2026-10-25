<script setup lang="ts">
/**
 * Widescreen Studio — quickFolio template
 * Slug suggestion: `widescreen-studio`
 *
 * Cinematic studio for video editors / filmmakers: full-screen theater hero with a 16:9 player
 * frame, edge glow lights, sound-wave badges and play overlays; a 2-column cinematic video grid
 * with hover scrub, aspect-ratio badges, duration tags and play triggers; and a post-production
 * suite service menu with interactive before/after comparison previews.
 * Dark cinema palette by default, light variant via Tailwind `dark:` / OS preference.
 *
 * Every visible value comes from the seeded field keys. Only small UI chrome labels are hardcoded.
 * Optional per-item keys understood in project / service entries:
 *   projects_list: { title, description, image, video | video_url | preview_url, live_url, repo_url,
 *                    tags | category, duration | length, ratio | aspect_ratio }
 *   services_list: { title, description, icon, image, before_image, after_image }
 */
import { resolvePortfolioMediaUrl } from '@/lib/media';
import {
    ArrowUpRight,
    AtSign,
    AudioLines,
    Briefcase,
    Camera,
    Check,
    Clapperboard,
    Code,
    Database,
    Dribbble,
    Download,
    Facebook,
    Film,
    Github,
    Globe,
    Instagram,
    Layers,
    Link2,
    Linkedin,
    Mail,
    MapPin,
    Maximize2,
    Megaphone,
    Monitor,
    Palette,
    PenTool,
    Phone,
    Play,
    Rocket,
    Send,
    Server,
    Smartphone,
    Sparkles,
    Terminal,
    Video,
    Volume2,
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

// Projects (video work)
const mediaValue = (v: unknown): string | undefined => {
    const s = typeof v === 'string' ? clean(v) : v && typeof v === 'object' ? clean((v as Dict).url ?? (v as Dict).path ?? (v as Dict).src) : undefined;
    return s ? resolvePortfolioMediaUrl(s) : undefined;
};
const isVideoFile = (u?: string) => !!u && /\.(mp4|webm|mov|m4v|ogv)(\?|#|$)/i.test(u);
const normRatio = (v: unknown): string | undefined => {
    const s = clean(v);
    if (!s) return undefined;
    const m = s.replace(/\s+/g, '').replace('/', ':').match(/^(\d+(?:\.\d+)?):(\d+(?:\.\d+)?)$/);
    return m ? `${m[1]}:${m[2]}` : s;
};
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
            const category = clean(p?.category);
            const live = clean(p?.live_url ?? p?.url);
            const videoSrc = mediaValue(p?.video ?? p?.video_url ?? p?.preview_url ?? p?.preview);
            const preview = isVideoFile(videoSrc) ? videoSrc : isVideoFile(live) ? mediaValue(live) : undefined;
            return {
                title: clean(p?.title ?? p?.name) ?? '',
                description: clean(p?.description),
                image: mediaValue(p?.image ?? p?.thumbnail),
                video: preview,
                repo: clean(p?.repo_url),
                live,
                duration: clean(p?.duration ?? p?.length ?? p?.runtime),
                ratio: normRatio(p?.ratio ?? p?.aspect_ratio ?? p?.aspect),
                tags: category && !tags.includes(category) ? [...tags, category] : tags,
            };
        })
        .filter((p) => p.title),
);
const featureFirst = computed(() => flag('projects', 'projects_show_featured', false));
const ratioClass = (r?: string) => (r === '9:16' ? 'is-916' : r === '4:5' ? 'is-45' : r === '1:1' ? 'is-11' : 'is-169');

// Services (post-production suite)
const services = computed(() =>
    arr('services', 'services_list')
        .map((s) => {
            const before = typeof s === 'object' ? mediaValue(s?.before_image ?? s?.before) : undefined;
            const after = typeof s === 'object' ? mediaValue(s?.after_image ?? s?.after ?? s?.image) : undefined;
            return {
                title: clean(typeof s === 'string' ? s : (s?.title ?? s?.name ?? s?.value)) ?? '',
                description: typeof s === 'object' ? clean(s?.description) : undefined,
                icon: typeof s === 'object' ? clean(s?.icon) : undefined,
                before: before ?? after,
                after: after,
                compare: !!(before && after),
            };
        })
        .filter((s) => s.title),
);
const activeService = ref(0);
const activeSvc = computed(() => services.value[Math.min(activeService.value, Math.max(services.value.length - 1, 0))]);
const compare = ref(52); // before/after split position (%)
const onCompare = (e: Event) => {
    compare.value = Number((e.target as HTMLInputElement).value);
};
watch(activeService, () => {
    compare.value = 52;
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
    color: Palette,
    grading: Palette,
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
    film: Film,
    video: Video,
    clapperboard: Clapperboard,
    editing: Clapperboard,
    sound: AudioLines,
    audio: AudioLines,
    volume: Volume2,
    motion: Sparkles,
    play: Play,
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
/* Video behaviour: hero player, hover scrub, play overlays            */
/* ------------------------------------------------------------------ */

const heroVideoUrl = computed(() => {
    const u = text('header', 'hero_cta_primary_url');
    const s = text('header', 'hero_cta_secondary_url');
    return [u, s].find((x) => isVideoFile(x));
});
const heroPlayUrl = computed(() => {
    const secondary = heroSecondary.value?.url;
    const primary = heroPrimary.value?.url;
    return [secondary, primary].find((x) => x && !x.startsWith('#') && !isVideoFile(x));
});
const heroVideoEl = ref<HTMLVideoElement | null>(null);
const heroPlaying = ref(false);
const heroMuted = ref(true);
const toggleHeroPlay = () => {
    const v = heroVideoEl.value;
    if (!v) return;
    if (v.paused) {
        v.play().catch(() => undefined);
    } else {
        v.pause();
    }
};
const toggleHeroMute = () => {
    heroMuted.value = !heroMuted.value;
    if (heroVideoEl.value) heroVideoEl.value.muted = heroMuted.value;
};
const heroFullscreen = () => {
    const el = (heroVideoEl.value ?? rootEl.value?.querySelector('.ws-screen')) as HTMLElement | null;
    el?.requestFullscreen?.().catch(() => undefined);
};

const scrubPct = ref<Record<number, number>>({});
const onScrub = (e: MouseEvent, i: number) => {
    const el = e.currentTarget as HTMLElement;
    const r = el.getBoundingClientRect();
    const pct = Math.max(0, Math.min(100, ((e.clientX - r.left) / r.width) * 100));
    scrubPct.value = { ...scrubPct.value, [i]: pct };
    const v = el.querySelector('video') as HTMLVideoElement | null;
    if (v && Number.isFinite(v.duration) && v.duration > 0) v.currentTime = (pct / 100) * v.duration;
};
const onScrubEnter = (e: MouseEvent) => {
    const v = (e.currentTarget as HTMLElement).querySelector('video') as HTMLVideoElement | null;
    v?.pause();
};
const onScrubLeave = (i: number) => {
    scrubPct.value = { ...scrubPct.value, [i]: 0 };
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
    const v = heroVideoEl.value;
    if (v) {
        v.addEventListener('play', () => (heroPlaying.value = true));
        v.addEventListener('pause', () => (heroPlaying.value = false));
    }
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
        class="ws-root [--paper:#08080C] [--soft:#0C0C12] [--surface:#12121A] [--ink:#F3F4F6] [--muted:#9CA3AF] [--line:#27273A] [--accent:#E11D48] [--accent-ink:#FFFFFF] selection:bg-[var(--accent)] selection:text-white"
        :class="{ 'ws-js': ready }"
    >
        <!-- ============================ NAVBAR ============================ -->
        <header v-if="logoImage || logoText || navLinks.length || has('contact')" class="inset-x-0 top-0 z-50" :class="stickyNav ? 'fixed' : 'absolute'">
            <div ref="progressEl" class="ws-progress" aria-hidden="true" />
            <nav ref="navEl" class="ws-nav" aria-label="Primary">
                <div class="mx-auto flex h-16 w-full max-w-[1320px] items-center justify-between gap-4 px-5 sm:px-8">
                    <a v-if="logoImage || logoText" href="#header" class="flex min-w-0 items-center gap-3" @click="onNavClick($event, '#header')">
                        <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-7 w-auto max-w-[7rem] object-contain" />
                        <span v-else class="ws-rec" aria-hidden="true" />
                        <span v-if="logoText" class="ws-display truncate text-base uppercase tracking-[0.14em]">{{ logoText }}</span>
                    </a>
                    <span v-else />

                    <ul v-if="navLinks.length" class="hidden items-center gap-1 md:flex">
                        <li v-for="l in navLinks" :key="l.id">
                            <a :href="`#${l.id}`" class="ws-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a>
                        </li>
                    </ul>

                    <a v-if="has('contact')" href="#contact" class="ws-btn ws-btn-primary !px-5 !py-2.5 !text-[0.8rem]" @click="onNavClick($event, '#contact')">Contact me<ArrowUpRight class="ws-arrow h-4 w-4" />
                    </a>
                    <span v-else />
                </div>
            </nav>
        </header>

        <!-- ============================== HERO (THEATER) ============================== -->
        <section v-if="has('header')" id="header" class="ws-hero relative isolate flex min-h-[100vh] items-center overflow-hidden">
            <span id="home" class="absolute top-0" aria-hidden="true" />
            <img
                v-if="media('header', 'hero_background_image')"
                :src="media('header', 'hero_background_image')"
                alt=""
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-[0.22] blur-[2px]"
            />
            <div class="ws-letterbox ws-letterbox-top" aria-hidden="true" />
            <div class="ws-letterbox ws-letterbox-bottom" aria-hidden="true" />
            <div class="ws-hero-glow" aria-hidden="true" />

            <div class="mx-auto flex w-full max-w-[1320px] flex-col gap-10 px-5 pb-16 pt-28 sm:px-8 lg:pt-24">
                <div class="grid items-end gap-8 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <p v-if="text('header', 'hero_greeting')" data-reveal class="ws-label" style="--d: 0ms">
                            <span class="ws-rec" aria-hidden="true" />{{ text('header', 'hero_greeting') }}
                        </p>
                        <h1 v-if="heroName" class="ws-display ws-hero-name mt-5">
                            <span class="sr-only">{{ heroName }}</span>
                            <span v-for="(w, i) in heroWords" :key="i" class="ws-mask" aria-hidden="true">
                                <span class="ws-word" :style="{ '--i': i }">{{ w }}</span>
                            </span>
                        </h1>
                    </div>
                    <div class="lg:col-span-4">
                        <p v-if="text('header', 'hero_role')" data-reveal class="ws-mono ws-role" style="--d: 380ms">
                            <Film class="h-4 w-4 text-[var(--accent)]" />{{ text('header', 'hero_role') }}
                        </p>
                        <p
                            v-if="text('header', 'hero_description')"
                            data-reveal
                            class="ws-muted mt-4 whitespace-pre-line leading-[1.7]"
                            style="--d: 460ms"
                        >
                            {{ text('header', 'hero_description') }}
                        </p>
                    </div>
                </div>

                <!-- 16:9 cinematic player frame -->
                <div data-reveal class="ws-player-wrap" style="--d: 250ms">
                    <div class="ws-edge ws-edge-l" aria-hidden="true" />
                    <div class="ws-edge ws-edge-r" aria-hidden="true" />
                    <figure class="ws-player group">
                        <div class="ws-screen">
                            <video
                                v-if="heroVideoUrl"
                                ref="heroVideoEl"
                                :src="heroVideoUrl"
                                :poster="media('header', 'hero_image')"
                                class="h-full w-full object-cover"
                                playsinline
                                loop
                                muted
                            />
                            <img
                                v-else-if="media('header', 'hero_image')"
                                :src="media('header', 'hero_image')"
                                :alt="heroName ?? ''"
                                class="h-full w-full object-cover transition-transform duration-[1200ms] ease-out group-hover:scale-[1.04]"
                            />
                            <span v-else-if="heroInitials" class="ws-display flex h-full w-full items-center justify-center bg-[var(--surface)] text-[clamp(6rem,18vw,14rem)] text-[var(--accent)]">{{
                                heroInitials
                            }}</span>
                            <div class="ws-scan" aria-hidden="true" />
                            <div class="ws-vig" aria-hidden="true" />

                            <!-- top HUD -->
                            <div class="ws-hud ws-hud-top">
                                <span class="ws-rec-pill ws-mono"><span class="ws-rec" aria-hidden="true" />REC</span>
                                <span v-if="location" class="ws-hud-chip ws-mono"><MapPin class="h-3 w-3" />{{ location }}</span>
                            </div>

                            <!-- sound wave badge -->
                            <div class="ws-wave-badge" aria-hidden="true">
                                <Volume2 class="h-4 w-4 text-[var(--accent)]" />
                                <span class="ws-wave"><i /><i /><i /><i /><i /><i /><i /></span>
                            </div>

                            <!-- center play overlay -->
                            <component
                                :is="heroVideoUrl ? 'button' : heroPlayUrl ? 'a' : 'span'"
                                :type="heroVideoUrl ? 'button' : undefined"
                                :href="!heroVideoUrl && heroPlayUrl ? heroPlayUrl : undefined"
                                :target="!heroVideoUrl && heroPlayUrl ? '_blank' : undefined"
                                :rel="!heroVideoUrl && heroPlayUrl ? 'noopener noreferrer' : undefined"
                                class="ws-play"
                                :class="{ 'is-playing': heroPlaying }"
                                :aria-label="heroName ?? 'Play'"
                                @click="heroVideoUrl ? toggleHeroPlay() : undefined"
                            >
                                <span class="ws-play-ring" aria-hidden="true" />
                                <Play class="ml-1 h-8 w-8 fill-current" />
                            </component>

                            <!-- control bar -->
                            <div class="ws-controls">
                                <button type="button" class="ws-ctrl" :aria-label="heroName ?? 'Play'" @click="toggleHeroPlay"><Play class="h-4 w-4 fill-current" /></button>
                                <span class="ws-timeline" aria-hidden="true"><i /></span>
                                <button type="button" class="ws-ctrl" aria-label="Volume" @click="toggleHeroMute">
                                    <Volume2 class="h-4 w-4" :class="{ 'opacity-40': heroMuted }" />
                                </button>
                                <button type="button" class="ws-ctrl" aria-label="Fullscreen" @click="heroFullscreen"><Maximize2 class="h-4 w-4" /></button>
                            </div>
                        </div>
                    </figure>
                </div>

                <div v-if="heroPrimary || heroSecondary || heroSocials.length" class="flex flex-wrap items-center justify-between gap-6">
                    <div v-if="heroPrimary || heroSecondary" data-reveal class="flex flex-wrap gap-3" style="--d: 540ms">
                        <a v-if="heroPrimary" :href="heroPrimary.url" class="ws-btn ws-btn-primary" @click="onNavClick($event, heroPrimary.url)"
                            ><Play class="h-4 w-4 fill-current" />{{ heroPrimary.text }}<ArrowUpRight class="ws-arrow h-4 w-4"
                        /></a>
                        <a v-if="heroSecondary" :href="heroSecondary.url" class="ws-btn ws-btn-ghost" @click="onNavClick($event, heroSecondary.url)">{{
                            heroSecondary.text
                        }}</a>
                    </div>
                    <ul v-if="heroSocials.length" data-reveal class="flex flex-wrap gap-2" style="--d: 620ms">
                        <li v-for="(s, i) in heroSocials" :key="i">
                            <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="ws-icon-btn">
                                <component :is="socialIconFor(s.platform)" class="h-[18px] w-[18px]" />
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ============================== ABOUT ============================== -->
        <section v-if="has('about')" id="about" class="ws-band scroll-mt-0">
            <div class="mx-auto grid w-full max-w-[1320px] items-center gap-14 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-16">
                <div v-if="media('about', 'about_image')" data-reveal class="lg:col-span-5">
                    <div class="ws-frame mx-auto max-w-md">
                        <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="aspect-[4/5] w-full object-cover" loading="lazy" />
                        <span class="ws-corner ws-corner-tl" aria-hidden="true" />
                        <span class="ws-corner ws-corner-br" aria-hidden="true" />
                    </div>
                </div>

                <div :class="media('about', 'about_image') ? 'lg:col-span-7' : 'lg:col-span-10'">
                    <div data-reveal>
                        <div class="ws-head">
                            <span class="ws-index ws-mono">{{ numberOf('about') }}</span>
                            <p v-if="text('about', 'about_subtitle')" class="ws-kicker">{{ text('about', 'about_subtitle') }}</p>
                        </div>
                        <h2 v-if="text('about', 'about_title')" class="ws-display ws-h2 mt-5">{{ text('about', 'about_title') }}</h2>
                        <p v-if="text('about', 'about_description')" class="ws-muted mt-7 max-w-[40rem] whitespace-pre-line text-lg leading-[1.75]">
                            {{ text('about', 'about_description') }}
                        </p>
                    </div>

                    <ul v-if="highlights.length" data-reveal class="mt-10 grid gap-3 sm:grid-cols-2">
                        <li v-for="(h, i) in highlights" :key="i" :style="{ '--k': i }" class="ws-hl">
                            <span class="ws-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>
                            <span>
                                <span class="block font-semibold leading-snug">{{ h.title }}</span>
                                <span v-if="h.description" class="ws-muted mt-1 block text-[0.92rem] leading-relaxed">{{ h.description }}</span>
                            </span>
                        </li>
                    </ul>

                    <div v-if="cvFile || aboutCta" data-reveal class="mt-10 flex flex-wrap gap-3">
                        
                        <a v-if="aboutCta && cvFile" :href="aboutCta.url" class="ws-btn ws-btn-primary" @click="onNavClick($event, aboutCta.url)">
                            {{ aboutCta.text }}<ArrowUpRight class="ws-arrow h-4 w-4" />
                        </a>
                        <a v-else-if="aboutCta" :href="aboutCta.url" class="ws-btn ws-btn-primary" @click="onNavClick($event, aboutCta.url)">
                            {{ aboutCta.text }}<ArrowUpRight class="ws-arrow h-4 w-4" />
                        </a>
                        <a v-else-if="cvFile" :href="cvFile" download target="_blank" rel="noopener" class="ws-btn ws-btn-primary">
                            <Download class="h-4 w-4" />{{ aboutCta?.text ?? text('about', 'about_title') ?? text('about', 'about_subtitle') ?? '' }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================== JOURNEY ============================== -->
        <section v-if="has('journey')" id="journey" class="scroll-mt-0">
            <div class="mx-auto grid w-full max-w-[1320px] gap-12 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-16">
                <div class="lg:col-span-4">
                    <div data-reveal class="lg:sticky lg:top-28">
                        <div class="ws-head">
                            <span class="ws-index ws-mono">{{ numberOf('journey') }}</span>
                            <p v-if="text('journey', 'journey_subtitle')" class="ws-kicker">{{ text('journey', 'journey_subtitle') }}</p>
                        </div>
                        <h2 v-if="text('journey', 'journey_title')" class="ws-display ws-h2 mt-5">{{ text('journey', 'journey_title') }}</h2>
                    </div>
                </div>

                <ol class="ws-reel lg:col-span-8">
                    <li v-for="(item, index) in journey" :key="index" data-reveal :style="{ '--d': `${(index % 4) * 70}ms` }" class="ws-clip">
                        <span class="ws-clip-num ws-mono" aria-hidden="true">{{ String(index + 1).padStart(2, '0') }}</span>
                        <div class="ws-clip-card">
                            <p v-if="item.period" class="ws-mono text-[0.78rem] font-medium uppercase tracking-[0.14em] text-[var(--accent)]">{{ item.period }}</p>
                            <h3 v-if="item.title" class="ws-display mt-2 text-2xl leading-tight">{{ item.title }}</h3>
                            <p v-if="item.company" class="mt-1 font-semibold">{{ item.company }}</p>
                            <p v-if="item.description" class="ws-muted mt-3 max-w-[38rem] whitespace-pre-line leading-[1.7]">{{ item.description }}</p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ============================== SKILLS ============================== -->
        <section v-if="has('skills')" id="skills" class="ws-band scroll-mt-0">
            <div class="mx-auto grid w-full max-w-[1320px] gap-12 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-16">
                <div class="lg:col-span-4">
                    <div data-reveal class="lg:sticky lg:top-28">
                        <div class="ws-head">
                            <span class="ws-index ws-mono">{{ numberOf('skills') }}</span>
                            <p v-if="text('skills', 'skills_subtitle')" class="ws-kicker">{{ text('skills', 'skills_subtitle') }}</p>
                        </div>
                        <h2 v-if="text('skills', 'skills_title')" class="ws-display ws-h2 mt-5">{{ text('skills', 'skills_title') }}</h2>
                        <p v-if="text('skills', 'skills_description')" class="ws-muted mt-6 whitespace-pre-line leading-[1.7]">{{ text('skills', 'skills_description') }}</p>
                    </div>
                </div>

                <ul class="grid content-start gap-4 sm:grid-cols-2 lg:col-span-8">
                    <li v-for="(s, i) in skills" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 50}ms` }" class="ws-skill">
                        <div class="flex items-center gap-3.5">
                            <span v-if="showIcon" class="ws-tile">
                                <component :is="iconFor(s.icon)" v-if="iconFor(s.icon)" class="h-[18px] w-[18px]" />
                                <span v-else-if="isGlyph(s.icon)" class="text-lg leading-none" aria-hidden="true">{{ s.icon }}</span>
                                <span v-else class="ws-display text-base leading-none" aria-hidden="true">{{ s.name.charAt(0).toUpperCase() }}</span>
                            </span>
                            <h3 class="font-semibold">{{ s.name }}</h3>
                            <span
                                v-if="showLevel && s.pct !== null"
                                class="ws-count ws-mono ws-muted ml-auto text-[0.8rem] font-medium"
                                :style="{ '--p': Math.round(s.pct) }"
                                aria-hidden="true"
                            />
                            <span v-else-if="showLevel && s.label" class="ws-pill ml-auto">{{ s.label }}</span>
                        </div>
                        <div
                            v-if="showLevel && s.pct !== null"
                            class="ws-track mt-4"
                            role="progressbar"
                            :aria-valuenow="Math.round(s.pct)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="s.name"
                        >
                            <div class="ws-bar" :style="{ '--w': `${s.pct}%` }"><span class="ws-knob" /></div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== PROJECTS (VIDEO WORK) ============================== -->
        <section v-if="has('projects')" id="projects" class="relative scroll-mt-0">
            <div class="mx-auto w-full max-w-[1320px] px-5 py-28 sm:px-8">
                <div data-reveal class="ws-head">
                    <span class="ws-index ws-mono">{{ numberOf('projects') }}</span>
                    <p v-if="text('projects', 'projects_subtitle')" class="ws-kicker">{{ text('projects', 'projects_subtitle') }}</p>
                </div>
                <div data-reveal class="mb-14 mt-5 grid gap-6 lg:grid-cols-12 lg:items-end">
                    <h2 v-if="text('projects', 'projects_title')" class="ws-display ws-h2 lg:col-span-8">{{ text('projects', 'projects_title') }}</h2>
                    <p v-if="text('projects', 'projects_description')" class="ws-muted whitespace-pre-line leading-[1.7] lg:col-span-4">
                        {{ text('projects', 'projects_description') }}
                    </p>
                </div>

                <ul class="grid gap-x-7 gap-y-14 md:grid-cols-2">
                    <li
                        v-for="(p, i) in projects"
                        :key="i"
                        data-reveal
                        :class="featureFirst && i === 0 ? 'md:col-span-2' : ''"
                        :style="{ '--d': `${(i % 2) * 80}ms` }"
                    >
                        <article class="ws-project group flex h-full flex-col">
                            <component
                                :is="p.live ? 'a' : 'div'"
                                :href="p.live || undefined"
                                :target="p.live ? '_blank' : undefined"
                                :rel="p.live ? 'noopener noreferrer' : undefined"
                                :aria-label="p.live ? p.title : undefined"
                                class="ws-thumb relative block overflow-hidden"
                                :class="[ratioClass(p.ratio), featureFirst && i === 0 ? 'is-feature' : '']"
                                @mouseenter="onScrubEnter"
                                @mousemove="onScrub($event, i)"
                                @mouseleave="onScrubLeave(i)"
                            >
                                <video
                                    v-if="p.video"
                                    :src="p.video"
                                    :poster="p.image"
                                    class="ws-thumb-media"
                                    muted
                                    playsinline
                                    preload="metadata"
                                />
                                <img v-else-if="p.image" :src="p.image" :alt="p.title" class="ws-thumb-media" loading="lazy" />
                                <div v-else class="ws-display ws-thumb-media ws-tone flex items-center justify-center text-8xl" :class="`ws-tone-${i % 3}`">
                                    {{ p.title.charAt(0).toUpperCase() }}
                                </div>

                                <div class="ws-thumb-shade" aria-hidden="true" />

                                <!-- badges -->
                                <span v-if="p.ratio" class="ws-ratio ws-mono">{{ p.ratio }}</span>
                                <span v-if="featureFirst && i === 0" class="ws-flag ws-mono"><Sparkles class="h-3 w-3" />{{ String(i + 1).padStart(2, '0') }}</span>
                                <span v-if="p.duration" class="ws-duration ws-mono"><Film class="h-3 w-3" />{{ p.duration }}</span>

                                <!-- play trigger -->
                                <span class="ws-thumb-play" aria-hidden="true"><Play class="ml-0.5 h-6 w-6 fill-current" /></span>

                                <!-- scrub bar -->
                                <span class="ws-scrub" aria-hidden="true"><i :style="{ width: `${scrubPct[i] ?? 0}%` }" /></span>
                            </component>

                            <div class="flex flex-1 flex-col pt-5">
                                <div class="flex items-start justify-between gap-4">
                                    <h3 class="ws-display text-2xl leading-tight"><span class="ws-underline">{{ p.title }}</span></h3>
                                    <Video class="mt-1 h-5 w-5 shrink-0 text-[var(--muted)] transition-colors group-hover:text-[var(--accent)]" aria-hidden="true" />
                                </div>
                                <p v-if="p.description" class="ws-muted mt-3 leading-[1.65]">{{ p.description }}</p>
                                <ul v-if="p.tags.length" class="mt-4 flex flex-wrap gap-2">
                                    <li v-for="(t, ti) in p.tags" :key="ti" class="ws-pill">{{ t }}</li>
                                </ul>
                                <div v-if="p.repo" class="mt-auto pt-5">
                                    <a :href="p.repo" target="_blank" rel="noopener noreferrer" class="ws-textlink ws-mono text-[0.8rem]"
                                        ><Code class="h-4 w-4" />{{ p.repo.replace(/^https?:\/\/(www\.)?/, '').split('/').slice(0, 2).join('/') }}</a
                                    >
                                </div>
                            </div>
                        </article>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== SERVICES (POST-PRODUCTION SUITE) ============================== -->
        <section v-if="has('services')" id="services" class="ws-band scroll-mt-0">
            <div class="mx-auto w-full max-w-[1320px] px-5 py-28 sm:px-8">
                <div data-reveal class="ws-head">
                    <span class="ws-index ws-mono">{{ numberOf('services') }}</span>
                    <p v-if="text('services', 'services_subtitle')" class="ws-kicker">{{ text('services', 'services_subtitle') }}</p>
                </div>
                <h2 v-if="text('services', 'services_title')" data-reveal class="ws-display ws-h2 mb-14 mt-5 max-w-4xl">{{ text('services', 'services_title') }}</h2>

                <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
                    <!-- service menu -->
                    <ul data-reveal class="ws-menu lg:col-span-5" role="tablist" aria-label="Services">
                        <li v-for="(s, i) in services" :key="i" :style="{ '--d': `${(i % 6) * 60}ms` }">
                            <button
                                type="button"
                                role="tab"
                                class="ws-menu-item group"
                                :aria-selected="activeService === i"
                                @click="activeService = i"
                                @mouseenter="activeService = i"
                                @focus="activeService = i"
                            >
                                <span class="ws-mono ws-menu-num">{{ String(i + 1).padStart(2, '0') }}</span>
                                <span class="ws-tile">
                                    <component :is="iconFor(s.icon) ?? Clapperboard" v-if="!isGlyph(s.icon)" class="h-[18px] w-[18px]" />
                                    <span v-else class="text-lg leading-none" aria-hidden="true">{{ s.icon }}</span>
                                </span>
                                <span class="ws-display ws-menu-title min-w-0 flex-1 text-left text-xl leading-tight">{{ s.title }}</span>
                                <ArrowUpRight class="ws-menu-arrow h-5 w-5 shrink-0" aria-hidden="true" />
                            </button>
                        </li>
                    </ul>

                    <!-- comparison / preview monitor -->
                    <div v-if="activeSvc" data-reveal class="lg:col-span-7" style="--d: 120ms">
                        <div class="ws-monitor">
                            <div class="ws-monitor-bar">
                                <span class="ws-mono flex items-center gap-2 text-[0.72rem] uppercase tracking-[0.14em]">
                                    <component :is="iconFor(activeSvc.icon) ?? Clapperboard" v-if="!isGlyph(activeSvc.icon)" class="h-3.5 w-3.5 text-[var(--accent)]" />
                                    {{ activeSvc.title }}
                                </span>
                                <span v-if="activeSvc.compare" class="ws-mono text-[0.7rem] uppercase tracking-[0.14em] text-[var(--muted)]">{{ Math.round(compare) }}%</span>
                            </div>

                            <div class="ws-compare" :class="{ 'is-static': !activeSvc.compare }">
                                <template v-if="activeSvc.compare">
                                    <img :src="activeSvc.after" alt="" class="ws-compare-img" />
                                    <div class="ws-compare-before" :style="{ clipPath: `inset(0 ${100 - compare}% 0 0)` }">
                                        <img :src="activeSvc.before" alt="" class="ws-compare-img ws-before-filter" />
                                    </div>
                                    <span class="ws-compare-handle" :style="{ left: `${compare}%` }" aria-hidden="true"><Maximize2 class="h-4 w-4 rotate-45" /></span>
                                    <input
                                        type="range"
                                        min="0"
                                        max="100"
                                        step="0.5"
                                        :value="compare"
                                        class="ws-compare-range"
                                        :aria-label="activeSvc.title"
                                        @input="onCompare"
                                    />
                                </template>
                                <template v-else>
                                    <img v-if="activeSvc.after" :src="activeSvc.after" alt="" class="ws-compare-img" />
                                    <div v-else class="ws-compare-fallback" aria-hidden="true">
                                        <span class="ws-wave ws-wave-lg"><i /><i /><i /><i /><i /><i /><i /><i /><i /></span>
                                    </div>
                                </template>
                                <div class="ws-scan" aria-hidden="true" />
                            </div>

                            <div class="ws-monitor-foot">
                                <p v-if="activeSvc.description" class="ws-muted leading-[1.65]">{{ activeSvc.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================== CONTACT ============================== -->
        <section v-if="has('contact')" id="contact" class="ws-contact scroll-mt-0">
            <div class="ws-contact-glow" aria-hidden="true" />
            <div class="relative z-10 mx-auto grid w-full max-w-[1320px] gap-14 px-5 py-24 sm:px-8 lg:grid-cols-12 lg:gap-12 lg:py-32">
                <div class="lg:col-span-7">
                    <div data-reveal>
                        <div class="ws-head">
                            <span class="ws-index ws-mono">{{ numberOf('contact') }}</span>
                            <p v-if="text('contact', 'contact_subtitle')" class="ws-kicker">{{ text('contact', 'contact_subtitle') }}</p>
                        </div>
                        <h2 v-if="text('contact', 'contact_title')" class="ws-display mt-5 text-[clamp(2.75rem,8vw,6.5rem)] leading-[0.94] [overflow-wrap:anywhere]">
                            {{ text('contact', 'contact_title') }}
                        </h2>
                        <p v-if="text('contact', 'contact_description')" class="ws-muted mt-7 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7]">
                            {{ text('contact', 'contact_description') }}
                        </p>
                    </div>

                    <div v-if="email || phone || location" data-reveal class="mt-10 space-y-5">
                        <a v-if="email" :href="`mailto:${email}`" class="ws-bigmail ws-display group inline-flex max-w-full items-center gap-3 break-all text-2xl sm:text-4xl">
                            <Mail class="h-7 w-7 shrink-0 text-[var(--accent)]" /><span class="ws-underline-contact">{{ email }}</span>
                            <ArrowUpRight class="ws-arrow h-7 w-7 shrink-0" />
                        </a>
                        <div class="flex flex-wrap gap-x-8 gap-y-3 font-medium">
                            <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="ws-contact-link"><Phone class="h-4 w-4 text-[var(--accent)]" />{{ phone }}</a>
                            <span v-if="location" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4 text-[var(--accent)]" />{{ location }}</span>
                        </div>
                    </div>

                    <ul v-if="contactSocials.length" data-reveal class="mt-8 flex flex-wrap gap-2">
                        <li v-for="(s, i) in contactSocials" :key="i">
                            <a :href="s.url" target="_blank" rel="noopener noreferrer" class="ws-social">
                                <component :is="socialIconFor(s.platform)" class="h-3.5 w-3.5" />{{ s.label }}<ArrowUpRight class="ws-arrow h-3.5 w-3.5" />
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
                            target="ws-gform-frame"
                            class="ws-form space-y-5 p-6 sm:p-8"
                            @submit="onSubmit"
                        >
                            <label class="block">
                                <span class="ws-mono ws-muted mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.14em]">Name</span>
                                <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="ws-input" />
                            </label>
                            <label class="block">
                                <span class="ws-mono ws-muted mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.14em]">Email</span>
                                <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="ws-input" />
                            </label>
                            <label class="block">
                                <span class="ws-mono ws-muted mb-2 block text-[0.72rem] font-medium uppercase tracking-[0.14em]">Message</span>
                                <textarea :name="`entry.${gf.message}`" rows="5" required class="ws-input resize-y" />
                            </label>
                            <button type="submit" class="ws-btn ws-btn-primary w-full justify-center" :disabled="sending">
                                {{ sending ? '…' : 'Send' }}<Send class="ws-arrow h-4 w-4" />
                            </button>
                            <p v-if="sent" role="status" class="ws-sent flex items-center gap-2 text-sm font-semibold">
                                <span class="ws-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>OK
                            </p>
                        </form>
                        <a v-else-if="email" :href="`mailto:${email}`" class="ws-btn ws-btn-ghost w-full justify-center !py-5 text-lg"><Mail class="h-5 w-5" />{{ email }}</a>
                    </div>
                    <iframe name="ws-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                </div>
            </div>

            <footer v-if="heroName || logoText" class="relative z-10 mx-auto flex max-w-[1320px] flex-wrap items-center justify-between gap-2 border-t border-[var(--line)] px-5 py-7 text-sm sm:px-8">
                <span class="ws-muted ws-mono text-[0.78rem]">&copy; {{ year }} {{ heroName ?? logoText }}</span>
                <a href="#header" class="ws-textlink" aria-label="Top" @click="onNavClick($event, '#header')"><ArrowUpRight class="ws-arrow h-4 w-4 -rotate-45" /></a>
            </footer>
        </section>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap');

/* ---------- Foundations ---------- */
.ws-root {
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
/* Light variant (OS preference or explicit light class on an ancestor) */
@media (prefers-color-scheme: light) {
    .ws-root:not(.dark) {
        --paper: #f4f4f7;
        --soft: #ebebf0;
        --surface: #ffffff;
        --ink: #0b0b10;
        --muted: #55556a;
        --line: #d4d4de;
        --accent: #d4123f;
    }
}
:global(.light) .ws-root {
    --paper: #f4f4f7;
    --soft: #ebebf0;
    --surface: #ffffff;
    --ink: #0b0b10;
    --muted: #55556a;
    --line: #d4d4de;
    --accent: #d4123f;
}
:global(.dark) .ws-root {
    --paper: #08080c;
    --soft: #0c0c12;
    --surface: #12121a;
    --ink: #f3f4f6;
    --muted: #9ca3af;
    --line: #27273a;
    --accent: #e11d48;
}
.ws-display {
    font-family: 'Barlow Condensed', 'Inter', ui-sans-serif, system-ui, sans-serif;
    font-weight: 700;
    letter-spacing: -0.005em;
}
.ws-mono {
    font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
}
.ws-muted {
    color: var(--muted);
}
.ws-h2 {
    font-size: clamp(2.6rem, 6vw, 5rem);
    line-height: 0.95;
    text-transform: uppercase;
    text-wrap: balance;
}
.ws-band {
    background: var(--soft);
    border-block: 1px solid var(--line);
}
.ws-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
}

/* Section header */
.ws-head {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.ws-index {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--accent);
}
.ws-kicker {
    display: inline-flex;
    align-items: center;
    gap: 1rem;
    font-family: 'IBM Plex Mono', ui-monospace, monospace;
    font-size: 0.76rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.14em;
}
.ws-kicker::before {
    content: '';
    width: 2.5rem;
    height: 1px;
    background: currentColor;
    transform-origin: left;
    transition: transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) 0.2s;
}
.ws-js [data-reveal]:not(.is-in) .ws-kicker::before {
    transform: scaleX(0);
}
.ws-rec {
    display: inline-block;
    height: 0.6rem;
    width: 0.6rem;
    flex-shrink: 0;
    border-radius: 999px;
    background: var(--accent);
    box-shadow: 0 0 12px var(--accent);
    animation: ws-blink 1.6s ease-in-out infinite;
}

/* ---------- Navbar ---------- */
.ws-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 3px;
    background: var(--accent);
    box-shadow: 0 0 14px var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
    z-index: 60;
}
.ws-nav {
    border-bottom: 1px solid transparent;
    transition:
        border-color 0.35s ease,
        background-color 0.35s ease;
}
.ws-nav.is-scrolled {
    border-color: var(--line);
    background: color-mix(in srgb, var(--paper) 82%, transparent);
    -webkit-backdrop-filter: blur(14px) saturate(1.3);
    backdrop-filter: blur(14px) saturate(1.3);
}
.ws-navlink {
    display: inline-block;
    padding: 0.5rem 0.95rem;
    font-family: 'IBM Plex Mono', ui-monospace, monospace;
    font-size: 0.76rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--muted);
    background: linear-gradient(var(--accent), var(--accent)) 0.95rem calc(100% - 0.15rem) / 0% 2px no-repeat;
    transition:
        color 0.25s ease,
        background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ws-navlink:hover,
.ws-navlink[data-active='true'] {
    color: var(--ink);
}
.ws-navlink[data-active='true'] {
    background-size: calc(100% - 1.9rem) 2px;
}

/* ---------- Buttons & small controls ---------- */
.ws-btn {
    position: relative;
    isolation: isolate;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    border-radius: 0.35rem;
    padding: 0.9rem 1.5rem;
    font-family: 'IBM Plex Mono', ui-monospace, monospace;
    font-size: 0.78rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.1em;
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
.ws-btn:hover {
    background-size: 100% 100%;
    transform: translateY(-2px);
}
.ws-btn:active {
    transform: translateY(0) scale(0.98);
}
.ws-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.ws-btn-primary {
    background-color: var(--accent);
    background-image: linear-gradient(var(--ink), var(--ink));
    color: var(--accent-ink);
    box-shadow: 0 10px 28px -12px var(--accent);
}
.ws-btn-primary:hover {
    color: var(--paper);
    box-shadow: 0 16px 34px -12px var(--accent);
}
.ws-btn-ghost {
    background-color: transparent;
    background-image: linear-gradient(var(--ink), var(--ink));
    border-color: var(--line);
    color: var(--ink);
}
.ws-btn-ghost:hover {
    color: var(--paper);
    border-color: var(--ink);
}
.ws-arrow {
    transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ws-btn:hover .ws-arrow,
.ws-bigmail:hover .ws-arrow,
.ws-social:hover .ws-arrow,
.ws-textlink:hover .ws-arrow {
    transform: translate(3px, -3px);
}
.ws-icon-btn {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.35rem;
    border: 1px solid var(--line);
    background: var(--surface);
    transition:
        transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1),
        background-color 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease,
        box-shadow 0.3s ease;
}
.ws-icon-btn:hover {
    transform: translateY(-3px);
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
    box-shadow: 0 10px 24px -8px var(--accent);
}
.ws-label {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: color-mix(in srgb, var(--surface) 80%, transparent);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    padding: 0.45rem 1rem 0.45rem 0.85rem;
    font-family: 'IBM Plex Mono', ui-monospace, monospace;
    font-size: 0.76rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}
.ws-pill {
    display: inline-block;
    border: 1px solid var(--line);
    border-radius: 0.3rem;
    padding: 0.2rem 0.6rem;
    font-family: 'IBM Plex Mono', ui-monospace, monospace;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
}
.ws-tick {
    display: inline-flex;
    height: 1.5rem;
    width: 1.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.3rem;
    background: var(--accent);
    color: var(--accent-ink);
}
.ws-textlink {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    background: linear-gradient(currentColor, currentColor) left bottom / 0% 1.5px no-repeat;
    transition: background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 2px;
}
.ws-textlink:hover {
    background-size: 100% 1.5px;
}

/* ---------- Hero / theater ---------- */
.ws-hero {
    background:
        radial-gradient(70% 55% at 50% 35%, color-mix(in srgb, var(--accent) 16%, transparent), transparent 70%),
        var(--paper);
}
.ws-letterbox {
    position: absolute;
    left: 0;
    right: 0;
    z-index: -1;
    height: 4.5rem;
    background: color-mix(in srgb, #000 70%, transparent);
    pointer-events: none;
}
.ws-letterbox-top {
    top: 0;
}
.ws-letterbox-bottom {
    bottom: 0;
}
.ws-hero-glow {
    position: absolute;
    inset: 0;
    z-index: -1;
    background: radial-gradient(40% 40% at 50% 70%, color-mix(in srgb, var(--accent) 22%, transparent), transparent 75%);
    animation: ws-breathe 7s ease-in-out infinite;
    pointer-events: none;
}
.ws-hero-name {
    font-size: clamp(3.5rem, 11vw, 9rem);
    line-height: 0.88;
    text-transform: uppercase;
    overflow-wrap: anywhere;
}
.ws-mask {
    display: inline-block;
    overflow: hidden;
    vertical-align: top;
    padding-bottom: 0.1em;
    margin-bottom: -0.1em;
    margin-right: 0.22em;
}
.ws-word {
    display: inline-block;
}
.ws-js .ws-word {
    transform: translateY(110%);
    animation: ws-rise 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: calc(0.15s + var(--i, 0) * 0.1s);
}
.ws-role {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.82rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}

.ws-player-wrap {
    position: relative;
}
.ws-edge {
    position: absolute;
    top: 8%;
    bottom: 8%;
    width: 22%;
    z-index: 0;
    background: var(--accent);
    filter: blur(70px);
    opacity: 0.42;
    pointer-events: none;
    animation: ws-breathe 5s ease-in-out infinite;
}
.ws-edge-l {
    left: -6%;
}
.ws-edge-r {
    right: -6%;
    animation-delay: -2.5s;
}
.ws-player {
    position: relative;
    z-index: 1;
    border: 1px solid color-mix(in srgb, var(--ink) 22%, var(--line));
    border-radius: 0.9rem;
    background: #000;
    padding: 0.4rem;
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--accent) 25%, transparent),
        0 40px 100px -30px #000,
        0 0 80px -30px var(--accent);
}
.ws-screen {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    border-radius: 0.6rem;
    background: var(--surface);
    isolation: isolate;
}
.ws-scan {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    background: repeating-linear-gradient(to bottom, transparent 0 2px, color-mix(in srgb, #000 14%, transparent) 2px 3px);
    mix-blend-mode: multiply;
    opacity: 0.5;
}
.ws-vig {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    background:
        radial-gradient(120% 100% at 50% 50%, transparent 55%, color-mix(in srgb, #000 55%, transparent) 100%),
        linear-gradient(to top, color-mix(in srgb, #000 70%, transparent), transparent 35%);
}
.ws-hud {
    position: absolute;
    z-index: 4;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    left: 0.9rem;
    right: 0.9rem;
}
.ws-hud-top {
    top: 0.9rem;
}
.ws-rec-pill,
.ws-hud-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 0.3rem;
    background: color-mix(in srgb, #000 60%, transparent);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    padding: 0.25rem 0.6rem;
    font-size: 0.68rem;
    font-weight: 500;
    letter-spacing: 0.14em;
    color: #fff;
}
.ws-hud-chip {
    text-transform: uppercase;
}
.ws-wave-badge {
    position: absolute;
    z-index: 4;
    right: 0.9rem;
    top: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    border: 1px solid color-mix(in srgb, #fff 18%, transparent);
    border-radius: 999px;
    background: color-mix(in srgb, #000 60%, transparent);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    padding: 0.35rem 0.8rem;
}
.ws-wave {
    display: inline-flex;
    height: 1.1rem;
    align-items: center;
    gap: 3px;
}
.ws-wave i {
    display: block;
    height: 100%;
    width: 3px;
    border-radius: 3px;
    background: var(--accent);
    transform-origin: center;
    animation: ws-wave 1.1s ease-in-out infinite;
}
.ws-wave i:nth-child(2) {
    animation-delay: -0.15s;
}
.ws-wave i:nth-child(3) {
    animation-delay: -0.3s;
}
.ws-wave i:nth-child(4) {
    animation-delay: -0.45s;
}
.ws-wave i:nth-child(5) {
    animation-delay: -0.6s;
}
.ws-wave i:nth-child(6) {
    animation-delay: -0.75s;
}
.ws-wave i:nth-child(7) {
    animation-delay: -0.9s;
}
.ws-wave i:nth-child(8) {
    animation-delay: -0.2s;
}
.ws-wave i:nth-child(9) {
    animation-delay: -0.5s;
}
.ws-wave-lg {
    height: 5rem;
    gap: 6px;
}
.ws-wave-lg i {
    width: 6px;
}
.ws-play {
    position: absolute;
    z-index: 4;
    left: 50%;
    top: 50%;
    display: inline-flex;
    height: 5.25rem;
    width: 5.25rem;
    transform: translate(-50%, -50%);
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-ink);
    cursor: pointer;
    box-shadow: 0 0 0 8px color-mix(in srgb, var(--accent) 28%, transparent), 0 20px 50px -10px var(--accent);
    transition:
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1),
        opacity 0.35s ease;
}
.ws-play:hover {
    transform: translate(-50%, -50%) scale(1.1);
}
.ws-play.is-playing {
    opacity: 0;
    pointer-events: none;
}
.ws-play-ring {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    border: 2px solid var(--accent);
    animation: ws-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.ws-controls {
    position: absolute;
    z-index: 4;
    left: 0.9rem;
    right: 0.9rem;
    bottom: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    color: #fff;
}
.ws-ctrl {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.35rem;
    background: color-mix(in srgb, #000 55%, transparent);
    cursor: pointer;
    transition:
        background-color 0.25s ease,
        transform 0.25s ease;
}
.ws-ctrl:hover {
    background: var(--accent);
    transform: translateY(-2px);
}
.ws-timeline {
    position: relative;
    height: 4px;
    flex: 1;
    overflow: hidden;
    border-radius: 999px;
    background: color-mix(in srgb, #fff 25%, transparent);
}
.ws-timeline i {
    position: absolute;
    inset: 0 auto 0 0;
    width: 100%;
    border-radius: inherit;
    background: var(--accent);
    transform-origin: left;
    animation: ws-timeline 14s linear infinite;
}

/* ---------- About ---------- */
.ws-frame {
    position: relative;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.5rem;
    border-radius: 0.5rem;
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ws-frame img {
    border-radius: 0.3rem;
    filter: grayscale(0.35) contrast(1.05);
    transition: filter 0.6s ease;
}
.ws-frame:hover {
    transform: translateY(-4px);
}
.ws-frame:hover img {
    filter: none;
}
.ws-corner {
    position: absolute;
    height: 1.4rem;
    width: 1.4rem;
    border: 2px solid var(--accent);
    pointer-events: none;
}
.ws-corner-tl {
    left: -0.5rem;
    top: -0.5rem;
    border-right: 0;
    border-bottom: 0;
}
.ws-corner-br {
    right: -0.5rem;
    bottom: -0.5rem;
    border-left: 0;
    border-top: 0;
}
.ws-hl {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    border: 1px solid var(--line);
    border-radius: 0.5rem;
    background: var(--surface);
    padding: 1.05rem 1.15rem;
    transition:
        border-color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ws-hl:hover {
    border-color: var(--accent);
    transform: translateY(-3px);
}

/* ---------- Journey (film reel) ---------- */
.ws-reel {
    position: relative;
    margin-left: 0.5rem;
    padding-left: 2.5rem;
}
.ws-reel::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 1.25rem;
    background:
        repeating-linear-gradient(to bottom, var(--line) 0 0.7rem, transparent 0.7rem 1.4rem) center / 0.55rem 100% no-repeat;
    opacity: 0.9;
}
.ws-clip {
    position: relative;
    padding-bottom: 1.25rem;
}
.ws-clip:last-child {
    padding-bottom: 0;
}
.ws-clip-num {
    position: absolute;
    left: -2.5rem;
    top: 1.5rem;
    transform: translateX(-100%) translateX(2.2rem);
    font-size: 0.66rem;
    color: var(--accent);
    display: none;
}
.ws-clip-card {
    position: relative;
    border: 1px solid var(--line);
    border-radius: 0.5rem;
    background: var(--surface);
    padding: 1.4rem 1.6rem 1.5rem;
    transition:
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease,
        box-shadow 0.45s ease;
}
.ws-clip-card::before {
    content: '';
    position: absolute;
    left: -2.5rem;
    top: 1.75rem;
    height: 0.75rem;
    width: 0.75rem;
    transform: translateX(-0.2rem);
    border-radius: 0.15rem;
    background: var(--paper);
    border: 2px solid var(--accent);
    transition:
        background-color 0.4s ease 0.3s,
        box-shadow 0.4s ease;
}
.ws-js .ws-clip.is-in .ws-clip-card::before {
    background: var(--accent);
}
.ws-clip-card:hover {
    transform: translateX(6px);
    border-color: var(--accent);
    box-shadow: 0 20px 40px -26px var(--accent);
}

/* ---------- Skills ---------- */
.ws-skill {
    border: 1px solid var(--line);
    border-radius: 0.5rem;
    background: var(--surface);
    padding: 1.2rem 1.3rem 1.35rem;
    transition:
        border-color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1),
        box-shadow 0.4s ease;
}
.ws-skill:hover {
    border-color: var(--accent);
    transform: translateY(-3px);
    box-shadow: 0 18px 34px -24px var(--accent);
}
.ws-tile {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.4rem;
    background: color-mix(in srgb, var(--accent) 14%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 40%, var(--line));
    color: var(--accent);
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ws-skill:hover .ws-tile,
.ws-menu-item:hover .ws-tile,
.ws-menu-item[aria-selected='true'] .ws-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.ws-track {
    position: relative;
    height: 5px;
    width: 100%;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ink) 10%, transparent);
}
.ws-bar {
    position: relative;
    height: 100%;
    width: var(--w, 0%);
    border-radius: inherit;
    background: linear-gradient(90deg, color-mix(in srgb, var(--accent) 45%, transparent), var(--accent));
    box-shadow: 0 0 14px -2px var(--accent);
}
.ws-knob {
    position: absolute;
    right: 0;
    top: 50%;
    height: 13px;
    width: 13px;
    transform: translate(50%, -50%);
    border-radius: 999px;
    border: 3px solid var(--accent);
    background: var(--paper);
}
.ws-js [data-reveal] .ws-bar {
    width: 0;
    transition: width 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s);
}
.ws-js [data-reveal].is-in .ws-bar {
    width: var(--w, 0%);
}
.ws-js [data-reveal].is-in .ws-knob {
    animation: ws-knob 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) calc(var(--d, 0ms) + 1.6s) backwards;
}

/* Percentage counts up in sync with the bar */
@property --n {
    syntax: '<integer>';
    inherits: false;
    initial-value: 0;
}
.ws-count {
    --n: var(--p, 0);
    counter-reset: n var(--n);
    font-variant-numeric: tabular-nums;
}
.ws-count::after {
    content: counter(n) '%';
}
.ws-js [data-reveal] .ws-count {
    --n: 0;
}
.ws-js [data-reveal].is-in .ws-count {
    animation: ws-count 1.5s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s) forwards;
}

/* ---------- Projects (cinematic grid) ---------- */
.ws-thumb {
    aspect-ratio: 16 / 9;
    border: 1px solid var(--line);
    border-radius: 0.6rem;
    background: var(--surface);
    isolation: isolate;
    cursor: pointer;
    box-shadow: 0 30px 60px -36px #000;
    transition:
        border-color 0.3s ease,
        box-shadow 0.45s ease,
        transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ws-thumb.is-169 {
    aspect-ratio: 16 / 9;
}
.ws-thumb.is-feature {
    aspect-ratio: 21 / 9;
}
.ws-thumb.is-45 {
    aspect-ratio: 16 / 9;
}
.ws-thumb.is-916 {
    aspect-ratio: 16 / 9;
}
.ws-thumb.is-11 {
    aspect-ratio: 16 / 9;
}
.ws-thumb-media {
    position: absolute;
    inset: 0;
    height: 100%;
    width: 100%;
    object-fit: cover;
    transition: transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
/* Non-16:9 sources are shown pillarboxed inside the widescreen frame */
.ws-thumb.is-45 .ws-thumb-media,
.ws-thumb.is-916 .ws-thumb-media,
.ws-thumb.is-11 .ws-thumb-media {
    inset: 0 auto 0 50%;
    width: auto;
    transform: translateX(-50%);
    box-shadow: 0 0 0 100vmax color-mix(in srgb, #000 55%, transparent);
}
.ws-thumb.is-45 .ws-thumb-media {
    aspect-ratio: 4 / 5;
}
.ws-thumb.is-916 .ws-thumb-media {
    aspect-ratio: 9 / 16;
}
.ws-thumb.is-11 .ws-thumb-media {
    aspect-ratio: 1 / 1;
}
.ws-project:hover .ws-thumb {
    border-color: var(--accent);
    transform: translateY(-4px);
    box-shadow:
        0 36px 70px -34px #000,
        0 0 60px -26px var(--accent);
}
.ws-project:hover .ws-thumb:is(.is-169, .is-feature) .ws-thumb-media {
    transform: scale(1.05);
}
.ws-tone-0 {
    background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 35%, #000));
    color: var(--accent-ink);
}
.ws-tone-1 {
    background: linear-gradient(135deg, var(--surface), var(--soft));
    color: var(--ink);
}
.ws-tone-2 {
    background: var(--ink);
    color: var(--paper);
}
.ws-thumb-shade {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    background: linear-gradient(to top, color-mix(in srgb, #000 75%, transparent), transparent 55%);
    opacity: 0.8;
    transition: opacity 0.4s ease;
}
.ws-project:hover .ws-thumb-shade {
    opacity: 1;
}
.ws-ratio,
.ws-duration,
.ws-flag {
    position: absolute;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 0.3rem;
    padding: 0.22rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 500;
    letter-spacing: 0.08em;
}
.ws-ratio {
    left: 0.75rem;
    top: 0.75rem;
    border: 1px solid color-mix(in srgb, #fff 30%, transparent);
    background: color-mix(in srgb, #000 60%, transparent);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    color: #fff;
}
.ws-flag {
    left: 0.75rem;
    bottom: 0.75rem;
    background: var(--accent);
    color: var(--accent-ink);
}
.ws-duration {
    right: 0.75rem;
    bottom: 0.75rem;
    background: color-mix(in srgb, #000 70%, transparent);
    color: #fff;
}
.ws-thumb-play {
    position: absolute;
    z-index: 3;
    left: 50%;
    top: 50%;
    display: inline-flex;
    height: 3.75rem;
    width: 3.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-ink);
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.7);
    box-shadow: 0 0 0 6px color-mix(in srgb, var(--accent) 30%, transparent), 0 16px 40px -10px var(--accent);
    transition:
        opacity 0.35s ease,
        transform 0.5s cubic-bezier(0.3, 1.5, 0.4, 1);
}
.ws-project:hover .ws-thumb-play,
.ws-thumb:focus-visible .ws-thumb-play {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
}
.ws-scrub {
    position: absolute;
    z-index: 3;
    inset: auto 0 0 0;
    height: 3px;
    background: color-mix(in srgb, #fff 18%, transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}
.ws-scrub i {
    display: block;
    height: 100%;
    background: var(--accent);
    box-shadow: 0 0 10px var(--accent);
}
.ws-project:hover .ws-scrub {
    opacity: 1;
}
.ws-underline {
    background: linear-gradient(currentColor, currentColor) left 96% / 0% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ws-project:hover .ws-underline {
    background-size: 100% 2px;
}

/* ---------- Services (post-production suite) ---------- */
.ws-menu {
    display: grid;
    align-content: start;
    gap: 0.5rem;
}
.ws-menu-item {
    position: relative;
    display: flex;
    width: 100%;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--line);
    border-radius: 0.5rem;
    background: var(--surface);
    padding: 0.9rem 1.1rem;
    cursor: pointer;
    text-align: left;
    transition:
        border-color 0.3s ease,
        background-color 0.3s ease,
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1),
        box-shadow 0.4s ease;
}
.ws-menu-item::before {
    content: '';
    position: absolute;
    left: -1px;
    top: 18%;
    bottom: 18%;
    width: 3px;
    border-radius: 3px;
    background: var(--accent);
    transform: scaleY(0);
    transition: transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ws-menu-item:hover,
.ws-menu-item[aria-selected='true'] {
    border-color: color-mix(in srgb, var(--accent) 60%, var(--line));
    transform: translateX(6px);
    box-shadow: 0 18px 36px -26px var(--accent);
}
.ws-menu-item[aria-selected='true']::before {
    transform: scaleY(1);
}
.ws-menu-num {
    font-size: 0.7rem;
    color: var(--muted);
}
.ws-menu-title {
    text-transform: uppercase;
    letter-spacing: 0.02em;
    transition: color 0.3s ease;
}
.ws-menu-item[aria-selected='true'] .ws-menu-title,
.ws-menu-item:hover .ws-menu-title {
    color: var(--accent);
}
.ws-menu-arrow {
    color: var(--accent);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem);
    transition:
        opacity 0.3s ease,
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ws-menu-item[aria-selected='true'] .ws-menu-arrow,
.ws-menu-item:hover .ws-menu-arrow {
    opacity: 1;
    transform: none;
}
.ws-monitor {
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 0.7rem;
    background: var(--surface);
    box-shadow:
        0 40px 80px -40px #000,
        0 0 60px -34px var(--accent);
}
.ws-monitor-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 1px solid var(--line);
    background: var(--soft);
    padding: 0.7rem 1rem;
}
.ws-compare {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: #000;
    isolation: isolate;
}
.ws-compare-img {
    position: absolute;
    inset: 0;
    height: 100%;
    width: 100%;
    object-fit: cover;
}
.ws-compare-before {
    position: absolute;
    inset: 0;
    z-index: 1;
}
.ws-before-filter {
    filter: grayscale(0.85) contrast(0.85) brightness(0.9);
}
.ws-compare-handle {
    position: absolute;
    z-index: 3;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--accent);
    box-shadow: 0 0 14px var(--accent);
    transform: translateX(-1px);
    pointer-events: none;
}
.ws-compare-handle :deep(svg) {
    position: absolute;
    left: 50%;
    top: 50%;
    height: 2.4rem;
    width: 2.4rem;
    transform: translate(-50%, -50%) rotate(45deg);
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-ink);
    padding: 0.6rem;
}
.ws-compare-range {
    position: absolute;
    inset: 0;
    z-index: 4;
    height: 100%;
    width: 100%;
    cursor: ew-resize;
    opacity: 0;
}
.ws-compare-fallback {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(60% 80% at 50% 50%, color-mix(in srgb, var(--accent) 22%, transparent), transparent 70%),
        var(--paper);
}
.ws-monitor-foot {
    min-height: 4.5rem;
    padding: 1.1rem 1.25rem 1.3rem;
}

/* ---------- Contact ---------- */
.ws-contact {
    position: relative;
    overflow: hidden;
    border-top: 1px solid var(--line);
    background: var(--soft);
}
.ws-contact-glow {
    position: absolute;
    left: 50%;
    top: -12rem;
    height: 28rem;
    width: 60rem;
    max-width: 100%;
    transform: translateX(-50%);
    border-radius: 999px;
    background: var(--accent);
    filter: blur(110px);
    opacity: 0.25;
    pointer-events: none;
    animation: ws-breathe 8s ease-in-out infinite;
}
.ws-underline-contact {
    background: linear-gradient(currentColor, currentColor) left bottom / 100% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 4px;
}
.ws-bigmail:hover .ws-underline-contact {
    background-size: 0% 2px;
    background-position: right bottom;
}
.ws-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.ws-contact-link:hover {
    text-decoration: underline;
    text-underline-offset: 4px;
}
.ws-social {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid var(--line);
    border-radius: 0.35rem;
    background: var(--surface);
    padding: 0.5rem 0.95rem;
    font-family: 'IBM Plex Mono', ui-monospace, monospace;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        border-color 0.3s ease,
        transform 0.3s ease;
}
.ws-social:hover {
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
    transform: translateY(-2px);
}
.ws-form {
    border: 1px solid var(--line);
    border-radius: 0.7rem;
    background: color-mix(in srgb, var(--surface) 88%, transparent);
    -webkit-backdrop-filter: blur(14px);
    backdrop-filter: blur(14px);
    box-shadow:
        0 40px 80px -44px #000,
        0 0 60px -36px var(--accent);
}
.ws-input {
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
.ws-input:hover {
    border-color: var(--muted);
}
.ws-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 1px 0 var(--accent);
}
.ws-sent {
    animation: ws-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.ws-js [data-reveal] {
    opacity: 0;
    transform: translateY(24px);
    transition:
        opacity 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.ws-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}

/* ---------- Section animations ---------- */
.ws-js [data-reveal].is-in .ws-frame {
    animation: ws-frame-in 1.1s cubic-bezier(0.7, 0, 0.2, 1) backwards;
}
.ws-js [data-reveal]:not(.is-in) .ws-hl {
    opacity: 0;
}
.ws-js [data-reveal].is-in .ws-hl {
    animation: ws-slide-x 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--k, 0) * 90ms + 150ms) backwards;
}
.ws-js [data-reveal].is-in .ws-hl .ws-tick {
    animation: ws-tick 0.6s cubic-bezier(0.3, 1.6, 0.4, 1) calc(var(--k, 0) * 90ms + 450ms) backwards;
}
.ws-js [data-reveal].is-in .ws-skill .ws-tile {
    animation: ws-tile 0.7s cubic-bezier(0.3, 1.5, 0.4, 1) calc(var(--d, 0ms) + 0.15s) backwards;
}
.ws-js [data-reveal].is-in .ws-thumb {
    animation: ws-wipe 1.2s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}
.ws-js [data-reveal].is-in .ws-thumb-media {
    animation: ws-settle 1.6s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s backwards;
}
.ws-js [data-reveal].is-in .ws-menu-item {
    animation: ws-slide-x 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) backwards;
}

@keyframes ws-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes ws-enter {
    from {
        opacity: 0;
    }
}
@keyframes ws-ping {
    75%,
    100% {
        transform: scale(1.7);
        opacity: 0;
    }
}
@keyframes ws-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}
@keyframes ws-blink {
    50% {
        opacity: 0.35;
    }
}
@keyframes ws-breathe {
    50% {
        opacity: 0.7;
        transform: scale(1.06);
    }
}
@keyframes ws-wave {
    0%,
    100% {
        transform: scaleY(0.25);
    }
    50% {
        transform: scaleY(1);
    }
}
@keyframes ws-timeline {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}
@keyframes ws-knob {
    from {
        transform: translate(50%, -50%) scale(0);
    }
}
@keyframes ws-count {
    to {
        --n: var(--p);
    }
}
@keyframes ws-frame-in {
    from {
        clip-path: inset(0 0 100% 0);
    }
}
@keyframes ws-slide-x {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
}
@keyframes ws-tick {
    from {
        transform: scale(0) rotate(-90deg);
    }
}
@keyframes ws-tile {
    from {
        transform: scale(0.4) rotate(-20deg);
        opacity: 0;
    }
}
@keyframes ws-wipe {
    from {
        clip-path: inset(0 0 100% 0 round 0.6rem);
    }
}
@keyframes ws-settle {
    from {
        transform: scale(1.22);
    }
}
.ws-js.ws-root {
    animation: ws-enter 0.7s ease both;
}

/* ---------- Reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    .ws-root *,
    .ws-root *::before,
    .ws-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .ws-js .ws-word,
    .ws-js [data-reveal],
    .ws-js [data-reveal]:not(.is-in) .ws-hl {
        opacity: 1;
        transform: none;
    }
    .ws-js [data-reveal] .ws-count {
        --n: var(--p, 0);
    }
    .ws-js [data-reveal] .ws-bar {
        width: var(--w, 0%);
    }
    .ws-js [data-reveal]:not(.is-in) .ws-kicker::before {
        transform: none;
    }
    .ws-timeline i {
        transform: scaleX(0.4);
    }
    .ws-wave i {
        transform: scaleY(0.6);
    }
    .ws-menu-item:hover,
    .ws-menu-item[aria-selected='true'],
    .ws-project:hover .ws-thumb {
        transform: none;
    }
}
</style>