<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Certification, ProjectPortfolio } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    certification: Certification;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Certifications', href: `/portfolios/${props.portfolio.id}/certifications` },
    { title: props.certification.name, href: `/portfolios/${props.portfolio.id}/certifications/${props.certification.id}` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="certification.name" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-3xl font-semibold">{{ certification.name }}</h1>
                    <p class="text-lg text-muted-foreground">{{ certification.organization }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('portfolios.certifications.edit', [portfolio.id, certification.id])">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <Link :href="route('portfolios.certifications.index', portfolio.id)">
                        <Button>All certifications</Button>
                    </Link>
                </div>
            </div>

            <img
                v-if="certification.image"
                :src="certification.image"
                :alt="`${certification.name} certificate`"
                class="max-h-96 w-full rounded-lg border object-contain"
            />

            <section class="grid gap-5 rounded-lg border bg-card p-6 sm:grid-cols-2">
                <div v-if="certification.credential_id">
                    <p class="text-sm font-medium text-muted-foreground">Credential ID</p>
                    <p class="mt-1">{{ certification.credential_id }}</p>
                </div>
                <div v-if="certification.issue_date">
                    <p class="text-sm font-medium text-muted-foreground">Issue date</p>
                    <p class="mt-1">{{ certification.issue_date }}</p>
                </div>
                <div v-if="certification.expiry_date">
                    <p class="text-sm font-medium text-muted-foreground">Expiry date</p>
                    <p class="mt-1">{{ certification.expiry_date }}</p>
                </div>
                <div v-if="certification.credential_url" class="sm:col-span-2">
                    <a :href="certification.credential_url" target="_blank" rel="noopener noreferrer" class="text-sm text-primary underline">
                        Verify credential
                    </a>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
