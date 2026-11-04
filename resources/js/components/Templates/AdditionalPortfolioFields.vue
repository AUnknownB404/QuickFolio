<script setup lang="ts">
import { computed, ref } from 'vue';

type FieldRecord = Record<string, unknown>;

const props = defineProps<{
    content: Record<string, FieldRecord>;
}>();

const section = (key: string): FieldRecord => {
    const value = props.content[key];
    return typeof value === 'object' && value !== null && !Array.isArray(value) ? (value as FieldRecord) : {};
};

const text = (fields: FieldRecord, key: string): string => {
    const value = fields[key];
    return typeof value === 'string' || typeof value === 'number' ? String(value).trim() : '';
};

const enabled = (fields: FieldRecord, key: string): boolean => {
    const value = fields[key];
    return value === true || value === 1 || value === '1' || value === 'true';
};

const entries = (value: unknown): unknown[] => {
    if (Array.isArray(value)) return value;
    if (typeof value === 'string')
        return value
            .split(/\r?\n/)
            .map((entry) => entry.trim())
            .filter(Boolean);
    return [];
};

const entryText = (value: unknown): string => {
    if (typeof value === 'string' || typeof value === 'number') return String(value).trim();
    if (typeof value !== 'object' || value === null || Array.isArray(value)) return '';
    const item = value as FieldRecord;
    const label = item.label ?? item.name ?? item.title ?? item.value;
    return typeof label === 'string' || typeof label === 'number' ? String(label).trim() : '';
};

const safeUrl = (value: string): string | null => {
    const url = value.trim();
    if (url.startsWith('#') || url.startsWith('/')) return url;

    try {
        const parsed = new URL(url);
        return ['http:', 'https:', 'mailto:', 'tel:'].includes(parsed.protocol) ? url : null;
    } catch {
        return null;
    }
};

const header = computed(() => section('header'));
const about = computed(() => section('about'));
const projects = computed(() => section('projects'));
const contact = computed(() => section('contact'));
const services = computed(() => section('services'));

const heroDetails = computed(() =>
    [
        { label: 'Location', value: text(header.value, 'hero_location') },
        { label: 'Availability', value: text(header.value, 'hero_availability') },
        { label: 'Experience', value: text(header.value, 'hero_years_experience') },
    ].filter((item) => item.value),
);

const specialties = computed(() => entries(about.value.about_specialties).map(entryText).filter(Boolean));
const equipment = computed(() => entries(about.value.about_equipment).map(entryText).filter(Boolean));
const clients = computed(() => entries(about.value.about_clients).map(entryText).filter(Boolean));
const aboutBookingUrl = computed(() => safeUrl(text(about.value, 'about_booking_url')));

const projectItems = computed(() =>
    entries(projects.value.projects_list)
        .map((value) => {
            if (typeof value !== 'object' || value === null || Array.isArray(value)) return null;
            const project = value as FieldRecord;
            const rawTags = Array.isArray(project.tags) ? project.tags : [];
            const category = typeof project.category === 'string' ? project.category.trim() : '';
            const tags = rawTags.map(entryText).filter(Boolean);
            if (category && !tags.includes(category)) tags.unshift(category);
            return {
                title: entryText(project.title ?? project.name),
                description: typeof project.description === 'string' ? project.description.trim() : '',
                category,
                tags,
                url: safeUrl(text(project, 'live_url') || text(project, 'url')),
            };
        })
        .filter((project): project is NonNullable<typeof project> => project !== null && Boolean(project.title)),
);

const projectCategories = computed(() => [
    ...new Set(projectItems.value.map((project) => project.category).filter((category) => category.length > 0)),
]);
const activeCategory = ref('All');
const showProjectCategories = computed(() => enabled(projects.value, 'projects_show_categories'));
const filterProjects = computed(() => enabled(projects.value, 'projects_filter_enabled'));
const projectLayout = computed(() => {
    const layout = text(projects.value, 'projects_gallery_layout').toLowerCase();
    return layout.includes('masonry') ? 'masonry' : layout.includes('list') ? 'list' : 'grid';
});
const filteredProjects = computed(() =>
    activeCategory.value === 'All' ? projectItems.value : projectItems.value.filter((project) => project.category === activeCategory.value),
);
const showProjectExtras = computed(
    () => showProjectCategories.value || filterProjects.value || Boolean(text(projects.value, 'projects_gallery_layout')),
);

const projectReelUrl = computed(() => safeUrl(text(projects.value, 'projects_reel_url')));
const projectBookingUrl = computed(() => safeUrl(text(projects.value, 'projects_booking_url')));
const contactBookingUrl = computed(() => safeUrl(text(contact.value, 'contact_booking_url')));
const servicesBookingUrl = computed(() => safeUrl(text(services.value, 'services_booking_url')));

const whatsapp = computed(() => {
    const value = text(contact.value, 'contact_whatsapp');
    if (!value) return null;

    const directUrl = safeUrl(value);
    if (directUrl && /^https?:\/\//i.test(directUrl)) return { label: value, url: directUrl };

    const phone = value.replace(/\D/g, '');
    return phone ? { label: value, url: `https://wa.me/${phone}` } : null;
});

const contactDetails = computed(() =>
    [
        { label: 'Studio', value: text(contact.value, 'contact_business_name') },
        { label: 'Business hours', value: text(contact.value, 'contact_business_hours') },
        { label: 'Response time', value: text(contact.value, 'contact_response_time') },
    ].filter((item) => item.value),
);

const serviceItems = computed(() =>
    entries(services.value.services_list)
        .map((value) => {
            if (typeof value !== 'object' || value === null || Array.isArray(value)) return null;
            const service = value as FieldRecord;
            const price = service.price ?? service.pricing ?? service.cost;
            return {
                title: entryText(service.title ?? service.name ?? service.value),
                price: typeof price === 'string' || typeof price === 'number' ? String(price).trim() : '',
            };
        })
        .filter((service): service is NonNullable<typeof service> => service !== null && Boolean(service.title)),
);

const showPricing = computed(() => enabled(services.value, 'services_show_pricing'));

const hasHeroExtras = computed(() =>
    Boolean(text(header.value, 'hero_tagline') || text(header.value, 'hero_background_video_url') || heroDetails.value.length),
);
const hasAboutExtras = computed(() =>
    Boolean(
        text(about.value, 'about_philosophy') || specialties.value.length || equipment.value.length || clients.value.length || aboutBookingUrl.value,
    ),
);
const hasProjectExtras = computed(() => Boolean(showProjectExtras.value || projectReelUrl.value || projectBookingUrl.value));
const hasContactExtras = computed(() => Boolean(contactDetails.value.length || whatsapp.value || contactBookingUrl.value));
const hasServiceExtras = computed(() =>
    Boolean((showPricing.value && serviceItems.value.some((service) => service.price)) || servicesBookingUrl.value),
);
const hasExtras = computed(
    () => hasHeroExtras.value || hasAboutExtras.value || hasProjectExtras.value || hasContactExtras.value || hasServiceExtras.value,
);
</script>

<template>
    <aside v-if="hasExtras" class="mx-auto grid w-full max-w-7xl gap-5 px-5 pb-12 text-current sm:px-8 lg:grid-cols-2">
        <section v-if="hasHeroExtras" class="border-current/10 bg-current/[0.03] rounded-2xl border p-6">
            <h2 class="text-lg font-semibold">More about me</h2>
            <p v-if="text(header, 'hero_tagline')" class="mt-3 leading-7 opacity-80">{{ text(header, 'hero_tagline') }}</p>
            <dl v-if="heroDetails.length" class="mt-4 grid gap-3 sm:grid-cols-3">
                <div v-for="detail in heroDetails" :key="detail.label">
                    <dt class="text-xs font-semibold uppercase tracking-wide opacity-60">{{ detail.label }}</dt>
                    <dd class="mt-1 text-sm">{{ detail.value }}</dd>
                </div>
            </dl>
            <a
                v-if="safeUrl(text(header, 'hero_background_video_url'))"
                :href="safeUrl(text(header, 'hero_background_video_url'))!"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-flex min-h-11 items-center font-semibold underline underline-offset-4"
            >
                View background video
            </a>
        </section>

        <section v-if="hasAboutExtras" class="border-current/10 bg-current/[0.03] rounded-2xl border p-6">
            <h2 class="text-lg font-semibold">Creative approach</h2>
            <p v-if="text(about, 'about_philosophy')" class="mt-3 whitespace-pre-line leading-7 opacity-80">
                {{ text(about, 'about_philosophy') }}
            </p>
            <div
                v-for="list in [
                    { label: 'Specialties', values: specialties },
                    { label: 'Equipment', values: equipment },
                    { label: 'Selected clients', values: clients },
                ]"
                :key="list.label"
            >
                <div v-if="list.values.length" class="mt-4">
                    <h3 class="text-sm font-semibold">{{ list.label }}</h3>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        <li v-for="item in list.values" :key="item" class="border-current/10 rounded-full border px-3 py-1 text-sm opacity-80">
                            {{ item }}
                        </li>
                    </ul>
                </div>
            </div>
            <a
                v-if="aboutBookingUrl"
                :href="aboutBookingUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-5 inline-flex min-h-11 items-center font-semibold underline underline-offset-4"
            >
                Book a session
            </a>
        </section>

        <section v-if="hasProjectExtras" class="border-current/10 bg-current/[0.03] rounded-2xl border p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">More selected work</h2>
                    <p v-if="text(projects, 'projects_gallery_layout')" class="mt-1 text-sm opacity-70">
                        {{ text(projects, 'projects_gallery_layout') }} gallery
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="projectReelUrl"
                        :href="projectReelUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="border-current/15 inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-semibold"
                        >Showreel</a
                    >
                    <a
                        v-if="projectBookingUrl"
                        :href="projectBookingUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="border-current/15 inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-semibold"
                        >Inquire about a project</a
                    >
                </div>
            </div>

            <div v-if="filterProjects && projectCategories.length" class="mt-4 flex flex-wrap gap-2" aria-label="Filter projects by category">
                <button
                    v-for="category in ['All', ...projectCategories]"
                    :key="category"
                    type="button"
                    class="border-current/15 min-h-10 rounded-full border px-3 text-sm transition"
                    :class="activeCategory === category ? 'bg-current text-background' : 'opacity-75 hover:opacity-100'"
                    :aria-pressed="activeCategory === category"
                    @click="activeCategory = category"
                >
                    {{ category }}
                </button>
            </div>

            <div
                v-if="projectItems.length"
                class="mt-5"
                :class="
                    projectLayout === 'masonry'
                        ? 'columns-1 gap-4 sm:columns-2'
                        : projectLayout === 'list'
                          ? 'grid grid-cols-1 gap-3'
                          : 'grid grid-cols-1 gap-4 sm:grid-cols-2'
                "
            >
                <article
                    v-for="project in filteredProjects"
                    :key="project.title"
                    class="border-current/10 mb-4 break-inside-avoid rounded-xl border p-4"
                >
                    <p v-if="showProjectCategories && project.category" class="text-xs font-semibold uppercase tracking-wide opacity-60">
                        {{ project.category }}
                    </p>
                    <h3 class="mt-1 font-semibold">{{ project.title }}</h3>
                    <p v-if="project.description" class="mt-2 text-sm leading-6 opacity-75">{{ project.description }}</p>
                    <a
                        v-if="project.url"
                        :href="project.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold underline underline-offset-4"
                    >
                        View project
                    </a>
                </article>
            </div>
        </section>

        <section v-if="hasContactExtras" class="border-current/10 bg-current/[0.03] rounded-2xl border p-6">
            <h2 class="text-lg font-semibold">Contact details</h2>
            <dl v-if="contactDetails.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                <div v-for="detail in contactDetails" :key="detail.label">
                    <dt class="text-xs font-semibold uppercase tracking-wide opacity-60">{{ detail.label }}</dt>
                    <dd class="mt-1 text-sm">{{ detail.value }}</dd>
                </div>
            </dl>
            <div class="mt-4 flex flex-wrap gap-4">
                <a
                    v-if="whatsapp"
                    :href="whatsapp.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center font-semibold underline underline-offset-4"
                >
                    WhatsApp: {{ whatsapp.label }}
                </a>
                <a
                    v-if="contactBookingUrl"
                    :href="contactBookingUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center font-semibold underline underline-offset-4"
                >
                    Book a consultation
                </a>
            </div>
        </section>

        <section v-if="hasServiceExtras" class="border-current/10 bg-current/[0.03] rounded-2xl border p-6">
            <h2 class="text-lg font-semibold">Services and pricing</h2>
            <ul v-if="showPricing && serviceItems.length" class="divide-current/10 mt-4 divide-y">
                <li
                    v-for="service in serviceItems"
                    :key="service.title"
                    class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <span class="font-medium">{{ service.title }}</span>
                    <span v-if="service.price" class="text-sm font-semibold">{{ service.price }}</span>
                </li>
            </ul>
            <a
                v-if="servicesBookingUrl"
                :href="servicesBookingUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-flex min-h-11 items-center font-semibold underline underline-offset-4"
            >
                Book a service
            </a>
        </section>
    </aside>
</template>
