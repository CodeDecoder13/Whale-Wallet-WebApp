<script setup lang="ts">
import { FileText } from '@lucide/vue';

// an attachment inside a chat bubble: pictures show as a thumbnail, other files (PDF) as a named chip; both open in a new tab
defineProps<{ file: { name: string; size: number; image: boolean }; url: string }>();

const size = (n: number) => (n > 1_048_576 ? `${(n / 1_048_576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);
</script>

<template>
    <a :href="url" target="_blank" rel="noopener" class="mt-1 block max-w-full">
        <img v-if="file.image" :src="url" :alt="file.name" loading="lazy" class="max-h-48 rounded-lg object-cover" />
        <span v-else class="flex items-center gap-2 rounded-lg bg-black/10 px-3 py-2 text-xs dark:bg-white/10">
            <FileText class="size-4 shrink-0" />
            <span class="truncate">{{ file.name }}</span>
            <span class="shrink-0 opacity-70">{{ size(file.size) }}</span>
        </span>
    </a>
</template>
