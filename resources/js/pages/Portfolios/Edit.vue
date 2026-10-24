<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import {
    ArrowLeft,
    ArrowUpRight,
    Check,
    ChevronDown,
    Download,
    Eye,
    Globe2,
    Image as ImageIcon,
    LayoutTemplate,
    LoaderCircle,
    Plus,
    Save,
    Settings2,
    Sparkles,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface FieldDefinition {
    id: number;
    field_key: string;
    label: string;
    data_type: string;
    is_repeatable: boolean;
}

interface PortfolioFieldValue {
    id?: number;
    field_definition_id: number;
    value: any;
    field_definition?: FieldDefinition;
}

interface PortfolioSection {
    id: number;
    section_key: string;
    is_enabled: boolean;
    display_order: number;
    field_values: PortfolioFieldValue[];
}

interface Template {
    id: number;
    name: string;
    slug: string;
    sections: {
        section_key: string;
        is_enabled: boolean;
        fields: {
            is_required: boolean;
            is_visible: boolean;
            field_definition: FieldDefinition;
        }[];
    }[];
}

interface Portfolio {
    id: number;
    title: string;
    slug: string;
    status: string;
    google_form_url: string | null;
    google_form_name_entry: string | null;
    google_form_email_entry: string | null;
    google_form_message_entry: string | null;
    template: Template;
    sections: PortfolioSection[];
}

interface FormFieldValue {
    field_definition_id: number;
    value: any;
}

interface FormSection {
    id: number;
    section_key: string;
    is_enabled: boolean;
    field_values: FormFieldValue[];
}

interface PendingUpload {
    file: File;
    type: 'image' | 'file';
    uploadKey: string;
    previewUrl?: string;
}

const props = defineProps<{
    portfolio: Portfolio;
    availableTemplates: { id: number; name: string; slug: string; category?: { name: string } }[];
    fieldDefinitions: FieldDefinition[];
}>();

const page = usePage();

const hiddenNavbarFieldKeys = new Set(['nav_links', 'cta_text', 'cta_url']);

/**
 * Display order of the editor sections.
 * Navbar first, then header/hero, about, ... and Contact always last
 * (the "Contact integration" tab is appended after it in the template).
 * Any section key not listed here is placed just before Contact.
 */
const sectionOrder = ['navbar', 'header', 'hero', 'about', 'journey', 'skills', 'projects', 'services', 'contact'];

const sectionRank = (key: string) => {
    if (key === 'contact') return 999;
    const index = sectionOrder.indexOf(key);
    return index === -1 ? 500 : index;
};

const sortSections = <T extends { section_key: string }>(sections: T[]): T[] =>
    [...sections].sort((a, b) => sectionRank(a.section_key) - sectionRank(b.section_key));

/**
 * Every field definition (all 48) belongs to exactly one editor section,
 * determined from its field_key. This makes the editor show ALL fields of a
 * section even when the template does not list them.
 */
const navbarFieldKeys = new Set(['logo_text', 'logo_image', 'sticky_nav', 'show_logo']);

const fieldGroupFor = (fieldKey: string) => {
    if (navbarFieldKeys.has(fieldKey)) return 'navbar';
    const prefix = fieldKey.split('_')[0];
    return ['nav', 'cta', 'logo', 'sticky', 'show'].includes(prefix) ? 'navbar' : prefix;
};

const sectionGroupFor = (sectionKey: string) => ({ header: 'hero', nav: 'navbar' } as Record<string, string>)[sectionKey] ?? sectionKey;

/**
 * Fields shown for a section: the template's configured fields (keeping their
 * is_required flag) plus every remaining field definition of that section.
 * Sorted by field definition id, so the order matches the database.
 */
const fieldsForSection = (sectionKey: string) => {
    const templateSection = props.portfolio.template.sections.find((ts) => ts.section_key === sectionKey);
    const configured = (templateSection?.fields ?? []).map((field) => ({ ...field, is_visible: true }));
    const group = sectionGroupFor(sectionKey);
    const extra = props.fieldDefinitions
        .filter((fd) =>
            fieldGroupFor(fd.field_key) === group &&
            !configured.some((field) => field.field_definition.id === fd.id),
        )
        .map((fd) => ({ is_required: false, is_visible: true, field_definition: fd }));

    return [...configured, ...extra].sort((a, b) => a.field_definition.id - b.field_definition.id);
};

const socialPlatforms = [
    { value: 'github', label: 'GitHub' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'x', label: 'X' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'youtube', label: 'YouTube' },
    { value: 'dribbble', label: 'Dribbble' },
    { value: 'behance', label: 'Behance' },
    { value: 'website', label: 'Website' },
    { value: 'email', label: 'Email' },
];

const fieldDefMap = computed(() => {
    const map = new Map<number, FieldDefinition>();
    props.fieldDefinitions.forEach((fd) => map.set(fd.id, fd));
    return map;
});

const defaultArrayItem = (fieldKey: string) => {
    switch (fieldKey) {
        case 'skills_list':
            return { label: '', percent: 100, icon: '' };
        case 'journey_list':
            return { period: '', title: '', company: '', description: '' };
        case 'projects_list':
            return { title: '', category: 'Website Design', description: '', image: '', url: '', featured: false };
        case 'hero_social_links':
            return { platform: 'github', url: '' };
        case 'about_highlights':
            return '';
        case 'services_list':
            return { icon: '', title: '', description: '', url: '' };
        case 'nav_links':
        case 'contact_social_links':
            return { label: '', url: '' };
        default:
            return { value: '' };
    }
};

const booleanDefaults = new Set(['contact_form_enabled', 'show_logo']);

const defaultValueFor = (dataType?: string, fieldKey?: string) => {
    if (dataType === 'array') return [defaultArrayItem(fieldKey || '')];
    if (dataType === 'boolean') return booleanDefaults.has(fieldKey || '');
    return '';
};

const normalizeAboutHighlights = (value: any): string[] => {
    if (!Array.isArray(value)) return typeof value === 'string' ? [value] : [];

    return value
        .map((item) => {
            if (typeof item === 'string') return item.trim();
            if (!item || typeof item !== 'object') return '';

            return [item.value, item.label, item.title]
                .filter((part) => typeof part === 'string' || typeof part === 'number')
                .map((part) => String(part).trim())
                .filter(Boolean)
                .join(' ');
        })
        .filter(Boolean);
};

const normalizeHeroSocialLinks = (value: any) => {
    if (!Array.isArray(value)) return value;

    return value.map((item) => {
        if (typeof item === 'string') return { platform: 'website', url: item };

        const legacyPlatform = String(item?.platform ?? item?.label ?? item?.name ?? '')
            .toLowerCase()
            .replace(/[^a-z]/g, '');
        const platform = legacyPlatform === 'twitter'
            ? 'x'
            : socialPlatforms.find((option) => option.value === legacyPlatform)?.value ?? 'website';

        return { ...item, platform };
    });
};

const buildSections = (): FormSection[] =>
    sortSections(props.portfolio.sections).map((sec) => {
        const field_values: FormFieldValue[] = sec.field_values.map((fv) => {
            const fd = fieldDefMap.value.get(fv.field_definition_id);
            const value = (fv.value !== null && fv.value !== undefined && (!Array.isArray(fv.value) || fv.value.length > 0))
                ? fv.value
                : defaultValueFor(fd?.data_type, fd?.field_key);
            const normalizedValue = fd?.field_key === 'about_highlights'
                ? normalizeAboutHighlights(value)
                : value;
            return {
                field_definition_id: fv.field_definition_id,
                value: fd?.field_key === 'hero_social_links' ? normalizeHeroSocialLinks(normalizedValue) : normalizedValue,
            };
        });

        fieldsForSection(sec.section_key).forEach((f) => {
            if (!field_values.some((v) => v.field_definition_id === f.field_definition.id)) {
                field_values.push({
                    field_definition_id: f.field_definition.id,
                    value: defaultValueFor(f.field_definition.data_type, f.field_definition.field_key),
                });
            }
        });

        return {
            id: sec.id,
            section_key: sec.section_key,
            is_enabled: sec.is_enabled,
            field_values,
        };
    });

const form = useForm<Record<string, any>>({
    sections: buildSections(),
    google_form_url: props.portfolio.google_form_url || '',
    google_form_name_entry: props.portfolio.google_form_name_entry || '',
    google_form_email_entry: props.portfolio.google_form_email_entry || '',
    google_form_message_entry: props.portfolio.google_form_message_entry || '',
});

const pendingUploadPrefix = 'quickfolio-pending-upload:';
const maxUploadBytes = 10 * 1024 * 1024;
const uploadingFiles = ref<Record<string, boolean>>({});
const uploadErrors = ref<Record<string, string>>({});
const uploadedFiles = ref<Record<string, string>>({});
const pendingUploads = ref<Record<string, PendingUpload>>({});
const hasPendingUploads = computed(() => Object.values(uploadingFiles.value).some(Boolean));

const pendingUploadId = (value: unknown): string | null => {
    if (typeof value !== 'string' || !value.startsWith(pendingUploadPrefix)) return null;
    const id = value.slice(pendingUploadPrefix.length);
    return /^[a-f0-9]{32}$/.test(id) ? id : null;
};

const discardPendingUpload = (uploadId: string) => {
    const upload = pendingUploads.value[uploadId];
    if (upload?.previewUrl) URL.revokeObjectURL(upload.previewUrl);
    delete pendingUploads.value[uploadId];
};

const discardPendingUploads = (value: unknown) => {
    if (Array.isArray(value)) {
        value.forEach(discardPendingUploads);
        return;
    }
    if (value && typeof value === 'object') {
        Object.values(value).forEach(discardPendingUploads);
        return;
    }

    const uploadId = pendingUploadId(value);
    if (uploadId) discardPendingUpload(uploadId);
};

const clearPendingUploads = () => {
    Object.keys(pendingUploads.value).forEach(discardPendingUpload);
    uploadedFiles.value = {};
    uploadErrors.value = {};
};

const assetPreviewUrl = (value: unknown): string => {
    const uploadId = pendingUploadId(value);
    if (uploadId) return pendingUploads.value[uploadId]?.previewUrl || '';
    return typeof value === 'string' ? value : '';
};

const removeImage = (value: unknown, onRemoved: () => void) => {
    discardPendingUploads(value);
    onRemoved();
};

const compressImage = async (file: File): Promise<File> => {
    if (file.type === 'image/gif') return file;

    const bitmap = await createImageBitmap(file);
    try {
        const maxDimension = 1920;
        const scale = Math.min(1, maxDimension / bitmap.width, maxDimension / bitmap.height);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));

        const context = canvas.getContext('2d');
        if (!context) throw new Error('Could not prepare the image for compression.');
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        const compressed = await new Promise<Blob>((resolve, reject) => {
            canvas.toBlob(
                (blob) => blob
                    ? resolve(blob)
                    : reject(new Error('Could not compress this image. Please try another image.')),
                'image/webp',
                0.82,
            );
        });
        if (compressed.size >= file.size) return file;

        const extension = compressed.type === 'image/webp'
            ? '.webp'
            : compressed.type === 'image/png'
                ? '.png'
                : '.jpg';

        return new File(
            [compressed],
            `${file.name.replace(/\.[^.]+$/, '')}${extension}`,
            { type: compressed.type, lastModified: file.lastModified },
        );
    } finally {
        bitmap.close();
    }
};

const uploadFile = async (
    event: Event,
    uploadKey: string,
    type: 'image' | 'file',
    currentValue: () => unknown,
    onQueued: (value: string) => void,
) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    uploadingFiles.value[uploadKey] = true;
    delete uploadErrors.value[uploadKey];
    delete uploadedFiles.value[uploadKey];

    try {
        if (file.size > maxUploadBytes) {
            throw new Error('Files must be 10 MB or smaller.');
        }

        const preparedFile = type === 'image' ? await compressImage(file) : file;
        discardPendingUploads(currentValue());

        const uploadId = crypto.randomUUID().replace(/-/g, '');
        const previewUrl = URL.createObjectURL(preparedFile);
        pendingUploads.value[uploadId] = { file: preparedFile, type, uploadKey, previewUrl };
        onQueued(`${pendingUploadPrefix}${uploadId}`);
        uploadedFiles.value[uploadKey] = file.name;
    } catch (error) {
        uploadErrors.value[uploadKey] = error instanceof Error
            ? error.message
            : 'Could not prepare this file. Please try again.';
    } finally {
        uploadingFiles.value[uploadKey] = false;
        input.value = '';
    }
};

onBeforeUnmount(() => {
    clearPendingUploads();
});

const firstSectionKey = () => form.sections[0]?.section_key || 'navbar';

const activeSectionKey = ref(firstSectionKey());
const isSwitchModalOpen = ref(false);
const selectedTemplateId = ref(props.portfolio.template.id);

watch(
    () => props.portfolio,
    () => {
        clearPendingUploads();
        form.sections = buildSections();
        form.google_form_url = props.portfolio.google_form_url || '';
        form.google_form_name_entry = props.portfolio.google_form_name_entry || '';
        form.google_form_email_entry = props.portfolio.google_form_email_entry || '';
        form.google_form_message_entry = props.portfolio.google_form_message_entry || '';
        form.defaults();
        selectedTemplateId.value = props.portfolio.template.id;
        if (
            activeSectionKey.value !== 'contact-integration' &&
            !props.portfolio.sections.some((s) => s.section_key === activeSectionKey.value)
        ) {
            activeSectionKey.value = firstSectionKey();
        }
    },
);

const activeFields = computed(() => fieldsForSection(activeSectionKey.value));

const activePortfolioSection = computed(() =>
    form.sections.find((s: FormSection) => s.section_key === activeSectionKey.value),
);

const activeValues = computed<Record<number, FormFieldValue>>(() => {
    const result: Record<number, FormFieldValue> = {};
    activePortfolioSection.value?.field_values.forEach((fv: FormFieldValue) => {
        result[fv.field_definition_id] = fv;
    });
    return result;
});

const addArrayItem = (fieldDefId: number, fieldKey: string) => {
    const fv = activeValues.value[fieldDefId];
    if (!fv) return;
    if (!Array.isArray(fv.value)) fv.value = [];
    fv.value.push(defaultArrayItem(fieldKey));
};

const removeArrayItem = (fieldDefId: number, index: number) => {
    const fv = activeValues.value[fieldDefId];
    if (fv && Array.isArray(fv.value)) {
        discardPendingUploads(fv.value[index]);
        fv.value.splice(index, 1);
    }
};

const savePortfolio = () => {
    if (hasPendingUploads.value) return;

    const uploads = pendingUploads.value;
    const uploadIds = Object.keys(uploads);

    form.transform((data) => ({
        ...data,
        _method: 'patch',
        ...(uploadIds.length > 0
            ? {
                pending_uploads: Object.fromEntries(uploadIds.map((id) => [id, uploads[id].file])),
                pending_upload_types: Object.fromEntries(uploadIds.map((id) => [id, uploads[id].type])),
            }
            : {}),
    })).post(route('portfolios.update', props.portfolio.id as any), {
        preserveScroll: true,
        onSuccess: clearPendingUploads,
        onError: (errors) => {
            uploadIds.forEach((id) => {
                const upload = uploads[id];
                const message = errors[`pending_uploads.${id}`] || errors.pending_uploads;
                if (upload && typeof message === 'string') {
                    uploadErrors.value[upload.uploadKey] = message;
                }
            });
        },
    });
};

const publishForm = useForm({});
const publishPortfolio = () => {
    publishForm.post(route('portfolios.publish', props.portfolio.id as any), {
        preserveScroll: true,
    });
};

const switchForm = useForm({ template_id: props.portfolio.template.id });
const switchTemplate = () => {
    switchForm.template_id = selectedTemplateId.value;
    switchForm.post(route('portfolios.switch-template', props.portfolio.id as any), {
        onSuccess: () => {
            isSwitchModalOpen.value = false;
        },
    });
};

const humanize = (value: string) =>
    value.replace(/[-_]/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

const sidebarSectionLabel = (sectionKey: string) => humanize(sectionKey);

const sectionFieldCount = (sectionKey: string) => {
    return fieldsForSection(sectionKey).filter((field) =>
        !(sectionKey === 'navbar' && hiddenNavbarFieldKeys.has(field.field_definition.field_key)),
    ).length;
};

const currentSectionLabel = computed(() =>
    activeSectionKey.value === 'contact-integration'
        ? 'Contact form integration'
        : humanize(activeSectionKey.value),
);
</script>

<template>
    <Head :title="`Editing ${portfolio.title} — quickFolio`" />

    <div class="min-h-screen overflow-x-clip bg-[#F4F7F7] dark:bg-slate-950 text-[#0F172A] dark:text-slate-100 transition-colors duration-200">
        <!-- Custom quickFolio editor navigation; no Laravel default layout -->
        <header class="sticky top-0 z-40 border-b border-[#E1E8E7] dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur">
            <div class="mx-auto flex min-h-[68px] max-w-[1800px] flex-wrap items-center justify-between gap-2 px-3 py-2 sm:flex-nowrap sm:gap-3 sm:px-5 xl:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <Link
                        :href="route('dashboard')"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#E1E8E7] dark:border-slate-800 bg-white dark:bg-slate-900 text-[#64748B] dark:text-slate-400 transition hover:border-[#BFD4CE] dark:border-slate-700 hover:text-[#00695C]"
                        aria-label="Back to dashboard"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Link>

                    <div class="hidden h-8 w-px bg-[#E1E8E7] sm:block" />

                    <Link href="/" class="hidden shrink-0 items-center sm:flex">
                        <AppLogo size="sm" />
                    </Link>

                    <div class="min-w-0 sm:ml-2">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="hidden text-[#CBD5D1] dark:text-slate-600 sm:inline">/</span>
                            <h1 class="max-w-[180px] truncate text-sm font-bold text-[#0F172A] dark:text-slate-100 sm:max-w-[260px] sm:text-base">
                                {{ portfolio.title }}
                            </h1>
                            <span
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2 py-1 text-[10px] font-semibold capitalize sm:text-xs"
                                :class="portfolio.status === 'published'
                                    ? 'border-[#C7E6D8] dark:border-emerald-900 bg-[#EFFAF4] dark:bg-emerald-950/40 text-[#16734A]'
                                    : 'border-[#F0DDB8] dark:border-amber-900 bg-[#FFF8E9] dark:bg-amber-950/40 text-[#9A6700]'"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="portfolio.status === 'published' ? 'bg-[#16A34A]' : 'bg-[#D99A18]'" />
                                {{ portfolio.status }}
                            </span>
                        </div>
                        <p class="mt-0.5 hidden text-xs text-[#94A3B8] dark:text-slate-500 sm:block">
                            Editing with {{ portfolio.template.name }}
                        </p>
                    </div>
                </div>

                <div class="flex max-w-full shrink-0 items-center gap-1.5 sm:gap-2">
                    <button v-if="page.props.auth.user.role === 'admin'"
                        type="button"
                        class="hidden h-10 items-center gap-2 rounded-xl border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm font-semibold text-[#475569] dark:text-slate-300 transition hover:bg-[#F8FBFA] dark:bg-slate-800/70 md:inline-flex"
                        @click="isSwitchModalOpen = true"
                    >
                        <LayoutTemplate class="h-4 w-4 text-[#00695C]" />
                        <span class="hidden lg:inline">Change template</span>
                        <span class="lg:hidden">Template</span>
                        <ChevronDown class="h-3.5 w-3.5" />
                    </button>

                    <a
                        v-if="portfolio.status === 'published'"
                        :href="route('portfolios.published', portfolio.slug as any)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-slate-900 text-[#00695C] transition hover:bg-[#F0F7F5] dark:bg-teal-950/50 sm:w-auto sm:px-3"
                        title="View live portfolio"
                    >
                        <Eye class="h-4 w-4" />
                        <span class="ml-2 hidden text-sm font-semibold sm:inline">View live</span>
                        <ArrowUpRight class="ml-1 hidden h-3.5 w-3.5 sm:inline" />
                    </a>

                    <a
                        :href="route('portfolios.download', portfolio.id as any)"
                        class="inline-flex h-10 w-10 items-center justify-center gap-2 rounded-xl border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-slate-900 text-[#00695C] transition hover:bg-[#F0F7F5] dark:bg-teal-950/50 sm:w-auto sm:px-3"
                        title="Download portfolio as ZIP"
                    >
                        <Download class="h-4 w-4" />
                        <span class="hidden text-sm font-semibold sm:inline">Download ZIP</span>
                    </a>

                    <button
                        type="button"
                        :disabled="publishForm.processing"
                        class="hidden h-10 items-center gap-2 rounded-xl border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm font-semibold text-[#475569] dark:text-slate-300 transition hover:bg-[#F8FBFA] dark:bg-slate-800/70 sm:inline-flex disabled:cursor-not-allowed disabled:opacity-50"
                        @click="publishPortfolio"
                    >
                        <Globe2 class="h-4 w-4 text-[#00695C]" />
                        {{ portfolio.status === 'published' ? 'Unpublish' : 'Publish' }}
                    </button>

                    <button
                        type="button"
                        :disabled="form.processing || hasPendingUploads"
                        class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#00695C] px-3.5 text-sm font-semibold text-white transition hover:bg-[#00574C] disabled:cursor-not-allowed disabled:opacity-50 sm:px-4"
                        @click="savePortfolio"
                    >
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ form.processing ? 'Saving…' : 'Save changes' }}</span>
                        <span class="sm:hidden">{{ form.processing ? 'Saving' : 'Save' }}</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Editor workspace -->
        <main class="mx-auto grid w-full max-w-[1800px] min-w-0 grid-cols-1 gap-4 px-3 py-4 sm:gap-5 sm:px-5 sm:py-5 lg:grid-cols-[210px_minmax(0,1fr)] lg:gap-5 xl:grid-cols-[220px_minmax(0,1fr)_280px] xl:px-6 xl:py-6">
            <!-- Section navigation -->
            <aside class="min-w-0">
                <div class="rounded-2xl border border-[#E1E8E7] dark:border-slate-800 bg-white dark:bg-slate-900 p-3 shadow-[0_2px_8px_rgba(15,23,42,0.025)] lg:sticky lg:top-[92px]">
                    <div class="px-3 pb-3 pt-2">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] dark:text-slate-500">Workspace</p>
                        <h2 class="mt-1 text-sm font-bold text-[#0F172A] dark:text-slate-100">Portfolio sections</h2>
                    </div>

                    <nav class="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible" aria-label="Portfolio sections">
                        <button
                            v-for="sec in form.sections"
                            :key="sec.id"
                            type="button"
                            class="flex min-w-max items-center gap-3 rounded-xl px-3 py-3 text-left transition lg:w-full"
                            :class="activeSectionKey === sec.section_key
                                ? 'bg-[#E8F4F1] dark:bg-teal-900/50 text-[#00695C]'
                                : 'text-[#64748B] dark:text-slate-400 hover:bg-[#F4F7F7] dark:bg-slate-950 hover:text-[#0F172A] dark:text-slate-100'"
                            @click="activeSectionKey = sec.section_key"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="activeSectionKey === sec.section_key ? 'bg-white dark:bg-slate-900 text-[#00695C]' : 'bg-[#F4F7F7] dark:bg-slate-950 text-[#94A3B8] dark:text-slate-500'"
                            >
                                <LayoutTemplate class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1 break-words">
                                <span class="block text-sm font-semibold">{{ sidebarSectionLabel(sec.section_key) }}</span>
                                <span class="mt-0.5 block text-[11px]" :class="activeSectionKey === sec.section_key ? 'text-[#47877D]' : 'text-[#94A3B8] dark:text-slate-500'">
                                    {{ sectionFieldCount(sec.section_key) }} fields
                                </span>
                            </span>
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                :class="sec.is_enabled ? 'bg-[#22A06B]' : 'bg-[#CBD5E1]'"
                                :title="sec.is_enabled ? 'Section enabled' : 'Section disabled'"
                            />
                        </button>

                        <button
                            type="button"
                            class="flex min-w-max items-center gap-3 rounded-xl px-3 py-3 text-left transition lg:w-full"
                            :class="activeSectionKey === 'contact-integration'
                                ? 'bg-[#E8F4F1] dark:bg-teal-900/50 text-[#00695C]'
                                : 'text-[#64748B] dark:text-slate-400 hover:bg-[#F4F7F7] dark:bg-slate-950 hover:text-[#0F172A] dark:text-slate-100'"
                            @click="activeSectionKey = 'contact-integration'"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="activeSectionKey === 'contact-integration' ? 'bg-white dark:bg-slate-900 text-[#00695C]' : 'bg-[#F4F7F7] dark:bg-slate-950 text-[#94A3B8] dark:text-slate-500'"
                            >
                                <Settings2 class="h-4 w-4" />
                            </span>
                            <span class="text-sm font-semibold">Contact integration</span>
                        </button>
                    </nav>

                    <div class="mt-4 hidden rounded-xl bg-[#F4F7F7] dark:bg-slate-950 p-3 lg:block">
                        <div class="flex items-center gap-2 text-xs font-semibold text-[#475569] dark:text-slate-300">
                            <Sparkles class="h-4 w-4 text-[#00695C]" />
                            Quick tip
                        </div>
                        <p class="mt-1.5 text-xs leading-5 text-[#64748B] dark:text-slate-400">
                            Keep your content concise. You can save changes as you work.
                        </p>
                    </div>
                </div>
            </aside>

            <!-- Main editor panel -->
            <section class="min-w-0 space-y-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-[#00695C]">EDITOR / CONTENT</p>
                        <h2 class="mt-1 text-xl font-bold tracking-tight sm:text-2xl text-[#0F172A] dark:text-slate-100">
                            {{ currentSectionLabel }}
                        </h2>
                        <p class="mt-1.5 text-sm leading-6 text-[#64748B] dark:text-slate-400">
                            {{ activeSectionKey === 'contact-integration'
                                ? 'Connect a Google Form to receive messages from your portfolio.'
                                : `Customize the content displayed in your ${humanize(activeSectionKey).toLowerCase()} section.` }}
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E1E8E7] dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-1.5 text-xs font-medium text-[#64748B] dark:text-slate-400">
                        <Check class="h-3.5 w-3.5 text-[#16A34A]" />
                        {{ form.isDirty ? 'Unsaved changes' : 'All changes saved' }}
                    </span>
                </div>

                <!-- Google Forms integration -->
                <div v-if="activeSectionKey === 'contact-integration'" class="overflow-hidden rounded-2xl border border-[#E1E8E7] dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_2px_8px_rgba(15,23,42,0.025)]">
                    <div class="border-b border-[#EDF1F0] dark:border-slate-800 px-5 py-5 sm:px-6">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F4F1] dark:bg-teal-900/50 text-[#00695C]">
                                <Settings2 class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="font-bold text-[#0F172A] dark:text-slate-100">Google Forms connection</h3>
                                <p class="mt-1 text-sm leading-5 text-[#64748B] dark:text-slate-400">
                                    Connect a form to receive portfolio contact submissions.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 p-5 sm:p-6">
                        <div class="rounded-xl border border-[#DCEBE6] dark:border-teal-900 bg-[#F5FAF8] dark:bg-teal-950/30 p-4 text-sm leading-6 text-[#526B65] dark:text-slate-300">
                            Create a Google Form with short-answer questions for name, email, and message. Use
                            <span class="font-semibold text-[#0F172A] dark:text-slate-100">More → Pre-fill form</span> to generate a link.
                            The URL contains the numeric question IDs after each <code class="rounded bg-white dark:bg-slate-900 px-1.5 py-0.5 text-xs text-[#00695C]">entry.</code>
                            Paste that URL below and enter the matching IDs.
                        </div>

                        <div class="space-y-2">
                            <Label for="google_form_url" class="text-sm font-semibold text-[#334155] dark:text-slate-200">Pre-filled Google Form URL</Label>
                            <Input
                                id="google_form_url"
                                v-model="form.google_form_url"
                                type="url"
                                placeholder="https://docs.google.com/forms/d/e/…/viewform?usp=pp_url"
                                class="h-11 rounded-xl border-[#DCE6E4] dark:border-slate-700 bg-white focus-visible:ring-[#00695C]/30 dark:focus-visible:ring-teal-400/30 text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                            />
                            <p v-if="form.errors.google_form_url" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.google_form_url }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="google_form_name_entry" class="text-sm font-semibold text-[#334155] dark:text-slate-200">Name field ID</Label>
                                <Input id="google_form_name_entry" v-model="form.google_form_name_entry" inputmode="numeric" placeholder="123456789" class="h-11 rounded-xl border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                <p v-if="form.errors.google_form_name_entry" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.google_form_name_entry }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="google_form_email_entry" class="text-sm font-semibold text-[#334155] dark:text-slate-200">Email field ID</Label>
                                <Input id="google_form_email_entry" v-model="form.google_form_email_entry" inputmode="numeric" placeholder="234567890" class="h-11 rounded-xl border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                <p v-if="form.errors.google_form_email_entry" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.google_form_email_entry }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="google_form_message_entry" class="text-sm font-semibold text-[#334155] dark:text-slate-200">Message field ID</Label>
                                <Input id="google_form_message_entry" v-model="form.google_form_message_entry" inputmode="numeric" placeholder="345678901" class="h-11 rounded-xl border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                <p v-if="form.errors.google_form_message_entry" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.google_form_message_entry }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 border-t border-[#EDF1F0] dark:border-slate-800 pt-5">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#E8F4F1] dark:bg-teal-900/50 text-[#00695C]">
                                <Check class="h-3 w-3" />
                            </span>
                            <p class="text-xs leading-5 text-[#64748B] dark:text-slate-400">
                                Leave all four fields empty to disable Google Forms. Save changes to apply the connection.
                                This setup is included in the downloaded portfolio.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Dynamic template section fields -->
                <div
                    v-if="activePortfolioSection"
                    class="overflow-hidden rounded-2xl border border-[#E1E8E7] dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_2px_8px_rgba(15,23,42,0.025)]"
                >
                    <div class="flex flex-col gap-4 border-b border-[#EDF1F0] dark:border-slate-800 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F4F1] dark:bg-teal-900/50 text-[#00695C]">
                                <LayoutTemplate class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="font-bold text-[#0F172A] dark:text-slate-100">{{ currentSectionLabel }} content</h3>
                                <p class="mt-1 text-sm text-[#64748B] dark:text-slate-400">Using the {{ portfolio.template.name }} template</p>
                            </div>
                        </div>

                        <label class="inline-flex cursor-pointer items-center gap-3 self-start rounded-xl border border-[#E1E8E7] dark:border-slate-800 bg-[#F8FAFA] dark:bg-slate-800/70 px-3 py-2.5 sm:self-auto">
                            <span class="text-sm font-semibold text-[#475569] dark:text-slate-300">Section enabled</span>
                            <input
                                id="enable_section"
                                v-model="activePortfolioSection.is_enabled"
                                type="checkbox"
                                class="peer sr-only"
                            />
                            <span class="relative h-5 w-9 rounded-full bg-[#CBD5E1] transition peer-checked:bg-[#00695C] after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white dark:bg-slate-900 after:shadow-sm after:transition after:content-[''] peer-checked:after:translate-x-4" />
                        </label>
                    </div>

                    <div class="p-5 sm:p-6">
                        <div
                            v-if="!activePortfolioSection.is_enabled"
                            class="rounded-xl border border-dashed border-[#DCE6E4] dark:border-slate-700 bg-[#F8FAFA] dark:bg-slate-800/70 px-5 py-12 text-center"
                        >
                            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-white dark:bg-slate-900 text-[#94A3B8] dark:text-slate-500 shadow-sm">
                                <Eye class="h-5 w-5" />
                            </span>
                            <h4 class="mt-4 font-semibold text-[#334155] dark:text-slate-200">This section is hidden</h4>
                            <p class="mt-1 text-sm text-[#64748B] dark:text-slate-400">Enable this section to show it on your portfolio website.</p>
                        </div>

                        <div v-else class="space-y-7">
                            <template v-for="fieldConfig in activeFields" :key="fieldConfig.field_definition.id">
                                <div
                                    v-if="
                                        fieldConfig.is_visible &&
                                        activeValues[fieldConfig.field_definition.id] &&
                                        !(activeSectionKey === 'navbar' && hiddenNavbarFieldKeys.has(fieldConfig.field_definition.field_key))
                                    "
                                    class="space-y-2.5"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <Label :for="fieldConfig.field_definition.field_key" class="text-sm font-semibold text-[#334155] dark:text-slate-200">
                                            {{ fieldConfig.field_definition.label }}
                                            <span v-if="fieldConfig.is_required" class="ml-0.5 text-[#00695C]">*</span>
                                        </Label>
                                        <span class="rounded-md bg-[#F4F7F7] dark:bg-slate-950 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8] dark:text-slate-500">
                                            {{ fieldConfig.field_definition.data_type.replace('_', ' ') }}
                                        </span>
                                    </div>

                                    <Input
                                        v-if="['string', 'url', 'email'].includes(fieldConfig.field_definition.data_type)"
                                        :id="fieldConfig.field_definition.field_key"
                                        v-model="activeValues[fieldConfig.field_definition.id].value"
                                        :type="fieldConfig.field_definition.data_type === 'email' ? 'email' : 'text'"
                                        :placeholder="
                                            fieldConfig.field_definition.field_key.includes('cta') &&
                                            fieldConfig.field_definition.field_key.endsWith('_url')
                                                ? 'Enter a URL, #section, mailto:, or tel: link'
                                                : `Enter ${fieldConfig.field_definition.label.toLowerCase()}`
                                        "
                                        class="h-11 rounded-xl border-[#DCE6E4] dark:border-slate-700 bg-white focus-visible:ring-[#00695C]/30 dark:focus-visible:ring-teal-400/30 text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                                    />

                                    <!-- Image upload -->
                                    <div v-else-if="fieldConfig.field_definition.data_type === 'image'" class="space-y-3">
                                        <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-[#BFD4CE] dark:border-slate-700 bg-[#F8FBFA] dark:bg-slate-800/70 px-5 py-6 text-center transition hover:border-[#00695C] hover:bg-[#F2F8F6] dark:bg-teal-950/50">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white dark:bg-slate-900 text-[#00695C] shadow-sm">
                                                <ImageIcon class="h-5 w-5" />
                                            </span>
                                            <span class="mt-3 text-sm font-semibold text-[#334155] dark:text-slate-200">
                                                {{ uploadingFiles[fieldConfig.field_definition.field_key] ? 'Preparing image…' : 'Choose an image to upload' }}
                                            </span>
                                            <span class="mt-1 text-xs text-[#94A3B8] dark:text-slate-500">PNG, JPG, GIF or WebP · Images are optimized before saving · Maximum 10 MB</span>
                                            <input
                                                type="file"
                                                accept="image/jpeg,image/png,image/gif,image/webp"
                                                :disabled="uploadingFiles[fieldConfig.field_definition.field_key]"
                                                class="sr-only"
                                                @change="(event: Event) => uploadFile(event, fieldConfig.field_definition.field_key, 'image', () => activeValues[fieldConfig.field_definition.id].value, (value) => activeValues[fieldConfig.field_definition.id].value = value)"
                                            />
                                        </label>
                                        <p v-if="uploadErrors[fieldConfig.field_definition.field_key]" class="text-sm text-red-600 dark:text-red-400">{{ uploadErrors[fieldConfig.field_definition.field_key] }}</p>
                                        <p v-if="uploadedFiles[fieldConfig.field_definition.field_key]" role="status" class="text-xs text-[#16734A]">
                                            Selected: {{ uploadedFiles[fieldConfig.field_definition.field_key] }}. Save changes to upload and apply it.
                                        </p>
                                        <div v-if="activeValues[fieldConfig.field_definition.id].value" class="flex items-center gap-3 rounded-xl border border-[#E1E8E7] dark:border-slate-800 bg-[#F8FAFA] dark:bg-slate-800/70 p-3">
                                            <img :src="assetPreviewUrl(activeValues[fieldConfig.field_definition.id].value)" alt="Uploaded image preview" class="h-16 w-16 rounded-lg border border-[#E1E8E7] dark:border-slate-800 object-cover" />
                                            <div class="min-w-0 flex-1 break-words">
                                                <p class="text-xs font-semibold text-[#334155] dark:text-slate-200">Image preview</p>
                                                <p class="mt-1 truncate text-xs text-[#94A3B8] dark:text-slate-500">
                                                    {{ uploadedFiles[fieldConfig.field_definition.field_key] || activeValues[fieldConfig.field_definition.id].value }}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                                :aria-label="`Remove ${fieldConfig.field_definition.label}`"
                                                @click="removeImage(activeValues[fieldConfig.field_definition.id].value, () => activeValues[fieldConfig.field_definition.id].value = null)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                Remove
                                            </button>
                                        </div>
                                    </div>

                                    <!-- File upload -->
                                    <div v-else-if="fieldConfig.field_definition.data_type === 'file'" class="space-y-3">
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-[#BFD4CE] dark:border-slate-700 bg-[#F8FBFA] dark:bg-slate-800/70 p-4 transition hover:border-[#00695C]">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-slate-900 text-[#00695C] shadow-sm">
                                                <Upload class="h-5 w-5" />
                                            </span>
                                            <span class="min-w-0 flex-1 break-words">
                                                <span class="block text-sm font-semibold text-[#334155] dark:text-slate-200">
                                                        {{ uploadingFiles[fieldConfig.field_definition.field_key] ? 'Preparing file…' : 'Choose a file' }}
                                                </span>
                                                <span class="mt-1 block text-xs text-[#94A3B8] dark:text-slate-500">PDF, DOC, DOCX, TXT or ZIP · Maximum 10 MB</span>
                                            </span>
                                            <input
                                                type="file"
                                                accept=".pdf,.doc,.docx,.txt,.zip"
                                                :disabled="uploadingFiles[fieldConfig.field_definition.field_key]"
                                                class="sr-only"
                                                @change="(event: Event) => uploadFile(event, fieldConfig.field_definition.field_key, 'file', () => activeValues[fieldConfig.field_definition.id].value, (value) => activeValues[fieldConfig.field_definition.id].value = value)"
                                            />
                                        </label>
                                        <p v-if="uploadErrors[fieldConfig.field_definition.field_key]" class="text-sm text-red-600 dark:text-red-400">{{ uploadErrors[fieldConfig.field_definition.field_key] }}</p>
                                        <p v-if="uploadedFiles[fieldConfig.field_definition.field_key]" role="status" class="text-xs text-[#16734A]">Selected: {{ uploadedFiles[fieldConfig.field_definition.field_key] }}. Save changes to upload and apply it.</p>
                                        <div v-if="activeValues[fieldConfig.field_definition.id].value" class="rounded-xl border border-[#E1E8E7] dark:border-slate-800 bg-[#F8FAFA] dark:bg-slate-800/70 p-3 text-sm">
                                            <a :href="assetPreviewUrl(activeValues[fieldConfig.field_definition.id].value)" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#00695C] hover:underline">View selected file <ArrowUpRight class="inline h-3.5 w-3.5" /></a>
                                        </div>
                                    </div>

                                    <Textarea
                                        v-else-if="['text', 'rich_text'].includes(fieldConfig.field_definition.data_type)"
                                        :id="fieldConfig.field_definition.field_key"
                                        v-model="activeValues[fieldConfig.field_definition.id].value"
                                        :rows="4"
                                        :placeholder="`Enter ${fieldConfig.field_definition.label.toLowerCase()}`"
                                        class="min-h-[110px] resize-y rounded-xl border-[#DCE6E4] dark:border-slate-700 bg-white focus-visible:ring-[#00695C]/30 dark:focus-visible:ring-teal-400/30 text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                                    />

                                    <div v-else-if="fieldConfig.field_definition.data_type === 'boolean'" class="flex items-center gap-3 rounded-xl border border-[#E1E8E7] dark:border-slate-800 bg-[#F8FAFA] dark:bg-slate-800/70 p-4">
                                        <Checkbox :id="fieldConfig.field_definition.field_key" v-model:checked="activeValues[fieldConfig.field_definition.id].value" />
                                        <span class="text-sm text-[#475569] dark:text-slate-300">Enable this option</span>
                                    </div>

                                    <!-- Repeatable fields -->
                                    <div v-else-if="fieldConfig.field_definition.data_type === 'array'" class="space-y-3">
                                        <div
                                            v-for="(arrayItem, index) in (activeValues[fieldConfig.field_definition.id].value as any[])"
                                            :key="index"
                                            class="rounded-xl border border-[#E1E8E7] dark:border-slate-800 bg-[#FBFCFC] dark:bg-slate-900 p-4 sm:p-5"
                                        >
                                            <div class="mb-4 flex items-center justify-between border-b border-[#EDF1F0] dark:border-slate-800 pb-3">
                                                <span class="text-xs font-bold uppercase tracking-wide text-[#64748B] dark:text-slate-400">
                                                    Item {{ Number(index) + 1 }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-[#94A3B8] dark:text-slate-500 transition hover:bg-red-50 dark:bg-red-950/30 dark:hover:bg-red-950/40 hover:text-red-600 dark:text-red-400"
                                                    @click="removeArrayItem(fieldConfig.field_definition.id, Number(index))"
                                                >
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                    Remove
                                                </button>
                                            </div>

                                            <!-- About highlights -->
                                            <div v-if="fieldConfig.field_definition.field_key === 'about_highlights'" class="space-y-2">
                                                <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Highlight</Label>
                                                <Input
                                                    v-model="activeValues[fieldConfig.field_definition.id].value[index]"
                                                    placeholder="e.g. 5+ years of experience"
                                                    class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30"
                                                />
                                            </div>

                                            <!-- Hero social links -->
                                            <div v-else-if="fieldConfig.field_definition.field_key === 'hero_social_links'" class="grid gap-4 sm:grid-cols-2">
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Platform / icon</Label>
                                                    <select
                                                        v-model="arrayItem.platform"
                                                        class="h-10 w-full rounded-lg border px-3 text-sm border-slate-300 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                                    >
                                                        <option v-for="platform in socialPlatforms" :key="platform.value" :value="platform.value">
                                                            {{ platform.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">URL</Label>
                                                    <Input v-model="arrayItem.url" placeholder="https://…" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                            </div>

                                            <!-- Contact social links -->
                                            <div v-else-if="fieldConfig.field_definition.field_key === 'contact_social_links'" class="grid gap-4 sm:grid-cols-2">
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Label / platform</Label>
                                                    <Input v-model="arrayItem.label" placeholder="e.g. GitHub or Home" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">URL</Label>
                                                    <Input v-model="arrayItem.url" placeholder="https://…" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                            </div>

                                            <!-- Skills -->
                                            <div v-else-if="fieldConfig.field_definition.field_key === 'skills_list'" class="grid gap-4 sm:grid-cols-3">
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Skill name</Label>
                                                    <Input v-model="arrayItem.label" placeholder="e.g. Figma" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Proficiency (%)</Label>
                                                    <Input v-model.number="arrayItem.percent" type="number" min="0" max="100" placeholder="100" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Icon (optional)</Label>
                                                    <Input v-model="arrayItem.icon" placeholder="e.g. figma or fa-brands fa-figma" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                            </div>

                                            <!-- Journey -->
                                            <div v-else-if="fieldConfig.field_definition.field_key === 'journey_list'" class="space-y-4">
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Period / year</Label>
                                                        <Input v-model="arrayItem.period" placeholder="e.g. 2023 - Present" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Role / title</Label>
                                                        <Input v-model="arrayItem.title" placeholder="e.g. Software Engineer" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                    </div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Company / institution</Label>
                                                    <Input v-model="arrayItem.company" placeholder="Company or institution name" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Description</Label>
                                                    <Textarea v-model="arrayItem.description" :rows="2" placeholder="What did you do or accomplish?" class="rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                            </div>

                                            <!-- Projects -->
                                            <div v-else-if="fieldConfig.field_definition.field_key === 'projects_list'" class="space-y-4">
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Project title</Label>
                                                    <Input v-model="arrayItem.title" placeholder="e.g. E-Commerce Dashboard" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Category</Label>
                                                        <Input v-model="arrayItem.category" placeholder="e.g. Web development" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Project URL</Label>
                                                        <Input v-model="arrayItem.url" placeholder="https://…" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                    </div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Description</Label>
                                                    <Textarea v-model="arrayItem.description" :rows="2" placeholder="Short summary of the project" class="rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                                <div class="flex items-center gap-3 rounded-lg border border-[#E1E8E7] dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2.5">
                                                    <Checkbox :id="`project-featured-${index}`" v-model:checked="arrayItem.featured" />
                                                    <Label :for="`project-featured-${index}`" class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Mark as featured</Label>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Project image</Label>
                                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-[#BFD4CE] dark:border-slate-700 bg-white dark:bg-slate-900 p-3 transition hover:border-[#00695C]">
                                                        <ImageIcon class="h-5 w-5 shrink-0 text-[#00695C]" />
                                                        <span class="flex-1 text-sm font-medium text-[#475569] dark:text-slate-300">Choose project image</span>
                                                        <input
                                                            type="file"
                                                            accept="image/jpeg,image/png,image/gif,image/webp"
                                                            :disabled="uploadingFiles[`project-image-${index}`]"
                                                            class="sr-only"
                                                            @change="(event: Event) => uploadFile(event, `project-image-${index}`, 'image', () => arrayItem.image, (value) => arrayItem.image = value)"
                                                        />
                                                    </label>
                                                    <p v-if="uploadingFiles[`project-image-${index}`]" class="text-xs text-[#64748B] dark:text-slate-400">Preparing image…</p>
                                                    <p v-if="uploadErrors[`project-image-${index}`]" class="text-xs text-red-600 dark:text-red-400">{{ uploadErrors[`project-image-${index}`] }}</p>
                                                    <p v-if="uploadedFiles[`project-image-${index}`]" role="status" class="text-xs text-[#16734A]">Selected: {{ uploadedFiles[`project-image-${index}`] }}. Save changes to upload and apply it.</p>
                                                    <div v-if="arrayItem.image" class="flex items-center gap-3">
                                                        <img :src="assetPreviewUrl(arrayItem.image)" :alt="arrayItem.title || 'Project image preview'" class="h-20 w-28 rounded-lg border border-[#E1E8E7] dark:border-slate-800 object-cover" />
                                                        <button
                                                            type="button"
                                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                                            :aria-label="`Remove image from ${arrayItem.title || `project ${Number(index) + 1}`}`"
                                                            @click="removeImage(arrayItem.image, () => arrayItem.image = null)"
                                                        >
                                                            <Trash2 class="h-3.5 w-3.5" />
                                                            Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Services -->
                                            <div v-else-if="fieldConfig.field_definition.field_key === 'services_list'" class="space-y-4">
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Service title</Label>
                                                        <Input v-model="arrayItem.title" placeholder="e.g. Web Design" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Icon (optional)</Label>
                                                        <Input v-model="arrayItem.icon" placeholder="e.g. palette or fa-solid fa-palette" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                    </div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Description</Label>
                                                    <Textarea v-model="arrayItem.description" :rows="2" placeholder="What does this service include?" class="rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Link URL (optional)</Label>
                                                    <Input v-model="arrayItem.url" placeholder="https://…" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                                </div>
                                            </div>

                                            <div v-else class="space-y-2">
                                                <Label class="text-xs font-semibold text-[#64748B] dark:text-slate-400">Value</Label>
                                                <Input v-model="arrayItem.value" placeholder="Enter item value…" class="h-10 rounded-lg border-[#DCE6E4] dark:border-slate-700 bg-white text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 focus-visible:ring-[#00695C]/30" />
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            class="inline-flex h-10 items-center gap-2 rounded-xl border border-[#BFD4CE] dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 text-sm font-semibold text-[#00695C] transition hover:bg-[#F0F7F5] dark:bg-teal-950/50"
                                            @click="addArrayItem(fieldConfig.field_definition.id, fieldConfig.field_definition.field_key)"
                                        >
                                            <Plus class="h-4 w-4" />
                                            Add item
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-[#EDF1F0] dark:border-slate-800 bg-[#FBFCFC] dark:bg-slate-900 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <p class="text-xs leading-5 text-[#94A3B8] dark:text-slate-500">
                            Required fields are marked with <span class="font-semibold text-[#00695C]">*</span>.
                            Remember to save your changes.
                        </p>
                        <button
                            type="button"
                            :disabled="form.processing || hasPendingUploads"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-4 text-sm font-semibold text-white transition hover:bg-[#00574C] disabled:cursor-not-allowed disabled:opacity-50"
                            @click="savePortfolio"
                        >
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <Save v-else class="h-4 w-4" />
                            {{ form.processing ? 'Saving changes…' : hasPendingUploads ? 'Preparing upload…' : 'Save changes' }}
                        </button>
                    </div>
                </div>

                <div v-if="Object.keys(form.errors).length" class="rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/30 p-4 text-sm text-red-700 dark:text-red-300">
                    Some fields need your attention. Review the error messages above and try saving again.
                </div>
            </section>

            <!-- Live summary preview -->
            <aside class="min-w-0">
                <div class="rounded-2xl border border-[#E1E8E7] dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-[0_2px_8px_rgba(15,23,42,0.025)] lg:sticky lg:top-[92px]">
                    <div class="flex items-center justify-between gap-2 px-1 pb-4">
                        <div>
                            <h2 class="text-sm font-bold text-[#0F172A] dark:text-slate-100">Content overview</h2>
                            <p class="mt-1 text-xs text-[#94A3B8] dark:text-slate-500">A quick look at your sections</p>
                        </div>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F4F1] dark:bg-teal-900/50 text-[#00695C]">
                            <Eye class="h-4 w-4" />
                        </span>
                    </div>

                    <div class="rounded-xl border border-[#E1E8E7] dark:border-slate-800 bg-[#F8FAFA] dark:bg-slate-800/70 p-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#E8F4F1] dark:bg-teal-900/50 text-lg font-bold text-[#00695C]">
                            {{ portfolio.title?.charAt(0)?.toUpperCase() || 'Q' }}
                        </div>
                        <h3 class="mt-3 break-words text-base font-bold text-[#0F172A] dark:text-slate-100">{{ portfolio.title }}</h3>
                        <p class="mt-1 text-xs text-[#64748B] dark:text-slate-400">{{ portfolio.template.name }}</p>
                        <div class="mt-3 flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full" :class="portfolio.status === 'published' ? 'bg-[#16A34A]' : 'bg-[#D99A18]'" />
                            <span class="text-xs font-medium capitalize text-[#64748B] dark:text-slate-400">{{ portfolio.status }}</span>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <div
                            v-for="sec in form.sections"
                            :key="sec.id"
                            class="rounded-xl border p-3 transition"
                            :class="activeSectionKey === sec.section_key ? 'border-[#BFD9D2] dark:border-teal-800 bg-[#F4FAF8] dark:bg-teal-950/40' : 'border-[#EDF1F0] dark:border-slate-800 bg-white dark:bg-slate-900'"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 text-left"
                                @click="activeSectionKey = sec.section_key"
                            >
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#F4F7F7] dark:bg-slate-950 text-[#64748B] dark:text-slate-400">
                                    <LayoutTemplate class="h-3.5 w-3.5" />
                                </span>
                                <span class="min-w-0 flex-1 truncate text-xs font-semibold text-[#334155] dark:text-slate-200">{{ sidebarSectionLabel(sec.section_key) }}</span>
                                <span class="h-2 w-2 shrink-0 rounded-full" :class="sec.is_enabled ? 'bg-[#22A06B]' : 'bg-[#CBD5E1]'" />
                            </button>

                            <div v-if="sec.is_enabled" class="mt-3 space-y-2 border-t border-[#EDF1F0] dark:border-slate-800 pt-3">
                                <div v-for="fv in sec.field_values" :key="fv.field_definition_id" class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8] dark:text-slate-500">
                                        {{ fieldDefMap.get(fv.field_definition_id)?.label || 'Field' }}
                                    </p>
                                    <p class="mt-0.5 break-words text-xs leading-5 text-[#64748B] dark:text-slate-400">
                                        {{
                                            Array.isArray(fv.value)
                                                ? `${fv.value.length} item${fv.value.length === 1 ? '' : 's'}`
                                                : typeof fv.value === 'boolean'
                                                    ? (fv.value ? 'Enabled' : 'Disabled')
                                                    : (fv.value || 'Not set')
                                        }}
                                    </p>
                                </div>
                            </div>
                            <p v-else class="mt-2 text-[11px] text-[#94A3B8] dark:text-slate-500">Hidden from portfolio</p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-[#DCEBE6] dark:border-teal-900 bg-[#F5FAF8] dark:bg-teal-950/30 p-3">
                        <div class="flex items-center gap-2 text-xs font-bold text-[#00695C]">
                            <Sparkles class="h-4 w-4" />
                            Keep it fresh
                        </div>
                        <p class="mt-1.5 text-xs leading-5 text-[#64748B] dark:text-slate-400">
                            Add meaningful details and use clear project images to make your portfolio stand out.
                        </p>
                    </div>
                </div>
            </aside>
        </main>

        <!-- Template switch dialog -->
        <Dialog v-model:open="isSwitchModalOpen">
            <DialogContent class="max-h-[90dvh] w-[calc(100%-1rem)] max-w-lg overflow-y-auto rounded-2xl border-[#E1E8E7] dark:border-slate-800 bg-white dark:bg-slate-900 p-0 sm:w-[calc(100%-2rem)]">
                <DialogHeader class="border-b border-[#EDF1F0] dark:border-slate-800 px-5 py-5 text-left sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F4F1] dark:bg-teal-900/50 text-[#00695C]">
                            <LayoutTemplate class="h-5 w-5" />
                        </span>
                        <div>
                            <DialogTitle class="text-lg font-bold text-[#0F172A] dark:text-slate-100">Change template</DialogTitle>
                            <DialogDescription class="mt-1 text-sm leading-5 text-[#64748B] dark:text-slate-400">
                                Choose a different design for your portfolio. Compatible content will be preserved where possible.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div class="p-5 sm:p-6">
                    <Label for="template_select" class="mb-2 block text-sm font-semibold text-[#334155] dark:text-slate-200">Available templates</Label>
                    <select
                        id="template_select"
                        v-model.number="selectedTemplateId"
                        class="h-11 w-full rounded-xl border px-3 text-sm outline-none transition focus:border-[#00695C] focus:ring-2 focus:ring-[#00695C]/15 border-slate-300 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                        <option v-for="template in availableTemplates" :key="template.id" :value="template.id">
                            {{ template.name }}<template v-if="template.category"> — {{ template.category.name }}</template>
                        </option>
                    </select>
                    <p class="mt-2 text-xs leading-5 text-[#94A3B8] dark:text-slate-500">
                        Your current template is {{ portfolio.template.name }}.
                    </p>
                </div>

                <DialogFooter class="border-t border-[#EDF1F0] dark:border-slate-800 bg-[#FBFCFC] dark:bg-slate-900 px-5 py-4 sm:px-6">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-xl border border-[#DCE6E4] dark:border-slate-700 bg-white dark:bg-slate-900 px-4 text-sm font-semibold text-[#475569] dark:text-slate-300 transition hover:bg-[#F4F7F7] dark:bg-slate-950"
                        @click="isSwitchModalOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="switchForm.processing || selectedTemplateId === portfolio.template.id"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#00695C] px-4 text-sm font-semibold text-white transition hover:bg-[#00574C] disabled:cursor-not-allowed disabled:opacity-50"
                        @click="switchTemplate"
                    >
                        <LoaderCircle v-if="switchForm.processing" class="h-4 w-4 animate-spin" />
                        Switch template
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
