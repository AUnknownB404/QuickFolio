import { defineAsyncComponent, type Component } from 'vue';

const templateRegistry: Record<string, Component> = {
    'dark-orange-designer': defineAsyncComponent(() => import('./DarkOrangeDesigner.vue')),
    'ivory-editorial': defineAsyncComponent(() => import('./IvoryEditorial.vue')),
    'bento-grid': defineAsyncComponent(() => import('./BentoGrid.vue')),
    'cobalt-brutalist': defineAsyncComponent(() => import('./CobaltBrutalist.vue')),
    'command-deck': defineAsyncComponent(() => import('./CommandDeck.vue')),
    'emerald-minimalist': defineAsyncComponent(() => import('./EmeraldMinimalist.vue')),
    'nebula-glass': defineAsyncComponent(() => import('./NebulaGlass.vue')),
    'newsprint': defineAsyncComponent(() => import('./Newsprint.vue')),
    'orbit-folio': defineAsyncComponent(() => import('./OrbitFolio.vue')),
    'paper-stack': defineAsyncComponent(() => import('./PaperStack.vue')),
    'stream-bands': defineAsyncComponent(() => import('./StreamBands.vue')),
    'swiss-grid': defineAsyncComponent(() => import('./SwissGrid.vue')),
    'swiss-signal': defineAsyncComponent(() => import('./SwissSignal.vue')),
    'tidepool': defineAsyncComponent(() => import('./Tidepool.vue')),
};

export function getTemplateComponent(slug: string): Component {
    return templateRegistry[slug] ?? templateRegistry['command-deck'];
}
