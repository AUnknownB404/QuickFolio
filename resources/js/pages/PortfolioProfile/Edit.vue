<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: { id: number; title: string };
    profile?: {
        first_name?: string;
        last_name?: string | null;
        headline?: string | null;
        about?: string | null;
        location?: string | null;
        phone?: string | null;
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Edit profile', href: `/portfolios/${props.portfolio.id}/profile/edit` },
];

const form = useForm({
    first_name: props.profile?.first_name ?? '',
    last_name: props.profile?.last_name ?? '',
    headline: props.profile?.headline ?? '',
    about: props.profile?.about ?? '',
    location: props.profile?.location ?? '',
    phone: props.profile?.phone ?? '',
});

const submit = () => {
    form.put(route('portfolios.profile.update', props.portfolio.id));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Edit profile for ${portfolio.title}`" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">Portfolio profile</p>
                    <h1 class="text-2xl font-semibold">Edit profile for {{ portfolio.title }}</h1>
                </div>
                <Link :href="route('portfolios.profile.show', portfolio.id)">
                    <Button variant="outline">View</Button>
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6 rounded-lg border bg-card p-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="first_name">First name</Label>
                        <Input id="first_name" v-model="form.first_name" required />
                    </div>
                    <div class="space-y-2">
                        <Label for="last_name">Last name</Label>
                        <Input id="last_name" v-model="form.last_name" />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="headline">Headline</Label>
                    <Input id="headline" v-model="form.headline" />
                </div>

                <div class="space-y-2">
                    <Label for="about">About</Label>
                    <textarea
                        id="about"
                        v-model="form.about"
                        rows="5"
                        class="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="location">Location</Label>
                        <Input id="location" v-model="form.location" />
                    </div>
                    <div class="space-y-2">
                        <Label for="phone">Phone</Label>
                        <Input id="phone" v-model="form.phone" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button :disabled="form.processing" type="submit">Update profile</Button>
                    <Link :href="route('portfolios.profile.show', portfolio.id)">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
