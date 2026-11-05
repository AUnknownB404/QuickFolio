<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle, ArrowRight, ChevronLeft, ChevronRight, Eye, LayoutTemplate, LoaderCircle, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

interface TemplateCategory {
    id: number;
    name: string;
    slug: string;
}

interface TemplateMedia {
    id: number;
    media_path: string;
    media_type: string;
}

interface Template {
    id: number;
    name: string;
    slug: string;
    description: string;
    thumbnail: string;
    category: TemplateCategory;
    media: TemplateMedia[];
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    templates: {
        data: Template[];
        links: PageLink[];
        // Present on Laravel paginator responses; falls back to the current page size.
        total?: number;
    };
    categories: TemplateCategory[];
    filters: {
        search?: string;
        category?: string;
    };
}>();

/* -------------------------------------------------------------------------
 * Finding a template: results update as you type, with feedback while loading
 * ---------------------------------------------------------------------- */

const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');
const isLoading = ref(false);

const hasFilters = computed(() => search.value.trim() !== '' || selectedCategory.value !== '');
const totalCount = computed(() => props.templates.total ?? props.templates.data.length);

let searchTimer: number | undefined;
let lastApplied = JSON.stringify({ search: search.value.trim(), category: selectedCategory.value });

const applyFilters = () => {
    window.clearTimeout(searchTimer);

    const query = { search: search.value.trim() || undefined, category: selectedCategory.value || undefined };
    const signature = JSON.stringify({ search: query.search ?? '', category: query.category ?? '' });
    if (signature === lastApplied) return; // nothing new to ask the server
    lastApplied = signature;

    router.get(route('templates.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['templates', 'filters'],
        onStart: () => {
            isLoading.value = true;
        },
        onFinish: () => {
            isLoading.value = false;
        },
    });
};

watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 300);
});
onBeforeUnmount(() => window.clearTimeout(searchTimer));

const selectCategory = (slug: string) => {
    selectedCategory.value = selectedCategory.value === slug ? '' : slug;
    applyFilters();
};

const clearFilters = () => {
    search.value = '';
    selectedCategory.value = '';
    applyFilters();
};

/* -------------------------------------------------------------------------
 * Pagination labels arrive from Laravel as HTML ("&laquo; Previous")
 * ---------------------------------------------------------------------- */

const plainLabel = (link: PageLink) => link.label.replace(/<[^>]*>/g, '').replace(/&[a-z]+;/gi, '').trim();
const isPrevious = (link: PageLink) => link.label.toLowerCase().includes('previous');
const isNext = (link: PageLink) => link.label.toLowerCase().includes('next');
const pageLabel = (link: PageLink) => (isPrevious(link) ? 'Previous page' : isNext(link) ? 'Next page' : `Page ${plainLabel(link)}`);

/* -------------------------------------------------------------------------
 * Starting a portfolio from a template
 * ---------------------------------------------------------------------- */

const selectedTemplate = ref<Template | null>(null);
const portfolioTitle = ref('');
const isUseModalOpen = ref(false);
const isCreatingPortfolio = ref(false);
const createError = ref<string | null>(null);

const openUseModal = async (template: Template) => {
    selectedTemplate.value = template;
    portfolioTitle.value = `My ${template.name} Portfolio`;
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
    if (isCreatingPortfolio.value || !selectedTemplate.value || !title) return;

    isCreatingPortfolio.value = true;
    createError.value = null;
    router.post(
        route('templates.use', { template: selectedTemplate.value.slug }),
        { title },
        {
            onError: (errors) => {
                // Previously a failed create left the dialog silent.
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
    <Head title="Choose a template — quickFolio" />

    <div class="min-h-screen bg-[#F4F7F7] text-[#0F172A] dark:bg-slate-950 dark:text-slate-100">
        <header class="sticky top-0 z-40 border-b border-[#E2E8E8] bg-white/95 backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8 lg:px-10">
                <Link :href="route('home')" class="flex items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40" aria-label="quickFolio home">
                    <AppLogo size="md" />
                </Link>

                <Link
                    :href="route('dashboard')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#DCE5E5] bg-white px-4 text-sm font-semibold text-[#334155] transition hover:border-[#00695C] hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                >
                    My portfolios
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
            </div>
        </header>

        <!-- Compact intro: the gallery, not the pitch, is the page -->
        <section class="border-b border-[#E2E8E8] bg-white dark:border-slate-700 dark:bg-slate-900">
            <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10 lg:px-10">
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Choose a template</h1>
                <p class="mt-2 max-w-2xl text-base leading-7 text-[#64748B] dark:text-slate-300">
                    Pick a starting point, then make it yours in the editor.
                </p>

                <form class="mt-6 max-w-xl" role="search" @submit.prevent="applyFilters">
                    <label for="template-search" class="sr-only">Search templates</label>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-4 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-[#94A3B8] dark:text-slate-400" aria-hidden="true" />
                        <Input
                            id="template-search"
                            v-model="search"
                            type="search"
                            placeholder="Search templates"
                            autocomplete="off"
                            class="h-12 rounded-xl border-[#DCE5E5] bg-white pl-11 pr-11 text-sm text-slate-900 shadow-none placeholder:text-[#94A3B8] focus-visible:ring-[#00695C] focus-visible:ring-offset-0 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-400 [&::-webkit-search-cancel-button]:hidden"
                        />
                        <button
                            v-if="search"
                            type="button"
                            aria-label="Clear search"
                            class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-[#94A3B8] hover:bg-[#F1F5F4] hover:text-[#334155] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:hover:bg-slate-700"
                            @click="search = ''"
                        >
                            <X class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>
                </form>

                <div v-if="categories.length" class="mt-4 flex flex-wrap gap-2" role="group" aria-label="Filter by category">
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                        :class="selectedCategory === ''
                            ? 'border-[#00695C] bg-[#00695C] text-white'
                            : 'border-[#DCE5E5] bg-white text-[#475569] hover:border-[#00695C] hover:text-[#00695C] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300'"
                        :aria-pressed="selectedCategory === ''"
                        @click="selectCategory('')"
                    >
                        All
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        type="button"
                        class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                        :class="selectedCategory === cat.slug
                            ? 'border-[#00695C] bg-[#00695C] text-white'
                            : 'border-[#DCE5E5] bg-white text-[#475569] hover:border-[#00695C] hover:text-[#00695C] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300'"
                        :aria-pressed="selectedCategory === cat.slug"
                        @click="selectCategory(cat.slug)"
                    >
                        {{ cat.name }}
                    </button>
                </div>
            </div>
        </section>

        <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10 lg:px-10">
            <div class="flex min-h-8 items-center justify-between gap-4">
                <p class="text-sm text-[#64748B] dark:text-slate-300" role="status" aria-live="polite">
                    <span class="font-semibold text-[#0F172A] dark:text-slate-100">{{ totalCount }}</span>
                    {{ totalCount === 1 ? 'template' : 'templates' }}
                    <template v-if="hasFilters"> match your filters</template>
                </p>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-sm font-semibold text-[#00695C] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:text-teal-300"
                    @click="clearFilters"
                >
                    <X class="h-3.5 w-3.5" aria-hidden="true" />
                    Clear filters
                </button>
            </div>

            <!-- Nothing matched -->
            <div v-if="templates.data.length === 0" class="flex flex-col items-center py-20 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E4F2EE] text-[#00695C] dark:bg-teal-950/60">
                    <Search class="h-6 w-6" aria-hidden="true" />
                </div>
                <h2 class="mt-5 text-lg font-bold">
                    {{ search.trim() ? `No templates match “${search.trim()}”` : 'No templates here yet' }}
                </h2>
                <p class="mt-2 max-w-sm text-sm leading-6 text-[#64748B] dark:text-slate-300">
                    Try a different word or category, or clear the filters to see everything.
                </p>
                <Button
                    v-if="hasFilters"
                    variant="outline"
                    class="mt-5 rounded-xl border-[#C8D8D5] bg-white text-[#00695C] hover:bg-[#EFF8F5] dark:border-slate-600 dark:bg-slate-900 dark:text-teal-300"
                    @click="clearFilters"
                >
                    View all templates
                </Button>
            </div>

            <!-- Gallery (dims while new results load) -->
            <ul
                v-else
                class="mt-5 grid grid-cols-1 gap-x-6 gap-y-8 transition-opacity duration-150 sm:grid-cols-2 lg:grid-cols-3"
                :class="isLoading ? 'opacity-60' : ''"
                :aria-busy="isLoading"
            >
                <li v-for="template in templates.data" :key="template.id" class="min-w-0">
                    <article class="group flex h-full flex-col overflow-hidden rounded-2xl border border-[#E2E8E8] bg-white transition duration-200 hover:border-[#C8D8D5] hover:shadow-[0_12px_32px_rgba(15,23,42,0.06)] motion-safe:hover:-translate-y-0.5 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600">
                        <!-- Big, forgiving target; the title below is the accessible link -->
                        <Link
                            :href="route('templates.show', { template: template.slug })"
                            class="relative block aspect-[4/3] overflow-hidden bg-[#E8EEEE] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#00695C] dark:bg-slate-800"
                            tabindex="-1"
                            aria-hidden="true"
                        >
                            <img
                                v-if="template.thumbnail"
                                :src="template.thumbnail"
                                alt=""
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-500 motion-safe:group-hover:scale-[1.03]"
                                decoding="async"
                            />
                            <div v-else class="flex h-full items-center justify-center">
                                <span class="grid h-14 w-14 place-items-center rounded-2xl border border-[#CFE0DB] bg-white text-[#00695C] shadow-sm dark:border-slate-600 dark:bg-slate-900">
                                    <LayoutTemplate class="h-7 w-7" />
                                </span>
                            </div>
                            <span class="absolute left-3 top-3 rounded-lg border border-white/70 bg-white/95 px-3 py-1.5 text-xs font-semibold text-[#334155] shadow-sm dark:border-slate-700 dark:bg-slate-900/95 dark:text-slate-200">
                                {{ template.category?.name || 'Portfolio' }}
                            </span>
                        </Link>

                        <div class="flex flex-1 flex-col p-5">
                            <h2 class="text-lg font-bold tracking-tight">
                                <Link
                                    :href="route('templates.show', { template: template.slug })"
                                    class="rounded hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:hover:text-teal-300"
                                >
                                    {{ template.name }}
                                </Link>
                            </h2>
                            <p class="mt-2 line-clamp-2 min-h-10 text-sm leading-5 text-[#64748B] dark:text-slate-300">
                                {{ template.description }}
                            </p>

                            <div class="mt-auto grid grid-cols-2 gap-2 pt-5">
                                <Link
                                    :href="route('templates.show', { template: template.slug })"
                                    :aria-label="`Preview ${template.name}`"
                                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#DCE5E5] bg-white text-sm font-semibold text-[#334155] transition hover:border-[#00695C] hover:text-[#00695C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                                >
                                    <Eye class="h-4 w-4" aria-hidden="true" />
                                    Preview
                                </Link>
                                <Button
                                    class="h-10 rounded-xl bg-[#00695C] text-sm font-semibold text-white hover:bg-[#00574D]"
                                    :aria-label="`Use ${template.name} template`"
                                    @click="openUseModal(template)"
                                >
                                    Use template
                                </Button>
                            </div>
                        </div>
                    </article>
                </li>
            </ul>

            <!-- Pagination: new pages open at the top, not where the last one ended -->
            <nav
                v-if="templates.links?.length > 3"
                aria-label="Template pagination"
                class="mt-12 flex flex-wrap items-center justify-center gap-2"
            >
                <template v-for="(link, index) in templates.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        :aria-label="pageLabel(link)"
                        :aria-current="link.active ? 'page' : undefined"
                        class="inline-flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                        :class="link.active
                            ? 'border-[#00695C] bg-[#00695C] text-white'
                            : 'border-[#DCE5E5] bg-white text-[#475569] hover:border-[#00695C] hover:text-[#00695C] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300'"
                    >
                        <ChevronLeft v-if="isPrevious(link)" class="h-4 w-4" aria-hidden="true" />
                        <ChevronRight v-else-if="isNext(link)" class="h-4 w-4" aria-hidden="true" />
                        <template v-else>{{ plainLabel(link) }}</template>
                    </Link>

                    <span
                        v-else-if="!isPrevious(link) && !isNext(link)"
                        class="inline-flex h-10 min-w-10 items-center justify-center text-sm text-[#94A3B8] dark:text-slate-500"
                        aria-hidden="true"
                    >
                        {{ plainLabel(link) }}
                    </span>
                </template>
            </nav>
        </main>

        <footer class="border-t border-[#E2E8E8] bg-white dark:border-slate-700 dark:bg-slate-900">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 text-sm text-[#94A3B8] dark:text-slate-400 sm:px-8 lg:px-10">
                <Link :href="route('home')" class="flex items-center" aria-label="quickFolio home">
                    <AppLogo size="sm" />
                </Link>
                <p>© {{ new Date().getFullYear() }} quickFolio</p>
            </div>
        </footer>

        <!-- Name it, then straight into the editor -->
        <Dialog :open="isUseModalOpen" @update:open="onUseModalChange">
            <DialogContent class="w-[calc(100%-1rem)] rounded-2xl border-[#E2E8E8] bg-white dark:border-slate-700 dark:bg-slate-900 sm:max-w-md">
                <DialogHeader class="text-left">
                    <div class="mb-1 flex items-center gap-3">
                        <div class="h-14 w-20 shrink-0 overflow-hidden rounded-lg border border-[#E2E8E8] bg-[#E8EEEE] dark:border-slate-700 dark:bg-slate-800">
                            <img v-if="selectedTemplate?.thumbnail" :src="selectedTemplate.thumbnail" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async" />
                            <div v-else class="grid h-full place-items-center text-[#00695C]"><LayoutTemplate class="h-5 w-5" /></div>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ selectedTemplate?.name }}</p>
                            <p class="truncate text-xs text-[#64748B] dark:text-slate-400">{{ selectedTemplate?.category?.name || 'Portfolio' }} template</p>
                        </div>
                    </div>

                    <DialogTitle class="text-xl font-bold tracking-tight">Name your portfolio</DialogTitle>
                    <DialogDescription class="leading-6 text-[#64748B] dark:text-slate-300">
                        This is just for you, to tell your portfolios apart. Next you’ll add your content in the editor.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-2 pt-2" @submit.prevent="createPortfolio">
                    <Label for="portfolio_title" class="text-sm font-semibold text-[#334155] dark:text-slate-200">Portfolio name</Label>
                    <Input
                        id="portfolio_title"
                        v-model="portfolioTitle"
                        required
                        maxlength="120"
                        placeholder="e.g. My Developer Portfolio"
                        :aria-invalid="createError ? 'true' : undefined"
                        :aria-describedby="createError ? 'create-error' : undefined"
                        class="h-11 rounded-xl border-[#DCE5E5] bg-white text-slate-900 shadow-none placeholder:text-slate-400 focus-visible:ring-[#00695C] focus-visible:ring-offset-0 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                    />
                    <p v-if="createError" id="create-error" class="flex items-start gap-2 text-sm text-red-600 dark:text-red-400" role="alert">
                        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ createError }}
                    </p>

                    <DialogFooter class="gap-2 pt-5">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isCreatingPortfolio"
                            class="rounded-xl border-[#DCE5E5] bg-white dark:border-slate-600 dark:bg-slate-900"
                            @click="isUseModalOpen = false"
                        >
                            Cancel
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

<style>
/* Keep native input rendering, autofill and date/text controls readable in either theme. */
input, textarea, select {
    color-scheme: light;
}
.dark input,
.dark textarea,
.dark select {
    color-scheme: dark;
}
input::placeholder,
textarea::placeholder {
    opacity: 1;
}
</style>
