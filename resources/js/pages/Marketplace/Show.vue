```vue
<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface TemplateCategory {
    id: number;
    name: string;
    slug: string;
}

interface TemplateMedia {
    id: number;
    media_path: string;
    media_type: string;
}

interface FieldDefinition {
    id: number;
    field_key: string;
    label: string;
}

interface TemplateSectionField {
    is_required: boolean;
    is_visible: boolean;
    field_definition: FieldDefinition;
}

interface TemplateSection {
    id: number;
    section_key: string;
    is_enabled: boolean;
    fields: TemplateSectionField[];
}

interface Template {
    id: number;
    name: string;
    slug: string;
    description: string;
    thumbnail: string;
    category: TemplateCategory;
    media: TemplateMedia[];
    sections: TemplateSection[];
}

const props = defineProps<{
    template: Template;
    relatedTemplates: Template[];
}>();

const portfolioTitle = ref(`My ${props.template.name} Portfolio`);
const isUseModalOpen = ref(false);
const isCreatingPortfolio = ref(false);

const enabledSections = () =>
    (props.template.sections || []).filter(section => section.is_enabled);

const createPortfolio = () => {
    if (isCreatingPortfolio.value || !portfolioTitle.value.trim()) return;

    isCreatingPortfolio.value = true;
    router.post(route('templates.use', { slug: props.template.slug }), {
        title: portfolioTitle.value.trim(),
    }, {
        onFinish: () => {
            isCreatingPortfolio.value = false;
        },
    });
};

const fallbackImage =
    'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?auto=format&fit=crop&w=1200&q=85';
</script>

<template>
    <Head :title="`${template.name} — quickFolio Templates`" />

    <div class="min-h-screen bg-[#F4F7F7] text-[#0F172A]">
        <!-- Navigation -->
        <header class="sticky top-0 z-40 border-b border-[#E2E8E8] bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between gap-4 px-5 sm:px-8 lg:px-10">
                <Link :href="route('home')" class="flex shrink-0 items-center">
                    <AppLogo size="md" />
                </Link>

                <nav class="hidden items-center gap-7 md:flex">
                    <Link
                        :href="route('templates.index')"
                        class="text-sm font-semibold text-[#00695C]"
                    >
                        Templates
                    </Link>
                    <Link
                        :href="route('dashboard')"
                        class="text-sm font-medium text-[#64748B] transition hover:text-[#00695C]"
                    >
                        Dashboard
                    </Link>
                </nav>

                <Button
                    class="rounded-xl bg-[#00695C] px-4 font-semibold text-white hover:bg-[#00574D]"
                    @click="isUseModalOpen = true"
                >
                    Use template
                    <span class="ml-1.5" aria-hidden="true">→</span>
                </Button>
            </div>
        </header>

        <main>
            <!-- Breadcrumb -->
            <div class="mx-auto max-w-7xl px-5 pt-7 sm:px-8 lg:px-10">
                <div class="flex flex-wrap items-center gap-2 text-sm text-[#94A3B8]">
                    <Link :href="route('templates.index')" class="transition hover:text-[#00695C]">
                        Templates
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span class="text-[#475569]">{{ template.name }}</span>
                </div>
            </div>

            <!-- Hero -->
            <section class="mx-auto max-w-7xl px-5 pb-14 pt-8 sm:px-8 sm:pb-20 sm:pt-12 lg:px-10">
                <div class="grid items-center gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:gap-16">
                    <!-- Details -->
                    <div class="order-2 lg:order-1">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[#C8E3DD] bg-[#EFF8F5] px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.12em] text-[#00695C]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#00695C]"></span>
                            {{ template.category?.name || 'Portfolio template' }}
                        </div>

                        <h1 class="mt-6 text-4xl font-bold leading-[1.12] tracking-tight sm:text-5xl lg:text-[56px]">
                            {{ template.name }}<span class="text-[#00695C]">.</span>
                        </h1>

                        <p class="mt-6 max-w-xl text-base leading-8 text-[#64748B] sm:text-lg">
                            {{ template.description }}
                        </p>

                        <div class="mt-7 flex flex-wrap gap-x-5 gap-y-3 text-sm text-[#475569]">
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#DDEFE9] text-xs font-bold text-[#00695C]">✓</span>
                                Customizable content
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#DDEFE9] text-xs font-bold text-[#00695C]">✓</span>
                                Responsive layout
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#DDEFE9] text-xs font-bold text-[#00695C]">✓</span>
                                No coding required
                            </div>
                        </div>

                        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <Button
                                size="lg"
                                class="h-12 rounded-xl bg-[#00695C] px-6 font-semibold text-white hover:bg-[#00574D]"
                                @click="isUseModalOpen = true"
                            >
                                Start with this template
                                <span class="ml-2" aria-hidden="true">→</span>
                            </Button>

                            <Link
                                :href="route('templates.index')"
                                class="inline-flex h-12 items-center justify-center rounded-xl border border-[#DCE5E5] bg-white px-5 text-sm font-semibold text-[#475569] transition hover:border-[#00695C] hover:text-[#00695C]"
                            >
                                Browse other templates
                            </Link>
                        </div>

                        <p class="mt-4 text-xs leading-5 text-[#94A3B8]">
                            Make it yours with your own content, images, and personal style.
                        </p>
                    </div>

                    <!-- Main Preview -->
                    <div class="order-1 lg:order-2">
                        <div class="overflow-hidden rounded-2xl border border-[#DCE5E5] bg-white p-2.5 shadow-[0_20px_60px_rgba(15,23,42,0.07)] sm:p-3">
                            <div class="flex h-9 items-center gap-1.5 border-b border-[#EEF1F1] px-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-[#F2B8B5]"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-[#E8D39A]"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-[#A8D8C3]"></span>
                                <div class="mx-auto max-w-[200px] flex-1 rounded-md bg-[#F4F7F7] px-3 py-1 text-center text-[10px] text-[#94A3B8]">
                                    your-portfolio.com
                                </div>
                            </div>

                            <div class="overflow-hidden rounded-b-lg bg-[#E8EEEE]">
                                <img
                                    :src="template.thumbnail || fallbackImage"
                                    :alt="`${template.name} full template preview`"
                                    class="aspect-[4/3] w-full object-cover sm:aspect-[5/3]"
                                />
                            </div>
                        </div>

                        <p class="mt-3 text-right text-xs text-[#94A3B8]">
                            Template preview · Actual content may vary
                        </p>
                    </div>
                </div>
            </section>

            <!-- Overview strip -->
            <section class="border-y border-[#E2E8E8] bg-white">
                <div class="mx-auto grid max-w-7xl grid-cols-1 divide-y divide-[#E2E8E8] px-5 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:px-8 lg:px-10">
                    <div class="py-5 sm:px-6 sm:first:pl-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#94A3B8]">Category</p>
                        <p class="mt-1.5 font-semibold text-[#0F172A]">{{ template.category?.name || 'Portfolio' }}</p>
                    </div>
                    <div class="py-5 sm:px-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#94A3B8]">Available sections</p>
                        <p class="mt-1.5 font-semibold text-[#0F172A]">{{ enabledSections().length }} sections included</p>
                    </div>
                    <div class="py-5 sm:px-6 sm:pr-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#94A3B8]">Getting started</p>
                        <p class="mt-1.5 font-semibold text-[#0F172A]">Choose, customize, publish</p>
                    </div>
                </div>
            </section>

            <!-- Supported sections -->
            <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20 lg:px-10">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#00695C]">
                        What's included
                    </p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        Everything you need to tell your story.
                    </h2>
                    <p class="mt-4 leading-7 text-[#64748B]">
                        Explore the sections and content fields available in this template.
                        Customize the details to match your work and personality.
                    </p>
                </div>

                <div
                    v-if="enabledSections().length"
                    class="mt-9 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Card
                        v-for="section in enabledSections()"
                        :key="section.id"
                        class="rounded-2xl border-[#E2E8E8] bg-white shadow-none transition hover:border-[#C8D8D5]"
                    >
                        <CardContent class="p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#E4F2EE] text-[#00695C]">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="22"
                                        height="22"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <rect x="3" y="3" width="18" height="18" rx="3" />
                                        <path d="M8 9h8M8 13h8M8 17h4" />
                                    </svg>
                                </div>

                                <span class="rounded-full bg-[#EFF8F5] px-2.5 py-1 text-[11px] font-semibold text-[#00695C]">
                                    Included
                                </span>
                            </div>

                            <h3 class="mt-5 text-lg font-bold capitalize text-[#0F172A]">
                                {{ section.section_key.replace(/[-_]/g, ' ') }}
                            </h3>

                            <p class="mt-1 text-sm text-[#64748B]">
                                {{ section.fields.filter(f => f.is_visible).length }}
                                visible content fields
                            </p>

                            <div class="mt-5 border-t border-[#EEF1F1] pt-4">
                                <ul
                                    v-if="section.fields.some(f => f.is_visible)"
                                    class="space-y-3"
                                >
                                    <li
                                        v-for="field in section.fields.filter(f => f.is_visible)"
                                        :key="field.field_definition?.id"
                                        class="flex items-start gap-2.5 text-sm text-[#475569]"
                                    >
                                        <span class="mt-0.5 text-[#00695C]" aria-hidden="true">✓</span>
                                        <span class="min-w-0 break-words">
                                            {{ field.field_definition?.label || field.field_definition?.field_key }}
                                            <span
                                                v-if="field.is_required"
                                                class="ml-1 text-xs text-[#B45309]"
                                            >
                                                Required
                                            </span>
                                        </span>
                                    </li>
                                </ul>

                                <p v-else class="text-sm text-[#94A3B8]">
                                    No visible fields configured.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div
                    v-else
                    class="mt-8 rounded-2xl border border-dashed border-[#C8D8D5] bg-white px-6 py-12 text-center"
                >
                    <h3 class="font-semibold text-[#0F172A]">Section information coming soon</h3>
                    <p class="mt-2 text-sm text-[#64748B]">
                        Start with this template to explore its available customization options.
                    </p>
                </div>
            </section>

            <!-- Screenshot Gallery -->
            <section
                v-if="template.media?.length"
                class="border-y border-[#E2E8E8] bg-white"
            >
                <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20 lg:px-10">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#00695C]">
                                Take a closer look
                            </p>
                            <h2 class="mt-3 text-3xl font-bold tracking-tight">
                                A look inside the design.
                            </h2>
                        </div>
                        <p class="max-w-sm text-sm leading-6 text-[#64748B]">
                            Explore additional screenshots to see more of this template's layout.
                        </p>
                    </div>

                    <div class="mt-9 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div
                            v-for="media in template.media.filter(m => m.media_type?.startsWith('image/'))"
                            :key="media.id"
                            class="overflow-hidden rounded-2xl border border-[#E2E8E8] bg-[#F4F7F7] p-2 shadow-sm"
                        >
                            <img
                                :src="media.media_path"
                                :alt="`${template.name} screenshot`"
                                loading="lazy"
                                class="aspect-video w-full rounded-xl object-cover"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Related templates -->
            <section
                v-if="relatedTemplates.length"
                class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20 lg:px-10"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#00695C]">
                            Keep exploring
                        </p>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight">
                            You might like these, too.
                        </h2>
                        <p class="mt-3 text-sm leading-6 text-[#64748B]">
                            A few more designs to consider for your portfolio.
                        </p>
                    </div>

                    <Link
                        :href="route('templates.index')"
                        class="text-sm font-semibold text-[#00695C] hover:underline"
                    >
                        Browse all templates →
                    </Link>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="rel in relatedTemplates"
                        :key="rel.id"
                        class="group overflow-hidden rounded-2xl border-[#E2E8E8] bg-white shadow-none transition hover:-translate-y-1 hover:shadow-lg"
                    >
                        <Link
                            :href="route('templates.show',{ slug: rel.slug })"
                            class="block overflow-hidden bg-[#E8EEEE]"
                        >
                            <img
                                :src="rel.thumbnail || fallbackImage"
                                :alt="`${rel.name} preview`"
                                loading="lazy"
                                class="aspect-[4/3] w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                            />
                        </Link>

                        <CardContent class="p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-[#00695C]">
                                {{ rel.category?.name || 'Portfolio' }}
                            </p>

                            <h3 class="mt-2 text-lg font-bold text-[#0F172A]">
                                {{ rel.name }}
                            </h3>

                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-[#64748B]">
                                {{ rel.description }}
                            </p>

                            <Link
                                :href="route('templates.show', { slug: rel.slug })"
                                class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-[#00695C] hover:underline"
                            >
                                Explore template
                                <span aria-hidden="true">→</span>
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Final CTA -->
            <section class="px-5 pb-16 sm:px-8 sm:pb-20 lg:px-10">
                <div class="mx-auto flex max-w-7xl flex-col gap-6 rounded-3xl bg-[#0F172A] px-7 py-10 sm:px-10 sm:py-12 lg:flex-row lg:items-center lg:justify-between lg:px-14">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#91C9BC]">
                            Your work deserves a home
                        </p>
                        <h2 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                            Ready to make this template yours?
                        </h2>
                        <p class="mt-3 text-sm leading-6 text-[#CBD5E1] sm:text-base">
                            Add your story, showcase your best work, and create a portfolio you can share.
                        </p>
                    </div>

                    <Button
                        size="lg"
                        class="h-12 shrink-0 rounded-xl bg-white px-6 font-bold text-[#0F172A] hover:bg-[#E7F2EF]"
                        @click="isUseModalOpen = true"
                    >
                        Create my portfolio
                        <span class="ml-2" aria-hidden="true">→</span>
                    </Button>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-[#E2E8E8] bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-6 text-sm text-[#64748B] sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
                <Link :href="route('home')" class="flex items-center">
                    <AppLogo size="sm" />
                </Link>
                <p>
                    Build something that feels like you.
                    <span class="text-[#94A3B8]">© {{ new Date().getFullYear() }} quickFolio</span>
                </p>
            </div>
        </footer>

        <!-- Create portfolio dialog -->
        <Dialog v-model:open="isUseModalOpen">
            <DialogContent class="rounded-2xl border-[#E2E8E8] bg-white sm:max-w-md">
                <DialogHeader>
                    <div class="mb-2 flex h-11 w-11 items-center justify-center rounded-xl bg-[#E4F2EE] text-[#00695C]">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="3" y="3" width="18" height="18" rx="3" />
                            <path d="M3 9h18M9 21V9" />
                        </svg>
                    </div>

                    <DialogTitle class="text-xl font-bold tracking-tight text-[#0F172A]">
                        Start your portfolio
                    </DialogTitle>

                    <DialogDescription class="leading-6 text-[#64748B]">
                        Create your portfolio using
                        <span class="font-semibold text-[#0F172A]">{{ template.name }}</span>.
                        You can customize your content after creation.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-2 py-4" @submit.prevent="createPortfolio">
                    <Label for="portfolio_title" class="text-sm font-semibold text-[#334155]">
                        Portfolio title
                    </Label>

                    <Input
                        id="portfolio_title"
                        v-model="portfolioTitle"
                        required
                        maxlength="120"
                        placeholder="e.g. My Developer Portfolio"
                        class="h-11 rounded-xl border-[#DCE5E5] shadow-none focus-visible:ring-[#00695C]"
                    />

                    <p class="text-xs text-[#94A3B8]">
                        You can change the title later.
                    </p>

                    <DialogFooter class="gap-2 pt-5">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isCreatingPortfolio"
                            class="rounded-xl border-[#DCE5E5] bg-white"
                            @click="isUseModalOpen = false"
                        >
                            Cancel
                        </Button>

                        <Button
                            type="submit"
                            :disabled="isCreatingPortfolio || !portfolioTitle.trim()"
                            class="rounded-xl bg-[#00695C] font-semibold text-white hover:bg-[#00574D] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <LoaderCircle v-if="isCreatingPortfolio" class="mr-1.5 h-4 w-4 animate-spin" aria-hidden="true" />
                            {{ isCreatingPortfolio ? 'Creating portfolio…' : 'Create portfolio' }}
                            <span v-if="!isCreatingPortfolio" class="ml-1.5" aria-hidden="true">→</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
```