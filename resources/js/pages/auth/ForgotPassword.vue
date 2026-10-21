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
    LoaderCircle,
    Mail,
    ShieldCheck,
} from 'lucide-vue-next'

defineProps<{
    status?: string
}>()

const form = useForm({
    email: '',
})

const emailSent = ref(false)

const submit = () => {
    form.post(route('password.email'), {
        onSuccess: () => {
            emailSent.value = true
        },
    })
}
</script>

<template>
    <Head title="Forgot Password — quickFolio" />

    <main class="min-h-screen bg-white font-sans text-[#0F172A]">
        <div class="grid min-h-screen lg:grid-cols-2">

            <!-- LEFT SIDE -->
            <section
                class="relative hidden overflow-hidden bg-[#F4F7F7] px-12 py-12 lg:flex lg:flex-col xl:px-16"
            >
                <!-- Logo -->
                <Link href="/" class="flex items-center gap-3">
                    <img src="/Logo.svg" alt="" class="h-12 w-12 shrink-0 object-contain" />

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
                        A fresh start
                        <br />
                        is one link away.
                    </h1>

                    <p class="mt-6 max-w-md text-lg leading-8 text-slate-600">
                        Forgot your password? It happens. We'll help you get
                        back to building your portfolio in just a few steps.
                    </p>

                    <!-- Feature 1 -->
                    <div class="mt-12 space-y-7">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]"
                            >
                                <Mail class="h-6 w-6" />
                            </div>

                            <div>
                                <h3 class="font-semibold text-[#0F172A]">
                                    Check your inbox
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500">
                                    We'll send a secure password reset link to
                                    your email address.
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
                                <h3 class="font-semibold text-[#0F172A]">
                                    Your account stays secure
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500">
                                    Only use the reset link sent to your
                                    registered email.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Decorative elements -->
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

            <!-- RIGHT SIDE -->
            <section
                class="flex min-h-screen items-center justify-center px-6 py-10 sm:px-10 lg:px-14"
            >
                <div class="w-full max-w-md">

                    <!-- Mobile logo -->
                    <Link
                        href="/"
                        class="mb-12 flex items-center justify-center gap-3 lg:hidden"
                    >
                        <img src="/Logo.svg" alt="" class="h-10 w-10 shrink-0 object-contain" />

                        <span class="text-2xl font-bold tracking-tight">
                            quick<span class="text-[#00695C]">Folio</span>
                        </span>
                    </Link>

                    <!-- Heading -->
                    <div class="mb-8">
                        <div
                            class="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-[#C8E3DD] bg-[#EFF8F5] text-[#00695C]"
                        >
                            <Mail class="h-6 w-6" />
                        </div>

                        <h2 class="text-3xl font-bold tracking-tight">
                            Forgot password?
                        </h2>

                        <p class="mt-3 text-sm leading-6 text-slate-500">
                            No worries. Enter your registered email address
                            and we'll send you a link to reset your password.
                        </p>
                    </div>

                    <!-- Success message -->
                    <div
                        v-if="status"
                        role="status"
                        class="mb-6 rounded-xl border border-[#C8E3DD] bg-[#EFF8F5] p-4 text-sm leading-6 text-[#00695C]"
                    >
                        {{ status }}
                    </div>

                    <!-- Email form -->
                    <form
                        v-if="!emailSent"
                        @submit.prevent="submit"
                        class="space-y-5"
                    >
                        <div class="space-y-2">
                            <Label
                                for="email"
                                class="text-sm font-semibold text-[#0F172A]"
                            >
                                Email address
                            </Label>

                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                name="email"
                                autocomplete="email"
                                placeholder="you@example.com"
                                required
                                autofocus
                                :disabled="form.processing"
                                :aria-invalid="!!form.errors.email"
                                class="h-12 rounded-xl border-slate-300 px-4 shadow-none placeholder:text-slate-400 focus-visible:border-[#00695C] focus-visible:ring-[#00695C]/20"
                            />

                            <p
                                v-if="form.errors.email"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

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
                                        ? 'Sending reset link...'
                                        : 'Send reset link'
                                }}
                            </span>

                            <ArrowRight
                                v-if="!form.processing"
                                class="ml-2 h-4 w-4"
                            />
                        </Button>
                    </form>

                    <!-- Confirmation state -->
                    <div
                        v-else
                        class="rounded-xl border border-[#C8E3DD] bg-[#EFF8F5] p-5"
                    >
                        <div class="flex items-start gap-3">
                            <ShieldCheck
                                class="mt-0.5 h-5 w-5 shrink-0 text-[#00695C]"
                            />

                            <div>
                                <h3 class="font-semibold text-[#0F172A]">
                                    Check your email
                                </h3>

                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    If the request was accepted, follow the
                                    instructions sent to your email to reset
                                    your password.
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-4 text-sm font-semibold text-[#00695C] hover:text-[#00574D]"
                            @click="emailSent = false"
                        >
                            Try another email
                        </button>
                    </div>

                    <!-- Back to login -->
                    <div class="mt-8 border-t border-slate-200 pt-6">
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-[#00695C]"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Back to log in
                        </Link>
                    </div>

                    <!-- Footer -->
                    <p class="mt-8 text-center text-xs leading-5 text-slate-400">
                        Secure access, built into quickFolio.
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>
