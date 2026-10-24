<script setup lang="ts">
/**
 * Newsprint — quickFolio template
 * Slug suggestion: `newsprint`
 *
 * High-density editorial grid: 12-column layout held together by hairline rules, a centered
 * 10-column hero flanked by margin columns, asymmetric section pairings (4 / 8), and numbered
 * project dispatches. Cool newsprint + emerald ink. Light + dark via Tailwind `dark:`.
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

const pad = (n: number) => String(n + 1).padStart(2, '0');

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

// Journey + Skills share one row: 4 / 8 when both exist, full width otherwise
const journeySpan = computed(() => (has('skills') ? 'lg:col-span-4' : 'lg:col-span-12'));
const skillsSpan = computed(() => (has('journey') ? 'lg:col-span-8' : 'lg:col-span-12'));

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
// Masonry rhythm: 7/5, 5/7 pairs; a featured first dispatch spans the full width
const projectSpan = (i: number) => {
    if (featureFirst.value && i === 0) return 'lg:col-span-12';
    const j = featureFirst.value ? i - 1 : i;
    return [0, 3].includes(j % 4) ? 'lg:col-span-7' : 'lg:col-span-5';
};

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
        { threshold: 0.1, rootMargin: '0px 0px -5% 0px' },
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
        class="np-root [--paper:#EDF0F3] [--soft:#E1E6EB] [--surface:#F8F9FB] [--ink:#10161E] [--muted:#10161EB0] [--line:#10161E38] [--accent:#0A7A63] [--accent-ink:#F2FFFB] selection:bg-[#0A7A63] selection:text-white dark:[--paper:#0E1217] dark:[--soft:#141A21] dark:[--surface:#1A212A] dark:[--ink:#ECEFF3] dark:[--muted:#ECEFF3AD] dark:[--line:#ECEFF33A] dark:[--accent:#5FE0B8] dark:[--accent-ink:#0E1217]"
        :class="{ 'np-js': ready }"
    >
        <!-- ============================ NAVBAR (masthead) ============================ -->
        <header class="inset-x-0 top-0 z-50" :class="stickyNav ? 'fixed' : 'absolute'">
            <div ref="progressEl" class="np-progress" aria-hidden="true" />
            <nav ref="navEl" class="np-nav" aria-label="Primary">
                <div class="np-wrap flex h-14 items-center justify-between gap-4 px-5 sm:px-8">
                    <a v-if="logoImage || logoText" href="#header" class="flex min-w-0 items-center gap-3" @click="onNavClick($event, '#header')">
                        <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-7 w-auto max-w-[7rem] object-contain" />
                        <span v-if="logoText" class="np-display truncate text-xl">{{ logoText }}</span>
                    </a>
                    <span v-else />

                    <ul v-if="navLinks.length" class="hidden items-center gap-6 md:flex">
                        <li v-for="l in navLinks" :key="l.id">
                            <a :href="`#${l.id}`" class="np-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a>
                        </li>
                    </ul>

                    <a v-if="has('contact')" href="#contact" class="np-btn np-btn-primary !px-4 !py-2 !text-sm" @click="onNavClick($event, '#contact')">
                        Contact<ArrowUpRight class="np-arrow h-4 w-4" />
                    </a>
                    <span v-else />
                </div>
            </nav>
        </header>

        <!-- ============================== HERO ============================== -->
        <section v-if="has('header')" id="header" class="relative isolate overflow-hidden pt-14">
            <img
                v-if="media('header', 'hero_background_image')"
                :src="media('header', 'hero_background_image')"
                alt=""
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-[0.1] dark:opacity-[0.16]"
            />

            <div class="np-wrap grid lg:grid-cols-12">
                <!-- left margin: social links -->
                <aside v-if="heroSocials.length" class="np-margin order-2 border-t border-[var(--line)] lg:order-1 lg:col-span-1 lg:border-r lg:border-t-0">
                    <ul class="flex flex-wrap items-center justify-center gap-2 p-4 lg:sticky lg:top-24 lg:flex-col lg:gap-3 lg:py-10">
                        <li v-for="(s, i) in heroSocials" :key="i">
                            <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="np-icon-btn">
                                <component :is="socialIconFor(s.platform)" class="h-[18px] w-[18px]" />
                            </a>
                        </li>
                    </ul>
                </aside>

                <!-- centre: 10-column headline -->
                <div class="order-1 text-center lg:order-2" :class="[heroSocials.length && (email || phone || location) ? 'lg:col-span-10' : heroSocials.length || email || phone || location ? 'lg:col-span-11' : 'lg:col-span-12']">
                    <div v-if="text('header', 'hero_greeting') || text('header', 'hero_role')" class="np-load flex flex-wrap items-center justify-between gap-x-6 gap-y-1 border-b border-[var(--line)] px-5 py-3 text-sm font-semibold sm:px-8" style="--d: 0ms">
                        <span v-if="text('header', 'hero_greeting')" class="inline-flex items-center gap-2"><span class="np-pulse" aria-hidden="true" />{{ text('header', 'hero_greeting') }}</span>
                        <span v-if="text('header', 'hero_role')" class="text-[var(--accent)]">{{ text('header', 'hero_role') }}</span>
                    </div>

                    <h1 v-if="heroName" class="np-display np-hero-name px-4 pb-6 pt-14 sm:px-8 lg:pt-20">
                        <span class="sr-only">{{ heroName }}</span>
                        <span v-for="(w, i) in heroWords" :key="i" class="np-mask" aria-hidden="true">
                            <span class="np-word" :style="{ '--i': i }">{{ w }}</span>
                        </span>
                    </h1>

                    <div class="grid border-t border-[var(--line)] text-left md:grid-cols-12">
                        <div class="np-load flex flex-col justify-center p-5 sm:p-8" :class="media('header', 'hero_image') || heroInitials ? 'md:col-span-7 md:border-r md:border-[var(--line)]' : 'md:col-span-12'" style="--d: 520ms">
                            <p v-if="text('header', 'hero_description')" class="np-muted np-body max-w-[36rem] whitespace-pre-line text-lg leading-[1.7]">{{ text('header', 'hero_description') }}</p>
                            <div v-if="heroPrimary || heroSecondary" class="mt-7 flex flex-wrap gap-3">
                                <a v-if="heroPrimary" :href="heroPrimary.url" class="np-btn np-btn-primary" @click="onNavClick($event, heroPrimary.url)">
                                    {{ heroPrimary.label }}<ArrowUpRight class="np-arrow h-4 w-4" />
                                </a>
                                <a v-if="heroSecondary" :href="heroSecondary.url" class="np-btn np-btn-ghost" @click="onNavClick($event, heroSecondary.url)">{{ heroSecondary.label }}</a>
                            </div>
                        </div>
                        <figure v-if="media('header', 'hero_image') || heroInitials" class="np-load np-portrait group relative md:col-span-5" style="--d: 640ms">
                            <img v-if="media('header', 'hero_image')" :src="media('header', 'hero_image')" :alt="heroName ?? ''" class="aspect-[4/3] h-full w-full object-cover md:aspect-auto" />
                            <span v-else class="np-display flex aspect-[4/3] h-full w-full items-center justify-center bg-[var(--soft)] text-8xl text-[var(--accent)] md:aspect-auto">{{ heroInitials }}</span>
                        </figure>
                    </div>
                </div>

                <!-- right margin: contact meta -->
                <aside v-if="email || phone || location" class="np-margin order-3 border-t border-[var(--line)] lg:col-span-1 lg:border-l lg:border-t-0">
                    <ul class="flex flex-wrap items-center justify-center gap-4 p-4 text-sm font-semibold lg:sticky lg:top-24 lg:flex-col lg:gap-6 lg:py-10">
                        <li v-if="email"><a :href="`mailto:${email}`" class="np-meta" :title="email"><Mail class="h-4 w-4 shrink-0" /><span class="np-vert">{{ email }}</span></a></li>
                        <li v-if="phone"><a :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="np-meta" :title="phone"><Phone class="h-4 w-4 shrink-0" /><span class="np-vert">{{ phone }}</span></a></li>
                        <li v-if="location"><span class="np-meta" :title="location"><MapPin class="h-4 w-4 shrink-0 text-[var(--accent)]" /><span class="np-vert">{{ location }}</span></span></li>
                    </ul>
                </aside>
            </div>
        </section>

        <!-- ============================== ABOUT ============================== -->
        <section v-if="has('about')" id="about" class="scroll-mt-14 border-t border-[var(--line)]">
            <div class="np-wrap grid lg:grid-cols-12">
                <div v-if="media('about', 'about_image')" data-reveal class="np-img-cell lg:col-span-5 lg:border-r lg:border-[var(--line)]">
                    <img :src="media('about', 'about_image')" :alt="text('about', 'about_title') ?? ''" class="h-full max-h-[44rem] min-h-[18rem] w-full object-cover" loading="lazy" />
                </div>

                <div :class="media('about', 'about_image') ? 'lg:col-span-7' : 'lg:col-span-12'">
                    <div data-reveal class="p-5 sm:p-10">
                        <h2 v-if="text('about', 'about_title')" class="np-display np-h2">{{ text('about', 'about_title') }}</h2>
                        <p v-if="text('about', 'about_subtitle')" class="np-dek mt-4">{{ text('about', 'about_subtitle') }}</p>
                        <p v-if="text('about', 'about_description')" class="np-muted np-body np-dropcap mt-7 max-w-[40rem] whitespace-pre-line text-lg leading-[1.75]">{{ text('about', 'about_description') }}</p>
                    </div>

                    <ul v-if="highlights.length" class="np-cells grid border-t border-[var(--line)] sm:grid-cols-2">
                        <li v-for="(h, i) in highlights" :key="i" data-reveal :style="{ '--d': `${(i % 4) * 60}ms` }" class="np-cell p-5 sm:p-7">
                            <span class="np-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>
                            <span class="np-display mt-3 block text-xl leading-tight">{{ h.title }}</span>
                            <span v-if="h.description" class="np-muted mt-2 block text-[0.95rem] leading-relaxed">{{ h.description }}</span>
                        </li>
                    </ul>

                    <div v-if="cvFile || aboutCta" data-reveal class="flex flex-wrap gap-3 border-t border-[var(--line)] p-5 sm:p-10">
                        <a v-if="cvFile" :href="cvFile" download target="_blank" rel="noopener" class="np-btn np-btn-primary"><Download class="h-4 w-4" />Download CV</a>
                        <a v-if="aboutCta" :href="aboutCta.url" class="np-btn" :class="cvFile ? 'np-btn-ghost' : 'np-btn-primary'" @click="onNavClick($event, aboutCta.url)">
                            {{ aboutCta.label }}<ArrowUpRight class="np-arrow h-4 w-4" />
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================ JOURNEY + SKILLS (4 / 8) ============================ -->
        <div v-if="has('journey') || has('skills')" class="border-t border-[var(--line)]">
            <div class="np-wrap grid lg:grid-cols-12">
                <!-- Journey: 4-col list -->
                <section v-if="has('journey')" id="journey" class="scroll-mt-14 lg:border-r lg:border-[var(--line)]" :class="[journeySpan, has('skills') ? 'border-b border-[var(--line)] lg:border-b-0' : '']">
                    <div data-reveal class="border-b border-[var(--line)] p-5 sm:p-8">
                        <h2 v-if="text('journey', 'journey_title')" class="np-display np-h3">{{ text('journey', 'journey_title') }}</h2>
                        <p v-if="text('journey', 'journey_subtitle')" class="np-dek mt-3">{{ text('journey', 'journey_subtitle') }}</p>
                    </div>
                    <ol>
                        <li v-for="(item, index) in journey" :key="index" data-reveal :style="{ '--d': `${(index % 4) * 60}ms` }" class="np-entry border-b border-[var(--line)] p-5 last:border-b-0 sm:px-8">
                            <p v-if="item.period" class="text-sm font-semibold text-[var(--accent)]">{{ item.period }}</p>
                            <h3 v-if="item.title" class="np-display mt-1 text-xl leading-snug">{{ item.title }}</h3>
                            <p v-if="item.company" class="mt-0.5 text-[0.95rem] font-semibold">{{ item.company }}</p>
                            <p v-if="item.description" class="np-muted mt-2 whitespace-pre-line text-[0.95rem] leading-[1.65]">{{ item.description }}</p>
                        </li>
                    </ol>
                </section>

                <!-- Skills: 8-col grid -->
                <section v-if="has('skills')" id="skills" class="scroll-mt-14" :class="skillsSpan">
                    <div data-reveal class="grid gap-4 border-b border-[var(--line)] p-5 sm:p-8 md:grid-cols-12 md:items-end">
                        <div class="md:col-span-7">
                            <h2 v-if="text('skills', 'skills_title')" class="np-display np-h3">{{ text('skills', 'skills_title') }}</h2>
                            <p v-if="text('skills', 'skills_subtitle')" class="np-dek mt-3">{{ text('skills', 'skills_subtitle') }}</p>
                        </div>
                        <p v-if="text('skills', 'skills_description')" class="np-muted whitespace-pre-line text-[0.95rem] leading-[1.7] md:col-span-5">{{ text('skills', 'skills_description') }}</p>
                    </div>
                    <ul class="np-cells grid sm:grid-cols-2 xl:grid-cols-3">
                        <li v-for="(s, i) in skills" :key="i" data-reveal :style="{ '--d': `${(i % 6) * 45}ms` }" class="np-cell np-skill p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span v-if="flag('skills', 'skills_show_icon', true)" class="np-tile">
                                    <component :is="iconFor(s.icon)" v-if="iconFor(s.icon)" class="h-[17px] w-[17px]" />
                                    <span v-else-if="isGlyph(s.icon)" class="text-base leading-none" aria-hidden="true">{{ s.icon }}</span>
                                    <span v-else class="np-display text-sm leading-none" aria-hidden="true">{{ s.name.charAt(0).toUpperCase() }}</span>
                                </span>
                                <h3 class="font-semibold">{{ s.name }}</h3>
                                <span v-if="flag('skills', 'skills_show_level', true) && s.pct !== null" class="np-muted ml-auto text-sm font-semibold tabular-nums">{{ Math.round(s.pct) }}%</span>
                                <span v-else-if="flag('skills', 'skills_show_level', true) && s.label" class="np-pill ml-auto">{{ s.label }}</span>
                            </div>
                            <div
                                v-if="flag('skills', 'skills_show_level', true) && s.pct !== null"
                                class="np-track mt-4"
                                role="progressbar"
                                :aria-valuenow="Math.round(s.pct)"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :aria-label="s.name"
                            >
                                <div class="np-bar" :style="{ '--w': `${s.pct}%` }" />
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <!-- ============================== PROJECTS ============================== -->
        <section v-if="has('projects')" id="projects" class="scroll-mt-14 border-t border-[var(--line)]">
            <div class="np-wrap">
                <div data-reveal class="grid gap-4 border-b border-[var(--line)] p-5 sm:p-10 lg:grid-cols-12 lg:items-end">
                    <div class="lg:col-span-7">
                        <h2 v-if="text('projects', 'projects_title')" class="np-display np-h2">{{ text('projects', 'projects_title') }}</h2>
                        <p v-if="text('projects', 'projects_subtitle')" class="np-dek mt-4">{{ text('projects', 'projects_subtitle') }}</p>
                    </div>
                    <p v-if="text('projects', 'projects_description')" class="np-muted whitespace-pre-line leading-[1.7] lg:col-span-5">{{ text('projects', 'projects_description') }}</p>
                </div>

                <ul class="grid lg:grid-cols-12">
                    <li
                        v-for="(p, i) in projects"
                        :key="i"
                        data-reveal
                        :style="{ '--d': `${(i % 2) * 70}ms` }"
                        class="border-b border-[var(--line)] lg:[&:not(.np-wide)]:odd:border-r"
                        :class="[projectSpan(i), featureFirst && i === 0 ? 'np-wide' : '']"
                    >
                        <article class="np-project group flex h-full flex-col">
                            <component
                                :is="p.live ? 'a' : 'div'"
                                :href="p.live || undefined"
                                :target="p.live ? '_blank' : undefined"
                                :rel="p.live ? 'noopener noreferrer' : undefined"
                                :aria-label="p.live ? `Open ${p.title}` : undefined"
                                class="np-media relative block overflow-hidden border-b border-[var(--line)]"
                                :class="featureFirst && i === 0 ? 'aspect-[21/9]' : 'aspect-[16/9]'"
                            >
                                <img v-if="p.image" :src="p.image" :alt="p.title" class="h-full w-full object-cover" loading="lazy" />
                                <div v-else class="np-display np-tone flex h-full w-full items-center justify-center text-8xl" :class="`np-tone-${i % 3}`">{{ p.title.charAt(0).toUpperCase() }}</div>
                                <span v-if="featureFirst && i === 0" class="np-flag">Featured</span>
                                <span v-if="p.live" class="np-go" aria-hidden="true"><ArrowUpRight class="h-5 w-5" /></span>
                            </component>

                            <div class="grid flex-1 gap-5 p-5 sm:p-8 sm:grid-cols-12">
                                <div class="sm:col-span-7">
                                    <div class="flex items-baseline gap-4">
                                        <span class="np-display np-num" aria-hidden="true">{{ pad(i) }}</span>
                                        <h3 class="np-display text-2xl leading-tight sm:text-[1.7rem]"><span class="np-underline">{{ p.title }}</span></h3>
                                    </div>
                                    <p v-if="p.description" class="np-muted mt-4 leading-[1.65] sm:pl-[calc(3.5rem+1rem)]">{{ p.description }}</p>
                                </div>
                                <div v-if="p.tags.length || p.repo" class="flex flex-col gap-4 sm:col-span-5 sm:border-l sm:border-[var(--line)] sm:pl-6">
                                    <ul v-if="p.tags.length" class="flex flex-wrap gap-2">
                                        <li v-for="(t, ti) in p.tags" :key="ti" class="np-pill">{{ t }}</li>
                                    </ul>
                                    <a v-if="p.repo" :href="p.repo" target="_blank" rel="noopener noreferrer" class="np-textlink mt-auto self-start"><Code class="h-4 w-4" />Source code</a>
                                </div>
                            </div>
                        </article>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== SERVICES ============================== -->
        <section v-if="has('services')" id="services" class="scroll-mt-14 border-t border-[var(--line)]">
            <div class="np-wrap">
                <div data-reveal class="border-b border-[var(--line)] p-5 sm:p-10">
                    <h2 v-if="text('services', 'services_title')" class="np-display np-h2">{{ text('services', 'services_title') }}</h2>
                    <p v-if="text('services', 'services_subtitle')" class="np-dek mt-4 whitespace-pre-line">{{ text('services', 'services_subtitle') }}</p>
                </div>
                <ul class="np-cells grid sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="(s, i) in services" :key="i" data-reveal :style="{ '--d': `${(i % 3) * 60}ms` }" class="np-cell np-service group p-6 sm:p-8">
                        <span class="np-tile np-tile-lg">
                            <component :is="iconFor(s.icon) ?? Sparkles" v-if="!isGlyph(s.icon)" class="h-5 w-5" />
                            <span v-else class="text-xl leading-none" aria-hidden="true">{{ s.icon }}</span>
                        </span>
                        <h3 class="np-display mt-7 text-2xl leading-tight">{{ s.title }}</h3>
                        <p v-if="s.description" class="np-muted mt-3 leading-[1.65]">{{ s.description }}</p>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ============================== CONTACT ============================== -->
        <section v-if="has('contact')" id="contact" class="scroll-mt-14 border-t border-[var(--line)]">
            <div class="np-wrap">
                <div class="np-contact grid lg:grid-cols-12">
                    <div data-reveal class="p-6 sm:p-12 lg:col-span-7">
                        <h2 v-if="text('contact', 'contact_title')" class="np-display text-[clamp(2.75rem,7.5vw,6rem)] leading-[0.95] [overflow-wrap:anywhere]">{{ text('contact', 'contact_title') }}</h2>
                        <p v-if="text('contact', 'contact_subtitle')" class="mt-5 text-xl font-medium text-[var(--accent)]">{{ text('contact', 'contact_subtitle') }}</p>
                        <p v-if="text('contact', 'contact_description')" class="mt-5 max-w-[34rem] whitespace-pre-line text-lg leading-[1.7] opacity-80">{{ text('contact', 'contact_description') }}</p>

                        <div v-if="email || phone || location" class="mt-10 space-y-5">
                            <a v-if="email" :href="`mailto:${email}`" class="np-bigmail np-display group inline-flex max-w-full items-center gap-3 break-all text-2xl sm:text-4xl">
                                <Mail class="h-7 w-7 shrink-0 text-[var(--accent)]" /><span class="np-underline-contact">{{ email }}</span>
                                <ArrowUpRight class="np-arrow h-7 w-7 shrink-0" />
                            </a>
                            <div class="flex flex-wrap gap-x-8 gap-y-3 font-medium">
                                <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="np-contact-link"><Phone class="h-4 w-4" />{{ phone }}</a>
                                <span v-if="location" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4" />{{ location }}</span>
                            </div>
                        </div>

                        <ul v-if="contactSocials.length" class="mt-8 flex flex-wrap gap-2">
                            <li v-for="(s, i) in contactSocials" :key="i">
                                <a :href="s.url" target="_blank" rel="noopener noreferrer" class="np-social">{{ s.label }}<ArrowUpRight class="np-arrow h-3.5 w-3.5" /></a>
                            </li>
                        </ul>
                    </div>

                    <div data-reveal style="--d: 100ms" class="p-6 sm:p-12 lg:col-span-5 lg:border-l lg:border-[color-mix(in_srgb,var(--paper)_28%,transparent)]">
                        <form v-if="formReady" :action="formAction" method="POST" target="np-gform-frame" class="space-y-5" @submit="onSubmit">
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold">Name</span>
                                <input :name="`entry.${gf.name}`" type="text" required autocomplete="name" class="np-input" />
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold">Email</span>
                                <input :name="`entry.${gf.email}`" type="email" required autocomplete="email" class="np-input" />
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold">Message</span>
                                <textarea :name="`entry.${gf.message}`" rows="5" required class="np-input resize-y" />
                            </label>
                            <button type="submit" class="np-btn np-btn-accent w-full justify-center" :disabled="sending">
                                {{ sending ? 'Sending…' : 'Send message' }}<Send class="np-arrow h-4 w-4" />
                            </button>
                            <p v-if="sent" role="status" class="np-sent flex items-center gap-2 text-sm font-semibold">
                                <span class="np-tick"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>Your message was submitted.
                            </p>
                        </form>
                        <a v-else-if="email" :href="`mailto:${email}`" class="np-btn np-btn-accent w-full justify-center !py-5 text-lg"><Mail class="h-5 w-5" />{{ email }}</a>
                        <iframe name="np-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                    </div>
                </div>

                <footer v-if="heroName || logoText" class="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--line)] px-5 py-6 text-sm sm:px-10">
                    <span class="np-muted">&copy; {{ year }} {{ heroName ?? logoText }}</span>
                    <a href="#header" class="np-textlink" @click="onNavClick($event, '#header')">Back to top<ArrowUpRight class="np-arrow h-4 w-4 -rotate-45" /></a>
                </footer>
            </div>
        </section>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600..900;1,500..700&family=Archivo:wght@400;500;600;700&display=swap');

/* ---------- Foundations ---------- */
.np-root {
    background: var(--paper);
    color: var(--ink);
    font-family: 'Archivo', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    transition:
        background-color 0.4s ease,
        color 0.4s ease;
}
.np-wrap {
    max-width: 1240px;
    margin-inline: auto;
}
@media (min-width: 1241px) {
    .np-wrap {
        border-inline: 1px solid var(--line);
    }
}
.np-display {
    font-family: 'Playfair Display', Georgia, 'Times New Roman', serif;
    font-weight: 800;
    letter-spacing: -0.025em;
}
.np-body {
    font-family: 'Playfair Display', Georgia, serif;
    font-weight: 500;
}
.np-muted {
    color: var(--muted);
}
.np-h2 {
    font-size: clamp(2.25rem, 5vw, 4rem);
    line-height: 1.02;
    letter-spacing: -0.035em;
    text-wrap: balance;
}
.np-h3 {
    font-size: clamp(1.75rem, 3vw, 2.5rem);
    line-height: 1.05;
    letter-spacing: -0.03em;
}
.np-dek {
    max-width: 34rem;
    font-family: 'Playfair Display', Georgia, serif;
    font-style: italic;
    font-weight: 500;
    font-size: 1.15rem;
    line-height: 1.5;
    color: var(--accent);
}
.np-dropcap::first-letter {
    float: left;
    margin: 0.08em 0.12em 0 0;
    font-family: 'Playfair Display', Georgia, serif;
    font-weight: 800;
    font-size: 4.2em;
    line-height: 0.78;
    color: var(--ink);
}
.np-root :where(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
}

/* Hairline cells: 1px gaps over a rule-coloured backplate */
.np-cells {
    gap: 1px;
    background: var(--line);
    border-top: 1px solid var(--line);
}
.np-cell {
    background: var(--paper);
    transition: background-color 0.3s ease;
}
.np-cell:hover {
    background: var(--surface);
}

/* ---------- Navbar ---------- */
.np-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 3px;
    background: var(--accent);
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
    z-index: 60;
}
.np-nav {
    border-bottom: 1px solid var(--line);
    background: color-mix(in srgb, var(--paper) 92%, transparent);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: box-shadow 0.35s ease;
}
.np-nav.is-scrolled {
    box-shadow: 0 1px 0 var(--ink);
}
.np-navlink {
    position: relative;
    display: inline-block;
    padding: 0.35rem 0;
    font-size: 0.92rem;
    font-weight: 500;
    color: var(--muted);
    background: linear-gradient(var(--accent), var(--accent)) left bottom / 0% 2px no-repeat;
    transition:
        color 0.25s ease,
        background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.np-navlink:hover,
.np-navlink[data-active='true'] {
    color: var(--ink);
}
.np-navlink[data-active='true'] {
    background-size: 100% 2px;
}

/* ---------- Buttons & small controls ---------- */
.np-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.2rem;
    padding: 0.8rem 1.4rem;
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
.np-btn:hover {
    transform: translateY(-2px);
}
.np-btn:active {
    transform: translateY(0) scale(0.98);
}
.np-btn[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.np-btn-primary {
    background: var(--ink);
    color: var(--paper);
}
.np-btn-primary:hover {
    background: var(--accent);
    color: var(--accent-ink);
}
.np-btn-ghost {
    border-color: var(--ink);
    color: var(--ink);
}
.np-btn-ghost:hover {
    background: var(--ink);
    color: var(--paper);
}
.np-btn-accent {
    background: var(--accent);
    color: var(--accent-ink);
}
.np-btn-accent:hover {
    background: var(--paper);
    color: var(--ink);
}
.np-arrow {
    transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.np-btn:hover .np-arrow,
.np-bigmail:hover .np-arrow,
.np-social:hover .np-arrow,
.np-textlink:hover .np-arrow {
    transform: translate(3px, -3px);
}
.np-icon-btn {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
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
.np-icon-btn:hover {
    transform: translateY(-3px);
    background: var(--accent);
    color: var(--accent-ink);
    border-color: var(--accent);
}
.np-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--muted);
    transition: color 0.25s ease;
}
a.np-meta:hover {
    color: var(--accent);
}
@media (min-width: 1024px) {
    .np-meta {
        flex-direction: column;
    }
    .np-vert {
        writing-mode: vertical-rl;
        max-height: 11rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}
.np-pulse {
    position: relative;
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 999px;
    background: var(--accent);
}
.np-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: inherit;
    animation: np-ping 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.np-pill {
    display: inline-block;
    border: 1px solid var(--line);
    padding: 0.2rem 0.65rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--muted);
}
.np-tick {
    display: inline-flex;
    height: 1.5rem;
    width: 1.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    background: var(--accent);
    color: var(--accent-ink);
}
.np-textlink {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    background: linear-gradient(currentColor, currentColor) left bottom / 0% 1.5px no-repeat;
    transition: background-size 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 2px;
}
.np-textlink:hover {
    background-size: 100% 1.5px;
}

/* ---------- Hero (one orchestrated load sequence) ---------- */
.np-hero-name {
    font-size: clamp(3.25rem, 11vw, 9.5rem);
    line-height: 0.92;
    letter-spacing: -0.045em;
    overflow-wrap: anywhere;
    text-wrap: balance;
}
.np-mask {
    display: inline-block;
    overflow: hidden;
    vertical-align: top;
    padding-bottom: 0.14em;
    margin-bottom: -0.14em;
    margin-inline: 0.1em;
}
.np-word {
    display: inline-block;
}
.np-js .np-word {
    transform: translateY(110%);
    animation: np-rise 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    animation-delay: calc(0.15s + var(--i, 0) * 0.12s);
}
.np-js .np-load {
    opacity: 0;
    transform: translateY(16px);
    animation: np-fade 0.9s cubic-bezier(0.2, 0.7, 0.2, 1) forwards;
    animation-delay: var(--d, 0ms);
}
.np-portrait {
    overflow: hidden;
    background: var(--soft);
    min-height: 16rem;
}
.np-portrait img {
    filter: grayscale(0.35) contrast(1.03);
    transition:
        transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1),
        filter 0.6s ease;
}
.np-portrait:hover img {
    transform: scale(1.04);
    filter: grayscale(0);
}

/* ---------- About ---------- */
.np-img-cell {
    overflow: hidden;
    background: var(--soft);
}
.np-img-cell img {
    filter: grayscale(0.3);
    transition:
        transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1),
        filter 0.6s ease;
}
.np-img-cell:hover img {
    transform: scale(1.03);
    filter: grayscale(0);
}

/* ---------- Journey ---------- */
.np-entry {
    position: relative;
    transition: background-color 0.3s ease;
}
.np-entry::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--accent);
    transform: scaleY(0);
    transform-origin: top;
    transition: transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.np-entry:hover {
    background: var(--surface);
}
.np-entry:hover::before {
    transform: scaleY(1);
}

/* ---------- Skills & services ---------- */
.np-tile {
    display: inline-flex;
    height: 2.25rem;
    width: 2.25rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--line);
    background: var(--surface);
    color: var(--accent);
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.np-tile-lg {
    height: 3.25rem;
    width: 3.25rem;
}
.np-skill:hover .np-tile,
.np-service:hover .np-tile {
    background: var(--accent);
    color: var(--accent-ink);
    transform: rotate(-6deg) scale(1.06);
}
.np-track {
    height: 4px;
    width: 100%;
    background: color-mix(in srgb, var(--ink) 12%, transparent);
    overflow: hidden;
}
.np-bar {
    height: 100%;
    width: var(--w, 0%);
    background: var(--accent);
}
.np-js [data-reveal] .np-bar {
    width: 0;
    transition: width 1.4s cubic-bezier(0.2, 0.7, 0.2, 1) calc(var(--d, 0ms) + 0.3s);
}
.np-js [data-reveal].is-in .np-bar {
    width: var(--w, 0%);
}

/* ---------- Projects ---------- */
.np-media {
    background: var(--soft);
    isolation: isolate;
}
.np-media img,
.np-tone {
    transition:
        transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1),
        filter 0.6s ease;
}
.np-media img {
    filter: grayscale(0.4);
}
.np-project:hover .np-media img {
    filter: grayscale(0);
}
.np-project:hover .np-media img,
.np-project:hover .np-tone {
    transform: scale(1.04);
}
.np-tone-0 {
    background: var(--accent);
    color: var(--accent-ink);
}
.np-tone-1 {
    background: var(--surface);
    color: var(--accent);
}
.np-tone-2 {
    background: var(--ink);
    color: var(--paper);
}
.np-num {
    flex-shrink: 0;
    width: 3.5rem;
    font-size: 3rem;
    line-height: 0.9;
    color: var(--accent);
    font-variant-numeric: tabular-nums;
}
.np-flag {
    position: absolute;
    left: 1rem;
    top: 1rem;
    background: var(--accent);
    color: var(--accent-ink);
    padding: 0.25rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 700;
}
.np-go {
    position: absolute;
    right: 1rem;
    top: 1rem;
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    background: var(--paper);
    color: var(--ink);
    opacity: 0;
    transform: translate(-0.5rem, 0.5rem);
    transition:
        opacity 0.35s ease,
        transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.np-project:hover .np-go,
.np-media:focus-visible .np-go {
    opacity: 1;
    transform: none;
}
.np-underline {
    background: linear-gradient(var(--accent), var(--accent)) left 96% / 0% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.np-project:hover .np-underline {
    background-size: 100% 2px;
}

/* ---------- Contact ---------- */
.np-contact {
    background: var(--ink);
    color: var(--paper);
}
.np-underline-contact {
    background: linear-gradient(currentColor, currentColor) left bottom / 100% 2px no-repeat;
    transition: background-size 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
    padding-bottom: 4px;
}
.np-bigmail:hover .np-underline-contact {
    background-size: 0% 2px;
    background-position: right bottom;
}
.np-contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.9;
}
.np-contact-link:hover {
    opacity: 1;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.np-social {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
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
.np-social:hover {
    background: var(--paper);
    color: var(--ink);
    border-color: var(--paper);
    transform: translateY(-2px);
}
.np-input {
    width: 100%;
    border: 1px solid color-mix(in srgb, var(--paper) 35%, transparent);
    background: color-mix(in srgb, var(--paper) 8%, transparent);
    padding: 0.8rem 1rem;
    font: inherit;
    font-weight: 500;
    color: var(--paper);
    transition:
        border-color 0.25s ease,
        box-shadow 0.25s ease;
}
.np-input:hover {
    border-color: color-mix(in srgb, var(--paper) 65%, transparent);
}
.np-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 35%, transparent);
}
.np-sent {
    animation: np-pop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.np-js [data-reveal] {
    opacity: 0;
    transform: translateY(20px);
    transition:
        opacity 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms),
        transform 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms);
}
.np-js [data-reveal].is-in {
    opacity: 1;
    transform: none;
}
.np-js [data-reveal].is-in .np-media {
    animation: np-wipe 1.1s cubic-bezier(0.7, 0, 0.2, 1) 0.1s backwards;
}

@keyframes np-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes np-fade {
    to {
        opacity: 1;
        transform: none;
    }
}
@keyframes np-ping {
    75%,
    100% {
        transform: scale(2.6);
        opacity: 0;
    }
}
@keyframes np-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.96);
    }
}
@keyframes np-wipe {
    from {
        clip-path: inset(0 0 100% 0);
    }
}

/* ---------- Reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    .np-root *,
    .np-root *::before,
    .np-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .np-js .np-word,
    .np-js .np-load,
    .np-js [data-reveal] {
        opacity: 1;
        transform: none;
    }
    .np-js [data-reveal] .np-bar {
        width: var(--w, 0%);
    }
    .np-progress {
        transition: none;
    }
}
</style>
