<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Experience, ProjectPortfolio } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    experience?: Experience | null;
}>();

const isEditing = computed(() => props.experience !== null && props.experience !== undefined);
const pageTitle = computed(() => isEditing.value ? `Edit ${props.experience?.position}` : 'Add experience');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Experience', href: `/portfolios/${props.portfolio.id}/experiences` },
    { title: pageTitle.value, href: isEditing.value ? `/portfolios/${props.portfolio.id}/experiences/${props.experience?.id}/edit` : `/portfolios/${props.portfolio.id}/experiences/create` },
];

const form = useForm({
    company: props.experience?.company ?? '',
    position: props.experience?.position ?? '',
    description: props.experience?.description ?? '',
    location: props.experience?.location ?? '',
    start_date: props.experience?.start_date ?? '',
    end_date: props.experience?.end_date ?? '',
    is_current: props.experience?.is_current ?? false,
    sort_order: props.experience?.sort_order ?? 0,
});

const submit = () => {
    const url = isEditing.value
        ? route('portfolios.experiences.update', [props.portfolio.id, props.experience?.id])
        : route('portfolios.experiences.store', props.portfolio.id);

    if (isEditing.value) {
        form.put(url);
    } else {
        form.post(url);
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
                        <Label for="position">Position</Label>
                        <Input id="position" v-model="form.position" required />
                        <p v-if="form.errors.position" class="text-sm text-destructive">{{ form.errors.position }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="company">Company</Label>
                        <Input id="company" v-model="form.company" required />
                        <p v-if="form.errors.company" class="text-sm text-destructive">{{ form.errors.company }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="location">Location</Label>
                        <Input id="location" v-model="form.location" placeholder="City, country or remote" />
                        <p v-if="form.errors.location" class="text-sm text-destructive">{{ form.errors.location }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="sort_order">Display order</Label>
                        <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" required />
                        <p v-if="form.errors.sort_order" class="text-sm text-destructive">{{ form.errors.sort_order }}</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="start_date">Start date</Label>
                        <Input id="start_date" v-model="form.start_date" type="date" />
                        <p v-if="form.errors.start_date" class="text-sm text-destructive">{{ form.errors.start_date }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="end_date">End date</Label>
                        <Input id="end_date" v-model="form.end_date" type="date" :disabled="form.is_current" />
                        <p v-if="form.errors.end_date" class="text-sm text-destructive">{{ form.errors.end_date }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_current" type="checkbox" class="rounded border-input" />
                    I currently work here
                </label>

                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <textarea id="description" v-model="form.description" rows="5" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button :disabled="form.processing" type="submit">{{ isEditing ? 'Update experience' : 'Save experience' }}</Button>
                    <Link :href="route('portfolios.experiences.index', portfolio.id)">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
