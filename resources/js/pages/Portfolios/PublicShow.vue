<script setup lang="ts">
import { getTemplateComponent } from '@/components/Templates/TemplateRegistry';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface FieldDefinition {
    id: number;
    field_key: string;
    label: string;
}

interface PortfolioFieldValue {
    field_definition_id: number;
    value: any;
    field_definition?: FieldDefinition;
}

interface PortfolioSection {
    id: number;
    section_key: string;
    is_enabled: boolean;
    field_values: PortfolioFieldValue[];
}

interface Portfolio {
    id: number;
    title: string;
    slug: string;
    status: string;
    design_settings?: Record<string, any>;
    template: {
        name: string;
        slug: string;
    };
    design_settings?: Record<string, any>;
    google_form_url: string | null;
    google_form_name_entry: string | null;
    google_form_email_entry: string | null;
    google_form_message_entry: string | null;
    sections: PortfolioSection[];
    user: {
        name: string;
        email: string;
    };
}

const props = defineProps<{
    portfolio: Portfolio;
    livePreview?: boolean;
    adminPreview?: {
        userName: string;
        portfoliosUrl: string;
    };
}>();

const livePreviewContent = ref<Record<string, Record<string, any>> | null>(null);
const livePreviewPortfolio = ref<Portfolio | null>(null);
const displayedPortfolio = computed(() => livePreviewPortfolio.value ?? props.portfolio);

const isRecord = (value: unknown): value is Record<string, any> => typeof value === 'object' && value !== null && !Array.isArray(value);

const isPortfolio = (value: unknown): value is Portfolio =>
    isRecord(value) &&
    typeof value.title === 'string' &&
    typeof value.slug === 'string' &&
    typeof value.status === 'string' &&
    isRecord(value.template) &&
    typeof value.template.slug === 'string' &&
    Array.isArray(value.sections);

const handleLivePreviewMessage = (event: MessageEvent<unknown>) => {
    if (!props.livePreview || event.origin !== window.location.origin || event.source !== window.parent) return;
    if (!isRecord(event.data) || event.data.type !== 'quickfolio:live-preview') return;
    if (!isRecord(event.data.content) || !isPortfolio(event.data.portfolio)) return;

    livePreviewContent.value = event.data.content;
    livePreviewPortfolio.value = event.data.portfolio;
};

onMounted(() => {
    if (props.livePreview) window.addEventListener('message', handleLivePreviewMessage);
});

onBeforeUnmount(() => {
    window.removeEventListener('message', handleLivePreviewMessage);
});

const portfolioContent = computed(() => {
    if (livePreviewContent.value) return livePreviewContent.value;

    const content: Record<string, Record<string, any>> = {};

    displayedPortfolio.value.sections.forEach((section) => {
        if (!section.is_enabled) return;

        const sectionContent: Record<string, any> = {};
        section.field_values.forEach((fieldValue) => {
            const fieldKey = fieldValue.field_definition?.field_key;
            if (fieldKey) {
                sectionContent[fieldKey] = fieldValue.value;
            }
        });
        content[section.section_key] = sectionContent;
    });

    return content;
});
</script>

<template>
    <Head :title="`${portfolio.title} — Portfolio`" />

    <div class="flex min-h-screen flex-col bg-background font-sans text-foreground selection:bg-primary selection:text-primary-foreground">
        <div
            v-if="livePreview"
            class="flex flex-wrap items-center justify-between gap-3 border-b border-sky-200 bg-sky-50 px-5 py-3 text-sm text-sky-950"
        >
            <p>
                Live preview — updates as you edit.
                {{ portfolio.status === 'published' ? 'Your portfolio is currently public.' : 'Publish when you are ready to make it public.' }}
            </p>
        </div>
        <div
            v-if="adminPreview"
            class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-950"
        >
            <p>Admin preview of {{ adminPreview.userName }}'s {{ portfolio.status }} portfolio.</p>
            <Link :href="adminPreview.portfoliosUrl" class="font-semibold underline underline-offset-4"> Back to their portfolios </Link>
        </div>

        <!-- Dynamic Template Rendering Based on Template Slug -->
        <main class="flex-1">
            <component
                :is="getTemplateComponent(displayedPortfolio.template.slug)"
                :content="portfolioContent"
                :design-settings="displayedPortfolio.design_settings"
                :portfolio="displayedPortfolio"
            />
        </main>

        <footer class="flex items-center justify-center gap-2 border-t py-8 text-center text-xs text-muted-foreground">
            <img src="/logo.svg" alt="" class="h-5 w-5 object-contain" />
            <span
                >Powered by <Link :href="route('home')" class="font-semibold text-primary hover:underline">quickFolio</Link> — Portfolio Builder &
                Template Marketplace
            </span>
        </footer>
    </div>
</template>
