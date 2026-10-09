<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Education, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    educations: Education[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Education', href: `/portfolios/${props.portfolio.id}/educations` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${portfolio.title} education`" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-2xl font-semibold">Education</h1>
                </div>
                <Link :href="route('portfolios.educations.create', portfolio.id)">
                    <Button>Add education</Button>
                </Link>
            </div>

            <div v-if="educations.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-lg font-medium">No education added yet</p>
                <p class="mt-2 text-sm text-muted-foreground">Add schools, degrees, and other education details.</p>
                <Link :href="route('portfolios.educations.create', portfolio.id)" class="mt-4 inline-block">
                    <Button variant="outline">Add your first education</Button>
                </Link>
            </div>

            <div v-else class="space-y-4">
                <article v-for="education in educations" :key="education.id" class="space-y-4 rounded-lg border bg-card p-5">
                    <div>
                        <h2 class="text-lg font-semibold">{{ education.degree }}</h2>
                        <p class="text-muted-foreground">
                            {{ education.institution }}<span v-if="education.field_of_study"> · {{ education.field_of_study }}</span>
                        </p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ education.start_date ?? 'Start date not set' }} – {{ education.end_date ?? 'End date not set' }}
                    </p>
                    <p v-if="education.description" class="whitespace-pre-line text-sm">{{ education.description }}</p>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('portfolios.educations.show', [portfolio.id, education.id])">
                            <Button size="sm" variant="outline">View</Button>
                        </Link>
                        <Link :href="route('portfolios.educations.edit', [portfolio.id, education.id])">
                            <Button size="sm" variant="outline">Edit</Button>
                        </Link>
                        <Link
                            :href="route('portfolios.educations.destroy', [portfolio.id, education.id])"
                            method="delete"
                            as="button"
                            class="inline-flex h-9 items-center justify-center rounded-md border border-destructive/50 px-3 text-sm font-medium text-destructive transition hover:bg-destructive hover:text-destructive-foreground"
                        >
                            Delete
                        </Link>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
