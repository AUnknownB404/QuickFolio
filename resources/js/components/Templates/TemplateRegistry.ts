import { defineAsyncComponent, type Component } from 'vue';

const templateRegistry: Record<string, Component> = {
    'emerald-minimalist': defineAsyncComponent(() => import('./EmeraldMinimalist.vue')),
    'dark-orange-designer': defineAsyncComponent(() => import('./DarkOrangeDesigner.vue')),
    'ivory-editorial': defineAsyncComponent(() => import('./IvoryEditorial.vue')),
    'bento-grid': defineAsyncComponent(() => import('./BentoGrid.vue')),
    'cobalt-brutalist': defineAsyncComponent(() => import('./CobaltBrutalist.vue')),
};

export function getTemplateComponent(slug: string): Component {
    return templateRegistry[slug] || templateRegistry['emerald-minimalist'];
}
