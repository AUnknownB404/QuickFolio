<script setup lang="ts">
import { getTemplateComponent } from '@/components/Templates/TemplateRegistry';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    adminPreview?: {
        userName: string;
        portfoliosUrl: string;
    };
}>();

const portfolioContent = computed(() => {
    const content: Record<string, Record<string, any>> = {};

    props.portfolio.sections.forEach((section) => {
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
        <div v-if="adminPreview" class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-950">
            <p>
                Admin preview of {{ adminPreview.userName }}'s {{ portfolio.status }} portfolio.
            </p>
            <Link :href="adminPreview.portfoliosUrl" class="font-semibold underline underline-offset-4">
                Back to their portfolios
            </Link>
        </div>

        <!-- Dynamic Template Rendering Based on Template Slug -->
        <main class="flex-1">
            <component
                :is="getTemplateComponent(portfolio.template.slug)"
                :content="portfolioContent"
                :design-settings="portfolio.design_settings"
                :portfolio="portfolio"
            />
        </main>

        <footer class="flex items-center justify-center gap-2 border-t py-8 text-center text-xs text-muted-foreground">
            <img src="/logo.svg" alt="" class="h-5 w-5 object-contain" />
            <span>Powered by <Link :href="route('home')" class="font-semibold text-primary hover:underline">quickFolio</Link> — Portfolio Builder & Template
            Marketplace
            </span>
        </footer>
    </div>
</template>
