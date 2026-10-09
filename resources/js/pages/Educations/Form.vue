<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Education, ProjectPortfolio } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    education?: Education | null;
}>();

const isEditing = computed(() => props.education !== null && props.education !== undefined);
const pageTitle = computed(() => isEditing.value ? `Edit ${props.education?.degree}` : 'Add education');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Education', href: `/portfolios/${props.portfolio.id}/educations` },
    { title: pageTitle.value, href: isEditing.value ? `/portfolios/${props.portfolio.id}/educations/${props.education?.id}/edit` : `/portfolios/${props.portfolio.id}/educations/create` },
];

const form = useForm({
    institution: props.education?.institution ?? '',
    degree: props.education?.degree ?? '',
    field_of_study: props.education?.field_of_study ?? '',
    description: props.education?.description ?? '',
    start_date: props.education?.start_date ?? '',
    end_date: props.education?.end_date ?? '',
    sort_order: props.education?.sort_order ?? 0,
});

const submit = () => {
    const url = isEditing.value
        ? route('portfolios.educations.update', [props.portfolio.id, props.education?.id])
        : route('portfolios.educations.store', props.portfolio.id);

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
                        <Label for="institution">Institution</Label>
                        <Input id="institution" v-model="form.institution" required />
                        <p v-if="form.errors.institution" class="text-sm text-destructive">{{ form.errors.institution }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="degree">Degree or qualification</Label>
                        <Input id="degree" v-model="form.degree" required />
                        <p v-if="form.errors.degree" class="text-sm text-destructive">{{ form.errors.degree }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="field_of_study">Field of study</Label>
                        <Input id="field_of_study" v-model="form.field_of_study" />
                        <p v-if="form.errors.field_of_study" class="text-sm text-destructive">{{ form.errors.field_of_study }}</p>
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
                        <Input id="end_date" v-model="form.end_date" type="date" />
                        <p v-if="form.errors.end_date" class="text-sm text-destructive">{{ form.errors.end_date }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <textarea id="description" v-model="form.description" rows="5" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button :disabled="form.processing" type="submit">{{ isEditing ? 'Update education' : 'Save education' }}</Button>
                    <Link :href="route('portfolios.educations.index', portfolio.id)">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
