<script setup lang="ts">
/**
 * Orbit Folio — quickFolio template
 * Slug suggestion: `orbit-folio`
 *
 * Recreation of the uploaded Fraunces / IBM Plex slate portfolio: cinematic word-by-word intro,
 * rising outlined hero name with orbit lines + floating badges, pill navbar on scroll, watermark
 * section titles, skill cards with animated bars, technology marquee, vertical journey timeline,
 * accordion projects with a full-screen case-study dialog, contact cards + form.
 * Light + dark via Tailwind `dark:` (palette lives in CSS variables on the root; the toggle
 * flips the `dark` class on <html> and remembers it).
 * Every visible value comes from the seeded field keys.
 */
import { resolvePortfolioMediaUrl } from '@/lib/media';
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    ArrowUpRight,
    AtSign,
    Braces,
    Briefcase,
    Camera,
    Code,
    Database,
    Download,
    Dribbble,
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
    Menu,
    MessageSquare,
    Monitor,
    Moon,
    Palette,
    PenTool,
    Phone,
    Rocket,
    Server,
    Smartphone,
    Sparkles,
    Sun,
    Terminal,
    X,
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
const toList = (v: unknown, splitter = /[,|\n]/): string[] =>
    Array.isArray(v)
        ? (v.map((t: any) => clean(typeof t === 'string' ? t : (t?.name ?? t?.title ?? t?.label ?? t?.text))).filter(Boolean) as string[])
        : typeof v === 'string'
          ? v
                .split(splitter)
                .map((t) => t.trim())
                .filter(Boolean)
          : [];

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

/** Headings: wrap words in *asterisks* to italicise them; otherwise the longest word is italicised. */
const emphasize = (title?: string): { t: string; em: boolean }[] => {
    if (!title) return [];
    if (title.includes('*')) {
        return title
            .split(/(\*[^*]+\*)/g)
            .filter(Boolean)
            .map((t) => (t.startsWith('*') && t.endsWith('*') ? { t: t.slice(1, -1), em: true } : { t, em: false }));
    }
    const words = title.split(/(\s+)/);
    const real = words.filter((w) => w.trim());
    if (real.length < 3) return [{ t: title, em: false }];
    const longest = real.reduce((a, b) => (b.replace(/\W/g, '').length > a.replace(/\W/g, '').length ? b : a));
    const idx = words.indexOf(longest);
    return words.map((w, i) => ({ t: w, em: i === idx }));
};

// Navbar
const logoText = computed(() => text('navbar', 'logo_text') ?? text('header', 'hero_name'));
const logoWords = computed(() => (logoText.value ?? '').split(/\s+/).filter(Boolean));
const logoImage = computed(() => (flag('navbar', 'show_logo', true) ? media('navbar', 'logo_image') : undefined));
const stickyNav = computed(() => flag('navbar', 'sticky_nav', true));

const sectionLabels: Record<string, string> = { about: 'About', skills: 'Skills', journey: 'Journey', projects: 'Projects', contact: 'Contact' };
const visibleSections = computed(() => ['about', 'skills', 'journey', 'projects', 'contact'].filter(has));
const num = (id: string) => pad(Math.max(0, visibleSections.value.indexOf(id)));
const navLinks = computed(() => [
    ...(has('header') ? [{ id: 'home', label: 'Home' }] : []),
    ...visibleSections.value.map((id) => ({ id, label: sectionLabels[id] })),
]);

// Hero
const heroName = computed(() => text('header', 'hero_name'));
const heroWords = computed(() => (heroName.value ?? '').split(/\s+/).filter(Boolean));
const heroFirst = computed(() => heroWords.value[0] ?? '');
const heroRest = computed(() => heroWords.value.slice(1).join(' '));
const heroRole = computed(() => text('header', 'hero_role'));
const heroGreeting = computed(() => text('header', 'hero_greeting'));
const heroInitials = computed(() =>
    heroWords.value
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
                ? { value: clean(h) ?? '', label: undefined as string | undefined }
                : { value: clean(h?.value ?? h?.title ?? h?.text ?? h?.name) ?? '', label: clean(h?.label ?? h?.description) },
        )
        .filter((h) => h.value),
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
            tags: toList(item?.tags),
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
            return {
                name: name ?? '',
                icon: typeof s === 'object' ? clean(s?.icon) : undefined,
                category: typeof s === 'object' ? clean(s?.category ?? s?.group ?? s?.type) : undefined,
                pct,
                label,
            };
        })
        .filter((s) => s.name),
);
// Cards: grouped by category when given, otherwise split into three even columns
const skillGroups = computed(() => {
    const list = skills.value;
    if (list.some((s) => s.category)) {
        const map = new Map<string, typeof list>();
        for (const s of list) {
            const k = s.category ?? '';
            map.set(k, [...(map.get(k) ?? []), s]);
        }
        return [...map.entries()].map(([title, items]) => ({ title: title || undefined, items }));
    }
    const size = Math.max(1, Math.ceil(list.length / 3));
    const out: { title?: string; items: typeof list }[] = [];
    for (let i = 0; i < list.length; i += size) out.push({ title: undefined, items: list.slice(i, i + size) });
    return out;
});
const isUrl = (v?: string) => !!v && /^(https?:\/\/|\/|data:|\.\/)/.test(v);

// Projects
const projects = computed(() =>
    arr('projects', 'projects_list')
        .map((p) => {
            const imageValue = typeof p?.image === 'string' ? clean(p.image) : clean(p?.image?.url ?? p?.image?.path);
            const tags = toList(p?.tags);
            const category = clean(p?.category);
            return {
                title: clean(p?.title ?? p?.name) ?? '',
                description: clean(p?.description),
                image: imageValue ? resolvePortfolioMediaUrl(imageValue) : undefined,
                repo: clean(p?.repo_url),
                live: clean(p?.live_url ?? p?.url),
                category,
                tags,
                features: toList(p?.features ?? p?.highlights, /[|\n]/),
            };
        })
        .filter((p) => p.title),
);
const featureFirst = computed(() => flag('projects', 'projects_show_featured', false));

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
const iconFor = (name?: string): Component | null => (name && !isUrl(name) ? (iconMap[name.toLowerCase().replace(/[^a-z]/g, '')] ?? null) : null);
const isGlyph = (name?: string) => !!name && !isUrl(name) && !iconFor(name) && [...name].length <= 3;
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
/* Contact form (Google Form when configured, otherwise a mailto)      */
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
const gfReady = computed(() => !!(gf.value.url && gf.value.name && gf.value.email && gf.value.message));
const formEnabled = computed(() => flag('contact', 'contact_form_enabled', true) && (gfReady.value || !!email.value));
const formAction = computed(() => (gf.value.url ?? '').replace(/\/viewform.*$/, '/formResponse'));
const fName = ref('');
const fEmail = ref('');
const fMessage = ref('');
const sending = ref(false);
const formStatus = ref('');
const onSubmit = (e: Event) => {
    if (!fName.value.trim() || !fEmail.value.trim() || !fMessage.value.trim()) {
        e.preventDefault();
        formStatus.value = 'Please fill in your name, email and message.';
        return;
    }
    const who = fName.value.trim();
    if (gfReady.value) {
        // Google answers inside the hidden iframe; its result can't be read, so confirm optimistically.
        sending.value = true;
        window.setTimeout(() => {
            sending.value = false;
            formStatus.value = `Thanks, ${who}! Your message was submitted.`;
            fName.value = fEmail.value = fMessage.value = '';
        }, 650);
        return;
    }
    e.preventDefault();
    const body = `${fMessage.value}\n\n${who} (${fEmail.value})`;
    window.location.href = `mailto:${email.value}?subject=${encodeURIComponent(`Message from ${who}`)}&body=${encodeURIComponent(body)}`;
    formStatus.value = `Thanks, ${who}! Your email app should open with your message.`;
    fName.value = fEmail.value = fMessage.value = '';
};

/* ------------------------------------------------------------------ */
/* Theme + mobile menu                                                 */
/* ------------------------------------------------------------------ */

const isDark = ref(false);
const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    try {
        localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
    } catch {
        /* storage unavailable */
    }
};
const menuOpen = ref(false);

/* ------------------------------------------------------------------ */
/* Projects accordion + case-study dialog                              */
/* ------------------------------------------------------------------ */

const openRow = ref(0); // one row is always open
const toggleRow = (i: number) => {
    openRow.value = openRow.value === i ? 0 : i;
};

const modalEl = ref<HTMLDialogElement | null>(null);
const modalOpen = ref(false);
const caseIndex = ref(0);
const caseProject = computed(() => projects.value[caseIndex.value]);
const openCase = (i: number) => {
    caseIndex.value = i;
    if (modalEl.value && !modalEl.value.open) {
        modalEl.value.showModal();
        modalOpen.value = true;
    }
    nextTick(() => modalEl.value?.scrollTo?.({ top: 0 }));
};
const closeCase = () => modalEl.value?.close();
const stepCase = (d: number) => {
    const n = caseIndex.value + d;
    if (n < 0 || n >= projects.value.length) return;
    caseIndex.value = n;
    nextTick(() => modalEl.value?.scrollTo?.({ top: 0 }));
};
const onModalKey = (e: KeyboardEvent) => {
    if (e.key === 'ArrowLeft') stepCase(-1);
    if (e.key === 'ArrowRight') stepCase(1);
};
const onModalClick = (e: MouseEvent) => {
    if (e.target === modalEl.value) closeCase();
};

/* ------------------------------------------------------------------ */
/* Cinematic intro: name, role, greeting — one word at a time          */
/* ------------------------------------------------------------------ */

const introWords = computed(() => [
    ...(heroName.value ? [{ kind: 'name' }] : []),
    ...(heroRole.value ? [{ kind: 'role' }] : []),
    ...(heroGreeting.value ? [{ kind: 'greet' }] : []),
]);
const introCls: Record<string, string> = {
    name: 'ak-display font-black text-5xl md:text-8xl',
    role: 'ak-display italic text-3xl md:text-6xl',
    greet: 'ak-display italic font-black text-6xl md:text-9xl',
};
const introActive = ref(false);
const introIndex = ref(0);
const introShow = ref(false);
const introPhase = ref<'play' | 'exiting' | 'closing'>('play');
const portfolioReady = ref(false);
const timers: number[] = [];
const later = (fn: () => void, ms: number) => timers.push(window.setTimeout(fn, ms));

const syncScrollLock = () => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = introActive.value || modalOpen.value ? 'hidden' : '';
};
watch([introActive, modalOpen], syncScrollLock);

const showPortfolio = () => {
    portfolioReady.value = true;
    introPhase.value = 'closing';
    later(() => {
        introActive.value = false;
    }, 800);
    nextTick(() => {
        observeReveals();
        observeSections();
        onScroll();
    });
};
const showWord = (i: number) => {
    if (i >= introWords.value.length) {
        introPhase.value = 'exiting';
        later(showPortfolio, 450);
        return;
    }
    introIndex.value = i;
    introShow.value = false;
    later(() => (introShow.value = true), 20);
    later(() => showWord(i + 1), 300);
};

/* ------------------------------------------------------------------ */
/* Behaviour: scroll progress, active section, reveal                  */
/* ------------------------------------------------------------------ */

const rootEl = ref<HTMLElement | null>(null);
const progressEl = ref<HTMLElement | null>(null);
const navEl = ref<HTMLElement | null>(null);
const ready = ref(false);
const activeId = ref('home');
let observer: IntersectionObserver | null = null;
let sectionObserver: IntersectionObserver | null = null;
let scrollFrame: number | null = null;

const sectionIds = ['home', 'about', 'skills', 'journey', 'projects', 'contact'];
const reduceMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const onNavClick = (e: Event, url?: string) => {
    menuOpen.value = false;
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
        navEl.value?.classList.toggle('is-scrolled', window.scrollY > 24);
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
        { threshold: 0.1, rootMargin: '0px 0px -40px 0px' },
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
    try {
        if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
    } catch {
        /* storage unavailable */
    }
    isDark.value = document.documentElement.classList.contains('dark');
    window.addEventListener('scroll', onScroll, { passive: true });
    modalEl.value?.addEventListener('close', () => (modalOpen.value = false));

    if (introWords.value.length && !reduceMotion()) {
        introActive.value = true;
        showWord(0);
    } else {
        portfolioReady.value = true;
        await nextTick();
        observeReveals();
        observeSections();
        onScroll();
    }
});
onBeforeUnmount(() => {
    observer?.disconnect();
    sectionObserver?.disconnect();
    window.removeEventListener('scroll', onScroll);
    if (scrollFrame !== null) window.cancelAnimationFrame(scrollFrame);
    timers.forEach((t) => window.clearTimeout(t));
    document.body.style.overflow = '';
});
// Re-scan when content changes (editor live preview)
watch(data, async () => {
    if (!ready.value || !portfolioReady.value) return;
    await nextTick();
    observeReveals();
    observeSections();
});

const year = new Date().getFullYear();
</script>

<template>
    <div
        ref="rootEl"
        class="ak-root [--paper:#F5F5F4] [--surface:#FFFFFF] [--soft:#F1F5F9] [--ink:#0F172A] [--on-ink:#FFFFFF] [--muted:#64748B] [--line:#E2E8F0] [--rule:#CBD5E1] selection:bg-[#0F172A] selection:text-white dark:[--paper:#0F172A] dark:[--surface:#1E293B] dark:[--soft:#0F172A] dark:[--ink:#F1F5F9] dark:[--on-ink:#0F172A] dark:[--muted:#94A3B8] dark:[--line:#334155] dark:[--rule:#475569] dark:selection:bg-[#F1F5F9] dark:selection:text-[#0F172A]"
        :class="{ 'ak-js': ready, 'ak-ready': portfolioReady }"
    >
        <!-- ================= CINEMATIC INTRO ================= -->
        <div v-if="introActive" class="ak-intro" :class="{ 'is-exiting': introPhase === 'exiting', 'is-closing': introPhase === 'closing' }" aria-hidden="true">
            <div class="ak-intro-box">
                <p v-if="introWords[introIndex]" :key="introIndex" class="ak-intro-line" :class="[introShow ? 'show' : '', introCls[introWords[introIndex].kind]]">
                    <template v-if="introWords[introIndex].kind === 'name'">{{ heroFirst }}<template v-if="heroRest"> <span class="ak-outline">{{ heroRest }}</span></template></template>
                    <template v-else-if="introWords[introIndex].kind === 'role'">{{ heroRole }}</template>
                    <template v-else>{{ heroGreeting }}</template>
                </p>
            </div>
        </div>

        <!-- ================= NAVBAR ================= -->
        <header class="inset-x-0 top-0 z-50 w-full px-4 pt-4" :class="stickyNav ? 'fixed' : 'absolute'">
            <div ref="progressEl" class="ak-progress" aria-hidden="true" />
            <nav ref="navEl" class="ak-nav relative mx-auto flex max-w-6xl items-center justify-between rounded-full border border-transparent px-6 py-3" aria-label="Primary">
                <a v-if="logoImage || logoText" href="#home" class="flex min-w-0 items-center gap-2" @click="onNavClick($event, '#home')">
                    <img v-if="logoImage" :src="logoImage" :alt="logoText ?? ''" class="h-7 w-auto max-w-[7rem] object-contain" />
                    <span v-if="logoText" class="ak-display truncate text-xl font-semibold italic">{{ logoWords[0] }}<span v-if="logoWords.length > 1" class="not-italic">{{ logoWords.slice(1).join(' ') }}</span></span>
                </a>
                <span v-else />

                <ul v-if="navLinks.length" class="ak-muted hidden gap-8 text-sm md:flex">
                    <li v-for="l in navLinks" :key="l.id">
                        <a :href="`#${l.id}`" class="ak-navlink" :data-active="activeId === l.id" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a>
                    </li>
                </ul>

                <div class="flex items-center gap-3">
                    <button type="button" aria-label="Toggle dark mode" class="ak-circle" @click="toggleTheme">
                        <Sun v-if="isDark" class="h-4 w-4" />
                        <Moon v-else class="h-4 w-4" />
                    </button>
                    <button type="button" :aria-label="menuOpen ? 'Close menu' : 'Open menu'" :aria-expanded="menuOpen" class="ak-circle md:hidden" @click="menuOpen = !menuOpen">
                        <X v-if="menuOpen" class="h-4 w-4" />
                        <Menu v-else class="h-4 w-4" />
                    </button>
                    <a v-if="has('contact')" href="#contact" class="ak-btn-line hidden !px-5 !py-2 text-sm md:block" @click="onNavClick($event, '#contact')">Let's talk</a>
                </div>

                <ul v-if="menuOpen && navLinks.length" class="ak-card absolute left-0 right-0 top-full mt-2 rounded-2xl p-3 shadow-lg md:hidden">
                    <li v-for="l in navLinks" :key="l.id"><a :href="`#${l.id}`" class="block px-4 py-3" @click="onNavClick($event, `#${l.id}`)">{{ l.label }}</a></li>
                </ul>
            </nav>
        </header>

        <!-- ================= STAGE (hidden until the intro ends) ================= -->
        <div class="ak-stage">
            <main>
                <!-- ================= HERO ================= -->
                <section v-if="has('header')" id="home" class="relative isolate mx-auto grid min-h-screen max-w-6xl grid-cols-1 items-center gap-8 px-5 pb-10 pt-32 lg:grid-cols-2">
                    <img
                        v-if="media('header', 'hero_background_image')"
                        :src="media('header', 'hero_background_image')"
                        alt=""
                        class="absolute inset-0 -z-10 h-full w-full object-cover opacity-[0.08] dark:opacity-[0.12]"
                    />
                    <div>
                        <p v-if="heroGreeting" class="ak-muted mb-5 text-sm">{{ heroGreeting }}</p>
                        <h1 v-if="heroName" class="ak-display text-6xl font-black leading-none md:text-8xl">
                            <span class="sr-only">{{ heroName }}</span>
                            <span class="ak-row" aria-hidden="true"><span>{{ heroFirst }}</span></span>
                            <span v-if="heroRest" class="ak-row" aria-hidden="true"><span class="ak-outline">{{ heroRest }}</span></span>
                        </h1>
                        <p v-if="heroRole" class="ak-muted my-6 flex items-center gap-3">
                            <span class="h-px w-8 bg-[var(--muted)]" />
                            <strong class="font-medium text-[var(--ink)]">{{ heroRole }}</strong>
                        </p>
                        <p v-if="text('header', 'hero_description')" class="ak-muted mb-8 max-w-md whitespace-pre-line leading-relaxed">{{ text('header', 'hero_description') }}</p>
                        <div class="flex flex-wrap items-center gap-6">
                            <a v-if="heroPrimary" :href="heroPrimary.url" class="ak-btn-solid" @click="onNavClick($event, heroPrimary.url)">{{ heroPrimary.label }}</a>
                            <a v-if="heroSecondary" :href="heroSecondary.url" class="ak-btn-line" @click="onNavClick($event, heroSecondary.url)">{{ heroSecondary.label }}</a>
                            <ul v-if="heroSocials.length" class="flex gap-3">
                                <li v-for="(s, i) in heroSocials" :key="i">
                                    <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="ak-circle !h-10 !w-10">
                                        <component :is="socialIconFor(s.platform)" class="h-4 w-4" />
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- profile image + floating badges -->
                    <div class="ak-art relative mx-auto aspect-square w-full max-w-md lg:ml-auto lg:max-w-xl">
                        <div class="ak-orbit ak-orbit-1" />
                        <div class="ak-orbit ak-orbit-2" />
                        <div class="ak-orbit ak-orbit-3" />
                        <span class="ak-float ak-card absolute left-[8%] top-[10%] z-10 flex h-14 w-14 -rotate-12 items-center justify-center rounded-2xl shadow" aria-hidden="true"><Code class="h-5 w-5" /></span>
                        <span class="ak-float ak-card absolute right-[8%] top-[5%] z-10 flex h-14 w-14 items-center justify-center rounded-full shadow" aria-hidden="true"><Braces class="h-5 w-5" /></span>
                        <span class="ak-float ak-card absolute bottom-[18%] right-0 z-10 flex h-10 w-16 items-center justify-center rounded-full shadow" aria-hidden="true"><Sparkles class="h-4 w-4" /></span>
                        <span class="ak-float absolute left-0 top-[40%] h-3 w-3 rounded-full bg-[var(--ink)]" aria-hidden="true" />
                        <span class="ak-float absolute bottom-[7%] right-[22%] h-3 w-3 rounded-full bg-[var(--muted)]" aria-hidden="true" />
                        <img v-if="media('header', 'hero_image')" :src="media('header', 'hero_image')" :alt="heroName ?? ''" class="h-full w-full object-cover drop-shadow-xl" />
                        <span v-else-if="heroInitials" class="ak-display flex h-full w-full items-center justify-center text-[9rem] font-black text-[var(--ink)] opacity-90">{{ heroInitials }}</span>
                    </div>

                    <div v-if="navLinks.length > 1" class="ak-muted hidden items-center gap-2 text-xs lg:col-span-2 lg:flex">
                        <span class="h-1.5 w-1.5 rounded-full bg-[var(--ink)]" /><ArrowDown class="h-3.5 w-3.5" />Scroll
                    </div>
                </section>

                <!-- ================= ABOUT ================= -->
                <section v-if="has('about')" id="about" class="relative mx-auto max-w-6xl px-5 py-20">
                    <span class="ak-mark" aria-hidden="true">{{ sectionLabels.about }}</span>
                    <div class="relative grid grid-cols-1 items-center gap-8 pt-10 md:grid-cols-[1.15fr_0.85fr]">
                        <div>
                            <div data-reveal>
                                <span class="ak-eyebrow"><span class="ak-eyebrow-num">{{ num('about') }}</span>{{ sectionLabels.about }}</span>
                                <h2 v-if="text('about', 'about_title')" class="ak-display mb-6 text-3xl font-semibold leading-tight md:text-5xl">
                                    <template v-for="(s, i) in emphasize(text('about', 'about_title'))" :key="i"><em v-if="s.em" class="font-medium">{{ s.t }}</em><template v-else>{{ s.t }}</template></template>
                                </h2>
                                <p v-if="text('about', 'about_subtitle')" class="ak-display mb-5 border-l-2 border-[var(--ink)] pl-5 text-xl italic leading-snug md:text-2xl">{{ text('about', 'about_subtitle') }}</p>
                                <p v-if="text('about', 'about_description')" class="ak-muted mb-6 max-w-lg whitespace-pre-line leading-relaxed">{{ text('about', 'about_description') }}</p>
                            </div>
                            <div v-if="cvFile || aboutCta" data-reveal class="flex flex-wrap items-center gap-5">
                                <a v-if="cvFile" :href="cvFile" download target="_blank" rel="noopener" class="ak-btn-card"><Download class="h-4 w-4" />Download CV</a>
                                <a v-if="aboutCta" :href="aboutCta.url" class="ak-btn-card" @click="onNavClick($event, aboutCta.url)">{{ aboutCta.label }}<ArrowUpRight class="h-4 w-4" /></a>
                            </div>
                        </div>

                        <div v-if="media('about', 'about_image')" data-reveal class="ak-card mx-auto w-full max-w-sm rounded-3xl p-3 shadow-md">
                            <img :src="media('about', 'about_image')" :alt="heroName ?? text('about', 'about_title') ?? ''" loading="lazy" class="aspect-[4/5] w-full rounded-2xl object-cover" />
                            <p v-if="heroName" class="ak-display mb-1 mt-4 text-center text-2xl italic">{{ heroName }}</p>
                        </div>
                    </div>

                    <!-- stats -->
                    <ul v-if="highlights.length" data-reveal class="relative mt-12 flex flex-wrap items-baseline gap-10 border-y border-[var(--line)] py-6">
                        <li v-for="(h, i) in highlights" :key="i" class="flex items-baseline gap-2">
                            <span class="ak-display text-4xl font-black">{{ h.value }}</span>
                            <span v-if="h.label" class="ak-muted text-sm">{{ h.label }}</span>
                        </li>
                    </ul>
                </section>

                <!-- ================= SKILLS ================= -->
                <section v-if="has('skills')" id="skills" class="relative mx-auto max-w-6xl px-5 py-20">
                    <span class="ak-mark" aria-hidden="true">{{ sectionLabels.skills }}</span>
                    <div data-reveal class="relative pt-10">
                        <span class="ak-eyebrow"><span class="ak-eyebrow-num">{{ num('skills') }}</span>{{ sectionLabels.skills }}</span>
                        <h2 v-if="text('skills', 'skills_title')" class="ak-display text-3xl font-semibold md:text-5xl">
                            <template v-for="(s, i) in emphasize(text('skills', 'skills_title'))" :key="i"><em v-if="s.em" class="font-medium">{{ s.t }}</em><template v-else>{{ s.t }}</template></template>
                        </h2>
                        <p v-if="text('skills', 'skills_subtitle')" class="ak-display mt-4 max-w-xl text-xl italic">{{ text('skills', 'skills_subtitle') }}</p>
                        <p v-if="text('skills', 'skills_description')" class="ak-muted mt-3 max-w-xl whitespace-pre-line leading-relaxed">{{ text('skills', 'skills_description') }}</p>
                        <div class="h-10" />
                    </div>

                    <div class="relative grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div v-for="(g, gi) in skillGroups" :key="gi" data-reveal :style="{ '--d': `${gi * 80}ms` }" class="ak-card rounded-3xl p-7">
                            <h3 v-if="g.title" class="ak-display mb-5 text-xl font-semibold">{{ g.title }}</h3>
                            <div v-for="(s, si) in g.items" :key="si" class="mb-4 last:mb-0">
                                <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                    <span class="flex items-center gap-2">
                                        <template v-if="flag('skills', 'skills_show_icon', true)">
                                            <img v-if="isUrl(s.icon)" :src="s.icon" alt="" class="h-4 w-4" />
                                            <component :is="iconFor(s.icon)" v-else-if="iconFor(s.icon)" class="h-4 w-4" />
                                            <span v-else-if="isGlyph(s.icon)" aria-hidden="true">{{ s.icon }}</span>
                                        </template>
                                        {{ s.name }}
                                    </span>
                                    <span v-if="flag('skills', 'skills_show_level', true) && s.pct !== null" class="tabular-nums">{{ Math.round(s.pct) }}%</span>
                                    <span v-else-if="flag('skills', 'skills_show_level', true) && s.label" class="ak-chip">{{ s.label }}</span>
                                </div>
                                <div v-if="flag('skills', 'skills_show_level', true) && s.pct !== null" class="h-1.5 rounded-full bg-[var(--line)]" role="progressbar" :aria-valuenow="Math.round(s.pct)" aria-valuemin="0" aria-valuemax="100" :aria-label="s.name">
                                    <div class="ak-bar relative h-full overflow-hidden rounded-full bg-[var(--ink)]" :style="{ '--w': `${s.pct}%`, '--sd': `${(gi * 3 + si) * 90}ms` }" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- technology marquee (the list is rendered twice so the loop is endless) -->
                    <div class="relative my-12 overflow-hidden border-y border-[var(--line)] py-5" role="group" aria-label="Technologies">
                        <div class="ak-marquee flex w-max">
                            <ul v-for="n in 2" :key="n" :aria-hidden="n === 2 ? 'true' : undefined" class="ak-muted flex items-center gap-12 whitespace-nowrap px-6 text-sm">
                                <li v-for="(s, i) in skills" :key="i" class="flex items-center gap-2">
                                    <template v-if="flag('skills', 'skills_show_icon', true)">
                                        <img v-if="isUrl(s.icon)" :src="s.icon" alt="" class="h-7 w-7" />
                                        <component :is="iconFor(s.icon)" v-else-if="iconFor(s.icon)" class="h-6 w-6" />
                                        <span v-else-if="isGlyph(s.icon)" class="text-xl leading-none" aria-hidden="true">{{ s.icon }}</span>
                                    </template>
                                    {{ s.name }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- ================= JOURNEY ================= -->
                <section v-if="has('journey')" id="journey" class="relative mx-auto max-w-6xl px-5 py-20">
                    <span class="ak-mark" aria-hidden="true">{{ sectionLabels.journey }}</span>
                    <div data-reveal class="relative pt-10">
                        <span class="ak-eyebrow"><span class="ak-eyebrow-num">{{ num('journey') }}</span>{{ sectionLabels.journey }}</span>
                        <h2 v-if="text('journey', 'journey_title')" class="ak-display text-3xl font-semibold md:text-5xl">
                            <template v-for="(s, i) in emphasize(text('journey', 'journey_title'))" :key="i"><em v-if="s.em" class="font-medium">{{ s.t }}</em><template v-else>{{ s.t }}</template></template>
                        </h2>
                        <p v-if="text('journey', 'journey_subtitle')" class="ak-muted mt-4 max-w-xl leading-relaxed">{{ text('journey', 'journey_subtitle') }}</p>
                        <div class="h-10" />
                    </div>

                    <div class="relative ml-2.5 flex max-w-xl flex-col gap-10 border-l border-[var(--rule)] pl-10">
                        <div v-for="(item, index) in journey" :key="index" data-reveal :style="{ '--d': `${Math.min((index + 1) * 110, 420)}ms` }" class="relative">
                            <span class="ak-card absolute -left-[3.1rem] top-0 flex h-5 w-5 items-center justify-center rounded-full border-[var(--rule)]"><span class="h-2 w-2 rounded-full bg-[var(--ink)]" /></span>
                            <span v-if="item.period" class="ak-display text-xs font-semibold">{{ item.period }}</span>
                            <h3 v-if="item.title" class="ak-display text-lg font-semibold">{{ item.title }}</h3>
                            <p v-if="item.company" class="ak-muted mb-2 text-sm">@ {{ item.company }}</p>
                            <p v-if="item.description" class="ak-muted mb-3 whitespace-pre-line text-sm leading-relaxed">{{ item.description }}</p>
                            <ul v-if="item.tags.length" class="flex flex-wrap gap-2">
                                <li v-for="(t, ti) in item.tags" :key="ti" class="ak-chip">{{ t }}</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- ================= PROJECTS ================= -->
                <section v-if="has('projects')" id="projects" class="relative mx-auto max-w-6xl px-5 py-20">
                    <span class="ak-mark" aria-hidden="true">{{ sectionLabels.projects }}</span>
                    <div data-reveal class="relative pt-10">
                        <span class="ak-eyebrow"><span class="ak-eyebrow-num">{{ num('projects') }}</span>{{ sectionLabels.projects }}</span>
                        <h2 v-if="text('projects', 'projects_title')" class="ak-display text-3xl font-semibold md:text-5xl">
                            <template v-for="(s, i) in emphasize(text('projects', 'projects_title'))" :key="i"><em v-if="s.em" class="font-medium">{{ s.t }}</em><template v-else>{{ s.t }}</template></template>
                        </h2>
                        <p v-if="text('projects', 'projects_subtitle')" class="ak-display mt-4 max-w-xl text-xl italic">{{ text('projects', 'projects_subtitle') }}</p>
                        <p v-if="text('projects', 'projects_description')" class="ak-muted mt-3 max-w-xl whitespace-pre-line leading-relaxed">{{ text('projects', 'projects_description') }}</p>
                        <div class="h-10" />
                    </div>

                    <!-- Click a row to open/close its details -->
                    <div class="relative border-t border-[var(--line)]">
                        <div
                            v-for="(p, i) in projects"
                            :key="i"
                            data-reveal
                            :style="{ '--d': `${Math.min((i + 1) * 70, 350)}ms` }"
                            class="ak-prow cursor-pointer border-b border-[var(--line)] px-2 py-6"
                            @click="toggleRow(i)"
                        >
                            <div class="flex flex-col gap-3 md:flex-row md:items-center" role="button" tabindex="0" :aria-expanded="openRow === i" @keydown.enter.prevent="toggleRow(i)" @keydown.space.prevent="toggleRow(i)">
                                <span class="ak-display italic">{{ pad(i) }}</span>
                                <div class="flex-1">
                                    <h3 class="ak-display text-xl font-semibold">{{ p.title }}</h3>
                                    <p v-if="p.description" class="ak-muted line-clamp-2 text-sm">{{ p.description }}</p>
                                </div>
                                <ul v-if="p.tags.length" class="flex flex-wrap gap-2">
                                    <li v-for="(t, ti) in p.tags" :key="ti" class="ak-chip">{{ t }}</li>
                                </ul>
                            </div>

                            <div v-if="openRow === i" class="ak-open mt-6 grid grid-cols-1 items-center gap-8" :class="p.image ? 'md:grid-cols-2' : ''">
                                <img v-if="p.image" :src="p.image" :alt="p.title" loading="lazy" class="aspect-[16/10] w-full rounded-2xl object-cover" />
                                <div>
                                    <span v-if="(featureFirst && i === 0) || p.category" class="text-xs">{{ [featureFirst && i === 0 ? 'Featured' : '', p.category].filter(Boolean).join(' · ') }}</span>
                                    <h3 class="ak-display my-2 text-3xl font-black">{{ p.title }}</h3>
                                    <p v-if="p.description" class="ak-muted leading-relaxed">{{ p.description }}</p>
                                    <button type="button" class="mt-4 font-medium hover:underline" @click.stop="openCase(i)">View case study <span>→</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ================= CONTACT ================= -->
                <section v-if="has('contact')" id="contact" class="relative mx-auto max-w-6xl overflow-hidden px-5 pb-6 pt-24">
                    <span class="ak-mark" aria-hidden="true">{{ sectionLabels.contact }}</span>
                    <div class="relative mt-24 grid grid-cols-1 gap-10 md:mt-40" :class="formEnabled ? 'md:grid-cols-2' : ''">
                        <div data-reveal>
                            <span class="ak-eyebrow"><span class="ak-eyebrow-num"><MessageSquare class="h-3.5 w-3.5" /></span>{{ num('contact') }}. {{ sectionLabels.contact }}</span>
                            <h2 v-if="text('contact', 'contact_title')" class="ak-display mb-3 mt-5 text-5xl font-black">
                                <template v-for="(s, i) in emphasize(text('contact', 'contact_title'))" :key="i"><em v-if="s.em" class="font-medium">{{ s.t }}</em><template v-else>{{ s.t }}</template></template>
                            </h2>
                            <p v-if="text('contact', 'contact_subtitle')" class="ak-display mb-3 max-w-sm text-xl italic">{{ text('contact', 'contact_subtitle') }}</p>
                            <p v-if="text('contact', 'contact_description')" class="ak-muted mb-8 max-w-xs whitespace-pre-line leading-relaxed">{{ text('contact', 'contact_description') }}</p>

                            <div v-if="email || phone || location" class="grid gap-3">
                                <a v-if="email" :href="`mailto:${email}`" class="ak-card ak-lift flex items-center gap-4 rounded-2xl px-4 py-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-[var(--line)]"><Mail class="h-5 w-5" /></span>
                                    <span class="min-w-0 flex-1"><strong class="block text-sm">Email me</strong><span class="ak-muted break-words text-sm">{{ email }}</span></span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--soft)]"><ArrowUpRight class="h-4 w-4" /></span>
                                </a>
                                <a v-if="phone" :href="`tel:${phone.replace(/[^+\d]/g, '')}`" class="ak-card ak-lift flex items-center gap-4 rounded-2xl px-4 py-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-[var(--line)]"><Phone class="h-5 w-5" /></span>
                                    <span class="min-w-0 flex-1"><strong class="block text-sm">Call me</strong><span class="ak-muted text-sm">{{ phone }}</span></span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--soft)]"><ArrowUpRight class="h-4 w-4" /></span>
                                </a>
                                <div v-if="location" class="ak-card flex items-center gap-4 rounded-2xl px-4 py-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-[var(--line)]"><MapPin class="h-5 w-5" /></span>
                                    <span class="min-w-0 flex-1"><strong class="block text-sm">Location</strong><span class="ak-muted text-sm">{{ location }}</span></span>
                                </div>
                            </div>

                            <ul v-if="contactSocials.length" class="mt-6 flex flex-wrap gap-3">
                                <li v-for="(s, i) in contactSocials" :key="i">
                                    <a :href="s.url" target="_blank" rel="noopener noreferrer" :aria-label="s.label" :title="s.label" class="ak-circle !h-10 !w-10"><component :is="socialIconFor(s.platform)" class="h-4 w-4" /></a>
                                </li>
                            </ul>
                        </div>

                        <form
                            v-if="formEnabled"
                            data-reveal
                            style="--d: 100ms"
                            :action="gfReady ? formAction : undefined"
                            :method="gfReady ? 'POST' : undefined"
                            :target="gfReady ? 'ak-gform-frame' : undefined"
                            class="ak-card flex flex-col gap-3 rounded-3xl p-3"
                            @submit="onSubmit"
                        >
                            <input v-model="fName" :name="gfReady ? `entry.${gf.name}` : 'name'" type="text" placeholder="Name" aria-label="Name" autocomplete="name" class="ak-input" />
                            <input v-model="fEmail" :name="gfReady ? `entry.${gf.email}` : 'email'" type="email" placeholder="Email" aria-label="Email" autocomplete="email" class="ak-input" />
                            <textarea v-model="fMessage" :name="gfReady ? `entry.${gf.message}` : 'message'" placeholder="Message" aria-label="Message" class="ak-input min-h-[200px] flex-1" />
                            <button type="submit" class="ak-btn-solid w-full justify-center !rounded-xl !py-3.5" :disabled="sending">{{ sending ? 'Sending…' : 'Submit' }}</button>
                            <p class="ak-muted min-h-[1.2em] px-1 text-sm" role="status">{{ formStatus }}</p>
                            <iframe v-if="gfReady" name="ak-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                        </form>
                    </div>

                    <footer v-if="heroName || logoText" class="ak-muted relative mt-20 flex flex-wrap justify-between gap-3 border-t border-[var(--line)] pt-5 text-sm">
                        <span>© {{ year }} {{ heroName ?? logoText }}</span>
                        <a href="#home" class="inline-flex items-center gap-1 hover:text-[var(--ink)]" @click="onNavClick($event, '#home')">Back to top<ArrowUp class="h-4 w-4" /></a>
                    </footer>
                </section>
            </main>
        </div>

        <!-- ================= CASE STUDY PAGE ================= -->
        <dialog ref="modalEl" class="ak-modal" aria-labelledby="ak-case-title" @keydown="onModalKey" @click="onModalClick">
            <template v-if="caseProject">
                <div class="sticky top-0 z-10 flex items-center justify-between gap-6 border-b border-[var(--line)] bg-[var(--surface)] px-6 py-4 md:px-16">
                    <button type="button" class="ak-muted flex items-center gap-2 text-xs font-medium uppercase hover:text-[var(--ink)]" @click="closeCase"><ArrowLeft class="h-4 w-4" />Back to projects</button>
                    <div class="ak-muted flex items-center gap-3 text-xs">
                        <button type="button" aria-label="Previous project" class="ak-circle disabled:cursor-not-allowed disabled:opacity-30" :disabled="caseIndex === 0" @click="stepCase(-1)"><ArrowLeft class="h-4 w-4" /></button>
                        <span class="text-[var(--ink)]">{{ pad(caseIndex) }}</span><span class="h-px w-8 bg-[var(--rule)]" /><span>{{ pad(projects.length - 1) }}</span>
                        <button type="button" aria-label="Next project" class="ak-circle disabled:cursor-not-allowed disabled:opacity-30" :disabled="caseIndex === projects.length - 1" @click="stepCase(1)"><ArrowRight class="h-4 w-4" /></button>
                    </div>
                </div>

                <div class="mx-auto max-w-6xl px-6 md:px-16">
                    <section class="grid grid-cols-1 items-center gap-12 border-b border-[var(--line)] py-12 md:py-20 lg:grid-cols-2">
                        <div>
                            <span v-if="caseProject.category" class="text-xs">{{ caseProject.category }}</span>
                            <h2 id="ak-case-title" class="ak-display mb-5 mt-3 text-4xl font-semibold leading-none md:text-6xl">{{ caseProject.title }}</h2>
                            <p v-if="caseProject.description" class="ak-muted max-w-lg leading-relaxed">{{ caseProject.description }}</p>
                            <ul v-if="caseProject.tags.length" class="mt-6 flex flex-wrap gap-2"><li v-for="(t, ti) in caseProject.tags" :key="ti" class="ak-chip">{{ t }}</li></ul>
                            <div v-if="caseProject.live || caseProject.repo" class="mt-7 flex flex-wrap gap-4">
                                <a v-if="caseProject.live" :href="caseProject.live" target="_blank" rel="noopener noreferrer" class="bg-[var(--ink)] px-4 py-3 text-xs font-medium uppercase text-[var(--on-ink)]">View Live ↗</a>
                                <a v-if="caseProject.repo" :href="caseProject.repo" target="_blank" rel="noopener noreferrer" class="border border-[var(--rule)] px-4 py-3 text-xs font-medium uppercase">GitHub Repository ↗</a>
                            </div>
                        </div>
                        <div v-if="caseProject.image" class="ak-card rounded-3xl p-3">
                            <img :src="caseProject.image" :alt="`${caseProject.title} screenshot`" class="aspect-[16/10] w-full rounded-xl object-cover" />
                        </div>
                    </section>

                    <section v-if="caseProject.description || caseProject.features.length || caseProject.category || caseProject.tags.length" class="grid grid-cols-1 gap-10 border-b border-[var(--line)] py-12 lg:grid-cols-3">
                        <article v-if="caseProject.description">
                            <h2 class="mb-4 text-lg font-semibold">About the Project</h2>
                            <p class="ak-muted whitespace-pre-line leading-relaxed">{{ caseProject.description }}</p>
                        </article>
                        <article v-if="caseProject.features.length">
                            <h2 class="mb-4 text-lg font-semibold">Key Features</h2>
                            <ul class="grid gap-3">
                                <li v-for="(f, fi) in caseProject.features" :key="fi" class="flex gap-3 rounded-2xl border border-[var(--line)] p-3 text-sm">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[var(--ink)] text-xs text-[var(--on-ink)]">+</span>{{ f }}
                                </li>
                            </ul>
                        </article>
                        <aside v-if="caseProject.category || caseProject.tags.length" class="h-fit rounded-md border border-[var(--line)] p-5">
                            <h2 class="mb-3 text-lg font-semibold">Project Details</h2>
                            <dl class="ak-dl m-0">
                                <template v-if="caseProject.category"><dt>Project Type</dt><dd>{{ caseProject.category }}</dd></template>
                                <template v-if="caseProject.tags.length"><dt>Tech Stack</dt><dd>{{ caseProject.tags.join(', ') }}</dd></template>
                            </dl>
                        </aside>
                    </section>

                    <section v-if="caseProject.image" class="py-12 pb-20">
                        <h2 class="ak-display mb-5 text-3xl font-semibold">Project Preview</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <figure class="m-0 overflow-hidden rounded-2xl border border-[var(--line)]">
                                <img :src="caseProject.image" :alt="`${caseProject.title} project screenshot`" class="w-full" />
                                <figcaption class="ak-muted p-2 text-xs">{{ caseProject.title }}</figcaption>
                            </figure>
                        </div>
                    </section>
                </div>
            </template>
        </dialog>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;0,900;1,500&family=IBM+Plex+Sans:wght@400;500;600&display=swap');

/* ---------- Foundations ---------- */
.ak-root {
    background: var(--paper);
    color: var(--ink);
    font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    transition:
        background-color 0.4s ease,
        color 0.4s ease;
}
.ak-display {
    font-family: 'Fraunces', Georgia, serif;
}
.ak-muted {
    color: var(--muted);
}
.ak-card {
    border: 1px solid var(--line);
    background: var(--surface);
}
.ak-chip {
    display: inline-block;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    color: var(--muted);
}
.ak-outline {
    color: transparent;
    -webkit-text-stroke: 1.5px var(--ink);
}
.ak-root :where(a, button, input, textarea, [role='button']):focus-visible {
    outline: 2px solid var(--ink);
    outline-offset: 3px;
    border-radius: 0.5rem;
}
.ak-mark {
    position: absolute;
    top: 1rem;
    left: 50%;
    transform: translateX(-50%);
    font-family: 'Fraunces', Georgia, serif;
    font-weight: 900;
    font-size: clamp(4.5rem, 15vw, 10rem);
    line-height: 1;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--ink) 5%, transparent);
    user-select: none;
    pointer-events: none;
    white-space: nowrap;
}
.ak-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 0.25rem 1rem 0.25rem 0.25rem;
    font-size: 0.875rem;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
}
.ak-eyebrow-num {
    display: inline-flex;
    height: 1.75rem;
    width: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--ink);
    color: var(--on-ink);
    font-size: 0.75rem;
    font-weight: 600;
}

/* ---------- Buttons ---------- */
.ak-btn-solid,
.ak-btn-line,
.ak-btn-card {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 999px;
    cursor: pointer;
    transition:
        transform 0.25s ease,
        background-color 0.25s ease;
}
.ak-btn-solid {
    background: var(--ink);
    color: var(--on-ink);
    padding: 0.75rem 1.75rem;
    font-weight: 500;
    box-shadow: 0 10px 25px -10px rgb(0 0 0 / 0.4);
}
.ak-btn-line {
    border: 1px solid var(--line);
    padding: 0.75rem 1.75rem;
}
.ak-btn-line:hover {
    background: var(--surface);
}
.ak-btn-card {
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.625rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 600;
}
.ak-btn-solid:hover,
.ak-btn-card:hover,
.ak-lift:hover {
    transform: translateY(-2px);
}
.ak-btn-solid[disabled] {
    opacity: 0.7;
    pointer-events: none;
}
.ak-lift {
    transition: transform 0.25s ease;
}
.ak-circle {
    display: inline-flex;
    height: 2.25rem;
    width: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--surface);
    cursor: pointer;
    transition:
        background-color 0.25s ease,
        transform 0.25s ease;
}
.ak-circle:hover:not([disabled]) {
    background: var(--soft);
    transform: translateY(-2px);
}
.ak-input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 0.875rem 1rem;
    color: var(--ink);
    font: inherit;
}
.ak-input::placeholder {
    color: var(--muted);
}
.ak-input:focus {
    outline: none;
    border-color: var(--ink);
}

/* ---------- Navbar ---------- */
.ak-progress {
    position: fixed;
    inset: 0 0 auto 0;
    height: 3px;
    background: var(--ink);
    transform: scaleX(0);
    transform-origin: left;
    pointer-events: none;
    z-index: 60;
}
.ak-nav {
    transition:
        max-width 0.45s ease,
        background 0.45s ease,
        border-color 0.45s ease,
        box-shadow 0.45s ease;
}
.ak-nav.is-scrolled {
    max-width: min(1100px, 85vw);
    background: color-mix(in srgb, var(--paper) 85%, transparent);
    border-color: var(--line);
    box-shadow: 0 8px 32px rgb(0 0 0 / 0.08);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}
.ak-navlink {
    transition: color 0.2s ease;
}
.ak-navlink:hover,
.ak-navlink[data-active='true'] {
    color: var(--ink);
}
.ak-js:not(.ak-ready) header {
    opacity: 0;
    visibility: hidden;
}
header {
    transition:
        opacity 0.9s ease,
        visibility 0.9s ease;
}

/* ---------- Stage + cinematic intro ---------- */
.ak-stage {
    transition:
        opacity 0.9s ease,
        visibility 0.9s ease,
        transform 0.9s ease;
}
.ak-js:not(.ak-ready) .ak-stage {
    opacity: 0;
    visibility: hidden;
    transform: translateY(18px);
    pointer-events: none;
}
.ak-intro {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--paper);
    overflow: hidden;
    transition:
        opacity 0.8s ease,
        visibility 0.8s ease;
}
.ak-intro.is-closing {
    opacity: 0;
    visibility: hidden;
}
.ak-intro-box {
    text-align: center;
    padding: 0 24px;
    transition:
        opacity 0.8s ease,
        transform 0.8s ease;
}
.ak-intro.is-exiting .ak-intro-box {
    opacity: 0.15;
    transform: scale(1.06);
}
.ak-intro-line {
    opacity: 0;
    transform: translateY(14px) scale(0.96);
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.ak-intro-line.show {
    opacity: 1;
    transform: none;
}

/* ---------- Hero ---------- */
.ak-row {
    display: block;
    overflow: hidden;
}
.ak-row > span {
    display: inline-block;
    transform: translateY(105%);
    animation: ak-rise 0.9s cubic-bezier(0.16, 0.84, 0.32, 1) forwards;
    animation-play-state: paused;
    animation-delay: 0.1s;
}
.ak-row:nth-of-type(2) > span {
    animation-delay: 0.18s;
}
.ak-ready .ak-row > span {
    animation-play-state: running;
}
.ak-art {
    isolation: isolate;
}
.ak-float {
    animation: ak-float 7s ease-in-out infinite;
}
.ak-orbit {
    position: absolute;
    z-index: -1;
    border: 1px solid color-mix(in srgb, var(--ink) 15%, transparent);
    border-radius: 50%;
    pointer-events: none;
    animation: ak-drift 18s ease-in-out infinite alternate;
}
.ak-orbit-1 {
    width: 112%;
    height: 58%;
    top: 22%;
    left: -6%;
    transform: rotate(-24deg);
}
.ak-orbit-2 {
    width: 94%;
    height: 82%;
    top: 8%;
    left: 2%;
    transform: rotate(38deg);
    animation-delay: -5s;
}
.ak-orbit-3 {
    width: 72%;
    height: 112%;
    top: -8%;
    left: 24%;
    transform: rotate(58deg);
    animation-delay: -10s;
}
@media (max-width: 759px) {
    .ak-orbit-2,
    .ak-orbit-3 {
        display: none;
    }
}

/* ---------- Skills ---------- */
.ak-bar {
    width: var(--w, 0%);
}
.ak-js [data-reveal] .ak-bar {
    width: 0;
    transition: width 1.1s cubic-bezier(0.22, 1, 0.36, 1) var(--sd, 0ms);
}
.ak-js [data-reveal].is-in .ak-bar {
    width: var(--w, 0%);
}
.ak-bar::after {
    position: absolute;
    inset: 0;
    content: '';
    background: linear-gradient(105deg, transparent 20%, rgb(255 255 255 / 0.5) 50%, transparent 80%);
    transform: translateX(-110%);
    transition: transform 0.9s ease 0.55s;
}
.ak-js [data-reveal].is-in .ak-bar::after {
    transform: translateX(110%);
}
.ak-marquee {
    animation: ak-scroll 34s linear infinite;
}
.ak-marquee:hover {
    animation-play-state: paused;
}

/* ---------- Projects ---------- */
.ak-prow {
    transition: background-color 0.25s ease;
}
.ak-prow:hover {
    background: color-mix(in srgb, var(--surface) 60%, transparent);
}
.ak-open {
    animation: ak-modal-in 0.35s ease-out both;
}

/* ---------- Case study dialog ---------- */
.ak-modal {
    width: 100%;
    max-width: none;
    height: 100%;
    max-height: none;
    margin: 0;
    padding: 0;
    overflow-y: auto;
    border: 0;
    background: var(--surface);
    color: var(--ink);
}
.ak-modal[open] {
    animation: ak-modal-in 0.28s ease-out both;
}
.ak-modal::backdrop {
    background: rgb(0 0 0 / 0.45);
    backdrop-filter: blur(8px);
}
.ak-dl dt {
    padding-top: 10px;
    font-size: 0.72rem;
    text-transform: uppercase;
    color: var(--muted);
}
.ak-dl dd {
    margin: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid color-mix(in srgb, var(--muted) 30%, transparent);
}

/* ---------- Scroll reveal (only after mount, so SSR / static export stay visible) ---------- */
.ak-js [data-reveal] {
    opacity: 0;
    translate: 0 24px;
    filter: blur(6px);
    transition:
        opacity 0.75s cubic-bezier(0.22, 1, 0.36, 1) var(--d, 0ms),
        translate 0.75s cubic-bezier(0.22, 1, 0.36, 1) var(--d, 0ms),
        filter 0.75s cubic-bezier(0.22, 1, 0.36, 1) var(--d, 0ms);
}
.ak-js [data-reveal].is-in {
    opacity: 1;
    translate: 0 0;
    filter: blur(0);
}

@keyframes ak-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}
@keyframes ak-scroll {
    to {
        transform: translateX(-50%);
    }
}
@keyframes ak-rise {
    to {
        transform: translateY(0);
    }
}
@keyframes ak-drift {
    to {
        translate: 8px -5px;
    }
}
@keyframes ak-modal-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

/* ---------- Reduced motion: show everything, no movement ---------- */
@media (prefers-reduced-motion: reduce) {
    .ak-root *,
    .ak-root *::before,
    .ak-root *::after {
        animation: none !important;
        transition: none !important;
    }
    .ak-row > span {
        transform: none;
    }
    .ak-js [data-reveal] {
        opacity: 1;
        translate: none;
        filter: none;
    }
    .ak-js [data-reveal] .ak-bar {
        width: var(--w, 0%);
    }
    .ak-bar::after {
        display: none;
    }
    .ak-js:not(.ak-ready) .ak-stage,
    .ak-js:not(.ak-ready) header {
        opacity: 1;
        visibility: visible;
        transform: none;
    }
}
</style>
