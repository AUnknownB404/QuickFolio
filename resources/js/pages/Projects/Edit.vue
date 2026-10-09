<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Project, ProjectSkill, ProjectPortfolio } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    project: Project;
    skills: ProjectSkill[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Projects', href: `/portfolios/${props.portfolio.id}/projects` },
    { title: props.project.title, href: `/portfolios/${props.portfolio.id}/projects/${props.project.id}/edit` },
];

const form = useForm({
    title: props.project.title,
    slug: props.project.slug,
    description: props.project.description ?? '',
    image: props.project.image ?? '',
    project_url: props.project.project_url ?? '',
    github_url: props.project.github_url ?? '',
    start_date: props.project.start_date ?? '',
    end_date: props.project.end_date ?? '',
    is_featured: props.project.is_featured,
    sort_order: props.project.sort_order,
    skills: props.project.skills.map((skill) => skill.id),
});

const submit = () => {
    form.put(route('portfolios.projects.update', [props.portfolio.id, props.project.id]));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Edit ${project.title}`" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted-foreground">{{ portfolio.title }}</p>
                    <h1 class="text-2xl font-semibold">Edit {{ project.title }}</h1>
                </div>
                <Link :href="route('portfolios.projects.show', [portfolio.id, project.id])">
                    <Button variant="outline">View project</Button>
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6 rounded-lg border bg-card p-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="title">Project title</Label>
                        <Input id="title" v-model="form.title" required />
                        <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" />
                        <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <textarea id="description" v-model="form.description" rows="5" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="project_url">Project URL</Label>
                        <Input id="project_url" v-model="form.project_url" type="url" placeholder="https://" />
                        <p v-if="form.errors.project_url" class="text-sm text-destructive">{{ form.errors.project_url }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="github_url">GitHub URL</Label>
                        <Input id="github_url" v-model="form.github_url" type="url" placeholder="https://" />
                        <p v-if="form.errors.github_url" class="text-sm text-destructive">{{ form.errors.github_url }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="image">Image URL</Label>
                        <Input id="image" v-model="form.image" type="url" placeholder="https://" />
                        <p v-if="form.errors.image" class="text-sm text-destructive">{{ form.errors.image }}</p>
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

                <div v-if="skills.length" class="space-y-3">
                    <Label>Related skills</Label>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="skill in skills" :key="skill.id" class="flex items-center gap-2 rounded-md border p-3 text-sm">
                            <input v-model="form.skills" type="checkbox" :value="skill.id" class="rounded border-input" />
                            {{ skill.name }}
                        </label>
                    </div>
                    <p v-if="form.errors.skills" class="text-sm text-destructive">{{ form.errors.skills }}</p>
                </div>
                <p v-else class="text-sm text-muted-foreground">Add skills to this portfolio first to associate them with projects.</p>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_featured" type="checkbox" class="rounded border-input" />
                    Feature this project
                </label>

                <div class="flex flex-wrap gap-2">
                    <Button :disabled="form.processing" type="submit">Update project</Button>
                    <Link :href="route('portfolios.projects.index', portfolio.id)">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
