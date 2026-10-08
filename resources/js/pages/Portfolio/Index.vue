<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Portfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolios: Portfolio[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Portfolios', href: '/portfolios' }];

const formatStatus = (status: string) => {
    return status === 'published' ? 'Published' : 'Draft';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Portfolios" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Portfolios</h1>
                    <p class="text-sm text-muted-foreground">Manage your portfolio collection.</p>
                </div>

                <Link :href="route('portfolios.create')">
                    <Button>Create portfolio</Button>
                </Link>
            </div>

            <div v-if="props.portfolios.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-lg font-medium">No portfolios yet</p>
                <p class="mt-2 text-sm text-muted-foreground">Create your first portfolio to start building.</p>
                <Link :href="route('portfolios.create')" class="mt-4 inline-block">
                    <Button variant="outline">Create your first portfolio</Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <div v-for="portfolio in props.portfolios" :key="portfolio.id" class="rounded-lg border bg-card p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ formatStatus(portfolio.status) }}</p>
                            <h2 class="mt-2 text-xl font-semibold">{{ portfolio.title }}</h2>
                        </div>

                        <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium capitalize">
                            {{ portfolio.status }}
                        </span>
                    </div>

                    <p class="mt-4 text-sm text-muted-foreground">{{ portfolio.slug }}</p>

                    <div class="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
                        <span>{{ portfolio.template?.name ?? 'No template selected' }}</span>
                    </div>

                    <div class="mt-6 flex items-center gap-2">
                        <Link :href="route('portfolios.show', portfolio.id)">
                            <Button variant="outline" size="sm">View</Button>
                        </Link>
                        <Link :href="route('portfolios.edit', portfolio.id)">
                            <Button variant="outline" size="sm">Edit</Button>
                        </Link>
                        <Link
                            :href="route('portfolios.destroy', portfolio.id)"
                            method="delete"
                            as="button"
                            class="inline-flex items-center justify-center rounded-md border border-destructive/50 bg-transparent px-3 py-2 text-sm font-medium text-destructive transition hover:bg-destructive hover:text-destructive-foreground"
                        >
                            Delete
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
