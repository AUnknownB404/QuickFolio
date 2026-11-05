<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { AlertCircle, ArrowLeft, ArrowRight, CheckCircle2, LoaderCircle, Mail } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const emailSent = ref(false);
const confirmationHeading = ref<HTMLElement | null>(null);

// Start typing immediately on desktop; never pop the keyboard unprompted on touch devices.
onMounted(() => {
    if (!window.matchMedia('(pointer: coarse)').matches) document.getElementById('email')?.focus();
});

/* -------------------------------------------------------------------------
 * Resend cooldown. Laravel throttles reset requests (60 seconds by default),
 * so the button counts down instead of letting people hit an error.
 * ---------------------------------------------------------------------- */

const RESEND_SECONDS = 60;
const cooldown = ref(0);
let cooldownTimer: number | undefined;

const startCooldown = () => {
    window.clearInterval(cooldownTimer);
    cooldown.value = RESEND_SECONDS;
    cooldownTimer = window.setInterval(() => {
        cooldown.value -= 1;
        if (cooldown.value <= 0) window.clearInterval(cooldownTimer);
    }, 1000);
};
onBeforeUnmount(() => window.clearInterval(cooldownTimer));

const canSend = computed(() => !form.processing && cooldown.value <= 0);

const send = () => {
    if (!canSend.value) return;

    form.post(route('password.email'), {
        preserveScroll: true,
        onSuccess: () => {
            emailSent.value = true;
            startCooldown();
            // Move to the confirmation so screen readers announce it, and sighted users aren't left on a dead form.
            nextTick(() => confirmationHeading.value?.focus());
        },
        onError: () => {
            if (!emailSent.value) nextTick(() => document.getElementById('email')?.focus());
        },
    });
};

const useDifferentEmail = () => {
    emailSent.value = false;
    form.clearErrors();
    nextTick(() => {
        const input = document.getElementById('email') as HTMLInputElement | null;
        input?.focus();
        input?.select();
    });
};

const inputClass = (hasError: boolean) => [
    'h-12 w-full rounded-xl border bg-white pl-12 pr-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-4 read-only:opacity-70 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:[color-scheme:dark]',
    hasError
        ? 'border-red-500 focus:border-red-500 focus:ring-red-100 dark:border-red-400 dark:focus:border-red-400 dark:focus:ring-red-500/20'
        : 'border-slate-300 hover:border-slate-400 focus:border-[#00695C] focus:ring-[#00695C]/10 dark:border-slate-700 dark:hover:border-slate-500 dark:focus:border-teal-400 dark:focus:ring-teal-400/20',
];

const steps = [
    { title: 'Enter your email', description: 'Use the address you signed up with.' },
    { title: 'Open the link we send', description: 'It usually arrives within a minute.' },
    { title: 'Choose a new password', description: 'Then you’re straight back in.' },
];
</script>

<template>
    <Head title="Reset your password — quickFolio" />

    <main class="min-h-screen bg-white font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <div class="grid min-h-screen lg:grid-cols-2">
            <!-- Brand panel: what is about to happen -->
            <aside class="relative hidden flex-col overflow-hidden bg-[#F4F7F7] px-12 py-12 dark:bg-slate-900 lg:flex xl:px-16" aria-label="How resetting works">
                <Link href="/" class="w-fit rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40" aria-label="quickFolio home">
                    <AppLogo size="lg" />
                </Link>

                <div class="mt-16 max-w-md">
                    <p class="text-5xl font-bold leading-[1.1] tracking-tight text-[#0F172A] dark:text-slate-50 xl:text-6xl">
                        Locked out?
                        <span class="text-[#00695C] dark:text-teal-300">We’ll get you back in.</span>
                    </p>

                    <ol class="mt-10 space-y-5">
                        <li v-for="(step, index) in steps" :key="step.title" class="flex gap-4">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#DCEDE9] text-sm font-bold text-[#00695C] dark:bg-teal-900/50 dark:text-teal-300" aria-hidden="true">{{ index + 1 }}</span>
                            <div>
                                <p class="font-semibold"><span class="sr-only">Step {{ index + 1 }}: </span>{{ step.title }}</p>
                                <p class="mt-0.5 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ step.description }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </aside>

            <!-- Form -->
            <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10 lg:px-14">
                <div class="w-full max-w-md">
                    <Link href="/" class="mb-10 flex justify-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 lg:hidden" aria-label="quickFolio home">
                        <AppLogo size="lg" />
                    </Link>

                    <!-- ── Ask for the email ── -->
                    <template v-if="!emailSent">
                        <div class="mb-8">
                            <h1 class="text-3xl font-bold tracking-tight text-[#0F172A] dark:text-slate-50">Reset your password</h1>
                            <p class="mt-2 leading-6 text-slate-500 dark:text-slate-400">
                                Enter the email you signed up with and we’ll send you a link to choose a new password.
                            </p>
                        </div>

                        <!-- Only if the server sent a message we have no confirmation for (e.g. a redirect back here) -->
                        <div
                            v-if="status"
                            class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                            role="status"
                        >
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                            {{ status }}
                        </div>

                        <form class="space-y-5" @submit.prevent="send">
                            <div>
                                <label for="email" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Email address</label>
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
                                        :readonly="form.processing"
                                        placeholder="you@example.com"
                                        :class="inputClass(Boolean(form.errors.email))"
                                        :aria-invalid="form.errors.email ? 'true' : undefined"
                                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                                    />
                                </div>
                                <p v-if="form.errors.email" id="email-error" class="mt-2 flex items-start gap-2 text-sm text-red-600 dark:text-red-400" role="alert">
                                    <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                :aria-busy="form.processing"
                                class="flex h-12 w-full items-center justify-center gap-2.5 rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574B] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#00695C]/25 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-teal-400/30"
                            >
                                <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
                                {{ form.processing ? 'Sending…' : 'Send reset link' }}
                                <ArrowRight v-if="!form.processing" class="h-5 w-5" aria-hidden="true" />
                            </button>
                        </form>
                    </template>

                    <!-- ── Sent: say where, what next, and what to do if it doesn't arrive ── -->
                    <template v-else>
                        <div class="mb-6 grid h-14 w-14 place-items-center rounded-2xl border border-[#C8E3DD] bg-[#EFF8F5] text-[#00695C] dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-300">
                            <Mail class="h-6 w-6" aria-hidden="true" />
                        </div>

                        <h1 ref="confirmationHeading" tabindex="-1" class="text-3xl font-bold tracking-tight text-[#0F172A] outline-none dark:text-slate-50">
                            Check your email
                        </h1>
                        <p class="mt-3 leading-6 text-slate-600 dark:text-slate-300" role="status">
                            If an account exists for
                            <strong class="break-all font-semibold text-slate-900 dark:text-slate-50">{{ form.email }}</strong>,
                            a reset link is on its way.
                        </p>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                            It can take a minute. If you don’t see it, check your spam or junk folder.
                        </p>

                        <p v-if="form.errors.email" class="mt-4 flex items-start gap-2 text-sm text-red-600 dark:text-red-400" role="alert">
                            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                            {{ form.errors.email }}
                        </p>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                            <button
                                type="button"
                                :disabled="!canSend"
                                :aria-busy="form.processing"
                                class="flex h-12 flex-1 items-center justify-center gap-2.5 rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-700"
                                @click="send"
                            >
                                <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
                                {{ form.processing ? 'Sending…' : cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend email' }}
                            </button>
                            <button
                                type="button"
                                class="flex h-12 flex-1 items-center justify-center rounded-xl px-5 text-sm font-semibold text-[#00695C] transition hover:bg-[#EFF8F5] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#00695C]/20 dark:text-teal-300 dark:hover:bg-teal-950/40"
                                @click="useDifferentEmail"
                            >
                                Use a different email
                            </button>
                        </div>
                    </template>

                    <div class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-800">
                        <Link
                            :href="route('login')"
                            class="inline-flex min-h-10 items-center gap-2 rounded text-sm font-semibold text-slate-500 transition hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:text-slate-400 dark:hover:text-teal-300"
                        >
                            <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                            Back to log in
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
