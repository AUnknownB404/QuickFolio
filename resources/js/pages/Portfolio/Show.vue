<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Portfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: Portfolio;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="portfolio.title" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">Portfolio</p>
                    <h1 class="text-3xl font-semibold">{{ portfolio.title }}</h1>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="route('portfolios.profile.show', portfolio.id)">
                        <Button variant="outline">Profile</Button>
                    </Link>
                    <Link :href="route('portfolios.edit', portfolio.id)">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <Link :href="route('portfolios.index')">
                        <Button>Back to list</Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border p-4">
                    <p class="text-sm font-medium text-muted-foreground">Slug</p>
                    <p class="mt-2 text-lg">{{ portfolio.slug }}</p>
                </div>

                <div class="rounded-lg border p-4">
                    <p class="text-sm font-medium text-muted-foreground">Status</p>
                    <p class="mt-2 text-lg capitalize">{{ portfolio.status }}</p>
                </div>

                <div class="rounded-lg border p-4">
                    <p class="text-sm font-medium text-muted-foreground">Template</p>
                    <p class="mt-2 text-lg">{{ portfolio.template?.name ?? 'Not selected' }}</p>
                </div>

                <div class="rounded-lg border p-4">
                    <p class="text-sm font-medium text-muted-foreground">Published</p>
                    <p class="mt-2 text-lg">{{ portfolio.published_at ? new Date(portfolio.published_at).toLocaleDateString() : 'Not published yet' }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
