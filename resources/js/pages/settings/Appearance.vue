<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SettingsLayout from '@/components/SettingsLayout.vue';
</script>

<template>
    <Head title="Appearance — quickFolio" />

    <SettingsLayout>
        <section class="rounded-2xl border border-[#E1E8E7] bg-white p-5 dark:border-slate-800 dark:bg-[#0F172A] sm:p-7" aria-labelledby="theme-heading">
            <h2 id="theme-heading" class="text-lg font-bold">Theme</h2>
            <p class="mt-1 text-sm leading-5 text-[#64748B] dark:text-slate-400">
                Choose how quickFolio looks. Your choice is remembered on this device.
            </p>

            <!-- The page itself now follows the choice, so you see the result immediately -->
            <div class="mt-6">
                <AppearanceTabs />
            </div>

            <dl class="mt-6 space-y-2 border-t border-[#EDF1F0] pt-5 text-sm leading-5 dark:border-slate-800">
                <div class="flex gap-2">
                    <dt class="w-16 shrink-0 font-semibold">Light</dt>
                    <dd class="text-[#64748B] dark:text-slate-400">Bright backgrounds, best in well-lit rooms.</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-16 shrink-0 font-semibold">Dark</dt>
                    <dd class="text-[#64748B] dark:text-slate-400">Easier on the eyes in low light.</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-16 shrink-0 font-semibold">System</dt>
                    <dd class="text-[#64748B] dark:text-slate-400">Matches your device and switches automatically, for example at night.</dd>
                </div>
            </dl>
        </section>
    </SettingsLayout>
</template>
