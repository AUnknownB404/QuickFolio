<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLogo from '@/components/AppLogo.vue';
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { CircleUserRound, KeyRound, Palette, ShieldCheck, CheckCircle2, LockKeyhole } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = ref<HTMLInputElement>();
const currentPasswordInput = ref<HTMLInputElement>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: any) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                if (passwordInput.value instanceof HTMLInputElement) {
                    passwordInput.value.focus();
                }
            }

            if (errors.current_password) {
                form.reset('current_password');
                if (currentPasswordInput.value instanceof HTMLInputElement) {
                    currentPasswordInput.value.focus();
                }
            }
        },
    });
};
</script>

<template>
    <Head title="Password settings — quickFolio" />

    <div class="min-h-screen bg-[#F4F7F7] text-[#0F172A]">
        <header class="sticky top-0 z-40 border-b border-[#E1E8E7] bg-white/95 backdrop-blur">
            <nav class="mx-auto flex min-h-[72px] flex-wrap items-center justify-between gap-x-5 gap-y-3 px-4 py-3 sm:px-6 lg:px-8" aria-label="Main navigation">
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
            <section class="mb-8">
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-[#00695C]">Account preferences</p>
                <h1 class="text-3xl font-bold tracking-tight text-[#0F172A] sm:text-4xl">Password settings<span class="text-[#00695C]">.</span></h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-[#64748B] sm:text-base">Keep your account secure by using a strong, unique password.</p>
            </section>

            <div class="grid gap-6 lg:grid-cols-[250px_minmax(0,1fr)]">
                <aside class="h-fit rounded-2xl border border-[#E1E8E7] bg-white p-4 shadow-[0_2px_8px_rgba(15,23,42,0.025)]">
                    <div class="flex items-center gap-3 border-b border-[#EDF1F0] px-2 pb-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#E8F4F1] text-[#00695C]"><CircleUserRound class="h-6 w-6" /></div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#0F172A]">Account settings</p>
                            <p class="text-xs text-[#64748B]">Manage your preferences</p>
                        </div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <Link href="/settings/profile" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-[#64748B] transition hover:bg-[#F4F7F7] hover:text-[#00695C]">
                            <CircleUserRound class="h-4 w-4" /> Profile information
                        </Link>
                        <Link href="/settings/password" class="flex items-center gap-3 rounded-xl bg-[#E8F4F1] px-3 py-2.5 text-sm font-semibold text-[#00695C]">
                            <ShieldCheck class="h-4 w-4" /> Password
                        </Link>
                        <Link href="/settings/appearance" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-[#64748B] transition hover:bg-[#F4F7F7] hover:text-[#00695C]">
                            <Palette class="h-4 w-4" /> Appearance
                        </Link>
                    </div>
                    <div class="mt-5 rounded-xl bg-[#F4F8F7] p-3">
                        <div class="flex items-center gap-2 text-xs font-semibold text-[#00695C]"><LockKeyhole class="h-4 w-4" /> Security tip</div>
                        <p class="mt-1.5 text-xs leading-5 text-[#64748B]">Use a unique password that you do not reuse on other websites.</p>
                    </div>
                </aside>

                <div class="min-w-0 space-y-6">
                    <section class="rounded-2xl border border-[#E1E8E7] bg-white p-5 shadow-[0_2px_8px_rgba(15,23,42,0.025)] sm:p-7">
                        <div class="mb-6 flex items-start gap-3 border-b border-[#EDF1F0] pb-5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E8F4F1] text-[#00695C]"><KeyRound class="h-5 w-5" /></span>
                            <div>
                                <h2 class="text-lg font-bold text-[#0F172A]">Update password</h2>
                                <p class="mt-1 text-sm leading-5 text-[#64748B]">Enter your current password, then choose a new one.</p>
                            </div>
                        </div>

                        <form @submit.prevent="updatePassword" class="space-y-5">
                            <div class="grid gap-2">
                                <Label for="current_password" class="text-sm font-semibold text-[#334155]">Current password</Label>
                                <Input
                                    id="current_password"
                                    ref="currentPasswordInput"
                                    v-model="form.current_password"
                                    type="password"
                                    class="mt-1 block min-h-11 w-full rounded-xl border-[#DCE6E4] bg-white px-3.5 focus-visible:ring-[#00695C]"
                                    autocomplete="current-password"
                                    placeholder="Enter current password"
                                />
                                <InputError class="mt-1" :message="form.errors.current_password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password" class="text-sm font-semibold text-[#334155]">New password</Label>
                                <Input
                                    id="password"
                                    ref="passwordInput"
                                    v-model="form.password"
                                    type="password"
                                    class="mt-1 block min-h-11 w-full rounded-xl border-[#DCE6E4] bg-white px-3.5 focus-visible:ring-[#00695C]"
                                    autocomplete="new-password"
                                    placeholder="Create a new password"
                                />
                                <InputError class="mt-1" :message="form.errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation" class="text-sm font-semibold text-[#334155]">Confirm new password</Label>
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    class="mt-1 block min-h-11 w-full rounded-xl border-[#DCE6E4] bg-white px-3.5 focus-visible:ring-[#00695C]"
                                    autocomplete="new-password"
                                    placeholder="Re-enter new password"
                                />
                                <InputError class="mt-1" :message="form.errors.password_confirmation" />
                            </div>

                            <div class="flex flex-wrap items-center gap-3 border-t border-[#EDF1F0] pt-5">
                                <button type="submit" :disabled="form.processing" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574C] disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-[#00695C]/30">
                                    {{ form.processing ? 'Saving password…' : 'Save password' }}
                                </button>
                                <TransitionRoot :show="form.recentlySuccessful" enter="transition ease-out duration-200" enter-from="opacity-0" leave="transition ease-in duration-150" leave-to="opacity-0">
                                    <p class="flex items-center gap-1.5 text-sm font-medium text-[#00695C]"><CheckCircle2 class="h-4 w-4" /> Password updated</p>
                                </TransitionRoot>
                            </div>
                        </form>
                    </section>

                    <section class="rounded-2xl border border-[#E1E8E7] bg-white p-5 shadow-[0_2px_8px_rgba(15,23,42,0.025)] sm:p-7">
                        <div class="flex items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E8F4F1] text-[#00695C]"><ShieldCheck class="h-5 w-5" /></span>
                            <div>
                                <h2 class="text-lg font-bold text-[#0F172A]">Protect your account</h2>
                                <p class="mt-1 text-sm leading-5 text-[#64748B]">A strong password helps keep your personal information and portfolio projects private.</p>
                            </div>
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
