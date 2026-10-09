<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Project, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    project: Project;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Projects', href: `/portfolios/${props.portfolio.id}/projects` },
    { title: props.project.title, href: `/portfolios/${props.portfolio.id}/projects/${props.project.id}` },
];

const formatDateRange = (start: string | null, end: string | null) => {
    if (!start && !end) return null;
    return [start, end].filter(Boolean).join(' – ');
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="project.title" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-3xl font-semibold">{{ project.title }}</h1>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('portfolios.projects.skills.index', [portfolio.id, project.id])">
                        <Button variant="outline">Manage skills</Button>
                    </Link>
                    <Link :href="route('portfolios.projects.edit', [portfolio.id, project.id])">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <Link :href="route('portfolios.projects.index', portfolio.id)">
                        <Button>All projects</Button>
                    </Link>
                </div>
            </div>

            <img v-if="project.image" :src="project.image" :alt="`${project.title} preview`" class="max-h-96 w-full rounded-lg border object-cover" />

            <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <section class="space-y-4 rounded-lg border bg-card p-6">
                    <div v-if="project.is_featured" class="text-sm font-medium uppercase tracking-wide text-primary">Featured project</div>
                    <h2 class="text-lg font-semibold">About this project</h2>
                    <p class="whitespace-pre-line text-muted-foreground">{{ project.description || 'No description has been added.' }}</p>
                </section>

                <aside class="space-y-5 rounded-lg border bg-card p-6">
                    <div v-if="formatDateRange(project.start_date, project.end_date)">
                        <p class="text-sm font-medium text-muted-foreground">Dates</p>
                        <p class="mt-1">{{ formatDateRange(project.start_date, project.end_date) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Display order</p>
                        <p class="mt-1">{{ project.sort_order }}</p>
                    </div>
                    <div v-if="project.skills.length">
                        <p class="mb-2 text-sm font-medium text-muted-foreground">Skills</p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="skill in project.skills" :key="skill.id" class="rounded-full bg-muted px-2.5 py-1 text-xs">
                                {{ skill.name }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <a v-if="project.project_url" :href="project.project_url" target="_blank" rel="noopener noreferrer" class="text-sm text-primary underline">Visit project</a>
                        <a v-if="project.github_url" :href="project.github_url" target="_blank" rel="noopener noreferrer" class="text-sm text-primary underline">View source code</a>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
