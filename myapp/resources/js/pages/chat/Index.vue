<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { ref } from 'vue';
import ChatHistory from '@/components/ChatHistory.vue';
import type { Thread } from '@/components/ChatHistory.vue';
import ChatPanel from '@/components/ChatPanel.vue';
import type { ChatProps } from '@/components/ChatPanel.vue';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { index } from '@/routes/chat';

defineProps<ChatProps & { threads: Thread[]; thread_id: number | null }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Whale Chat', href: index() }] },
});

const historyOpen = ref(false);

// a sent message names the chat and moves it to the top, so refresh just the history list
const refreshHistory = () => router.reload({ only: ['threads'] });
</script>

<template>
    <Head title="Whale Chat" />

    <h1 class="sr-only">Whale Chat</h1>

    <!-- the chat is the page: it fills everything under the top bar, with history on the right -->
    <div class="flex h-[calc(100svh-5rem)] bg-linear-to-br from-accent/50 via-background to-background">
        <div class="relative flex min-w-0 flex-1 flex-col">
            <Button
                variant="outline"
                size="sm"
                class="absolute top-3 right-3 z-20 lg:hidden"
                @click="historyOpen = true"
            >
                <History /> History
            </Button>

            <!-- keyed by chat, so picking another one starts from its own messages -->
            <ChatPanel
                :key="thread_id ?? 'new'"
                page
                class="min-h-0 flex-1"
                :consented="consented"
                :messages="messages"
                :thread-id="thread_id"
                @sent="refreshHistory"
            />
        </div>

        <aside class="hidden w-72 shrink-0 border-l bg-card/60 backdrop-blur lg:block">
            <ChatHistory :threads="threads" :active-id="thread_id" />
        </aside>

        <Sheet v-model:open="historyOpen">
            <SheetContent class="w-80 p-0">
                <SheetHeader class="sr-only">
                    <SheetTitle>Chat history</SheetTitle>
                    <SheetDescription>Your earlier chats, and a button to start a new one.</SheetDescription>
                </SheetHeader>
                <ChatHistory :threads="threads" :active-id="thread_id" @pick="historyOpen = false" />
            </SheetContent>
        </Sheet>
    </div>
</template>
