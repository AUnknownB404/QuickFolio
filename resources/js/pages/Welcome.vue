
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';

const features = [
    {
        number: '01',
        title: 'No coding required',
        description:
            'Build a beautiful website with a simple visual editor. If you can use a computer, you can build a portfolio.',
        icon: '✳',
    },
    {
        number: '02',
        title: 'Designed to impress',
        description:
            'Choose from thoughtfully designed templates that help your work stand out and look professional.',
        icon: '▦',
    },
    {
        number: '03',
        title: 'Your own online space',
        description:
            'Publish your portfolio and share one simple link with recruiters, clients, and the world.',
        icon: '↗',
    },
]

const steps = [
    {
        number: '01',
        title: 'Choose a template',
        description: 'Start with a design that matches your profession and personality.',
    },
    {
        number: '02',
        title: 'Make it yours',
        description: 'Add your projects, skills, experience, images, and personal story.',
    },
    {
        number: '03',
        title: 'Publish and share',
        description: 'Bring your work online and share your portfolio with confidence.',
    },
]
</script>

<template>
    <Head title="quickFolio — Your Portfolio, Without the Code" />

    <main class="overflow-hidden bg-white font-sans text-[#0F172A]">

        <!-- NAVBAR -->
        <header class="relative z-20 border-b border-slate-100 bg-white">
            <nav
                class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-12"
                aria-label="Main navigation"
            >
                <Link href="/" class="flex items-center">
                    <AppLogo size="lg" />
                </Link>

                <div class="hidden items-center gap-8 md:flex">
                    <a href="#features" class="text-sm text-slate-600 transition hover:text-[#00695C]">
                        Features
                    </a>
                    <a href="#how-it-works" class="text-sm text-slate-600 transition hover:text-[#00695C]">
                        How it works
                    </a>
                    <a href="#templates" class="text-sm text-slate-600 transition hover:text-[#00695C]">
                        Templates
                    </a>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('login')"
                        class="hidden rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:inline-flex"
                    >
                        Log in
                    </Link>

                    <Link
                        :href="route('register')"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#00695C] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#00574B] sm:px-5"
                    >
                        Get started
                        <span aria-hidden="true">→</span>
                    </Link>
                </div>
            </nav>
        </header>

        <!-- HERO -->
        <section class="relative bg-[#F4F7F7]">
            <div
                class="mx-auto grid min-h-[650px] max-w-7xl items-center gap-8 px-5 py-16 sm:px-8 md:py-20 lg:grid-cols-2 lg:px-12 lg:py-24"
            >
                <!-- Hero copy -->
                <div class="relative z-10 max-w-xl">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-[#C8E1DA] bg-white px-3.5 py-2 text-xs font-semibold text-[#00695C]"
                    >
                        <span class="h-2 w-2 rounded-full bg-[#00695C]"></span>
                        YOUR WORK DESERVES TO BE SEEN
                    </div>

                    <h1
                        class="mt-7 text-5xl font-bold leading-[1.08] tracking-tight sm:text-6xl xl:text-7xl"
                    >
                        Your portfolio.
                        <br />
                        <span class="text-[#00695C]">Your story.</span>
                        <br />
                        No code.
                    </h1>

                    <p class="mt-7 max-w-lg text-base leading-8 text-slate-600 sm:text-lg">
                        Turn your skills, projects, and ideas into a professional
                        portfolio website — without writing a single line of code.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center justify-center gap-3 rounded-xl bg-[#00695C] px-6 py-4 text-sm font-semibold text-white transition hover:bg-[#00574B]"
                        >
                            Create your portfolio
                            <span aria-hidden="true">→</span>
                        </Link>

                        <a
                            href="#how-it-works"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-4 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
                        >
                            See how it works
                        </a>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-slate-500">
                        <span class="flex items-center gap-2">
                            <span class="font-bold text-[#00695C]">✓</span>
                            No coding skills needed
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="font-bold text-[#00695C]">✓</span>
                            Easy customization
                        </span>
                    </div>
                </div>

                <!-- Floating device mockup -->
                <div class="relative mx-auto flex w-full max-w-2xl items-center justify-center lg:min-h-[510px]">
                    <div
                        class="absolute right-4 top-6 h-28 w-28 rounded-full border border-[#D8E6E1]"
                    ></div>
                    <div
                        class="absolute bottom-8 left-4 h-20 w-20 rounded-full bg-[#DCEDE9]"
                    ></div>

                    <div class="relative z-10 w-full">
                        <img
                            src="/storage/Mockup.png"
                            alt="quickFolio portfolio preview on a laptop and phone beside a potted plant"
                            class="hero-mockup relative z-10 h-auto w-full object-contain drop-shadow-2xl"
                        />
                    </div>

                    <!-- Floating information card -->
                    <div
                        class="absolute bottom-5 left-0 z-20 hidden max-w-[230px] rounded-2xl border border-slate-100 bg-white p-4 shadow-xl sm:block lg:-left-5"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#DCEDE9] text-lg text-[#00695C]">
                                ↗
                            </div>
                            <div>
                                <p class="text-sm font-bold">Your work, online</p>
                                <p class="mt-1 text-xs text-slate-500">Ready to share</p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="absolute right-0 top-12 z-20 hidden rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-lg sm:block"
                    >
                        <p class="text-xs font-medium text-slate-500">MADE FOR YOU</p>
                        <p class="mt-1 text-sm font-bold text-[#00695C]">100% your style</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SIMPLE VALUE STRIP -->
        <section class="border-b border-slate-100 bg-white">
            <div
                class="mx-auto grid max-w-7xl grid-cols-1 gap-5 px-5 py-7 sm:grid-cols-3 sm:px-8 lg:px-12"
            >
                <div class="flex items-center justify-center gap-3 sm:justify-start">
                    <span class="text-xl text-[#00695C]">✳</span>
                    <span class="text-sm font-medium text-slate-700">Beginner friendly</span>
                </div>
                <div class="flex items-center justify-center gap-3">
                    <span class="text-xl text-[#00695C]">▦</span>
                    <span class="text-sm font-medium text-slate-700">Professionally designed templates</span>
                </div>
                <div class="flex items-center justify-center gap-3 sm:justify-end">
                    <span class="text-xl text-[#00695C]">↗</span>
                    <span class="text-sm font-medium text-slate-700">Simple portfolio publishing</span>
                </div>
            </div>
        </section>

        <!-- FEATURES -->
        <section id="features" class="scroll-mt-20 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-[#00695C]">
                        WHY QUICKFOLIO
                    </p>

                    <h2 class="mt-5 text-4xl font-bold tracking-tight sm:text-5xl">
                        Everything you need.
                        <br />
                        Nothing you don't.
                    </h2>

                    <p class="mt-5 max-w-xl leading-7 text-slate-600">
                        Building your online presence shouldn't require technical
                        skills. We make the process straightforward, from the first
                        click to your published portfolio.
                    </p>
                </div>

                <div class="mt-14 grid gap-5 md:grid-cols-3">
                    <article
                        v-for="feature in features"
                        :key="feature.number"
                        class="rounded-2xl border border-slate-200 bg-white p-7 transition hover:-translate-y-1 hover:border-[#B6D6CE] hover:shadow-lg"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#DCEDE9] text-2xl text-[#00695C]">
                                {{ feature.icon }}
                            </div>
                            <span class="text-xs font-semibold tracking-widest text-slate-400">
                                {{ feature.number }}
                            </span>
                        </div>

                        <h3 class="mt-7 text-xl font-bold">
                            {{ feature.title }}
                        </h3>

                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            {{ feature.description }}
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <!-- TEMPLATES -->
        <section id="templates" class="scroll-mt-20 bg-[#F4F7F7] py-20 sm:py-28">
            <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-[0.25em] text-[#00695C]">
                            FIND YOUR STYLE
                        </p>
                        <h2 class="mt-5 text-4xl font-bold tracking-tight sm:text-5xl">
                            A portfolio that feels like you.
                        </h2>
                        <p class="mt-4 leading-7 text-slate-600">
                            Start with a layout designed around your kind of work.
                            Personalize it to tell your own story.
                        </p>
                    </div>

                    <Link
                        :href="route('templates.index')"
                        class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-[#00695C] hover:underline"
                    >
                        Browse templates <span>→</span>
                    </Link>
                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <!-- Developer -->
                    <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div class="aspect-[4/3] overflow-hidden bg-[#E5ECEA] p-5">
                            <div class="h-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                    <span class="text-[10px] font-bold">DEV.STUDIO</span>
                                    <span class="text-[9px] text-slate-500">WORK &nbsp; ABOUT</span>
                                </div>
                                <div class="grid h-full grid-cols-2 gap-2 p-4">
                                    <div class="pt-4">
                                        <div class="h-2 w-10 rounded bg-[#00695C]"></div>
                                        <div class="mt-3 h-4 w-full rounded-sm bg-slate-800"></div>
                                        <div class="mt-1 h-4 w-3/4 rounded-sm bg-slate-800"></div>
                                        <div class="mt-3 h-2 w-full rounded-sm bg-slate-200"></div>
                                        <div class="mt-1 h-2 w-4/5 rounded-sm bg-slate-200"></div>
                                        <div class="mt-4 h-5 w-16 rounded-sm bg-[#00695C]"></div>
                                    </div>
                                    <div class="mt-4 rounded bg-[#DCEDE9]"></div>
                                </div>
                            </div>
                        </div>
                        <div class="p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-[#00695C]">01 / DEVELOPER</p>
                            <h3 class="mt-2 text-lg font-bold">For the builders</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                Showcase projects, technical skills, and experience.
                            </p>
                        </div>
                    </article>

                    <!-- Designer -->
                    <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div class="aspect-[4/3] overflow-hidden bg-[#E9E5DE] p-5">
                            <div class="h-full overflow-hidden rounded-lg bg-[#FBF9F4] p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold tracking-tight">STUDIO / 01</span>
                                    <span class="text-[9px] text-slate-500">DESIGNER</span>
                                </div>
                                <div class="mt-7 text-3xl font-serif leading-tight">
                                    Design
                                    <br />
                                    with intent.
                                </div>
                                <div class="mt-4 grid grid-cols-3 gap-2">
                                    <div class="h-16 bg-[#C6D6CC]"></div>
                                    <div class="h-16 bg-[#D9B9A7]"></div>
                                    <div class="h-16 bg-[#D7D4C8]"></div>
                                </div>
                                <div class="mt-4 h-1 w-14 bg-[#00695C]"></div>
                            </div>
                        </div>
                        <div class="p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-[#00695C]">02 / CREATIVE</p>
                            <h3 class="mt-2 text-lg font-bold">For the creatives</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                Present visual projects, creative thinking, and your style.
                            </p>
                        </div>
                    </article>

                    <!-- Professional -->
                    <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div class="aspect-[4/3] overflow-hidden bg-[#E1E7EF] p-5">
                            <div class="h-full overflow-hidden rounded-lg bg-white p-5 shadow-sm">
                                <div class="h-2 w-20 rounded bg-slate-800"></div>
                                <div class="mt-5 h-3 w-2/3 rounded bg-slate-800"></div>
                                <div class="mt-2 h-2 w-1/2 rounded bg-[#00695C]"></div>
                                <div class="mt-5 h-2 w-full rounded bg-slate-200"></div>
                                <div class="mt-2 h-2 w-5/6 rounded bg-slate-200"></div>
                                <div class="mt-5 grid grid-cols-2 gap-3">
                                    <div class="rounded border border-slate-200 p-3">
                                        <div class="h-2 w-12 rounded bg-slate-700"></div>
                                        <div class="mt-3 h-8 rounded bg-[#E8F0ED]"></div>
                                    </div>
                                    <div class="rounded border border-slate-200 p-3">
                                        <div class="h-2 w-12 rounded bg-slate-700"></div>
                                        <div class="mt-3 h-8 rounded bg-[#E8F0ED]"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-[#00695C]">03 / PROFESSIONAL</p>
                            <h3 class="mt-2 text-lg font-bold">For your next opportunity</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                Organize your achievements, experience, and professional story.
                            </p>
                        </div>
                    </article>
                </div>

                <p class="mt-6 text-xs leading-5 text-slate-500">
                    Preview layouts are illustrative. Connect these cards to your
                    actual template library when it is ready.
                </p>
            </div>
        </section>

        <!-- HOW IT WORKS -->
        <section id="how-it-works" class="scroll-mt-20 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-[#00695C]">
                        SIMPLE BY DESIGN
                    </p>
                    <h2 class="mt-5 text-4xl font-bold tracking-tight sm:text-5xl">
                        From idea to online.
                    </h2>
                    <p class="mt-4 leading-7 text-slate-600">
                        Three simple steps. No coding tutorials. No complicated setup.
                    </p>
                </div>

                <div class="relative mt-16 grid gap-10 md:grid-cols-3 md:gap-8">
                    <div
                        v-for="step in steps"
                        :key="step.number"
                        class="relative"
                    >
                        <span class="text-5xl font-bold tracking-tight text-[#C8E1DA]">
                            {{ step.number }}
                        </span>
                        <h3 class="mt-5 text-xl font-bold">{{ step.title }}</h3>
                        <p class="mt-3 max-w-sm text-sm leading-7 text-slate-600">
                            {{ step.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="bg-[#F4F7F7] px-5 py-20 sm:px-8 sm:py-24 lg:px-12">
            <div class="mx-auto max-w-5xl rounded-3xl bg-[#0F2928] px-6 py-14 text-center sm:px-12 sm:py-16">
                <p class="text-xs font-bold uppercase tracking-[0.25em] text-[#9ED4C5]">
                    YOUR NEXT CHAPTER STARTS HERE
                </p>

                <h2 class="mx-auto mt-5 max-w-3xl text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    Your skills deserve more than a resume.
                </h2>

                <p class="mx-auto mt-5 max-w-xl leading-7 text-slate-300">
                    Build a home for your work. Make your first impression count.
                    Start creating your portfolio today.
                </p>

                <Link
                    :href="route('register')"
                    class="mt-8 inline-flex items-center justify-center gap-3 rounded-xl bg-white px-6 py-4 text-sm font-bold text-[#00695C] transition hover:bg-[#E7F1ED]"
                >
                    Create your free account
                    <span aria-hidden="true">→</span>
                </Link>

                <p class="mt-5 text-xs text-slate-400">
                    No coding required. Just bring your ideas.
                </p>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="border-t border-slate-100 bg-white">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-12"
            >
                <Link href="/" class="flex w-fit items-center">
                    <AppLogo size="sm" />
                </Link>

                <p class="text-sm text-slate-500">
                    Make your work visible. Make your story yours.
                </p>

                <div class="flex flex-wrap gap-5 text-sm text-slate-500">
                    <a href="#features" class="hover:text-[#00695C]">Features</a>
                    <a href="#templates" class="hover:text-[#00695C]">Templates</a>
                    <Link :href="route('login')" class="hover:text-[#00695C]">Log in</Link>
                </div>
            </div>

            <div class="border-t border-slate-100 px-5 py-5 text-center text-xs text-slate-400">
                © {{ new Date().getFullYear() }} quickFolio. All rights reserved.
            </div>
        </footer>
    </main>
</template>

<style scoped>
@keyframes hero-float {
    0%, 100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-12px);
    }
}

.hero-mockup {
    animation: hero-float 5s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .hero-mockup {
        animation: none;
    }
}
</style>
