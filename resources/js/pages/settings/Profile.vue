<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData, type User } from '@/types';
import { AlertCircle, ArrowLeft, CheckCircle2, CircleUserRound, LoaderCircle, LogOut, Palette, ShieldCheck } from 'lucide-vue-next';
import { computed, nextTick } from 'vue';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const page = usePage<SharedData>();
// Reactive: the sidebar updates the moment a save succeeds, instead of showing the old name until a reload.
const user = computed(() => page.props.auth.user as User);

const form = useForm({
    name: user.value.name,
    email: user.value.email,
});

const emailChanged = computed(() => form.email.trim() !== user.value.email);
const isUnverified = computed(() => !user.value.email_verified_at);

const initials = computed(
    () =>
        user.value.name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word.charAt(0).toUpperCase())
            .join('') || '?',
);

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onError: (errors) => {
            // Take the user to the first thing that needs fixing.
            const first = (['name', 'email'] as const).find((key) => errors[key]);
            nextTick(() => first && document.getElementById(first)?.focus());
        },
    });
};

const logout = () => router.post(route('logout'));

const navItems = [
    { label: 'Profile', href: '/settings/profile', icon: CircleUserRound },
    { label: 'Password', href: '/settings/password', icon: ShieldCheck },
    { label: 'Appearance', href: '/settings/appearance', icon: Palette },
];

const isCurrent = (href: string) => page.url.split('?')[0] === href;

const inputClass = (hasError: boolean) => [
    'block min-h-11 w-full rounded-xl bg-white px-3.5 text-[#0F172A] focus-visible:ring-[#00695C] dark:border-[#334155] dark:bg-[#0F172A] dark:text-slate-100 dark:placeholder:text-slate-500',
    hasError ? 'border-red-500' : 'border-[#DCE6E4]',
];
</script>

<template>
    <Head title="Profile — quickFolio" />

    <div class="min-h-screen bg-[#F4F7F7] text-[#0F172A] dark:bg-[#0F172A] dark:text-slate-100">
        <header class="sticky top-0 z-40 border-b border-[#E1E8E7] bg-white/95 backdrop-blur dark:border-slate-700 dark:bg-[#0F172A]/95">
            <nav class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8" aria-label="Main navigation">
                <Link :href="route('dashboard')" class="flex shrink-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40" aria-label="quickFolio dashboard">
                    <AppLogo size="md" />
                </Link>

                <!-- Always has an icon: the old button was an empty teal box on mobile -->
                <Link
                    :href="route('dashboard')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#DCE6E4] bg-white px-3.5 text-sm font-semibold text-[#334155] transition hover:border-[#00695C] hover:text-[#00695C] dark:border-slate-600 dark:bg-[#0F172A] dark:text-slate-200 dark:hover:text-teal-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                >
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    Dashboard
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Settings</h1>

            <div class="mt-6 grid gap-6 lg:mt-8 lg:grid-cols-[220px_minmax(0,1fr)]">
                <!-- Settings navigation: tabs on mobile, a sidebar with your identity on desktop -->
                <aside class="min-w-0">
                    <div class="hidden items-center gap-3 px-2 pb-5 lg:flex">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#E8F4F1] text-sm font-bold text-[#00695C] dark:bg-teal-950 dark:text-teal-300" aria-hidden="true">{{ initials }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ user.name }}</p>
                            <p class="truncate text-xs text-[#64748B] dark:text-slate-400">{{ user.email }}</p>
                        </div>
                    </div>

                    <nav aria-label="Settings" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:-mx-6 sm:px-6 lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0 lg:pb-0">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            :aria-current="isCurrent(item.href) ? 'page' : undefined"
                            class="inline-flex min-h-10 shrink-0 items-center gap-3 rounded-xl border px-3.5 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 lg:border-transparent"
                            :class="isCurrent(item.href)
                                ? 'border-[#BFD4CE] bg-[#E8F4F1] font-semibold text-[#00695C] dark:border-teal-800 dark:bg-teal-950 dark:text-teal-300'
                                : 'border-[#E1E8E7] bg-white text-[#64748B] hover:bg-[#F4F7F7] hover:text-[#00695C] lg:bg-transparent dark:border-slate-700 dark:bg-[#0F172A] dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-teal-300 lg:dark:bg-transparent'"
                        >
                            <component :is="item.icon" class="h-4 w-4" aria-hidden="true" />
                            {{ item.label }}
                        </Link>

                        <button
                            type="button"
                            class="inline-flex min-h-10 shrink-0 items-center gap-3 rounded-xl border border-[#E1E8E7] bg-white px-3.5 text-sm font-medium text-[#64748B] transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300 lg:mt-3 lg:border-transparent lg:bg-transparent dark:border-slate-700 dark:bg-[#0F172A] dark:text-slate-400 dark:hover:bg-red-950/40 dark:hover:text-red-300 lg:dark:bg-transparent"
                            @click="logout"
                        >
                            <LogOut class="h-4 w-4" aria-hidden="true" />
                            Log out
                        </button>
                    </nav>
                </aside>

                <div class="min-w-0 space-y-6">
                    <section class="rounded-2xl border border-[#E1E8E7] bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-[#0F172A]" aria-labelledby="profile-heading">
                        <h2 id="profile-heading" class="text-lg font-bold dark:text-slate-100">Profile</h2>
                        <p class="mt-1 text-sm leading-5 text-[#64748B] dark:text-slate-400">Your name appears on your published portfolios.</p>

                        <form class="mt-6 space-y-5" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <Label for="name" class="text-sm font-semibold text-[#334155] dark:text-slate-200">Full name</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    :class="inputClass(Boolean(form.errors.name))"
                                    required
                                    autocomplete="name"
                                    placeholder="Your full name"
                                    :aria-invalid="form.errors.name ? 'true' : undefined"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email" class="text-sm font-semibold text-[#334155] dark:text-slate-200">Email address</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    :class="inputClass(Boolean(form.errors.email))"
                                    required
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                    aria-describedby="email-hint"
                                    :aria-invalid="form.errors.email ? 'true' : undefined"
                                />
                                <InputError :message="form.errors.email" />
                                <p id="email-hint" class="text-xs leading-5 text-[#64748B] dark:text-slate-400">We’ll send important account messages here, so use an address you can access.</p>

                                <!-- Say what will happen before it does -->
                                <p
                                    v-if="mustVerifyEmail && emailChanged && !form.errors.email"
                                    class="flex items-start gap-2 rounded-xl border border-[#BFD4CE] bg-[#F0F7F5] px-3 py-2.5 text-sm text-[#00574C] dark:border-teal-900 dark:bg-teal-950/50 dark:text-teal-200"
                                    role="status"
                                >
                                    <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                    You’ll need to verify the new address after saving.
                                </p>
                            </div>

                            <div v-if="mustVerifyEmail && isUnverified && !emailChanged" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
                                <p class="flex items-start gap-2 text-sm font-medium text-amber-900 dark:text-amber-200">
                                    <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                    Your email address isn’t verified yet.
                                </p>
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="mt-2 rounded text-left text-sm font-semibold text-[#00695C] underline decoration-[#00695C]/30 underline-offset-4 hover:text-[#00574C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:text-teal-300 dark:hover:text-teal-200"
                                >
                                    Resend verification email
                                </Link>
                                <p v-if="status === 'verification-link-sent'" class="mt-2 flex items-center gap-2 text-sm font-medium text-[#00695C] dark:text-teal-300" role="status">
                                    <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
                                    A new verification link is on its way to your inbox.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 border-t border-[#EDF1F0] pt-5 dark:border-slate-700">
                                <button
                                    type="submit"
                                    :disabled="form.processing || !form.isDirty"
                                    class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                                    {{ form.processing ? 'Saving…' : 'Save changes' }}
                                </button>

                                <button
                                    v-if="form.isDirty && !form.processing"
                                    type="button"
                                    class="inline-flex h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-[#64748B] transition hover:bg-[#F4F7F7] hover:text-[#334155] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                    @click="form.reset(); form.clearErrors()"
                                >
                                    Discard changes
                                </button>

                                <Transition name="saved">
                                    <p v-if="form.recentlySuccessful" class="flex items-center gap-1.5 text-sm font-medium text-[#00695C] dark:text-teal-300" role="status">
                                        <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
                                        Changes saved
                                    </p>
                                </Transition>
                            </div>
                        </form>
                    </section>

                    <!-- Honestly named: this is the destructive action, not "account security" -->
                    <section class="rounded-2xl border border-red-200 bg-white p-5 sm:p-7 dark:border-red-900 dark:bg-[#0F172A]" aria-labelledby="danger-heading">
                        <h2 id="danger-heading" class="text-lg font-bold text-red-700 dark:text-red-300">Danger zone</h2>
                        <p class="mt-1 text-sm leading-5 text-[#64748B] dark:text-slate-400">Deleting your account is permanent and can’t be undone.</p>
                        <div class="mt-5">
                            <DeleteUser />
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.saved-enter-active,
.saved-leave-active {
    transition: opacity 180ms ease;
}
.saved-enter-from,
.saved-leave-to {
    opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
    .saved-enter-active,
    .saved-leave-active {
        transition: none;
    }
}
</style>
