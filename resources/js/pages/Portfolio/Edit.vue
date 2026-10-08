<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem, Portfolio } from '@/types';

const props = defineProps<{
    portfolio: Portfolio;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}/edit` },
];

const form = useForm({
    title: props.portfolio.title,
    slug: props.portfolio.slug,
    status: props.portfolio.status,
    template_id: props.portfolio.template_id,
});

const submit = () => {
    form.put(route('portfolios.update', props.portfolio.id));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Edit ${portfolio.title}`" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">Update portfolio</p>
                    <h1 class="text-2xl font-semibold">Edit {{ portfolio.title }}</h1>
                </div>

                <Link :href="route('portfolios.show', portfolio.id)">
                    <Button variant="outline">View</Button>
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6 rounded-lg border bg-card p-6">
                <div class="space-y-2">
                    <Label for="title">Portfolio title</Label>
                    <Input id="title" v-model="form.title" placeholder="My portfolio" required />
                </div>

                <div class="space-y-2">
                    <Label for="slug">Slug</Label>
                    <Input id="slug" v-model="form.slug" placeholder="my-portfolio" />
                </div>

                <div class="space-y-2">
                    <Label for="status">Status</Label>
                    <select
                        id="status"
                        v-model="form.status"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <Button :disabled="form.processing" type="submit">Update portfolio</Button>
                    <Link :href="route('portfolios.show', portfolio.id)">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
