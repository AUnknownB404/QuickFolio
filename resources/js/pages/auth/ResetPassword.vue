```vue
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Eye,
    EyeOff,
    KeyRound,
    LoaderCircle,
    LockKeyhole,
    ShieldCheck,
} from 'lucide-vue-next'

interface Props {
    token: string
    email: string
}

const props = defineProps<Props>()

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation')
        },
    })
}
</script>

<template>
    <Head title="Reset Password — quickFolio" />

    <main class="auth-page min-h-screen bg-white font-sans text-[#0F172A]">
        <div class="grid min-h-screen lg:grid-cols-2">

            <!-- LEFT PANEL -->
            <section
                class="relative hidden overflow-hidden bg-[#F4F7F7] px-12 py-12 lg:flex lg:flex-col xl:px-16"
            >
                <!-- Brand -->
                <Link href="/" class="flex items-center gap-3">
                    <img src="/logo.svg" alt="" class="h-12 w-12 shrink-0 object-contain" />

                    <span class="text-3xl font-bold tracking-tight">
                        quick<span class="text-[#00695C]">Folio</span>
                    </span>
                </Link>

                <!-- Main content -->
                <div class="relative z-10 mt-20 max-w-xl">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-[#00695C]"
                    >
                        Account Recovery
                    </p>

                    <h1 class="mt-6 text-5xl font-bold leading-tight tracking-tight">
                        A stronger password.
                        <br />
                        A fresh start.
                    </h1>

                    <p class="mt-6 max-w-md text-lg leading-8 text-slate-600">
                        You're almost back in. Choose a new password to secure
                        your account and continue building your portfolio.
                    </p>

                    <!-- Security features -->
                    <div class="mt-12 space-y-7">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]"
                            >
                                <KeyRound class="h-6 w-6" />
                            </div>

                            <div>
                                <h3 class="font-semibold">
                                    Create a new password
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500">
                                    Choose a password that you haven't used
                                    elsewhere.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]"
                            >
                                <ShieldCheck class="h-6 w-6" />
                            </div>

                            <div>
                                <h3 class="font-semibold">
                                    Keep your account secure
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500">
                                    A strong, unique password helps protect
                                    your portfolio and account.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Decorative circles -->
                <div
                    class="pointer-events-none absolute -bottom-24 -right-20 h-80 w-80 rounded-full border border-[#00695C]/10"
                />
                <div
                    class="pointer-events-none absolute -bottom-12 -right-8 h-60 w-60 rounded-full border border-[#00695C]/10"
                />

                <p class="mt-auto pt-12 text-xs text-slate-400">
                    Your next chapter starts here.
                </p>
            </section>

            <!-- RIGHT PANEL -->
            <section
                class="flex min-h-screen items-center justify-center px-6 py-10 sm:px-10 lg:px-14"
            >
                <div class="w-full max-w-md">

                    <!-- Mobile brand -->
                    <Link
                        href="/"
                        class="mb-10 flex items-center justify-center gap-3 lg:hidden"
                    >
                        <img src="/logo.svg" alt="" class="h-10 w-10 shrink-0 object-contain" />

                        <span class="text-2xl font-bold tracking-tight">
                            quick<span class="text-[#00695C]">Folio</span>
                        </span>
                    </Link>

                    <!-- Heading -->
                    <div class="mb-8">
                        <div
                            class="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-[#C8E3DD] bg-[#EFF8F5] text-[#00695C]"
                        >
                            <LockKeyhole class="h-6 w-6" />
                        </div>

                        <h2 class="text-3xl font-bold tracking-tight">
                            Set a new password
                        </h2>

                        <p class="mt-3 text-sm leading-6 text-slate-500">
                            Enter your new password below to regain access to
                            your quickFolio account.
                        </p>
                    </div>

                    <!-- Reset form -->
                    <form @submit.prevent="submit" class="space-y-5">

                        <!-- Email -->
                        <div class="space-y-2">
                            <Label
                                for="email"
                                class="text-sm font-semibold text-[#0F172A]"
                            >
                                Email address
                            </Label>

                            <div class="relative">
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    name="email"
                                    autocomplete="email"
                                    readonly
                                    class="h-12 rounded-xl border-slate-200 bg-slate-50 px-4 pr-10 text-slate-500 shadow-none focus-visible:ring-0"
                                />

                                <CheckCircle2
                                    class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#00695C]"
                                />
                            </div>

                            <p
                                v-if="form.errors.email"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <!-- New password -->
                        <div class="space-y-2">
                            <Label
                                for="password"
                                class="text-sm font-semibold text-[#0F172A]"
                            >
                                New password
                            </Label>

                            <div class="relative">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    autocomplete="new-password"
                                    placeholder="Enter your new password"
                                    required
                                    autofocus
                                    :disabled="form.processing"
                                    :aria-invalid="!!form.errors.password"
                                    class="h-12 rounded-xl border-slate-300 px-4 pr-12 shadow-none placeholder:text-slate-400 focus-visible:border-[#00695C] focus-visible:ring-[#00695C]/20"
                                />

                                <button
                                    type="button"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 transition hover:text-[#00695C]"
                                >
                                    <EyeOff
                                        v-if="showPassword"
                                        class="h-5 w-5"
                                    />
                                    <Eye
                                        v-else
                                        class="h-5 w-5"
                                    />
                                </button>
                            </div>

                            <p
                                v-if="form.errors.password"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Confirm password -->
                        <div class="space-y-2">
                            <Label
                                for="password_confirmation"
                                class="text-sm font-semibold text-[#0F172A]"
                            >
                                Confirm new password
                            </Label>

                            <div class="relative">
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    name="password_confirmation"
                                    autocomplete="new-password"
                                    placeholder="Enter your new password again"
                                    required
                                    :disabled="form.processing"
                                    :aria-invalid="!!form.errors.password_confirmation"
                                    class="h-12 rounded-xl border-slate-300 px-4 pr-12 shadow-none placeholder:text-slate-400 focus-visible:border-[#00695C] focus-visible:ring-[#00695C]/20"
                                />

                                <button
                                    type="button"
                                    :aria-label="showPasswordConfirmation ? 'Hide confirmation password' : 'Show confirmation password'"
                                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 transition hover:text-[#00695C]"
                                >
                                    <EyeOff
                                        v-if="showPasswordConfirmation"
                                        class="h-5 w-5"
                                    />
                                    <Eye
                                        v-else
                                        class="h-5 w-5"
                                    />
                                </button>
                            </div>

                            <p
                                v-if="form.errors.password_confirmation"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.password_confirmation }}
                            </p>
                        </div>

                        <!-- Submit -->
                        <Button
                            type="submit"
                            class="h-12 w-full rounded-xl bg-[#00695C] font-semibold text-white transition hover:bg-[#00574D] focus-visible:ring-[#00695C] disabled:cursor-not-allowed disabled:opacity-70"
                            :disabled="form.processing"
                        >
                            <LoaderCircle
                                v-if="form.processing"
                                class="mr-2 h-4 w-4 animate-spin"
                            />

                            <span>
                                {{
                                    form.processing
                                        ? 'Resetting password...'
                                        : 'Reset password'
                                }}
                            </span>

                            <ArrowRight
                                v-if="!form.processing"
                                class="ml-2 h-4 w-4"
                            />
                        </Button>
                    </form>

                    <!-- Login link -->
                    <div class="mt-8 border-t border-slate-200 pt-6">
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-[#00695C]"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Back to log in
                        </Link>
                    </div>

                    <p class="mt-8 text-center text-xs leading-5 text-slate-400">
                        Secure access, built into quickFolio.
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>
