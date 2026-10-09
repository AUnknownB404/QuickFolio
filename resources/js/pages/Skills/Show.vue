<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PortfolioSkill, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    skill: PortfolioSkill;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Skills', href: `/portfolios/${props.portfolio.id}/skills` },
    { title: props.skill.name, href: `/portfolios/${props.portfolio.id}/skills/${props.skill.id}` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="skill.name" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }} skill</p>
                    <h1 class="text-3xl font-semibold">{{ skill.name }}</h1>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('portfolios.skills.edit', [portfolio.id, skill.id])">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <Link :href="route('portfolios.skills.index', portfolio.id)">
                        <Button>All skills</Button>
                    </Link>
                </div>
            </div>

            <section class="space-y-5 rounded-lg border bg-card p-6">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Proficiency</p>
                    <p class="mt-1 text-lg">{{ skill.level === null ? 'Not set' : `${skill.level}%` }}</p>
                    <div v-if="skill.level !== null" class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary" :style="{ width: `${skill.level}%` }" />
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Display order</p>
                    <p class="mt-1">{{ skill.sort_order }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Catalog slug</p>
                    <p class="mt-1">{{ skill.slug }}</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
