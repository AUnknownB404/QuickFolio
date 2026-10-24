import { defineAsyncComponent, type Component } from 'vue';

const templateRegistry: Record<string, Component> = {
    'dark-orange-designer': defineAsyncComponent(() => import('./DarkOrangeDesigner.vue')),
    'ivory-editorial': defineAsyncComponent(() => import('./IvoryEditorial.vue')),
    'bento-grid': defineAsyncComponent(() => import('./BentoGrid.vue')),
    'cobalt-brutalist': defineAsyncComponent(() => import('./CobaltBrutalist.vue')),
    'command-deck': defineAsyncComponent(() => import('./CommandDeck.vue')),
    'emerald-minimalist': defineAsyncComponent(() => import('./CommandDeck.vue')),
};

export function getTemplateComponent(slug: string): Component {
    return templateRegistry[slug] ?? templateRegistry['command-deck'];
}
