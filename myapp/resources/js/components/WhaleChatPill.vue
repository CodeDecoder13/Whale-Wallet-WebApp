<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageCircle, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import ChatPanel from '@/components/ChatPanel.vue';
import type { ChatProps } from '@/components/ChatPanel.vue';
import { api } from '@/lib/api';
import { index, recent } from '@/routes/chat';

// the chat page is the chat, so the shortcut hides there
const show = computed(() => usePage().component !== 'chat/Index');
const open = ref(false);
const history = ref<ChatProps | null>(null);

async function toggle() {
    open.value = !open.value;

    // ponytail: loaded on every open so it matches the chat page; cache if it feels slow
    if (open.value) {
        history.value = null;
        history.value = await api<ChatProps>(recent.url());
    }
}
</script>

<template>
    <template v-if="show">
        <section
            v-if="open"
            class="fixed right-4 bottom-20 z-40 flex max-h-[min(34rem,calc(100svh-7rem))] w-[calc(100vw-2rem)] flex-col gap-2 overflow-y-auto rounded-2xl border bg-background p-3 shadow-xl sm:w-96 md:right-6 md:bottom-24"
            aria-label="Whale Chat"
        >
            <div class="flex items-center justify-between text-sm font-medium">
                <Link :href="index()" class="hover:underline">
                    Whale Chat
                </Link>
                <button type="button" aria-label="Close chat" @click="open = false">
                    <X class="size-4" />
                </button>
            </div>
            <ChatPanel
                v-if="history"
                :consented="history.consented"
                :messages="history.messages"
                log-class="h-80"
            />
        </section>
        <button
            type="button"
            class="fixed right-4 bottom-4 z-40 flex items-center gap-2 rounded-full bg-navy px-4 py-3 text-sm font-medium text-white shadow-lg ring-1 ring-white/25 transition-colors hover:bg-navy-2 md:right-6 md:bottom-6"
            @click="toggle"
        >
            <MessageCircle class="size-4" />
            Whale Chat
        </button>
    </template>
</template>
