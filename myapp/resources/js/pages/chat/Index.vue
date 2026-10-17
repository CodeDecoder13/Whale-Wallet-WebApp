<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ChatPanel from '@/components/ChatPanel.vue';
import type { ChatProps } from '@/components/ChatPanel.vue';
import Heading from '@/components/Heading.vue';
import { index } from '@/routes/chat';

defineProps<ChatProps>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Whale Chat', href: index() }] },
});
</script>

<template>
    <Head title="Whale Chat" />

    <div class="flex flex-col gap-4 p-4 md:p-8">
        <Heading
            class="mb-0!"
            title="Whale Chat"
            description="Log an expense or ask about your spending. Nothing is saved until you tap Save."
        />

        <ChatPanel class="max-w-3xl" :consented="consented" :messages="messages" />

        <p class="text-xs text-muted-foreground">
            Budgeting guidance, not financial advice. Numbers in answers come
            from your data.
        </p>
    </div>
</template>
