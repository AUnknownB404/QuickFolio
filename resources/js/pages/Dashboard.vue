<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    ArrowRight,
    ExternalLink,
    FolderKanban,
    Globe2,
    LayoutTemplate,
    MoreHorizontal,
    Plus,
    Search,
    Sparkles,
    Trash2,
    PencilLine,
    Clock3,
    Settings,
    LogOut,
    X,
} from 'lucide-vue-next';

interface PortfolioTemplate {
    name?: string;
    thumbnail?: string | null;
}

interface Portfolio {
    id: number;
    title: string;
    slug: string;
    status: string;
    updated_at: string;
    template?: PortfolioTemplate | null;
}

const props = defineProps<{
    portfolios: Portfolio[];
}>();

const searchQuery = ref('');
const statusFilter = ref<'all' | 'published' | 'draft'>('all');

const filteredPortfolios = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return props.portfolios.filter((portfolio) => {
        const matchesSearch = !query ||
            portfolio.title.toLowerCase().includes(query) ||
            (portfolio.template?.name || '').toLowerCase().includes(query) ||
            portfolio.slug.toLowerCase().includes(query);
        const matchesStatus = statusFilter.value === 'all' ||
            (statusFilter.value === 'published' && portfolio.status === 'published') ||
            (statusFilter.value === 'draft' && portfolio.status !== 'published');

        return matchesSearch && matchesStatus;
    });
});

const publishedCount = computed(
    () => props.portfolios.filter((portfolio) => portfolio.status === 'published').length,
);

const draftCount = computed(
    () => props.portfolios.filter((portfolio) => portfolio.status !== 'published').length,
);

const formatDate = (date: string) => {
    if (!date) return 'Recently updated';

    const parsedDate = new Date(date);
    if (Number.isNaN(parsedDate.getTime())) return 'Recently updated';

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(parsedDate);
};

const deletePortfolio = (portfolio: Portfolio) => {
    const confirmed = window.confirm(
        `Delete "${portfolio.title}"? This action cannot be undone.`,
    );

    if (!confirmed) return;

    router.delete(`/portfolios/${portfolio.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="My Portfolios — quickFolio" />

    <div class="min-h-screen bg-[#F4F7F7] text-[#0F172A]">
        <!-- Top navigation: intentionally standalone, with no Laravel default layout/sidebar -->
        <header class="sticky top-0 z-40 border-b border-[#E1E8E7] bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex shrink-0 items-center">
                    <AppLogo size="md" />
                </Link>

                <div class="hidden items-center gap-2 rounded-full border border-[#E5EBEA] bg-[#F8FAFA] px-3 py-2 text-sm text-[#64748B] md:flex">
                    <FolderKanban class="h-4 w-4 text-[#00695C]" />
                    <span>My workspace</span>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <Link
                        :href="'/settings/profile'"
                        aria-label="Settings"
                        title="Settings"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E1E8E7] bg-white text-[#475569] transition hover:border-[#B9D5CE] hover:bg-[#F4F7F7] hover:text-[#00695C] focus:outline-none focus:ring-2 focus:ring-[#00695C]/30"
                    >
                        <Settings class="h-[18px] w-[18px]" />
                    </Link>

                    <button
                        type="button"
                        aria-label="Log out"
                        title="Log out"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E1E8E7] bg-white text-[#475569] transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-200"
                        @click="router.post(route('logout'))"
                    >
                        <LogOut class="h-[18px] w-[18px]" />
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <!-- Page heading -->
            <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-[#00695C]">
                        Your workspace
                    </p>
                    <h1 class="text-3xl font-bold tracking-tight text-[#0F172A] sm:text-4xl">
                        My portfolios<span class="text-[#00695C]">.</span>
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-[#64748B] sm:text-base">
                        Create, manage, and publish your personal portfolio websites from one place.
                    </p>
                </div>

                <Link
                    href="/templates"
                    class="inline-flex h-11 items-center justify-center gap-2 self-start rounded-xl border border-[#D6E2DF] bg-white px-4 text-sm font-semibold text-[#0F172A] transition hover:border-[#00695C]/40 hover:bg-[#F8FBFA] sm:self-auto"
                >
                    <LayoutTemplate class="h-4 w-4 text-[#00695C]" />
                    Explore templates
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </section>

            <section class="mt-6 rounded-2xl border border-[#DCEBE6] bg-white p-5 shadow-[0_2px_8px_rgba(15,23,42,0.025)] sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#00695C]">How to get started</p>
                        <h2 class="mt-1 text-lg font-bold text-[#0F172A]">From template to live portfolio</h2>
                    </div>
                    <p class="text-sm text-[#64748B]">You can save and preview your work before anyone else can see it.</p>
                </div>
                <ol class="mt-5 grid gap-4 sm:grid-cols-3">
                    <li class="flex gap-3 rounded-xl bg-[#F8FBFA] p-4">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#E8F4F1] text-sm font-bold text-[#00695C]">1</span>
                        <div>
                            <p class="text-sm font-semibold text-[#0F172A]">Choose a template</p>
                            <p class="mt-1 text-xs leading-5 text-[#64748B]">Pick a design that suits your work to create a portfolio.</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-xl bg-[#F8FBFA] p-4">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#E8F4F1] text-sm font-bold text-[#00695C]">2</span>
                        <div>
                            <p class="text-sm font-semibold text-[#0F172A]">Add your content</p>
                            <p class="mt-1 text-xs leading-5 text-[#64748B]">Fill in each section and save your changes as you go.</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-xl bg-[#F8FBFA] p-4">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#E8F4F1] text-sm font-bold text-[#00695C]">3</span>
                        <div>
                            <p class="text-sm font-semibold text-[#0F172A]">Preview, then publish</p>
                            <p class="mt-1 text-xs leading-5 text-[#64748B]">Preview your work in the editor, then publish when it is ready.</p>
                        </div>
                    </li>
                </ol>
            </section>

            <!-- Summary cards -->
            <section class="mt-8 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-[#E1E8E7] bg-white p-5 shadow-[0_2px_8px_rgba(15,23,42,0.025)]">
                    <div class="flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F4F1] text-[#00695C]">
                            <FolderKanban class="h-5 w-5" />
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full bg-[#F1F5F4] px-2.5 py-1 text-xs font-medium text-[#64748B]">
                            <ArrowUpRight class="h-3.5 w-3.5" />
                            Workspace
                        </span>
                    </div>
                    <p class="mt-5 text-sm font-medium text-[#64748B]">Total portfolios</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-[#0F172A]">{{ portfolios.length }}</p>
                </div>

                <div class="rounded-2xl border border-[#E1E8E7] bg-white p-5 shadow-[0_2px_8px_rgba(15,23,42,0.025)]">
                    <div class="flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F4F1] text-[#00695C]">
                            <Globe2 class="h-5 w-5" />
                        </div>
                        <span class="rounded-full bg-[#E8F4F1] px-2.5 py-1 text-xs font-semibold text-[#00695C]">
                            Live
                        </span>
                    </div>
                    <p class="mt-5 text-sm font-medium text-[#64748B]">Published</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-[#0F172A]">{{ publishedCount }}</p>
                </div>

                <div class="rounded-2xl border border-[#E1E8E7] bg-white p-5 shadow-[0_2px_8px_rgba(15,23,42,0.025)]">
                    <div class="flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FFF5E6] text-[#A16207]">
                            <Clock3 class="h-5 w-5" />
                        </div>
                        <span class="rounded-full bg-[#FFF5E6] px-2.5 py-1 text-xs font-semibold text-[#A16207]">
                            In progress
                        </span>
                    </div>
                    <p class="mt-5 text-sm font-medium text-[#64748B]">Drafts</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-[#0F172A]">{{ draftCount }}</p>
                </div>
            </section>

            <!-- Portfolio collection -->
            <section class="mt-10">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-[#0F172A]">Your collection</h2>
                        <p class="mt-1 text-sm text-[#64748B]">
                            {{ portfolios.length }} {{ portfolios.length === 1 ? 'portfolio' : 'portfolios' }} in your workspace
                        </p>
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <label class="relative block min-w-0 flex-1 sm:w-64">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]" />
                            <input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Search portfolios..."
                                aria-label="Search portfolios"
                                class="h-10 w-full rounded-xl border border-[#E1E8E7] bg-white pl-9 pr-9 text-sm text-[#0F172A] outline-none transition placeholder:text-[#94A3B8] focus:border-[#00695C] focus:ring-2 focus:ring-[#00695C]/10"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                aria-label="Clear search"
                                class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-[#94A3B8] hover:bg-[#F1F5F4] hover:text-[#334155]"
                                @click="searchQuery = ''"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </label>
                        <select
                            v-model="statusFilter"
                            aria-label="Filter portfolios by status"
                            class="h-10 rounded-xl border border-[#E1E8E7] bg-white px-3 text-sm text-[#475569] outline-none transition focus:border-[#00695C] focus:ring-2 focus:ring-[#00695C]/10"
                        >
                            <option value="all">All portfolios</option>
                            <option value="published">Published</option>
                            <option value="draft">Drafts</option>
                        </select>
                    </div>
                </div>

                <div v-if="filteredPortfolios.length" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="portfolio in filteredPortfolios"
                        :key="portfolio.id"
                        class="group overflow-hidden rounded-2xl border border-[#E1E8E7] bg-white transition duration-200 hover:-translate-y-0.5 hover:border-[#B9D5CE] hover:shadow-[0_12px_30px_rgba(15,23,42,0.06)]"
                    >
                        <!-- Thumbnail -->
                        <div class="relative aspect-[16/10] overflow-hidden border-b border-[#E8EEED] bg-[#EAF1EF]">
                            <img
                                v-if="portfolio.template?.thumbnail"
                                :src="portfolio.template.thumbnail"
                                :alt="`${portfolio.template?.name || 'Portfolio'} template preview`"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.02]"
                            />
                            <div v-else class="flex h-full flex-col items-center justify-center bg-[#EAF1EF] p-6 text-center">
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-[#CFE0DB] bg-white text-[#00695C] shadow-sm">
                                    <LayoutTemplate class="h-7 w-7" />
                                </div>
                                <p class="mt-4 text-sm font-semibold text-[#0F172A]">{{ portfolio.template?.name || 'Portfolio template' }}</p>
                                <p class="mt-1 text-xs text-[#64748B]">Your personal website</p>
                            </div>

                            <div class="absolute left-3 top-3">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize shadow-sm"
                                    :class="portfolio.status === 'published'
                                        ? 'border-[#C7E6D8] bg-white/95 text-[#16734A]'
                                        : 'border-[#F0DDB8] bg-white/95 text-[#9A6700]'"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="portfolio.status === 'published' ? 'bg-[#16A34A]' : 'bg-[#D99A18]'"
                                    />
                                    {{ portfolio.status }}
                                </span>
                            </div>

                            <div class="absolute right-3 top-3">
                                <div class="rounded-lg border border-white/70 bg-white/90 p-1.5 text-[#64748B] shadow-sm">
                                    <MoreHorizontal class="h-4 w-4" />
                                </div>
                            </div>
                        </div>

                        <!-- Card details -->
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-bold text-[#0F172A]">
                                        {{ portfolio.title }}
                                    </h3>
                                    <p class="mt-1 truncate text-sm text-[#64748B]">
                                        {{ portfolio.template?.name || 'Custom template' }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-lg bg-[#F4F7F7] px-2 py-1 text-[11px] font-medium text-[#64748B]">
                                    #{{ portfolio.id }}
                                </span>
                            </div>

                            <div class="mt-4 flex items-center gap-2 text-xs text-[#64748B]">
                                <Clock3 class="h-3.5 w-3.5" />
                                Updated {{ formatDate(portfolio.updated_at) }}
                            </div>

                            <div class="mt-5 flex items-center gap-2 border-t border-[#EDF1F0] pt-4">
                                <Link
                                    :href="`/portfolios/${portfolio.id}/edit`"
                                    class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-3 text-sm font-semibold text-white transition hover:bg-[#00574C]"
                                >
                                    <PencilLine class="h-4 w-4" />
                                    Edit portfolio
                                </Link>

                                <a
                                    v-if="portfolio.status === 'published'"
                                    :href="`/p/${portfolio.slug}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    :aria-label="`View ${portfolio.title} live`"
                                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#DCE6E4] bg-white text-[#00695C] transition hover:bg-[#F0F7F5]"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>

                                <button
                                    type="button"
                                    :aria-label="`Delete ${portfolio.title}`"
                                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#E1E8E7] bg-white text-[#94A3B8] transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                                    @click="deletePortfolio(portfolio)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </article>

                    <!-- Create another card -->
                    <Link
                        href="/templates"
                        class="flex min-h-[300px] flex-col items-center justify-center rounded-2xl border border-dashed border-[#BFD4CE] bg-white/60 p-8 text-center transition hover:border-[#00695C] hover:bg-white"
                    >
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F4F1] text-[#00695C]">
                            <Plus class="h-6 w-6" />
                        </span>
                        <span class="mt-4 text-sm font-bold text-[#0F172A]">Create another portfolio</span>
                        <span class="mt-1 max-w-[220px] text-sm leading-5 text-[#64748B]">
                            Start with a template and make it yours.
                        </span>
                        <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-[#00695C]">
                            Browse templates <ArrowRight class="h-4 w-4" />
                        </span>
                    </Link>
                </div>

                <!-- Empty / no-results state -->
                <div v-else-if="portfolios.length && (searchQuery || statusFilter !== 'all')" class="rounded-2xl border border-[#E1E8E7] bg-white px-6 py-14 text-center sm:px-12">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#F1F5F4] text-[#64748B]">
                        <Search class="h-6 w-6" />
                    </div>
                    <p class="mt-5 text-lg font-bold tracking-tight text-[#0F172A]">No matching portfolios</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#64748B]">
                        Try another search term or change the status filter to see more portfolios.
                    </p>
                    <button
                        type="button"
                        class="mt-5 inline-flex h-10 items-center justify-center rounded-xl border border-[#D6E2DF] bg-white px-4 text-sm font-semibold text-[#00695C] transition hover:bg-[#F4F7F7]"
                        @click="searchQuery = ''; statusFilter = 'all'"
                    >
                        Clear filters
                    </button>
                </div>

                <div v-else class="rounded-2xl border border-[#E1E8E7] bg-white px-6 py-16 text-center sm:px-12">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#E8F4F1] text-[#00695C]">
                        <FolderKanban class="h-8 w-8" />
                    </div>
                    <p class="mt-5 text-xl font-bold tracking-tight text-[#0F172A]">Your first portfolio starts here.</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#64748B]">
                        Choose a template, add your details, and publish a portfolio website that represents you.
                    </p>
                    <Link
                        href="/templates"
                        class="mt-6 inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574C]"
                    >
                        <Plus class="h-4 w-4" />
                        Choose your first template
                    </Link>
                </div>
            </section>

            <!-- Simple footer -->
            <footer class="mt-12 flex flex-col gap-2 border-t border-[#E1E8E7] py-6 text-xs text-[#94A3B8] sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ new Date().getFullYear() }} quickFolio</p>
                <p>Build your presence. Keep it yours.</p>
            </footer>
        </main>
    </div>
</template>
