<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import { AlertCircle, ArrowRight, Check, Eye, EyeOff, LoaderCircle, Lock, Mail, User } from 'lucide-vue-next';

defineProps({
    // Opt-in: show "Continue with Google" here too, matching the login page.
    googleEnabled: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const showPasswords = ref(false);
const capsLock = ref(false);
const confirmTouched = ref(false);
const nameInput = ref<HTMLInputElement | null>(null);

// Start typing immediately on desktop; never pop the keyboard unprompted on touch devices.
onMounted(() => {
    if (!window.matchMedia('(pointer: coarse)').matches) nameInput.value?.focus();
});

const fieldOrder = ['name', 'email', 'password', 'password_confirmation', 'terms'] as const;

const submit = () => {
    form.post(route('register'), {
        onError: (errors) => {
            // Take the user to the first thing that needs fixing.
            const first = fieldOrder.find((key) => errors[key]);
            nextTick(() => first && document.getElementById(first)?.focus());
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const trackCapsLock = (event: KeyboardEvent) => {
    capsLock.value = event.getModifierState?.('CapsLock') ?? false;
};

/* -------------------------------------------------------------------------
 * Password guidance — shown while typing, instead of a rejection after submit
 * ---------------------------------------------------------------------- */

const strength = computed(() => {
    const password = form.password;
    if (!password) return { level: 0, label: '' };
    if (password.length < 8) return { level: 1, label: 'Too short' };

    const variety = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter((pattern) => pattern.test(password)).length;
    const score = variety + (password.length >= 12 ? 1 : 0) + (password.length >= 16 ? 1 : 0);
    if (score <= 2) return { level: 2, label: 'Fair' };
    if (score <= 3) return { level: 3, label: 'Good' };
    return { level: 4, label: 'Strong' };
});

const strengthColors = ['bg-slate-200', 'bg-red-500', 'bg-amber-500', 'bg-[#2E9E8F]', 'bg-[#00695C]'];

const passwordsMatch = computed(() => form.password_confirmation !== '' && form.password === form.password_confirmation);
const passwordsDiffer = computed(() => confirmTouched.value && form.password_confirmation !== '' && !passwordsMatch.value);

const emailTaken = computed(() => /taken|already/i.test(form.errors.email ?? ''));

const inputClass = (hasError: boolean) => [
    'h-12 w-full rounded-xl border bg-white text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-4',
    hasError
        ? 'border-red-500 focus:border-red-500 focus:ring-red-100'
        : 'border-slate-300 hover:border-slate-400 focus:border-[#00695C] focus:ring-[#00695C]/10',
];

const describedBy = (...ids: (string | false | undefined)[]) => ids.filter(Boolean).join(' ') || undefined;

const nextSteps = [
    { title: 'Choose a template', description: 'Browse designs made for your field.' },
    { title: 'Make it yours', description: 'Edit right on the page and preview it on mobile.' },
    { title: 'Publish and share', description: 'Go live with one link, or download a ZIP.' },
];
</script>

<template>
    <Head title="Create account — quickFolio" />

    <main class="min-h-screen bg-white font-sans text-slate-900">
        <div class="grid min-h-screen lg:grid-cols-2">
            <!-- Brand panel: tells people what happens after they sign up -->
            <aside class="relative hidden flex-col overflow-hidden bg-[#F4F7F7] px-12 py-12 lg:flex xl:px-16" aria-label="What happens next">
                <Link href="/" class="w-fit rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40" aria-label="quickFolio home">
                    <AppLogo size="lg" />
                </Link>

                <div class="mt-14 max-w-md">
                    <p class="text-5xl font-bold leading-[1.1] tracking-tight text-[#0F172A] xl:text-6xl">
                        Your next chapter
                        <span class="text-[#00695C]">starts here.</span>
                    </p>
                    <p class="mt-5 text-lg leading-8 text-slate-600">Three steps from sign-up to a portfolio you can share.</p>

                    <ol class="mt-8 space-y-5">
                        <li v-for="(step, index) in nextSteps" :key="step.title" class="flex gap-4">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#DCEDE9] text-sm font-bold text-[#00695C]" aria-hidden="true">{{ index + 1 }}</span>
                            <div>
                                <p class="font-semibold"><span class="sr-only">Step {{ index + 1 }}: </span>{{ step.title }}</p>
                                <p class="mt-0.5 text-sm leading-6 text-slate-500">{{ step.description }}</p>
                            </div>
                        </li>
                    </ol>
                </div>

                <div class="mt-8 flex min-h-0 flex-1 items-end justify-end" aria-hidden="true">
                    <img src="/Mockup.png" alt="" class="portfolio-float max-h-full w-full max-w-[460px] object-contain object-bottom drop-shadow-2xl" />
                </div>
            </aside>

            <!-- Form -->
            <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10 lg:px-12">
                <div class="w-full max-w-md">
                    <Link href="/" class="mb-8 flex justify-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 lg:hidden" aria-label="quickFolio home">
                        <AppLogo size="lg" />
                    </Link>

                    <div class="mb-7">
                        <h1 class="text-3xl font-bold tracking-tight text-[#0F172A]">Create your account</h1>
                        <p class="mt-2 leading-6 text-slate-500">Join quickFolio and build a portfolio that stands out.</p>
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                        <!-- Name -->
                        <div>
                            <label for="name" class="mb-2 block text-sm font-medium text-slate-700">Full name</label>
                            <div class="relative">
                                <User class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    id="name"
                                    ref="nameInput"
                                    v-model="form.name"
                                    type="text"
                                    name="name"
                                    autocomplete="name"
                                    autocapitalize="words"
                                    required
                                    placeholder="e.g. Alex Carter"
                                    :class="[...inputClass(Boolean(form.errors.name)), 'pl-12 pr-4']"
                                    :aria-invalid="form.errors.name ? 'true' : undefined"
                                    :aria-describedby="describedBy(form.errors.name && 'name-error')"
                                />
                            </div>
                            <p v-if="form.errors.name" id="name-error" class="mt-2 flex items-start gap-2 text-sm text-red-600" role="alert">
                                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email address</label>
                            <div class="relative">
                                <Mail class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    name="email"
                                    autocomplete="email"
                                    inputmode="email"
                                    autocapitalize="none"
                                    :spellcheck="false"
                                    required
                                    placeholder="you@example.com"
                                    :class="[...inputClass(Boolean(form.errors.email)), 'pl-12 pr-4']"
                                    :aria-invalid="form.errors.email ? 'true' : undefined"
                                    :aria-describedby="describedBy(form.errors.email && 'email-error')"
                                />
                            </div>
                            <p v-if="form.errors.email" id="email-error" class="mt-2 text-sm text-red-600" role="alert">
                                <span class="flex items-start gap-2">
                                    <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                    {{ form.errors.email }}
                                </span>
                                <!-- A dead end becomes a way forward -->
                                <span v-if="emailTaken" class="mt-1 block pl-6 text-slate-600">
                                    Already registered?
                                    <Link :href="route('login')" class="font-semibold text-[#00695C] hover:underline">Log in instead</Link>
                                </span>
                            </p>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password</label>
                            <div class="relative">
                                <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPasswords ? 'text' : 'password'"
                                    name="password"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Create a password"
                                    :class="[...inputClass(Boolean(form.errors.password)), 'pl-12 pr-14']"
                                    :aria-invalid="form.errors.password ? 'true' : undefined"
                                    :aria-describedby="describedBy('password-hint', form.errors.password && 'password-error', capsLock && 'caps-hint')"
                                    @keydown="trackCapsLock"
                                    @keyup="trackCapsLock"
                                    @blur="capsLock = false"
                                />
                                <button
                                    type="button"
                                    class="absolute right-1.5 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                                    :aria-label="showPasswords ? 'Hide passwords' : 'Show passwords'"
                                    :aria-pressed="showPasswords"
                                    @click="showPasswords = !showPasswords"
                                >
                                    <EyeOff v-if="showPasswords" class="h-5 w-5" aria-hidden="true" />
                                    <Eye v-else class="h-5 w-5" aria-hidden="true" />
                                </button>
                            </div>

                            <!-- Strength: a guide that updates as you type -->
                            <div v-if="form.password" class="mt-2.5 flex items-center gap-3" aria-hidden="true">
                                <div class="flex flex-1 gap-1">
                                    <span
                                        v-for="segment in 4"
                                        :key="segment"
                                        class="h-1.5 flex-1 rounded-full transition-colors duration-200 motion-reduce:transition-none"
                                        :class="segment <= strength.level ? strengthColors[strength.level] : 'bg-slate-200'"
                                    />
                                </div>
                                <span class="w-16 text-right text-xs font-medium text-slate-600">{{ strength.label }}</span>
                            </div>
                            <p class="sr-only" aria-live="polite">{{ strength.label ? `Password strength: ${strength.label}` : '' }}</p>

                            <p id="password-hint" class="mt-2 text-xs leading-5 text-slate-500">
                                At least 8 characters. A few unrelated words make a strong, memorable password.
                            </p>
                            <p v-if="capsLock" id="caps-hint" class="mt-1.5 flex items-center gap-2 text-sm text-[#9A6700]">
                                <AlertCircle class="h-4 w-4 shrink-0" aria-hidden="true" />
                                Caps Lock is on.
                            </p>
                            <p v-if="form.errors.password" id="password-error" class="mt-1.5 flex items-start gap-2 text-sm text-red-600" role="alert">
                                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Confirm password -->
                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">Confirm password</label>
                            <div class="relative">
                                <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    :type="showPasswords ? 'text' : 'password'"
                                    name="password_confirmation"
                                    autocomplete="new-password"
                                    required
                                    placeholder="Enter it again"
                                    :class="[...inputClass(Boolean(form.errors.password_confirmation) || passwordsDiffer), 'pl-12 pr-4']"
                                    :aria-invalid="form.errors.password_confirmation || passwordsDiffer ? 'true' : undefined"
                                    :aria-describedby="describedBy('confirm-status', form.errors.password_confirmation && 'confirm-error')"
                                    @blur="confirmTouched = true"
                                />
                            </div>
                            <!-- Immediate, not after submit -->
                            <p id="confirm-status" class="mt-1.5 min-h-5 text-sm" aria-live="polite">
                                <span v-if="passwordsMatch" class="flex items-center gap-2 text-[#16734A]">
                                    <Check class="h-4 w-4 shrink-0" aria-hidden="true" />
                                    Passwords match
                                </span>
                                <span v-else-if="passwordsDiffer" class="flex items-center gap-2 text-red-600">
                                    <AlertCircle class="h-4 w-4 shrink-0" aria-hidden="true" />
                                    These don’t match yet
                                </span>
                            </p>
                            <p v-if="form.errors.password_confirmation" id="confirm-error" class="flex items-start gap-2 text-sm text-red-600" role="alert">
                                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                {{ form.errors.password_confirmation }}
                            </p>
                        </div>

                        <!-- Terms: opens in a new tab so nothing typed above is lost -->
                        <div>
                            <label class="flex min-h-10 cursor-pointer items-start gap-3 text-sm leading-5 text-slate-600">
                                <input
                                    id="terms"
                                    v-model="form.terms"
                                    type="checkbox"
                                    name="terms"
                                    required
                                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 accent-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                                    :aria-invalid="form.errors.terms ? 'true' : undefined"
                                    :aria-describedby="describedBy(form.errors.terms && 'terms-error')"
                                />
                                <span>
                                    I agree to the
                                    <a href="/terms" target="_blank" rel="noopener noreferrer" class="font-medium text-[#00695C] hover:underline">Terms of Service<span class="sr-only"> (opens in a new tab)</span></a>
                                    and
                                    <a href="/privacy" target="_blank" rel="noopener noreferrer" class="font-medium text-[#00695C] hover:underline">Privacy Policy<span class="sr-only"> (opens in a new tab)</span></a>.
                                </span>
                            </label>
                            <p v-if="form.errors.terms" id="terms-error" class="mt-1.5 flex items-start gap-2 text-sm text-red-600" role="alert">
                                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                {{ form.errors.terms }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            :aria-busy="form.processing"
                            class="flex h-12 w-full items-center justify-center gap-2.5 rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574B] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#00695C]/25 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
                            {{ form.processing ? 'Creating account…' : 'Create account' }}
                            <ArrowRight v-if="!form.processing" class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </form>

                    <template v-if="googleEnabled">
                        <div class="my-6 flex items-center gap-4" aria-hidden="true">
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
                            Sign up with Google
                        </a>
                        <p class="mt-3 text-center text-xs leading-5 text-slate-500">
                            By continuing you agree to the
                            <a href="/terms" target="_blank" rel="noopener noreferrer" class="underline hover:text-[#00695C]">Terms</a>
                            and
                            <a href="/privacy" target="_blank" rel="noopener noreferrer" class="underline hover:text-[#00695C]">Privacy Policy</a>.
                        </p>
                    </template>

                    <p class="mt-7 text-center text-sm text-slate-500">
                        Already have an account?
                        <Link :href="route('login')" class="ml-1 rounded font-semibold text-[#00695C] hover:text-[#004D40] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40">
                            Log in
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
