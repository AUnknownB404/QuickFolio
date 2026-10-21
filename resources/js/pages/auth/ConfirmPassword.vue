<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LoaderCircle, LockKeyhole, ArrowLeft, ArrowRight } from 'lucide-vue-next'

const showPassword = ref(false)

const form = useForm({
    password: '',
})

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    })
}
</script>

<template>
    <Head title="Confirm Password — quickFolio" />

    <main class="min-h-screen bg-white font-sans text-[#0F172A]">
        <div class="grid min-h-screen lg:grid-cols-2">

            <!-- LEFT SIDE -->
            <section class="relative hidden overflow-hidden bg-[#F4F7F7] lg:flex lg:flex-col px-12 py-12">

                <!-- Logo -->
                <Link href="/" class="flex items-center gap-3">
                    <img src="/Logo.svg" alt="" class="h-12 w-12 shrink-0 object-contain" />

                    <span class="text-3xl font-bold">
                        quick<span class="text-[#00695C]">Folio</span>
                    </span>
                </Link>

                <div class="relative z-10 mt-16 max-w-xl">
                    <p class="text-xs uppercase tracking-[0.25em] font-semibold text-slate-500">
                        Secure Access
                    </p>

                    <h1 class="mt-6 text-5xl font-bold leading-tight">
                        One quick
                        <br />
                        security check.
                    </h1>

                    <p class="mt-6 text-lg leading-8 text-slate-600">
                        We need to verify it's really you before allowing access
                        to this protected area of your account.
                    </p>

                    <div class="mt-10 space-y-6">

                        <div class="flex gap-4">
                            <div class="h-12 w-12 rounded-xl bg-[#DCEDE9] flex items-center justify-center text-[#00695C]">
                                <LockKeyhole class="h-6 w-6" />
                            </div>

                            <div>
                                <h3 class="font-semibold">Identity verification</h3>
                                <p class="text-sm text-slate-500 mt-1">
                                    Your password confirms it's really you.
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div class="h-12 w-12 rounded-xl bg-[#DCEDE9] flex items-center justify-center text-[#00695C]">
                                <ArrowRight class="h-6 w-6" />
                            </div>

                            <div>
                                <h3 class="font-semibold">Continue safely</h3>
                                <p class="text-sm text-slate-500 mt-1">
                                    Once verified, you'll continue instantly.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Floating image placeholder -->
                <div class="absolute bottom-10 right-0 w-[60%] translate-x-[8%]">
                    <img
                        src=""
                        alt="Security illustration"
                        class="w-full portfolio-float"
                    />
                </div>

            </section>

            <!-- RIGHT SIDE -->
            <section class="flex items-center justify-center px-6 py-10 sm:px-10 lg:px-14">

                <div class="w-full max-w-md">

                    <!-- Mobile Logo -->
                    <Link href="/" class="flex items-center justify-center gap-2 mb-10 lg:hidden">
                        <img src="/Logo.svg" alt="" class="h-10 w-10 shrink-0 object-contain" />

                        <span class="text-2xl font-bold">
                            quick<span class="text-[#00695C]">Folio</span>
                        </span>
                    </Link>

                    <div class="mb-8">

                        <div class="hidden lg:flex items-center gap-2 mb-8">
                            <img src="/Logo.svg" alt="" class="h-10 w-10 shrink-0 object-contain" />

                            <span class="text-2xl font-bold">
                                quick<span class="text-[#00695C]">Folio</span>
                            </span>
                        </div>

                        <h2 class="text-3xl font-bold">
                            Confirm password
                        </h2>

                        <p class="mt-3 text-slate-500 leading-6">
                            Enter your current password to continue.
                        </p>

                    </div>

                    <form @submit.prevent="submit" class="space-y-5">

                        <!-- Password -->
                        <div>

                            <Label for="password" class="mb-2 block">
                                Current password
                            </Label>

                            <div class="relative">

                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                    autofocus
                                    class="h-12 rounded-xl border-slate-300 pr-12 focus-visible:border-[#00695C] focus-visible:ring-[#00695C]/20"
                                />

                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-[#00695C] font-medium"
                                >
                                    {{ showPassword ? 'Hide' : 'Show' }}
                                </button>

                            </div>

                            <p
                                v-if="form.errors.password"
                                class="text-sm text-red-600 mt-2"
                            >
                                {{ form.errors.password }}
                            </p>

                        </div>

                        <!-- Submit -->
                        <Button
                            class="w-full h-12 rounded-xl bg-[#00695C] hover:bg-[#00574B] text-white font-semibold"
                            :disabled="form.processing"
                        >
                            <LoaderCircle
                                v-if="form.processing"
                                class="mr-2 h-4 w-4 animate-spin"
                            />

                            {{ form.processing ? 'Verifying...' : 'Confirm password' }}

                            <ArrowRight
                                v-if="!form.processing"
                                class="ml-2 h-4 w-4"
                            />
                        </Button>

                    </form>

                    <div class="border-t border-slate-200 mt-8 pt-6">

                        <Link
                            href="/dashboard"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-[#00695C]"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Back
                        </Link>

                    </div>

                </div>

            </section>

        </div>
    </main>
</template>

<style scoped>
@keyframes portfolio-float {
    0%,100%{
        transform:translateY(0);
    }
    50%{
        transform:translateY(-12px);
    }
}

.portfolio-float{
    animation:portfolio-float 5s ease-in-out infinite;
}
</style>
