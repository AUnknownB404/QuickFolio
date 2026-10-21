
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
})

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation')
        },
    })
}
</script>

<template>
    <Head title="Create Account" />

    <main class="min-h-screen bg-white font-sans text-slate-900">
        <div class="grid min-h-screen lg:grid-cols-2">

            <!-- LEFT BRAND PANEL -->
            <section
                class="relative hidden flex-col overflow-hidden bg-[#F4F7F7] px-12 py-12 lg:flex xl:px-16"
            >
                <Link href="/" class="flex w-fit items-center gap-3">
                    <img src="/logo.svg" alt="" class="h-12 w-12 shrink-0 object-contain" />
                    <span class="text-3xl font-bold tracking-tight text-[#0F172A]">
                        quick<span class="text-[#00695C]">Folio</span>
                    </span>
                </Link>

                <p class="mt-12 text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">
                    Create <span class="mx-2">•</span>
                    Customize <span class="mx-2">•</span>
                    Launch
                </p>

                <h1 class="mt-7 max-w-xl text-5xl font-bold leading-[1.15] tracking-tight text-[#0F172A] xl:text-6xl">
                    Your next chapter
                    <br />
                    starts here.
                </h1>

                <p class="mt-6 max-w-md text-lg leading-8 text-slate-600">
                    Turn your skills, ideas, and achievements into a
                    portfolio that opens new opportunities.
                </p>

                <!-- Benefits -->
                <div class="mt-10 space-y-7">
                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M3 12h18"/>
                                <circle cx="12" cy="12" r="9"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold">Start for free</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                Create your account and start building your online presence.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path stroke-linecap="round" d="M3 9h18M9 21V9"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold">Make it your own</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                Choose a template and personalize it without writing code.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m-5 5 5-5 5 5"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 14v5a1 1 0 001 1h14a1 1 0 001-1v-5"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold">Share your talent</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                Publish your portfolio and share it with the world.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Decorative preview -->
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

            <!-- RIGHT REGISTRATION FORM -->
            <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10 lg:px-12">
                <div class="w-full max-w-md">

                    <!-- Mobile brand -->
                    <Link href="/" class="mb-8 flex items-center justify-center gap-2 lg:hidden">
                        <img src="/logo.svg" alt="" class="h-10 w-10 shrink-0 object-contain" />
                        <span class="text-2xl font-bold tracking-tight">
                            quick<span class="text-[#00695C]">Folio</span>
                        </span>
                    </Link>

                    <!-- Heading -->
                    <div class="mb-7">
                        <div class="mb-7 hidden items-center gap-2 lg:flex">
                            <img src="/logo.svg" alt="" class="h-10 w-10 shrink-0 object-contain" />
                            <span class="text-2xl font-bold tracking-tight">
                                quick<span class="text-[#00695C]">Folio</span>
                            </span>
                        </div>

                        <h2 class="text-3xl font-bold tracking-tight text-[#0F172A]">
                            Create your account
                        </h2>
                        <p class="mt-3 leading-6 text-slate-500">
                            Join quickFolio and build a portfolio that stands out.
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">

                        <!-- Full name -->
                        <div>
                            <label for="name" class="mb-2 block text-sm font-medium text-slate-700">
                                Full name
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                name="name"
                                autocomplete="name"
                                required
                                autofocus
                                placeholder="e.g. Alex Carter"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-[#00695C] focus:ring-4 focus:ring-[#00695C]/10"
                                :class="{ 'border-red-500': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-700">
                                Email address
                            </label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                name="email"
                                autocomplete="email"
                                required
                                placeholder="you@example.com"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-[#00695C] focus:ring-4 focus:ring-[#00695C]/10"
                                :class="{ 'border-red-500': form.errors.email }"
                            />
                            <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700">
                                Password
                            </label>
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Create a strong password"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 pr-12 text-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-[#00695C] focus:ring-4 focus:ring-[#00695C]/10"
                                    :class="{ 'border-red-500': form.errors.password }"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-medium text-[#00695C] hover:text-[#004D40]"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                >
                                    {{ showPassword ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">
                                Use a password that is difficult for others to guess.
                            </p>
                            <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Confirm password -->
                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">
                                Confirm password
                            </label>
                            <div class="relative">
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    name="password_confirmation"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Enter your password again"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 pr-12 text-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-[#00695C] focus:ring-4 focus:ring-[#00695C]/10"
                                    :class="{ 'border-red-500': form.errors.password_confirmation }"
                                />
                                <button
                                    type="button"
                                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-medium text-[#00695C] hover:text-[#004D40]"
                                    :aria-label="showPasswordConfirmation ? 'Hide password' : 'Show password'"
                                >
                                    {{ showPasswordConfirmation ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                            <p v-if="form.errors.password_confirmation" class="mt-1.5 text-sm text-red-600">
                                {{ form.errors.password_confirmation }}
                            </p>
                        </div>

                        <!-- Terms -->
                        <div>
                            <label class="flex cursor-pointer items-start gap-3 text-sm leading-5 text-slate-600">
                                <input
                                    v-model="form.terms"
                                    type="checkbox"
                                    name="terms"
                                    required
                                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 accent-[#00695C] focus:ring-[#00695C]"
                                />
                                <span>
                                    I agree to the
                                    <Link href="/terms" class="font-medium text-[#00695C] hover:underline">
                                        Terms of Service
                                    </Link>
                                    and
                                    <Link href="/privacy" class="font-medium text-[#00695C] hover:underline">
                                        Privacy Policy
                                    </Link>.
                                </span>
                            </label>
                        </div>

                        <!-- Server error -->
                        <div
                            v-if="form.errors.terms"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors.terms }}
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center gap-3 rounded-xl bg-[#00695C] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#00574B] focus:outline-none focus:ring-4 focus:ring-[#00695C]/20 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? 'Creating account...' : 'Create account' }}
                            <svg v-if="!form.processing" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-7-7 7 7-7 7"/>
                            </svg>
                        </button>
                    </form>

                    <!-- Login link -->
                    <p class="mt-7 text-center text-sm text-slate-500">
                        Already have an account?
                        <Link
                            :href="route('login')"
                            class="ml-1 font-semibold text-[#00695C] hover:text-[#004D40] hover:underline"
                        >
                            Log in
                        </Link>
                    </p>

                    <p class="mt-8 text-center text-xs text-slate-400">
                        © {{ new Date().getFullYear() }} quickFolio. All rights reserved.
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>
