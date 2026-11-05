<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import { AlertCircle, ArrowLeft, ArrowRight, Eye, EyeOff, LoaderCircle, Lock, ShieldCheck } from 'lucide-vue-next';

const page = usePage();
// Which account's password is being asked for matters if you use more than one.
const accountEmail = computed(() => ((page.props as any).auth?.user?.email as string | undefined) ?? '');

const form = useForm({
    password: '',
});

const showPassword = ref(false);
const capsLock = ref(false);

// Start typing immediately on desktop; never pop the keyboard unprompted on touch devices.
onMounted(() => {
    if (!window.matchMedia('(pointer: coarse)').matches) document.getElementById('password')?.focus();
});

const submit = () => {
    form.post(route('password.confirm'), {
        onError: () => nextTick(() => document.getElementById('password')?.focus()),
        onFinish: () => form.reset(),
    });
};

const trackCapsLock = (event: KeyboardEvent) => {
    capsLock.value = event.getModifierState?.('CapsLock') ?? false;
};
</script>

<template>
    <Head title="Confirm your password — quickFolio" />

    <main class="min-h-screen bg-white font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <div class="grid min-h-screen lg:grid-cols-2">
            <!-- Brand panel: one clear reason, no filler -->
            <aside class="relative hidden flex-col overflow-hidden bg-[#F4F7F7] px-12 py-12 dark:bg-slate-900 lg:flex xl:px-16" aria-hidden="true">
                <Link href="/" class="w-fit" tabindex="-1">
                    <AppLogo size="lg" />
                </Link>

                <div class="mt-16 max-w-md">
                    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-[#DCEDE9] text-[#00695C] dark:bg-teal-900/50 dark:text-teal-300">
                        <ShieldCheck class="h-7 w-7" />
                    </span>
                    <p class="mt-8 text-5xl font-bold leading-[1.1] tracking-tight text-[#0F172A] dark:text-slate-50 xl:text-6xl">
                        A quick check
                        <span class="text-[#00695C] dark:text-teal-300">before you continue.</span>
                    </p>
                    <p class="mt-5 text-lg leading-8 text-slate-600 dark:text-slate-300">
                        This area is protected. Confirming your password keeps your account safe, even if someone else is using your device.
                    </p>
                </div>
            </aside>

            <!-- Form -->
            <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10 lg:px-14">
                <div class="w-full max-w-md">
                    <Link href="/" class="mb-10 flex justify-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 lg:hidden" aria-label="quickFolio home">
                        <AppLogo size="lg" />
                    </Link>

                    <div class="mb-8">
                        <h1 class="text-3xl font-bold tracking-tight text-[#0F172A] dark:text-slate-50">Confirm your password</h1>
                        <p class="mt-2 leading-6 text-slate-500 dark:text-slate-400">Enter your password to continue to this protected area.</p>

                        <p
                            v-if="accountEmail"
                            class="mt-4 inline-flex max-w-full items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <span class="shrink-0 text-slate-400 dark:text-slate-500">Signed in as</span>
                            <span class="truncate font-medium text-slate-800 dark:text-slate-100">{{ accountEmail }}</span>
                        </p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Password</label>
                            <div class="relative">
                                <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Enter your password"
                                    class="h-12 w-full rounded-xl border bg-white pl-12 pr-14 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-4 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:[color-scheme:dark]"
                                    :class="form.errors.password
                                        ? 'border-red-500 focus:border-red-500 focus:ring-red-100 dark:border-red-400 dark:focus:border-red-400 dark:focus:ring-red-500/20'
                                        : 'border-slate-300 hover:border-slate-400 focus:border-[#00695C] focus:ring-[#00695C]/10 dark:border-slate-700 dark:hover:border-slate-500 dark:focus:border-teal-400 dark:focus:ring-teal-400/20'"
                                    :aria-invalid="form.errors.password ? 'true' : undefined"
                                    :aria-describedby="[form.errors.password ? 'password-error' : '', capsLock ? 'caps-hint' : ''].filter(Boolean).join(' ') || undefined"
                                    @keydown="trackCapsLock"
                                    @keyup="trackCapsLock"
                                    @blur="capsLock = false"
                                />
                                <button
                                    type="button"
                                    class="absolute right-1.5 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:hover:bg-slate-800 dark:hover:text-teal-300"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showPassword"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="h-5 w-5" aria-hidden="true" />
                                    <Eye v-else class="h-5 w-5" aria-hidden="true" />
                                </button>
                            </div>
                            <p v-if="capsLock" id="caps-hint" class="mt-2 flex items-center gap-2 text-sm text-[#9A6700] dark:text-amber-300">
                                <AlertCircle class="h-4 w-4 shrink-0" aria-hidden="true" />
                                Caps Lock is on.
                            </p>
                            <p v-if="form.errors.password" id="password-error" class="mt-2 flex items-start gap-2 text-sm text-red-600 dark:text-red-400" role="alert">
                                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            :aria-busy="form.processing"
                            class="flex h-12 w-full items-center justify-center gap-2.5 rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574B] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#00695C]/25 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-teal-400/30"
                        >
                            <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin" aria-hidden="true" />
                            {{ form.processing ? 'Confirming…' : 'Confirm' }}
                            <ArrowRight v-if="!form.processing" class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </form>

                    <div class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-800">
                        <Link
                            :href="route('dashboard')"
                            class="inline-flex min-h-10 items-center gap-2 rounded text-sm font-semibold text-slate-500 transition hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:text-slate-400 dark:hover:text-teal-300"
                        >
                            <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                            Cancel and go to dashboard
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
