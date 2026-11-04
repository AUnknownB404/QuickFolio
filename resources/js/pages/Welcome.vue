<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import {
    ArrowRight,
    Check,
    Download,
    Globe2,
    LayoutTemplate,
    Mail,
    MousePointerClick,
} from 'lucide-vue-next';

const page = usePage();
const isAuthed = computed(() => Boolean((page.props as any).auth?.user));

/**
 * One primary action, always the most useful next step for *this* visitor:
 * new visitors are invited to start; returning users go back to their work.
 */
const primaryCta = computed(() =>
    isAuthed.value
        ? { href: route('dashboard'), label: 'Go to your dashboard' }
        : { href: route('register'), label: 'Create your portfolio' },
);

/* -------------------------------------------------------------------------
 * Try-it demo: the core idea of the product (edit → see it instantly),
 * shown instead of described. Nothing here is saved or sent anywhere.
 * ---------------------------------------------------------------------- */

const accents = [
    { id: 'teal', label: 'Teal', color: '#00695C' },
    { id: 'indigo', label: 'Indigo', color: '#4F46E5' },
    { id: 'amber', label: 'Amber', color: '#B45309' },
    { id: 'rose', label: 'Rose', color: '#BE123C' },
];

const demoName = ref('');
const demoRole = ref('');
const accent = ref('teal');
const mode = ref<'light' | 'dark'>('light');

// Empty fields fall back to a sample so the preview never looks broken.
const shownName = computed(() => demoName.value.trim() || 'Alex Morgan');
const shownRole = computed(() => demoRole.value.trim() || 'Product designer');
const accentColor = computed(() => accents.find((option) => option.id === accent.value)?.color ?? '#00695C');
const initials = computed(() =>
    shownName.value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join(''),
);
const hasEdited = computed(() => demoName.value.trim() !== '' || demoRole.value.trim() !== '');

const sampleProjects = [
    { title: 'Brand refresh', category: 'Identity' },
    { title: 'Banking app', category: 'Mobile' },
    { title: 'Studio website', category: 'Web' },
];
const sampleSkills = ['Figma', 'Research', 'Prototyping', 'Vue'];

/* -------------------------------------------------------------------------
 * Content — each point maps to something the editor really does.
 * ---------------------------------------------------------------------- */

const portfolioSections = ['Intro', 'About', 'Experience', 'Skills', 'Projects', 'Services', 'Contact'];

const steps = [
    {
        number: '1',
        title: 'Pick a template',
        description: 'Browse the designs and choose the one that suits your field.',
        link: true,
    },
    {
        number: '2',
        title: 'Fill in your story',
        description: 'Add your intro, experience, skills and projects. The preview updates as you type.',
        link: false,
    },
    {
        number: '3',
        title: 'Publish and share',
        description: 'Go live with one click and share a single link. Download a copy whenever you like.',
        link: false,
    },
];
</script>

<template>
    <Head title="quickFolio — Build Your Portfolio">
        <meta
            name="description"
            content="Pick a template, fill in your story and publish a portfolio you can share with one link. No code required."
        />
    </Head>

    <div class="min-h-screen bg-white dark:bg-[#0F172A] font-sans text-[#0F172A] dark:text-slate-100">
        <a
            href="#main"
            class="sr-only rounded-lg bg-white dark:bg-[#0F172A] px-3 py-2 text-sm font-semibold text-[#00695C] shadow focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50"
        >
            Skip to content
        </a>

        <!-- ───────────── Header ───────────── -->
        <header class="sticky top-0 z-30 border-b border-slate-100 dark:border-slate-700 bg-white/90 dark:bg-[#0F172A]/90 backdrop-blur">
            <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8" aria-label="Main navigation">
                <div class="flex items-center gap-8">
                    <Link href="/" class="flex items-center" aria-label="quickFolio home">
                        <AppLogo size="lg" />
                    </Link>
                    <div class="hidden items-center gap-1 md:flex">
                        <a href="#features" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 transition hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40">What you get</a>
                        <a href="#how-it-works" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 transition hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40">How it works</a>
                        <Link :href="route('templates.index')" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 transition hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40">Templates</Link>
                    </div>
                </div>

                <!-- The filled button is always the action we most want from this visitor -->
                <div v-if="!isAuthed" class="flex items-center gap-1 sm:gap-2">
                    <Link :href="route('login')" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-medium text-slate-600 dark:text-slate-300 transition hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40">
                        Log in
                    </Link>
                    <Link :href="route('register')" class="inline-flex min-h-10 items-center rounded-lg bg-[#00695C] px-4 text-sm font-semibold text-white transition hover:bg-[#00574B] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 focus-visible:ring-offset-2">
                        Sign up
                    </Link>
                </div>
                <div v-else>
                    <Link :href="route('dashboard')" class="inline-flex min-h-10 items-center rounded-lg bg-[#00695C] px-4 text-sm font-semibold text-white transition hover:bg-[#00574B] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 focus-visible:ring-offset-2">
                        Dashboard
                    </Link>
                </div>
            </nav>
        </header>

        <main id="main">
            <!-- ───────────── Hero + live demo ───────────── -->
            <section class="overflow-hidden bg-[#F4F7F7] dark:bg-[#0F172A]">
                <div class="mx-auto grid max-w-7xl items-start gap-10 px-5 py-12 sm:px-8 sm:py-16 lg:grid-cols-[minmax(0,1fr)_minmax(0,36rem)] lg:gap-14 lg:py-20">
                    <div class="max-w-xl lg:pt-6">
                        <h1 class="text-4xl font-bold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl">
                            Show your work.
                            <span class="text-[#00695C]">Skip the website building.</span>
                        </h1>
                        <p class="mt-5 max-w-lg text-base leading-7 text-slate-600 dark:text-slate-300 sm:text-lg">
                            Pick a template, fill in your story, and publish a portfolio you can share with a single link. No code, no setup.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Link
                                :href="primaryCta.href"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-6 text-base font-semibold text-white shadow-sm transition hover:bg-[#00574B] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#00695C]/25"
                            >
                                {{ primaryCta.label }}
                                <ArrowRight class="h-5 w-5" aria-hidden="true" />
                            </Link>
                            <Link
                                :href="route('templates.index')"
                                class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[#BFD4CE] dark:border-slate-600 bg-white dark:bg-[#0F172A] px-6 text-base font-semibold text-[#00695C] transition hover:bg-[#F0F7F5] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#00695C]/20"
                            >
                                Browse templates
                            </Link>
                        </div>

                        <ul class="mt-8 space-y-2.5 text-sm text-slate-600 dark:text-slate-300">
                            <li class="flex items-center gap-2.5"><Check class="h-4 w-4 shrink-0 text-[#00695C]" aria-hidden="true" />Click any text on the page to edit it</li>
                            <li class="flex items-center gap-2.5"><Check class="h-4 w-4 shrink-0 text-[#00695C]" aria-hidden="true" />Preview on desktop and mobile before you publish</li>
                            <li class="flex items-center gap-2.5"><Check class="h-4 w-4 shrink-0 text-[#00695C]" aria-hidden="true" />Download your portfolio as a ZIP, any time</li>
                        </ul>
                    </div>

                    <!-- Try it -->
                    <div class="w-full min-w-0">
                        <div class="rounded-2xl border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-[#0F172A] p-4 shadow-sm sm:p-5">
                            <p class="flex items-center gap-2 text-sm font-semibold text-[#00695C]">
                                <MousePointerClick class="h-4 w-4" aria-hidden="true" />
                                Try it — make it yours
                            </p>

                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="demo-name" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Your name</label>
                                    <input
                                        id="demo-name"
                                        v-model="demoName"
                                        type="text"
                                        maxlength="32"
                                        autocomplete="off"
                                        placeholder="Alex Morgan"
                                        class="mt-1 h-11 w-full rounded-lg border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-[#0F172A] px-3 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-[#00695C] focus:outline-none focus:ring-2 focus:ring-[#00695C]/20"
                                    />
                                </div>
                                <div>
                                    <label for="demo-role" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">What you do</label>
                                    <input
                                        id="demo-role"
                                        v-model="demoRole"
                                        type="text"
                                        maxlength="36"
                                        autocomplete="off"
                                        placeholder="Product designer"
                                        class="mt-1 h-11 w-full rounded-lg border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-[#0F172A] px-3 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-[#00695C] focus:outline-none focus:ring-2 focus:ring-[#00695C]/20"
                                    />
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                                <fieldset class="flex items-center gap-2">
                                    <legend class="sr-only">Accent colour</legend>
                                    <span class="mr-1 text-xs font-semibold text-slate-600 dark:text-slate-300" aria-hidden="true">Colour</span>
                                    <label v-for="option in accents" :key="option.id" class="relative cursor-pointer">
                                        <input v-model="accent" type="radio" name="demo-accent" :value="option.id" class="peer sr-only" />
                                        <span
                                            class="block h-9 w-9 rounded-full ring-2 ring-transparent ring-offset-2 transition peer-checked:ring-slate-900 peer-focus-visible:ring-[#00695C]"
                                            :style="{ backgroundColor: option.color }"
                                        >
                                            <span class="sr-only">{{ option.label }}</span>
                                        </span>
                                    </label>
                                </fieldset>

                                <fieldset class="inline-flex rounded-lg border border-[#DCE6E4] dark:border-slate-700 p-0.5">
                                    <legend class="sr-only">Appearance</legend>
                                    <label v-for="option in (['light', 'dark'] as const)" :key="option" class="relative cursor-pointer">
                                        <input v-model="mode" type="radio" name="demo-mode" :value="option" class="peer sr-only" />
                                        <span class="flex h-9 items-center rounded-md px-3.5 text-sm font-medium capitalize text-slate-600 dark:text-slate-300 transition peer-checked:bg-[#E8F4F1] peer-checked:text-[#00695C] peer-focus-visible:ring-2 peer-focus-visible:ring-[#00695C]">
                                            {{ option }}
                                        </span>
                                    </label>
                                </fieldset>
                            </div>
                        </div>

                        <!-- The portfolio, updating as you type -->
                        <figure class="mt-3 overflow-hidden rounded-2xl border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-[#0F172A] shadow-lg" aria-label="A live example of a portfolio">
                            <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-[#0F172A] px-3 py-2" aria-hidden="true">
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                                <span class="mx-auto max-w-[60%] truncate rounded-md bg-white dark:bg-[#0F172A] px-3 py-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ shownName }} · portfolio</span>
                            </div>

                            <div
                                class="transition-colors duration-200 motion-reduce:transition-none"
                                :class="mode === 'dark' ? 'bg-slate-900 text-slate-100' : 'bg-white text-slate-900'"
                            >
                                <div class="flex items-center justify-between px-5 pt-4 sm:px-7">
                                    <span
                                        class="grid h-8 w-8 place-items-center rounded-lg text-xs font-bold text-white"
                                        :style="{ backgroundColor: accentColor }"
                                    >
                                        {{ initials }}
                                    </span>
                                    <span class="flex gap-4 text-[11px] font-medium" :class="mode === 'dark' ? 'text-slate-400' : 'text-slate-500'">
                                        <span>About</span><span>Projects</span><span>Contact</span>
                                    </span>
                                </div>

                                <div class="px-5 pb-5 pt-6 sm:px-7">
                                    <p class="text-xs font-medium" :class="mode === 'dark' ? 'text-slate-400' : 'text-slate-500'">Hello, I am</p>
                                    <p class="mt-1 break-words text-2xl font-bold leading-tight sm:text-3xl">{{ shownName }}</p>
                                    <span
                                        class="mt-2 inline-block max-w-full break-words rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                        :style="{ color: mode === 'dark' ? '#fff' : accentColor, backgroundColor: mode === 'dark' ? accentColor : `${accentColor}1A` }"
                                    >
                                        {{ shownRole }}
                                    </span>
                                    <p class="mt-3 max-w-sm text-sm leading-6" :class="mode === 'dark' ? 'text-slate-300' : 'text-slate-600'">
                                        I make simple, useful things. Here is some of my recent work.
                                    </p>
                                    <span class="mt-4 inline-flex rounded-lg px-3.5 py-2 text-xs font-semibold text-white" :style="{ backgroundColor: accentColor }">
                                        View my work
                                    </span>
                                </div>

                                <div class="grid grid-cols-3 gap-2.5 px-5 pb-4 sm:px-7">
                                    <div
                                        v-for="project in sampleProjects"
                                        :key="project.title"
                                        class="overflow-hidden rounded-lg border"
                                        :class="mode === 'dark' ? 'border-slate-700 bg-slate-800' : 'border-slate-200 bg-slate-50'"
                                    >
                                        <div class="h-12" :style="{ background: `linear-gradient(135deg, ${accentColor}, ${accentColor}66)` }"></div>
                                        <div class="p-2">
                                            <p class="truncate text-[11px] font-semibold">{{ project.title }}</p>
                                            <p class="truncate text-[10px]" :class="mode === 'dark' ? 'text-slate-400' : 'text-slate-500'">{{ project.category }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-1.5 px-5 pb-5 sm:px-7">
                                    <span
                                        v-for="skill in sampleSkills"
                                        :key="skill"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium"
                                        :class="mode === 'dark' ? 'border-slate-700 text-slate-300' : 'border-slate-200 text-slate-600'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: accentColor }"></span>
                                        {{ skill }}
                                    </span>
                                </div>
                            </div>
                        </figure>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-sm">
                            <p class="text-slate-500 dark:text-slate-400" aria-live="polite">
                                {{ hasEdited ? 'Looking good. Real templates have many more sections.' : 'A sample only. Real templates have many more sections.' }}
                            </p>
                            <Link :href="primaryCta.href" class="inline-flex items-center gap-1.5 font-semibold text-[#00695C] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40">
                                {{ isAuthed ? 'Open dashboard' : 'Start building yours' }}
                                <ArrowRight class="h-4 w-4" aria-hidden="true" />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ───────────── What you get ───────────── -->
            <section id="features" class="mx-auto max-w-7xl scroll-mt-20 px-5 py-16 sm:px-8 sm:py-20">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Built around how you actually make a portfolio</h2>
                    <p class="mt-3 leading-7 text-slate-600 dark:text-slate-300">Everything below is in the editor today.</p>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-6">
                    <article class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0F172A] p-6 transition hover:border-[#B5D7CE] hover:shadow-sm md:col-span-3">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-[#E2F0EC] text-[#00695C]"><MousePointerClick class="h-5 w-5" aria-hidden="true" /></span>
                        <h3 class="mt-4 text-lg font-semibold">Edit right on the page</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Click any text or image in the live preview and change it in a side panel. Check how it looks on desktop and mobile before anyone else sees it.</p>
                        <div class="mt-5 rounded-xl bg-[#F4F7F7] dark:bg-[#0F172A] p-4" aria-hidden="true">
                            <span class="inline-block rounded-md border-2 border-[#00695C] px-2 py-1 text-base font-bold">Alex Morgan</span>
                            <span class="ml-2 inline-block rounded-full bg-[#E8F4F1] px-2 py-0.5 text-[11px] font-semibold text-[#00695C]">Intro › Name</span>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0F172A] p-6 transition hover:border-[#B5D7CE] hover:shadow-sm md:col-span-3">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-[#E2F0EC] text-[#00695C]"><LayoutTemplate class="h-5 w-5" aria-hidden="true" /></span>
                        <h3 class="mt-4 text-lg font-semibold">Sections that fit your story</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Show what matters and hide what doesn’t. Reorder projects and skills, and bring hidden sections back whenever you want.</p>
                        <ul class="mt-5 flex flex-wrap gap-2" aria-label="Portfolio sections">
                            <li v-for="name in portfolioSections" :key="name" class="rounded-full border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-[#0F172A] px-3 py-1 text-xs font-medium text-slate-600 dark:text-slate-300">{{ name }}</li>
                        </ul>
                    </article>

                    <article class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0F172A] p-6 transition hover:border-[#B5D7CE] hover:shadow-sm md:col-span-2">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-[#E2F0EC] text-[#00695C]"><Globe2 class="h-5 w-5" aria-hidden="true" /></span>
                        <h3 class="mt-4 text-lg font-semibold">Publish with one link</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Go live when you’re ready and take it offline just as easily. Nothing is public until you say so.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0F172A] p-6 transition hover:border-[#B5D7CE] hover:shadow-sm md:col-span-2">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-[#E2F0EC] text-[#00695C]"><Mail class="h-5 w-5" aria-hidden="true" /></span>
                        <h3 class="mt-4 text-lg font-semibold">Messages come to you</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Connect a Google Form and visitors can contact you straight from your portfolio.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0F172A] p-6 transition hover:border-[#B5D7CE] hover:shadow-sm md:col-span-2">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-[#E2F0EC] text-[#00695C]"><Download class="h-5 w-5" aria-hidden="true" /></span>
                        <h3 class="mt-4 text-lg font-semibold">Yours to keep</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Download your whole portfolio as a ZIP whenever you like. You’re never locked in.</p>
                    </article>
                </div>
            </section>

            <!-- ───────────── How it works ───────────── -->
            <section id="how-it-works" class="scroll-mt-20 bg-[#F4F7F7] dark:bg-[#0F172A]">
                <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
                    <div class="max-w-2xl">
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">From template to live link in three steps</h2>
                    </div>

                    <ol class="relative mt-10 grid gap-5 md:grid-cols-3">
                        <li v-for="step in steps" :key="step.number" class="relative rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0F172A] p-6">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-[#00695C] text-base font-bold text-white" aria-hidden="true">{{ step.number }}</span>
                            <h3 class="mt-4 text-lg font-semibold"><span class="sr-only">Step {{ step.number }}: </span>{{ step.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ step.description }}</p>
                            <Link
                                v-if="step.link"
                                :href="route('templates.index')"
                                class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[#00695C] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                            >
                                Browse templates
                                <ArrowRight class="h-4 w-4" aria-hidden="true" />
                            </Link>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- ───────────── Closing call to action ───────────── -->
            <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
                <div class="rounded-3xl bg-[#00695C] px-6 py-12 text-center text-white sm:px-12 sm:py-16">
                    <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
                        {{ isAuthed ? 'Your portfolio is waiting.' : 'Ready to put your work online?' }}
                    </h2>
                    <p class="mx-auto mt-3 max-w-xl leading-7 text-white/80">
                        {{ isAuthed ? 'Pick up where you left off, or start a new one from a template.' : 'Choose a template and have your first section filled in today.' }}
                    </p>
                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            :href="primaryCta.href"
                            class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-white dark:bg-[#0F172A] px-6 text-base font-semibold text-[#00695C] transition hover:bg-[#F0F7F5] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/40 sm:w-auto"
                        >
                            {{ primaryCta.label }}
                            <ArrowRight class="h-5 w-5" aria-hidden="true" />
                        </Link>
                        <Link
                            :href="route('templates.index')"
                            class="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-white/40 px-6 text-base font-semibold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/40 sm:w-auto"
                        >
                            Browse templates
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-100 dark:border-slate-700">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <Link href="/" class="flex w-fit items-center" aria-label="quickFolio home"><AppLogo size="sm" /></Link>
                <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-500 dark:text-slate-400" aria-label="Footer">
                    <a href="#features" class="hover:text-[#00695C]">What you get</a>
                    <a href="#how-it-works" class="hover:text-[#00695C]">How it works</a>
                    <Link :href="route('templates.index')" class="hover:text-[#00695C]">Templates</Link>
                </nav>
                <p class="text-sm text-slate-500 dark:text-slate-400">© {{ new Date().getFullYear() }} quickFolio. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>
