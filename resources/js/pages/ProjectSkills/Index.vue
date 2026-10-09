<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ProjectSkill, ProjectPortfolio } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    project: ProjectPortfolio;
    attachedSkills: ProjectSkill[];
    availableSkills: ProjectSkill[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Projects', href: `/portfolios/${props.portfolio.id}/projects` },
    { title: props.project.title, href: `/portfolios/${props.portfolio.id}/projects/${props.project.id}` },
    { title: 'Skills', href: `/portfolios/${props.portfolio.id}/projects/${props.project.id}/skills` },
];

const form = useForm({
    skill_id: '',
});

const submit = () => {
    form.post(route('portfolios.projects.skills.store', [props.portfolio.id, props.project.id]), {
        onSuccess: () => form.reset('skill_id'),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${project.title} skills`" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-2xl font-semibold">{{ project.title }} skills</h1>
                </div>
                <Link :href="route('portfolios.projects.show', [portfolio.id, project.id])">
                    <Button variant="outline">Back to project</Button>
                </Link>
            </div>

            <form v-if="availableSkills.length" @submit.prevent="submit" class="flex flex-col gap-4 rounded-lg border bg-card p-5 sm:flex-row sm:items-end">
                <div class="flex-1 space-y-2">
                    <label for="skill_id" class="text-sm font-medium">Add a portfolio skill</label>
                    <select
                        id="skill_id"
                        v-model="form.skill_id"
                        required
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="" disabled>Select a skill</option>
                        <option v-for="skill in availableSkills" :key="skill.id" :value="String(skill.id)">
                            {{ skill.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.skill_id" class="text-sm text-destructive">{{ form.errors.skill_id }}</p>
                </div>
                <Button type="submit" :disabled="form.processing || !form.skill_id">Add skill</Button>
            </form>
            <div v-else-if="attachedSkills.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                Add skills to this portfolio before associating them with this project.
                <Link :href="route('portfolios.skills.create', portfolio.id)" class="text-primary underline">Add a portfolio skill</Link>
            </div>
            <p v-else class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                All skills in this portfolio are already associated with this project.
            </p>

            <section class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Project skills</h2>
                    <p class="text-sm text-muted-foreground">Removing a skill here only detaches it from this project.</p>
                </div>

                <div v-if="attachedSkills.length" class="divide-y rounded-lg border bg-card">
                    <div v-for="skill in attachedSkills" :key="skill.id" class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <p class="font-medium">{{ skill.name }}</p>
                            <p class="text-sm text-muted-foreground">Portfolio skill</p>
                        </div>
                        <Link
                            :href="route('portfolios.projects.skills.destroy', [portfolio.id, project.id, skill.id])"
                            method="delete"
                            as="button"
                            class="inline-flex h-9 items-center justify-center rounded-md border border-destructive/50 px-3 text-sm font-medium text-destructive transition hover:bg-destructive hover:text-destructive-foreground"
                        >
                            Remove
                        </Link>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    No skills are associated with this project yet.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
