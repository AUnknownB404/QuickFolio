<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
        default: null,
    },
});

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <main class="min-h-screen bg-white font-sans text-slate-900">
        <div class="grid min-h-screen lg:grid-cols-2">
            <!-- LEFT: BRAND PANEL -->
            <section class="relative hidden overflow-hidden z-10 bg-[#F4F7F7] px-12 py-12 lg:flex lg:flex-col xl:px-16">
                <!-- Brand -->
                <Link href="/" class="flex w-fit items-center gap-3">
                    <img src="/logo.svg" alt="" class="h-12 w-12 shrink-0 object-contain" />

                    <span class="text-3xl font-bold tracking-tight text-[#0F172A]"> quick<span class="text-[#00695C]">Folio</span> </span>
                </Link>

                <p class="mt-12 text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">
                    Build <span class="mx-2">•</span> Customize <span class="mx-2">•</span>
                    Publish
                </p>

                <h1 class="mt-7 max-w-xl text-5xl font-bold leading-[1.15] tracking-tight text-[#0F172A] xl:text-6xl">
                    Your portfolio.
                    <br />
                    Built in minutes.
                </h1>

                <p class="mt-6 max-w-md text-lg leading-8 text-slate-600">
                    Create a professional portfolio website without writing a single line of code. Your work deserves to be seen.
                </p>

                <!-- Features -->
                <div class="mt-10 space-y-7">
                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L8 18l-4 1 1-4Z" />
                            </svg>
                        </div>

                        <div>
                            <h3 class="font-semibold">Easy to use</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">No coding skills required. Just customize and create.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <path stroke-linecap="round" d="M3 9h18M9 21V9" />
                            </svg>
                        </div>

                        <div>
                            <h3 class="font-semibold">Beautiful templates</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Find a design that fits your personality and profession.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 16.5 3 21l5-2 10-10a3.5 3.5 0 00-5-5Z" />
                                <path stroke-linecap="round" d="m13 6 5 5" />
                            </svg>
                        </div>

                        <div>
                            <h3 class="font-semibold">Publish with confidence</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Share your work with the world in just a few clicks.</p>
                        </div>
                    </div>
                </div>

                <!-- Decorative portfolio preview -->
<div
    class="pointer-events-none absolute top-64  right-0 z-0 w-[58%] max-w-[560px] translate-x-[8%] lg:bottom-12 xl:bottom-8"
>
    <img
        src="/Mockup.png"
        alt="Laptop and phone displaying a portfolio beside a potted plant"
        class="portfolio-float h-auto w-full object-contain drop-shadow-2xl"
    />
</div>

            </section>

            <!-- RIGHT: LOGIN FORM -->
            <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10 lg:px-12">
                <div class="w-full max-w-md">
                    <!-- Mobile brand -->
                    <Link href="/" class="mb-10 flex items-center justify-center gap-2 lg:hidden">
                        <img src="/logo.svg" alt="" class="h-10 w-10 shrink-0 object-contain" />
                        <span class="text-2xl font-bold tracking-tight"> quick<span class="text-[#00695C]">Folio</span> </span>
                    </Link>

                    <div class="mb-8">
                        <div class="mb-7 hidden items-center gap-2 lg:flex">
                            <img src="/logo.svg" alt="" class="h-10 w-10 shrink-0 object-contain" />
                            <span class="text-2xl font-bold tracking-tight"> quick<span class="text-[#00695C]">Folio</span> </span>
                        </div>

                        <h2 class="text-3xl font-bold tracking-tight text-[#0F172A]">Welcome back</h2>
                        <p class="mt-3 leading-6 text-slate-500">Log in to your account and continue building your professional portfolio.</p>
                    </div>

                    <!-- Status message -->
                    <div v-if="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- Email -->
                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-700"> Email address </label>

                            <div class="relative">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                                >
                                    <rect x="3" y="5" width="18" height="14" rx="2" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6" />
                                </svg>

                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    name="email"
                                    autocomplete="username"
                                    required
                                    autofocus
                                    placeholder="you@example.com"
                                    class="w-full rounded-xl border border-slate-300 bg-white py-3.5 pl-12 pr-4 text-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-[#00695C] focus:ring-4 focus:ring-[#00695C]/10"
                                    :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-100': form.errors.email }"
                                />
                            </div>

                            <p v-if="form.errors.email" class="mt-2 text-sm text-red-600">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700"> Password </label>

                            <div class="relative">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                                >
                                    <rect x="5" y="10" width="14" height="11" rx="2" />
                                    <path stroke-linecap="round" d="M8 10V7a4 4 0 018 0v3" />
                                </svg>

                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Enter your password"
                                    class="w-full rounded-xl border border-slate-300 bg-white py-3.5 pl-12 pr-12 text-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-[#00695C] focus:ring-4 focus:ring-[#00695C]/10"
                                    :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-100': form.errors.password }"
                                />

                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-[#00695C]"
                                >
                                    <svg
                                        v-if="!showPassword"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        class="h-5 w-5"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.2A10.5 10.5 0 0112 5c6.5 0 10 7 10 7a15 15 0 01-3 3.8M6.2 6.2C3.5 8 2 12 2 12s3.5 7 10 7c1.3 0 2.5-.3 3.5-.7"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <p v-if="form.errors.password" class="mt-2 text-sm text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Remember + Forgot password -->
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    name="remember"
                                    class="h-4 w-4 rounded border-slate-300 accent-[#00695C] focus:ring-[#00695C]"
                                />
                                Remember me
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm font-medium text-[#00695C] transition hover:text-[#004D40] hover:underline"
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center gap-3 rounded-xl bg-[#00695C] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#00574B] focus:outline-none focus:ring-4 focus:ring-[#00695C]/20 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span>{{ form.processing ? 'Logging in...' : 'Log in' }}</span>

                            <svg v-if="!form.processing" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-7-7 7 7-7 7" />
                            </svg>
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="my-7 flex items-center gap-4">
                        <div class="h-px flex-1 bg-slate-200"></div>
                        <span class="text-xs font-medium uppercase tracking-wider text-slate-400"> Or continue with </span>
                        <div class="h-px flex-1 bg-slate-200"></div>
                    </div>

                    <!-- Google sign in: requires OAuth setup -->
                    <a
                        href="/auth/google/redirect"
                        class="flex w-full items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white px-5 py-3.5 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                    >
                        <svg viewBox="0 0 48 48" class="h-5 w-5">
                            <path
                                fill="#EA4335"
                                d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z"
                                transform="translate(0 4)"
                            />
                            <path
                                fill="#4285F4"
                                d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.76 7.18l7.73 6C44.42 38.05 46.98 31.94 46.98 24.55Z"
                            />
                            <path
                                fill="#FBBC05"
                                d="M10.53 28.59a14.4 14.4 0 010-9.18l-7.98-6.19a24 24 0 000 21.56l7.98-6.19Z"
                                transform="translate(0 0)"
                            />
                            <path
                                fill="#34A853"
                                d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.15 1.45-4.89 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48Z"
                            />
                        </svg>
                        Continue with Google
                    </a>

                    <!-- Register -->
                    <p v-if="canRegister" class="mt-8 text-center text-sm text-slate-500">
                        Don't have an account?
                        <Link :href="route('register')" class="ml-1 font-semibold text-[#00695C] hover:text-[#004D40] hover:underline">
                            Sign up
                        </Link>
                    </p>

                    <p class="mt-10 text-center text-xs text-slate-400">© {{ new Date().getFullYear() }} quickFolio. All rights reserved.</p>
                </div>
            </section>
        </div>
    </main>
</template>
