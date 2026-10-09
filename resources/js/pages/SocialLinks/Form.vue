<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ProjectPortfolio, SocialLink } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    socialLink?: SocialLink | null;
}>();

const isEditing = computed(() => props.socialLink !== null && props.socialLink !== undefined);
const pageTitle = computed(() => (isEditing.value ? `Edit ${props.socialLink?.platform} link` : 'Add social link'));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Social links', href: `/portfolios/${props.portfolio.id}/social-links` },
    {
        title: pageTitle.value,
        href: isEditing.value
            ? `/portfolios/${props.portfolio.id}/social-links/${props.socialLink?.id}/edit`
            : `/portfolios/${props.portfolio.id}/social-links/create`,
    },
];

const form = useForm({
    platform: props.socialLink?.platform ?? '',
    url: props.socialLink?.url ?? '',
    sort_order: props.socialLink?.sort_order ?? 0,
});

const submit = () => {
    if (isEditing.value) {
        form.put(route('portfolios.social-links.update', [props.portfolio.id, props.socialLink?.id]));
    } else {
        form.post(route('portfolios.social-links.store', props.portfolio.id));
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${pageTitle} · ${portfolio.title}`" />

        <div class="space-y-6 p-6">
            <div>
                <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6 rounded-lg border bg-card p-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="platform">Platform</Label>
                        <Input id="platform" v-model="form.platform" placeholder="e.g. LinkedIn, GitHub, Personal site" required />
                        <p v-if="form.errors.platform" class="text-sm text-destructive">{{ form.errors.platform }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="url">Profile URL</Label>
                        <Input id="url" v-model="form.url" type="url" placeholder="https://" required />
                        <p v-if="form.errors.url" class="text-sm text-destructive">{{ form.errors.url }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="sort_order">Display order</Label>
                        <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" required />
                        <p v-if="form.errors.sort_order" class="text-sm text-destructive">{{ form.errors.sort_order }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button :disabled="form.processing" type="submit">{{ isEditing ? 'Update link' : 'Save link' }}</Button>
                    <Link :href="route('portfolios.social-links.index', portfolio.id)">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
