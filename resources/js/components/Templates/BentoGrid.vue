<script setup lang="ts">
import { resolvePortfolioMediaUrl } from '@/lib/media';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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

const root = ref<HTMLElement | null>(null);
const activeSection = ref('home');
const progress = ref(0);
let raf = 0;
let revealObserver: IntersectionObserver | undefined;
let sectionObserver: IntersectionObserver | undefined;

const navbar = computed(() => props.content?.navbar || {});
const hero = computed(() => props.content?.header || {});
const about = computed(() => props.content?.about || {});
const journey = computed(() => props.content?.journey || {});
const skills = computed(() => props.content?.skills || {});
const portfolio = computed(() => props.content?.portfolio || props.content?.projects || {});
const contact = computed(() => props.content?.contact || {});
const projects = computed<any[]>(() => portfolio.value.projects_list || portfolio.value.projects || []);

const hasAbout = computed(() => about.value.about_title || about.value.about_description || about.value.about_image || about.value.about_cv_file);
const hasContact = computed(
    () =>
        contact.value.contact_form_enabled !== false ||
        contact.value.contact_title ||
        contact.value.contact_description ||
        contact.value.contact_email ||
        contact.value.contact_phone ||
        contact.value.contact_location,
);

const links = computed(() => {
    const l = [{ id: 'home', label: 'Intro' }];
    if (hasAbout.value) l.push({ id: 'about', label: 'About' });
    if (projects.value.length) l.push({ id: 'portfolio', label: 'Work' });
    if (skills.value.skills_list?.length) l.push({ id: 'skills', label: 'Skills' });
    if (journey.value.journey_list?.length) l.push({ id: 'journey', label: 'Journey' });
    if (hasContact.value) l.push({ id: 'contact', label: 'Contact' });
    return l;
});

const heroWords = computed(() => (hero.value.hero_name || '').split(' ').filter(Boolean));
const socialLinks = computed<any[]>(() => hero.value.hero_social_links || contact.value.contact_social_links || []);

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

function onScroll() {
    if (raf) return;
    raf = requestAnimationFrame(() => {
        const h = document.documentElement.scrollHeight - window.innerHeight;
        progress.value = h > 0 ? Math.min(1, window.scrollY / h) : 0;
        raf = 0;
    });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const hasIO = 'IntersectionObserver' in window;

    if (hasIO) {
        sectionObserver = new IntersectionObserver(
            (entries) => entries.forEach((e) => e.isIntersecting && (activeSection.value = e.target.id)),
            { rootMargin: '-40% 0px -55% 0px' },
        );
        root.value?.querySelectorAll('[data-section]').forEach((el) => sectionObserver?.observe(el));
    }

    if (reduced || !hasIO) return;

    revealObserver = new IntersectionObserver(
        (entries) =>
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver?.unobserve(entry.target);
                }
            }),
        { threshold: 0.35 },
    );
    root.value?.classList.add('motion-ready');
    root.value?.querySelectorAll('[data-reveal]').forEach((el) => revealObserver?.observe(el));
    requestAnimationFrame(() => root.value?.classList.add('is-ready'));
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    if (raf) cancelAnimationFrame(raf);
    revealObserver?.disconnect();
    sectionObserver?.disconnect();
});
</script>

<template>
    <div ref="root" class="rail relative min-h-screen overflow-x-clip font-body">
        <div class="progress" :style="{ transform: `scaleX(${progress})` }" aria-hidden="true"></div>

        <!-- Mobile top bar -->
        <nav class="sticky top-0 z-40 flex items-center gap-4 border-b border-[var(--line)] bg-[#e3cfe6]/85 px-4 py-3 backdrop-blur lg:hidden">
            <img v-if="navbar.show_logo !== false && navbar.show_logo !== 0 && navbar.show_logo !== '0' && navbar.show_logo !== 'false'" :src="navbar.logo_image || '/logo.svg'" :alt="navbar.logo_text" class="h-8 w-8 shrink-0 object-contain" />
            <ul class="no-scrollbar flex gap-1 overflow-x-auto">
                <li v-for="l in links" :key="l.id" class="shrink-0">
                    <a :href="`#${l.id}`" class="block rounded-full px-3 py-1.5 text-sm font-medium" :class="activeSection === l.id ? 'bg-[var(--ink)] text-white' : 'text-[var(--muted)]'">{{ l.label }}</a>
                </li>
            </ul>
        </nav>

        <div class="relative z-[1] grid items-start lg:grid-cols-[264px_minmax(0,1fr)]">
            <!-- Desktop rail -->
            <aside class="rail-panel sticky top-4 hidden h-[calc(100svh-2rem)] flex-col justify-between p-6 lg:ml-4 lg:mt-4 lg:flex">
                <a href="#home" class="rail-in flex items-center gap-3 rounded-lg" style="--i: 0" aria-label="Back to top">
                    <img v-if="navbar.show_logo !== false && navbar.show_logo !== 0 && navbar.show_logo !== '0' && navbar.show_logo !== 'false'" :src="navbar.logo_image || '/logo.svg'" :alt="navbar.logo_text" class="h-10 w-10 shrink-0 object-contain" />
                </a>

                <ul class="space-y-1">
                    <li v-for="(l, i) in links" :key="l.id" class="rail-in" :style="{ '--i': i + 1 }">
                        <a :href="`#${l.id}`" class="rail-link" :class="{ active: activeSection === l.id }">
                            <span class="rail-bar"></span>{{ l.label }}
                        </a>
                    </li>
                </ul>

                <div class="rail-in space-y-3 text-sm text-[var(--muted)]" :style="{ '--i': links.length + 1 }">
                    <a v-if="contact.contact_email" :href="`mailto:${contact.contact_email}`" class="sweep block break-all font-semibold text-[var(--ink)]">{{ contact.contact_email }}</a>
                    <div v-if="socialLinks.length" class="flex flex-wrap gap-x-4 gap-y-1">
                        <a v-for="(link, i) in socialLinks" :key="i" :href="link.url" target="_blank" rel="noreferrer" class="transition-colors hover:text-[var(--accent)]">{{ link.label }}</a>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 px-5 pb-10 sm:px-10 lg:px-16 xl:px-24">
                <!-- INTRO -->
                <header id="home" data-section class="grid min-h-[calc(100svh-60px)] items-center gap-12 py-16 lg:min-h-screen lg:grid-cols-12 lg:py-24">
                    <div class="lg:col-span-8">
                        <p v-if="hero.hero_greeting" class="rail-fade mb-6 flex items-center gap-2 text-base font-medium text-[var(--muted)]" style="--i: 3">
                            <span class="dot"></span>{{ hero.hero_greeting }}
                        </p>

                        <h1 v-if="heroWords.length" class="font-display text-[clamp(3.25rem,8vw,7.5rem)] leading-[0.95] tracking-[-0.025em]">
                            <span v-for="(w, i) in heroWords" :key="i" class="mask">
                                <span class="mask-inner" :style="{ '--i': i }">{{ w }}&nbsp;</span>
                            </span>
                        </h1>

                        <p v-if="hero.hero_role" class="mt-6 text-2xl font-semibold sm:text-3xl">
                            <span class="mark" data-reveal>{{ hero.hero_role }}</span>
                        </p>
                        <p v-if="hero.hero_description" class="rail-fade mt-6 max-w-xl text-lg leading-relaxed text-[var(--muted)] sm:text-xl" style="--i: 6">
                            {{ hero.hero_description }}
                        </p>

                        <div class="rail-fade mt-10 flex flex-wrap items-center gap-x-8 gap-y-4" style="--i: 7">
                            <a v-if="hero.hero_cta_primary_text" :href="hero.hero_cta_primary_url || '#portfolio'" class="btn-ink group">
                                {{ hero.hero_cta_primary_text }}
                                <span class="btn-ink-icon" aria-hidden="true">↓</span>
                            </a>
                            <a v-for="(link, i) in hero.hero_social_links || []" :key="i" :href="link.url" target="_blank" rel="noreferrer" class="sweep font-semibold lg:hidden">{{ link.label }}</a>
                        </div>
                    </div>

                    <figure class="rail-fade lg:col-span-4" style="--i: 5">
                        <div class="portrait group">
                            <img v-if="hero.hero_image" :src="resolvePortfolioMediaUrl(hero.hero_image)" :alt="hero.hero_name || ''" class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.05]" />
                            <span v-else class="grid h-full w-full place-items-center font-display text-[9rem] text-[#2f1a7a]/30" aria-hidden="true">{{ (hero.hero_name || navbar.logo_text || '•').charAt(0) }}</span>
                        </div>
                    </figure>
                </header>

                <!-- ABOUT -->
                <section v-if="hasAbout" id="about" data-section class="section grid gap-12 lg:grid-cols-5">
                    <div v-if="about.about_image" class="lg:col-span-2">
                        <div class="portrait portrait-alt group">
                            <img :src="resolvePortfolioMediaUrl(about.about_image)" :alt="about.about_title || ''" class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.05]" />
                        </div>
                    </div>
                    <div :class="about.about_image ? 'lg:col-span-3' : 'lg:col-span-5 max-w-3xl'">
                        <h2 v-if="about.about_title" class="h2"><span class="mark" data-reveal>{{ about.about_title }}</span></h2>
                        <p v-if="about.about_description" class="mt-8 max-w-xl whitespace-pre-line text-lg leading-[1.8] text-[var(--muted)]">{{ about.about_description }}</p>
                        <a v-if="about.about_cv_file" :href="resolvePortfolioMediaUrl(about.about_cv_file)" class="btn-ink group mt-10">
                            Download CV <span class="btn-ink-icon" aria-hidden="true">↓</span>
                        </a>
                    </div>
                </section>

                <!-- WORK: cards stack as you scroll -->
                <section v-if="projects.length" id="portfolio" data-section class="section">
                    <div class="mb-12 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 v-if="portfolio.projects_title" class="h2">{{ portfolio.projects_title }}</h2>
                            <p v-if="portfolio.projects_subtitle" class="mt-4 max-w-lg text-lg text-[var(--muted)]">{{ portfolio.projects_subtitle }}</p>
                        </div>
                        <p class="text-[var(--muted)]">{{ projects.length }} {{ projects.length === 1 ? 'project' : 'projects' }}</p>
                    </div>

                    <div>
                        <article
                            v-for="(project, index) in projects"
                            :key="index"
                            class="project group mb-14 lg:sticky lg:mb-[34vh]"
                            :class="{ 'lg:!mb-0': index === projects.length - 1 }"
                            :style="{ top: `calc(1.5rem + ${Math.min(index, 5) * 14}px)` }"
                        >
                            <component :is="project.url ? 'a' : 'div'" :href="project.url" :target="project.url ? '_blank' : undefined" rel="noreferrer" class="block">
                                <div class="relative h-[70vh] w-full aspect-[16/9] overflow-hidden bg-gradient-to-br from-[#f1e6f8] to-[#e07ad8]">
                                    <img v-if="project.image" :src="resolvePortfolioMediaUrl(project.image)" :alt="project.title || ''" class="h-full w-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.04]" loading="lazy" />
                                    <span v-else class="grid h-full w-full place-items-center font-display text-9xl text-[#2f1a7a]/30" aria-hidden="true">{{ (project.title || '•').charAt(0) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-6 p-6 sm:p-8">
                                    <div class="min-w-0">
                                        <p class="mb-2 text-sm font-semibold tabular-nums text-[var(--muted)]">{{ index + 1 }} / {{ projects.length }}</p>
                                        <h3 v-if="project.title" class="font-display text-2xl sm:text-4xl">{{ project.title }}</h3>
                                        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-[var(--muted)]">
                                            <span v-if="project.category" class="rounded-full bg-[var(--lilac)] px-3 py-1 font-semibold text-[var(--ink)]">{{ project.category }}</span>
                                            <span v-if="project.period">{{ project.period }}</span>
                                        </div>
                                    </div>
                                    <span v-if="project.url" class="project-arrow" aria-hidden="true">↗</span>
                                </div>
                            </component>
                        </article>
                    </div>
                </section>

                <!-- SKILLS: pills filled to their level -->
                <section v-if="skills.skills_list?.length" id="skills" data-section class="section">
                    <h2 v-if="skills.skills_title" class="h2">{{ skills.skills_title }}</h2>
                    <p v-if="skills.skills_description" class="mt-4 max-w-lg text-lg text-[var(--muted)]">{{ skills.skills_description }}</p>

                    <ul class="mt-12 flex flex-wrap gap-3" data-reveal>
                        <li v-for="(skill, index) in skills.skills_list" :key="index" class="pill" :style="{ '--d': `${index * 70}ms` }">
                            <span v-if="skill.percent" class="pill-fill" :style="{ width: skill.percent + '%' }"></span>
                            <span class="relative z-10 font-semibold">{{ skill.label }}</span>
                            <span v-if="skill.percent" class="relative z-10 text-sm tabular-nums text-[var(--muted)]">{{ skill.percent }}%</span>
                        </li>
                    </ul>
                </section>

                <!-- JOURNEY: large-type ledger on a dark panel -->
                <section v-if="journey.journey_list?.length" id="journey" data-section class="section">
                    <div class="journey-panel">
                        <h2 v-if="journey.journey_title" class="h2 text-white">{{ journey.journey_title }}</h2>
                        <p v-if="journey.journey_subtitle" class="mt-4 max-w-lg text-lg text-white/85">{{ journey.journey_subtitle }}</p>

                        <div class="mt-14">
                            <article v-for="(item, index) in journey.journey_list" :key="index" class="jrow" data-reveal>
                                <p v-if="item.period" class="jperiod">{{ item.period }}</p>
                                <div class="md:col-start-2">
                                    <h3 v-if="item.title" class="font-display text-2xl leading-tight text-white sm:text-4xl">{{ item.title }}</h3>
                                    <p v-if="item.company" class="mt-2 text-lg font-semibold text-[#fff0fb]">{{ item.company }}</p>
                                    <p v-if="item.description" class="mt-4 max-w-xl leading-relaxed text-white/85">{{ item.description }}</p>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <!-- CONTACT -->
                <section v-if="hasContact" id="contact" data-section class="section">
                    <h2 v-if="contact.contact_title" class="font-display text-[clamp(2.75rem,7vw,6rem)] leading-[0.98] tracking-[-0.025em]">{{ contact.contact_title }}</h2>
                    <p v-if="contact.contact_description" class="mt-6 max-w-xl text-lg text-[var(--muted)] sm:text-xl">{{ contact.contact_description }}</p>

                    <a v-if="contact.contact_email" :href="`mailto:${contact.contact_email}`" class="sweep mt-10 inline-block break-all font-display text-2xl text-[var(--accent)] sm:text-4xl">{{ contact.contact_email }}</a>
                    <div class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-[var(--muted)]">
                        <a v-if="contact.contact_phone" :href="`tel:${contact.contact_phone}`" class="hover:text-[var(--ink)]">{{ contact.contact_phone }}</a>
                        <span v-if="contact.contact_location">{{ contact.contact_location }}</span>
                        <a v-for="(link, i) in contact.contact_social_links || []" :key="i" :href="link.url" target="_blank" rel="noreferrer" class="hover:text-[var(--ink)]">{{ link.label }}</a>
                    </div>

                    <div v-if="contact.contact_form_enabled !== false" class="mt-14 rounded-[28px] border border-white/60 bg-white/75 p-6 shadow-[0_30px_60px_-40px_rgba(91,33,182,0.5)] backdrop-blur-md sm:p-10">
                        <form
                            v-if="formReady"
                            :action="formAction"
                            method="POST"
                            target="bg-gform-frame"
                            class="space-y-5 text-left"
                            @submit="onSubmit"
                        >
                            <h3 class="font-display text-2xl font-semibold text-[var(--ink)]">Send a message</h3>
                            <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">
                                Name
                                <input :name="`entry.${googleForm?.nameEntry}`" type="text" required autocomplete="name" class="w-full rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-[var(--ink)] focus:border-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/20" />
                            </label>
                            <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">
                                Email
                                <input :name="`entry.${googleForm?.emailEntry}`" type="email" required autocomplete="email" class="w-full rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-[var(--ink)] focus:border-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/20" />
                            </label>
                            <label class="block space-y-2 text-sm font-medium text-[var(--muted)]">
                                Message
                                <textarea :name="`entry.${googleForm?.messageEntry}`" rows="5" required class="w-full resize-y rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-[var(--ink)] focus:border-[var(--accent)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/20" />
                            </label>
                            <button type="submit" :disabled="sending" class="btn-ink disabled:cursor-not-allowed disabled:opacity-60">
                                {{ sending ? 'Sending…' : 'Send message' }}
                                <span class="btn-ink-icon" aria-hidden="true">→</span>
                            </button>
                            <p v-if="sent" role="status" class="text-sm font-semibold text-[var(--accent)]">Your message was submitted.</p>
                        </form>
                    </div>
                    <iframe name="bg-gform-frame" title="Form submission target" class="hidden" tabindex="-1" aria-hidden="true" />
                </section>

                <footer v-if="navbar.logo_text" class="mt-24 flex items-center justify-between border-t border-[var(--line)] pt-6 text-sm text-[var(--muted)]">
                    <img v-if="navbar.show_logo !== false && navbar.show_logo !== 0 && navbar.show_logo !== '0' && navbar.show_logo !== 'false'" :src="navbar.logo_image || '/logo.svg'" :alt="navbar.logo_text" class="h-7 w-7 object-contain" />
                    <a href="#home" class="sweep font-semibold text-[var(--ink)]">Back to top ↑</a>
                </footer>
            </main>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Young+Serif&family=Hanken+Grotesk:wght@400;500;600;700&display=swap');
html { scroll-behavior: smooth; scroll-padding-top: 70px; }
</style>

<style scoped>
.rail {
    --ink: #2a0f2d;
    --paper: #e3cfe6;
    --muted: #4a2650;
    --accent: #5b21b6;
    --orchid: #e07ad8;
    --orchid-soft: #f7c6f1;
    --lilac: #f1e6f8;
    --mark: rgba(255, 255, 255, 0.85);
    --line: rgba(42, 15, 45, 0.16);
    --grad: linear-gradient(180deg, #ddd5e3 0%, #dfcfe2 24%, #e4badf 44%, #e8a6dd 62%, #e484d6 82%, #d676d6 100%);
    --grain: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='220' height='220'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2' stitchTiles='stitch'/><feColorMatrix type='saturate' values='0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
    --ease: cubic-bezier(0.22, 1, 0.36, 1);
    background: var(--grad);
    color: var(--ink);
}
.rail::before { content: ''; position: absolute; inset: 0; z-index: 0; pointer-events: none; background-image: var(--grain); opacity: 0.55; mix-blend-mode: soft-light; }
.font-display { font-family: 'Young Serif', ui-serif, Georgia, serif; font-weight: 400; }
.font-body { font-family: 'Hanken Grotesk', ui-sans-serif, system-ui, sans-serif; }
.rail a:focus-visible, .rail button:focus-visible { outline: 2px solid var(--accent); outline-offset: 4px; }
/* Sidebar: same grainy gradient as the page, as a floating panel */
.rail-panel { position: sticky; border-radius: 28px; background: var(--grad); border: 1px solid rgba(255, 255, 255, 0.6); box-shadow: 0 30px 60px -30px rgba(130, 40, 150, 0.55); overflow: hidden; isolation: isolate; }
.rail-panel::before { content: ''; position: absolute; inset: 0; z-index: -1; pointer-events: none; background-image: var(--grain); opacity: 0.55; mix-blend-mode: soft-light; }
.rail ::selection { background: var(--ink); color: var(--orchid-soft); }
.rail h1, .rail h2, .rail h3 { text-wrap: balance; }
.rail p { text-wrap: pretty; }
.progress { position: fixed; inset: 0 0 auto 0; z-index: 60; height: 3px; transform-origin: left; background: linear-gradient(90deg, var(--accent), var(--orchid)); }
.no-scrollbar { scrollbar-width: none; }
.no-scrollbar::-webkit-scrollbar { display: none; }

.section { padding-top: clamp(5rem, 12vw, 9rem); }
.h2 { font-family: 'Young Serif', serif; font-size: clamp(2.5rem, 5.5vw, 4.5rem); line-height: 1.02; letter-spacing: -0.02em; }

/* Rail nav */
.rail-link { display: flex; align-items: center; gap: 0.8rem; padding: 0.5rem 0; font-size: 1rem; font-weight: 500; color: rgba(42, 15, 45, 0.55); transition: color 0.3s, transform 0.4s var(--ease); }
.rail-link:hover { color: var(--ink); transform: translateX(4px); }
.rail-bar { height: 2px; width: 14px; border-radius: 2px; background: currentColor; transition: width 0.5s var(--ease), background 0.3s; }
.rail-link.active { color: var(--ink); }
.rail-link.active .rail-bar { width: 40px; background: var(--ink); }

/* Intro entrance */
.mask { display: inline-block; overflow: hidden; vertical-align: top; padding-bottom: 0.12em; margin-bottom: -0.12em; }
.mask-inner { display: inline-block; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); animation: ping 2.4s infinite; }
@keyframes ping { 0% { box-shadow: 0 0 0 0 rgba(91, 33, 182, 0.45); } 70%, 100% { box-shadow: 0 0 0 10px rgba(91, 33, 182, 0); } }
.rail.motion-ready .mask-inner { transform: translateY(115%); transition: transform 1.1s var(--ease) calc(var(--i) * 100ms + 250ms); }
.rail.motion-ready.is-ready .mask-inner { transform: none; }
.rail.motion-ready .rail-fade { opacity: 0; transform: translateY(18px); transition: opacity 0.9s var(--ease) calc(var(--i) * 100ms + 250ms), transform 0.9s var(--ease) calc(var(--i) * 100ms + 250ms); }
.rail.motion-ready.is-ready .rail-fade { opacity: 1; transform: none; }
.rail.motion-ready .rail-in { opacity: 0; transform: translateX(-16px); transition: opacity 0.8s var(--ease) calc(var(--i) * 70ms + 150ms), transform 0.8s var(--ease) calc(var(--i) * 70ms + 150ms); }
.rail.motion-ready.is-ready .rail-in { opacity: 1; transform: none; }

/* Highlighter that sweeps across a heading */
.mark { background: linear-gradient(transparent 62%, var(--mark) 62%) no-repeat left / 0% 100%; box-decoration-break: clone; -webkit-box-decoration-break: clone; padding: 0 0.12em; margin: 0 -0.12em; transition: background-size 1.2s var(--ease) 0.5s; }
.rail:not(.motion-ready) .mark, .mark.is-visible { background-size: 100% 100%; }

/* Portraits */
.portrait { aspect-ratio: 4 / 5; overflow: hidden; background: rgba(255, 255, 255, 0.55); border-radius: 140px 28px 140px 28px; }
.portrait-alt { border-radius: 28px 140px 28px 140px; }

/* Buttons + links */
.btn-ink { display: inline-flex; align-items: center; gap: 0.9rem; padding: 0.5rem 0.5rem 0.5rem 1.6rem; border-radius: 999px; background: var(--ink); color: #fff; font-weight: 600; transition: background 0.3s, transform 0.35s var(--ease); }
.btn-ink:hover { background: var(--accent); transform: translateY(-2px); }
.btn-ink-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%; background: var(--orchid-soft); color: var(--ink); transition: transform 0.45s var(--ease); }
.btn-ink:hover .btn-ink-icon { transform: translateY(3px); }
.sweep { background: linear-gradient(currentColor, currentColor) no-repeat 100% 100% / 0 2px; padding-bottom: 2px; transition: background-size 0.45s var(--ease); }
.sweep:hover { background-position: 0 100%; background-size: 100% 2px; }

/* Work */
.project { overflow: hidden; border-radius: 28px; background: #fff; border: 1px solid var(--line); box-shadow: 0 30px 60px -36px rgba(91, 33, 182, 0.55); }
.project-arrow { display: grid; flex-shrink: 0; place-items: center; width: 52px; height: 52px; border-radius: 50%; background: var(--ink); color: #fff; font-size: 1.3rem; transition: background 0.3s, color 0.3s, transform 0.5s var(--ease); }
.project:hover .project-arrow { background: var(--orchid); color: var(--ink); transform: rotate(45deg); }

/* Skills */
.pill { position: relative; display: inline-flex; align-items: center; gap: 0.9rem; overflow: hidden; padding: 0.8rem 1.4rem; border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.7); background: rgba(255, 255, 255, 0.78); backdrop-filter: blur(10px); transition: border-color 0.3s, transform 0.4s var(--ease); }
.pill:hover { border-color: var(--accent); transform: translateY(-2px); }
.pill-fill { position: absolute; inset: 0 auto 0 0; background: var(--orchid-soft); transform-origin: left; }
.rail.motion-ready .pill-fill { transform: scaleX(0); transition: transform 1.2s var(--ease) calc(var(--d, 0ms) + 200ms); }
.rail.motion-ready .is-visible .pill-fill { transform: none; }

/* Journey */
.journey-panel { padding: clamp(1.75rem, 5vw, 4.5rem); border-radius: 36px; background: linear-gradient(155deg, #ea74ca 0%, #d04aa8 50%, #a82a86 100%); box-shadow: 0 40px 80px -40px rgba(168, 42, 134, 0.7); }
.jrow { position: relative; display: grid; gap: 1rem 3rem; padding: 2.5rem 0; }
@media (min-width: 768px) { .jrow { grid-template-columns: minmax(170px, 0.7fr) 2fr; padding: 3.25rem 0; } }
.jrow::before, .jrow::after { content: ''; position: absolute; inset: 0 0 auto 0; height: 2px; }
.jrow::before { background: rgba(255, 255, 255, 0.28); }
.jrow::after { background: #fff; transform-origin: left; }
.rail.motion-ready .jrow::after { transform: scaleX(0); transition: transform 1.3s var(--ease); }
.rail.motion-ready .jrow.is-visible::after { transform: none; }
.jperiod { font-family: 'Young Serif', serif; font-size: clamp(2rem, 4.2vw, 3.5rem); line-height: 1; color: #fff; transition: transform 0.5s var(--ease); }
.jrow:hover .jperiod { transform: translateX(10px); }

@media (prefers-reduced-motion: reduce) {
    .rail .mask-inner, .rail .rail-fade, .rail .rail-in, .rail .pill-fill { opacity: 1 !important; transform: none !important; transition: none !important; }
    .mark { background-size: 100% 100% !important; transition: none !important; }
    .dot { animation: none !important; }
    .jrow::after { transform: none !important; transition: none !important; }
}
</style>
