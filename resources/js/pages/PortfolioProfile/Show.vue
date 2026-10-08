<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: { id: number; title: string };
    profile?: {
        id: number;
        first_name: string;
        last_name?: string | null;
        headline?: string | null;
        about?: string | null;
        location?: string | null;
        phone?: string | null;
        profile_image?: string | null;
        resume?: string | null;
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Profile', href: `/portfolios/${props.portfolio.id}/profile` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${portfolio.title} profile`" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">Portfolio profile</p>
                    <h1 class="text-2xl font-semibold">{{ portfolio.title }}</h1>
                </div>

                <div class="flex items-center gap-2">
                    <Link v-if="profile" :href="route('portfolios.profile.edit', portfolio.id)">
                        <Button variant="outline">Edit profile</Button>
                    </Link>
                    <Link v-else :href="route('portfolios.profile.create', portfolio.id)">
                        <Button>Create profile</Button>
                    </Link>
                </div>
            </div>

            <div v-if="profile" class="rounded-lg border bg-card p-6">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-muted-foreground">Name</p>
                        <h2 class="text-3xl font-semibold">{{ profile.first_name }} {{ profile.last_name ?? '' }}</h2>
                    </div>

                    <div v-if="profile.headline">
                        <p class="text-sm text-muted-foreground">Headline</p>
                        <p class="text-lg">{{ profile.headline }}</p>
                    </div>

                    <div v-if="profile.about">
                        <p class="text-sm text-muted-foreground">About</p>
                        <p class="whitespace-pre-line">{{ profile.about }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-if="profile.location">
                            <p class="text-sm text-muted-foreground">Location</p>
                            <p>{{ profile.location }}</p>
                        </div>
                        <div v-if="profile.phone">
                            <p class="text-sm text-muted-foreground">Phone</p>
                            <p>{{ profile.phone }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-lg font-medium">No profile created yet.</p>
                <p class="mt-2 text-sm text-muted-foreground">Add the core personal information for this portfolio.</p>
            </div>
        </div>
    </AppLayout>
</template>
