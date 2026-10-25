<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onBeforeUnmount, onMounted } from 'vue';
import {
    AlertCircle,
    ArrowLeft,
    ArrowUpRight,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Download,
    Eye,
    EyeOff,
    FileText,
    Globe2,
    Image as ImageIcon,
    LayoutTemplate,
    LoaderCircle,
    Maximize2,
    Minimize2,
    Monitor,
    MoreHorizontal,
    MousePointerClick,
    Plus,
    Save,
    Settings2,
    Smartphone,
    Trash2,
    Undo2,
    Upload,
    X,
} from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    design_settings?: Record<string, any>;
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

interface InlineEditTarget {
    sectionKey: string;
    fieldDefinitionId: number;
    fieldKey: string;
    label: string;
    path: (string | number)[];
    kind: 'text' | 'image';
}
interface RepeatableItemField {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'percent' | 'image' | 'boolean' | 'select';
    placeholder?: string;
    options?: { value: string; label: string }[];
}

interface RequiredIssue {
    sectionKey: string;
    field: FieldDefinition;
}

interface ToastState {
    id: number;
    message: string;
    undo?: () => void;
    scope: 'form' | 'global';
}

const props = defineProps<{
    portfolio: Portfolio;
    availableTemplates: { id: number; name: string; slug: string; category?: { name: string } }[];
    fieldDefinitions: FieldDefinition[];
}>();

const page = usePage<{ auth: { user: { role: string } } }>();

const fieldGuidance: Record<string, { description: string; example?: string }> = {
    logo_text: { description: 'The short brand name shown in your navigation bar.', example: 'Alex Morgan' },
    logo_image: { description: 'An optional logo image shown in place of text branding.' },
    sticky_nav: { description: 'Keep the navigation visible at the top as visitors scroll.' },
    show_logo: { description: 'Show or hide your logo in the navigation bar.' },
    hero_greeting: { description: 'A short greeting displayed above your name.', example: 'Hello, I am' },
    hero_name: { description: 'Your name, usually the largest text in the opening section.', example: 'Alex Morgan' },
    hero_role: { description: 'Your profession or the kind of work you do.', example: 'Product designer' },
    hero_description: { description: 'A brief introduction visitors see near your name and role.', example: 'I design simple, useful digital products.' },
    hero_image: { description: 'Your portrait or profile image in the opening section.' },
    hero_background_image: { description: 'A background image behind the opening section, if this template supports one.' },
    hero_cta_primary_text: { description: 'The label on the main action button near your introduction.', example: 'View my work' },
    hero_cta_primary_url: { description: 'Where the main action button takes visitors. Use a URL or section link.', example: '#projects' },
    hero_cta_secondary_text: { description: 'An optional label for a second action button.', example: 'Contact me' },
    hero_cta_secondary_url: { description: 'Where the second action button takes visitors.', example: '#contact' },
    hero_social_links: { description: 'Add links to your social or professional profiles; each entry becomes a profile link.' },
    about_title: { description: 'The heading for the section where you tell visitors about yourself.', example: 'A little about me' },
    about_subtitle: { description: 'A short supporting line below your About heading.' },
    about_description: { description: 'Your background, approach, or what you care about in your work.' },
    about_image: { description: 'An optional image displayed alongside your About content.' },
    about_highlights: { description: 'Short facts or achievements displayed as highlights.', example: '5+ years of experience' },
    about_cv_file: { description: 'A downloadable CV or resume visitors can open from your portfolio.' },
    about_cta_text: { description: 'The label on an optional action button in the About section.', example: 'Download my resume' },
    about_cta_url: { description: 'The destination for the About section action button.' },
    journey_title: { description: 'The heading above your work or education history.', example: 'My experience' },
    journey_subtitle: { description: 'A short introduction to your professional journey.' },
    journey_list: { description: 'Add one entry for each role, study period, or career milestone.' },
    skills_title: { description: 'The heading above your skills list.', example: 'Skills & tools' },
    skills_subtitle: { description: 'A short line under the skills heading.' },
    skills_description: { description: 'A brief explanation of your strengths or how you use your skills.' },
    skills_list: { description: 'Add one entry for each skill you want to feature.' },
    skills_show_level: { description: 'Show proficiency indicators for skills that include a percentage.' },
    skills_show_icon: { description: 'Show icons next to skills when the template supports them.' },
    projects_title: { description: 'The heading above your selected work.', example: 'Featured projects' },
    projects_subtitle: { description: 'A short introduction to the projects visitors will see.' },
    projects_description: { description: 'Optional context shown before your project list.' },
    projects_list: { description: 'Add one entry for each project card shown in your portfolio.' },
    projects_show_featured: { description: 'Show a featured label on projects you mark as featured.' },
    contact_title: { description: 'The heading inviting visitors to get in touch.', example: 'Let’s work together' },
    contact_subtitle: { description: 'A short supporting line under the contact heading.' },
    contact_description: { description: 'Tell visitors what they can contact you about.' },
    contact_email: { description: 'The email address visitors can use to contact you.', example: 'hello@example.com' },
    contact_phone: { description: 'An optional phone number you are comfortable sharing publicly.' },
    contact_location: { description: 'Your city or region, if you want to share it.', example: 'Toronto, Canada' },
    contact_form_enabled: { description: 'Show the contact form area when the template supports it.' },
    contact_social_links: { description: 'Add links visitors can use to find you on other platforms.' },
    services_title: { description: 'The heading above the services you offer.', example: 'How I can help' },
    services_subtitle: { description: 'A short introduction to your services.' },
    services_list: { description: 'Add one entry for each service you offer.' },
};

const guidanceForField = (field: FieldDefinition) =>
    fieldGuidance[field.field_key] ?? {
        description: field.data_type === 'image'
            ? 'Upload an image that visitors will see in this part of your portfolio.'
            : field.data_type === 'file'
              ? 'Upload a file visitors can open or download from this part of your portfolio.'
              : field.data_type === 'array'
                ? 'Add one or more entries; each entry appears in this portfolio section.'
                : field.data_type === 'boolean'
                  ? 'Choose whether this option is shown on your portfolio.'
                  : `This content appears in the ${humanize(field.field_key.split('_')[0]).toLowerCase()} section of your portfolio.`,
    };

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

    return [...configured, ...extra]
        .sort((a, b) => a.field_definition.id - b.field_definition.id);
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
            return { label: 'New skill', percent: 100, icon: '' };
        case 'journey_list':
            return { period: '', title: 'New role', company: '', description: '' };
        case 'projects_list':
            return { title: 'New project', category: 'Website Design', description: '', image: '', url: '', featured: false };
        case 'hero_social_links':
            return { platform: 'github', url: '' };
        case 'about_highlights':
            return 'New highlight';
        case 'services_list':
            return { icon: '', title: 'New service', description: '', url: '' };
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

/* -------------------------------------------------------------------------
 * Editor UI state
 * ---------------------------------------------------------------------- */

const isAdmin = computed(() => page.props.auth.user.role === 'admin');
const isPublished = computed(() => props.portfolio.status === 'published');

const previewFrame = ref<HTMLIFrameElement | null>(null);
const previewDevice = ref<'desktop' | 'mobile'>('desktop');
const isPreviewLoading = ref(true);
const isFocusMode = ref(false);

/** What is selected on the canvas (a field, or an item inside a repeatable field). */
const inlineEditTarget = ref<InlineEditTarget | null>(null);
/** The selected section (from the canvas, the outline, or a required-field jump). */
const selectedCanvasSectionKey = ref<string | null>(null);
const isContactSettingsSelected = ref(false);
const flashFieldId = ref<number | null>(null);

const isMoreOpen = ref(false);
const moreMenuRoot = ref<HTMLElement | null>(null);
const isChecklistOpen = ref(false);
const isLeaveOpen = ref(false);
const isSwitchModalOpen = ref(false);
const selectedTemplateId = ref(props.portfolio.template.id);

let pendingLeaveUrl: string | null = null;
let bypassLeaveGuard = false;

/** Shared class recipes, so every control looks and behaves the same. */
const ui = {
    btn: 'inline-flex shrink-0 items-center justify-center gap-2 rounded-xl text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 disabled:cursor-not-allowed disabled:opacity-50 motion-safe:active:scale-[0.98]',
    primary: 'bg-[#00695C] text-white hover:bg-[#00574C]',
    secondary: 'border border-[#DCE6E4] bg-white text-[#475569] hover:bg-[#F4F7F7] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
    accent: 'border border-[#BFD4CE] bg-white text-[#00695C] hover:bg-[#F0F7F5] dark:border-teal-900 dark:bg-slate-900 dark:text-teal-300 dark:hover:bg-teal-950/40',
    danger: 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40',
    input: 'rounded-lg border-[#DCE6E4] bg-white text-slate-900 placeholder:text-slate-400 focus-visible:ring-[#00695C]/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus-visible:ring-teal-400/30',
    select: 'h-10 w-full rounded-lg border border-[#DCE6E4] bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-[#00695C] focus:ring-2 focus:ring-[#00695C]/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100',
    switchTrack: "relative h-5 w-9 shrink-0 rounded-full bg-[#CBD5E1] transition-colors peer-checked:bg-[#00695C] peer-focus-visible:ring-2 peer-focus-visible:ring-[#00695C]/50 peer-focus-visible:ring-offset-1 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow-sm after:transition-transform after:content-[''] peer-checked:after:translate-x-4 dark:bg-slate-600",
    dropzone: 'flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-[#BFD4CE] bg-[#F8FBFA] p-3 transition hover:border-[#00695C] focus-within:border-[#00695C] focus-within:ring-2 focus-within:ring-[#00695C]/30 dark:border-slate-700 dark:bg-slate-800/70',
    label: 'text-sm font-semibold text-[#334155] dark:text-slate-200',
    hint: 'text-xs leading-5 text-[#64748B] dark:text-slate-400',
    muted: 'text-[#64748B] dark:text-slate-400',
};

const fieldWrapClass = (active: boolean) =>
    active ? 'bg-[#F0F7F5] ring-2 ring-[#00695C]/30 dark:bg-teal-950/30' : '';

const navItemClass = (selected: boolean) =>
    selected
        ? 'border-[#BFD4CE] bg-[#E8F4F1] text-[#00695C] dark:border-teal-900 dark:bg-teal-900/50 dark:text-teal-300 xl:border-transparent'
        : 'border-[#E1E8E7] bg-white text-[#475569] hover:bg-white hover:text-[#0F172A] dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 xl:border-transparent xl:bg-transparent xl:hover:bg-white dark:xl:hover:bg-slate-900';

/* -------------------------------------------------------------------------
 * Toasts with Undo
 * Destructive actions apply immediately and can be undone for a few seconds,
 * instead of interrupting the user with an "Are you sure?" dialog. Files that
 * belong to a removed item are only discarded once the undo window closes.
 * ---------------------------------------------------------------------- */

const toast = ref<ToastState | null>(null);
let toastTimer: number | undefined;
let toastExpire: (() => void) | null = null;
let toastCounter = 0;

const finalizeToast = () => {
    window.clearTimeout(toastTimer);
    const expire = toastExpire;
    toastExpire = null;
    toast.value = null;
    expire?.();
};

const showToast = (
    message: string,
    options: { undo?: () => void; onExpire?: () => void; scope?: 'form' | 'global'; duration?: number } = {},
) => {
    finalizeToast();
    toast.value = { id: ++toastCounter, message, undo: options.undo, scope: options.scope ?? 'global' };
    toastExpire = options.onExpire ?? null;
    toastTimer = window.setTimeout(finalizeToast, options.duration ?? 7000);
};

const undoToast = () => {
    window.clearTimeout(toastTimer);
    const undo = toast.value?.undo;
    toastExpire = null;
    toast.value = null;
    undo?.();
};

const resolvePreviewUploads = (value: any): any => {
    if (Array.isArray(value)) return value.map(resolvePreviewUploads);
    if (value && typeof value === 'object') {
        return Object.fromEntries(
            Object.entries(value).map(([key, nestedValue]) => [key, resolvePreviewUploads(nestedValue)]),
        );
    }

    const uploadId = pendingUploadId(value);
    return uploadId ? pendingUploads.value[uploadId]?.previewUrl || '' : value;
};

const livePreviewPayload = computed(() => {
    const content: Record<string, Record<string, any>> = {};
    const sections = form.sections.map((section: FormSection) => {
        const fieldValues = section.field_values.map((fieldValue: FormFieldValue) => {
            const fieldDefinition = fieldDefMap.value.get(fieldValue.field_definition_id);
            const value = resolvePreviewUploads(fieldValue.value);

            if (section.is_enabled && fieldDefinition) {
                content[section.section_key] ??= {};
                content[section.section_key][fieldDefinition.field_key] = value;
            }

            return {
                ...fieldValue,
                value,
                ...(fieldDefinition ? { field_definition: fieldDefinition } : {}),
            };
        });

        return {
            ...section,
            field_values: fieldValues,
        };
    });

    return {
        type: 'quickfolio:live-preview',
        content,
        portfolio: {
            ...props.portfolio,
            sections,
            google_form_url: form.google_form_url || null,
            google_form_name_entry: form.google_form_name_entry || null,
            google_form_email_entry: form.google_form_email_entry || null,
            google_form_message_entry: form.google_form_message_entry || null,
        },
    };
});

const sendLivePreview = () => {
    const target = previewFrame.value?.contentWindow;
    if (!target) return;

    target.postMessage(
        JSON.parse(JSON.stringify(livePreviewPayload.value)),
        window.location.origin,
    );
};

// Live preview: coalesce rapid keystrokes into one message (still well under the 100 ms "instant" threshold).
let previewTimer: number | undefined;
watch(
    livePreviewPayload,
    () => {
        window.clearTimeout(previewTimer);
        previewTimer = window.setTimeout(sendLivePreview, 60);
    },
    { deep: true },
);

const onPreviewLoad = () => {
    isPreviewLoading.value = false;
    sendLivePreview();
};

watch(
    () => props.portfolio.template.slug,
    () => {
        isPreviewLoading.value = true;
    },
);


const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

const findFormFieldValue = (sectionKey: string, fieldDefinitionId: number) => {
    const section = form.sections.find((item: FormSection) => item.section_key === sectionKey);
    return section?.field_values.find((item: FormFieldValue) => item.field_definition_id === fieldDefinitionId);
};

const valueAtPath = (value: unknown, path: (string | number)[]) => {
    let current: any = value;
    for (const key of path) {
        if (current === null || current === undefined) return undefined;
        current = current[key];
    }
    return current;
};

const setValueAtPath = (value: unknown, path: (string | number)[], nextValue: unknown) => {
    if (path.length === 0) return nextValue;

    let current: any = value;
    for (const key of path.slice(0, -1)) {
        if (current === null || current === undefined) return value;
        current = current[key];
    }
    if (current !== null && current !== undefined) current[path[path.length - 1]] = nextValue;
    return value;
};

/* -------------------------------------------------------------------------
 * Selection & focus
 * ---------------------------------------------------------------------- */

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const hasCoarsePointer = () => window.matchMedia('(pointer: coarse)').matches;

/** Scroll an inspector element into view and (on pointer devices) focus its first control. */
const focusInInspector = async (selector: string, options: { focus?: boolean } = {}) => {
    await nextTick();
    window.requestAnimationFrame(() => {
        const element = document.querySelector<HTMLElement>(selector);
        if (!element) return;
        element.scrollIntoView({ block: 'nearest', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
        // Never pop the on-screen keyboard unprompted on touch devices.
        if (options.focus === false || hasCoarsePointer()) return;
        const focusable = element.matches('input, textarea, select, button')
            ? element
            : element.querySelector<HTMLElement>('input:not([type="hidden"]), textarea, select, button');
        focusable?.focus({ preventScroll: true });
    });
};

const focusSectionField = (fieldDefinitionId: number, focus = true) => {
    flashFieldId.value = fieldDefinitionId;
    window.setTimeout(() => {
        if (flashFieldId.value === fieldDefinitionId) flashFieldId.value = null;
    }, 1600);
    focusInInspector(`#insp-field-${fieldDefinitionId}`, { focus });
};

const clearSelection = () => {
    inlineEditTarget.value = null;
    selectedCanvasSectionKey.value = null;
    isContactSettingsSelected.value = false;
};

const selectSection = (sectionKey: string) => {
    inlineEditTarget.value = null;
    isContactSettingsSelected.value = false;
    selectedCanvasSectionKey.value = sectionKey;
};

const selectContactSettings = () => {
    inlineEditTarget.value = null;
    selectedCanvasSectionKey.value = null;
    isContactSettingsSelected.value = true;
};

const setSectionEnabled = (sectionKey: string, enabled: boolean) => {
    const section = form.sections.find((item: FormSection) => item.section_key === sectionKey);
    if (section) section.is_enabled = enabled;
};

const closeItemEditor = () => {
    if (inlineEditTarget.value) selectedCanvasSectionKey.value = inlineEditTarget.value.sectionKey;
    inlineEditTarget.value = null;
};

const handleInlineEditMessage = (event: MessageEvent<unknown>) => {
    if (
        event.origin !== window.location.origin ||
        event.source !== previewFrame.value?.contentWindow ||
        !isRecord(event.data) ||
        event.data.type !== 'quickfolio:inline-edit'
    ) {
        return;
    }

    const { action, sectionKey, fieldDefinitionId, path } = event.data;
    if (action === 'select-section') {
        if (typeof sectionKey !== 'string' || !form.sections.some((section: FormSection) => section.section_key === sectionKey)) return;
        selectSection(sectionKey);
        return;
    }

    if (
        typeof sectionKey !== 'string' ||
        typeof fieldDefinitionId !== 'number' ||
        !Array.isArray(path) ||
        !path.every((part) => typeof part === 'string' || typeof part === 'number')
    ) {
        return;
    }

    const fieldValue = findFormFieldValue(sectionKey, fieldDefinitionId);
    const definition = fieldDefMap.value.get(fieldDefinitionId);
    if (!fieldValue || !definition || valueAtPath(fieldValue.value, path) === undefined) return;

    if (action === 'select') {
        if (event.data.kind !== 'text' && event.data.kind !== 'image') return;
        const isText = event.data.kind === 'text';
        inlineEditTarget.value = {
            sectionKey,
            fieldDefinitionId,
            fieldKey: definition.field_key,
            label: definition.label,
            path,
            kind: event.data.kind,
        };
        selectedCanvasSectionKey.value = sectionKey;
        isContactSettingsSelected.value = false;

        // Take the user straight to the control that edits what they clicked.
        if (definition.data_type === 'array' && typeof path[0] === 'number') {
            const key = typeof path[1] === 'string' ? path[1] : 'value';
            focusInInspector(`#item-field-${key}`, { focus: isText });
        } else {
            focusSectionField(fieldDefinitionId, isText);
        }
    }
};


const selectedArrayIndex = computed(() => {
    const target = inlineEditTarget.value;
    return target?.path[0] !== undefined && typeof target.path[0] === 'number'
        ? target.path[0]
        : null;
});

const canEditSelectedArray = computed(() =>
    inlineEditTarget.value !== null &&
    fieldDefMap.value.get(inlineEditTarget.value.fieldDefinitionId)?.data_type === 'array',
);

const selectedArrayItem = computed<Record<string, any> | string | null>(() => {
    const target = inlineEditTarget.value;
    const index = selectedArrayIndex.value;
    if (!target || index === null) return null;
    const fieldValue = findFormFieldValue(target.sectionKey, target.fieldDefinitionId);
    return Array.isArray(fieldValue?.value) ? fieldValue.value[index] ?? null : null;
});

const selectedArrayItemFields = computed<RepeatableItemField[]>(() => {
    const target = inlineEditTarget.value;
    if (!target || !canEditSelectedArray.value) return [];

    const fieldsByKey: Record<string, RepeatableItemField[]> = {
        skills_list: [
            { key: 'label', label: 'Skill name', type: 'text', placeholder: 'e.g. Vue.js' },
            { key: 'percent', label: 'Proficiency', type: 'percent' },
            { key: 'icon', label: 'Icon (optional)', type: 'text', placeholder: 'Icon name' },
        ],
        journey_list: [
            { key: 'period', label: 'Period', type: 'text', placeholder: 'e.g. 2022–Present' },
            { key: 'title', label: 'Role or degree', type: 'text', placeholder: 'e.g. Product Designer' },
            { key: 'company', label: 'Institution or company', type: 'text', placeholder: 'e.g. Acme University' },
            { key: 'description', label: 'Description', type: 'textarea', placeholder: 'Describe this experience' },
        ],
        projects_list: [
            { key: 'title', label: 'Project name', type: 'text', placeholder: 'e.g. Portfolio website' },
            { key: 'category', label: 'Category', type: 'text', placeholder: 'e.g. Web app' },
            { key: 'description', label: 'Description', type: 'textarea', placeholder: 'Describe the project' },
            { key: 'url', label: 'Project URL', type: 'text', placeholder: 'https://' },
            { key: 'image', label: 'Project image', type: 'image' },
            { key: 'featured', label: 'Featured project', type: 'boolean' },
        ],
        services_list: [
            { key: 'title', label: 'Service name', type: 'text', placeholder: 'e.g. Web development' },
            { key: 'description', label: 'Description', type: 'textarea', placeholder: 'Describe this service' },
            { key: 'icon', label: 'Icon (optional)', type: 'text', placeholder: 'Icon name' },
            { key: 'url', label: 'Service URL (optional)', type: 'text', placeholder: 'https://' },
        ],
        hero_social_links: [
            { key: 'platform', label: 'Platform', type: 'select', options: socialPlatforms },
            { key: 'url', label: 'Profile URL', type: 'text', placeholder: 'https://' },
        ],
        contact_social_links: [
            { key: 'label', label: 'Label', type: 'text', placeholder: 'e.g. GitHub or Home' },
            { key: 'url', label: 'Profile URL', type: 'text', placeholder: 'https://' },
        ],
        nav_links: [
            { key: 'label', label: 'Link label', type: 'text', placeholder: 'e.g. About' },
            { key: 'url', label: 'Link destination', type: 'text', placeholder: '#about' },
        ],
        about_highlights: [{ key: 'value', label: 'Highlight', type: 'text', placeholder: 'e.g. 5+ years experience' }],
    };

    const item = selectedArrayItem.value;
    if (typeof item === 'string') {
        return [{ key: 'value', label: 'Highlight', type: 'text', placeholder: 'Add a highlight' }];
    }

    const configuredFields = fieldsByKey[target.fieldKey];
    if (configuredFields) return configuredFields;
    if (item && typeof item === 'object') {
        return Object.keys(item).map((key) => ({
            key,
            label: humanize(key),
            type: typeof item[key] === 'number' ? 'number' : typeof item[key] === 'boolean' ? 'boolean' : 'text',
        }));
    }
    return [];
});

const setSelectedArrayProperty = (key: string, value: unknown) => {
    const target = inlineEditTarget.value;
    const index = selectedArrayIndex.value;
    if (!target || index === null) return;

    const fieldValue = findFormFieldValue(target.sectionKey, target.fieldDefinitionId);
    if (!fieldValue || !Array.isArray(fieldValue.value)) return;

    let item = fieldValue.value[index];
    if (typeof item === 'string') {
        item = { value: item };
        fieldValue.value[index] = item;
    }
    if (item && typeof item === 'object') item[key] = value;
};
const selectedArrayProperty = (key: string): string | number | boolean | undefined => {
    const item = selectedArrayItem.value;
    if (key === 'value' && typeof item === 'string') return item;
    if (!item || typeof item !== 'object') return undefined;
    const value = item[key];
    return typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ? value : undefined;
};

const uploadSelectedArrayImage = (event: Event, key: string) => {
    const target = inlineEditTarget.value;
    const index = selectedArrayIndex.value;
    if (!target || index === null) return;

    const fieldValue = findFormFieldValue(target.sectionKey, target.fieldDefinitionId);
    if (!fieldValue || !Array.isArray(fieldValue.value)) return;

    uploadFile(
        event,
        `inline-${target.fieldKey}-${index}-${key}`,
        'image',
        () => fieldValue.value[index]?.[key],
        (value) => setSelectedArrayProperty(key, value),
    );
};

const selectedSection = computed(() =>
    form.sections.find((section: FormSection) =>
        section.section_key === (inlineEditTarget.value?.sectionKey ?? selectedCanvasSectionKey.value),
    ),
);

const selectedSectionFields = computed(() =>
    selectedSection.value ? fieldsForSection(selectedSection.value.section_key) : [],
);

const selectedSectionValues = computed<Record<number, FormFieldValue>>(() => {
    const values: Record<number, FormFieldValue> = {};
    selectedSection.value?.field_values.forEach((fieldValue) => {
        values[fieldValue.field_definition_id] = fieldValue;
    });
    return values;
});


/* -------------------------------------------------------------------------
 * Repeatable items (projects, skills, links, …)
 * ---------------------------------------------------------------------- */

const itemNouns: Record<string, string> = {
    skills_list: 'skill',
    journey_list: 'experience',
    projects_list: 'project',
    services_list: 'service',
    hero_social_links: 'link',
    contact_social_links: 'link',
    nav_links: 'link',
    about_highlights: 'highlight',
};
const itemNoun = (fieldKey: string) => itemNouns[fieldKey] ?? 'item';

const itemTitle = (item: unknown, index: number): string => {
    if (typeof item === 'string') return item.trim() || `Item ${index + 1}`;
    if (item && typeof item === 'object') {
        const record = item as Record<string, unknown>;
        const text = [record.title, record.label, record.platform, record.value].find(
            (part) => typeof part === 'string' && part.trim() !== '',
        );
        if (typeof text === 'string') {
            return socialPlatforms.find((platform) => platform.value === text)?.label ?? text;
        }
    }
    return `Item ${index + 1}`;
};

const itemSummary = (item: unknown): string => {
    if (!item || typeof item !== 'object') return '';
    const record = item as Record<string, unknown>;
    if (typeof record.percent === 'number') return `${record.percent}%`;
    const text = [record.category, record.company, record.period, record.url].find(
        (part) => typeof part === 'string' && part.trim() !== '',
    );
    return typeof text === 'string' ? text : '';
};

const arrayItems = (fieldDefinitionId: number): unknown[] => {
    const value = selectedSectionValues.value[fieldDefinitionId]?.value;
    return Array.isArray(value) ? value : [];
};

const selectedArrayText = (key: string): string | number => {
    const value = selectedArrayProperty(key);
    return typeof value === 'string' || typeof value === 'number' ? value : '';
};

const selectedItemTitle = computed(() => itemTitle(selectedArrayItem.value, selectedArrayIndex.value ?? 0));

const selectedItemCount = computed(() => {
    const target = inlineEditTarget.value;
    if (!target) return 0;
    const value = findFormFieldValue(target.sectionKey, target.fieldDefinitionId)?.value;
    return Array.isArray(value) ? value.length : 0;
});

const highlightedItemKey = computed(() => {
    const key = inlineEditTarget.value?.path[1];
    return typeof key === 'string' ? key : null;
});

const itemImageKey = (key: string) =>
    `inline-${inlineEditTarget.value?.fieldKey}-${selectedArrayIndex.value}-${key}`;

/** Remove an item immediately, with a short window to undo. */
const removeItemWithUndo = (fieldValue: FormFieldValue, index: number) => {
    if (!Array.isArray(fieldValue.value) || index < 0 || index >= fieldValue.value.length) return;

    const label = itemTitle(fieldValue.value[index], index);
    const [removed] = fieldValue.value.splice(index, 1);
    showToast(`Removed “${label}”`, {
        scope: 'form',
        undo: () => {
            if (!Array.isArray(fieldValue.value)) fieldValue.value = [];
            fieldValue.value.splice(Math.min(index, fieldValue.value.length), 0, removed);
        },
        onExpire: () => discardPendingUploads(removed),
    });
};

const editSectionArrayItem = (field: FieldDefinition, index: number) => {
    const section = selectedSection.value;
    if (!section) return;
    inlineEditTarget.value = {
        sectionKey: section.section_key,
        fieldDefinitionId: field.id,
        fieldKey: field.field_key,
        label: field.label,
        path: [index],
        kind: 'text',
    };
    selectedCanvasSectionKey.value = section.section_key;
    isContactSettingsSelected.value = false;
    focusInInspector('#item-fields');
};

const addSectionArrayItem = (field: FieldDefinition) => {
    const value = selectedSectionValues.value[field.id];
    if (!value) return;
    if (!Array.isArray(value.value)) value.value = [];
    value.value.push(defaultArrayItem(field.field_key));
    // Go straight to editing the new item instead of leaving a placeholder behind.
    editSectionArrayItem(field, value.value.length - 1);
};

const removeSectionArrayItem = (field: FieldDefinition, index: number) => {
    const value = selectedSectionValues.value[field.id];
    if (value) removeItemWithUndo(value, index);
};

const moveSectionArrayItem = (field: FieldDefinition, index: number, direction: -1 | 1) => {
    const items = selectedSectionValues.value[field.id]?.value;
    const next = index + direction;
    if (!Array.isArray(items) || next < 0 || next >= items.length) return;
    [items[index], items[next]] = [items[next], items[index]];
};

const addSelectedArrayItem = () => {
    const target = inlineEditTarget.value;
    if (!target || !canEditSelectedArray.value) return;

    const fieldValue = findFormFieldValue(target.sectionKey, target.fieldDefinitionId);
    if (!fieldValue) return;
    if (!Array.isArray(fieldValue.value)) fieldValue.value = [];
    fieldValue.value.push(defaultArrayItem(target.fieldKey));

    const firstKey = target.fieldKey === 'skills_list'
        ? 'label'
        : ['projects_list', 'journey_list', 'services_list'].includes(target.fieldKey)
            ? 'title'
            : null;
    inlineEditTarget.value = {
        ...target,
        path: [fieldValue.value.length - 1, ...(firstKey ? [firstKey] : [])],
    };
    focusInInspector(`#item-field-${firstKey ?? 'value'}`);
};

const removeSelectedArrayItem = () => {
    const target = inlineEditTarget.value;
    const index = selectedArrayIndex.value;
    if (!target || index === null) return;

    const fieldValue = findFormFieldValue(target.sectionKey, target.fieldDefinitionId);
    if (!fieldValue) return;
    closeItemEditor();
    removeItemWithUndo(fieldValue, index);
};

const clearSelectedArrayImage = (key: string, label: string) => {
    const target = inlineEditTarget.value;
    const index = selectedArrayIndex.value;
    if (!target || index === null) return;

    const fieldValue = findFormFieldValue(target.sectionKey, target.fieldDefinitionId);
    const item = Array.isArray(fieldValue?.value) ? fieldValue.value[index] : null;
    if (!fieldValue || !item || typeof item !== 'object' || !item[key]) return;

    const previous = item[key];
    item[key] = null;
    delete uploadedFiles.value[itemImageKey(key)];
    showToast(`${label} removed`, {
        scope: 'form',
        undo: () => {
            const current = Array.isArray(fieldValue.value) ? fieldValue.value[index] : null;
            if (current && typeof current === 'object') current[key] = previous;
        },
        onExpire: () => discardPendingUploads(previous),
    });
};

/* -------------------------------------------------------------------------
 * Image & file fields on a whole section
 * ---------------------------------------------------------------------- */

const acceptFor = (dataType: string) =>
    dataType === 'image' ? 'image/jpeg,image/png,image/gif,image/webp' : '.pdf,.doc,.docx,.txt,.zip';

const assetName = (value: unknown, uploadKey: string): string => {
    if (pendingUploadId(value)) return uploadedFiles.value[uploadKey] || 'New file';
    const raw = typeof value === 'string' ? value : '';
    try {
        return decodeURIComponent(raw.split('?')[0].split('/').pop() || 'Current file');
    } catch {
        return 'Current file';
    }
};

const uploadSectionAsset = (event: Event, field: FieldDefinition) => {
    const fieldValue = selectedSectionValues.value[field.id];
    if (!fieldValue) return;
    uploadFile(
        event,
        field.field_key,
        field.data_type === 'image' ? 'image' : 'file',
        () => fieldValue.value,
        (value) => {
            fieldValue.value = value;
        },
    );
};

const clearSectionAsset = (field: FieldDefinition) => {
    const fieldValue = selectedSectionValues.value[field.id];
    if (!fieldValue || !fieldValue.value) return;

    const previous = fieldValue.value;
    fieldValue.value = null;
    delete uploadedFiles.value[field.field_key];
    showToast(`${field.label} removed`, {
        scope: 'form',
        undo: () => {
            fieldValue.value = previous;
        },
        onExpire: () => discardPendingUploads(previous),
    });
};

const placeholderFor = (field: FieldDefinition) =>
    field.field_key.includes('cta') && field.field_key.endsWith('_url')
        ? 'Enter a URL, #section, mailto:, or tel: link'
        : guidanceForField(field).example || `Enter ${field.label.toLowerCase()}`;


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


/* -------------------------------------------------------------------------
 * Inspector
 * One panel, four states: nothing selected → section → item, plus the contact
 * form integration. It replaces the old toolbar + three separate dialogs.
 * ---------------------------------------------------------------------- */

const sectionLabels: Record<string, string> = {
    navbar: 'Navigation',
    header: 'Intro',
    hero: 'Intro',
    about: 'About',
    journey: 'Experience',
    skills: 'Skills',
    projects: 'Projects',
    services: 'Services',
    contact: 'Contact',
};
const sectionLabel = (sectionKey: string) => sectionLabels[sectionKey] ?? humanize(sectionKey);
const capitalize = (value: string) => value.charAt(0).toUpperCase() + value.slice(1);

const inspectorMode = computed<'item' | 'section' | 'contact' | 'empty'>(() => {
    if (isContactSettingsSelected.value) return 'contact';
    if (
        inlineEditTarget.value &&
        selectedArrayIndex.value !== null &&
        canEditSelectedArray.value &&
        selectedArrayItem.value !== null
    ) {
        return 'item';
    }
    return selectedSection.value ? 'section' : 'empty';
});

const selectedSectionKeyForNav = computed(() =>
    isContactSettingsSelected.value ? null : (selectedSection.value?.section_key ?? null),
);

const inspectorViewKey = computed(
    () => `${inspectorMode.value}:${selectedSection.value?.section_key ?? ''}:${selectedArrayIndex.value ?? ''}`,
);

const inspectorEyebrow = computed(() => {
    switch (inspectorMode.value) {
        case 'item':
            return `${capitalize(itemNoun(inlineEditTarget.value?.fieldKey ?? ''))} ${(selectedArrayIndex.value ?? 0) + 1} of ${selectedItemCount.value}`;
        case 'section':
            return 'Section';
        case 'contact':
            return 'Integration';
        default:
            return 'Inspector';
    }
});

const inspectorTitle = computed(() => {
    switch (inspectorMode.value) {
        case 'item':
            return selectedItemTitle.value;
        case 'section':
            return sectionLabel(selectedSection.value?.section_key ?? '');
        case 'contact':
            return 'Contact form';
        default:
            return 'Edit your portfolio';
    }
});

const selectionLabel = computed(() => {
    if (isContactSettingsSelected.value) return 'Contact form';
    const section = selectedSection.value ? sectionLabel(selectedSection.value.section_key) : null;
    if (!section) return null;
    if (inspectorMode.value === 'item') return `${section} › ${selectedItemTitle.value}`;
    return inlineEditTarget.value ? `${section} › ${inlineEditTarget.value.label}` : section;
});

const contentFields = computed(() =>
    selectedSectionFields.value.filter(
        (field) =>
            field.field_definition.data_type !== 'boolean' && selectedSectionValues.value[field.field_definition.id],
    ),
);
const optionFields = computed(() =>
    selectedSectionFields.value.filter(
        (field) =>
            field.field_definition.data_type === 'boolean' && selectedSectionValues.value[field.field_definition.id],
    ),
);

const highlightedFieldId = computed(() => inlineEditTarget.value?.fieldDefinitionId ?? flashFieldId.value);

/* -------------------------------------------------------------------------
 * Publish readiness — makes "required to publish" visible and actionable
 * ---------------------------------------------------------------------- */

const isEmptyValue = (value: unknown): boolean => {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    return false;
};

const requiredIssues = computed<RequiredIssue[]>(() => {
    const issues: RequiredIssue[] = [];
    form.sections.forEach((section: FormSection) => {
        if (!section.is_enabled) return;
        const templateSection = props.portfolio.template.sections.find((item) => item.section_key === section.section_key);
        templateSection?.fields.forEach((field) => {
            if (!field.is_required) return;
            const fieldValue = section.field_values.find(
                (item: FormFieldValue) => item.field_definition_id === field.field_definition.id,
            );
            if (!fieldValue || isEmptyValue(fieldValue.value)) {
                issues.push({ sectionKey: section.section_key, field: field.field_definition });
            }
        });
    });
    return issues;
});

const issueCount = (sectionKey: string) =>
    requiredIssues.value.filter((issue) => issue.sectionKey === sectionKey).length;

const jumpToIssue = (issue: RequiredIssue) => {
    isChecklistOpen.value = false;
    selectSection(issue.sectionKey);
    focusSectionField(issue.field.id);
};

/* -------------------------------------------------------------------------
 * Contact form (Google Forms) — detect question IDs from the pasted link
 * ---------------------------------------------------------------------- */

const contactEntryFields = [
    { model: 'google_form_name_entry', label: 'Name question', placeholder: '123456789', guess: /name/i },
    { model: 'google_form_email_entry', label: 'Email question', placeholder: '234567890', guess: /e-?mail/i },
    { model: 'google_form_message_entry', label: 'Message question', placeholder: '345678901', guess: /message|msg/i },
];

const detectedFormEntries = computed(() => {
    const raw = String(form.google_form_url ?? '').trim();
    if (!raw) return [] as { id: string; sample: string }[];
    try {
        const found = new Map<string, string>();
        new URL(raw).searchParams.forEach((value, key) => {
            const match = key.match(/^entry\.(\d+)$/);
            if (match && !found.has(match[1])) found.set(match[1], value);
        });
        return [...found].map(([id, sample]) => ({ id, sample }));
    } catch {
        return [];
    }
});

const shortEntryId = (id: string) => (id.length > 6 ? `…${id.slice(-5)}` : id);

// Auto-match questions whose sample answer says what they are ("name", "email", "message"); never overwrite typed IDs.
watch(detectedFormEntries, (entries) => {
    const taken = new Set(contactEntryFields.map((field) => String(form[field.model] ?? '')).filter(Boolean));
    contactEntryFields.forEach((field) => {
        if (String(form[field.model] ?? '').trim()) return;
        const match = entries.find((entry) => field.guess.test(entry.sample) && !taken.has(entry.id));
        if (match) {
            form[field.model] = match.id;
            taken.add(match.id);
        }
    });
});

const contactStatus = computed<'connected' | 'incomplete' | 'none'>(() => {
    const values = ['google_form_url', ...contactEntryFields.map((field) => field.model)].map((key) =>
        String(form[key] ?? '').trim(),
    );
    const filled = values.filter(Boolean).length;
    return filled === 0 ? 'none' : filled === values.length ? 'connected' : 'incomplete';
});

const contactStatusLabel = computed(
    () => ({ connected: 'Connected', incomplete: 'Incomplete', none: 'Not set up' })[contactStatus.value],
);
const contactStatusClass = computed(
    () =>
        ({
            connected: 'bg-[#EFFAF4] text-[#16734A] dark:bg-emerald-950/40 dark:text-emerald-300',
            incomplete: 'bg-[#FFF8E9] text-[#9A6700] dark:bg-amber-950/40 dark:text-amber-300',
            none: 'bg-[#F1F5F4] text-[#64748B] dark:bg-slate-800 dark:text-slate-400',
        })[contactStatus.value],
);

/* -------------------------------------------------------------------------
 * Saving, publishing, template switching
 * ---------------------------------------------------------------------- */

const savePortfolio = (afterSave?: () => void) => {
    if (hasPendingUploads.value || form.processing) return;
    // Close the undo window first so removed items' files aren't uploaded needlessly.
    finalizeToast();

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
        onSuccess: () => {
            clearPendingUploads();
            form.defaults();
            afterSave?.();
        },
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

/** Template-safe wrapper (a click handler would otherwise pass the event as `afterSave`). */
const saveChanges = () => savePortfolio();

/** Run an action that needs saved data, saving first if there are unsaved changes. */
const runAfterSave = (next: () => void) => {
    if (form.isDirty) savePortfolio(next);
    else next();
};

const saveStatus = computed<'saving' | 'preparing' | 'error' | 'dirty' | 'saved'>(() => {
    if (form.processing) return 'saving';
    if (hasPendingUploads.value) return 'preparing';
    if (form.isDirty) return form.hasErrors ? 'error' : 'dirty';
    return 'saved';
});

const saveStatusText = computed(
    () =>
        ({
            saving: 'Saving…',
            preparing: 'Preparing upload…',
            error: 'Couldn’t save — see details below',
            dirty: 'Unsaved changes',
            saved: 'All changes saved',
        })[saveStatus.value],
);

const saveDisabled = computed(() => !form.isDirty || form.processing || hasPendingUploads.value);

const publishForm = useForm<{ publish?: string }>({});
watch(
    () => form.sections,
    () => publishForm.clearErrors('publish'),
    { deep: true },
);

const publishLabel = computed(() => (isPublished.value ? 'Unpublish' : form.isDirty ? 'Save & publish' : 'Publish'));
const publishDisabled = computed(() => publishForm.processing || form.processing || hasPendingUploads.value);
// Emphasis follows the next best action: save while there are changes, publish once everything is saved and ready.
const publishIsPrimary = computed(() => !isPublished.value && !form.isDirty && requiredIssues.value.length === 0);

const togglePublish = (isUndo = false) => {
    const wasPublished = props.portfolio.status === 'published';
    publishForm.post(route('portfolios.publish', props.portfolio.id as any), {
        preserveScroll: true,
        onSuccess: () => {
            if (wasPublished) {
                showToast('Portfolio unpublished — it is no longer public', {
                    undo: () => togglePublish(true),
                    duration: 9000,
                });
            } else {
                showToast(isUndo ? 'Portfolio is live again' : 'Your portfolio is live');
            }
        },
    });
};

const publishPortfolio = () => {
    if (publishDisabled.value) return;
    if (!isPublished.value && requiredIssues.value.length > 0) {
        isChecklistOpen.value = true;
        return;
    }
    runAfterSave(() => togglePublish());
};

const switchForm = useForm({ template_id: props.portfolio.template.id });
const openTemplateDialog = () => {
    isMoreOpen.value = false;
    isSwitchModalOpen.value = true;
};
const switchTemplate = () => {
    runAfterSave(() => {
        switchForm.template_id = selectedTemplateId.value;
        switchForm.post(route('portfolios.switch-template', props.portfolio.id as any), {
            onSuccess: () => {
                isSwitchModalOpen.value = false;
            },
        });
    });
};

const errorMessages = computed(() => {
    const messages = [
        ...Object.values(form.errors),
        ...Object.values(uploadErrors.value),
        publishForm.errors.publish,
    ].filter((message): message is string => typeof message === 'string' && message !== '');
    return [...new Set(messages)].slice(0, 4);
});

const dismissErrors = () => {
    form.clearErrors();
    publishForm.clearErrors();
    uploadErrors.value = {};
};

/* -------------------------------------------------------------------------
 * Leaving the editor with unsaved changes
 * ---------------------------------------------------------------------- */

const leaveWithoutSaving = () => {
    const url = pendingLeaveUrl ?? route('dashboard');
    isLeaveOpen.value = false;
    bypassLeaveGuard = true;
    router.visit(url);
    window.setTimeout(() => {
        bypassLeaveGuard = false;
    }, 0);
};

const saveAndLeave = () => savePortfolio(leaveWithoutSaving);

/* -------------------------------------------------------------------------
 * Keyboard, global listeners, lifecycle
 * ---------------------------------------------------------------------- */

const anyDialogOpen = computed(() => isChecklistOpen.value || isLeaveOpen.value || isSwitchModalOpen.value);

const handleKeydown = (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        savePortfolio();
        return;
    }
    if (event.key !== 'Escape') return;

    if (isMoreOpen.value) {
        isMoreOpen.value = false;
        return;
    }
    if (anyDialogOpen.value) return;

    // First Escape leaves the field you are typing in; the next one clears the selection.
    const active = document.activeElement as HTMLElement | null;
    if (active && active.matches('input, textarea, select') && active.closest('[data-inspector]')) {
        active.blur();
        return;
    }
    if (inspectorMode.value !== 'empty') clearSelection();
};

const handleDocumentPointerDown = (event: PointerEvent) => {
    if (isMoreOpen.value && !moreMenuRoot.value?.contains(event.target as Node)) isMoreOpen.value = false;
};

const handleWindowBlur = () => {
    isMoreOpen.value = false;
};

const handleBeforeUnload = (event: BeforeUnloadEvent) => {
    if (!form.isDirty) return;
    event.preventDefault();
    event.returnValue = '';
};

let removeBeforeVisitListener: (() => void) | undefined;

onMounted(() => {
    window.addEventListener('message', handleInlineEditMessage);
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('beforeunload', handleBeforeUnload);
    window.addEventListener('blur', handleWindowBlur);
    document.addEventListener('pointerdown', handleDocumentPointerDown);

    // Intercept in-app navigation (back button, logo) while there are unsaved changes.
    removeBeforeVisitListener = router.on('before', (event) => {
        if (bypassLeaveGuard || !form.isDirty || event.detail.visit.method !== 'get') return;
        pendingLeaveUrl = String(event.detail.visit.url);
        isLeaveOpen.value = true;
        return false;
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('message', handleInlineEditMessage);
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('beforeunload', handleBeforeUnload);
    window.removeEventListener('blur', handleWindowBlur);
    document.removeEventListener('pointerdown', handleDocumentPointerDown);
    removeBeforeVisitListener?.();
    window.clearTimeout(previewTimer);
    window.clearTimeout(toastTimer);
    clearPendingUploads();
});

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

        if (toast.value?.scope === 'form') finalizeToast();

        const sectionExists = (key: string | null | undefined) =>
            !key || form.sections.some((section: FormSection) => section.section_key === key);
        if (!sectionExists(selectedCanvasSectionKey.value) || !sectionExists(inlineEditTarget.value?.sectionKey)) {
            clearSelection();
        }
    },
);


const humanize = (value: string) =>
    value.replace(/[-_]/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

const sectionGuidance: Record<string, string> = {
    navbar: 'This controls the branding visitors see in the navigation at the top of your site.',
    header: 'This is the opening area visitors see first, including your name, role, introduction, and main links.',
    about: 'Use this section to introduce yourself and share helpful background, highlights, or a resume.',
    journey: 'Show your work history, education, or other milestones in this timeline.',
    skills: 'Choose the skills and tools you want visitors to notice.',
    projects: 'Add examples of your work. Each item can appear as a project card in this section.',
    services: 'Describe the services you offer so visitors can understand how you can help.',
    contact: 'Give visitors a clear way to contact or follow you.',
};
sectionGuidance.hero = sectionGuidance.header;
</script>

<template>
    <Head :title="`Editing ${portfolio.title} — quickFolio`" />

    <div class="min-h-screen overflow-x-clip bg-[#F4F7F7] text-[#0F172A] transition-colors duration-200 dark:bg-slate-950 dark:text-slate-100">
        <!-- ───────────── Top bar: identity · save state · primary actions ───────────── -->
        <header class="sticky top-0 z-40 border-b border-[#E1E8E7] bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
            <div class="mx-auto flex h-14 max-w-[1800px] items-center justify-between gap-2 px-3 sm:px-5 xl:px-6">
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                    <Link
                        :href="route('dashboard')"
                        :class="[ui.btn, ui.secondary, 'h-10 w-10']"
                        aria-label="Back to dashboard"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Link>

                    <Link href="/" class="hidden shrink-0 items-center md:flex" aria-label="quickFolio home">
                        <AppLogo size="sm" />
                    </Link>
                    <span class="hidden h-6 w-px bg-[#E1E8E7] dark:bg-slate-700 md:block" aria-hidden="true" />

                    <div class="min-w-0">
                        <div class="flex min-w-0 items-center gap-2">
                            <h1 class="max-w-[32vw] truncate text-sm font-bold sm:max-w-xs sm:text-base">{{ portfolio.title }}</h1>
                            <span
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize sm:text-xs"
                                :class="isPublished
                                    ? 'border-[#C7E6D8] bg-[#EFFAF4] text-[#16734A] dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'border-[#F0DDB8] bg-[#FFF8E9] text-[#9A6700] dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300'"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="isPublished ? 'bg-[#16A34A]' : 'bg-[#D99A18]'" />
                                {{ portfolio.status }}
                            </span>
                        </div>
                        <p
                            class="mt-0.5 flex items-center gap-1.5 text-xs"
                            :class="{
                                'text-[#9A6700] dark:text-amber-400': saveStatus === 'dirty',
                                'text-red-600 dark:text-red-400': saveStatus === 'error',
                                [ui.muted]: saveStatus === 'saved' || saveStatus === 'saving' || saveStatus === 'preparing',
                            }"
                            role="status"
                            aria-live="polite"
                        >
                            <LoaderCircle v-if="saveStatus === 'saving' || saveStatus === 'preparing'" class="h-3 w-3 animate-spin" />
                            <AlertCircle v-else-if="saveStatus === 'error'" class="h-3 w-3" />
                            <span v-else-if="saveStatus === 'dirty'" class="h-1.5 w-1.5 rounded-full bg-[#D99A18]" />
                            <Check v-else class="h-3 w-3 text-[#16A34A]" />
                            {{ saveStatusText }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                    <a
                        v-if="isPublished"
                        :href="route('portfolios.published', portfolio.slug as any)"
                        target="_blank"
                        rel="noopener noreferrer"
                        :class="[ui.btn, ui.accent, 'h-10 w-10 sm:w-auto sm:px-3']"
                        title="View live portfolio"
                    >
                        <Eye class="h-4 w-4" />
                        <span class="hidden sm:inline">View live</span>
                        <ArrowUpRight class="hidden h-3.5 w-3.5 sm:inline" />
                    </a>

                    <button
                        type="button"
                        :disabled="saveDisabled"
                        :class="[ui.btn, form.isDirty ? ui.primary : ui.secondary, 'h-10 px-3 sm:px-4']"
                        title="Save changes (Ctrl+S)"
                        :aria-label="saveStatus === 'saved' ? 'All changes saved' : 'Save changes'"
                        @click="saveChanges"
                    >
                        <LoaderCircle v-if="saveStatus === 'saving' || saveStatus === 'preparing'" class="h-4 w-4 animate-spin" />
                        <Save v-else-if="form.isDirty" class="h-4 w-4" />
                        <Check v-else class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ form.isDirty ? 'Save' : 'Saved' }}</span>
                    </button>

                    <button
                        type="button"
                        :disabled="publishDisabled"
                        :class="[ui.btn, publishIsPrimary ? ui.primary : ui.secondary, 'relative h-10 px-3 sm:px-4']"
                        :aria-label="publishLabel"
                        @click="publishPortfolio"
                    >
                        <LoaderCircle v-if="publishForm.processing" class="h-4 w-4 animate-spin" />
                        <Globe2 v-else class="h-4 w-4" :class="publishIsPrimary ? '' : 'text-[#00695C] dark:text-teal-300'" />
                        <span class="hidden sm:inline">{{ publishLabel }}</span>
                        <span
                            v-if="!isPublished && requiredIssues.length"
                            class="absolute -right-1.5 -top-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-[#D99A18] px-1 text-[10px] font-bold text-white"
                            :title="`${requiredIssues.length} required ${requiredIssues.length === 1 ? 'field needs' : 'fields need'} content`"
                        >
                            {{ requiredIssues.length }}
                        </span>
                    </button>

                    <div ref="moreMenuRoot" class="relative">
                        <button
                            type="button"
                            :class="[ui.btn, ui.secondary, 'h-10 w-10']"
                            aria-label="More actions"
                            :aria-expanded="isMoreOpen"
                            @click="isMoreOpen = !isMoreOpen"
                        >
                            <MoreHorizontal class="h-4 w-4" />
                        </button>
                        <div
                            v-if="isMoreOpen"
                            class="absolute right-0 top-12 z-50 w-56 rounded-xl border border-[#E1E8E7] bg-white p-1.5 shadow-lg dark:border-slate-700 dark:bg-slate-900"
                        >
                            <a
                                :href="route('portfolios.download', portfolio.id as any)"
                                class="flex min-h-10 items-center gap-2.5 rounded-lg px-3 text-sm font-medium text-[#334155] hover:bg-[#F4F7F7] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 dark:text-slate-200 dark:hover:bg-slate-800"
                                @click="isMoreOpen = false"
                            >
                                <Download class="h-4 w-4 text-[#00695C] dark:text-teal-300" />
                                Download as ZIP
                            </a>
                            <button
                                v-if="isAdmin"
                                type="button"
                                class="flex min-h-10 w-full items-center gap-2.5 rounded-lg px-3 text-left text-sm font-medium text-[#334155] hover:bg-[#F4F7F7] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 dark:text-slate-200 dark:hover:bg-slate-800"
                                @click="openTemplateDialog"
                            >
                                <LayoutTemplate class="h-4 w-4 text-[#00695C] dark:text-teal-300" />
                                Change template
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main
            class="mx-auto w-full max-w-[1800px] min-w-0 px-3 py-3 sm:px-5 xl:px-6 xl:py-5"
            :class="inspectorMode !== 'empty' ? 'pb-[56dvh] xl:pb-5' : ''"
        >
            <!-- Problems are surfaced once, in one place, and can be dismissed -->
            <div
                v-if="errorMessages.length"
                class="mb-3 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
                role="alert"
            >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                <ul class="min-w-0 flex-1 space-y-1">
                    <li v-for="message in errorMessages" :key="message">{{ message }}</li>
                </ul>
                <button
                    type="button"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400 dark:hover:bg-red-900/40"
                    aria-label="Dismiss"
                    @click="dismissErrors"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div
                class="grid min-w-0 gap-3 xl:gap-5"
                :class="isFocusMode ? 'xl:grid-cols-[minmax(0,1fr)_380px]' : 'xl:grid-cols-[208px_minmax(0,1fr)_380px]'"
            >
                <!-- ───────────── Outline: every section, including hidden ones ───────────── -->
                <nav
                    aria-label="Portfolio sections"
                    class="min-w-0 xl:sticky xl:top-[76px] xl:self-start"
                    :class="isFocusMode ? 'xl:hidden' : ''"
                >
                    <p class="hidden px-3 pb-2 text-[11px] font-semibold uppercase tracking-wide text-[#64748B] dark:text-slate-400 xl:block">Sections</p>
                    <ul class="-mx-3 flex gap-2 overflow-x-auto px-3 pb-1 sm:-mx-5 sm:px-5 xl:mx-0 xl:flex-col xl:gap-0.5 xl:overflow-visible xl:px-0 xl:pb-0">
                        <li v-for="section in form.sections" :key="section.id" class="shrink-0 xl:shrink">
                            <div class="group flex items-center rounded-xl border transition-colors" :class="navItemClass(selectedSectionKeyForNav === section.section_key)">
                                <button
                                    type="button"
                                    class="flex min-h-10 min-w-0 flex-1 items-center gap-2 rounded-xl px-3 text-left text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50"
                                    :aria-current="selectedSectionKeyForNav === section.section_key ? 'true' : undefined"
                                    @click="selectSection(section.section_key)"
                                >
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full"
                                        :class="section.is_enabled ? 'bg-[#22A06B]' : 'bg-[#CBD5E1] dark:bg-slate-600'"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate" :class="section.is_enabled ? '' : 'text-[#94A3B8] dark:text-slate-500'">{{ sectionLabel(section.section_key) }}</span>
                                    <span v-if="!section.is_enabled" class="shrink-0 text-[10px] font-medium uppercase tracking-wide text-[#94A3B8] dark:text-slate-500">Hidden</span>
                                    <span
                                        v-else-if="issueCount(section.section_key)"
                                        class="ml-auto grid h-4 min-w-4 shrink-0 place-items-center rounded-full bg-[#FFF1D0] px-1 text-[10px] font-bold text-[#9A6700] dark:bg-amber-950/60 dark:text-amber-300"
                                        :title="`${issueCount(section.section_key)} required to publish`"
                                    >
                                        {{ issueCount(section.section_key) }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="mr-1 hidden h-8 w-8 shrink-0 place-items-center rounded-lg text-[#64748B] transition hover:bg-black/5 hover:text-[#0F172A] focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-slate-100 xl:grid"
                                    :class="section.is_enabled ? 'opacity-0 group-hover:opacity-100' : 'opacity-100'"
                                    :aria-label="`${section.is_enabled ? 'Hide' : 'Show'} ${sectionLabel(section.section_key)} section`"
                                    :title="section.is_enabled ? 'Hide section' : 'Show section'"
                                    @click="setSectionEnabled(section.section_key, !section.is_enabled)"
                                >
                                    <EyeOff v-if="section.is_enabled" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </li>

                        <li class="shrink-0 xl:mt-2 xl:shrink xl:border-t xl:border-[#E1E8E7] xl:pt-2 dark:xl:border-slate-800">
                            <button
                                type="button"
                                class="flex min-h-10 w-full items-center gap-2 rounded-xl border px-3 text-left text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50"
                                :class="navItemClass(isContactSettingsSelected)"
                                :aria-current="isContactSettingsSelected ? 'true' : undefined"
                                @click="selectContactSettings"
                            >
                                <Settings2 class="h-4 w-4 shrink-0 text-[#00695C] dark:text-teal-300" />
                                <span class="truncate">Contact form</span>
                                <span class="ml-auto shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold" :class="contactStatusClass">{{ contactStatusLabel }}</span>
                            </button>
                        </li>
                    </ul>

                    <p class="mt-4 hidden px-3 text-xs leading-5 text-[#94A3B8] dark:text-slate-500 xl:block">
                        Template · <span class="font-medium text-[#64748B] dark:text-slate-400">{{ portfolio.template.name }}</span>
                    </p>
                </nav>

                <!-- ───────────── Canvas ───────────── -->
                <section class="min-w-0" aria-label="Portfolio canvas">
                    <div class="overflow-hidden rounded-xl border border-[#E1E8E7] bg-white dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex min-h-12 items-center gap-2 border-b border-[#EDF1F0] px-3 py-1.5 dark:border-slate-800">
                            <div role="group" aria-label="Preview size" class="inline-flex shrink-0 rounded-lg border border-[#DCE6E4] p-0.5 dark:border-slate-700">
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-9 items-center justify-center rounded-md transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50"
                                    :class="previewDevice === 'desktop' ? 'bg-[#E8F4F1] text-[#00695C] dark:bg-teal-900/50 dark:text-teal-300' : 'text-[#64748B] hover:bg-[#F4F7F7] dark:text-slate-400 dark:hover:bg-slate-800'"
                                    :aria-pressed="previewDevice === 'desktop'"
                                    aria-label="Desktop preview"
                                    title="Desktop"
                                    @click="previewDevice = 'desktop'"
                                >
                                    <Monitor class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-9 items-center justify-center rounded-md transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50"
                                    :class="previewDevice === 'mobile' ? 'bg-[#E8F4F1] text-[#00695C] dark:bg-teal-900/50 dark:text-teal-300' : 'text-[#64748B] hover:bg-[#F4F7F7] dark:text-slate-400 dark:hover:bg-slate-800'"
                                    :aria-pressed="previewDevice === 'mobile'"
                                    aria-label="Mobile preview"
                                    title="Mobile"
                                    @click="previewDevice = 'mobile'"
                                >
                                    <Smartphone class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="flex min-w-0 flex-1 items-center gap-2 text-xs" role="status" aria-live="polite">
                                <template v-if="selectionLabel">
                                    <span class="inline-flex min-w-0 items-center gap-1.5 rounded-full bg-[#E8F4F1] py-1 pl-2.5 pr-1 font-semibold text-[#00695C] dark:bg-teal-900/50 dark:text-teal-300">
                                        <span class="truncate">{{ selectionLabel }}</span>
                                        <button
                                            type="button"
                                            class="grid h-5 w-5 shrink-0 place-items-center rounded-full hover:bg-[#00695C]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50"
                                            aria-label="Clear selection"
                                            @click="clearSelection"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </span>
                                </template>
                                <p v-else class="flex min-w-0 items-center gap-1.5" :class="ui.muted">
                                    <MousePointerClick class="h-3.5 w-3.5 shrink-0" />
                                    <span class="truncate">Click any text, image or section to edit it</span>
                                </p>
                            </div>

                            <button
                                type="button"
                                :class="[ui.btn, ui.secondary, 'hidden h-8 px-2.5 text-xs xl:inline-flex']"
                                :aria-pressed="isFocusMode"
                                :title="isFocusMode ? 'Show the section list' : 'Hide the section list for a wider canvas'"
                                @click="isFocusMode = !isFocusMode"
                            >
                                <component :is="isFocusMode ? Minimize2 : Maximize2" class="h-3.5 w-3.5" />
                                {{ isFocusMode ? 'Show sections' : 'Widen' }}
                            </button>
                        </div>

                        <div class="relative bg-[#EEF3F2] dark:bg-slate-950/60">
                            <div
                                class="mx-auto bg-white transition-[max-width] duration-200 motion-reduce:transition-none"
                                :class="previewDevice === 'mobile' ? 'max-w-[390px] border-x border-[#DCE6E4] dark:border-slate-700' : 'max-w-full'"
                            >
                                <iframe
                                    :key="`${portfolio.id}:${portfolio.template.slug}`"
                                    ref="previewFrame"
                                    :src="`${route('portfolios.edit', portfolio.id as any)}?live_preview=1`"
                                    :title="`Live preview of ${portfolio.title}`"
                                    sandbox="allow-scripts allow-same-origin"
                                    class="block h-[calc(100dvh-176px)] min-h-[420px] w-full border-0 bg-white xl:h-[calc(100dvh-152px)] xl:min-h-[520px]"
                                    @load="onPreviewLoad"
                                />
                            </div>
                            <div
                                v-if="isPreviewLoading"
                                class="absolute inset-0 grid place-items-center bg-white/85 dark:bg-slate-950/85"
                                role="status"
                            >
                                <p class="flex items-center gap-2 text-sm font-medium" :class="ui.muted">
                                    <LoaderCircle class="h-4 w-4 animate-spin" />
                                    Loading your portfolio…
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ───────────── Inspector: context-sensitive, modeless, docked ───────────── -->
                <aside
                    data-inspector
                    aria-label="Inspector"
                    class="min-w-0 xl:sticky xl:top-[76px] xl:self-start"
                    :class="inspectorMode === 'empty' ? 'hidden xl:block' : 'block'"
                >
                    <div
                        class="inspector-sheet fixed inset-x-0 bottom-0 z-40 flex max-h-[52dvh] flex-col overflow-hidden rounded-t-2xl border border-b-0 border-[#E1E8E7] bg-white shadow-[0_-10px_30px_rgba(15,23,42,0.12)] dark:border-slate-800 dark:bg-slate-900 xl:static xl:z-auto xl:max-h-[calc(100dvh-96px)] xl:rounded-xl xl:border-b xl:shadow-none"
                    >
                        <!-- Header -->
                        <div class="flex items-start gap-2 border-b border-[#EDF1F0] px-3 py-3 dark:border-slate-800 sm:px-4">
                            <button
                                v-if="inspectorMode === 'item'"
                                type="button"
                                :class="[ui.btn, ui.secondary, 'mt-0.5 h-9 w-9']"
                                :aria-label="`Back to ${sectionLabel(inlineEditTarget?.sectionKey ?? '')}`"
                                title="Back to list"
                                @click="closeItemEditor"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </button>
                            <div class="min-w-0 flex-1 px-1">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#00695C] dark:text-teal-300">{{ inspectorEyebrow }}</p>
                                <h2 class="truncate text-base font-bold leading-6">{{ inspectorTitle }}</h2>
                            </div>
                            <button
                                v-if="inspectorMode !== 'empty'"
                                type="button"
                                :class="[ui.btn, ui.secondary, 'mt-0.5 h-9 w-9']"
                                aria-label="Close inspector"
                                title="Close (Esc)"
                                @click="clearSelection"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                            <div :key="inspectorViewKey" class="inspector-view">
                                <!-- ── Nothing selected ── -->
                                <div v-if="inspectorMode === 'empty'" class="space-y-5 p-4">
                                    <ul class="space-y-3 text-sm leading-6 text-[#475569] dark:text-slate-300">
                                        <li class="flex gap-3">
                                            <MousePointerClick class="mt-1 h-4 w-4 shrink-0 text-[#00695C] dark:text-teal-300" />
                                            <span><strong class="font-semibold">Click any text or image</strong> on your portfolio to edit it here, with a live preview.</span>
                                        </li>
                                        <li class="flex gap-3">
                                            <LayoutTemplate class="mt-1 h-4 w-4 shrink-0 text-[#00695C] dark:text-teal-300" />
                                            <span><strong class="font-semibold">Pick a section</strong> on the left to edit everything in it, including sections you’ve hidden.</span>
                                        </li>
                                        <li class="flex gap-3">
                                            <Save class="mt-1 h-4 w-4 shrink-0 text-[#00695C] dark:text-teal-300" />
                                            <span>Press <kbd class="rounded border border-[#DCE6E4] px-1 py-0.5 text-[11px] dark:border-slate-700">Ctrl</kbd> + <kbd class="rounded border border-[#DCE6E4] px-1 py-0.5 text-[11px] dark:border-slate-700">S</kbd> to save at any time.</span>
                                        </li>
                                    </ul>

                                    <div
                                        v-if="isPublished"
                                        class="flex items-start gap-3 rounded-xl border border-[#C7E6D8] bg-[#EFFAF4] p-3 text-sm dark:border-emerald-900 dark:bg-emerald-950/30"
                                    >
                                        <Globe2 class="mt-0.5 h-4 w-4 shrink-0 text-[#16734A] dark:text-emerald-300" />
                                        <p class="text-[#16734A] dark:text-emerald-300"><strong class="font-semibold">Your portfolio is live.</strong> Save to update the published page.</p>
                                    </div>
                                    <div
                                        v-else-if="requiredIssues.length === 0"
                                        class="flex items-start gap-3 rounded-xl border border-[#C7E6D8] bg-[#EFFAF4] p-3 text-sm dark:border-emerald-900 dark:bg-emerald-950/30"
                                    >
                                        <Check class="mt-0.5 h-4 w-4 shrink-0 text-[#16734A] dark:text-emerald-300" />
                                        <p class="text-[#16734A] dark:text-emerald-300"><strong class="font-semibold">Ready to publish.</strong> All required details are filled in.</p>
                                    </div>
                                    <div v-else class="rounded-xl border border-[#F0DDB8] bg-[#FFF8E9] p-3 dark:border-amber-900 dark:bg-amber-950/30">
                                        <p class="text-sm font-semibold text-[#9A6700] dark:text-amber-300">
                                            {{ requiredIssues.length }} {{ requiredIssues.length === 1 ? 'detail is' : 'details are' }} needed to publish
                                        </p>
                                        <ul class="mt-2 space-y-0.5">
                                            <li v-for="issue in requiredIssues" :key="`${issue.sectionKey}-${issue.field.id}`">
                                                <button
                                                    type="button"
                                                    class="flex min-h-9 w-full items-center justify-between gap-2 rounded-lg px-2 text-left text-sm text-[#7A5200] hover:bg-[#FFF1D0] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D99A18] dark:text-amber-200 dark:hover:bg-amber-950/50"
                                                    @click="jumpToIssue(issue)"
                                                >
                                                    <span class="min-w-0 truncate">{{ issue.field.label }} <span class="opacity-70">· {{ sectionLabel(issue.sectionKey) }}</span></span>
                                                    <ChevronRight class="h-4 w-4 shrink-0" />
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- ── A section ── -->
                                <div v-else-if="inspectorMode === 'section' && selectedSection" class="space-y-4 p-4">
                                    <p v-if="sectionGuidance[selectedSection.section_key]" :class="[ui.hint, 'text-[13px] leading-5']">
                                        {{ sectionGuidance[selectedSection.section_key] }}
                                    </p>

                                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-[#E1E8E7] bg-[#F8FAFA] px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/70">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold">Show on portfolio</span>
                                            <span :class="[ui.hint, 'block']">{{ selectedSection.is_enabled ? 'Visitors can see this section.' : 'Hidden — you can still edit its content.' }}</span>
                                        </span>
                                        <input
                                            type="checkbox"
                                            class="peer sr-only"
                                            :checked="selectedSection.is_enabled"
                                            @change="setSectionEnabled(selectedSection.section_key, ($event.target as HTMLInputElement).checked)"
                                        />
                                        <span :class="ui.switchTrack" aria-hidden="true" />
                                    </label>

                                    <!-- Content fields -->
                                    <div
                                        v-for="fieldConfig in contentFields"
                                        :id="`insp-field-${fieldConfig.field_definition.id}`"
                                        :key="fieldConfig.field_definition.id"
                                        class="-mx-2 space-y-2 rounded-xl p-2 transition-colors duration-300"
                                        :class="fieldWrapClass(highlightedFieldId === fieldConfig.field_definition.id)"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <Label :for="`section-field-${fieldConfig.field_definition.id}`" :class="ui.label">
                                                {{ fieldConfig.field_definition.label }}
                                            </Label>
                                            <span
                                                v-if="fieldConfig.is_required"
                                                class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                                                :class="isEmptyValue(selectedSectionValues[fieldConfig.field_definition.id]?.value)
                                                    ? 'bg-[#FFF1D0] text-[#9A6700] dark:bg-amber-950/60 dark:text-amber-300'
                                                    : 'bg-[#E8F4F1] text-[#00695C] dark:bg-teal-950/50 dark:text-teal-300'"
                                            >
                                                Required to publish
                                            </span>
                                        </div>
                                        <p :class="ui.hint">{{ guidanceForField(fieldConfig.field_definition).description }}</p>

                                        <!-- Repeatable list -->
                                        <div v-if="fieldConfig.field_definition.data_type === 'array'" class="space-y-2">
                                            <ul v-if="arrayItems(fieldConfig.field_definition.id).length" class="space-y-1.5">
                                                <li
                                                    v-for="(item, index) in arrayItems(fieldConfig.field_definition.id)"
                                                    :key="index"
                                                    class="flex items-center gap-0.5 rounded-xl border border-[#E1E8E7] bg-[#F8FBFA] pl-1 pr-1 dark:border-slate-700 dark:bg-slate-800/70"
                                                >
                                                    <button
                                                        type="button"
                                                        class="min-h-11 min-w-0 flex-1 rounded-lg px-2 text-left hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 dark:hover:bg-slate-800"
                                                        :aria-label="`Edit ${itemTitle(item, index)}`"
                                                        @click="editSectionArrayItem(fieldConfig.field_definition, index)"
                                                    >
                                                        <span class="block truncate text-sm font-semibold">{{ itemTitle(item, index) }}</span>
                                                        <span v-if="itemSummary(item)" :class="[ui.muted, 'block truncate text-xs']">{{ itemSummary(item) }}</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        :class="[ui.btn, 'h-9 w-8 text-[#64748B] hover:bg-black/5 dark:text-slate-400 dark:hover:bg-white/10']"
                                                        :disabled="index === 0"
                                                        :aria-label="`Move ${itemTitle(item, index)} up`"
                                                        @click="moveSectionArrayItem(fieldConfig.field_definition, index, -1)"
                                                    >
                                                        <ChevronUp class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        :class="[ui.btn, 'h-9 w-8 text-[#64748B] hover:bg-black/5 dark:text-slate-400 dark:hover:bg-white/10']"
                                                        :disabled="index === arrayItems(fieldConfig.field_definition.id).length - 1"
                                                        :aria-label="`Move ${itemTitle(item, index)} down`"
                                                        @click="moveSectionArrayItem(fieldConfig.field_definition, index, 1)"
                                                    >
                                                        <ChevronDown class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        :class="[ui.btn, ui.danger, 'h-9 w-9']"
                                                        :aria-label="`Remove ${itemTitle(item, index)}`"
                                                        @click="removeSectionArrayItem(fieldConfig.field_definition, index)"
                                                    >
                                                        <Trash2 class="h-4 w-4" />
                                                    </button>
                                                </li>
                                            </ul>
                                            <p v-else :class="[ui.muted, 'rounded-xl border border-dashed border-[#DCE6E4] px-3 py-4 text-center text-sm dark:border-slate-700']">
                                                No {{ itemNoun(fieldConfig.field_definition.field_key) }}s yet.
                                            </p>
                                            <button
                                                type="button"
                                                :class="[ui.btn, ui.accent, 'h-10 w-full px-3']"
                                                @click="addSectionArrayItem(fieldConfig.field_definition)"
                                            >
                                                <Plus class="h-4 w-4" />
                                                Add {{ itemNoun(fieldConfig.field_definition.field_key) }}
                                            </button>
                                        </div>

                                        <!-- Image / file -->
                                        <div v-else-if="['image', 'file'].includes(fieldConfig.field_definition.data_type)" class="space-y-2">
                                            <div
                                                v-if="selectedSectionValues[fieldConfig.field_definition.id]?.value"
                                                class="flex items-center gap-3 rounded-xl border border-[#E1E8E7] bg-[#F8FAFA] p-2.5 dark:border-slate-700 dark:bg-slate-800/70"
                                            >
                                                <img
                                                    v-if="fieldConfig.field_definition.data_type === 'image'"
                                                    :src="assetPreviewUrl(selectedSectionValues[fieldConfig.field_definition.id]?.value)"
                                                    alt=""
                                                    class="h-14 w-14 shrink-0 rounded-lg border border-[#E1E8E7] object-cover dark:border-slate-700"
                                                />
                                                <span v-else class="grid h-14 w-14 shrink-0 place-items-center rounded-lg border border-[#E1E8E7] bg-white dark:border-slate-700 dark:bg-slate-900">
                                                    <FileText class="h-5 w-5 text-[#00695C] dark:text-teal-300" />
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-sm font-semibold">
                                                        {{ assetName(selectedSectionValues[fieldConfig.field_definition.id]?.value, fieldConfig.field_definition.field_key) }}
                                                    </p>
                                                    <p v-if="pendingUploadId(selectedSectionValues[fieldConfig.field_definition.id]?.value)" class="text-xs text-[#9A6700] dark:text-amber-300">
                                                        Not saved yet
                                                    </p>
                                                    <a
                                                        v-else
                                                        :href="assetPreviewUrl(selectedSectionValues[fieldConfig.field_definition.id]?.value)"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="inline-flex items-center gap-1 text-xs font-semibold text-[#00695C] hover:underline dark:text-teal-300"
                                                    >
                                                        View current
                                                        <ArrowUpRight class="h-3 w-3" />
                                                    </a>
                                                </div>
                                                <button
                                                    type="button"
                                                    :class="[ui.btn, ui.danger, 'h-9 w-9']"
                                                    :aria-label="`Remove ${fieldConfig.field_definition.label}`"
                                                    @click="clearSectionAsset(fieldConfig.field_definition)"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>

                                            <label :class="ui.dropzone">
                                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-[#00695C] shadow-sm dark:bg-slate-900 dark:text-teal-300">
                                                    <ImageIcon v-if="fieldConfig.field_definition.data_type === 'image'" class="h-5 w-5" />
                                                    <Upload v-else class="h-5 w-5" />
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-sm font-semibold">
                                                        {{ uploadingFiles[fieldConfig.field_definition.field_key]
                                                            ? 'Preparing…'
                                                            : selectedSectionValues[fieldConfig.field_definition.id]?.value
                                                                ? `Replace ${fieldConfig.field_definition.data_type}`
                                                                : `Choose ${fieldConfig.field_definition.data_type}` }}
                                                    </span>
                                                    <span :class="[ui.muted, 'block text-xs']">
                                                        {{ fieldConfig.field_definition.data_type === 'image' ? 'JPG, PNG, GIF or WebP' : 'PDF, DOC, DOCX, TXT or ZIP' }} · up to 10 MB
                                                    </span>
                                                </span>
                                                <input
                                                    :id="`section-field-${fieldConfig.field_definition.id}`"
                                                    type="file"
                                                    :accept="acceptFor(fieldConfig.field_definition.data_type)"
                                                    :disabled="uploadingFiles[fieldConfig.field_definition.field_key]"
                                                    class="sr-only"
                                                    @change="(event: Event) => uploadSectionAsset(event, fieldConfig.field_definition)"
                                                />
                                            </label>
                                            <p v-if="uploadErrors[fieldConfig.field_definition.field_key]" class="text-sm text-red-600 dark:text-red-400" role="alert">
                                                {{ uploadErrors[fieldConfig.field_definition.field_key] }}
                                            </p>
                                        </div>

                                        <!-- Long text -->
                                        <Textarea
                                            v-else-if="['text', 'rich_text'].includes(fieldConfig.field_definition.data_type)"
                                            :id="`section-field-${fieldConfig.field_definition.id}`"
                                            v-model="selectedSectionValues[fieldConfig.field_definition.id].value"
                                            :rows="4"
                                            :placeholder="placeholderFor(fieldConfig.field_definition)"
                                            :class="[ui.input, 'min-h-[104px] resize-y']"
                                        />

                                        <!-- Short text -->
                                        <Input
                                            v-else
                                            :id="`section-field-${fieldConfig.field_definition.id}`"
                                            v-model="selectedSectionValues[fieldConfig.field_definition.id].value"
                                            :type="fieldConfig.field_definition.data_type === 'email' ? 'email' : 'text'"
                                            :placeholder="placeholderFor(fieldConfig.field_definition)"
                                            :class="[ui.input, 'h-10']"
                                        />
                                    </div>

                                    <!-- Display options (on/off settings, kept apart from content) -->
                                    <div v-if="optionFields.length" class="space-y-1 border-t border-[#EDF1F0] pt-4 dark:border-slate-800">
                                        <h3 class="pb-1 text-[11px] font-semibold uppercase tracking-wide text-[#64748B] dark:text-slate-400">Display options</h3>
                                        <label
                                            v-for="fieldConfig in optionFields"
                                            :id="`insp-field-${fieldConfig.field_definition.id}`"
                                            :key="fieldConfig.field_definition.id"
                                            class="-mx-2 flex cursor-pointer items-start justify-between gap-3 rounded-xl p-2 transition-colors duration-300"
                                            :class="fieldWrapClass(highlightedFieldId === fieldConfig.field_definition.id)"
                                        >
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold">{{ fieldConfig.field_definition.label }}</span>
                                                <span :class="[ui.hint, 'block']">{{ guidanceForField(fieldConfig.field_definition).description }}</span>
                                            </span>
                                            <input
                                                :id="`section-field-${fieldConfig.field_definition.id}`"
                                                v-model="selectedSectionValues[fieldConfig.field_definition.id].value"
                                                type="checkbox"
                                                class="peer sr-only"
                                            />
                                            <span :class="[ui.switchTrack, 'mt-0.5']" aria-hidden="true" />
                                        </label>
                                    </div>
                                </div>

                                <!-- ── One item of a list (project, skill, link…) ── -->
                                <div v-else-if="inspectorMode === 'item' && inlineEditTarget" class="space-y-4 p-4">
                                    <div id="item-fields" class="space-y-4">
                                        <div
                                            v-for="field in selectedArrayItemFields"
                                            :id="`item-field-${field.key}`"
                                            :key="field.key"
                                            class="-mx-2 space-y-1.5 rounded-xl p-2 transition-colors duration-300"
                                            :class="fieldWrapClass(highlightedItemKey === field.key)"
                                        >
                                            <Label v-if="field.type !== 'boolean'" :for="`repeatable-${field.key}`" :class="ui.label">{{ field.label }}</Label>

                                            <Input
                                                v-if="field.type === 'text' || field.type === 'number'"
                                                :id="`repeatable-${field.key}`"
                                                :type="field.type === 'number' ? 'number' : 'text'"
                                                :model-value="selectedArrayText(field.key)"
                                                :placeholder="field.placeholder"
                                                :class="[ui.input, 'h-10']"
                                                @update:model-value="setSelectedArrayProperty(field.key, field.type === 'number' ? Number($event) : $event)"
                                            />

                                            <Textarea
                                                v-else-if="field.type === 'textarea'"
                                                :id="`repeatable-${field.key}`"
                                                :model-value="selectedArrayText(field.key)"
                                                :rows="3"
                                                :placeholder="field.placeholder"
                                                :class="[ui.input, 'min-h-[84px] resize-y']"
                                                @update:model-value="setSelectedArrayProperty(field.key, $event)"
                                            />

                                            <!-- Constrained choices instead of free text -->
                                            <select
                                                v-else-if="field.type === 'select'"
                                                :id="`repeatable-${field.key}`"
                                                :value="selectedArrayText(field.key)"
                                                :class="ui.select"
                                                @change="setSelectedArrayProperty(field.key, ($event.target as HTMLSelectElement).value)"
                                            >
                                                <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>

                                            <!-- Constrained range instead of a number box -->
                                            <div v-else-if="field.type === 'percent'" class="flex items-center gap-3">
                                                <input
                                                    :id="`repeatable-${field.key}`"
                                                    type="range"
                                                    min="0"
                                                    max="100"
                                                    step="5"
                                                    :value="Number(selectedArrayText(field.key)) || 0"
                                                    class="h-10 min-w-0 flex-1 cursor-pointer accent-[#00695C]"
                                                    @input="setSelectedArrayProperty(field.key, Number(($event.target as HTMLInputElement).value))"
                                                />
                                                <span class="w-12 shrink-0 text-right text-sm font-semibold tabular-nums">{{ Number(selectedArrayText(field.key)) || 0 }}%</span>
                                            </div>

                                            <div v-else-if="field.type === 'image'" class="space-y-2">
                                                <img
                                                    v-if="assetPreviewUrl(selectedArrayProperty(field.key))"
                                                    :src="assetPreviewUrl(selectedArrayProperty(field.key))"
                                                    alt="Preview of the selected image"
                                                    class="h-36 w-full rounded-xl border border-[#E1E8E7] object-cover dark:border-slate-700"
                                                />
                                                <label :class="ui.dropzone">
                                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-[#00695C] shadow-sm dark:bg-slate-900 dark:text-teal-300">
                                                        <ImageIcon class="h-5 w-5" />
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block text-sm font-semibold">
                                                            {{ uploadingFiles[itemImageKey(field.key)] ? 'Preparing image…' : selectedArrayProperty(field.key) ? 'Replace image' : 'Add image' }}
                                                        </span>
                                                        <span :class="[ui.muted, 'block text-xs']">JPG, PNG, GIF or WebP · up to 10 MB</span>
                                                    </span>
                                                    <input
                                                        :id="`repeatable-${field.key}`"
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/gif,image/webp"
                                                        :disabled="uploadingFiles[itemImageKey(field.key)]"
                                                        class="sr-only"
                                                        @change="(event: Event) => uploadSelectedArrayImage(event, field.key)"
                                                    />
                                                </label>
                                                <button
                                                    v-if="selectedArrayProperty(field.key)"
                                                    type="button"
                                                    :class="[ui.btn, ui.danger, 'h-9 px-3 text-xs']"
                                                    @click="clearSelectedArrayImage(field.key, field.label)"
                                                >
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                    Remove image
                                                </button>
                                                <p v-if="uploadErrors[itemImageKey(field.key)]" class="text-sm text-red-600 dark:text-red-400" role="alert">
                                                    {{ uploadErrors[itemImageKey(field.key)] }}
                                                </p>
                                            </div>

                                            <label
                                                v-else-if="field.type === 'boolean'"
                                                class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-[#E1E8E7] bg-[#F8FAFA] px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/70"
                                            >
                                                <span class="text-sm font-semibold">{{ field.label }}</span>
                                                <input
                                                    :id="`repeatable-${field.key}`"
                                                    type="checkbox"
                                                    class="peer sr-only"
                                                    :checked="Boolean(selectedArrayProperty(field.key))"
                                                    @change="setSelectedArrayProperty(field.key, ($event.target as HTMLInputElement).checked)"
                                                />
                                                <span :class="ui.switchTrack" aria-hidden="true" />
                                            </label>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        :class="[ui.btn, ui.danger, 'h-9 px-3 text-xs']"
                                        @click="removeSelectedArrayItem"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Remove this {{ itemNoun(inlineEditTarget.fieldKey) }}
                                    </button>
                                </div>

                                <!-- ── Contact form integration ── -->
                                <div v-else-if="inspectorMode === 'contact'" class="space-y-5 p-4">
                                    <div class="flex items-start gap-3 rounded-xl border p-3" :class="contactStatus === 'connected'
                                        ? 'border-[#C7E6D8] bg-[#EFFAF4] dark:border-emerald-900 dark:bg-emerald-950/30'
                                        : contactStatus === 'incomplete'
                                            ? 'border-[#F0DDB8] bg-[#FFF8E9] dark:border-amber-900 dark:bg-amber-950/30'
                                            : 'border-[#E1E8E7] bg-[#F8FAFA] dark:border-slate-800 dark:bg-slate-800/70'">
                                        <Check v-if="contactStatus === 'connected'" class="mt-0.5 h-4 w-4 shrink-0 text-[#16734A] dark:text-emerald-300" />
                                        <AlertCircle v-else-if="contactStatus === 'incomplete'" class="mt-0.5 h-4 w-4 shrink-0 text-[#9A6700] dark:text-amber-300" />
                                        <Settings2 v-else class="mt-0.5 h-4 w-4 shrink-0 text-[#64748B] dark:text-slate-400" />
                                        <p class="text-sm leading-5">
                                            <strong class="font-semibold">
                                                {{ contactStatus === 'connected' ? 'Messages go to your Google Form.' : contactStatus === 'incomplete' ? 'Almost there — finish the details below.' : 'Receive messages from your contact form.' }}
                                            </strong>
                                            <span v-if="contactStatus === 'none'" :class="[ui.muted, 'block']">Connect a Google Form and visitors’ messages will arrive in its responses.</span>
                                        </p>
                                    </div>

                                    <details class="group rounded-xl border border-[#E1E8E7] px-3 py-2 text-sm dark:border-slate-800">
                                        <summary class="flex min-h-8 cursor-pointer list-none items-center justify-between font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50">
                                            How do I get this link?
                                            <ChevronDown class="h-4 w-4 transition-transform group-open:rotate-180" />
                                        </summary>
                                        <ol :class="[ui.muted, 'mt-2 list-decimal space-y-1.5 pb-1 pl-5 leading-5']">
                                            <li>Create a Google Form with three short-answer questions: name, email and message.</li>
                                            <li>Open the form’s <strong class="font-semibold">⋮ menu → Get pre-filled link</strong>.</li>
                                            <li>Type <em>name</em>, <em>email</em> and <em>message</em> as the sample answers, then choose <strong class="font-semibold">Get link</strong>.</li>
                                            <li>Paste the link below. We’ll find the question IDs and match them for you.</li>
                                        </ol>
                                    </details>

                                    <div class="space-y-2">
                                        <Label for="canvas_google_form_url" :class="ui.label">Pre-filled Google Form link</Label>
                                        <Input
                                            id="canvas_google_form_url"
                                            v-model="form.google_form_url"
                                            type="url"
                                            placeholder="https://docs.google.com/forms/d/e/…/viewform?usp=pp_url"
                                            :class="[ui.input, 'h-10']"
                                        />
                                        <p v-if="form.errors.google_form_url" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.google_form_url }}</p>
                                        <p v-else-if="detectedFormEntries.length" class="flex items-center gap-1.5 text-xs font-medium text-[#16734A] dark:text-emerald-300">
                                            <Check class="h-3.5 w-3.5" />
                                            Found {{ detectedFormEntries.length }} {{ detectedFormEntries.length === 1 ? 'question' : 'questions' }} in this link.
                                        </p>
                                        <p v-else-if="form.google_form_url" class="text-xs text-[#9A6700] dark:text-amber-300">
                                            No questions found. Make sure this is the pre-filled link, not the plain form address.
                                        </p>
                                    </div>

                                    <div v-for="entry in contactEntryFields" :key="entry.model" class="space-y-2">
                                        <Label :for="`contact-${entry.model}`" :class="ui.label">{{ entry.label }} ID</Label>
                                        <Input
                                            :id="`contact-${entry.model}`"
                                            v-model="form[entry.model]"
                                            inputmode="numeric"
                                            :placeholder="entry.placeholder"
                                            :class="[ui.input, 'h-10']"
                                        />
                                        <div v-if="detectedFormEntries.length" class="flex flex-wrap gap-1.5">
                                            <button
                                                v-for="detected in detectedFormEntries"
                                                :key="detected.id"
                                                type="button"
                                                class="inline-flex min-h-8 max-w-full items-center rounded-full border px-2.5 text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50"
                                                :class="form[entry.model] === detected.id
                                                    ? 'border-[#00695C] bg-[#E8F4F1] text-[#00695C] dark:bg-teal-900/50 dark:text-teal-300'
                                                    : 'border-[#DCE6E4] text-[#475569] hover:bg-[#F4F7F7] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'"
                                                :aria-pressed="form[entry.model] === detected.id"
                                                :title="`entry.${detected.id}`"
                                                @click="form[entry.model] = detected.id"
                                            >
                                                <span class="truncate">{{ detected.sample ? `“${detected.sample}” · ` : '' }}{{ shortEntryId(detected.id) }}</span>
                                            </button>
                                        </div>
                                        <p v-if="form.errors[entry.model]" class="text-xs text-red-600 dark:text-red-400">{{ form.errors[entry.model] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </main>

        <!-- Undo / confirmation toast (always-mounted live region so it is announced) -->
        <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex justify-center px-3" role="status" aria-live="polite">
            <Transition name="toast">
                <div
                    v-if="toast"
                    :key="toast.id"
                    class="pointer-events-auto flex max-w-full items-center gap-1 rounded-xl bg-[#0F172A] py-1.5 pl-4 pr-1.5 text-sm text-white shadow-lg dark:bg-slate-100 dark:text-slate-900"
                >
                    <span class="min-w-0 truncate py-1.5 pr-3">{{ toast.message }}</span>
                    <button
                        v-if="toast.undo"
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-3 font-semibold text-[#7DD3C7] hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#7DD3C7] dark:text-[#00695C] dark:hover:bg-black/5"
                        @click="undoToast"
                    >
                        <Undo2 class="h-4 w-4" />
                        Undo
                    </button>
                    <button
                        type="button"
                        class="grid h-9 w-9 shrink-0 place-items-center rounded-lg hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white dark:hover:bg-black/5"
                        aria-label="Dismiss"
                        @click="finalizeToast"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </Transition>
        </div>

        <!-- Publish checklist -->
        <Dialog v-model:open="isChecklistOpen">
            <DialogContent class="max-h-[90dvh] w-[calc(100%-1rem)] max-w-md overflow-y-auto rounded-2xl border-[#E1E8E7] bg-white p-0 dark:border-slate-800 dark:bg-slate-900 sm:w-[calc(100%-2rem)]">
                <DialogHeader class="border-b border-[#EDF1F0] px-5 py-5 text-left dark:border-slate-800">
                    <DialogTitle class="text-lg font-bold">Almost ready to publish</DialogTitle>
                    <DialogDescription :class="[ui.hint, 'mt-1 text-sm leading-5']">
                        Add {{ requiredIssues.length === 1 ? 'this detail' : 'these details' }} first. Choose one to jump straight to it.
                    </DialogDescription>
                </DialogHeader>
                <ul class="p-2">
                    <li v-for="issue in requiredIssues" :key="`${issue.sectionKey}-${issue.field.id}`">
                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-between gap-3 rounded-xl px-3 text-left hover:bg-[#F4F7F7] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00695C]/50 dark:hover:bg-slate-800"
                            @click="jumpToIssue(issue)"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold">{{ issue.field.label }}</span>
                                <span :class="[ui.muted, 'block text-xs']">{{ sectionLabel(issue.sectionKey) }}</span>
                            </span>
                            <ChevronRight class="h-4 w-4 shrink-0 text-[#94A3B8]" />
                        </button>
                    </li>
                </ul>
                <DialogFooter class="border-t border-[#EDF1F0] px-5 py-4 dark:border-slate-800">
                    <button type="button" :class="[ui.btn, ui.secondary, 'h-10 px-4']" @click="isChecklistOpen = false">Close</button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Leaving with unsaved changes -->
        <Dialog v-model:open="isLeaveOpen">
            <DialogContent class="max-h-[90dvh] w-[calc(100%-1rem)] max-w-md overflow-y-auto rounded-2xl border-[#E1E8E7] bg-white p-0 dark:border-slate-800 dark:bg-slate-900 sm:w-[calc(100%-2rem)]">
                <DialogHeader class="px-5 py-5 text-left">
                    <DialogTitle class="text-lg font-bold">You have unsaved changes</DialogTitle>
                    <DialogDescription :class="[ui.hint, 'mt-1 text-sm leading-5']">
                        Your edits to “{{ portfolio.title }}” haven’t been saved yet.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 border-t border-[#EDF1F0] px-5 py-4 dark:border-slate-800 sm:justify-between">
                    <button type="button" :class="[ui.btn, ui.danger, 'h-10 px-3']" @click="leaveWithoutSaving">Leave without saving</button>
                    <span class="flex flex-col-reverse gap-2 sm:flex-row">
                        <button type="button" :class="[ui.btn, ui.secondary, 'h-10 px-4']" @click="isLeaveOpen = false">Keep editing</button>
                        <button
                            type="button"
                            :disabled="form.processing || hasPendingUploads"
                            :class="[ui.btn, ui.primary, 'h-10 px-4']"
                            @click="saveAndLeave"
                        >
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            Save &amp; leave
                        </button>
                    </span>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Template switch (admin) -->
        <Dialog v-model:open="isSwitchModalOpen">
            <DialogContent class="max-h-[90dvh] w-[calc(100%-1rem)] max-w-lg overflow-y-auto rounded-2xl border-[#E1E8E7] bg-white p-0 dark:border-slate-800 dark:bg-slate-900 sm:w-[calc(100%-2rem)]">
                <DialogHeader class="border-b border-[#EDF1F0] px-5 py-5 text-left dark:border-slate-800 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F4F1] text-[#00695C] dark:bg-teal-900/50 dark:text-teal-300">
                            <LayoutTemplate class="h-5 w-5" />
                        </span>
                        <div>
                            <DialogTitle class="text-lg font-bold">Change template</DialogTitle>
                            <DialogDescription :class="[ui.hint, 'mt-1 text-sm leading-5']">
                                Choose a different design. Compatible content is kept where possible.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div class="space-y-3 p-5 sm:p-6">
                    <div>
                        <Label for="template_select" :class="[ui.label, 'mb-2 block']">Available templates</Label>
                        <select id="template_select" v-model.number="selectedTemplateId" :class="[ui.select, 'h-11 rounded-xl']">
                            <option v-for="template in availableTemplates" :key="template.id" :value="template.id">
                                {{ template.name }}<template v-if="template.category"> — {{ template.category.name }}</template>
                            </option>
                        </select>
                        <p :class="[ui.hint, 'mt-2']">Current template: {{ portfolio.template.name }}.</p>
                    </div>
                    <p
                        v-if="form.isDirty"
                        class="flex items-start gap-2 rounded-xl border border-[#F0DDB8] bg-[#FFF8E9] p-3 text-xs leading-5 text-[#7A5200] dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        <AlertCircle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        You have unsaved changes. They’ll be saved before the template switches.
                    </p>
                </div>

                <DialogFooter class="gap-2 border-t border-[#EDF1F0] bg-[#FBFCFC] px-5 py-4 dark:border-slate-800 dark:bg-slate-900 sm:px-6">
                    <button type="button" :class="[ui.btn, ui.secondary, 'h-10 px-4']" @click="isSwitchModalOpen = false">Cancel</button>
                    <button
                        type="button"
                        :disabled="switchForm.processing || form.processing || selectedTemplateId === portfolio.template.id"
                        :class="[ui.btn, ui.primary, 'h-10 px-4']"
                        @click="switchTemplate"
                    >
                        <LoaderCircle v-if="switchForm.processing || form.processing" class="h-4 w-4 animate-spin" />
                        {{ form.isDirty ? 'Save & switch template' : 'Switch template' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
/* Motion is short, purposeful, and removed entirely for people who ask for less of it. */
.toast-enter-active,
.toast-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

@keyframes inspector-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
.inspector-view {
    animation: inspector-in 140ms ease-out;
}

@keyframes sheet-in {
    from {
        opacity: 0;
        transform: translateY(24px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
@media (max-width: 1279px) {
    .inspector-sheet {
        animation: sheet-in 200ms cubic-bezier(0.2, 0.8, 0.2, 1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .toast-enter-active,
    .toast-leave-active {
        transition: none;
    }
    .inspector-view,
    .inspector-sheet {
        animation: none;
    }
}
</style>
