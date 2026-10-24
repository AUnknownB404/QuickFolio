<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData, type User } from '@/types';
import { CheckCircle2, CircleUserRound, Mail, Palette, ShieldCheck } from 'lucide-vue-next';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}

defineProps<Props>();

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};

const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="Profile settings — quickFolio" />

    <div class="min-h-screen bg-[#F4F7F7] text-[#0F172A]">
        <header class="sticky top-0 z-40 border-b border-[#E1E8E7] bg-white/95 backdrop-blur">
            <nav class="mx-auto flex min-h-[72px] max-w-7xl flex-wrap items-center justify-between gap-x-5 gap-y-3 px-4 py-3 sm:px-6 lg:px-8" aria-label="Main navigation">
                <Link href="/" class="flex shrink-0 items-center" aria-label="quickFolio home">
                    <AppLogo size="md" />
                </Link>

                <div class="ml-auto flex items-center gap-2 sm:ml-0 sm:gap-3">
                    <Link href="/dashboard" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-3.5 text-sm font-semibold text-white transition hover:bg-[#00574C] focus:outline-none focus:ring-2 focus:ring-[#00695C]/30 sm:px-4">
                        <span class="hidden sm:inline">Dashboard</span>
                    </Link>
                </div>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <section class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-[#00695C]">Account preferences</p>
                    <h1 class="text-3xl font-bold tracking-tight text-[#0F172A] sm:text-4xl">Profile settings<span class="text-[#00695C]">.</span></h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-[#64748B] sm:text-base">Manage your personal information and keep your quickFolio account up to date.</p>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-[250px_minmax(0,1fr)]">
                <aside class="h-fit rounded-2xl border border-[#E1E8E7] bg-white p-4 shadow-[0_2px_8px_rgba(15,23,42,0.025)]">
                    <div class="flex items-center gap-3 border-b border-[#EDF1F0] px-2 pb-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#E8F4F1] text-[#00695C]"><CircleUserRound class="h-6 w-6" /></div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-[#0F172A]">{{ user.name }}</p>
                            <p class="truncate text-xs text-[#64748B]">{{ user.email }}</p>
                        </div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <a href="#profile-information" class="flex items-center gap-3 rounded-xl bg-[#E8F4F1] px-3 py-2.5 text-sm font-semibold text-[#00695C]">
                            <CircleUserRound class="h-4 w-4" /> Profile information
                        </a>
                        <a href="/settings/password" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-[#64748B] transition hover:bg-[#F4F7F7] hover:text-[#00695C]">
                            <ShieldCheck class="h-4 w-4" /> Password
                        </a>
                        <a href="/settings/appearance" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-[#64748B] transition hover:bg-[#F4F7F7] hover:text-[#00695C]">
                            <Palette class="h-4 w-4" /> Appearance
                        </a>
                    </div>
                    <div class="mt-5 rounded-xl bg-[#F4F8F7] p-3">
                        <div class="flex items-center gap-2 text-xs font-semibold text-[#00695C]"><Mail class="h-4 w-4" /> Email address</div>
                        <p class="mt-1.5 text-xs leading-5 text-[#64748B]">Use an email you can access to receive important account messages.</p>
                    </div>
                </aside>

                <div class="space-y-6">
                    <section id="profile-information" class="rounded-2xl border border-[#E1E8E7] bg-white p-5 shadow-[0_2px_8px_rgba(15,23,42,0.025)] sm:p-7">
                        <div class="mb-6 flex items-start gap-3 border-b border-[#EDF1F0] pb-5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E8F4F1] text-[#00695C]"><CircleUserRound class="h-5 w-5" /></span>
                            <div>
                                <h2 class="text-lg font-bold text-[#0F172A]">Profile information</h2>
                                <p class="mt-1 text-sm leading-5 text-[#64748B]">Update the name and email associated with your account.</p>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div class="grid gap-2">
                                <Label for="name" class="text-sm font-semibold text-[#334155]">Full name</Label>
                                <Input id="name" v-model="form.name" class="mt-1 block min-h-11 w-full rounded-xl border-[#DCE6E4] bg-white px-3.5 focus-visible:ring-[#00695C]" required autocomplete="name" placeholder="Your full name" />
                                <InputError class="mt-1" :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email" class="text-sm font-semibold text-[#334155]">Email address</Label>
                                <Input id="email" v-model="form.email" type="email" class="mt-1 block min-h-11 w-full rounded-xl border-[#DCE6E4] bg-white px-3.5 focus-visible:ring-[#00695C]" required autocomplete="username" placeholder="you@example.com" />
                                <InputError class="mt-1" :message="form.errors.email" />
                            </div>

                            <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-medium text-amber-900">Your email address is not verified yet.</p>
                                <Link :href="route('verification.send')" method="post" as="button" class="mt-1 text-left text-sm font-semibold text-[#00695C] underline decoration-[#00695C]/30 underline-offset-4 hover:text-[#00574C]">
                                    Resend verification email
                                </Link>
                                <p v-if="status === 'verification-link-sent'" class="mt-2 flex items-center gap-2 text-sm font-medium text-[#00695C]"><CheckCircle2 class="h-4 w-4" /> A new verification link has been sent to your email.</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 border-t border-[#EDF1F0] pt-5">
                                <button type="submit" :disabled="form.processing" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574C] disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-[#00695C]/30">
                                    {{ form.processing ? 'Saving changes…' : 'Save changes' }}
                                </button>
                                <TransitionRoot :show="form.recentlySuccessful" enter="transition ease-out duration-200" enter-from="opacity-0" leave="transition ease-in duration-150" leave-to="opacity-0">
                                    <p class="flex items-center gap-1.5 text-sm font-medium text-[#00695C]"><CheckCircle2 class="h-4 w-4" /> Changes saved</p>
                                </TransitionRoot>
                            </div>
                        </form>
                    </section>

                    <section id="account-security" class="rounded-2xl border border-[#E1E8E7] bg-white p-5 shadow-[0_2px_8px_rgba(15,23,42,0.025)] sm:p-7">
                        <div class="mb-5 flex items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E8F4F1] text-[#00695C]"><ShieldCheck class="h-5 w-5" /></span>
                            <div>
                                <h2 class="text-lg font-bold text-[#0F172A]">Account security</h2>
                                <p class="mt-1 text-sm leading-5 text-[#64748B]">Review account access or permanently remove your account.</p>
                            </div>
                        </div>
                        <div class="rounded-xl border border-[#E1E8E7] bg-[#F8FBFA] p-4 sm:p-5">
                            <DeleteUser />
                        </div>
                    </section>
                </div>
            </div>

            <footer class="mt-10 border-t border-[#E1E8E7] py-5 text-center text-xs text-[#94A3B8]">
                quickFolio <span class="mx-1">·</span> Your portfolio, your story.
            </footer>
        </main>
    </div>
</template>
