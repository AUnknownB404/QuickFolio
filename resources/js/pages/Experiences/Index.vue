<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Experience, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    experiences: Experience[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Experience', href: `/portfolios/${props.portfolio.id}/experiences` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${portfolio.title} experience`" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-2xl font-semibold">Experience</h1>
                </div>
                <Link :href="route('portfolios.experiences.create', portfolio.id)">
                    <Button>Add experience</Button>
                </Link>
            </div>

            <div v-if="experiences.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-lg font-medium">No experience added yet</p>
                <p class="mt-2 text-sm text-muted-foreground">Add roles to show your professional history.</p>
                <Link :href="route('portfolios.experiences.create', portfolio.id)" class="mt-4 inline-block">
                    <Button variant="outline">Add your first role</Button>
                </Link>
            </div>

            <div v-else class="space-y-4">
                <article v-for="experience in experiences" :key="experience.id" class="space-y-4 rounded-lg border bg-card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">{{ experience.position }}</h2>
                            <p class="text-muted-foreground">{{ experience.company }}<span v-if="experience.location"> · {{ experience.location }}</span></p>
                        </div>
                        <span v-if="experience.is_current" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">Current role</span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ experience.start_date ?? 'Start date not set' }} –
                        {{ experience.is_current ? 'Present' : experience.end_date ?? 'End date not set' }}
                    </p>
                    <p v-if="experience.description" class="whitespace-pre-line text-sm">{{ experience.description }}</p>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('portfolios.experiences.show', [portfolio.id, experience.id])">
                            <Button size="sm" variant="outline">View</Button>
                        </Link>
                        <Link :href="route('portfolios.experiences.edit', [portfolio.id, experience.id])">
                            <Button size="sm" variant="outline">Edit</Button>
                        </Link>
                        <Link
                            :href="route('portfolios.experiences.destroy', [portfolio.id, experience.id])"
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
