<script setup lang="ts">
/**
 * Tidepool — quickFolio template
 * Slug suggestion: `tidepool`
 *
 * Deep-pine and sea-glass surfaces with a single raspberry accent, wide Syne display type,
 * leaf-shaped portrait, bento skills, staggered project grid. One orchestrated hero load
 * sequence, restrained scroll reveals. Light + dark via Tailwind `dark:` (palette in CSS variables).
 * Every visible value comes from the seeded field keys.
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
        content?: Dict | any[];
        sections?: Dict | any[];
        designSettings?: Dict | null;
        enabled?: Record<string, boolean>;
        googleForm?: GoogleFormConfig | null;
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
const logoImage = computed(() => (flag('navbar', 'show_logo', true) ? media('navbar', 'logo_image') : undefined));
const stickyNav = computed(() => flag('navbar', 'sticky_nav', true));
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
const heroPrimary = computed(() => (text('header', 'hero_cta_primary_text') && text('header', 'hero_cta_primary_url') ? { label: text('header', 'hero_cta_primary_text')!, url: text('header', 'hero_cta_primary_url')! } : null));
const heroSecondary = computed(() => (text('header', 'hero_cta_secondary_text') && text('header', 'hero_cta_secondary_url') ? { label: text('header', 'hero_cta_secondary_text')!, url: text('header', 'hero_cta_secondary_url')! } : null));

// About
const highlights = computed(() =>
    arr('about', 'about_highlights')
        .map((h) =>
            typeof h === 'string'
                ? { title: clean(h) ?? '', description: undefined as string | undefined }
                : { title: clean(h?.title ?? h?.value ?? h?.label ?? h?.text ?? h?.name) ?? '', description: clean(h?.description ?? h?.label) },
        )
        .filter((h) => h.title),
);
const cvFile = computed(() => media('about', 'about_cv_file'));
const aboutCta = computed(() => (text('about', 'about_cta_text') && text('about', 'about_cta_url') ? { label: text('about', 'about_cta_text')!, url: text('about', 'about_cta_url')! } : null));

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
const isGlyph = (name?: string) => !!name && !iconFor(name) && [...name].length <= 3;
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
/* Google Form contact                                                 */
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
        class="ts-root [--paper:#ECF2EE] [--soft:#DDE8E2] [--surface:#F8FBF9] [--ink:#0D2B26] [--muted:#0D2B26B3] [--line:#0D2B2624] [--accent:#D6336C] [--accent-ink:#FFF5F8] selection:bg-[#D6336C] selection:text-white dark:[--paper:#0A1D1A] dark:[--soft:#0F2723] dark:[--surface:#143029] dark:[--ink:#E6F3EC] dark:[--muted:#E6F3ECB0] dark:[--line:#E6F3EC26] dark:[--accent:#FF8DB0] dark:[--accent-ink:#0A1D1A]"
        :class="{ 'ts-js': ready }"
    >
        <!-- ============================ NAVBAR ============================ -->
        <header class="inset-x-0 top-0 z-50" :class="stickyNav ? 'fixed' : 'absolute'">
            <div ref="progressEl" class="ts-progress" aria-hidden="true" />
            <nav ref="navEl" class="ts-nav" aria-label="Primary">
                <div class="mx-auto flex h-16 max-w-[1240px] items-center justify-between gap-4 px-5 sm:px-8">
                    <a v-if="logoImage || logoText" href="#header" class="flex min-w-0 items-center gap-3" @click="onNavClick($event, '#header')">
                        <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-8 w-auto max-w-[8rem] object-contain" />
                        <span v-if="logoText" class="ts-display truncate text-lg">{{ logoText }}</span>
                    </a>
                    <span v-else />

                    <ul v-if="navLinks.length" class="hidden items-center gap-7 md:flex">
                        <li v-for="l in navLinks" :key="l.id">
                            <a :href="`#${l.id}`" class="ts-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a>
                        </li>
                    </ul>

                    <a v-if="has('contact')" href="#contact" class="ts-btn ts-btn-primary !px-5 !py-2.5 !text-sm" @click="onNavClick($event, '#contact')">
                        Contact me<ArrowUpRight class="ts-arrow h-4 w-4" />
                    </a>
                    <span v-else />
                </div>
            </nav>
        </header>

        <!-- ============================== HERO ============================== -->
        <section v-if="has('header')" id="header" class="relative isolate flex min-h-[100svh] items-center overflow-hidden">
            <img
                v-if="media('header', 'hero_background_image')"
                :src="media('header', 'hero_background_image')"
                alt=""
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-[0.12] dark:opacity-[0.18]"
            />
            <div class="ts-wash -z-10" aria-hidden="true" />

            <div class="mx-auto grid w-full max-w-[1240px] items-center gap-14 px-5 pb-20 pt-32 sm:px-8 lg:grid-cols-12 lg:gap-8">
                <div class="lg:col-span-7">
                    <p v-if="text('header', 'hero_greeting')" class="ts-load ts-greeting" style="--d: 0ms">
                        <span class="ts-pulse" aria-hidden="true" />{{ text('header', 'hero_greeting') }}
                    </p>

                    <h1 v-if="heroName" class="ts-display ts-hero-name mt-6">
                        <span class="sr-only">{{ heroName }}</span>
                        <span v-for="(w, i) in heroWords" :key="i" class="ts-mask" aria-hidden="true">
                            <span class="ts-word" :style="{ '--i': i }">{{ w }}</span>
                        </span>
                    </h1>

                    <p v-if="text('header', 'hero_role')" class="ts-load ts-display mt-6 text-2xl text-[var(--accent)] sm:text-3xl" style="--d: 520ms">
                        {{ text('header', 'hero_role') }}
                    </p>

                    <p v-if="text('header', 'hero_description')" class="ts-load ts-muted mt-6 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7]" style="--d: 620ms">
                        {{ text('header', 'hero_description') }}
                    </p>

                    <div v-if="heroPrimary || heroSecondary" class="ts-load mt-9 flex flex-wrap gap-3" style="--d: 720ms">
                        <a v-if="heroPrimary" :href="heroPrimary.url" class="ts-btn ts-btn-primary" @click="onNavClick($event, heroPrimary.url)">
                            {{ heroPrimary.label }}<ArrowUpRight class="ts-arrow h-4 w-4" />
                        </a>
                        <a v-if="heroSecondary" :href="heroSecondary.url" class="ts-btn ts-btn-ghost" @click="onNavClick($event, heroSecondary.url)">
                            {{ heroSecondary.label }}
                        </a>
                    </div>

                    <ul v-if="heroSocials.length" class="ts-load mt-9 flex flex-wrap gap-2" style="--d: 800ms">
                        <li v-for="(s, i) in heroSocials" :key="i">
                            <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="ts-icon-btn">
                                <component :is="socialIconFor(s.platform)" class="h-[18px] w-[18px]" />
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="lg:col-span-5">
                    <figure class="ts-load ts-portrait relative mx-auto w-[78%] max-w-[26rem] sm:w-full" style="--d: 300ms">
                        <div class="ts-shape" aria-hidden="true" />
                        <div class="ts-photo group">
                            <img
                                v-if="media('header', 'hero_image')"
                                :src="media('header', 'hero_image')"
                                :alt="heroName ?? ''"
                                class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.05]"
                            />
                            <span v-else-if="heroInitials" class="ts-display flex h-full w-full items-center justify-center bg-[var(--soft)] text-[7rem] text-[var(--accent)]">{{ heroInitials }}</span>
                        </div>
                        <span class="ts-orbit" aria-hidden="true"><Sparkles class="h-5 w-5" /></span>
                        <figcaption v-if="location" class="ts-badge"><MapPin class="h-4 w-4 text-[var(--accent)]" />{{ location }}</figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- ============================== ABOUT ============================== -->
        <section v-if="has('about')" id="about" class="ts-band scroll-mt-4">
            <div class="mx-auto grid w-full max-w-[1240px] items-center gap-14 px-5 py-28 sm:px-8 lg:grid-cols-12 lg:gap-20">
                <div v-if="media('about', 'about_image')" data-reveal class="lg:col-span-5">
                    <div class="ts-frame mx-auto max-w-md">
                        <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="aspect-[4/5] w-full object-cover" loading="lazy" />
                    </div>
                </div>

                <div :class="media('about', 'about_image') ? 'lg:col-span-7' : 'lg:col-span-10'">
                    <div data-reveal>
                        <h2 v-if="text('about', 'about_title')" class="ts-display ts-h2">{{ text('about', 'about_title') }}</h2>
                        <p v-if="text('about', 'about_subtitle')" class="ts-lead mt-4">{{ text('about', 'about_subtitle') }}</p>
                        <div v-if="text('about', 'about_description')" class="ts-muted ts-prose mt-7 max-w-[38rem] whitespace-pre-line text-lg leading-[1.75]">{{ text('about', 'about_description') }}</div>
                    </div>

                    <ul v-if="highlights.length" class="mt-10 grid gap-4 sm:grid-cols-2">
                        <li v-for="(h, i) in highlights" :key="i" data-reveal :style="{ '--d': `${(i % 4) * 70}ms` }" class="ts-stat">
                            <span class="ts-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>
                            <span class="ts-display mt-4 block text-xl leading-tight">{{ h.title }}</span>
                            <span v-if="h.description" class="ts-muted mt-2 block text-[0.95rem] leading-relaxed">{{ h.description }}</span>
                        </li>
                    </ul>

                    <div v-if="cvFile || aboutCta" data-reveal class="mt-10 flex flex-wrap gap-3">
                        <a v-if="cvFile" :href="cvFile" download target="_blank" rel="noopener" class="ts-btn ts-btn-primary"><Download class="h-4 w-4" />Download CV</a>
                        <a
                            v-if="aboutCta"
                            :href="aboutCta.url"
                            class="ts-btn"
                            :class="cvFile ? 'ts-btn-ghost' : 'ts-btn-primary'"
                            @click="onNavClick($event, aboutCta.url)"
                        >
                            {{ aboutCta.label }}<ArrowUpRight class="ts-arrow h-4 w-4" />
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
                        <h2 v-if="text('journey', 'journey_title')" class="ts-display ts-h2">{{ text('journey', 'journey_title') }}</h2>
                        <p v-if="text('journey', 'journey_subtitle')" class="ts-lead mt-4">{{ text('journey', 'journey_subtitle') }}</p>
                    </div>
                </div>

                <ol class="ts-timeline lg:col-span-8">
                    <li v-for="(item, index) in journey" :key="index" data-reveal :style="{ '--d': `${(index % 4) * 70}ms` }" class="ts-tl-item">
                        <p v-if="item.period" class="text-sm font-semibold text-[var(--accent)]">{{ item.period }}</p>
                        <h3 v-if="item.title" class="ts-display mt-1.5 text-2xl">{{ item.title }}</h3>
                        <p v-if="item.company" class="mt-1 font-medium">{{ item.company }}</p>
                        <p v-if="item.description" class="ts-muted mt-3 max-w-[38rem] whitespace-pre-line leading-[1.7]">{{ item.description }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ============================== SKILLS ============================== -->
        <section v-if="has('skills')" id="skills" class="ts-band scroll-mt-4">
            <div class="mx-auto w-full max-w-[1240px] px-5 py-28 sm:px-8">
                <div data-reveal class="mb-14 grid gap-6 lg:grid-cols-12 lg:items-end">
                    <div class="lg:col-span-7">
                        <h2 v-if="text('skills', 'skills_title')" class="ts-display ts-h2">{{ text('skills', 'skills_title') }}</h2>
                        <p v-if="text('skills', 'skills_subtitle')" class="ts-lead mt-4">{{ text('skills', 'skills_subtitle') }}</p>
                    </div>
                    <p v-if="text('skills', 'skills_description')" class="ts-muted whitespace-pre-line leading-[1.7] lg:col-span-5">{{ text('skills', 'skills_description') }}</p>
                </div>

                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="(s, i) in skills" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 50}ms` }" class="ts-skill">
                        <div class="flex items-center gap-3.5">
                            <span v-if="flag('skills', 'skills_show_icon', true)" class="ts-tile">
                                <component :is="iconFor(s.icon)" v-if="iconFor(s.icon)" class="h-[18px] w-[18px]" />
                                <span v-else-if="isGlyph(s.icon)" class="text-lg leading-none" aria-hidden="true">{{ s.icon }}</span>
                                <span v-else class="ts-display text-base leading-none" aria-hidden="true">{{ s.name.charAt(0).toUpperCase() }}</span>
                            </span>
                            <h3 class="font-semibold">{{ s.name }}</h3>
                            <span v-if="flag('skills', 'skills_show_level', true) && s.pct !== null" class="ts-muted ml-auto text-sm font-semibold tabular-nums">{{ Math.round(s.pct) }}%</span>
                            <span v-else-if="flag('skills', 'skills_show_level', true) && s.label" class="ts-pill ml-auto">{{ s.label }}</span>
                        </div>
                        <div
                            v-if="flag('skills', 'skills_show_level', true) && s.pct !== null"
                            class="ts-track mt-5"
                            role="progressbar"
                            :aria-valuenow="Math.round(s.pct)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="s.name"
                        >
                            <div class="ts-bar" :style="{ '--w': `${s.pct}%` }" />
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
                        <h2 v-if="text('projects', 'projects_title')" class="ts-display ts-h2">{{ text('projects', 'projects_title') }}</h2>
                        <p v-if="text('projects', 'projects_subtitle')" class="ts-lead mt-4">{{ text('projects', 'projects_subtitle') }}</p>
                    </div>
                    <p v-if="text('projects', 'projects_description')" class="ts-muted whitespace-pre-line leading-[1.7] lg:col-span-5">{{ text('projects', 'projects_description') }}</p>
                </div>

                <ul class="grid gap-x-8 gap-y-14 md:grid-cols-2">
                    <li
                        v-for="(p, i) in projects"
                        :key="i"
                        data-reveal
                        :class="[featureFirst && i === 0 ? 'md:col-span-2' : '', !(featureFirst && i === 0) && i % 2 === 1 ? 'md:mt-16' : '']"
                        :style="{ '--d': `${(i % 2) * 80}ms` }"
                    >
                        <article class="ts-project group flex h-full flex-col" :class="featureFirst && i === 0 ? 'lg:flex-row lg:items-stretch lg:gap-10' : ''">
                            <component
                                :is="p.live ? 'a' : 'div'"
                                :href="p.live || undefined"
                                :target="p.live ? '_blank' : undefined"
                                :rel="p.live ? 'noopener noreferrer' : undefined"
                                :aria-label="p.live ? `Open ${p.title}` : undefined"
                                class="ts-media relative block overflow-hidden"
                                :class="featureFirst && i === 0 ? 'aspect-[16/10] lg:w-[58%] lg:shrink-0' : 'aspect-[4/3]'"
                            >
                                <img v-if="p.image" :src="p.image" :alt="p.title" class="h-full w-full object-cover" loading="lazy" />
                                <div v-else class="ts-display ts-tone flex h-full w-full items-center justify-center text-8xl" :class="`ts-tone-${i % 3}`">
                                    {{ p.title.charAt(0).toUpperCase() }}
                                </div>
                                <span v-if="featureFirst && i === 0" class="ts-flag">Featured</span>
                                <span v-if="p.live" class="ts-go" aria-hidden="true"><ArrowUpRight class="h-5 w-5" /></span>
                            </component>

                            <div class="flex flex-1 flex-col pt-5" :class="featureFirst && i === 0 ? 'lg:justify-center lg:pt-0' : ''">
                                <h3 class="ts-display text-2xl leading-tight" :class="featureFirst && i === 0 ? 'lg:text-4xl' : ''"><span class="ts-underline">{{ p.title }}</span></h3>
                                <p v-if="p.description" class="ts-muted mt-3 leading-[1.65]">{{ p.description }}</p>
                                <ul v-if="p.tags.length" class="mt-4 flex flex-wrap gap-2">
                                    <li v-for="(t, ti) in p.tags" :key="ti" class="ts-pill">{{ t }}</li>
                                </ul>
                                <div v-if="p.repo" class="mt-auto pt-5">
                                    <a :href="p.repo" target="_blank" rel="noopener noreferrer" class="ts-textlink"><Code class="h-4 w-4" />Source code</a>
                                </div>
                            </div>
                        </article>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== SERVICES ============================== -->
        <section v-if="has('services')" id="services" class="ts-band scroll-mt-4">
            <div class="mx-auto w-full max-w-[1240px] px-5 py-28 sm:px-8">
                <div data-reveal class="mb-14 max-w-3xl">
                    <h2 v-if="text('services', 'services_title')" class="ts-display ts-h2">{{ text('services', 'services_title') }}</h2>
                    <p v-if="text('services', 'services_subtitle')" class="ts-lead mt-4 whitespace-pre-line">{{ text('services', 'services_subtitle') }}</p>
                </div>

                <ul class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <li v-for="(s, i) in services" :key="i" data-reveal :style="{ '--d': `${(i % 3) * 70}ms` }">
                        <div class="ts-service group h-full">
                            <span class="ts-tile ts-tile-lg">
                                <component :is="iconFor(s.icon) ?? Sparkles" v-if="!isGlyph(s.icon)" class="h-5 w-5" />
                                <span v-else class="text-xl leading-none" aria-hidden="true">{{ s.icon }}</span>
                            </span>
                            <h3 class="ts-display mt-8 text-2xl leading-tight">{{ s.title }}</h3>
                            <p v-if="s.description" class="ts-muted mt-3 leading-[1.65]">{{ s.description }}</p>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== CONTACT ============================== -->
        <section v-if="has('contact')" id="contact" class="scroll-mt-4 px-3 pb-6 pt-24 sm:px-6">
            <div data-reveal class="ts-contact mx-auto max-w-[1240px]">
                <div class="relative z-10 grid gap-14 p-7 sm:p-12 lg:grid-cols-12 lg:gap-12 lg:p-16">
                    <div class="lg:col-span-7">
                        <h2 v-if="text('contact', 'contact_title')" class="ts-display text-[clamp(2.75rem,7.5vw,6rem)] leading-[0.95] [overflow-wrap:anywhere]">
                            {{ text('contact', 'contact_title') }}
                        </h2>
                        <p v-if="text('contact', 'contact_subtitle')" class="mt-5 text-xl font-medium text-[var(--accent)]">{{ text('contact', 'contact_subtitle') }}</p>
                        <p v-if="text('contact', 'contact_description')" class="mt-5 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7] opacity-80">{{ text('contact', 'contact_description') }}</p>

                        <div v-if="email || phone || location" class="mt-10 space-y-5">
                            <a v-if="email" :href="`mailto:${email}`" class="ts-bigmail ts-display group inline-flex max-w-full items-center gap-3 break-all text-2xl sm:text-4xl">
                                <Mail class="h-7 w-7 shrink-0 text-[var(--accent)]" /><span class="ts-underline-contact">{{ email }}</span>
                                <ArrowUpRight class="ts-arrow h-7 w-7 shrink-0" />
                            </a>
                            <div class="flex flex-wrap gap-x-8 gap-y-3 font-medium">
                                <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="ts-contact-link"><Phone class="h-4 w-4" />{{ phone }}</a>
                                <span v-if="location" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4" />{{ location }}</span>
                            </div>
                        </div>

                        <ul v-if="contactSocials.length" class="mt-8 flex flex-wrap gap-2">
                            <li v-for="(s, i) in contactSocials" :key="i">
                                <a :href="s.url" target="_blank" rel="noopener noreferrer" class="ts-social">{{ s.label }}<ArrowUpRight class="ts-arrow h-3.5 w-3.5" /></a>
                            </li>
                        </ul>
                    </div>

                    <div class="lg:col-span-5">
                        <form v-if="formReady" :action="formAction" method="POST" target="ts-gform-frame" class="ts-form space-y-5 p-6 sm:p-8" @submit="onSubmit">
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold">Name</span>
                                <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="ts-input" />
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold">Email</span>
                                <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="ts-input" />
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold">Message</span>
                                <textarea :name="`entry.${gf.message}`" rows="5" required class="ts-input resize-y" />
                            </label>
                            <button type="submit" class="ts-btn ts-btn-primary w-full justify-center" :disabled="sending">
                                {{ sending ? 'Sending…' : 'Send message' }}<Send class="ts-arrow h-4 w-4" />
                            </button>
                            <p v-if="sent" role="status" class="ts-sent flex items-center gap-2 text-sm font-semibold">
                                <span class="ts-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>Your message was submitted.
                            </p>
                        </form>
                        <a v-else-if="email" :href="`mailto:${email}`" class="ts-btn ts-btn-onink w-full justify-center !py-5 text-lg"><Mail class="h-5 w-5" />{{ email }}</a>
                        <iframe name="ts-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                    </div>
                </div>
            </div>

            <footer v-if="heroName || logoText" class="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-2 px-2 py-8 text-sm">
                <span class="ts-muted">&copy; {{ year }} {{ heroName ?? logoText }}</span>
                <a href="#header" class="ts-textlink" @click="onNavClick($event, '#header')">Back to top<ArrowUpRight class="ts-arrow h-4 w-4 -rotate-45" /></a>
            </footer>
        </section>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600..800&family=Instrument+Sans:wght@400;500;600;700&display=swap');

/* ---------- Foundations ---------- */
.ts-root {
    background: var(--paper);
    color: var(--ink);
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    transition:
        background-color 0.4s ease,
        color 0.4s ease;
}
.ts-display {
    font-family: 'Syne', 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
    font-weight: 700;
    letter-spacing: -0.03em;
}
.ts-muted {
    color: var(--muted);
}
.ts-h2 {
    font-size: clamp(2.25rem, 5vw, 4rem);
    line-height: 1.02;
    letter-spacing: -0.04em;
    text-wrap: balance;
}
.ts-lead {
    max-width: 34rem;
    font-size: 1.2rem;
    line-height: 1.5;
    font-weight: 500;
    color: var(--accent);
}
.ts-band {
    background: var(--soft);
}
.ts-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
    border-radius: 0.5rem;
}
.ts-wash {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(60rem 36rem at 88% 8%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 70%),
        radial-gradient(40rem 30rem at 0% 100%, color-mix(in srgb, var(--soft) 90%, transparent), transparent 70%);
    pointer-events: none;
}

/* ---------- Navbar ---------- */
.ts-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 3px;
    background: var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
    z-index: 60;
}
.ts-nav {
    border-bottom: 1px solid transparent;
    transition:
        background-color 0.35s ease,
        border-color 0.35s ease,
        backdrop-filter 0.35s ease;
}
.ts-nav.is-scrolled {
    background: color-mix(in srgb, var(--paper) 84%, transparent);
    border-color: var(--line);
    backdrop-filter: blur(14px) saturate(1.3);
    -webkit-backdrop-filter: blur(14px) saturate(1.3);
}
.ts-navlink {
    position: relative;
    display: inline-block;
    padding: 0.4rem 0;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--muted);
    transition: color 0.25s ease;
}
.ts-navlink::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: -2px;
    height: 5px;
    width: 5px;
    border-radius: 999px;
    background: var(--accent);
    transform: translateX(-50%) scale(0);
    transition: transform 0.35s cubic-bezier(0.3, 1.5, 0.4, 1);
}
.ts-navlink:hover,
.ts-navlink[data-active='true'] {
    color: var(--ink);
}
.ts-navlink[data-active='true']::after {
    transform: translateX(-50%) scale(1);
}

/* ---------- Buttons & small controls ---------- */
.ts-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.9rem;
    padding: 0.85rem 1.5rem;
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.2;
    cursor: pointer;
    border: 1px solid transparent;
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.25s ease,
        border-color 0.3s ease;
}
.ts-btn:hover {
    transform: translateY(-2px);
}
.ts-btn:active {
    transform: translateY(0) scale(0.98);
}
.ts-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.ts-btn-primary {
    background: var(--accent);
    color: var(--accent-ink);
}
.ts-btn-primary:hover {
    background: var(--ink);
    color: var(--paper);
}
.ts-btn-ghost {
    border-color: var(--line);
    color: var(--ink);
    background: transparent;
}
.ts-btn-ghost:hover {
    border-color: var(--ink);
    background: var(--surface);
}
.ts-btn-onink {
    background: var(--accent);
    color: var(--accent-ink);
}
.ts-arrow {
    transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ts-btn:hover .ts-arrow,
.ts-bigmail:hover .ts-arrow,
.ts-social:hover .ts-arrow,
.ts-textlink:hover .ts-arrow {
    transform: translate(3px, -3px);
}
.ts-icon-btn {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    border: 1px solid var(--line);
    background: var(--surface);
    transition:
        transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1),
        background-color 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease;
}
.ts-icon-btn:hover {
    transform: translateY(-3px);
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
}
.ts-greeting {
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
.ts-pulse {
    position: relative;
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 999px;
    background: var(--accent);
}
.ts-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: inherit;
    animation: ts-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.ts-pill {
    display: inline-block;
    border-radius: 0.5rem;
    border: 1px solid var(--line);
    padding: 0.2rem 0.65rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--muted);
}
.ts-tick {
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
.ts-textlink {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    background: linear-gradient(currentColor, currentColor) left bottom / 0% 1.5px no-repeat;
    transition: background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 2px;
}
.ts-textlink:hover {
    background-size: 100% 1.5px;
}

/* ---------- Hero (one orchestrated load sequence) ---------- */
.ts-hero-name {
    font-size: clamp(3rem, 10vw, 8.5rem);
    line-height: 0.92;
    letter-spacing: -0.05em;
    overflow-wrap: anywhere;
}
.ts-mask {
    display: inline-block;
    overflow: hidden;
    vertical-align: top;
    padding-bottom: 0.12em;
    margin-bottom: -0.12em;
    margin-right: 0.2em;
}
.ts-word {
    display: inline-block;
}
.ts-js .ts-word {
    transform: translateY(110%);
    animation: ts-rise 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: calc(0.15s + var(--i, 0) * 0.12s);
}
.ts-js .ts-load {
    opacity: 0;
    transform: translateY(18px);
    animation: ts-fade 0.9s cubic-bezier(0.2, 0.7, 0.2, 1) forwards;
    animation-delay: var(--d, 0ms);
}
.ts-portrait {
    aspect-ratio: 4 / 5;
}
.ts-shape {
    position: absolute;
    inset: 0;
    border-radius: 11rem 1.5rem 1.5rem 1.5rem;
    background: var(--accent);
    transform: translate(-1.1rem, 1.1rem) rotate(-3deg);
    transition: transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ts-portrait:hover .ts-shape {
    transform: translate(-0.5rem, 0.5rem) rotate(-1.5deg);
}
.ts-photo {
    position: absolute;
    inset: 0;
    overflow: hidden;
    border-radius: 11rem 1.5rem 1.5rem 1.5rem;
    border: 5px solid var(--paper);
    background: var(--soft);
}
.ts-orbit {
    position: absolute;
    top: 6%;
    right: -4%;
    display: inline-flex;
    height: 3.25rem;
    width: 3.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    color: var(--accent);
    animation: ts-float 5.5s ease-in-out infinite;
}
.ts-badge {
    position: absolute;
    bottom: -1.1rem;
    left: 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    border-radius: 0.9rem;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.55rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
}

/* ---------- About ---------- */
.ts-frame {
    position: relative;
    border-radius: 1.5rem 9rem 1.5rem 1.5rem;
    overflow: hidden;
    border: 1px solid var(--line);
    background: var(--surface);
    transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ts-frame:hover {
    transform: rotate(-1deg) scale(1.01);
}
.ts-stat {
    border-radius: 1.25rem;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 1.4rem;
    transition:
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease;
}
.ts-stat:hover {
    transform: translateY(-3px);
    border-color: var(--accent);
}

/* ---------- Journey (a true sequence) ---------- */
.ts-timeline {
    position: relative;
    margin-left: 0.5rem;
    border-left: 1.5px solid var(--line);
    padding-left: 2.25rem;
}
.ts-tl-item {
    position: relative;
    padding-bottom: 3rem;
}
.ts-tl-item:last-child {
    padding-bottom: 0;
}
.ts-tl-item::before {
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
.ts-js .ts-tl-item.is-in::before {
    background: var(--accent);
}
.ts-tl-item:hover::before {
    box-shadow: 0 0 0 6px color-mix(in srgb, var(--accent) 20%, transparent);
}

/* ---------- Skills ---------- */
.ts-skill {
    height: 100%;
    border-radius: 1.25rem;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 1.4rem;
    transition:
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease;
}
.ts-skill:hover {
    transform: translateY(-3px);
    border-color: var(--accent);
}
.ts-tile {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    background: var(--soft);
    color: var(--accent);
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ts-tile-lg {
    height: 3.25rem;
    width: 3.25rem;
    border-radius: 1rem;
}
.ts-skill:hover .ts-tile,
.ts-service:hover .ts-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.ts-track {
    height: 6px;
    width: 100%;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ink) 10%, transparent);
    overflow: hidden;
}
.ts-bar {
    height: 100%;
    width: var(--w, 0%);
    border-radius: inherit;
    background: var(--accent);
}
.ts-js [data-reveal] .ts-bar {
    width: 0;
    transition: width 1.4s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s);
}
.ts-js [data-reveal].is-in .ts-bar {
    width: var(--w, 0%);
}

/* ---------- Projects ---------- */
.ts-media {
    border-radius: 1.5rem;
    background: var(--soft);
    border: 1px solid var(--line);
    isolation: isolate;
}
.ts-media img,
.ts-tone {
    transition: transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ts-project:hover .ts-media img,
.ts-project:hover .ts-tone {
    transform: scale(1.05);
}
.ts-tone-0 {
    background: var(--accent);
    color: var(--accent-ink);
}
.ts-tone-1 {
    background: var(--surface);
    color: var(--accent);
}
.ts-tone-2 {
    background: var(--ink);
    color: var(--paper);
}
.ts-flag {
    position: absolute;
    left: 1rem;
    top: 1rem;
    border-radius: 0.6rem;
    background: var(--accent);
    color: var(--accent-ink);
    padding: 0.25rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 700;
}
.ts-go {
    position: absolute;
    right: 1rem;
    top: 1rem;
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    background: var(--paper);
    color: var(--ink);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem) scale(0.9);
    transition:
        opacity 0.35s ease,
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ts-project:hover .ts-go,
.ts-media:focus-visible .ts-go {
    opacity: 1;
    transform: none;
}
.ts-underline {
    background: linear-gradient(var(--accent), var(--accent)) left 96% / 0% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ts-project:hover .ts-underline {
    background-size: 100% 2px;
}

/* ---------- Services ---------- */
.ts-service {
    border-radius: 1.5rem;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 1.75rem;
    transition:
        transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1),
        border-color 0.3s ease;
}
.ts-service:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
}

/* ---------- Contact ---------- */
.ts-contact {
    position: relative;
    border-radius: 2.5rem;
    background: var(--ink);
    color: var(--paper);
    overflow: hidden;
}
.ts-contact::before {
    content: '';
    position: absolute;
    height: 26rem;
    width: 26rem;
    right: -8rem;
    top: -10rem;
    border-radius: 999px;
    background: var(--accent);
    opacity: 0.22;
    pointer-events: none;
    animation: ts-drift 16s ease-in-out infinite;
}
.ts-underline-contact {
    background: linear-gradient(currentColor, currentColor) left bottom / 100% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 4px;
}
.ts-bigmail:hover .ts-underline-contact {
    background-size: 0% 2px;
    background-position: right bottom;
}
.ts-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.9;
}
.ts-contact-link:hover {
    opacity: 1;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.ts-social {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 0.7rem;
    border: 1px solid color-mix(in srgb, currentColor 40%, transparent);
    padding: 0.45rem 1rem;
    font-size: 0.9rem;
    font-weight: 600;
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        border-color 0.3s ease,
        transform 0.3s ease;
}
.ts-social:hover {
    background: var(--paper);
    color: var(--ink);
    border-color: var(--paper);
    transform: translateY(-2px);
}
.ts-form {
    border-radius: 1.75rem;
    background: var(--surface);
    color: var(--ink);
}
.ts-input {
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
        box-shadow 0.25s ease;
}
.ts-input:hover {
    border-color: color-mix(in srgb, var(--ink) 35%, transparent);
}
.ts-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 22%, transparent);
}
.ts-sent {
    animation: ts-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.ts-js [data-reveal] {
    opacity: 0;
    transform: translateY(22px);
    transition:
        opacity 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.ts-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}
.ts-js .ts-tl-item[data-reveal] {
    transform: translateX(28px);
}
.ts-js .ts-tl-item[data-reveal].is-in {
    transform: none;
}
.ts-tl-item::after {
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
.ts-js .ts-tl-item.is-in::after {
    transform: scaleY(1);
}
.ts-tl-item:last-child::after {
    display: none;
}
.ts-js [data-reveal].is-in .ts-media {
    animation: ts-wipe 1.1s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}

@keyframes ts-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes ts-fade {
    to {
        opacity: 1;
        transform: none;
    }
}
@keyframes ts-float {
    50% {
        transform: translateY(-12px);
    }
}
@keyframes ts-ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}
@keyframes ts-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}
@keyframes ts-wipe {
    from {
        clip-path: inset(0 0 100% 0 round 1.5rem);
    }
}
@keyframes ts-drift {
    50% {
        transform: translate(-2.5rem, 2rem) scale(1.08);
    }
}

/* ---------- Reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    .ts-root *,
    .ts-root *::before,
    .ts-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .ts-js .ts-word,
    .ts-js .ts-load,
    .ts-js [data-reveal],
    .ts-js .ts-tl-item[data-reveal] {
        opacity: 1;
        transform: none;
    }
    .ts-js [data-reveal] .ts-bar {
        width: var(--w, 0%);
    }
    .ts-tl-item::after {
        transform: scaleY(1);
    }
    .ts-progress {
        transition: none;
    }
}
</style>
