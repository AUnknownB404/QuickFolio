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

const sendInlineEdit = (message: Record<string, unknown>) => {
    window.parent.postMessage({ type: 'quickfolio:inline-edit', ...message }, window.location.origin);
};

const sameMediaUrl = (storedValue: string, renderedValue: string) => {
    try {
        return new URL(storedValue, window.location.href).href === new URL(renderedValue, window.location.href).href;
    } catch {
        return storedValue.trim() === renderedValue.trim();
    }
};

const findMatchingField = (sectionKey: string | null, value: string, kind: 'text' | 'image') => {
    const sections = displayedPortfolio.value.sections.filter(
        (section) => sectionKey === null || section.section_key === sectionKey,
    );

    for (const section of sections) {
        for (const fieldValue of section.field_values) {
            const definition = fieldValue.field_definition;
            if (!definition) continue;
            const path: (string | number)[] = [];
            let match: (string | number)[] | null = null;

            const visit = (current: unknown) => {
                if (match) return;
                if (typeof current === 'string') {
                    const matches = kind === 'image'
                        ? sameMediaUrl(current, value)
                        : current.trim() === value.trim();
                    if (matches) match = [...path];
                    return;
                }
                if (Array.isArray(current)) {
                    current.forEach((item, index) => {
                        path.push(index);
                        visit(item);
                        path.pop();
                    });
                    return;
                }
                if (typeof current === 'object' && current !== null) {
                    Object.entries(current).forEach(([key, nestedValue]) => {
                        path.push(key);
                        visit(nestedValue);
                        path.pop();
                    });
                }
            };

            visit(fieldValue.value);
            if (match) {
                return {
                    sectionKey: section.section_key,
                    fieldDefinitionId: fieldValue.field_definition_id,
                    fieldKey: definition.field_key,
                    label: definition.label,
                    path: match,
                    kind,
                };
            }
        }
    }

    return null;
};

const sectionForElement = (element: Element): string | null => {
    let current: Element | null = element;
    while (current && current !== document.body) {
        const id = current.id.toLowerCase();
        const sectionKey = id === 'home' || id === 'hero'
            ? 'header'
            : id === 'experience'
                ? 'journey'
                : id === 'work'
                    ? 'projects'
                    : id;
        const matchingSection = displayedPortfolio.value.sections.find(
            (section) => section.section_key === sectionKey,
        );
        if (matchingSection) return matchingSection.section_key;
        current = current.parentElement;
    }
    return null;
};

const highlightSelectedElement = (element: HTMLElement) => {
    document.querySelectorAll<HTMLElement>('[data-quickfolio-selected]').forEach((selected) => {
        delete selected.dataset.quickfolioSelected;
        selected.style.outline = '';
        selected.style.outlineOffset = '';
    });
    element.dataset.quickfolioSelected = 'true';
    element.style.outline = '2px solid #0f9d82';
    element.style.outlineOffset = '3px';
};

const handleCanvasClick = (event: MouseEvent) => {
    if (!props.livePreview || !(event.target instanceof Element)) return;

    const image = event.target.closest('img');
    if (image) {
        const field = findMatchingField(sectionForElement(image), image.currentSrc || image.src, 'image');
        if (!field) {
            const sectionKey = sectionForElement(image);
            if (sectionKey) {
                event.preventDefault();
                event.stopPropagation();
                sendInlineEdit({ action: 'select-section', sectionKey });
            }
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        highlightSelectedElement(image);
        image.style.cursor = 'pointer';
        sendInlineEdit({ action: 'select', ...field });
        return;
    }

    let current: Element | null = event.target;
    let field: ReturnType<typeof findMatchingField> = null;
    while (current && current !== document.body) {
        const text = (current as HTMLElement).innerText?.trim();
        if (text) {
            field = findMatchingField(sectionForElement(current), text, 'text');
            if (field) break;
        }
        current = current.parentElement;
    }
    if (!field) {
        const sectionKey = sectionForElement(event.target);
        if (!sectionKey) return;
        event.preventDefault();
        event.stopPropagation();
        sendInlineEdit({ action: 'select-section', sectionKey });
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    sendInlineEdit({ action: 'select', ...field });
    const selectedElement = event.target.closest<HTMLElement>('h1, h2, h3, h4, p, a, span, li, strong');
    if (selectedElement) {
        highlightSelectedElement(selectedElement);
    }
};

onMounted(() => {
    if (props.livePreview) {
        window.addEventListener('message', handleLivePreviewMessage);
        document.addEventListener('click', handleCanvasClick, true);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('message', handleLivePreviewMessage);
    document.removeEventListener('click', handleCanvasClick, true);
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

    <div class="portfolio-page flex min-h-svh flex-col overflow-x-clip bg-background font-sans text-foreground selection:bg-primary selection:text-primary-foreground">
        <div
            v-if="adminPreview"
            class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-950"
        >
            <p>Admin preview of {{ adminPreview.userName }}'s {{ portfolio.status }} portfolio.</p>
            <Link :href="adminPreview.portfoliosUrl" class="font-semibold underline underline-offset-4"> Back to their portfolios </Link>
        </div>

        <!-- Dynamic Template Rendering Based on Template Slug -->
        <main class="portfolio-main min-w-0 flex-1">
            <component
                :is="getTemplateComponent(displayedPortfolio.template.slug)"
                :content="portfolioContent"
                :design-settings="displayedPortfolio.design_settings"
                :portfolio="displayedPortfolio"
            />
        </main>

        <footer class="portfolio-footer border-t border-slate-200/80 bg-white/90 px-5 py-5 text-slate-600 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 dark:text-slate-300 sm:px-8 sm:py-6">
            <div class="portfolio-footer-inner mx-auto flex w-full max-w-7xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <img src="/logo.svg" alt="" class="h-6 w-6 object-contain" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ portfolio.title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Portfolio powered by quickFolio</p>
                    </div>
                </div>
                <Link
                    :href="route('home')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-primary/40 hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:text-primary"
                >
                    Create your portfolio
                    <span aria-hidden="true">↗</span>
                </Link>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .portfolio-page {
        width: 100%;
        min-width: 0;
        overflow-x: clip;
    }

    .portfolio-main {
        min-width: 0;
        width: 100%;
    }

    .portfolio-footer {
        padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));
    }

    :deep(.ak-root),
    :deep(.bg-root),
    :deep(.cd-root),
    :deep(.em-root),
    :deep(.ie-root),
    :deep(.nb-root),
    :deep(.ng-root),
    :deep(.np-root),
    :deep(.ps-root),
    :deep(.sg-root),
    :deep(.gi-root),
    :deep(.ts-root),
    :deep(.ws-root),
    :deep(.sb-root),
    :deep(.do-root),
    :deep(.folio),
    :deep(.rail),
    :deep(.theme) {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        overflow-x: clip;
    }

    :deep(section),
    :deep(article),
    :deep(figure),
    :deep(header) {
        min-width: 0;
        max-width: 100%;
    }

    :deep(h1),
    :deep(h2),
    :deep(h3),
    :deep(p),
    :deep(a) {
        overflow-wrap: anywhere;
    }

    :deep(input),
    :deep(textarea),
    :deep(select) {
        font-size: 16px;
    }
}
</style>
