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

interface ComponentProps {
    content: Record<string, any>;
    designSettings?: Record<string, any>;
    portfolio?: PortfolioIntegration;
}

const props = defineProps<ComponentProps>();

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

/* ---------- Projects + category filter ---------- */
const activeCategory = ref('All');
const allProjects = computed<any[]>(() => projectSection.value.projects_list || projectSection.value.projects || []);
const categories = computed(() => {
    const set = new Set<string>();
    allProjects.value.forEach((p) => p.category && set.add(p.category));
    return set.size > 1 ? ['All', ...set] : [];
});
const filteredProjects = computed(() =>
    activeCategory.value === 'All' ? allProjects.value : allProjects.value.filter((p) => p.category === activeCategory.value),
);

/* ---------- Nav: only link to sections that render ---------- */
const links = computed(() => {
    const l = [{ id: 'about', label: 'About' }];
    if (journey.value.journey_list?.length) l.push({ id: 'journey', label: 'Journey' });
    if (skills.value.skills_list?.length) l.push({ id: 'skills', label: 'Skills' });
    if (allProjects.value.length) l.push({ id: 'projects', label: 'Work' });
    l.push({ id: 'contact', label: 'Contact' });
    return l;
});

/* ---------- Page-load, scroll progress, active section ---------- */
const ready = ref(false);
const menuOpen = ref(false);
const progress = ref(0);
const activeSection = ref('home');
let raf = 0;
let sectionObserver: IntersectionObserver | null = null;

function onScroll() {
    if (raf) return;
    raf = requestAnimationFrame(() => {
        const h = document.documentElement.scrollHeight - window.innerHeight;
        progress.value = h > 0 ? Math.min(1, window.scrollY / h) : 0;
        raf = 0;
    });
}

onMounted(() => {
    requestAnimationFrame(() => (ready.value = true));
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    sectionObserver = new IntersectionObserver(
        (entries) => entries.forEach((e) => e.isIntersecting && (activeSection.value = e.target.id)),
        { rootMargin: '-45% 0px -50% 0px' },
    );
    document.querySelectorAll('[data-section]').forEach((el) => sectionObserver!.observe(el));
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    sectionObserver?.disconnect();
    if (raf) cancelAnimationFrame(raf);
});

/* ---------- Reveal-on-scroll directive (v-reveal="delayMs") ---------- */
const vReveal = {
    mounted(el: HTMLElement, binding: { value?: number }) {
        el.classList.add('reveal');
        if (binding.value) el.style.setProperty('--d', `${binding.value}ms`);
        if (!('IntersectionObserver' in window)) return el.classList.add('is-in');
        const io = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    el.classList.add('is-in');
                    io.disconnect();
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -8% 0px' },
        );
        io.observe(el);
    },
};

/* ---------- Hero portrait: gentle pointer tilt ---------- */
const tilt = ref({ x: 0, y: 0 });
function onPointer(e: PointerEvent) {
    const r = (e.currentTarget as HTMLElement).getBoundingClientRect();
    tilt.value = { x: ((e.clientX - r.left) / r.width - 0.5) * 2, y: ((e.clientY - r.top) / r.height - 0.5) * 2 };
}
const resetTilt = () => (tilt.value = { x: 0, y: 0 });

const openJourney = ref(0);
const hovered = ref(-1);
const mouse = ref({ x: 0, y: 0 });
function onWorkMove(e: PointerEvent) {
    mouse.value = { x: e.clientX + 28, y: e.clientY - 150 };
}

const heroWords = computed(() => (hero.value.hero_name || '').split(' ').filter(Boolean));
</script>

<template>
    <div class="theme min-h-screen overflow-x-hidden font-body" :class="{ 'is-ready': ready }">
        <!-- Scroll progress -->
        <div class="fixed inset-x-0 top-0 z-[60] h-[2px] bg-transparent">
            <div class="h-full origin-left bg-[var(--ember)]" :style="{ transform: `scaleX(${progress})` }"></div>
        </div>

        <!-- Navbar -->
        <nav class="nav fixed inset-x-0 top-0 z-50" :class="{ 'nav-in': ready }">
            <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between px-6">
                <a href="#home" class="flex items-center gap-3 focus-ring rounded-lg" aria-label="Back to top">
                    <img v-if="navbar.show_logo !== false && navbar.show_logo !== 0 && navbar.show_logo !== '0' && navbar.show_logo !== 'false'" :src="navbar.logo_image || '/logo.svg'" :alt="navbar.logo_text" class="h-9 w-9 shrink-0 object-contain" />
                </a>

                <ul class="hidden items-center gap-1 md:flex">
                    <li v-for="l in links" :key="l.id">
                        <a :href="`#${l.id}`" class="nav-link focus-ring" :class="{ active: activeSection === l.id }">{{ l.label }}</a>
                    </li>
                </ul>

                <button
                    class="focus-ring grid h-10 w-10 place-items-center rounded-full border border-[var(--line)] md:hidden"
                    :aria-expanded="menuOpen"
                    aria-label="Toggle menu"
                    @click="menuOpen = !menuOpen"
                >
                    <span class="burger" :class="{ open: menuOpen }"><i></i><i></i></span>
                </button>
            </div>

            <Transition name="menu">
                <ul v-if="menuOpen" class="mx-4 mb-4 rounded-2xl border border-[var(--line)] bg-[var(--surface)] p-2 md:hidden">
                    <li v-for="l in links" :key="l.id">
                        <a :href="`#${l.id}`" class="block rounded-xl px-4 py-3 text-lg hover:bg-white/5" @click="menuOpen = false">{{ l.label }}</a>
                    </li>
                </ul>
            </Transition>
        </nav>

        <!-- Hero -->
        <header id="home" data-section class="relative flex min-h-[100svh] items-center px-6 pb-16 pt-28">
            <div class="aurora" aria-hidden="true"></div>

            <div class="relative z-10 mx-auto grid w-full max-w-7xl items-center gap-14 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <p v-if="hero.hero_greeting" class="hero-line mb-6 inline-flex items-center gap-2 text-base text-[var(--muted)]" style="--i: 0">
                        <span class="pulse-dot"></span>{{ hero.hero_greeting }}
                    </p>

                    <h1 v-if="heroWords.length" class="font-display text-[clamp(3.25rem,10vw,8rem)] font-semibold leading-[0.92] tracking-[-0.04em]">
                        <span v-for="(w, i) in heroWords" :key="i" class="mask">
                            <span class="mask-inner" :style="{ '--i': i + 1 }">{{ w }}&nbsp;</span>
                        </span>
                    </h1>

                    <p v-if="hero.hero_role" class="hero-line mt-6 font-display text-2xl font-medium tracking-tight text-[var(--ember)] sm:text-3xl" :style="{ '--i': heroWords.length + 2 }">
                        {{ hero.hero_role }}
                    </p>

                    <p v-if="hero.hero_description" class="hero-line mt-6 max-w-[34rem] text-lg leading-relaxed text-[var(--muted)]" :style="{ '--i': heroWords.length + 3 }">
                        {{ hero.hero_description }}
                    </p>

                    <div v-if="hero.hero_cta_primary_text" class="hero-line mt-10" :style="{ '--i': heroWords.length + 4 }">
                        <a :href="hero.hero_cta_primary_url || '#projects'" class="btn-primary focus-ring group">
                            <span>{{ hero.hero_cta_primary_text }}</span>
                            <span class="btn-arrow">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 12h14m-6-6 6 6-6 6" /></svg>
                            </span>
                        </a>
                    </div>
                </div>

                <div class="hero-line flex justify-center lg:col-span-5 lg:justify-end" style="--i: 3">
                    <div class="portrait-wrap" @pointermove="onPointer" @pointerleave="resetTilt">
                        <div class="portrait" :style="{ transform: `perspective(900px) rotateY(${tilt.x * 5}deg) rotateX(${-tilt.y * 5}deg)` }">
                            <img v-if="hero.hero_image" :src="resolvePortfolioMediaUrl(hero.hero_image)" :alt="hero.hero_name" class="h-full w-full object-cover" />
                            <span v-else class="grid h-full w-full place-items-center font-display text-[8rem] font-semibold text-[var(--ember)]/80">
                                {{ (hero.hero_name || navbar.logo_text || '•').charAt(0) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- About -->
        <section id="about" data-section class="section">
            <div class="mx-auto grid max-w-7xl items-center gap-16 lg:grid-cols-12">
                <div v-if="about.about_image" v-reveal class="lg:col-span-5">
                    <div class="img-frame aspect-[4/5] w-full max-w-md">
                        <img :src="resolvePortfolioMediaUrl(about.about_image)" :alt="about.about_title" class="h-full w-full object-cover" />
                    </div>
                </div>

                <div :class="about.about_image ? 'lg:col-span-7' : 'lg:col-span-12 mx-auto max-w-3xl'">
                    <h2 v-if="about.about_title" v-reveal class="h2">{{ about.about_title }}</h2>
                    <p v-if="about.about_description" v-reveal="100" class="mt-8 max-w-[38rem] whitespace-pre-line text-lg leading-[1.75] text-[var(--muted)]">
                        {{ about.about_description }}
                    </p>
                    <div v-if="about.about_cv_file" v-reveal="200" class="mt-10">
                        <a :href="resolvePortfolioMediaUrl(about.about_cv_file)" class="btn-ghost focus-ring group">
                            <svg class="h-4 w-4 transition-transform group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4-4 4m0 0-4-4m4 4V4" /></svg>
                            Download my CV
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Journey -->
        <section v-if="journey.journey_list?.length" id="journey" data-section class="section">
            <div class="mx-auto max-w-5xl">
                <div class="max-w-2xl">
                    <h2 v-if="journey.journey_title" v-reveal class="h2">{{ journey.journey_title }}</h2>
                    <p v-if="journey.journey_subtitle" v-reveal="100" class="mt-5 text-lg text-[var(--muted)]">{{ journey.journey_subtitle }}</p>
                </div>

                <ul class="mt-16 border-t border-[var(--line)]">
                    <li v-for="(item, idx) in journey.journey_list" :key="idx" v-reveal="idx * 60" class="jr" :class="{ open: openJourney === idx }">
                        <button class="jr-head focus-ring" :aria-expanded="openJourney === idx" @click="openJourney = openJourney === idx ? -1 : idx">
                            <span class="jr-period">{{ item.period }}</span>
                            <span class="jr-main">
                                <span class="jr-title">{{ item.title }}</span>
                                <span v-if="item.company" class="jr-company">{{ item.company }}</span>
                            </span>
                            <span class="jr-icon" aria-hidden="true"></span>
                        </button>
                        <div class="jr-body">
                            <div>
                                <p v-if="item.description" class="jr-desc">{{ item.description }}</p>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Skills -->
        <section v-if="skills.skills_list?.length" id="skills" data-section class="section">
            <div class="mx-auto max-w-5xl">
                <div class="max-w-2xl">
                    <h2 v-if="skills.skills_title" v-reveal class="h2">{{ skills.skills_title }}</h2>
                    <p v-if="skills.skills_description" v-reveal="100" class="mt-5 text-lg text-[var(--muted)]">{{ skills.skills_description }}</p>
                </div>

                <ul class="mt-14 grid gap-x-16 gap-y-8 sm:grid-cols-2">
                    <li v-for="(skill, idx) in skills.skills_list" :key="idx" v-reveal="(idx % 6) * 60" class="skill">
                        <div class="mb-3 flex items-baseline justify-between">
                            <span class="font-display text-lg font-medium">{{ skill.label }}</span>
                            <span class="text-sm tabular-nums text-[var(--muted)]">{{ skill.percent || 100 }}%</span>
                        </div>
                        <div class="h-[6px] overflow-hidden rounded-full bg-white/[0.07]">
                            <div class="skill-bar h-full rounded-full" :style="{ '--w': (skill.percent || 100) + '%' }"></div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Work -->
        <section v-if="allProjects.length" id="projects" data-section class="section">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-wrap items-end justify-between gap-8">
                    <div class="max-w-2xl">
                        <h2 v-if="projectSection.projects_title" v-reveal class="h2">{{ projectSection.projects_title }}</h2>
                        <p v-if="projectSection.projects_subtitle" v-reveal="100" class="mt-5 text-lg text-[var(--muted)]">{{ projectSection.projects_subtitle }}</p>
                    </div>

                    <div v-if="categories.length" v-reveal="150" class="flex flex-wrap gap-2" role="tablist" aria-label="Filter projects">
                        <button
                            v-for="c in categories"
                            :key="c"
                            role="tab"
                            :aria-selected="activeCategory === c"
                            class="chip focus-ring"
                            :class="{ active: activeCategory === c }"
                            @click="activeCategory = c"
                        >
                            {{ c }}
                        </button>
                    </div>
                </div>

                <div class="mt-14 border-t border-[var(--line)]" @pointermove="onWorkMove" @pointerleave="hovered = -1">
                    <TransitionGroup name="grid" tag="div">
                        <article v-for="(proj, idx) in filteredProjects" :key="proj.title + proj.category" class="work-row" @pointerenter="hovered = idx">
                            <component :is="proj.url ? 'a' : 'div'" :href="proj.url" :target="proj.url ? '_blank' : undefined" rel="noopener" class="work-link focus-ring">
                                <img v-if="proj.image" :src="resolvePortfolioMediaUrl(proj.image)" :alt="proj.title" class="work-thumb lg:hidden" loading="lazy" />
                                <span class="work-title">{{ proj.title }}</span>
                                <span class="work-meta">
                                    <span v-if="proj.category">{{ proj.category }}</span>
                                    <svg v-if="proj.url" class="work-arrow h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17 17 7m0 0H8m9 0v9" /></svg>
                                </span>
                            </component>
                        </article>
                    </TransitionGroup>

                    <!-- Cursor-following preview (desktop) -->
                    <div class="work-preview hidden lg:block" :class="{ show: hovered > -1 && filteredProjects[hovered]?.image }" :style="{ transform: `translate3d(${mouse.x}px, ${mouse.y}px, 0)` }" aria-hidden="true">
                        <div class="work-preview-inner">
                            <img v-for="(proj, idx) in filteredProjects" :key="'p' + idx" v-show="proj.image" :src="proj.image ? resolvePortfolioMediaUrl(proj.image) : ''" alt="" :class="{ on: hovered === idx }" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section id="contact" data-section class="section">
            <div class="mx-auto max-w-4xl">
                <h2 v-if="contact.contact_title" v-reveal class="h2">{{ contact.contact_title }}</h2>
                <p v-if="contact.contact_description" v-reveal="100" class="mt-5 max-w-xl text-lg text-[var(--muted)]">{{ contact.contact_description }}</p>

                <div v-if="contact.contact_email || contact.contact_phone || contact.contact_location" v-reveal="150" class="mt-10 flex flex-wrap gap-x-10 gap-y-3 text-[var(--muted)]">
                    <a v-if="contact.contact_email" :href="`mailto:${contact.contact_email}`" class="contact-link focus-ring">{{ contact.contact_email }}</a>
                    <span v-if="contact.contact_phone">{{ contact.contact_phone }}</span>
                    <span v-if="contact.contact_location">{{ contact.contact_location }}</span>
                </div>

                <form
                    v-if="formReady"
                    v-reveal="200"
                    :action="formAction"
                    method="POST"
                    target="do-gform-frame"
                    class="mt-12 space-y-5 rounded-3xl border border-[var(--line)] bg-[var(--surface)] p-6 text-left sm:p-8"
                    @submit="onSubmit"
                >
                    <h3 class="font-display text-2xl font-semibold text-[var(--paper)]">Send a message</h3>
                    <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">
                        Name
                        <input :name="`entry.${googleForm?.nameEntry}`" type="text" required autocomplete="name" class="w-full rounded-xl border border-[var(--line)] bg-[var(--ink)] px-4 py-3 text-[var(--paper)] focus:border-[var(--ember)] focus:outline-none focus:ring-2 focus:ring-[var(--ember)]/25" />
                    </label>
                    <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">
                        Email
                        <input :name="`entry.${googleForm?.emailEntry}`" type="email" required autocomplete="email" class="w-full rounded-xl border border-[var(--line)] bg-[var(--ink)] px-4 py-3 text-[var(--paper)] focus:border-[var(--ember)] focus:outline-none focus:ring-2 focus:ring-[var(--ember)]/25" />
                    </label>
                    <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">
                        Message
                        <textarea :name="`entry.${googleForm?.messageEntry}`" rows="5" required class="w-full resize-y rounded-xl border border-[var(--line)] bg-[var(--ink)] px-4 py-3 text-[var(--paper)] focus:border-[var(--ember)] focus:outline-none focus:ring-2 focus:ring-[var(--ember)]/25" />
                    </label>
                    <button type="submit" :disabled="sending" class="btn-primary disabled:cursor-not-allowed disabled:opacity-60">
                        {{ sending ? 'Sending…' : 'Send message' }}
                        <span class="btn-arrow" aria-hidden="true">→</span>
                    </button>
                    <p v-if="sent" role="status" class="text-sm font-medium text-[var(--ember)]">Your message was submitted.</p>
                </form>
                <iframe name="do-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-[var(--line)] px-6 py-12">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 text-sm text-[var(--muted)]">
                <img v-if="navbar.show_logo !== false && navbar.show_logo !== 0 && navbar.show_logo !== '0' && navbar.show_logo !== 'false'" :src="navbar.logo_image || '/logo.svg'" :alt="navbar.logo_text" class="h-7 w-7 object-contain" />
                <span class="inline-flex items-center gap-2"><img src="/storage/Logo.svg" alt="" class="h-5 w-5 object-contain" />Made with quickFolio</span>
                <a href="#home" class="focus-ring rounded-md hover:text-[var(--paper)]">Back to top</a>
            </div>
        </footer>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Instrument+Sans:wght@400;500;600&display=swap');

html { scroll-behavior: smooth; scroll-padding-top: 80px; }

.theme {
    --ink: #0f0e0d;
    --surface: #181614;
    --paper: #f3efe9;
    --muted: #a39d94;
    --ember: #ff5a1f;
    --line: rgba(243, 239, 233, 0.1);
    --ease: cubic-bezier(0.22, 1, 0.36, 1);
    background: var(--ink);
    color: var(--paper);
}
.theme ::selection { background: var(--ember); color: var(--ink); }
.font-display { font-family: 'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif; }
.font-body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }

.focus-ring:focus-visible { outline: 2px solid var(--ember); outline-offset: 3px; }

/* Layout helpers */
.section { position: relative; padding: clamp(5rem, 12vw, 9rem) 1.5rem; border-top: 1px solid var(--line); }
.h2 { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 600; letter-spacing: -0.03em; line-height: 1; font-size: clamp(2.5rem, 6vw, 4.5rem); }

/* Nav */
.nav { background: rgba(15, 14, 13, 0.72); backdrop-filter: blur(16px) saturate(1.4); border-bottom: 1px solid var(--line); transform: translateY(-100%); transition: transform 0.8s var(--ease) 0.1s; }
.nav-in { transform: none; }
.nav-link { position: relative; padding: 0.5rem 0.9rem; border-radius: 999px; color: var(--muted); transition: color 0.25s, background 0.25s; }
.nav-link:hover { color: var(--paper); background: rgba(255, 255, 255, 0.05); }
.nav-link.active { color: var(--paper); background: rgba(255, 255, 255, 0.08); }
.burger { position: relative; display: block; width: 16px; height: 10px; }
.burger i { position: absolute; left: 0; width: 100%; height: 2px; background: currentColor; border-radius: 2px; transition: transform 0.35s var(--ease), top 0.35s var(--ease); }
.burger i:first-child { top: 0; } .burger i:last-child { top: 8px; }
.burger.open i:first-child { top: 4px; transform: rotate(45deg); }
.burger.open i:last-child { top: 4px; transform: rotate(-45deg); }
.menu-enter-active, .menu-leave-active { transition: opacity 0.25s, transform 0.35s var(--ease); }
.menu-enter-from, .menu-leave-to { opacity: 0; transform: translateY(-8px); }

/* Hero: one orchestrated entrance */
.aurora { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.aurora::before { content: ''; position: absolute; width: 60vw; height: 60vw; max-width: 760px; max-height: 760px; right: -10%; top: 5%; background: radial-gradient(closest-side, rgba(255, 90, 31, 0.22), transparent); filter: blur(40px); animation: drift 16s ease-in-out infinite alternate; }
@keyframes drift { to { transform: translate(-6%, 8%) scale(1.12); } }

.mask { display: inline-block; overflow: hidden; vertical-align: top; padding-bottom: 0.08em; }
.mask-inner { display: inline-block; transform: translateY(110%); transition: transform 1s var(--ease); transition-delay: calc(var(--i) * 90ms + 250ms); }
.is-ready .mask-inner { transform: none; }
.hero-line { opacity: 0; transform: translateY(18px); transition: opacity 0.9s var(--ease), transform 0.9s var(--ease); transition-delay: calc(var(--i) * 90ms + 250ms); }
.is-ready .hero-line { opacity: 1; transform: none; }
.pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ember); box-shadow: 0 0 0 0 rgba(255, 90, 31, 0.6); animation: ping 2.2s infinite; }
@keyframes ping { 70% { box-shadow: 0 0 0 10px rgba(255, 90, 31, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 90, 31, 0); } }

.portrait-wrap { width: min(100%, 380px); }
.portrait { aspect-ratio: 4 / 5; border-radius: 999px 999px 28px 28px; overflow: hidden; background: var(--surface); border: 1px solid var(--line); transition: transform 0.25s ease-out; box-shadow: 0 40px 80px -30px rgba(255, 90, 31, 0.3); }

/* Buttons */
.btn-primary { display: inline-flex; align-items: center; gap: 0.9rem; padding: 0.55rem 0.55rem 0.55rem 1.6rem; border-radius: 999px; background: var(--ember); color: var(--ink); font-weight: 600; transition: transform 0.35s var(--ease), box-shadow 0.35s; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 34px -10px rgba(255, 90, 31, 0.6); }
.btn-arrow { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%; background: var(--ink); color: var(--paper); transition: transform 0.35s var(--ease); }
.btn-primary:hover .btn-arrow { transform: translateX(3px) rotate(-45deg); }
.btn-ghost { display: inline-flex; align-items: center; gap: 0.7rem; padding: 0.85rem 1.5rem; border-radius: 999px; border: 1px solid var(--line); font-weight: 500; transition: border-color 0.3s, background 0.3s; }
.btn-ghost:hover { border-color: var(--ember); background: rgba(255, 90, 31, 0.08); }

/* Media */
.img-frame { position: relative; overflow: hidden; border-radius: 24px; background: var(--surface); border: 1px solid var(--line); }

/* Skills */
.skill-bar { width: var(--w); background: linear-gradient(90deg, #ff8a3d, var(--ember)); transform: scaleX(0); transform-origin: left; transition: transform 1.3s var(--ease) 0.2s; }
.skill.is-in .skill-bar { transform: none; }

/* Filter + list transitions */
.chip { padding: 0.5rem 1.1rem; border-radius: 999px; border: 1px solid var(--line); color: var(--muted); font-size: 0.95rem; transition: all 0.3s var(--ease); }
.chip:hover { color: var(--paper); border-color: rgba(243, 239, 233, 0.3); }
.chip.active { background: var(--paper); color: var(--ink); border-color: var(--paper); }
.grid-enter-active, .grid-leave-active { transition: opacity 0.4s, transform 0.5s var(--ease); }
.grid-enter-from, .grid-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
.grid-move { transition: transform 0.5s var(--ease); }


/* Journey: expandable list */
.jr { border-bottom: 1px solid var(--line); }
.jr-head { display: grid; grid-template-columns: 1fr auto; gap: 0.25rem 1.5rem; align-items: center; width: 100%; padding: 1.75rem 0; text-align: left; transition: padding 0.4s var(--ease); }
@media (min-width: 768px) { .jr-head { grid-template-columns: 220px 1fr auto; gap: 2rem; padding: 2.25rem 0; } }
.jr-period { grid-column: 1 / -1; font-size: 0.95rem; color: var(--muted); transition: color 0.3s; }
@media (min-width: 768px) { .jr-period { grid-column: auto; } }
.jr-main { display: flex; flex-direction: column; gap: 0.25rem; }
.jr-title { font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(1.5rem, 3.4vw, 2.5rem); font-weight: 600; letter-spacing: -0.025em; line-height: 1.1; transition: transform 0.5s var(--ease), color 0.3s; }
.jr-company { color: var(--muted); }
.jr:hover .jr-title { transform: translateX(10px); }
.jr:hover .jr-period, .jr.open .jr-period { color: var(--ember); }
.jr-icon { position: relative; width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--line); transition: background 0.3s, border-color 0.3s, transform 0.5s var(--ease); }
.jr-icon::before, .jr-icon::after { content: ''; position: absolute; left: 50%; top: 50%; width: 14px; height: 2px; margin: -1px 0 0 -7px; background: var(--paper); transition: transform 0.4s var(--ease), background 0.3s; }
.jr-icon::after { transform: rotate(90deg); }
.jr:hover .jr-icon { border-color: var(--ember); }
.jr.open .jr-icon { background: var(--ember); border-color: var(--ember); transform: rotate(180deg); }
.jr.open .jr-icon::before, .jr.open .jr-icon::after { background: var(--ink); }
.jr.open .jr-icon::after { transform: rotate(0deg); }
.jr-body { display: grid; grid-template-rows: 0fr; transition: grid-template-rows 0.55s var(--ease); }
.jr-body > div { overflow: hidden; }
.jr.open .jr-body { grid-template-rows: 1fr; }
.jr-desc { max-width: 40rem; padding: 0 0 2.25rem; color: var(--muted); font-size: 1.05rem; line-height: 1.75; opacity: 0; transform: translateY(8px); transition: opacity 0.5s 0.1s, transform 0.5s var(--ease) 0.1s; }
@media (min-width: 768px) { .jr-desc { margin-left: calc(220px + 2rem); } }
.jr.open .jr-desc { opacity: 1; transform: none; }

/* Work: index list with cursor preview */
.work-row { border-bottom: 1px solid var(--line); }
.work-link { display: flex; flex-direction: column; gap: 1rem; padding: 1.5rem 0; border-radius: 8px; }
@media (min-width: 1024px) { .work-link { flex-direction: row; align-items: center; justify-content: space-between; gap: 2rem; padding: 2.5rem 0; transition: padding 0.5s var(--ease); } }
.work-thumb { width: 100%; aspect-ratio: 16 / 10; object-fit: cover; border-radius: 20px; border: 1px solid var(--line); }
.work-title { font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(1.6rem, 5.5vw, 4rem); font-weight: 600; letter-spacing: -0.03em; line-height: 1; color: var(--paper); transition: color 0.35s, transform 0.6s var(--ease), opacity 0.35s; }
.work-meta { display: inline-flex; align-items: center; gap: 1.25rem; color: var(--muted); }
.work-arrow { color: var(--ember); transition: transform 0.5s var(--ease); }
@media (min-width: 1024px) {
    .work-row > * { opacity: 1; }
    .work-row:hover .work-link { padding-left: 1.25rem; padding-right: 1.25rem; }
    .work-row:hover .work-title { color: var(--ember); }
    .work-row:hover .work-arrow { transform: translate(4px, -4px) rotate(0deg); }
    .work-row:hover .work-meta { color: var(--paper); }
}
.work-preview { position: fixed; left: 0; top: 0; z-index: 40; width: 340px; pointer-events: none; opacity: 0; transition: opacity 0.35s, transform 0.28s cubic-bezier(0.22, 1, 0.36, 1); }
.work-preview.show { opacity: 1; }
.work-preview-inner { position: relative; aspect-ratio: 4 / 3; overflow: hidden; border-radius: 20px; border: 1px solid var(--line); background: var(--surface); box-shadow: 0 30px 60px -20px rgba(0, 0, 0, 0.7); transform: scale(0.92) rotate(-2deg); transition: transform 0.5s var(--ease); }
.work-preview.show .work-preview-inner { transform: none; }
.work-preview-inner img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.35s; }
.work-preview-inner img.on { opacity: 1; }

/* Contact */
.contact-link { color: var(--paper); background: linear-gradient(var(--ember), var(--ember)) no-repeat 0 100% / 100% 1px; transition: background-size 0.35s var(--ease), color 0.25s; }
.contact-link:hover { color: var(--ember); background-size: 100% 100%; background-image: none; }

/* Scroll reveal */
.reveal { opacity: 0; transform: translateY(24px); transition: opacity 0.8s var(--ease), transform 0.8s var(--ease); transition-delay: var(--d, 0ms); }
.reveal.is-in { opacity: 1; transform: none; }

@media (prefers-reduced-motion: reduce) {
    html { scroll-behavior: auto; }
    .theme *, .theme *::before, .theme *::after { animation: none !important; transition-duration: 0.01ms !important; transition-delay: 0ms !important; }
    .reveal, .hero-line, .mask-inner, .nav { opacity: 1 !important; transform: none !important; }
    .skill-bar { transform: none !important; }
}
</style>
