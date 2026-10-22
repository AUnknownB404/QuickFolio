<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { getTemplateComponent } from '@/components/Templates/TemplateRegistry';
import { Head, Link, router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface Template {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    template: Template;
    demoContent: Record<string, any>;
    designSettings: Record<string, any>;
    demoPortfolio: {
        google_form_url: string;
        google_form_name_entry: string;
        google_form_email_entry: string;
        google_form_message_entry: string;
    };
}>();

const isCreatingPortfolio = ref(false);

const useTemplate = () => {
    if (isCreatingPortfolio.value) return;

    isCreatingPortfolio.value = true;
    router.post(
        route('templates.use', { template: props.template.slug }),
        {
            title: `${props.template.name} Portfolio`,
        },
        {
            onFinish: () => {
                isCreatingPortfolio.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="`${template.name} Preview — quickFolio`" />

    <div class="min-h-screen bg-[#F4F7F7]">
        <header class="sticky top-0 z-50 border-b border-[#DCE5E5] bg-white/95 backdrop-blur-xl">
            <div class="mx-auto flex min-h-[68px] max-w-[1800px] items-center justify-between gap-3 px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3 sm:gap-5">
                    <Link :href="route('home')" class="flex shrink-0 items-center">
                        <img src="/logo.svg" alt="quickFolio" class="h-8 w-8 object-contain sm:hidden" />
                        <AppLogo size="sm" class="hidden sm:inline-flex" />
                    </Link>

                    <div class="hidden h-8 w-px bg-[#E2E8E8] sm:block"></div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h1 class="max-w-[180px] truncate text-sm font-semibold text-[#0F172A] sm:max-w-xs">
                                {{ template.name }}
                            </h1>
                            <span
                                class="hidden items-center gap-1.5 rounded-full border border-[#C8E3DD] bg-[#EFF8F5] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-[#00695C] sm:inline-flex"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-[#00695C]"></span>
                                Live preview
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-[#94A3B8]">Preview with sample portfolio content</p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <Link
                        :href="route('templates.index')"
                        class="inline-flex h-10 items-center gap-1.5 rounded-xl px-2.5 text-xs font-semibold text-[#64748B] transition hover:bg-[#F0F5F4] hover:text-[#00695C] sm:px-4 sm:text-sm"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="m15 18-6-6 6-6" />
                        </svg>
                        <span class="hidden sm:inline">Browse templates</span>
                        <span class="sm:hidden">Back</span>
                    </Link>

                    <button
                        type="button"
                        :disabled="isCreatingPortfolio"
                        class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#00695C] px-3.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#00574D] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:px-5 sm:text-sm"
                        @click="useTemplate"
                    >
                        <LoaderCircle v-if="isCreatingPortfolio" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M5 12h14" />
                            <path d="m12 5 7 7-7 7" />
                        </svg>
                        {{ isCreatingPortfolio ? 'Creating portfolio…' : 'Use template' }}
                    </button>
                </div>
            </div>
        </header>

        <div class="border-b border-[#E2E8E8] bg-[#F4F7F7] px-4 py-2.5 sm:px-6">
            <div class="mx-auto flex max-w-[1800px] items-center justify-between gap-3">
                <p class="text-xs leading-5 text-[#64748B]">
                    <span class="font-semibold text-[#334155]">Preview mode.</span>
                    This is sample content. Your portfolio will use your own details.
                </p>
                <span class="hidden shrink-0 items-center gap-1.5 text-xs text-[#64748B] md:inline-flex">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <rect x="3" y="4" width="18" height="16" rx="2" />
                        <path d="M8 20v-4h8v4" />
                    </svg>
                    Full-width preview
                </span>
            </div>
        </div>

        <main id="portfolio-preview" class="min-h-[calc(100vh-110px)] pt-16">
            <component
                :is="getTemplateComponent(template.slug)"
                :content="demoContent"
                :design-settings="designSettings"
                :portfolio="demoPortfolio"
            />
        </main>
    </div>
</template>

<style scoped>
#portfolio-preview :deep(.folio > nav),
#portfolio-preview :deep(.theme > nav.nav),
#portfolio-preview :deep(.ie-navwrap),
#portfolio-preview :deep(header.fixed) {
    top: 68px !important;
}
</style>
