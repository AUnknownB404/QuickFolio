<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Eye, FileText, ShieldCheck } from 'lucide-vue-next';

interface UserSummary {
    id: number;
    name: string;
    email: string;
}

interface PortfolioRow {
    id: number;
    title: string;
    slug: string;
    status: string;
    updated_at: string;
    template: {
        name: string;
        slug: string;
    };
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PageLink[];
    total: number;
}

defineProps<{
    user: UserSummary;
    portfolios: Paginated<PortfolioRow>;
}>();
</script>

<template>
    <Head :title="`${user.name}'s portfolios — Admin`" />

    <div class="min-h-screen bg-[#F5F7F6] text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <aside
            class="fixed inset-y-0 left-0 z-20 hidden w-64 flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 lg:flex"
        >
            <Link :href="route('admin.dashboard')" class="flex h-20 items-center border-b border-slate-100 px-7 dark:border-slate-800">
                <AppLogo size="md" />
            </Link>
            <div class="mt-auto border-t border-slate-100 p-5 dark:border-slate-800">
                <Link
                    :href="route('admin.dashboard')"
                    class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800"
                >
                    <ArrowLeft class="h-4 w-4" /> Back to users
                </Link>
            </div>
        </aside>

        <main class="lg:pl-64">
            <header
                class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-5 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90 sm:px-8"
            >
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-50 text-emerald-800 lg:hidden">
                    <ShieldCheck class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-xs font-medium text-slate-400">Quickfolio / Admin / Users</p>
                    <p class="text-sm font-semibold">User portfolios</p>
                </div>
                <Link
                    :href="route('admin.dashboard')"
                    class="ml-auto inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
                >
                    <ArrowLeft class="h-3.5 w-3.5" /> Users
                </Link>
            </header>

            <div class="mx-auto max-w-6xl space-y-6 p-5 sm:p-8">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">Portfolio owner</p>
                    <h1 class="mt-2 text-2xl font-bold">{{ user.name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ user.email }}</p>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5 dark:border-slate-800">
                        <div>
                            <h2 class="font-semibold">Portfolios</h2>
                            <p class="mt-1 text-xs text-slate-500">Includes drafts that are not visible to the public.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {{ portfolios.total }} total
                        </span>
                    </div>

                    <div v-if="portfolios.data.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <article
                            v-for="portfolio in portfolios.data"
                            :key="portfolio.id"
                            class="flex flex-wrap items-center justify-between gap-4 px-6 py-5"
                        >
                            <div class="flex min-w-0 items-start gap-3">
                                <span
                                    class="mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                                >
                                    <FileText class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold">{{ portfolio.title }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ portfolio.template.name }} · Updated {{ new Date(portfolio.updated_at).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span
                                    class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide"
                                    :class="
                                        portfolio.status === 'published'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                    "
                                >
                                    {{ portfolio.status }}
                                </span>
                                <Link
                                    :href="route('admin.portfolios.show', { portfolio: portfolio.id })"
                                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-800 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-900"
                                >
                                    <Eye class="h-3.5 w-3.5" /> View
                                </Link>
                            </div>
                        </article>
                    </div>
                    <p v-else class="px-6 py-12 text-center text-sm text-slate-500">This user has not created any portfolios.</p>

                    <div
                        v-if="portfolios.links.length > 3"
                        class="flex items-center justify-between border-t border-slate-100 px-6 py-4 dark:border-slate-800"
                    >
                        <div class="flex gap-1">
                            <Link
                                v-for="(link, index) in portfolios.links"
                                :key="index"
                                :href="link.url || '#'"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                                :class="
                                    link.active
                                        ? 'bg-emerald-800 text-white'
                                        : link.url
                                          ? 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                                          : 'pointer-events-none text-slate-300'
                                "
                            >
                                {{ link.label.replace(/&laquo;|&raquo;/g, '').trim() }}
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
