<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ProjectPortfolio, SocialLink } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    socialLink: SocialLink;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Social links', href: `/portfolios/${props.portfolio.id}/social-links` },
    { title: props.socialLink.platform, href: `/portfolios/${props.portfolio.id}/social-links/${props.socialLink.id}` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${socialLink.platform} link`" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-3xl font-semibold">{{ socialLink.platform }}</h1>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('portfolios.social-links.edit', [portfolio.id, socialLink.id])">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <Link :href="route('portfolios.social-links.index', portfolio.id)">
                        <Button>All social links</Button>
                    </Link>
                </div>
            </div>

            <section class="space-y-4 rounded-lg border bg-card p-6">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Profile URL</p>
                    <a :href="socialLink.url" target="_blank" rel="noopener noreferrer" class="mt-1 break-all text-primary underline">{{
                        socialLink.url
                    }}</a>
                </div>
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Display order</p>
                    <p class="mt-1">{{ socialLink.sort_order }}</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
