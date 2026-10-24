<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import type { SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Download, LayoutDashboard, LoaderCircle, Search, ShieldCheck, Users, WandSparkles, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: 'user' | 'admin';
    created_at: string;
    portfolios_count: number;
}

interface Category {
    id: number;
    name: string;
}

interface TemplateRow {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    thumbnail: string | null;
    preview_url: string | null;
    is_published: boolean;
    portfolios_count: number;
    category: Category | null;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PageLink[];
    total: number;
}

const props = defineProps<{
    stats: {
        users: number;
        templates: number;
        publishedTemplates: number;
        portfolios: number;
    };

    users: Paginated<UserRow>;
    templates: Paginated<TemplateRow>;
    categories: Category[];
    filters: {
        search: string;
    };
}>();

const page = usePage<SharedData>();
const activeTab = ref<'users' | 'templates'>('users');
const search = ref(props.filters.search);
const editingTemplate = ref<TemplateRow | null>(null);
const templateModalOpen = ref(false);
const thumbnailPreview = ref<string | null>(null);
const flash = computed(() => page.props.flash);
const templateForm = useForm({
    name: '',
    slug: '',
    category_id: '',
    description: '',
    preview_url: '',
    is_published: false as boolean,
    thumbnail: null as File | null,
});

const openCreateTemplate = () => {
    editingTemplate.value = null;
    clearThumbnailPreview();
    templateForm.reset();
    templateForm.clearErrors();
    templateModalOpen.value = true;
};

const openEditTemplate = (template: TemplateRow) => {
    editingTemplate.value = template;
    clearThumbnailPreview();
    templateForm.name = template.name;
    templateForm.slug = template.slug;
    templateForm.category_id = template.category?.id.toString() ?? '';
    templateForm.description = template.description ?? '';
    templateForm.preview_url = template.preview_url ?? '';
    templateForm.is_published = template.is_published;
    templateForm.thumbnail = null;
    templateForm.clearErrors();
    templateModalOpen.value = true;
};

const saveTemplate = () => {
    if (templateForm.processing) {
        return;
    }

    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            templateModalOpen.value = false;
            clearThumbnailPreview();
            templateForm.reset();
        },
    };

    if (editingTemplate.value) {
        templateForm
            .transform((data) => ({ ...data, _method: 'patch' }))
            .post(route('admin.templates.update', { template: editingTemplate.value.id }), {
                ...options,
                onFinish: () => templateForm.transform((data) => data),
            });
        return;
    }

    templateForm.post(route('admin.templates.store'), options);
};

const updateRole = (user: UserRow, event: Event) => {
    const role = (event.target as HTMLSelectElement).value;
    router.patch(route('admin.users.role', { user: user.id }), { role }, { preserveScroll: true });
};

const deleteUser = (user: UserRow) => {
    if (confirm(`Delete ${user.name} and all of their portfolios? This cannot be undone.`)) {
        router.delete(route('admin.users.destroy', { user: user.id }), { preserveScroll: true });
    }
};

const deleteTemplate = (template: TemplateRow) => {
    if (confirm(`Delete the "${template.name}" template? Templates in use by portfolios cannot be deleted.`)) {
        router.delete(route('admin.templates.destroy', { template: template.id }), { preserveScroll: true });
    }
};

const setThumbnail = (event: Event) => {
    const input = event.target as HTMLInputElement;
    templateForm.thumbnail = input.files?.[0] ?? null;
    clearThumbnailPreview();
    if (templateForm.thumbnail) {
        thumbnailPreview.value = URL.createObjectURL(templateForm.thumbnail);
    }
};

const clearThumbnailPreview = () => {
    if (thumbnailPreview.value) {
        URL.revokeObjectURL(thumbnailPreview.value);
        thumbnailPreview.value = null;
    }
};

const closeTemplateModal = () => {
    if (templateForm.processing) {
        return;
    }

    templateModalOpen.value = false;
    clearThumbnailPreview();
};

const runSearch = () => {
    router.get(
        route('admin.dashboard'),
        { search: search.value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};
</script>

<template>
    <Head title="Admin panel — quickFolio" />

    <div class="min-h-screen bg-[#F5F7F6] text-slate-900">
        <aside class="fixed inset-y-0 left-0 z-20 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex">
            <Link :href="route('dashboard')" class="flex h-20 items-center border-b border-slate-100 px-7">
                <AppLogo size="md" />
            </Link>
            <div class="px-5 pt-8">
                <p class="px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Workspace</p>
                <nav class="mt-3 space-y-1">
                    <button
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition"
                        :class="activeTab === 'users' ? 'bg-emerald-50 text-emerald-900' : 'text-slate-500 hover:bg-slate-50'"
                        @click="activeTab = 'users'"
                    >
                        <Users class="h-4 w-4" /> Users
                    </button>
                    <button
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition"
                        :class="activeTab === 'templates' ? 'bg-emerald-50 text-emerald-900' : 'text-slate-500 hover:bg-slate-50'"
                        @click="activeTab = 'templates'"
                    >
                        <WandSparkles class="h-4 w-4" /> Templates
                    </button>
                </nav>
            </div>
            <div class="mt-auto border-t border-slate-100 p-5">
                <Link
                    :href="route('dashboard')"
                    class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-500 hover:bg-slate-50"
                >
                    <LayoutDashboard class="h-4 w-4" /> Back to dashboard
                </Link>
                <div class="mt-3 flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-900">
                        {{ page.props.auth.user.name.slice(0, 1).toUpperCase() }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ page.props.auth.user.name }}</p>
                        <p class="truncate text-xs text-slate-500">Administrator</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="lg:pl-64">
            <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-5 backdrop-blur sm:px-8">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-50 text-emerald-800 lg:hidden"
                        ><ShieldCheck class="h-5 w-5"
                    /></span>
                    <div>
                        <p class="text-xs font-medium text-slate-400">Quickfolio / Admin</p>
                        <p class="text-sm font-semibold">{{ activeTab === 'users' ? 'User management' : 'Template library' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('templates.index')" class="hidden text-sm font-semibold text-slate-600 hover:text-emerald-800 sm:block"
                        >View marketplace</Link
                    >
                    <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800">ADMIN</span>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-8 p-5 sm:p-8">
                <div v-if="flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                    {{ flash.error }}
                </div>

                <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-sm font-semibold text-emerald-800">ADMINISTRATION</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Project overview</h1>
                        <p class="mt-2 max-w-2xl text-sm text-slate-500">Manage your community and keep the template marketplace fresh.</p>
                    </div>
                    <button
                        v-if="activeTab === 'templates'"
                        @click="openCreateTemplate"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-800 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900"
                    >
                        <span class="text-lg leading-none">+</span> Add template
                    </button>
                </section>

                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Total users</p>
                        <div class="mt-4 flex items-end justify-between">
                            <p class="text-3xl font-bold">{{ stats.users.toLocaleString() }}</p>
                            <span class="rounded-xl bg-blue-50 p-2.5 text-blue-700"><Users class="h-5 w-5" /></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">All templates</p>
                        <div class="mt-4 flex items-end justify-between">
                            <p class="text-3xl font-bold">{{ stats.templates.toLocaleString() }}</p>
                            <span class="rounded-xl bg-violet-50 p-2.5 text-violet-700"><WandSparkles class="h-5 w-5" /></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Published templates</p>
                        <div class="mt-4 flex items-end justify-between">
                            <p class="text-3xl font-bold">{{ stats.publishedTemplates.toLocaleString() }}</p>
                            <span class="rounded-xl bg-emerald-50 p-2.5 text-emerald-700"><ShieldCheck class="h-5 w-5" /></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Portfolios created</p>
                        <div class="mt-4 flex items-end justify-between">
                            <p class="text-3xl font-bold">{{ stats.portfolios.toLocaleString() }}</p>
                            <span class="rounded-xl bg-amber-50 p-2.5 text-amber-700"><LayoutDashboard class="h-5 w-5" /></span>
                        </div>
                    </article>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <div class="flex gap-2">
                            <button
                                @click="activeTab = 'users'"
                                :class="activeTab === 'users' ? 'bg-emerald-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                            >
                                Users
                            </button>
                            <button
                                @click="activeTab = 'templates'"
                                :class="activeTab === 'templates' ? 'bg-emerald-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                            >
                                Templates
                            </button>
                        </div>
                        <form class="relative" @submit.prevent="runSearch">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                class="w-full rounded-lg border-input bg-background py-2 pl-9 pr-10 text-sm text-foreground placeholder:text-muted-foreground focus:border-emerald-700 focus:ring-emerald-700 sm:w-64"
                                :placeholder="activeTab === 'users' ? 'Search users' : 'Search templates'"
                            />
                            <button
                                type="submit"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md px-2 py-1 text-xs font-semibold text-emerald-800 hover:bg-emerald-50"
                            >
                                Go
                            </button>
                        </form>
                    </div>

                    <div v-if="activeTab === 'users'" class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <tr>
                                    <th class="px-7 py-4">User</th>
                                    <th class="px-5 py-4">Portfolios</th>
                                    <th class="px-5 py-4">Joined</th>
                                    <th class="px-5 py-4">Access</th>
                                    <th class="px-7 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50/70">
                                    <td class="px-7 py-4">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 font-bold text-slate-600"
                                                >{{ user.name.slice(0, 1).toUpperCase() }}</span
                                            >
                                            <div>
                                                <p class="font-semibold">{{ user.name }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ user.portfolios_count }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                                    <td class="px-5 py-4">
                                        <select
                                            :value="user.role"
                                            class="rounded-lg border-input bg-background py-1.5 pl-3 pr-8 text-xs font-semibold text-foreground focus:border-emerald-700 focus:ring-emerald-700"
                                            @change="updateRole(user, $event)"
                                        >
                                            <option value="user">User</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </td>
                                    <td class="px-7 py-4 text-right">
                                        <button class="text-xs font-semibold text-rose-600 hover:text-rose-800" @click="deleteUser(user)">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="users.data.length === 0">
                                    <td colspan="5" class="px-7 py-12 text-center text-sm text-slate-500">No users found.</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="flex items-center justify-between border-t border-slate-100 px-6 py-4">
                            <p class="text-xs text-slate-500">{{ users.total }} accounts</p>
                            <div class="flex gap-1">
                                <Link
                                    v-for="(link, index) in users.links"
                                    :key="index"
                                    :href="link.url || '#'"
                                    v-html="link.label"
                                    class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                                    :class="
                                        link.active
                                            ? 'bg-emerald-800 text-white'
                                            : link.url
                                              ? 'text-slate-600 hover:bg-slate-100'
                                              : 'pointer-events-none text-slate-300'
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7 xl:grid-cols-3">
                        <article v-for="template in templates.data" :key="template.id" class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="relative aspect-[16/10] bg-slate-100">
                                <img
                                    v-if="template.thumbnail"
                                    :src="template.thumbnail"
                                    :alt="`${template.name} preview`"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="grid h-full place-items-center text-slate-300"><WandSparkles class="h-12 w-12" /></div>
                                <span
                                    class="absolute left-3 top-3 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide"
                                    :class="template.is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-white/90 text-slate-600'"
                                    >{{ template.is_published ? 'Published' : 'Draft' }}</span
                                >
                            </div>
                            <div class="space-y-3 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="truncate font-bold">{{ template.name }}</h3>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ template.category?.name ?? 'Uncategorized' }} · {{ template.portfolios_count }} portfolios
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        <button class="text-xs font-semibold text-emerald-800 hover:underline" @click="openEditTemplate(template)">
                                            Edit
                                        </button>
                                        <button class="text-xs font-semibold text-rose-600 hover:underline" @click="deleteTemplate(template)">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                                <p class="line-clamp-2 min-h-9 text-xs leading-5 text-slate-500">
                                    {{ template.description || 'No description added yet.' }}
                                </p>
                                <Link
                                    v-if="template.is_published"
                                    :href="route('templates.preview', { template: template.slug })"
                                    class="inline-flex text-xs font-semibold text-slate-500 hover:text-emerald-800"
                                    >Preview template ↗</Link
                                >
                                <span v-else class="text-xs text-slate-400">Draft template</span>
                                <a
                                    :href="route('admin.templates.download-demo', { template: template.id })"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-700 hover:text-emerald-800"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                    Download demo ZIP
                                </a>
                            </div>
                        </article>
                        <div
                            v-if="templates.data.length === 0"
                            class="col-span-full rounded-xl border border-dashed border-slate-300 py-16 text-center"
                        >
                            <WandSparkles class="mx-auto h-9 w-9 text-slate-300" />
                            <p class="mt-3 font-semibold">No templates yet</p>
                            <p class="mt-1 text-sm text-slate-500">Add your first portfolio template to the marketplace.</p>
                        </div>
                        <div class="col-span-full flex justify-end gap-1 border-t border-slate-100 pt-4">
                            <Link
                                v-for="(link, index) in templates.links"
                                :key="index"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                                :class="
                                    link.active
                                        ? 'bg-emerald-800 text-white'
                                        : link.url
                                          ? 'text-slate-600 hover:bg-slate-100'
                                          : 'pointer-events-none text-slate-300'
                                "
                            />
                        </div>
                    </div>
                </section>
            </div>
        </main>

        <div
            v-if="templateModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4"
            @click.self="closeTemplateModal"
        >
            <section class="my-8 w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
                <header class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-800">Marketplace</p>
                        <h2 class="mt-1 text-xl font-bold">{{ editingTemplate ? 'Edit template' : 'Create a template' }}</h2>
                    </div>
                    <button
                        :disabled="templateForm.processing"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Close"
                        @click="closeTemplateModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </header>
                <form class="space-y-5 p-6" :aria-busy="templateForm.processing" @submit.prevent="saveTemplate">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="space-y-1.5 text-sm font-medium"
                            >Template name
                            <input
                                v-model="templateForm.name"
                                required
                                maxlength="255"
                                class="w-full rounded-lg border-input bg-background text-sm text-foreground placeholder:text-muted-foreground focus:border-emerald-700 focus:ring-emerald-700"
                                placeholder="e.g. Minimal Portfolio"
                            />
                            <p v-if="templateForm.errors.name" class="text-xs text-rose-600">{{ templateForm.errors.name }}</p>
                        </label>
                        <label class="space-y-1.5 text-sm font-medium"
                            >URL slug
                            <input
                                v-model="templateForm.slug"
                                maxlength="255"
                                class="w-full rounded-lg border-input bg-background text-sm text-foreground placeholder:text-muted-foreground focus:border-emerald-700 focus:ring-emerald-700"
                                placeholder="generated from the name if blank"
                            />
                            <p v-if="templateForm.errors.slug" class="text-xs text-rose-600">{{ templateForm.errors.slug }}</p>
                        </label>
                        <label class="space-y-1.5 text-sm font-medium"
                            >Category
                            <select
                                v-model="templateForm.category_id"
                                class="w-full rounded-lg border-input bg-background text-sm text-foreground focus:border-emerald-700 focus:ring-emerald-700"
                            >
                                <option value="">No category</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id.toString()">{{ category.name }}</option>
                            </select>
                            <p v-if="templateForm.errors.category_id" class="text-xs text-rose-600">{{ templateForm.errors.category_id }}</p>
                        </label>
                        <label class="space-y-1.5 text-sm font-medium"
                            >Preview link <span class="font-normal text-slate-400">(optional)</span>
                            <input
                                v-model="templateForm.preview_url"
                                type="text"
                                class="w-full rounded-lg border-input bg-background text-sm text-foreground placeholder:text-muted-foreground focus:border-emerald-700 focus:ring-emerald-700"
                                placeholder="https://example.com"
                            />
                            <p v-if="templateForm.errors.preview_url" class="text-xs text-rose-600">{{ templateForm.errors.preview_url }}</p>
                        </label>
                    </div>
                    <label class="block space-y-1.5 text-sm font-medium"
                        >Description
                        <textarea
                            v-model="templateForm.description"
                            rows="3"
                            maxlength="5000"
                            class="w-full rounded-lg border-input bg-background text-sm text-foreground placeholder:text-muted-foreground focus:border-emerald-700 focus:ring-emerald-700"
                            placeholder="Describe what makes this template useful."
                        />
                        <p v-if="templateForm.errors.description" class="text-xs text-rose-600">{{ templateForm.errors.description }}</p>
                    </label>
                    <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-center">
                        <label class="block space-y-1.5 text-sm font-medium"
                            >Template image <span class="font-normal text-slate-400">(JPG, PNG, or WebP; max 5 MB)</span>
                            <input
                                type="file"
                                accept="image/*"
                                class="block w-full rounded-lg border border-input bg-background text-sm text-foreground file:mr-3 file:border-0 file:bg-muted file:px-3 file:py-2 file:text-foreground"
                                @change="setThumbnail"
                            />
                            <p v-if="templateForm.errors.thumbnail" class="text-xs text-rose-600">{{ templateForm.errors.thumbnail }}</p>
                        </label>
                        <img
                            v-if="thumbnailPreview || editingTemplate?.thumbnail"
                            :src="thumbnailPreview ?? editingTemplate?.thumbnail ?? ''"
                            alt="Template thumbnail preview"
                            class="h-16 w-24 rounded-lg border border-slate-200 object-cover"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input
                            v-model="templateForm.is_published"
                            type="checkbox"
                            class="rounded border-slate-300 text-emerald-800 focus:ring-emerald-700"
                        />
                        Publish in the public marketplace
                    </label>
                    <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                        <button
                            type="button"
                            :disabled="templateForm.processing"
                            class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                            @click="closeTemplateModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="templateForm.processing"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <LoaderCircle v-if="templateForm.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            {{
                                templateForm.processing
                                    ? editingTemplate
                                        ? 'Saving changes…'
                                        : 'Creating template…'
                                    : editingTemplate
                                      ? 'Save changes'
                                      : 'Create template'
                            }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>
