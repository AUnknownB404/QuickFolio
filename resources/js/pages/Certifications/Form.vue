<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Certification, ProjectPortfolio } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    portfolio: ProjectPortfolio;
    certification?: Certification | null;
}>();

const isEditing = computed(() => props.certification !== null && props.certification !== undefined);
const pageTitle = computed(() => (isEditing.value ? `Edit ${props.certification?.name}` : 'Add certification'));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portfolios', href: '/portfolios' },
    { title: props.portfolio.title, href: `/portfolios/${props.portfolio.id}` },
    { title: 'Certifications', href: `/portfolios/${props.portfolio.id}/certifications` },
    {
        title: pageTitle.value,
        href: isEditing.value
            ? `/portfolios/${props.portfolio.id}/certifications/${props.certification?.id}/edit`
            : `/portfolios/${props.portfolio.id}/certifications/create`,
    },
];

const form = useForm({
    name: props.certification?.name ?? '',
    organization: props.certification?.organization ?? '',
    credential_id: props.certification?.credential_id ?? '',
    credential_url: props.certification?.credential_url ?? '',
    issue_date: props.certification?.issue_date ?? '',
    expiry_date: props.certification?.expiry_date ?? '',
    image: props.certification?.image ?? '',
});

const submit = () => {
    if (isEditing.value) {
        form.put(route('portfolios.certifications.update', [props.portfolio.id, props.certification?.id]));
    } else {
        form.post(route('portfolios.certifications.store', props.portfolio.id));
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
                        <Label for="name">Certification name</Label>
                        <Input id="name" v-model="form.name" required />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="organization">Issuing organization</Label>
                        <Input id="organization" v-model="form.organization" required />
                        <p v-if="form.errors.organization" class="text-sm text-destructive">{{ form.errors.organization }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="credential_id">Credential ID</Label>
                        <Input id="credential_id" v-model="form.credential_id" />
                        <p v-if="form.errors.credential_id" class="text-sm text-destructive">{{ form.errors.credential_id }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="credential_url">Credential URL</Label>
                        <Input id="credential_url" v-model="form.credential_url" type="url" placeholder="https://" />
                        <p v-if="form.errors.credential_url" class="text-sm text-destructive">{{ form.errors.credential_url }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="issue_date">Issue date</Label>
                        <Input id="issue_date" v-model="form.issue_date" type="date" />
                        <p v-if="form.errors.issue_date" class="text-sm text-destructive">{{ form.errors.issue_date }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="expiry_date">Expiry date</Label>
                        <Input id="expiry_date" v-model="form.expiry_date" type="date" />
                        <p v-if="form.errors.expiry_date" class="text-sm text-destructive">{{ form.errors.expiry_date }}</p>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label for="image">Certificate image URL</Label>
                        <Input id="image" v-model="form.image" type="url" placeholder="https://" />
                        <p v-if="form.errors.image" class="text-sm text-destructive">{{ form.errors.image }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button :disabled="form.processing" type="submit">{{ isEditing ? 'Update certification' : 'Save certification' }}</Button>
                    <Link :href="route('portfolios.certifications.index', portfolio.id)">
                        <Button variant="outline" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
