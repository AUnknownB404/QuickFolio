<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';
import { AlertCircle, ArrowRight, CheckCircle2, Eye, EyeOff, Lock, LoaderCircle, Mail } from 'lucide-vue-next';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
    // Lets the backend hide the Google button when OAuth isn't configured (defaults to the old behaviour).
    googleEnabled: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const capsLock = ref(false);
const emailInput = ref<HTMLInputElement | null>(null);
const passwordInput = ref<HTMLInputElement | null>(null);

// Start typing immediately on desktop; never pop the keyboard unprompted on touch devices.
onMounted(() => {
    if (!window.matchMedia('(pointer: coarse)').matches) emailInput.value?.focus();
});

const submit = () => {
    form.post(route('login'), {
        onError: () => {
            // The email is kept and the password cleared, so put the cursor where retyping starts.
            nextTick(() => (form.email ? passwordInput.value : emailInput.value)?.focus());
        },
        onFinish: () => form.reset('password'),
    });
};

const trackCapsLock = (event: KeyboardEvent) => {
    capsLock.value = event.getModifierState?.('CapsLock') ?? false;
};

const inputClass = (hasError: boolean) => [
    'h-12 w-full rounded-xl border bg-white text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-4',
    hasError
        ? 'border-red-500 focus:border-red-500 focus:ring-red-100'
        : 'border-slate-300 hover:border-slate-400 focus:border-[#00695C] focus:ring-[#00695C]/10',
];
</script>

<template>
    <Head title="Log in — quickFolio" />

    <main class="min-h-screen bg-white font-sans text-slate-900">
        <div class="grid min-h-screen lg:grid-cols-2">
            <!-- Brand panel: decorative reassurance, not a second sales pitch -->
            <aside class="relative hidden flex-col overflow-hidden bg-[#F4F7F7] px-12 py-12 lg:flex xl:px-16" aria-hidden="true">
                <Link href="/" class="w-fit" tabindex="-1">
                    <AppLogo size="lg" />
                </Link>

                <div class="mt-16 max-w-md">
                    <p class="text-5xl font-bold leading-[1.1] tracking-tight text-[#0F172A] xl:text-6xl">
                        Pick up where you
                        <span class="text-[#00695C]">left off.</span>
                    </p>
                    <p class="mt-5 text-lg leading-8 text-slate-600">Your portfolios, drafts and published pages are waiting for you.</p>
                </div>

                <div class="mt-10 flex min-h-0 flex-1 items-end justify-end">
                    <img
                        src="/Mockup.png"
                        alt=""
                        class="portfolio-float max-h-full w-full max-w-[520px] object-contain object-bottom drop-shadow-2xl"
                    />
                </div>
            </aside>

            <!-- Form -->
            <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10 lg:px-12">
                <div class="w-full max-w-md">
                    <Link href="/" class="mb-10 flex justify-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 lg:hidden" aria-label="quickFolio home">
                        <AppLogo size="lg" />
                    </Link>

                    <div class="mb-8">
                        <h1 class="text-3xl font-bold tracking-tight text-[#0F172A]">Welcome back</h1>
                        <p class="mt-2 leading-6 text-slate-500">Log in to continue building your portfolio.</p>
                    </div>

                    <!-- e.g. "Your password has been reset." -->
                    <div
                        v-if="status"
                        class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                        role="status"
                    >
                        <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ status }}
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <!-- Email -->
                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email address</label>
                            <div class="relative">
                                <Mail class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    id="email"
                                    ref="emailInput"
                                    v-model="form.email"
                                    type="email"
                                    name="email"
                                    autocomplete="username"
                                    inputmode="email"
                                    autocapitalize="none"
                                    :spellcheck="false"
                                    required
                                    placeholder="you@example.com"
                                    :class="[...inputClass(Boolean(form.errors.email)), 'pl-12 pr-4']"
                                    :aria-invalid="form.errors.email ? 'true' : undefined"
                                    :aria-describedby="form.errors.email ? 'email-error' : undefined"
                                />
                            </div>
                            <p v-if="form.errors.email" id="email-error" class="mt-2 flex items-start gap-2 text-sm text-red-600" role="alert">
                                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <!-- Password -->
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                                <Link
                                    v-if="props.canResetPassword"
                                    :href="route('password.request')"
                                    class="rounded text-sm font-medium text-[#00695C] transition hover:text-[#004D40] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                                >
                                    Forgot password?
                                </Link>
                            </div>
                            <div class="relative">
                                <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    id="password"
                                    ref="passwordInput"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Enter your password"
                                    :class="[...inputClass(Boolean(form.errors.password)), 'pl-12 pr-14']"
                                    :aria-invalid="form.errors.password ? 'true' : undefined"
                                    :aria-describedby="[form.errors.password ? 'password-error' : '', capsLock ? 'caps-hint' : ''].filter(Boolean).join(' ') || undefined"
                                    @keydown="trackCapsLock"
                                    @keyup="trackCapsLock"
                                    @blur="capsLock = false"
                                />
                                <button
                                    type="button"
                                    class="absolute right-1.5 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showPassword"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="h-5 w-5" aria-hidden="true" />
                                    <Eye v-else class="h-5 w-5" aria-hidden="true" />
                                </button>
                            </div>
                            <p v-if="capsLock" id="caps-hint" class="mt-2 flex items-center gap-2 text-sm text-[#9A6700]">
                                <AlertCircle class="h-4 w-4 shrink-0" aria-hidden="true" />
                                Caps Lock is on.
                            </p>
                            <p v-if="form.errors.password" id="password-error" class="mt-2 flex items-start gap-2 text-sm text-red-600" role="alert">
                                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <label class="flex min-h-10 w-fit cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                name="remember"
                                class="h-4 w-4 rounded border-slate-300 accent-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                            />
                            Keep me logged in
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            :aria-busy="form.processing"
                            class="flex h-12 w-full items-center justify-center gap-2.5 rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574B] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#00695C]/25 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
                            {{ form.processing ? 'Logging in…' : 'Log in' }}
                            <ArrowRight v-if="!form.processing" class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </form>

                    <template v-if="googleEnabled">
                        <div class="my-7 flex items-center gap-4" aria-hidden="true">
                            <div class="h-px flex-1 bg-slate-200"></div>
                            <span class="text-xs font-medium uppercase tracking-wider text-slate-400">or</span>
                            <div class="h-px flex-1 bg-slate-200"></div>
                        </div>

                        <a
                            href="/auth/google/redirect"
                            class="flex h-12 w-full items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white px-5 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                        >
                            <svg viewBox="0 0 48 48" class="h-5 w-5" aria-hidden="true">
                                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z" transform="translate(0 4)" />
                                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.76 7.18l7.73 6C44.42 38.05 46.98 31.94 46.98 24.55Z" />
                                <path fill="#FBBC05" d="M10.53 28.59a14.4 14.4 0 010-9.18l-7.98-6.19a24 24 0 000 21.56l7.98-6.19Z" />
                                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.15 1.45-4.89 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48Z" />
                            </svg>
                            Continue with Google
                        </a>
                    </template>

                    <p v-if="canRegister" class="mt-8 text-center text-sm text-slate-500">
                        New to quickFolio?
                        <Link :href="route('register')" class="ml-1 rounded font-semibold text-[#00695C] hover:text-[#004D40] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40">
                            Create an account
                        </Link>
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    .portfolio-float {
        animation: none !important;
    }
}
</style>
