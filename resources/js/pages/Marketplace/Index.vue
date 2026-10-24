```vue
<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
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

const props = defineProps<{
    templates: {
        data: Template[];
        links: any[];
    };
    categories: TemplateCategory[];
    filters: {
        search?: string;
        category?: string;
    };
}>();

const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');
const selectedTemplate = ref<Template | null>(null);
const portfolioTitle = ref('');
const isUseModalOpen = ref(false);
const isCreatingPortfolio = ref(false);

const handleSearch = () => {
    router.get(
        route('templates.index'),
        {
            search: search.value || undefined,
            category: selectedCategory.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const filterByCategory = (slug: string) => {
    selectedCategory.value =
        selectedCategory.value === slug ? '' : slug;

    handleSearch();
};

const openUseModal = (template: Template) => {
    selectedTemplate.value = template;
    portfolioTitle.value = `My ${template.name} Portfolio`;
    isUseModalOpen.value = true;
};

const createPortfolio = () => {
    if (isCreatingPortfolio.value || !selectedTemplate.value || !portfolioTitle.value.trim()) return;

    isCreatingPortfolio.value = true;
    router.post(
        route('templates.use', { template: selectedTemplate.value.slug }),
        {
            title: portfolioTitle.value.trim(),
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
    <Head title="Explore Templates — quickFolio" />

    <div class="min-h-screen bg-[#F4F7F7] text-[#0F172A]">
        <!-- Navigation -->
        <header
            class="sticky top-0 z-40 border-b border-[#E2E8E8] bg-white/95 backdrop-blur"
        >
            <div
                class="mx-auto flex h-[72px] max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-10"
            >
                <Link :href="route('home')" class="flex items-center">
                    <AppLogo size="md" />
                </Link>

                <Link
                    :href="route('dashboard')"
                    class="inline-flex items-center justify-center rounded-xl bg-[#00695C] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#00574D]"
                >
                    Dashboard
                    <span class="ml-2" aria-hidden="true">→</span>
                </Link>
            </div>
        </header>

        <!-- Hero -->
        <section class="border-b border-[#E2E8E8] bg-white">
            <div
                class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20 lg:px-10 lg:py-24"
            >
                <div class="max-w-3xl">
                    <div
                        class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#C8E3DD] bg-[#EFF8F5] px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.14em] text-[#00695C]"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-[#00695C]"></span>
                        The template collection
                    </div>

                    <h1
                        class="max-w-3xl text-4xl font-bold leading-[1.12] tracking-tight text-[#0F172A] sm:text-5xl lg:text-6xl"
                    >
                        Your next chapter
                        <span class="text-[#00695C]">starts here.</span>
                    </h1>

                    <p
                        class="mt-6 max-w-2xl text-base leading-7 text-[#64748B] sm:text-lg sm:leading-8"
                    >
                        Find a design that feels like you. Choose a template,
                        make it yours, and publish a portfolio you’re proud to
                        share — no coding required.
                    </p>

                    <!-- Search -->
                    <form
                        class="mt-9 flex max-w-2xl flex-col gap-3 sm:flex-row"
                        @submit.prevent="handleSearch"
                    >
                        <div class="relative flex-1">
                            <span
                                class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#94A3B8]"
                                aria-hidden="true"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="19"
                                    height="19"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <circle cx="11" cy="11" r="8" />
                                    <path d="m21 21-4.35-4.35" />
                                </svg>
                            </span>

                            <Input
                                v-model="search"
                                placeholder="Search by name, style, or profession..."
                                class="h-12 rounded-xl border-[#DCE5E5] bg-white pl-11 text-sm shadow-none placeholder:text-[#94A3B8] focus-visible:ring-[#00695C]"
                            />
                        </div>

                        <Button
                            type="submit"
                            class="h-12 rounded-xl bg-[#00695C] px-7 font-semibold text-white hover:bg-[#00574D]"
                        >
                            Search templates
                        </Button>
                    </form>

                    <p class="mt-4 text-sm text-[#94A3B8]">
                        Designed for developers, designers, creatives, and
                        professionals.
                    </p>
                </div>
            </div>
        </section>

        <!-- Template Collection -->
        <main
            id="template-gallery"
            class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16 lg:px-10"
        >
            <!-- Heading and result context -->
            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-[0.18em] text-[#00695C]"
                    >
                        Find your style
                    </p>

                    <h2
                        class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl"
                    >
                        Explore templates
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-[#64748B]">
                        A thoughtful starting point for your personal brand.
                    </p>
                </div>

                <button
                    v-if="search || selectedCategory"
                    type="button"
                    class="w-fit text-sm font-semibold text-[#00695C] hover:underline"
                    @click="
                        search = '';
                        selectedCategory = '';
                        handleSearch();
                    "
                >
                    Clear filters
                    <span aria-hidden="true">×</span>
                </button>
            </div>

            <!-- Category filters -->
            <div class="mt-7 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full border px-4 py-2.5 text-sm font-medium transition"
                    :class="
                        selectedCategory === ''
                            ? 'border-[#00695C] bg-[#00695C] text-white'
                            : 'border-[#DCE5E5] bg-white text-[#475569] hover:border-[#00695C] hover:text-[#00695C]'
                    "
                    @click="filterByCategory('')"
                >
                    All templates
                </button>

                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    type="button"
                    class="rounded-full border px-4 py-2.5 text-sm font-medium transition"
                    :class="
                        selectedCategory === cat.slug
                            ? 'border-[#00695C] bg-[#00695C] text-white'
                            : 'border-[#DCE5E5] bg-white text-[#475569] hover:border-[#00695C] hover:text-[#00695C]'
                    "
                    @click="filterByCategory(cat.slug)"
                >
                    {{ cat.name }}
                </button>
            </div>

            <!-- Results count -->
            <div
                class="mt-9 flex items-center justify-between border-b border-[#E2E8E8] pb-4"
            >
                <p class="text-sm text-[#64748B]">
                    <span class="font-semibold text-[#0F172A]">
                        {{ templates.data.length }}
                    </span>
                    {{ templates.data.length === 1 ? 'template' : 'templates' }}
                    shown
                </p>

                <span
                    class="hidden text-xs font-medium text-[#94A3B8] sm:inline"
                >
                    Made for your next big thing
                </span>
            </div>

            <!-- Empty state -->
            <div
                v-if="templates.data.length === 0"
                class="flex flex-col items-center py-20 text-center"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E4F2EE] text-[#00695C]"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="25"
                        height="25"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.35-4.35" />
                    </svg>
                </div>

                <h3 class="mt-5 text-lg font-bold text-[#0F172A]">
                    No templates found
                </h3>

                <p class="mt-2 max-w-sm text-sm leading-6 text-[#64748B]">
                    Try a different search or category to find the right design
                    for your portfolio.
                </p>

                <Button
                    variant="outline"
                    class="mt-5 rounded-xl border-[#C8D8D5] bg-white text-[#00695C] hover:bg-[#EFF8F5]"
                    @click="
                        search = '';
                        selectedCategory = '';
                        handleSearch();
                    "
                >
                    View all templates
                </Button>
            </div>

            <!-- Template cards -->
            <div
                v-else
                class="mt-6 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="template in templates.data"
                    :key="template.id"
                    class="group flex min-w-0 flex-col overflow-hidden rounded-2xl border border-[#E2E8E8] bg-white shadow-none transition duration-300 hover:-translate-y-1 hover:border-[#C8D8D5] hover:shadow-[0_12px_32px_rgba(15,23,42,0.06)]"
                >
                    <!-- Preview image -->
                    <div
                        class="relative aspect-[4/3] overflow-hidden bg-[#E8EEEE]"
                    >
                        <img
                            :src="
                                template.thumbnail ||
                                'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?auto=format&fit=crop&w=900&q=85'
                            "
                            :alt="`${template.name} portfolio template preview`"
                            loading="lazy"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                        />

                        <div
                            class="absolute left-3 top-3 rounded-lg border border-white/70 bg-white/95 px-3 py-1.5 text-xs font-semibold text-[#334155] shadow-sm"
                        >
                            {{ template.category?.name || 'Portfolio' }}
                        </div>

                        <Link
                            :href="route('templates.show', { template: template.slug })"
                            :aria-label="`View ${template.name} template details`"
                            class="absolute inset-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#00695C]"
                        />
                    </div>

                    <CardHeader class="space-y-2 px-5 pb-3 pt-5">
                        <CardTitle
                            class="text-lg font-bold tracking-tight text-[#0F172A]"
                        >
                            {{ template.name }}
                        </CardTitle>

                        <CardDescription
                            class="line-clamp-2 min-h-10 text-sm leading-5 text-[#64748B]"
                        >
                            {{ template.description }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="px-5 pb-4">
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="rounded-md bg-[#F0F5F4] px-2.5 py-1 text-xs font-medium text-[#42645E]"
                            >
                                Responsive
                            </span>

                            <span
                                class="rounded-md bg-[#F0F5F4] px-2.5 py-1 text-xs font-medium text-[#42645E]"
                            >
                                Customizable
                            </span>
                        </div>
                    </CardContent>

                    <CardFooter
                        class="mt-auto flex items-center justify-between gap-3 border-t border-[#EEF1F1] px-5 py-4"
                    >
                        <Link
                            :href="route('templates.show',{template: template.slug})"
                            class="text-sm font-semibold text-[#475569] transition hover:text-[#00695C]"
                        >
                            Preview
                            <span aria-hidden="true">↗</span>
                        </Link>

                        <Button
                            class="rounded-xl bg-[#00695C] px-4 text-sm font-semibold text-white hover:bg-[#00574D]"
                            @click="openUseModal(template)"
                        >
                            Use template
                            <span class="ml-1.5" aria-hidden="true">→</span>
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <!-- Pagination -->
            <nav
                v-if="templates.links?.length > 3"
                aria-label="Template pagination"
                class="mt-12 flex flex-wrap justify-center gap-2"
            >
                <template v-for="(link, index) in templates.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="rounded-lg border px-3.5 py-2 text-sm font-medium transition"
                        :class="
                            link.active
                                ? 'border-[#00695C] bg-[#00695C] text-white'
                                : 'border-[#DCE5E5] bg-white text-[#475569] hover:border-[#00695C] hover:text-[#00695C]'
                        "
                    >
                        <span v-html="link.label" />
                    </Link>

                    <span
                        v-else
                        class="cursor-not-allowed rounded-lg border border-[#E2E8E8] bg-[#F0F3F3] px-3.5 py-2 text-sm text-[#A0AAAA]"
                    >
                        <span v-html="link.label" />
                    </span>
                </template>
            </nav>
        </main>

        <!-- Bottom CTA -->
        <section class="px-5 pb-16 sm:px-8 sm:pb-20 lg:px-10">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 rounded-3xl bg-[#0F172A] px-7 py-10 sm:px-10 sm:py-12 lg:flex-row lg:items-center lg:justify-between lg:px-14"
            >
                <div class="max-w-2xl">
                    <p
                        class="text-xs font-bold uppercase tracking-[0.18em] text-[#91C9BC]"
                    >
                        Your portfolio, your rules
                    </p>

                    <h2
                        class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl"
                    >
                        Ready to make your work stand out?
                    </h2>

                    <p class="mt-3 text-sm leading-6 text-[#CBD5E1] sm:text-base">
                        Pick a template and start building a portfolio that
                        tells your story.
                    </p>
                </div>

                <a
                    href="#template-gallery"
                    class="inline-flex w-fit shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-5 py-3.5 text-sm font-bold text-[#0F172A] transition hover:bg-[#E7F2EF]"
                >
                    Browse templates
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-[#E2E8E8] bg-white">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-6 text-sm text-[#64748B] sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10"
            >
                <Link :href="route('home')" class="flex items-center">
                    <AppLogo size="sm" />
                </Link>

                <p>
                    Build something that feels like you.
                    <span class="text-[#94A3B8]">© {{ new Date().getFullYear() }} quickFolio</span>
                </p>
            </div>
        </footer>

        <!-- Create portfolio dialog -->
        <Dialog v-model:open="isUseModalOpen">
            <DialogContent
                class="rounded-2xl border-[#E2E8E8] bg-white sm:max-w-md"
            >
                <DialogHeader>
                    <div
                        class="mb-2 flex h-11 w-11 items-center justify-center rounded-xl bg-[#E4F2EE] text-[#00695C]"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="3" y="3" width="18" height="18" rx="3" />
                            <path d="M3 9h18M9 21V9" />
                        </svg>
                    </div>

                    <DialogTitle class="text-xl font-bold tracking-tight text-[#0F172A]">
                        Start your portfolio
                    </DialogTitle>

                    <DialogDescription class="leading-6 text-[#64748B]">
                        You selected
                        <span class="font-semibold text-[#0F172A]">
                            {{ selectedTemplate?.name }}
                        </span>.
                        Give your portfolio a name. You can customize the
                        content after creating it.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-2 py-4" @submit.prevent="createPortfolio">
                    <Label
                        for="portfolio_title"
                        class="text-sm font-semibold text-[#334155]"
                    >
                        Portfolio title
                    </Label>

                    <Input
                        id="portfolio_title"
                        v-model="portfolioTitle"
                        required
                        maxlength="120"
                        placeholder="e.g. My Developer Portfolio"
                        class="h-11 rounded-xl border-[#DCE5E5] shadow-none focus-visible:ring-[#00695C]"
                    />

                    <p class="text-xs text-[#94A3B8]">
                        You can change this title later.
                    </p>

                    <DialogFooter class="gap-2 pt-5">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isCreatingPortfolio"
                            class="rounded-xl border-[#DCE5E5] bg-white"
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
                            {{ isCreatingPortfolio ? 'Creating portfolio…' : 'Create portfolio' }}
                            <span v-if="!isCreatingPortfolio" class="ml-1.5" aria-hidden="true">→</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
