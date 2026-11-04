<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    active: boolean;
}>();
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
        leave-to-class="opacity-0"
    >
        <div
            v-if="active"
            class="fixed inset-0 z-[9999] flex cursor-wait items-center justify-center bg-background/70 backdrop-blur-sm"
            role="status"
            aria-live="polite"
            aria-label="Loading"
        >
            <LoaderCircle class="h-10 w-10 animate-spin text-primary" aria-hidden="true" />
            <span class="sr-only">Loading, please wait...</span>
        </div>
    </Transition>
</template>
