<script setup lang="ts">
interface Props {
    class?: string;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const iconSizes = {
    sm: 'size-7',
    md: 'size-9',
    lg: 'size-12',
};

const textSizes = {
    sm: 'text-base',
    md: 'text-xl',
    lg: 'text-3xl',
};
</script>

<template>
    <div :class="['inline-flex shrink-0 items-center gap-2.5', props.class]">
        <img src="/logo.svg" alt="" :class="[iconSizes[props.size], 'object-contain']" />
        <span :class="['font-bold tracking-tight text-[#0F172A]', textSizes[props.size]]">
            quick<span class="text-[#00695C]">Folio</span>
        </span>
    </div>
</template>
