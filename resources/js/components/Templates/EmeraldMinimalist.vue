<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { resolvePortfolioMediaUrl } from '@/lib/media';

interface GoogleFormIntegration {
    url: string;
    nameEntry: string;
    emailEntry: string;
    messageEntry: string;
}

interface PortfolioIntegration {
    google_form_url?: string | null;
    google_form_name_entry?: string | null;
    google_form_email_entry?: string | null;
    google_form_message_entry?: string | null;
}

const props = defineProps<{
    content: Record<string, any>;
    designSettings?: Record<string, any>;
    portfolio?: PortfolioIntegration;
}>();

const activeCategory = ref('All');
const menuOpen = ref(false);
const scrolled = ref(false);
const progress = ref(0);

const navbar = computed(() => props.content?.navbar || {});
const hero = computed(() => props.content?.header || {});
const about = computed(() => props.content?.about || {});
const journey = computed(() => props.content?.journey || {});
const skills = computed(() => props.content?.skills || {});
const projectSection = computed(() => props.content?.projects || {});
const contact = computed(() => props.content?.contact || {});
const googleForm = computed<GoogleFormIntegration | null>(() => {
    const integration = props.portfolio;
    const entryId = (value?: string | null) => value?.trim().replace(/^entry\./, '');
    if (
        !integration?.google_form_url ||
        !entryId(integration.google_form_name_entry) ||
        !entryId(integration.google_form_email_entry) ||
        !entryId(integration.google_form_message_entry)
    ) return null;

    return {
        url: integration.google_form_url,
        nameEntry: entryId(integration.google_form_name_entry)!,
        emailEntry: entryId(integration.google_form_email_entry)!,
        messageEntry: entryId(integration.google_form_message_entry)!,
    };
});
const formReady = computed(() => contact.value.contact_form_enabled !== false && !!googleForm.value);
const formAction = computed(() => (googleForm.value?.url ?? '').replace(/\/viewform.*$/, '/formResponse'));
const sent = ref(false);
const sending = ref(false);
const onSubmit = (event: Event) => {
    const form = event.target as HTMLFormElement;
    sent.value = false;
    sending.value = true;
    window.setTimeout(() => {
        sending.value = false;
        sent.value = true;
        form.reset();
    }, 700);
};

const allProjects = computed(() => projectSection.value.projects_list || projectSection.value.projects || []);

const categories = computed(() => {
    const set = new Set<string>();
    allProjects.value.forEach((p: any) => p.category && set.add(p.category));
    return ['All', ...Array.from(set)];
});

const filteredProjects = computed(() => {
    if (activeCategory.value === 'All') return allProjects.value;
    return allProjects.value.filter((p: any) => p.category === activeCategory.value);
});

const heroWords = computed(() => (hero.value.hero_name ? String(hero.value.hero_name).split(' ') : []));

const navLinks = computed(() => {
    const links: { href: string; label: string }[] = [{ href: '#home', label: 'Home' }];
    if (about.value.about_title || about.value.about_description) links.push({ href: '#about', label: 'About' });
    if (journey.value.journey_list?.length) links.push({ href: '#journey', label: 'Experience' });
    if (skills.value.skills_list?.length) links.push({ href: '#skills', label: 'Skills' });
    if (allProjects.value.length) links.push({ href: '#projects', label: 'Projects' });
    links.push({ href: '#contact', label: 'Contact' });
    return links;
});

// Marquee strip: repeat the skill labels so the loop is seamless
const marqueeItems = computed(() => {
    const labels = (skills.value.skills_list || []).map((s: any) => s.label).filter(Boolean);
    if (!labels.length) return [];
    let out = [...labels];
    while (out.length < 8) out = [...out, ...labels];
    return out;
});

/* ---------- Circular skill meters ---------- */
const RADIUS = 52;
const CIRC = 2 * Math.PI * RADIUS;
const counts = ref<Record<number, number>>({});
const timers: number[] = [];

function skillPercent(s: any): number {
    const n = Number(s?.percent) || 100;
    return Math.max(0, Math.min(100, n));
}

function animateCount(idx: number, target: number, delay: number) {
    const reduce = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    if (reduce) {
        counts.value[idx] = target;
        return;
    }
    const t = window.setTimeout(() => {
        const start = performance.now();
        const dur = 1900;
        const tick = (now: number) => {
            const p = Math.min(1, (now - start) / dur);
            counts.value[idx] = Math.round(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }, delay);
    timers.push(t);
}

/* ---------- Scroll observers ---------- */
let observer: IntersectionObserver | null = null;
let skillObserver: IntersectionObserver | null = null;

const vReveal = {
    mounted(el: HTMLElement, binding: { value?: number }) {
        if (binding.value) el.style.transitionDelay = `${binding.value * 90}ms`;
        if (typeof IntersectionObserver === 'undefined') {
            el.classList.add('is-in');
            return;
        }
        if (!observer) {
            observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-in');
                            observer?.unobserve(entry.target);
                        }
                    });
                },
                { threshold: 0.15, rootMargin: '0px 0px -8% 0px' },
            );
        }
        observer.observe(el);
    },
};

const vSkill = {
    mounted(el: HTMLElement, binding: { value: { idx: number; target: number } }) {
        const { idx, target } = binding.value;
        el.style.transitionDelay = `${(idx % 3) * 90}ms`;
        if (typeof IntersectionObserver === 'undefined') {
            el.classList.add('is-in');
            counts.value[idx] = target;
            return;
        }
        if (!skillObserver) {
            skillObserver = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            const node = entry.target as HTMLElement;
                            node.classList.add('is-in');
                            animateCount(Number(node.dataset.idx), Number(node.dataset.target), Number(node.dataset.idx) * 110 + 250);
                            skillObserver?.unobserve(node);
                        }
                    });
                },
                { threshold: 0.3 },
            );
        }
        el.dataset.idx = String(idx);
        el.dataset.target = String(target);
        skillObserver.observe(el);
    },
};

function onScroll() {
    const y = window.scrollY;
    scrolled.value = y > 24;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    progress.value = max > 0 ? Math.min(100, (y / max) * 100) : 0;
}

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    observer?.disconnect();
    skillObserver?.disconnect();
    observer = null;
    skillObserver = null;
    timers.forEach((t) => clearTimeout(t));
});
</script>

<template>
    <div class="folio min-h-screen bg-[#06231E] text-white overflow-x-hidden selection:bg-[#5CF0BD] selection:text-[#04201A]">
        <!-- Scroll progress -->
        <div class="fixed top-0 left-0 h-[3px] z-[60] bg-gradient-to-r from-[#00A388] via-[#5CF0BD] to-[#BDF3E0]" :style="{ width: progress + '%' }"></div>

        <!-- Navbar -->
        <nav
            class="fixed top-0 left-0 w-full z-50 transition-all duration-500"
            :class="scrolled || menuOpen ? 'bg-[#06231E]/80 backdrop-blur-xl border-b border-white/10 shadow-[0_8px_30px_rgba(0,0,0,0.25)]' : 'bg-transparent border-b border-transparent'"
        >
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <a href="#home" class="flex items-center gap-3 group" aria-label="Back to top">
                    <img v-if="navbar.show_logo !== false && navbar.show_logo !== 0 && navbar.show_logo !== '0' && navbar.show_logo !== 'false'" :src="navbar.logo_image || '/logo.svg'" :alt="navbar.logo_text" class="h-10 w-10 object-contain transition-transform duration-500 group-hover:scale-105" />
                </a>

                <div class="hidden md:flex items-center gap-1">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="px-4 py-2 text-sm font-medium rounded-full text-white/75 hover:text-white hover:bg-white/10 transition-colors"
                    >
                        {{ link.label }}
                    </a>
                </div>

                <button
                    class="md:hidden w-11 h-11 rounded-xl bg-white/10 flex flex-col items-center justify-center gap-[5px]"
                    :aria-expanded="menuOpen"
                    aria-label="Toggle menu"
                    @click="menuOpen = !menuOpen"
                >
                    <span class="burger-line bg-white" :class="menuOpen ? 'translate-y-[7px] rotate-45' : ''"></span>
                    <span class="burger-line bg-white" :class="menuOpen ? 'opacity-0' : ''"></span>
                    <span class="burger-line bg-white" :class="menuOpen ? '-translate-y-[7px] -rotate-45' : ''"></span>
                </button>
            </div>

            <Transition name="drop">
                <div v-if="menuOpen" class="md:hidden px-6 pb-6 flex flex-col gap-1">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="px-4 py-3 rounded-xl text-white font-medium hover:bg-white/10"
                        @click="menuOpen = false"
                    >
                        {{ link.label }}
                    </a>
                </div>
            </Transition>
        </nav>

        <!-- Hero -->
        <header id="home" class="hero min-h-screen min-h-[100svh] flex items-center justify-center relative px-6 pt-28 pb-20 text-white overflow-hidden">
            <div class="absolute inset-0 bg-[#06231E]"></div>
            <div class="orb orb-a"></div>
            <div class="orb orb-b"></div>
            <div class="orb orb-c"></div>
            <div class="absolute inset-0 hero-grid pointer-events-none"></div>

            <div class="max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-14 items-center relative z-10">
                <div class="lg:col-span-7 space-y-8 order-2 lg:order-1">
                    <div v-if="hero.hero_greeting" class="hero-in inline-flex items-center gap-2.5 rounded-full border border-white/15 bg-white/[0.07] backdrop-blur px-4 py-2 text-sm font-medium text-[#BDF3E0]" style="--d: 0.1s">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full rounded-full bg-[#5CF0BD] opacity-70 animate-ping"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-[#5CF0BD]"></span>
                        </span>
                        {{ hero.hero_greeting }}
                    </div>

                    <h1 v-if="hero.hero_name" class="display text-5xl sm:text-7xl lg:text-[88px] font-bold tracking-[-0.03em] leading-[1.02]">
                        <span v-for="(word, i) in heroWords" :key="i" class="word-mask">
                            <span class="word" :style="{ '--d': 0.25 + i * 0.12 + 's' }">{{ word }}</span>
                        </span>
                    </h1>

                    <div v-if="hero.hero_role" class="hero-in display text-xl sm:text-2xl lg:text-3xl font-semibold tracking-tight shimmer-text" style="--d: 0.7s">
                        {{ hero.hero_role }}
                    </div>

                    <p v-if="hero.hero_description" class="hero-in text-[#A9C9BF] text-base sm:text-lg max-w-xl leading-relaxed" style="--d: 0.85s">
                        {{ hero.hero_description }}
                    </p>

                    <div v-if="hero.hero_cta_primary_text" class="hero-in flex flex-wrap items-center gap-4 pt-2" style="--d: 1s">
                        <a :href="hero.hero_cta_primary_url || '#projects'" class="btn-glow group relative inline-flex items-center gap-3 px-8 py-4 rounded-full bg-[#5CF0BD] text-[#04201A] font-semibold text-sm tracking-wide transition-transform duration-300 hover:-translate-y-0.5">
                            <span>{{ hero.hero_cta_primary_text }}</span>
                            <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-5 flex justify-center relative order-1 lg:order-2">
                    <div class="hero-in portrait-wrap relative w-64 h-64 sm:w-[380px] sm:h-[380px]" style="--d: 0.4s">
                        <div class="ring ring-1"></div>
                        <div class="ring ring-2"></div>
                        <div class="blob absolute inset-4 bg-gradient-to-br from-[#0E8F7C] to-[#06352D] overflow-hidden flex items-center justify-center shadow-[0_30px_80px_rgba(0,0,0,0.45)] border border-white/10">
                            <img v-if="hero.hero_image" :src="resolvePortfolioMediaUrl(hero.hero_image)" :alt="hero.hero_name" class="absolute inset-0 w-full h-full object-cover" />
                            <div v-else class="text-center space-y-3">
                                <span class="text-6xl block float-slow">🌿</span>
                                <span class="text-xs font-semibold tracking-widest text-[#BDF3E0] block">Emerald Portfolio</span>
                            </div>
                        </div>
                        <span class="dot dot-1"></span>
                        <span class="dot dot-2"></span>
                        <span class="dot dot-3"></span>
                    </div>
                </div>
            </div>

            <a href="#about" class="hero-in absolute bottom-8 left-1/2 -translate-x-1/2 hidden sm:flex flex-col items-center gap-2 text-white/50 hover:text-white/90 transition-colors" style="--d: 1.4s" aria-label="Scroll down">
                <span class="w-6 h-10 rounded-full border border-current flex justify-center pt-2">
                    <span class="scroll-wheel w-1 h-2 rounded-full bg-current"></span>
                </span>
            </a>
        </header>

        <!-- Skills marquee -->
        <div v-if="marqueeItems.length" class="relative bg-[#00695C] text-white py-5 overflow-hidden border-y border-[#0A7F70]" aria-hidden="true">
            <div class="marquee flex w-max gap-12 whitespace-nowrap">
                <template v-for="n in 2" :key="n">
                    <div class="flex gap-12 items-center">
                        <span v-for="(item, i) in marqueeItems" :key="n + '-' + i" class="display text-lg font-medium flex items-center gap-12">
                            {{ item }}
                            <svg class="w-4 h-4 text-[#9BF0D2]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 7.8L22 12l-7.8 2.2L12 22l-2.2-7.8L2 12l7.8-2.2z" /></svg>
                        </span>
                    </div>
                </template>
            </div>
        </div>

        <!-- About -->
        <section id="about" class="min-h-screen min-h-[100svh] flex items-center justify-center relative px-6 py-28 bg-[#06231E] overflow-hidden">
            <div class="orb orb-b opacity-50"></div>
            <div class="absolute inset-0 hero-grid pointer-events-none"></div>

            <div class="max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-16 items-center relative z-10">
                <div v-if="about.about_image" v-reveal class="rv rv-left lg:col-span-5 flex justify-center">
                    <div class="relative">
                        <div class="absolute -inset-4 rounded-[2.5rem] bg-gradient-to-br from-[#00A388] to-[#9BF0D2] opacity-30 rotate-3 blur-[2px]"></div>
                        <div class="relative w-72 h-[420px] sm:w-[360px] sm:h-[480px] rounded-[2rem] bg-[#0B3B33] border border-white/10 overflow-hidden shadow-[0_30px_80px_rgba(0,0,0,0.45)] group">
                            <img :src="resolvePortfolioMediaUrl(about.about_image)" :alt="about.about_title" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
                        </div>
                    </div>
                </div>

                <div v-reveal :class="about.about_image ? 'lg:col-span-7' : 'lg:col-span-12 max-w-3xl mx-auto text-center'" class="rv space-y-6">
                    <span class="section-tag">About me</span>
                    <h2 v-if="about.about_title" class="display text-4xl sm:text-6xl font-bold tracking-[-0.025em] leading-[1.05]">{{ about.about_title }}</h2>
                    <p v-if="about.about_description" class="text-[#A9C9BF] text-base sm:text-lg leading-relaxed max-w-2xl" :class="about.about_image ? '' : 'mx-auto'">
                        {{ about.about_description }}
                    </p>
                    <div v-if="about.about_cv_file" class="pt-4">
                        <a :href="resolvePortfolioMediaUrl(about.about_cv_file)" class="btn-glow group inline-flex items-center gap-3 px-8 py-4 rounded-full bg-[#5CF0BD] text-[#04201A] font-semibold text-sm transition-transform duration-300 hover:-translate-y-0.5">
                            <span>Download CV / Resume</span>
                            <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-y-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v14M6 12l6 6 6-6" /></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Journey -->
        <section v-if="journey.journey_list?.length" id="journey" class="min-h-screen min-h-[100svh] flex flex-col justify-center relative px-6 py-28 bg-[#05201B] overflow-hidden border-t border-white/5">
            <div class="orb orb-a opacity-30"></div>
            <div class="absolute inset-0 hero-grid pointer-events-none"></div>

            <div class="max-w-4xl mx-auto w-full space-y-16 relative z-10">
                <div v-reveal class="rv text-center space-y-4 max-w-2xl mx-auto">
                    <span class="section-tag">Experience</span>
                    <h2 v-if="journey.journey_title" class="display text-4xl sm:text-6xl font-bold tracking-[-0.025em] leading-[1.05]">{{ journey.journey_title }}</h2>
                    <p v-if="journey.journey_subtitle" class="text-[#A9C9BF] text-base sm:text-lg">{{ journey.journey_subtitle }}</p>
                </div>

                <div class="relative pl-10 sm:pl-14">
                    <div v-reveal class="spine absolute left-[11px] sm:left-[19px] top-2 bottom-2 w-[2px] bg-gradient-to-b from-[#5CF0BD] via-[#00A388] to-transparent"></div>

                    <div class="space-y-8">
                        <div v-for="(item, idx) in journey.journey_list" :key="idx" v-reveal="idx % 3" class="rv relative">
                            <span class="absolute -left-10 sm:-left-14 top-8 w-6 h-6 sm:w-10 sm:h-10 flex items-center justify-center">
                                <span class="timeline-dot w-4 h-4 rounded-full bg-[#06231E] border-[3px] border-[#5CF0BD]"></span>
                            </span>
                            <div class="card-lift p-7 sm:p-8 rounded-3xl bg-white/[0.05] border border-white/10 backdrop-blur space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span v-if="item.period" class="text-sm font-semibold text-[#9BF0D2] px-3.5 py-1 rounded-full bg-white/[0.07] border border-white/10">{{ item.period }}</span>
                                    <span v-if="item.company" class="text-sm font-semibold text-[#A9C9BF]">{{ item.company }}</span>
                                </div>
                                <h3 v-if="item.title" class="display text-xl sm:text-2xl font-semibold tracking-tight">{{ item.title }}</h3>
                                <p v-if="item.description" class="text-[#A9C9BF] text-[15px] leading-relaxed">{{ item.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Skills -->
        <section v-if="skills.skills_list?.length" id="skills" class="min-h-screen min-h-[100svh] flex flex-col justify-center relative px-6 py-28 bg-[#06231E] text-white overflow-hidden border-t border-white/5">
            <div class="orb orb-b opacity-60"></div>
            <div class="absolute inset-0 hero-grid pointer-events-none"></div>

            <div class="max-w-6xl mx-auto w-full space-y-16 relative z-10">
                <div v-reveal class="rv text-center space-y-4 max-w-2xl mx-auto">
                    <span class="section-tag">Expertise</span>
                    <h2 v-if="skills.skills_title" class="display text-4xl sm:text-6xl font-bold tracking-[-0.025em] leading-[1.05]">{{ skills.skills_title }}</h2>
                    <p v-if="skills.skills_description" class="text-[#A9C9BF] text-base sm:text-lg">{{ skills.skills_description }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="(skill, idx) in skills.skills_list"
                        :key="idx"
                        v-skill="{ idx, target: skillPercent(skill) }"
                        class="rv skill-card group relative p-8 rounded-3xl bg-white/[0.05] border border-white/10 backdrop-blur flex flex-col items-center gap-6 transition-colors duration-500 hover:bg-white/[0.09] hover:border-[#5CF0BD]/40"
                        :style="{
                            '--circ': CIRC,
                            '--off': CIRC * (1 - skillPercent(skill) / 100),
                            '--deg': skillPercent(skill) * 3.6 + 'deg',
                            '--bd': 0.25 + idx * 0.11 + 's',
                        }"
                    >
                        <div class="meter relative w-44 h-44">
                            <span class="halo"></span>
                            <svg viewBox="0 0 120 120" class="relative w-full h-full overflow-visible">
                                <defs>
                                    <linearGradient :id="'skillGrad' + idx" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0%" stop-color="#00A388" />
                                        <stop offset="100%" stop-color="#9BF0D2" />
                                    </linearGradient>
                                </defs>

                                <circle class="orbit" cx="60" cy="60" r="58" fill="none" stroke="rgba(155,240,210,0.3)" stroke-width="0.8" stroke-dasharray="1.5 5" />

                                <g transform="rotate(-90 60 60)">
                                    <circle cx="60" cy="60" :r="RADIUS" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="8" />
                                    <circle
                                        class="arc"
                                        cx="60"
                                        cy="60"
                                        :r="RADIUS"
                                        fill="none"
                                        :stroke="'url(#skillGrad' + idx + ')'"
                                        stroke-width="8"
                                        stroke-linecap="round"
                                        :stroke-dasharray="CIRC"
                                    />
                                    <g class="tip">
                                        <circle cx="112" cy="60" r="8" fill="rgba(92,240,189,0.25)" />
                                        <circle cx="112" cy="60" r="4" fill="#fff" />
                                    </g>
                                </g>
                            </svg>

                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="display text-4xl font-bold tracking-tight tabular-nums transition-transform duration-500 group-hover:scale-110">
                                    {{ counts[idx] ?? 0 }}<span class="text-xl text-[#5CF0BD]">%</span>
                                </span>
                            </div>
                        </div>

                        <span class="display font-semibold text-lg text-center">{{ skill.label }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Portfolio -->
        <section v-if="allProjects.length" id="projects" class="min-h-screen min-h-[100svh] flex flex-col justify-center relative px-6 py-28 bg-[#05201B] overflow-hidden border-t border-white/5">
            <div class="orb orb-a opacity-25"></div>
            <div class="absolute inset-0 hero-grid pointer-events-none"></div>

            <div class="max-w-7xl mx-auto w-full space-y-14 relative z-10">
                <div v-reveal class="rv text-center space-y-4 max-w-2xl mx-auto">
                    <span class="section-tag">Selected projects</span>
                    <h2 v-if="projectSection.projects_title" class="display text-4xl sm:text-6xl font-bold tracking-[-0.025em] leading-[1.05]">{{ projectSection.projects_title }}</h2>
                    <p v-if="projectSection.projects_subtitle" class="text-[#A9C9BF] text-base sm:text-lg">{{ projectSection.projects_subtitle }}</p>
                </div>

                <div v-if="categories.length > 2" v-reveal class="rv flex flex-wrap justify-center gap-2.5">
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        class="px-5 py-2.5 rounded-full text-sm font-semibold border transition-all duration-300"
                        :class="activeCategory === cat ? 'bg-[#5CF0BD] text-[#04201A] border-[#5CF0BD] btn-glow' : 'bg-white/[0.05] text-[#BDF3E0] border-white/15 hover:border-[#5CF0BD]/60 hover:text-white'"
                        @click="activeCategory = cat"
                    >
                        {{ cat }}
                    </button>
                </div>

                <TransitionGroup name="proj" tag="div" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <article
                        v-for="(proj, idx) in filteredProjects"
                        :key="(proj.title || 'project') + '-' + idx"
                        class="project-card rounded-3xl bg-white/[0.05] border border-white/10 backdrop-blur overflow-hidden group flex flex-col"
                    >
                        <div class="h-64 bg-gradient-to-br from-[#0B4A40] to-[#06352D] relative overflow-hidden flex items-center justify-center">
                            <img v-if="proj.image" :src="resolvePortfolioMediaUrl(proj.image)" :alt="proj.title" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                            <span v-else class="text-5xl transition-transform duration-500 group-hover:scale-125 group-hover:rotate-6">📂</span>
                            <div v-if="proj.url" class="absolute inset-0 bg-gradient-to-t from-[#04201A]/90 via-[#04201A]/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex items-end justify-start p-5">
                                <a :href="proj.url" target="_blank" rel="noopener" class="translate-y-3 group-hover:translate-y-0 transition-transform duration-500 inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#5CF0BD] text-[#04201A] font-semibold text-sm shadow-lg" aria-label="View Project">
                                    View project
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M8 7h9v9" /></svg>
                                </a>
                            </div>
                        </div>
                        <div class="p-6 space-y-3">
                            <span v-if="proj.category" class="inline-block text-sm font-semibold text-[#9BF0D2] px-3 py-1 rounded-full bg-white/[0.07] border border-white/10">{{ proj.category }}</span>
                            <h3 v-if="proj.title" class="display text-xl sm:text-2xl font-semibold tracking-tight">{{ proj.title }}</h3>
                        </div>
                    </article>
                </TransitionGroup>
            </div>
        </section>

        <!-- Contact -->
        <section id="contact" class="min-h-screen min-h-[100svh] flex flex-col justify-center relative px-6 py-28 bg-[#06231E] overflow-hidden border-t border-white/5">
            <div class="orb orb-b opacity-50"></div>
            <div class="absolute inset-0 hero-grid pointer-events-none"></div>

            <div v-reveal class="rv max-w-4xl mx-auto w-full relative z-10">
                <div class="contact-card relative rounded-[2.5rem] bg-gradient-to-br from-[#0B5A4D] to-[#06352D] border border-white/10 px-8 sm:px-16 py-16 sm:py-20 text-center overflow-hidden space-y-8 shadow-[0_40px_100px_rgba(0,0,0,0.4)]">
                    <div class="orb orb-a opacity-40"></div>
                    <div class="absolute inset-0 hero-grid pointer-events-none"></div>

                    <div class="relative z-10 space-y-5">
                        <span class="section-tag">Get in touch</span>
                        <h2 v-if="contact.contact_title" class="display text-4xl sm:text-6xl font-bold tracking-[-0.025em] leading-[1.05]">{{ contact.contact_title }}</h2>
                        <p v-if="contact.contact_description" class="text-[#BFE0D6] text-base sm:text-lg max-w-xl mx-auto">{{ contact.contact_description }}</p>
                    </div>

                    <a v-if="contact.contact_email" :href="'mailto:' + contact.contact_email" class="relative z-10 group inline-flex items-center gap-3 px-8 py-4 rounded-full bg-[#5CF0BD] text-[#04201A] font-semibold transition-transform duration-300 hover:-translate-y-0.5 btn-glow break-all">
                        {{ contact.contact_email }}
                        <svg class="w-4 h-4 shrink-0 transition-transform duration-300 group-hover:translate-x-1 group-hover:-translate-y-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M8 7h9v9" /></svg>
                    </a>

                    <div class="relative z-10 flex flex-wrap items-center justify-center gap-x-10 gap-y-3 text-[#BFE0D6] text-sm font-medium">
                        <div v-if="contact.contact_phone">Phone: {{ contact.contact_phone }}</div>
                        <div v-if="contact.contact_location">Location: {{ contact.contact_location }}</div>
                    </div>

                    <form
                        v-if="formReady"
                        :action="formAction"
                        method="POST"
                        target="em-gform-frame"
                        class="relative z-10 mx-auto w-full max-w-2xl space-y-5 rounded-3xl border border-[#9BF0D2]/20 bg-[#041A16]/60 p-6 text-left shadow-2xl sm:p-8"
                        @submit="onSubmit"
                    >
                        <h3 class="display text-2xl font-semibold">Send a message</h3>
                        <label class="block space-y-2 text-sm font-medium text-[#BFE0D6]">
                            Name
                            <input :name="`entry.${googleForm?.nameEntry}`" type="text" required autocomplete="name" class="w-full rounded-xl border border-[#9BF0D2]/20 bg-[#06231E] px-4 py-3 text-[#F0FFF9] focus:border-[#5CF0BD] focus:outline-none focus:ring-2 focus:ring-[#5CF0BD]/25" />
                        </label>
                        <label class="block space-y-2 text-sm font-medium text-[#BFE0D6]">
                            Email
                            <input :name="`entry.${googleForm?.emailEntry}`" type="email" required autocomplete="email" class="w-full rounded-xl border border-[#9BF0D2]/20 bg-[#06231E] px-4 py-3 text-[#F0FFF9] focus:border-[#5CF0BD] focus:outline-none focus:ring-2 focus:ring-[#5CF0BD]/25" />
                        </label>
                        <label class="block space-y-2 text-sm font-medium text-[#BFE0D6]">
                            Message
                            <textarea :name="`entry.${googleForm?.messageEntry}`" rows="5" required class="w-full resize-y rounded-xl border border-[#9BF0D2]/20 bg-[#06231E] px-4 py-3 text-[#F0FFF9] focus:border-[#5CF0BD] focus:outline-none focus:ring-2 focus:ring-[#5CF0BD]/25" />
                        </label>
                        <button type="submit" :disabled="sending" class="btn-glow rounded-full bg-[#5CF0BD] px-6 py-3 font-semibold text-[#04201A] transition-transform hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60">
                            {{ sending ? 'Sending…' : 'Send message' }}
                        </button>
                        <p v-if="sent" role="status" class="text-sm font-semibold text-[#9BF0D2]">Your message was submitted.</p>
                    </form>
                    <iframe name="em-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />

                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-white/10 py-10 px-6 bg-[#041A16]">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                <img v-if="navbar.show_logo !== false && navbar.show_logo !== 0 && navbar.show_logo !== '0' && navbar.show_logo !== 'false'" :src="navbar.logo_image || '/logo.svg'" :alt="navbar.logo_text" class="h-7 w-7 object-contain" />
                <p class="inline-flex items-center gap-2 text-sm text-[#86A79C]"><img src="/storage/Logo.svg" alt="" class="h-5 w-5 object-contain" />Built with quickFolio · Greenish Edition</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=DM+Sans:wght@400;500;600;700&display=swap');

.folio {
    font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
}
.display {
    font-family: 'Bricolage Grotesque', 'DM Sans', ui-sans-serif, system-ui, sans-serif;
}
:global(html) {
    scroll-behavior: smooth;
}

a:focus-visible,
button:focus-visible {
    outline: 3px solid #5cf0bd;
    outline-offset: 3px;
}

/* ---------- Section tag ---------- */
.section-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 1rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #9bf0d2;
    font-size: 0.875rem;
    font-weight: 600;
}
.section-tag::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

/* ---------- Ambient ---------- */
.hero-grid {
    background-image: linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
    -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
}
.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(90px);
    pointer-events: none;
}
.orb-a {
    width: 560px;
    height: 560px;
    background: #00a388;
    opacity: 0.4;
    top: -140px;
    left: -120px;
    animation: drift 18s ease-in-out infinite;
}
.orb-b {
    width: 480px;
    height: 480px;
    background: #2fbf9b;
    opacity: 0.25;
    bottom: -120px;
    right: -100px;
    animation: drift 22s ease-in-out infinite reverse;
}
.orb-c {
    width: 320px;
    height: 320px;
    background: #9bf0d2;
    opacity: 0.12;
    top: 40%;
    left: 45%;
    animation: drift 26s ease-in-out infinite;
}
@keyframes drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(60px, 40px) scale(1.1); }
    66% { transform: translate(-40px, 60px) scale(0.95); }
}

/* ---------- Hero entrance ---------- */
.hero-in {
    opacity: 0;
    transform: translateY(22px);
    animation: heroIn 0.9s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: var(--d, 0s);
}
@keyframes heroIn {
    to { opacity: 1; transform: none; }
}
.word-mask {
    display: inline-block;
    overflow: hidden;
    padding-bottom: 0.12em;
    margin-bottom: -0.12em;
    margin-right: 0.22em;
    vertical-align: top;
}
.word {
    display: inline-block;
    transform: translateY(110%);
    animation: wordUp 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: var(--d, 0s);
}
@keyframes wordUp {
    to { transform: translateY(0); }
}

.shimmer-text {
    background: linear-gradient(100deg, #5cf0bd 20%, #d4fff0 45%, #5cf0bd 70%);
    background-size: 220% 100%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: heroIn 0.9s cubic-bezier(0.22, 1, 0.36, 1) forwards, shimmer 5s linear 1.8s infinite;
    animation-delay: var(--d, 0s), 1.8s;
}
@keyframes shimmer {
    to { background-position: -220% 0; }
}

/* ---------- Portrait ---------- */
.blob {
    border-radius: 58% 42% 60% 40% / 48% 55% 45% 52%;
    animation: morph 12s ease-in-out infinite;
}
@keyframes morph {
    0%, 100% { border-radius: 58% 42% 60% 40% / 48% 55% 45% 52%; }
    25% { border-radius: 42% 58% 45% 55% / 58% 42% 58% 42%; }
    50% { border-radius: 55% 45% 38% 62% / 42% 60% 40% 58%; }
    75% { border-radius: 40% 60% 58% 42% / 55% 45% 55% 45%; }
}
.ring {
    position: absolute;
    border-radius: 50%;
    border: 1px dashed rgba(155, 240, 210, 0.35);
}
.ring-1 {
    inset: -16px;
    animation: spin 40s linear infinite;
}
.ring-2 {
    inset: -40px;
    border-color: rgba(155, 240, 210, 0.15);
    animation: spin 60s linear infinite reverse;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.dot {
    position: absolute;
    border-radius: 50%;
    background: #5cf0bd;
    box-shadow: 0 0 24px rgba(92, 240, 189, 0.8);
}
.dot-1 { width: 14px; height: 14px; top: 6%; right: 8%; animation: float 5s ease-in-out infinite; }
.dot-2 { width: 9px; height: 9px; bottom: 14%; left: -2%; animation: float 6s ease-in-out infinite 1s; }
.dot-3 { width: 18px; height: 18px; bottom: 2%; right: 14%; background: #2fbf9b; animation: float 7s ease-in-out infinite 0.5s; }
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-16px); }
}
.float-slow {
    animation: float 4s ease-in-out infinite;
}

/* ---------- Buttons ---------- */
.btn-glow {
    box-shadow: 0 10px 30px rgba(92, 240, 189, 0.25);
}
.btn-glow:hover {
    box-shadow: 0 14px 40px rgba(92, 240, 189, 0.45);
}

/* ---------- Scroll cue ---------- */
.scroll-wheel {
    animation: wheel 1.8s ease-in-out infinite;
}
@keyframes wheel {
    0% { transform: translateY(0); opacity: 1; }
    80% { transform: translateY(12px); opacity: 0; }
    100% { opacity: 0; }
}

/* ---------- Marquee ---------- */
.marquee {
    animation: marquee 32s linear infinite;
}
.marquee:hover {
    animation-play-state: paused;
}
@keyframes marquee {
    to { transform: translateX(-50%); }
}

/* ---------- Burger ---------- */
.burger-line {
    display: block;
    width: 20px;
    height: 2px;
    border-radius: 2px;
    transition: transform 0.35s ease, opacity 0.25s ease;
}
.drop-enter-active,
.drop-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.drop-enter-from,
.drop-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* ---------- Scroll reveal ---------- */
.rv {
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1), transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
}
.rv-left {
    transform: translateX(-36px);
}
.rv.is-in {
    opacity: 1;
    transform: none;
}

/* ---------- Timeline ---------- */
.spine {
    transform: scaleY(0);
    transform-origin: top;
    transition: transform 1.6s cubic-bezier(0.22, 1, 0.36, 1);
}
.spine.is-in {
    transform: scaleY(1);
}
.timeline-dot {
    animation: pulse 2.6s ease-out infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(92, 240, 189, 0.5); }
    70%, 100% { box-shadow: 0 0 0 12px rgba(92, 240, 189, 0); }
}

/* ---------- Cards ---------- */
.card-lift {
    transition: transform 0.4s ease, box-shadow 0.4s ease, border-color 0.4s ease, background-color 0.4s ease;
}
.card-lift:hover {
    transform: translateY(-4px);
    border-color: rgba(92, 240, 189, 0.4);
    background-color: rgba(255, 255, 255, 0.09);
    box-shadow: 0 24px 50px rgba(0, 0, 0, 0.3);
}

/* ---------- Circular skill meters ---------- */
.skill-card:hover {
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35), 0 0 40px rgba(92, 240, 189, 0.08);
}
.skill-card:hover .meter {
    transform: scale(1.04);
}
.meter {
    transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.halo {
    position: absolute;
    inset: 14px;
    border-radius: 50%;
    border: 1px solid rgba(92, 240, 189, 0.35);
    opacity: 0;
}
.skill-card.is-in .halo {
    animation: halo 3.2s ease-out infinite;
    animation-delay: calc(var(--bd, 0s) + 1.6s);
}
@keyframes halo {
    0% { transform: scale(0.85); opacity: 0.7; }
    100% { transform: scale(1.3); opacity: 0; }
}
.orbit {
    transform-origin: 60px 60px;
    animation: spin 30s linear infinite;
}
.skill-card:hover .orbit {
    animation-duration: 8s;
}
.arc {
    stroke-dashoffset: var(--circ);
    filter: drop-shadow(0 0 6px rgba(92, 240, 189, 0.55));
    transition: stroke-dashoffset 2s cubic-bezier(0.22, 1, 0.36, 1);
    transition-delay: var(--bd, 0s);
}
.skill-card.is-in .arc {
    stroke-dashoffset: var(--off);
}
.tip {
    transform-origin: 60px 60px;
    transform: rotate(0deg);
    filter: drop-shadow(0 0 6px rgba(92, 240, 189, 0.9));
    transition: transform 2s cubic-bezier(0.22, 1, 0.36, 1);
    transition-delay: var(--bd, 0s);
}
.skill-card.is-in .tip {
    transform: rotate(var(--deg));
}

/* ---------- Projects ---------- */
.project-card {
    transition: transform 0.45s ease, box-shadow 0.45s ease, border-color 0.45s ease;
}
.project-card:hover {
    transform: translateY(-8px);
    border-color: rgba(92, 240, 189, 0.4);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
}
.proj-enter-active,
.proj-leave-active {
    transition: opacity 0.4s ease, transform 0.4s ease;
}
.proj-enter-from,
.proj-leave-to {
    opacity: 0;
    transform: scale(0.94) translateY(16px);
}
.proj-move {
    transition: transform 0.5s ease;
}

/* ---------- Reduced motion ---------- */
@media (prefers-reduced-motion: reduce) {
    :global(html) {
        scroll-behavior: auto;
    }
    .hero-in,
    .word,
    .rv,
    .spine {
        opacity: 1 !important;
        transform: none !important;
        animation: none !important;
        transition: none !important;
    }
    .arc {
        stroke-dashoffset: var(--off);
        transition: none;
    }
    .tip {
        transform: rotate(var(--deg));
        transition: none;
    }
    .orb,
    .blob,
    .ring,
    .dot,
    .marquee,
    .timeline-dot,
    .scroll-wheel,
    .float-slow,
    .halo,
    .orbit,
    .shimmer-text {
        animation: none !important;
    }
    .shimmer-text {
        -webkit-text-fill-color: #5cf0bd;
        background: none;
    }
}
</style>
