<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SettingsLayout from '@/components/SettingsLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { AlertCircle, Check, CheckCircle2, Eye, EyeOff, LoaderCircle } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showPasswords = ref(false);
const capsLock = ref(false);
const confirmTouched = ref(false);

// Needs all three before it can possibly succeed.
const canSubmit = computed(() => form.current_password !== '' && form.password !== '' && form.password_confirmation !== '');

// A template ref on a component is the component, not the <input>, so focus by id instead.
const focusField = (id: string) => nextTick(() => document.getElementById(id)?.focus());

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            confirmTouched.value = false;
            showPasswords.value = false;
        },
        onError: (errors) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                confirmTouched.value = false;
                focusField('password');
            }
            if (errors.current_password) {
                form.reset('current_password');
                focusField('current_password');
            }
        },
    });
};

const trackCapsLock = (event: KeyboardEvent) => {
    capsLock.value = event.getModifierState?.('CapsLock') ?? false;
};

/* Password guidance — shown while typing, instead of a rejection after submit */
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

const inputClass = (hasError: boolean) => [
    'block min-h-11 w-full rounded-xl bg-white px-3.5 pr-12 focus-visible:ring-[#00695C] dark:bg-slate-950 dark:text-slate-100',
    hasError ? 'border-red-500' : 'border-[#DCE6E4] dark:border-slate-700',
];

const describedBy = (...ids: (string | false | undefined)[]) => ids.filter(Boolean).join(' ') || undefined;
</script>

<template>
    <Head title="Password — quickFolio" />

    <SettingsLayout>
        <section class="rounded-2xl border border-[#E1E8E7] bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-7" aria-labelledby="password-heading">
            <h2 id="password-heading" class="text-lg font-bold">Password</h2>
            <p class="mt-1 text-sm leading-5 text-[#64748B] dark:text-slate-400">Enter your current password, then choose a new one.</p>

            <form class="mt-6 space-y-5" @submit.prevent="updatePassword">
                <!-- Current -->
                <div class="grid gap-2">
                    <Label for="current_password" class="text-sm font-semibold text-[#334155] dark:text-slate-200">Current password</Label>
                    <div class="relative">
                        <Input
                            id="current_password"
                            v-model="form.current_password"
                            :type="showPasswords ? 'text' : 'password'"
                            :class="inputClass(Boolean(form.errors.current_password))"
                            autocomplete="current-password"
                            placeholder="Enter current password"
                            :aria-invalid="form.errors.current_password ? 'true' : undefined"
                        />
                        <button
                            type="button"
                            class="absolute right-1.5 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:hover:bg-slate-800"
                            :aria-label="showPasswords ? 'Hide passwords' : 'Show passwords'"
                            :aria-pressed="showPasswords"
                            @click="showPasswords = !showPasswords"
                        >
                            <EyeOff v-if="showPasswords" class="h-4 w-4" aria-hidden="true" />
                            <Eye v-else class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>
                    <InputError :message="form.errors.current_password" />
                </div>

                <!-- New -->
                <div class="grid gap-2">
                    <Label for="password" class="text-sm font-semibold text-[#334155] dark:text-slate-200">New password</Label>
                    <div class="relative">
                        <Input
                            id="password"
                            v-model="form.password"
                            :type="showPasswords ? 'text' : 'password'"
                            :class="inputClass(Boolean(form.errors.password))"
                            autocomplete="new-password"
                            placeholder="Create a new password"
                            :aria-invalid="form.errors.password ? 'true' : undefined"
                            :aria-describedby="describedBy('password-hint', capsLock && 'caps-hint')"
                            @keydown="trackCapsLock"
                            @keyup="trackCapsLock"
                            @blur="capsLock = false"
                        />
                        <button
                            type="button"
                            class="absolute right-1.5 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:hover:bg-slate-800"
                            :aria-label="showPasswords ? 'Hide passwords' : 'Show passwords'"
                            :aria-pressed="showPasswords"
                            @click="showPasswords = !showPasswords"
                        >
                            <EyeOff v-if="showPasswords" class="h-4 w-4" aria-hidden="true" />
                            <Eye v-else class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>

                    <div v-if="form.password" class="flex items-center gap-3" aria-hidden="true">
                        <div class="flex flex-1 gap-1">
                            <span
                                v-for="segment in 4"
                                :key="segment"
                                class="h-1.5 flex-1 rounded-full transition-colors duration-200 motion-reduce:transition-none"
                                :class="segment <= strength.level ? strengthColors[strength.level] : 'bg-slate-200 dark:bg-slate-700'"
                            />
                        </div>
                        <span class="w-16 text-right text-xs font-medium text-[#64748B] dark:text-slate-400">{{ strength.label }}</span>
                    </div>
                    <p class="sr-only" aria-live="polite">{{ strength.label ? `Password strength: ${strength.label}` : '' }}</p>

                    <p id="password-hint" class="text-xs leading-5 text-[#64748B] dark:text-slate-400">
                        At least 8 characters, and different from passwords you use elsewhere. A few unrelated words make a strong, memorable one.
                    </p>
                    <p v-if="capsLock" id="caps-hint" class="flex items-center gap-2 text-sm text-[#9A6700] dark:text-amber-300">
                        <AlertCircle class="h-4 w-4 shrink-0" aria-hidden="true" />
                        Caps Lock is on.
                    </p>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm -->
                <div class="grid gap-2">
                    <Label for="password_confirmation" class="text-sm font-semibold text-[#334155] dark:text-slate-200">Confirm new password</Label>
                    <div class="relative">
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showPasswords ? 'text' : 'password'"
                            :class="inputClass(Boolean(form.errors.password_confirmation) || passwordsDiffer)"
                            autocomplete="new-password"
                            placeholder="Re-enter new password"
                            :aria-invalid="form.errors.password_confirmation || passwordsDiffer ? 'true' : undefined"
                            aria-describedby="confirm-status"
                            @blur="confirmTouched = true"
                        />
                    </div>
                    <p id="confirm-status" class="min-h-5 text-sm" aria-live="polite">
                        <span v-if="passwordsMatch" class="flex items-center gap-2 text-[#16734A] dark:text-emerald-300">
                            <Check class="h-4 w-4 shrink-0" aria-hidden="true" />
                            Passwords match
                        </span>
                        <span v-else-if="passwordsDiffer" class="flex items-center gap-2 text-red-600 dark:text-red-400">
                            <AlertCircle class="h-4 w-4 shrink-0" aria-hidden="true" />
                            These don’t match yet
                        </span>
                    </p>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t border-[#EDF1F0] pt-5 dark:border-slate-800">
                    <button
                        type="submit"
                        :disabled="form.processing || !canSubmit"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        {{ form.processing ? 'Saving…' : 'Update password' }}
                    </button>

                    <Transition name="saved">
                        <p v-if="form.recentlySuccessful" class="flex items-center gap-1.5 text-sm font-medium text-[#00695C] dark:text-teal-300" role="status">
                            <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
                            Password updated
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    </SettingsLayout>
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
