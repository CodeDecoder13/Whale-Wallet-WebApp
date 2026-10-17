<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { computed } from 'vue';
import { index } from '@/routes/chat';

// the chat page is the chat, so the shortcut hides there
const show = computed(() => usePage().component !== 'chat/Index');
</script>

<template>
    <Link
        v-if="show"
        :href="index()"
        class="fixed right-4 bottom-4 z-40 flex items-center gap-2 rounded-full bg-navy px-4 py-3 text-sm font-medium text-white shadow-lg ring-1 ring-white/25 transition-colors hover:bg-navy-2 md:right-6 md:bottom-6"
    >
        <MessageCircle class="size-4" />
        Whale Chat
    </Link>
</template>
