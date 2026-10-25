```vue
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    LoaderCircle,
    Mail,
    MailCheck,
    ShieldCheck,
} from 'lucide-vue-next'

defineProps<{
    status?: string
}>()

const form = useForm({})
const resent = ref(false)

const submit = () => {
    form.post(route('verification.send'), {
        onSuccess: () => {
            resent.value = true
        },
    })
}
</script>

<template>
    <Head title="Verify Email — quickFolio" />

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
                        One Last Step
                    </p>

                    <h1 class="mt-6 text-5xl font-bold leading-tight tracking-tight">
                        Verify your email.
                        <br />
                        Own your identity.
                    </h1>

                    <p class="mt-6 max-w-md text-lg leading-8 text-slate-600">
                        Confirm your email address to secure your account and
                        unlock your quickFolio portfolio-building experience.
                    </p>

                    <!-- Benefits -->
                    <div class="mt-12 space-y-7">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DCEDE9] text-[#00695C]"
                            >
                                <MailCheck class="h-6 w-6" />
                            </div>

                            <div>
                                <h3 class="font-semibold">
                                    Verify in seconds
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500">
                                    Open the verification email and follow its
                                    secure link.
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
                                    A verified email helps protect access to
                                    your account.
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

                    <!-- Icon -->
                    <div
                        class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl border border-[#C8E3DD] bg-[#EFF8F5] text-[#00695C]"
                    >
                        <Mail class="h-7 w-7" />
                    </div>

                    <!-- Heading -->
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold tracking-tight">
                            Verify your email
                        </h2>

                        <p class="mt-3 text-sm leading-6 text-slate-500">
                            We've sent a verification link to the email address
                            you used to register. Open that email and click the
                            link to activate your account.
                        </p>
                    </div>

                    <!-- Verification status -->
                    <div
                        v-if="status === 'verification-link-sent' || resent"
                        role="status"
                        class="mb-6 flex items-start gap-3 rounded-xl border border-[#C8E3DD] bg-[#EFF8F5] p-4"
                    >
                        <CheckCircle2
                            class="mt-0.5 h-5 w-5 shrink-0 text-[#00695C]"
                        />

                        <div>
                            <p class="text-sm font-semibold text-[#00695C]">
                                Verification email requested
                            </p>

                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                If your email is still unverified, a new
                                verification link has been requested. Check
                                your inbox and spam folder.
                            </p>
                        </div>
                    </div>

                    <!-- Resend form -->
                    <form @submit.prevent="submit" class="space-y-5">
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
                                        ? 'Sending verification email...'
                                        : 'Resend verification email'
                                }}
                            </span>

                            <ArrowRight
                                v-if="!form.processing"
                                class="ml-2 h-4 w-4"
                            />
                        </Button>
                    </form>

                    <!-- Helpful note -->
                    <div
                        class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <MailCheck
                                class="mt-0.5 h-5 w-5 shrink-0 text-slate-500"
                            />

                            <p class="text-sm leading-6 text-slate-500">
                                Can't find the email? Check your spam or junk
                                folder before requesting another link.
                            </p>
                        </div>
                    </div>

                    <!-- Logout -->
                    <div class="mt-8 border-t border-slate-200 pt-6">
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-[#00695C]"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Log out of your account
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
