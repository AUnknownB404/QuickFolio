<script setup lang="ts">
import AdditionalPortfolioFields from '@/components/Templates/AdditionalPortfolioFields.vue';
import { getTemplateComponent } from '@/components/Templates/TemplateRegistry';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, LayoutTemplate } from 'lucide-vue-next';
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
        // Not used on this page. If your controller still sends it, remove it: it would be visible in a public page's source.
        email?: string;
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

/* -------------------------------------------------------------------------
 * Editor live preview — message protocol with the editor (unchanged)
 *   in : { type: 'quickfolio:live-preview', content, portfolio }
 *   out: { type: 'quickfolio:inline-edit', action: 'select' | 'select-section', ... }
 * ---------------------------------------------------------------------- */

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

type MatchedField = NonNullable<ReturnType<typeof findMatchingField>>;
type CanvasTarget =
    | { type: 'field'; field: MatchedField; element: HTMLElement | null }
    | { type: 'section'; sectionKey: string }
    | null;

/**
 * What would the editor edit if this element were clicked?
 * One resolver serves both hover (to show what is clickable) and click (to select it),
 * so what you see highlighted is always what you get.
 */
const resolveCanvasTarget = (target: Element): CanvasTarget => {
    const image = target.closest('img');
    if (image) {
        const sectionKey = sectionForElement(image);
        const field = findMatchingField(sectionKey, image.currentSrc || image.src, 'image');
        if (field) return { type: 'field', field, element: image };
        return sectionKey ? { type: 'section', sectionKey } : null;
    }

    let current: Element | null = target;
    while (current && current !== document.body) {
        const text = (current as HTMLElement).innerText?.trim();
        if (text) {
            const field = findMatchingField(sectionForElement(current), text, 'text');
            if (field) {
                return { type: 'field', field, element: target.closest<HTMLElement>('h1, h2, h3, h4, p, a, span, li, strong') };
            }
        }
        current = current.parentElement;
    }

    const sectionKey = sectionForElement(target);
    return sectionKey ? { type: 'section', sectionKey } : null;
};

const clearMarks = (attribute: 'quickfolioSelected' | 'quickfolioHover') => {
    const name = attribute === 'quickfolioSelected' ? 'data-quickfolio-selected' : 'data-quickfolio-hover';
    document.querySelectorAll<HTMLElement>(`[${name}]`).forEach((element) => {
        delete element.dataset[attribute];
    });
};

const highlightSelectedElement = (element: HTMLElement) => {
    clearMarks('quickfolioSelected');
    clearMarks('quickfolioHover');
    element.dataset.quickfolioSelected = 'true';
};

const handleCanvasClick = (event: MouseEvent) => {
    if (!props.livePreview || !(event.target instanceof Element)) return;

    const resolved = resolveCanvasTarget(event.target);

    if (resolved?.type === 'field') {
        event.preventDefault();
        event.stopPropagation();
        sendInlineEdit({ action: 'select', ...resolved.field });
        if (resolved.element) highlightSelectedElement(resolved.element);
        return;
    }

    if (resolved?.type === 'section') {
        event.preventDefault();
        event.stopPropagation();
        sendInlineEdit({ action: 'select-section', sectionKey: resolved.sectionKey });
        return;
    }

    // Nothing to edit here. Never let a stray link navigate the editor's preview away
    // (in-page #anchors are fine: they only scroll).
    const link = event.target.closest('a[href]');
    if (link && !(link.getAttribute('href') ?? '').startsWith('#')) event.preventDefault();
};

// Showing real contact-form submissions from inside the editor would message the owner's actual form.
const handleCanvasSubmit = (event: Event) => {
    if (props.livePreview) event.preventDefault();
};

/* Hover cue: dashed outline on whatever a click would edit */
let hoverFrame = 0;
let hoverElement: HTMLElement | null = null;

const clearHover = () => {
    hoverElement = null;
    clearMarks('quickfolioHover');
};

const handleCanvasHover = (event: MouseEvent) => {
    if (!props.livePreview || !(event.target instanceof Element)) return;
    const target = event.target;

    window.cancelAnimationFrame(hoverFrame);
    hoverFrame = window.requestAnimationFrame(() => {
        const resolved = resolveCanvasTarget(target);
        const element = resolved?.type === 'field' ? resolved.element : null;
        if (element === hoverElement) return;

        clearMarks('quickfolioHover');
        hoverElement = element;
        if (element && !element.dataset.quickfolioSelected) element.dataset.quickfolioHover = 'true';
    });
};

onMounted(() => {
    if (props.livePreview) {
        window.addEventListener('message', handleLivePreviewMessage);
        document.addEventListener('click', handleCanvasClick, true);
        document.addEventListener('submit', handleCanvasSubmit, true);
        document.addEventListener('mouseover', handleCanvasHover, true);
        document.documentElement.addEventListener('mouseleave', clearHover);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('message', handleLivePreviewMessage);
    document.removeEventListener('click', handleCanvasClick, true);
    document.removeEventListener('submit', handleCanvasSubmit, true);
    document.removeEventListener('mouseover', handleCanvasHover, true);
    document.documentElement.removeEventListener('mouseleave', clearHover);
    window.cancelAnimationFrame(hoverFrame);
});

/* -------------------------------------------------------------------------
 * Content
 * ---------------------------------------------------------------------- */

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

// A template that isn't registered used to leave visitors on a blank page.
const templateComponent = computed(() => getTemplateComponent(displayedPortfolio.value.template.slug));

/* -------------------------------------------------------------------------
 * What visitors, browsers and link previews see
 * The owner's name, not the portfolio's internal title ("My Minimal Portfolio").
 * ---------------------------------------------------------------------- */

const ownerName = computed(() => displayedPortfolio.value.user?.name || props.portfolio.user?.name || props.portfolio.title);
const pageTitle = computed(() => `${ownerName.value} — Portfolio`);

const firstTextMatching = (sectionKeys: string[], pattern: RegExp, minLength: number) => {
    for (const sectionKey of sectionKeys) {
        const section = portfolioContent.value[sectionKey];
        if (!isRecord(section)) continue;
        for (const [key, value] of Object.entries(section)) {
            if (pattern.test(key) && typeof value === 'string' && value.trim().length >= minLength) return value.trim();
        }
    }
    return '';
};

const description = computed(() => {
    const text = firstTextMatching(['about', 'header'], /(description|bio|summary|intro|tagline|subtitle)/i, 20).replace(/\s+/g, ' ');
    if (!text) return `${ownerName.value}’s portfolio.`;
    return text.length > 160 ? `${text.slice(0, 157).trimEnd()}…` : text;
});

const shareImage = computed(() => {
    const image = firstTextMatching(['header', 'about'], /(image|photo|avatar|picture)/i, 4);
    if (!/\.(png|jpe?g|webp|gif)(\?|$)/i.test(image)) return '';
    if (/^https?:\/\//i.test(image)) return image;
    return typeof window !== 'undefined' ? new URL(image, window.location.origin).href : '';
});

// Only a published, public page should be findable. Drafts, admin previews and the editor canvas should not.
const noIndex = computed(() => Boolean(props.livePreview || props.adminPreview) || props.portfolio.status !== 'published');
</script>

<template>
    <Head :title="pageTitle">
        <meta head-key="description" name="description" :content="description" />
        <meta head-key="og:title" property="og:title" :content="pageTitle" />
        <meta head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:type" property="og:type" content="profile" />
        <meta v-if="shareImage" head-key="og:image" property="og:image" :content="shareImage" />
        <meta head-key="twitter:card" name="twitter:card" :content="shareImage ? 'summary_large_image' : 'summary'" />
        <meta v-if="noIndex" head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>

    <div class="portfolio-page flex min-h-svh flex-col overflow-x-clip bg-background font-sans text-foreground selection:bg-primary selection:text-primary-foreground">
        <a
            href="#main-content"
            class="sr-only rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100]"
        >
            Skip to content
        </a>

        <div
            v-if="adminPreview"
            class="admin-banner flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-950"
            role="status"
        >
            <p>Admin preview of {{ adminPreview.userName }}’s {{ portfolio.status }} portfolio. It isn’t visible to the public unless it is published.</p>
            <Link :href="adminPreview.portfoliosUrl" class="font-semibold underline underline-offset-4"> Back to their portfolios </Link>
        </div>

        <main id="main-content" class="portfolio-main min-w-0 flex-1" tabindex="-1">
            <template v-if="templateComponent">
                <component
                    :is="templateComponent"
                    :content="portfolioContent"
                    :design-settings="displayedPortfolio.design_settings"
                    :portfolio="displayedPortfolio"
                />
                <AdditionalPortfolioFields :content="portfolioContent" />
            </template>

            <div v-else class="mx-auto flex max-w-md flex-col items-center px-6 py-24 text-center">
                <span class="grid h-14 w-14 place-items-center rounded-2xl bg-muted text-muted-foreground">
                    <LayoutTemplate class="h-6 w-6" aria-hidden="true" />
                </span>
                <h1 class="mt-5 text-lg font-bold">This portfolio can’t be shown right now</h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">Please try again later.</p>
            </div>
        </main>

        <!-- Not part of the owner's content, so it stays out of the editor canvas -->
        <footer
            v-if="!livePreview"
            class="portfolio-footer border-t border-slate-200/80 bg-white/90 px-5 py-4 text-slate-600 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 dark:text-slate-300 sm:px-8"
        >
            <div class="portfolio-footer-inner mx-auto flex w-full max-w-7xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex min-w-0 items-center gap-2.5 text-sm">
                    <img src="/logo.svg" alt="" class="h-5 w-5 shrink-0 object-contain" />
                    <span class="truncate">
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ ownerName }}</span>
                        <span class="text-slate-500 dark:text-slate-400"> · made with quickFolio</span>
                    </span>
                </p>
                <a
                    :href="route('home')"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-slate-700 transition hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:text-slate-200 dark:hover:text-primary"
                >
                    Make your own portfolio
                    <ArrowUpRight class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">(opens in a new tab)</span>
                </a>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Editor canvas: what a click would edit, and what is selected. */
:global([data-quickfolio-hover]) {
    outline: 2px dashed rgb(15 157 130 / 0.55);
    outline-offset: 3px;
    cursor: pointer;
}

:global([data-quickfolio-selected]) {
    outline: 2px solid #0f9d82 !important;
    outline-offset: 3px;
    cursor: pointer;
}

/* People print and save portfolios as PDFs: leave out everything that isn't theirs. */
@media print {
    .admin-banner,
    .portfolio-footer {
        display: none !important;
    }
}

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
