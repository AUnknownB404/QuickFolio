<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Project, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    projects: Project[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Projects', href: `/portfolios/${props.portfolio.id}/projects` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${portfolio.title} projects`" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-2xl font-semibold">Projects</h1>
                </div>

                <Link :href="route('portfolios.projects.create', portfolio.id)">
                    <Button>Add project</Button>
                </Link>
            </div>

            <div v-if="projects.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-lg font-medium">No projects yet</p>
                <p class="mt-2 text-sm text-muted-foreground">Add projects to showcase your work.</p>
                <Link :href="route('portfolios.projects.create', portfolio.id)" class="mt-4 inline-block">
                    <Button variant="outline">Create your first project</Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <article v-for="project in projects" :key="project.id" class="space-y-4 rounded-lg border bg-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p v-if="project.is_featured" class="text-xs font-medium uppercase tracking-wide text-primary">Featured</p>
                            <h2 class="mt-1 truncate text-xl font-semibold">{{ project.title }}</h2>
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground">Order {{ project.sort_order }}</span>
                    </div>

                    <p v-if="project.description" class="line-clamp-3 text-sm text-muted-foreground">{{ project.description }}</p>

                    <div v-if="project.skills.length" class="flex flex-wrap gap-2">
                        <span v-for="skill in project.skills" :key="skill.id" class="rounded-full bg-muted px-2.5 py-1 text-xs">
                            {{ skill.name }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('portfolios.projects.show', [portfolio.id, project.id])">
                            <Button size="sm" variant="outline">View</Button>
                        </Link>
                        <Link :href="route('portfolios.projects.edit', [portfolio.id, project.id])">
                            <Button size="sm" variant="outline">Edit</Button>
                        </Link>
                        <Link
                            :href="route('portfolios.projects.destroy', [portfolio.id, project.id])"
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
