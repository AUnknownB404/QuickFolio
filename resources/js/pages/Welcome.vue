<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';

const page = usePage();

const features = [
    { title: 'No coding required', description: 'Build your portfolio without writing a single line of code.', icon: '✳' },
    { title: 'Templates ready to use', description: 'Choose a design that fits your style and personalize it.', icon: '▦' },
    { title: 'Easy to share', description: 'Publish your portfolio and share it with one simple link.', icon: '↗' },
];

const steps = [
    { number: '01', title: 'Browse templates', description: 'Find a design you like.' },
    { number: '02', title: 'Make it yours', description: 'Add your skills, projects, and experience.' },
    { number: '03', title: 'Publish and share', description: 'Put your work online.' },
];
</script>

<template>
    <Head title="quickFolio — Build Your Portfolio" />

    <main class="min-h-screen bg-white font-sans text-[#0F172A]">
        <header class="border-b border-slate-100 bg-white">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8" aria-label="Main navigation">
                <Link href="/" class="flex items-center" aria-label="quickFolio home">
                    <AppLogo size="lg" />
                </Link>
                <div v-if="!page.props.auth.user" class="flex items-center gap-2">
                    <Link :href="route('register')" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-[#00695C]">
                        Sign up
                    </Link>
                    <Link :href="route('login')" class="bg-[#00695C] rounded-lg px-3 py-2 text-sm font-medium text-white transition hover:bg-[#00574B]">
                        Log in
                    </Link>
                </div>
                <div v-else class="flex items-center gap-2">
                    <Link :href="route('dashboard')" class="bg-[#00695C] rounded-lg px-3 py-2 text-sm font-medium text-white transition hover:bg-[#00574B]">
                        Dashboard
                    </Link>
                </div>
            </nav>
        </header>

        <section class="overflow-hidden bg-[#F4F7F7]">
            <div class="mx-auto grid max-w-7xl items-center gap-8 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-2 lg:gap-12 lg:py-24">
                <div class="max-w-xl">
                    <p class="inline-flex items-center gap-2 rounded-full border border-[#C8E2DB] bg-white px-3 py-1.5 text-sm font-semibold text-[#00695C]">
                        <span class="h-2 w-2 rounded-full bg-[#00695C]"></span>
                        Your work deserves to be seen
                    </p>
                    <h1 class="mt-5 text-4xl font-bold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                        Your portfolio,
                        <span class="text-[#00695C]">made simple.</span>
                    </h1>
                    <p class="mt-5 max-w-lg text-base leading-7 text-slate-600 sm:text-lg">
                        Choose a design, add your work, and publish your personal portfolio.
                        No coding or complicated setup required.
                    </p>
                    <div class="mt-8">
                        <Link :href="route('templates.index')" class="inline-flex min-h-12 w-full items-center justify-center gap-3 rounded-xl bg-[#00695C] px-6 py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-[#00574B] focus:outline-none focus:ring-4 focus:ring-[#00695C]/20 sm:w-auto">
                            Browse Templates <span aria-hidden="true" class="text-xl">→</span>
                        </Link>
                        <p class="mt-3 text-sm text-slate-500">Explore designs and find the right fit for you.</p>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600">
                        <span class="inline-flex items-center gap-2"><span class="font-bold text-[#00695C]" aria-hidden="true">✓</span>No coding needed</span>
                        <span class="inline-flex items-center gap-2"><span class="font-bold text-[#00695C]" aria-hidden="true">✓</span>Easy to customize</span>
                    </div>
                </div>
                <div class="flex justify-center lg:justify-end">
                    <img src="/Mockup.png" alt="A portfolio website displayed on a laptop and phone" class="h-auto w-full max-w-xl object-contain" />
                </div>
            </div>
        </section>

        <section id="features" class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Everything you need. Nothing complicated.</h2>
                <p class="mt-3 leading-7 text-slate-600">Simple tools to help you present your work with confidence.</p>
            </div>
            <div class="mt-10 grid gap-5 md:grid-cols-3">
                <article v-for="feature in features" :key="feature.title" class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:border-[#B5D7CE] hover:shadow-sm">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#E2F0EC] text-xl text-[#00695C]">{{ feature.icon }}</div>
                    <h3 class="mt-4 text-lg font-semibold">{{ feature.title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ feature.description }}</p>
                </article>
            </div>
        </section>

        <section id="how-it-works" class="bg-[#F4F7F7]">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Three simple steps</h2>
                    <p class="mt-3 leading-7 text-slate-600">Go from choosing a template to sharing your portfolio.</p>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-3">
                    <article v-for="step in steps" :key="step.number" class="rounded-2xl border border-slate-200 bg-white p-6">
                        <span class="text-sm font-bold tracking-widest text-[#00695C]">STEP {{ step.number }}</span>
                        <h3 class="mt-3 text-lg font-semibold">{{ step.title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ step.description }}</p>
                    </article>
                </div>
            </div>
        </section>

        <footer class="border-t border-slate-100">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-6 sm:px-8 sm:flex-row sm:items-center sm:justify-between">
                <Link href="/" class="flex w-fit items-center" aria-label="quickFolio home"><AppLogo size="sm" /></Link>
                <p class="text-sm text-slate-500">© {{ new Date().getFullYear() }} quickFolio. All rights reserved.</p>
            </div>
        </footer>
    </main>
</template>
