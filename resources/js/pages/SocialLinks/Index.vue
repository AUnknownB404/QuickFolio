<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ProjectPortfolio, SocialLink } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    socialLinks: SocialLink[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Social links', href: `/portfolios/${props.portfolio.id}/social-links` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${portfolio.title} social links`" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-2xl font-semibold">Social links</h1>
                </div>
                <Link :href="route('portfolios.social-links.create', portfolio.id)">
                    <Button>Add social link</Button>
                </Link>
            </div>

            <div v-if="socialLinks.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-lg font-medium">No social links added yet</p>
                <p class="mt-2 text-sm text-muted-foreground">Connect your portfolio to your social profiles and websites.</p>
                <Link :href="route('portfolios.social-links.create', portfolio.id)" class="mt-4 inline-block">
                    <Button variant="outline">Add your first link</Button>
                </Link>
            </div>

            <div v-else class="divide-y rounded-lg border bg-card">
                <div v-for="socialLink in socialLinks" :key="socialLink.id" class="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div class="min-w-0">
                        <h2 class="font-semibold">{{ socialLink.platform }}</h2>
                        <a :href="socialLink.url" target="_blank" rel="noopener noreferrer" class="break-all text-sm text-primary underline">{{
                            socialLink.url
                        }}</a>
                        <p class="text-xs text-muted-foreground">Display order: {{ socialLink.sort_order }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('portfolios.social-links.show', [portfolio.id, socialLink.id])">
                            <Button size="sm" variant="outline">View</Button>
                        </Link>
                        <Link :href="route('portfolios.social-links.edit', [portfolio.id, socialLink.id])">
                            <Button size="sm" variant="outline">Edit</Button>
                        </Link>
                        <Link
                            :href="route('portfolios.social-links.destroy', [portfolio.id, socialLink.id])"
                            method="delete"
                            as="button"
                            class="inline-flex h-9 items-center justify-center rounded-md border border-destructive/50 px-3 text-sm font-medium text-destructive transition hover:bg-destructive hover:text-destructive-foreground"
                        >
                            Delete
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
