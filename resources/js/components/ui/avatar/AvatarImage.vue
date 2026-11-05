<script setup lang="ts">
import { AvatarImage, type AvatarImageProps } from 'radix-vue';

const props = defineProps<AvatarImageProps>();
</script>

<template>
    <AvatarImage v-bind="props" loading="lazy" decoding="async" class="h-full w-full object-cover" />
</template>
