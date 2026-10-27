<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

// Laravel's paginator links ("&laquo; Previous", 1, 2, ... "Next &raquo;"); the labels come from the server, so v-html is safe here
defineProps<{ links: { url: string | null; label: string; active: boolean }[] }>();
</script>

<template>
    <nav v-if="links.length > 3" class="mt-4 flex flex-wrap gap-1" aria-label="Pages">
        <template v-for="(l, i) in links" :key="i">
            <Link v-if="l.url" :href="l.url" preserve-scroll class="rounded-lg border px-3 py-1.5 text-sm" :class="l.active ? 'bg-primary text-primary-foreground' : 'bg-card hover:bg-accent'" v-html="l.label" />
            <span v-else class="rounded-lg border px-3 py-1.5 text-sm text-muted-foreground" v-html="l.label" />
        </template>
    </nav>
</template>
