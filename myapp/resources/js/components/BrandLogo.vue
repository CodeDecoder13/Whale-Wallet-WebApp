<script setup lang="ts">
import { House } from '@lucide/vue';
import { computed } from 'vue';
import { findBrand } from '@/lib/brandLogos';

// a logo the user uploaded wins over the built-in match
const props = defineProps<{ name: string; logo?: string | null }>();
const brand = computed(() => findBrand(props.name));
const src = computed(() => props.logo || brand.value?.image);
</script>

<template>
    <span
        class="flex size-9 shrink-0 items-center justify-center rounded-lg"
        :class="
            src || brand?.icon
                ? 'bg-white ring-1 ring-black/10'
                : 'bg-accent font-semibold text-accent-foreground'
        "
    >
        <img
            v-if="src"
            :src="src"
            :alt="name"
            class="size-full object-contain p-0.5"
        />
        <House v-else-if="brand?.home" class="size-5" />
        <svg
            v-else-if="brand?.icon"
            viewBox="0 0 24 24"
            class="size-5"
            role="img"
            :aria-label="name"
        >
            <path :d="brand.icon.path" :fill="`#${brand.icon.hex}`" />
        </svg>
        <template v-else>{{ name.charAt(0).toUpperCase() }}</template>
    </span>
</template>
