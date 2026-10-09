<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Education, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    education: Education;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Education', href: `/portfolios/${props.portfolio.id}/educations` },
    { title: props.education.degree, href: `/portfolios/${props.portfolio.id}/educations/${props.education.id}` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="education.degree" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-3xl font-semibold">{{ education.degree }}</h1>
                    <p class="text-lg text-muted-foreground">{{ education.institution }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('portfolios.educations.edit', [portfolio.id, education.id])">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <Link :href="route('portfolios.educations.index', portfolio.id)">
                        <Button>All education</Button>
                    </Link>
                </div>
            </div>

            <section class="space-y-5 rounded-lg border bg-card p-6">
                <p v-if="education.field_of_study">{{ education.field_of_study }}</p>
                <p class="text-muted-foreground">
                    {{ education.start_date ?? 'Start date not set' }} – {{ education.end_date ?? 'End date not set' }}
                </p>
                <p v-if="education.description" class="whitespace-pre-line">{{ education.description }}</p>
                <p v-else class="text-muted-foreground">No description has been added.</p>
            </section>
        </div>
    </AppLayout>
</template>
