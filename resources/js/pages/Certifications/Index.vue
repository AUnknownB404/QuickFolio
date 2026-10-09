<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Certification, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    certifications: Certification[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Certifications', href: `/portfolios/${props.portfolio.id}/certifications` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${portfolio.title} certifications`" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-2xl font-semibold">Certifications</h1>
                </div>
                <Link :href="route('portfolios.certifications.create', portfolio.id)">
                    <Button>Add certification</Button>
                </Link>
            </div>

            <div v-if="certifications.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-lg font-medium">No certifications added yet</p>
                <p class="mt-2 text-sm text-muted-foreground">Add professional certificates and credentials.</p>
                <Link :href="route('portfolios.certifications.create', portfolio.id)" class="mt-4 inline-block">
                    <Button variant="outline">Add your first certification</Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <article v-for="certification in certifications" :key="certification.id" class="space-y-4 rounded-lg border bg-card p-5">
                    <div>
                        <h2 class="text-lg font-semibold">{{ certification.name }}</h2>
                        <p class="text-muted-foreground">{{ certification.organization }}</p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Issued {{ certification.issue_date ?? 'date not set' }}
                        <span v-if="certification.expiry_date"> · Expires {{ certification.expiry_date }}</span>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('portfolios.certifications.show', [portfolio.id, certification.id])">
                            <Button size="sm" variant="outline">View</Button>
                        </Link>
                        <Link :href="route('portfolios.certifications.edit', [portfolio.id, certification.id])">
                            <Button size="sm" variant="outline">Edit</Button>
                        </Link>
                        <Link
                            :href="route('portfolios.certifications.destroy', [portfolio.id, certification.id])"
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
