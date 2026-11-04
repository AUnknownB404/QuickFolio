<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AdditionalPortfolioFields from '@/components/Templates/AdditionalPortfolioFields.vue';
import { getTemplateComponent } from '@/components/Templates/TemplateRegistry';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle, ArrowRight, ChevronLeft, LayoutTemplate, LoaderCircle } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface Template {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    template: Template;
    demoContent: Record<string, any>;
    designSettings: Record<string, any>;
    // Optional here: older routes to this page don't send it.
    demoPortfolio?: {
        google_form_url: string;
        google_form_name_entry: string;
        google_form_email_entry: string;
        google_form_message_entry: string;
    };
}>();

// A template without a registered component used to render a silent blank page.
const previewComponent = computed(() => getTemplateComponent(props.template.slug));

/* -------------------------------------------------------------------------
 * Using the template — same flow as the gallery: name it, then start editing
 * ---------------------------------------------------------------------- */

const isUseModalOpen = ref(false);
const isCreatingPortfolio = ref(false);
const portfolioTitle = ref('');
const createError = ref<string | null>(null);

const openUseModal = async () => {
    portfolioTitle.value = `My ${props.template.name} Portfolio`;
    createError.value = null;
    isUseModalOpen.value = true;

    // Pre-select the suggestion so typing replaces it, and Enter accepts it.
    await nextTick();
    window.setTimeout(() => {
        const input = document.getElementById('portfolio_title') as HTMLInputElement | null;
        input?.focus();
        input?.select();
    }, 50);
};

const onUseModalChange = (open: boolean) => {
    if (!open && isCreatingPortfolio.value) return;
    isUseModalOpen.value = open;
};

const createPortfolio = () => {
    const title = portfolioTitle.value.trim();
    if (isCreatingPortfolio.value || !title) return;

    isCreatingPortfolio.value = true;
    createError.value = null;
    router.post(
        route('templates.use', { template: props.template.slug }),
        { title },
        {
            onError: (errors) => {
                createError.value = errors.title || Object.values(errors)[0] || 'Something went wrong. Please try again.';
            },
            onFinish: () => {
                isCreatingPortfolio.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="`Preview ${template.name} — quickFolio`" />

    <div class="min-h-screen bg-[#F4F7F7]">
        <a
            href="#portfolio-preview"
            class="sr-only rounded-lg bg-white px-3 py-2 text-sm font-semibold text-[#00695C] shadow focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-[60]"
        >
            Skip to preview
        </a>

        <!-- One bar, fixed height: the preview's own fixed navs are offset by exactly this -->
        <header class="preview-bar sticky top-0 z-50 border-b border-[#DCE5E5] bg-white/95 backdrop-blur-xl">
            <div class="mx-auto flex h-full max-w-[1800px] items-center justify-between gap-3 px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-2 sm:gap-4">
                    <Link :href="route('home')" class="hidden shrink-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 sm:flex" aria-label="quickFolio home">
                        <AppLogo size="sm" />
                    </Link>
                    <div class="hidden h-8 w-px bg-[#E2E8E8] sm:block" aria-hidden="true"></div>

                    <Link
                        :href="route('templates.index')"
                        class="inline-flex h-10 shrink-0 items-center gap-1 rounded-xl px-2 text-sm font-semibold text-[#64748B] transition hover:bg-[#F0F5F4] hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 sm:px-3"
                        aria-label="Back to all templates"
                    >
                        <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                        <span class="hidden md:inline">All templates</span>
                    </Link>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="max-w-[150px] truncate text-sm font-semibold text-[#0F172A] sm:max-w-xs">{{ template.name }}</p>
                            <span class="hidden shrink-0 rounded-full border border-[#E2E8E8] bg-[#F4F7F7] px-2.5 py-0.5 text-[11px] font-semibold text-[#64748B] sm:inline-flex">
                                Sample content
                            </span>
                        </div>
                        <p class="mt-0.5 hidden text-xs text-[#94A3B8] sm:block">Your own details will replace this.</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl bg-[#00695C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00574D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C] focus-visible:ring-offset-2 sm:px-5"
                    @click="openUseModal"
                >
                    Use this template
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        </header>

        <main id="portfolio-preview" class="preview-main pt-16" tabindex="-1" :aria-label="`${template.name} template preview with sample content`">
            <template v-if="previewComponent">
                <component
                    :is="previewComponent"
                    :content="demoContent"
                    :design-settings="designSettings"
                    v-bind="demoPortfolio ? { portfolio: demoPortfolio } : {}"
                />
                <AdditionalPortfolioFields :content="demoContent" />
            </template>

            <div v-else class="mx-auto flex max-w-md flex-col items-center px-6 py-24 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E4F2EE] text-[#00695C]">
                    <LayoutTemplate class="h-6 w-6" aria-hidden="true" />
                </div>
                <h1 class="mt-5 text-lg font-bold text-[#0F172A]">This preview isn’t available</h1>
                <p class="mt-2 text-sm leading-6 text-[#64748B]">
                    We can’t show {{ template.name }} right now. You can still start from it, or choose another template.
                </p>
                <Link
                    :href="route('templates.index')"
                    class="mt-5 inline-flex h-10 items-center rounded-xl border border-[#C8D8D5] bg-white px-4 text-sm font-semibold text-[#00695C] hover:bg-[#EFF8F5] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                >
                    Browse templates
                </Link>
            </div>
        </main>

        <!-- Name it, then straight into the editor -->
        <Dialog :open="isUseModalOpen" @update:open="onUseModalChange">
            <DialogContent class="w-[calc(100%-1rem)] rounded-2xl border-[#E2E8E8] bg-white sm:max-w-md">
                <DialogHeader class="text-left">
                    <DialogTitle class="text-xl font-bold tracking-tight text-[#0F172A]">Name your portfolio</DialogTitle>
                    <DialogDescription class="leading-6 text-[#64748B]">
                        You’re starting from
                        <span class="font-semibold text-[#0F172A]">{{ template.name }}</span>.
                        The name is just for you, to tell your portfolios apart. Next you’ll add your content in the editor.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-2 pt-2" @submit.prevent="createPortfolio">
                    <Label for="portfolio_title" class="text-sm font-semibold text-[#334155]">Portfolio name</Label>
                    <Input
                        id="portfolio_title"
                        v-model="portfolioTitle"
                        required
                        maxlength="120"
                        placeholder="e.g. My Developer Portfolio"
                        :aria-invalid="createError ? 'true' : undefined"
                        :aria-describedby="createError ? 'create-error' : undefined"
                        class="h-11 rounded-xl border-[#DCE5E5] bg-white text-slate-900 shadow-none placeholder:text-slate-400 focus-visible:ring-[#00695C] focus-visible:ring-offset-0"
                    />
                    <p v-if="createError" id="create-error" class="flex items-start gap-2 text-sm text-red-600" role="alert">
                        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ createError }}
                    </p>

                    <DialogFooter class="gap-2 pt-5">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isCreatingPortfolio"
                            class="rounded-xl border-[#DCE5E5] bg-white"
                            @click="isUseModalOpen = false"
                        >
                            Keep browsing
                        </Button>
                        <Button
                            type="submit"
                            :disabled="isCreatingPortfolio || !portfolioTitle.trim()"
                            class="rounded-xl bg-[#00695C] font-semibold text-white hover:bg-[#00574D] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <LoaderCircle v-if="isCreatingPortfolio" class="mr-1.5 h-4 w-4 animate-spin" aria-hidden="true" />
                            {{ isCreatingPortfolio ? 'Creating…' : 'Create & start editing' }}
                            <ArrowRight v-if="!isCreatingPortfolio" class="ml-1.5 h-4 w-4" aria-hidden="true" />
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
/* Single source of truth for the bar height. Templates bring their own fixed navs;
   they are pushed down by exactly this much so nothing hides underneath our bar. */
.preview-bar {
    --preview-bar: 68px;
    height: var(--preview-bar);
}
.preview-main {
    min-height: calc(100vh - 68px);
    outline: none;
}

#portfolio-preview :deep(.folio > nav),
#portfolio-preview :deep(.theme > nav.nav),
#portfolio-preview :deep(.ie-navwrap),
#portfolio-preview :deep(header.fixed) {
    top: 68px !important;
}
</style>
