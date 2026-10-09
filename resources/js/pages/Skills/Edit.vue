<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PortfolioSkill, ProjectPortfolio } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    skill: PortfolioSkill;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Skills', href: `/portfolios/${props.portfolio.id}/skills` },
    { title: props.skill.name, href: `/portfolios/${props.portfolio.id}/skills/${props.skill.id}/edit` },
];

const form = useForm({
    level: props.skill.level,
    sort_order: props.skill.sort_order,
});

const submit = () => {
    form.put(route('portfolios.skills.update', [props.portfolio.id, props.skill.id]));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Edit ${skill.name}`" />

        <div class="space-y-6 p-6">
            <div>
                <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                <h1 class="text-2xl font-semibold">Edit {{ skill.name }}</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6 rounded-lg border bg-card p-6">
                <div class="space-y-2">
                    <Label for="level">Proficiency (1–100, optional)</Label>
                    <Input id="level" v-model.number="form.level" type="number" min="1" max="100" />
                    <p v-if="form.errors.level" class="text-sm text-destructive">{{ form.errors.level }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="sort_order">Display order</Label>
                    <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" required />
                    <p v-if="form.errors.sort_order" class="text-sm text-destructive">{{ form.errors.sort_order }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button :disabled="form.processing" type="submit">Update skill</Button>
                    <Link :href="route('portfolios.skills.show', [portfolio.id, skill.id])">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
