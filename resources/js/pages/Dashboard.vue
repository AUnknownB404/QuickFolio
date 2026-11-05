<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Clock3,
    Copy,
    ExternalLink,
    FolderKanban,
    Globe2,
    LayoutTemplate,
    LoaderCircle,
    LogOut,
    PencilLine,
    Plus,
    Search,
    Settings,
    Trash2,
    X,
} from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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

type StatusFilter = 'all' | 'published' | 'draft';

const searchQuery = ref('');
const statusFilter = ref<StatusFilter>('all');

const isPublished = (portfolio: Portfolio) => portfolio.status === 'published';
const timeOf = (portfolio: Portfolio) => {
    const time = new Date(portfolio.updated_at).getTime();
    return Number.isNaN(time) ? 0 : time;
};

/** Most recently edited first, so the work you are likely to resume is always at the top. */
const sortedPortfolios = computed(() => [...props.portfolios].sort((a, b) => timeOf(b) - timeOf(a)));

const publishedCount = computed(() => props.portfolios.filter(isPublished).length);
const draftCount = computed(() => props.portfolios.length - publishedCount.value);

const filteredPortfolios = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return sortedPortfolios.value.filter((portfolio) => {
        const matchesSearch =
            !query ||
            portfolio.title.toLowerCase().includes(query) ||
            (portfolio.template?.name || '').toLowerCase().includes(query) ||
            portfolio.slug.toLowerCase().includes(query);
        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'published' ? isPublished(portfolio) : !isPublished(portfolio));

        return matchesSearch && matchesStatus;
    });
});

// Controls appear only when they have something to do.
const showSearch = computed(() => props.portfolios.length >= 5);
const showFilters = computed(() => publishedCount.value > 0 && draftCount.value > 0);
const isFiltering = computed(() => searchQuery.value.trim() !== '' || statusFilter.value !== 'all');

const summary = computed(() => {
    const parts: string[] = [];
    if (publishedCount.value) parts.push(`${publishedCount.value} published`);
    if (draftCount.value) parts.push(`${draftCount.value} ${draftCount.value === 1 ? 'draft' : 'drafts'}`);
    return parts.join(' · ');
});

const filterTabs = computed<{ id: StatusFilter; label: string; count: number }[]>(() => [
    { id: 'all', label: 'All', count: props.portfolios.length },
    { id: 'published', label: 'Published', count: publishedCount.value },
    { id: 'draft', label: 'Drafts', count: draftCount.value },
]);

const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = 'all';
};

/* -------------------------------------------------------------------------
 * Dates — "2 days ago" is more useful than a calendar date for resuming work
 * ---------------------------------------------------------------------- */

const absoluteDate = (date: string) => {
    const parsed = new Date(date);
    if (!date || Number.isNaN(parsed.getTime())) return '';
    return new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(parsed);
};

const relativeTime = (date: string) => {
    const parsed = new Date(date);
    if (!date || Number.isNaN(parsed.getTime())) return 'recently';

    const seconds = (Date.now() - parsed.getTime()) / 1000;
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return rtf.format(-Math.floor(seconds / 60), 'minute');
    if (seconds < 86400) return rtf.format(-Math.floor(seconds / 3600), 'hour');
    if (seconds < 7 * 86400) return rtf.format(-Math.floor(seconds / 86400), 'day');
    return absoluteDate(date);
};

/* -------------------------------------------------------------------------
 * Sharing
 * ---------------------------------------------------------------------- */

const liveUrl = (portfolio: Portfolio) => route('portfolios.published', portfolio.slug as any);

const toast = ref<string | null>(null);
let toastTimer: number | undefined;
const showToast = (message: string) => {
    window.clearTimeout(toastTimer);
    toast.value = message;
    toastTimer = window.setTimeout(() => (toast.value = null), 4000);
};
onBeforeUnmount(() => window.clearTimeout(toastTimer));

const copiedId = ref<number | null>(null);
let copiedTimer: number | undefined;

const copyLink = async (portfolio: Portfolio) => {
    try {
        await navigator.clipboard.writeText(liveUrl(portfolio));
        copiedId.value = portfolio.id;
        window.clearTimeout(copiedTimer);
        copiedTimer = window.setTimeout(() => (copiedId.value = null), 2000);
        showToast('Link copied');
    } catch {
        showToast('Couldn’t copy automatically — open the live page and copy its address.');
    }
};
onBeforeUnmount(() => window.clearTimeout(copiedTimer));

/* -------------------------------------------------------------------------
 * Deleting — permanent, so it gets a real confirmation that says what is lost
 * ---------------------------------------------------------------------- */

const portfolioToDelete = ref<Portfolio | null>(null);
const isDeleting = ref(false);

const onDeleteDialogChange = (open: boolean) => {
    if (!open && !isDeleting.value) portfolioToDelete.value = null;
};

const confirmDelete = () => {
    const portfolio = portfolioToDelete.value;
    if (!portfolio || isDeleting.value) return;

    router.delete(`/portfolios/${portfolio.id}`, {
        preserveScroll: true,
        onStart: () => {
            isDeleting.value = true;
        },
        onSuccess: () => {
            portfolioToDelete.value = null;
            showToast(`Deleted “${portfolio.title}”`);
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const startSteps = [
    { title: 'Choose a template', description: 'Pick a design that suits your work.' },
    { title: 'Add your content', description: 'Fill in each section and watch the preview update.' },
    { title: 'Publish and share', description: 'Go live when you’re ready and share one link.' },
];
</script>

<template>
    <Head title="My Portfolios — quickFolio" />

    <div class="min-h-screen bg-[#F4F7F7] dark:bg-[#0F172A] text-[#0F172A] dark:text-slate-100">
        <header class="sticky top-0 z-40 border-b border-[#E1E8E7] dark:border-slate-700 bg-white/95 dark:bg-[#0F172A]/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <Link :href="route('dashboard')" class="flex shrink-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40" aria-label="quickFolio dashboard">
                    <AppLogo size="md" />
                </Link>

                <div class="flex items-center gap-2">
                    <Link
                        href="/settings/profile"
                        aria-label="Settings"
                        title="Settings"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E1E8E7] dark:border-slate-700 bg-white dark:bg-slate-900 text-[#475569] dark:text-slate-300 transition hover:border-[#B9D5CE] dark:border-slate-600 hover:bg-[#F4F7F7] dark:hover:bg-slate-800 hover:text-[#00695C] dark:text-teal-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                    >
                        <Settings class="h-[18px] w-[18px]" />
                    </Link>
                    <button
                        type="button"
                        aria-label="Log out"
                        title="Log out"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E1E8E7] dark:border-slate-700 bg-white dark:bg-slate-900 text-[#475569] dark:text-slate-300 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300"
                        @click="router.post(route('logout'))"
                    >
                        <LogOut class="h-[18px] w-[18px]" />
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <!-- Heading: what you have, and the one thing you most likely want to do -->
            <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div class="min-w-0">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">My portfolios</h1>
                    <p class="mt-2 text-sm text-[#64748B] dark:text-slate-400 sm:text-base">
                        {{ summary || 'Create your first portfolio to get started.' }}
                    </p>
                </div>

                <Link
                    :href="route('templates.index')"
                    class="inline-flex h-11 items-center justify-center gap-2 self-start rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 focus-visible:ring-offset-2 sm:self-auto"
                >
                    <Plus class="h-4 w-4" />
                    New portfolio
                </Link>
            </section>

            <!-- Find things — only when there is enough to need finding -->
            <section v-if="showSearch || showFilters" class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div v-if="showFilters" role="group" aria-label="Filter portfolios by status" class="inline-flex w-fit rounded-xl border border-[#E1E8E7] dark:border-slate-700 bg-white dark:bg-slate-900 p-1">
                    <button
                        v-for="tab in filterTabs"
                        :key="tab.id"
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                        :class="statusFilter === tab.id ? 'bg-[#E8F4F1] dark:bg-slate-800 text-[#00695C] dark:text-teal-400' : 'text-[#64748B] dark:text-slate-400 hover:bg-[#F4F7F7] dark:hover:bg-slate-800'"
                        :aria-pressed="statusFilter === tab.id"
                        @click="statusFilter = tab.id"
                    >
                        {{ tab.label }}
                        <span class="text-xs tabular-nums" :class="statusFilter === tab.id ? 'text-[#00695C] dark:text-teal-400' : 'text-[#94A3B8] dark:text-slate-400'">{{ tab.count }}</span>
                    </button>
                </div>

                <label v-if="showSearch" class="relative block w-full sm:w-72">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8] dark:text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search portfolios"
                        aria-label="Search portfolios"
                        class="h-10 w-full rounded-xl border border-[#E1E8E7] dark:border-slate-700 bg-white dark:bg-slate-900 pl-9 pr-9 text-sm outline-none transition placeholder:text-[#94A3B8] dark:placeholder:text-slate-500 dark:text-slate-400 focus:border-[#00695C] focus:ring-2 focus:ring-[#00695C]/15"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        aria-label="Clear search"
                        class="absolute right-1.5 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-[#94A3B8] dark:text-slate-400 hover:bg-[#F1F5F4] dark:hover:bg-slate-800 hover:text-[#334155] dark:text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                        @click="searchQuery = ''"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </label>
            </section>

            <section class="mt-6">
                <!-- Collection -->
                <ul v-if="filteredPortfolios.length" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <li v-for="portfolio in filteredPortfolios" :key="portfolio.id" class="min-w-0">
                        <article class="group flex h-full flex-col rounded-2xl border border-[#E1E8E7] dark:border-slate-700 bg-white dark:bg-slate-900 transition duration-200 hover:border-[#B9D5CE] dark:border-slate-600 hover:shadow-[0_12px_30px_rgba(15,23,42,0.06)] motion-safe:hover:-translate-y-0.5">
                            <!-- The preview is a big, forgiving target for the most common action -->
                            <Link
                                :href="route('portfolios.edit', portfolio.id as any)"
                                class="relative block aspect-[16/10] overflow-hidden rounded-t-2xl border-b border-[#E8EEED] dark:border-slate-700 bg-[#EAF1EF] dark:bg-[#0F172A] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#00695C]/50"
                                tabindex="-1"
                                aria-hidden="true"
                            >
                                <img
                                    v-if="portfolio.template?.thumbnail"
                                    :src="portfolio.template.thumbnail"
                                    alt=""
                                    class="h-full w-full object-cover transition duration-500 motion-safe:group-hover:scale-[1.02]"
                                loading="lazy" decoding="async" />
                                <div v-else class="flex h-full flex-col items-center justify-center p-6 text-center">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-[#CFE0DB] dark:border-slate-600 bg-white dark:bg-slate-900 text-[#00695C] dark:text-teal-400 shadow-sm">
                                        <LayoutTemplate class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold">{{ portfolio.template?.name || 'Portfolio template' }}</p>
                                </div>
                            </Link>

                            <div class="flex flex-1 flex-col p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h2 class="truncate text-base font-bold">{{ portfolio.title }}</h2>
                                        <p class="mt-1 truncate text-sm text-[#64748B] dark:text-slate-400">{{ portfolio.template?.name || 'Custom template' }}</p>
                                    </div>
                                    <span
                                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                        :class="isPublished(portfolio)
                                            ? 'border-[#C7E6D8] dark:border-emerald-800 bg-[#EFFAF4] text-[#16734A] dark:bg-emerald-950 dark:text-emerald-300'
                                            : 'border-[#F0DDB8] dark:border-amber-800 bg-[#FFF8E9] text-[#9A6700] dark:bg-amber-950 dark:text-amber-300'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="isPublished(portfolio) ? 'bg-[#16A34A]' : 'bg-[#D99A18]'" />
                                        {{ isPublished(portfolio) ? 'Live' : 'Draft' }}
                                    </span>
                                </div>

                                <p class="mt-3 flex items-center gap-1.5 text-xs text-[#64748B] dark:text-slate-400" :title="absoluteDate(portfolio.updated_at)">
                                    <Clock3 class="h-3.5 w-3.5" />
                                    Edited {{ relativeTime(portfolio.updated_at) }}
                                </p>

                                <!-- Published: the link is the product, so it is right here and one click to copy -->
                                <div v-if="isPublished(portfolio)" class="mt-3 flex items-center gap-1 rounded-xl border border-[#E1E8E7] dark:border-slate-700 bg-[#F8FAFA] dark:bg-[#0F172A] py-1 pl-3 pr-1">
                                    <Globe2 class="h-3.5 w-3.5 shrink-0 text-[#16734A]" />
                                    <a
                                        :href="liveUrl(portfolio)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="min-w-0 flex-1 truncate rounded text-xs font-medium text-[#475569] dark:text-slate-300 hover:text-[#00695C] dark:text-teal-400 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                                        :title="`Open ${liveUrl(portfolio)}`"
                                    >
                                        /p/{{ portfolio.slug }}
                                    </a>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-[#00695C] dark:text-teal-400 transition hover:bg-white dark:bg-slate-900 dark:hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                                        :aria-label="`Copy link to ${portfolio.title}`"
                                        @click="copyLink(portfolio)"
                                    >
                                        <Check v-if="copiedId === portfolio.id" class="h-3.5 w-3.5" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                        {{ copiedId === portfolio.id ? 'Copied' : 'Copy' }}
                                    </button>
                                </div>

                                <div class="mt-auto flex items-center gap-2 pt-5">
                                    <Link
                                        :href="route('portfolios.edit', portfolio.id as any)"
                                        class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-3 text-sm font-semibold text-white transition hover:bg-[#00574C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 focus-visible:ring-offset-2"
                                    >
                                        <PencilLine class="h-4 w-4" />
                                        {{ isPublished(portfolio) ? 'Edit' : 'Continue editing' }}
                                    </Link>

                                    <a
                                        v-if="isPublished(portfolio)"
                                        :href="liveUrl(portfolio)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        :aria-label="`View ${portfolio.title} live (opens in a new tab)`"
                                        title="View live"
                                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-slate-900 text-[#00695C] dark:text-teal-400 transition hover:bg-[#F0F7F5] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                    </a>

                                    <button
                                        type="button"
                                        :aria-label="`Delete ${portfolio.title}`"
                                        title="Delete"
                                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#E1E8E7] dark:border-slate-700 bg-white dark:bg-slate-900 text-[#94A3B8] dark:text-slate-400 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300"
                                        @click="portfolioToDelete = portfolio"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </article>
                    </li>

                    <!-- Create another (not while the list is filtered) -->
                    <li v-if="!isFiltering" class="min-w-0">
                        <Link
                            :href="route('templates.index')"
                            class="flex h-full min-h-[240px] flex-col items-center justify-center rounded-2xl border border-dashed border-[#BFD4CE] dark:border-slate-600 bg-white dark:bg-slate-900/60 dark:bg-[#0F172A]/60 p-8 text-center transition hover:border-[#00695C] hover:bg-white dark:bg-slate-900 dark:hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                        >
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F4F1] dark:bg-slate-800 text-[#00695C] dark:text-teal-400">
                                <Plus class="h-6 w-6" />
                            </span>
                            <span class="mt-4 text-sm font-bold">Start another portfolio</span>
                            <span class="mt-1 max-w-[220px] text-sm leading-5 text-[#64748B] dark:text-slate-400">Great for a different audience or role.</span>
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-[#00695C] dark:text-teal-400">
                                Browse templates <ArrowRight class="h-4 w-4" />
                            </span>
                        </Link>
                    </li>
                </ul>

                <!-- Filters matched nothing -->
                <div v-else-if="portfolios.length" class="rounded-2xl border border-[#E1E8E7] dark:border-slate-700 bg-white dark:bg-slate-900 px-6 py-14 text-center sm:px-12">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#F1F5F4] dark:bg-slate-800 text-[#64748B] dark:text-slate-400">
                        <Search class="h-6 w-6" />
                    </div>
                    <p class="mt-5 text-lg font-bold">Nothing matches {{ searchQuery.trim() ? `“${searchQuery.trim()}”` : 'this filter' }}</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#64748B] dark:text-slate-400">Try a different word, or clear the filters to see everything.</p>
                    <button
                        type="button"
                        class="mt-5 inline-flex h-10 items-center justify-center rounded-xl border border-[#D6E2DF] dark:border-slate-700 bg-white dark:bg-slate-900 px-4 text-sm font-semibold text-[#00695C] dark:text-teal-400 transition hover:bg-[#F4F7F7] dark:hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <!-- First visit: the only time the how-it-works guide earns its space -->
                <div v-else class="rounded-2xl border border-[#E1E8E7] dark:border-slate-700 bg-white dark:bg-slate-900 px-6 py-12 sm:px-12 sm:py-14">
                    <div class="mx-auto max-w-2xl text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#E8F4F1] dark:bg-slate-800 text-[#00695C] dark:text-teal-400">
                            <FolderKanban class="h-8 w-8" />
                        </div>
                        <h2 class="mt-5 text-xl font-bold tracking-tight">Your first portfolio starts here</h2>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#64748B] dark:text-slate-400">
                            Nothing is public until you publish, so you can save and preview as you go.
                        </p>
                    </div>

                    <ol class="mx-auto mt-8 grid max-w-3xl gap-3 sm:grid-cols-3">
                        <li v-for="(step, index) in startSteps" :key="step.title" class="flex gap-3 rounded-xl bg-[#F8FBFA] dark:bg-[#0F172A] p-4">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#E8F4F1] dark:bg-slate-800 text-sm font-bold text-[#00695C] dark:text-teal-400" aria-hidden="true">{{ index + 1 }}</span>
                            <div>
                                <p class="text-sm font-semibold"><span class="sr-only">Step {{ index + 1 }}: </span>{{ step.title }}</p>
                                <p class="mt-1 text-xs leading-5 text-[#64748B] dark:text-slate-400">{{ step.description }}</p>
                            </div>
                        </li>
                    </ol>

                    <div class="mt-8 text-center">
                        <Link
                            :href="route('templates.index')"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-5 text-sm font-semibold text-white transition hover:bg-[#00574C] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 focus-visible:ring-offset-2"
                        >
                            Choose your first template
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </section>

            <footer class="mt-12 border-t border-[#E1E8E7] dark:border-slate-700 py-6 text-xs text-[#94A3B8] dark:text-slate-400">
                <p>© {{ new Date().getFullYear() }} quickFolio</p>
            </footer>
        </main>

        <!-- Confirmation toast (always-mounted live region so it is announced) -->
        <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex justify-center px-3" role="status" aria-live="polite">
            <Transition name="toast">
                <p v-if="toast" class="pointer-events-auto max-w-full rounded-xl bg-[#0F172A] px-4 py-2.5 text-sm text-white shadow-lg">{{ toast }}</p>
            </Transition>
        </div>

        <!-- Delete confirmation -->
        <Dialog :open="portfolioToDelete !== null" @update:open="onDeleteDialogChange">
            <DialogContent class="w-[calc(100%-1rem)] max-w-md rounded-2xl border-[#E1E8E7] dark:border-slate-700 bg-white dark:bg-slate-900 p-0 sm:w-[calc(100%-2rem)]">
                <DialogHeader class="px-5 py-5 text-left">
                    <DialogTitle class="text-lg font-bold">Delete “{{ portfolioToDelete?.title }}”?</DialogTitle>
                    <DialogDescription class="mt-2 text-sm leading-6 text-[#64748B] dark:text-slate-400">
                        This permanently removes the portfolio and everything in it. It can’t be undone.
                        <template v-if="portfolioToDelete && isPublished(portfolioToDelete)">
                            <strong class="mt-2 block font-semibold text-[#9A6700]">
                                It’s live right now — its public link (/p/{{ portfolioToDelete.slug }}) will stop working immediately.
                            </strong>
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 border-t border-[#EDF1F0] dark:border-slate-700 px-5 py-4">
                    <button
                        type="button"
                        autofocus
                        :disabled="isDeleting"
                        class="inline-flex h-10 items-center justify-center rounded-xl border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-slate-900 px-4 text-sm font-semibold text-[#475569] dark:text-slate-300 transition hover:bg-[#F4F7F7] dark:hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/40 disabled:opacity-50"
                        @click="portfolioToDelete = null"
                    >
                        Keep it
                    </button>
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-red-600 px-4 text-sm font-semibold text-white transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="confirmDelete"
                    >
                        <LoaderCircle v-if="isDeleting" class="h-4 w-4 animate-spin" />
                        {{ isDeleting ? 'Deleting…' : 'Delete portfolio' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
@media (prefers-reduced-motion: reduce) {
    .toast-enter-active,
    .toast-leave-active {
        transition: none;
    }
}
</style>
