<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ChatPanel from '@/components/ChatPanel.vue';
import type { ChatProps } from '@/components/ChatPanel.vue';
import { index } from '@/routes/chat';

defineProps<ChatProps>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Whale Chat', href: index() }] },
});
</script>

<template>
    <Head title="Whale Chat" />

    <h1 class="sr-only">Whale Chat</h1>

    <!-- the chat is the page: it fills everything under the top bar -->
    <div class="flex h-[calc(100svh-5rem)] flex-col gap-2 p-3 md:p-5">
        <ChatPanel
            class="min-h-0 flex-1"
            :consented="consented"
            :messages="messages"
        />

        <p class="text-center text-xs text-muted-foreground">
            Log an expense or ask about your spending. Nothing is saved until
            you tap Save. Budgeting guidance, not financial advice.
        </p>
    </div>
</template>
