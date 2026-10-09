<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ProjectPortfolio } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Skills', href: `/portfolios/${props.portfolio.id}/skills` },
    { title: 'Add skill', href: `/portfolios/${props.portfolio.id}/skills/create` },
];

const form = useForm({
    skill_name: '',
    level: null as number | null,
    sort_order: 0,
});

const submit = () => {
    form.post(route('portfolios.skills.store', props.portfolio.id));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Add skill to ${portfolio.title}`" />

        <div class="space-y-6 p-6">
            <div>
                <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                <h1 class="text-2xl font-semibold">Add a skill</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6 rounded-lg border bg-card p-6">
                <div class="space-y-2">
                    <Label for="skill_name">Skill name</Label>
                    <Input id="skill_name" v-model="form.skill_name" placeholder="e.g. Vue.js" required />
                    <p class="text-sm text-muted-foreground">Existing skills are reused; new names are added to the shared skill catalog.</p>
                    <p v-if="form.errors.skill_name" class="text-sm text-destructive">{{ form.errors.skill_name }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
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
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button :disabled="form.processing" type="submit">Save skill</Button>
                    <Link :href="route('portfolios.skills.index', portfolio.id)">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
