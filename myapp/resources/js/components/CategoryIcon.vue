<script setup lang="ts">
import { computed } from 'vue';
import { categoryLook } from '@/lib/categoryLook';

// a colourful cartoon-style tile for a category; the merchant is a fallback hint when the category is blank or "Other"
const props = defineProps<{ category?: string | null; merchant?: string | null }>();
const look = computed(() => categoryLook(props.category, props.merchant));
</script>

<template>
    <span
        class="flex size-9 shrink-0 items-center justify-center rounded-xl leading-none"
        :class="[look.tile, look.emoji.length > 3 ? 'text-xs' : 'text-lg']"
        aria-hidden="true"
    >
        {{ look.emoji }}
    </span>
</template>
