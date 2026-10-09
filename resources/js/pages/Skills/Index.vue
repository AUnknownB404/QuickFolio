<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PortfolioSkill, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    skills: PortfolioSkill[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Skills', href: `/portfolios/${props.portfolio.id}/skills` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${portfolio.title} skills`" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-2xl font-semibold">Skills</h1>
                </div>
                <Link :href="route('portfolios.skills.create', portfolio.id)">
                    <Button>Add skill</Button>
                </Link>
            </div>

            <div v-if="skills.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-lg font-medium">No skills added yet</p>
                <p class="mt-2 text-sm text-muted-foreground">Add skills to show your areas of expertise.</p>
                <Link :href="route('portfolios.skills.create', portfolio.id)" class="mt-4 inline-block">
                    <Button variant="outline">Add your first skill</Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <article v-for="skill in skills" :key="skill.id" class="space-y-4 rounded-lg border bg-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">{{ skill.name }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ skill.level === null ? 'Proficiency not set' : `${skill.level}% proficiency` }}
                            </p>
                        </div>
                        <span class="text-xs text-muted-foreground">Order {{ skill.sort_order }}</span>
                    </div>

                    <div v-if="skill.level !== null" class="h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary" :style="{ width: `${skill.level}%` }" />
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('portfolios.skills.show', [portfolio.id, skill.id])">
                            <Button size="sm" variant="outline">View</Button>
                        </Link>
                        <Link :href="route('portfolios.skills.edit', [portfolio.id, skill.id])">
                            <Button size="sm" variant="outline">Edit</Button>
                        </Link>
                        <Link
                            :href="route('portfolios.skills.destroy', [portfolio.id, skill.id])"
                            method="delete"
                            as="button"
                            class="inline-flex h-9 items-center justify-center rounded-md border border-destructive/50 px-3 text-sm font-medium text-destructive transition hover:bg-destructive hover:text-destructive-foreground"
                        >
                            Remove
                        </Link>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
