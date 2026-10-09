<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Experience, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    experience: Experience;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Experience', href: `/portfolios/${props.portfolio.id}/experiences` },
    { title: props.experience.position, href: `/portfolios/${props.portfolio.id}/experiences/${props.experience.id}` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="experience.position" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-3xl font-semibold">{{ experience.position }}</h1>
                    <p class="text-lg text-muted-foreground">{{ experience.company }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('portfolios.experiences.edit', [portfolio.id, experience.id])">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <Link :href="route('portfolios.experiences.index', portfolio.id)">
                        <Button>All experience</Button>
                    </Link>
                </div>
            </div>

            <section class="space-y-5 rounded-lg border bg-card p-6">
                <p class="text-muted-foreground">
                    {{ experience.start_date ?? 'Start date not set' }} –
                    {{ experience.is_current ? 'Present' : experience.end_date ?? 'End date not set' }}
                </p>
                <p v-if="experience.location">{{ experience.location }}</p>
                <p v-if="experience.description" class="whitespace-pre-line">{{ experience.description }}</p>
                <p v-else class="text-muted-foreground">No description has been added.</p>
            </section>
        </div>
    </AppLayout>
</template>
