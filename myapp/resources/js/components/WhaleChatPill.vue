<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Maximize2, SquarePen, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ChatPanel from '@/components/ChatPanel.vue';
import Mascot from '@/components/Mascot.vue';
import type { ChatProps } from '@/components/ChatPanel.vue';
import { api } from '@/lib/api';
import { index, recent } from '@/routes/chat';
import { store as startChat } from '@/routes/chat/threads';

// the chat page is the chat, so the shortcut hides there
const show = computed(() => usePage().component !== 'chat/Index');
const open = ref(false);
const history = ref<ChatProps | null>(null);

const due = computed(() => (usePage().props.whaleDue as number) ?? 0);
// the ping stops once the chat has been opened today; the count stays while something is due
const today = new Date().toDateString();
const seen = ref(false);
try {
    seen.value = localStorage.getItem('whale-seen') === today;
} catch {
    // storage blocked: keep pinging
}

// Wally's speech bubble: shown for a few seconds on each page load, and again on hover
const bubble = ref(false);
let hide: ReturnType<typeof setTimeout> | undefined;
const peek = () => {
    bubble.value = true;
    clearTimeout(hide);
    hide = setTimeout(() => (bubble.value = false), 9000);
};
onMounted(() => setTimeout(peek, 1200));
onBeforeUnmount(() => clearTimeout(hide));

// a fresh chat for people who do not want to continue the last one
const starting = ref(false);
async function fresh() {
    starting.value = true;

    try {
        await api(startChat.url(), {});
        history.value = null;
        history.value = await api<ChatProps>(recent.url());
    } finally {
        starting.value = false;
    }
}

async function toggle() {
    bubble.value = false;
    open.value = !open.value;
    seen.value = true;

    try {
        localStorage.setItem('whale-seen', today);
    } catch {
        // storage blocked
    }

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
            class="fixed right-4 bottom-24 z-40 flex h-[min(44rem,calc(100svh-9rem))] w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-3xl border bg-linear-to-br from-accent/50 via-background to-background shadow-2xl sm:w-[30rem] md:right-6 md:bottom-28 lg:w-[34rem]"
            aria-label="Chat with Wally"
        >
            <div class="flex items-center justify-between gap-3 border-b bg-background/70 px-4 py-3 backdrop-blur">
                <Link :href="index()" class="flex items-center gap-2.5 hover:underline">
                    <Mascot pose="happy" round class="size-9" />
                    <span class="grid leading-tight">
                        <span class="text-sm font-semibold">Wally</span>
                        <span class="text-[11px] font-normal text-muted-foreground">Your Whale Wallet buddy</span>
                    </span>
                </Link>
                <span class="flex items-center gap-1">
                    <button
                        v-if="history?.messages.length"
                        type="button"
                        class="mr-1 flex items-center gap-1.5 rounded-full border bg-card px-3 py-1.5 text-xs font-medium transition hover:bg-accent active:scale-[0.97] disabled:opacity-50"
                        :disabled="starting"
                        @click="fresh"
                    >
                        <SquarePen class="size-3.5" /> New chat
                    </button>
                    <Link :href="index()" class="rounded-full p-1.5 text-muted-foreground hover:bg-accent" aria-label="Open the full chat page" title="Open full page">
                        <Maximize2 class="size-4" />
                    </Link>
                    <button type="button" class="rounded-full p-1.5 text-muted-foreground hover:bg-accent" aria-label="Close chat" @click="open = false">
                        <X class="size-4" />
                    </button>
                </span>
            </div>
            <ChatPanel
                v-if="history"
                page
                compact
                class="min-h-0 flex-1"
                :consented="history.consented"
                :messages="history.messages"
            />
            <div v-else class="grid flex-1 place-items-center text-sm text-muted-foreground">
                <Mascot pose="thinking" round class="whale-pulse size-16" />
            </div>
        </section>
        <div class="fixed right-4 bottom-4 z-40 md:right-6 md:bottom-6">
            <Transition name="bubble">
                <div
                    v-if="bubble && !open"
                    class="absolute right-[4.75rem] bottom-3 w-max max-w-[15rem] rounded-2xl rounded-br-md border bg-card px-4 py-2.5 text-sm shadow-lg"
                    role="status"
                >
                    <span class="font-semibold">Hi, I'm Wally!</span>
                    <span class="block text-muted-foreground">
                        {{ due ? `${due} ${due === 1 ? 'thing is' : 'things are'} due. Chat with me?` : 'Chat with me about your money.' }}
                    </span>
                </div>
            </Transition>
            <button
                type="button"
                class="group relative grid size-16 place-items-center rounded-full bg-card shadow-xl ring-2 ring-white/70 transition active:scale-95 dark:ring-white/20"
                :aria-label="due ? `Chat with Wally, ${due} ${due === 1 ? 'item' : 'items'} due` : 'Chat with Wally'"
                @pointerenter="peek"
                @click="toggle"
            >
                <Mascot pose="happy" round class="whale-bob size-full transition group-hover:scale-105" />
                <!-- due today or late: a count, plus a soft ping until the chat has been opened today -->
                <span
                    v-if="due && !open"
                    class="absolute -top-1 -right-1 grid h-6 min-w-6 place-items-center rounded-full bg-red-500 px-1 text-xs font-bold text-white ring-2 ring-background"
                >
                    <span v-if="!seen" class="ping absolute inset-0 rounded-full bg-red-500" aria-hidden="true" />
                    <span class="relative">{{ due > 9 ? '9+' : due }}</span>
                </span>
            </button>
        </div>
    </template>
</template>

<style scoped>
.whale-bob {
    animation: whale-bob 4s ease-in-out infinite;
}

.bubble-enter-active,
.bubble-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
}

.bubble-enter-from,
.bubble-leave-to {
    opacity: 0;
    transform: translateX(8px) scale(0.96);
}

@keyframes whale-bob {
    50% {
        transform: translateY(-3px);
    }
}

.ping {
    animation: ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes ping {
    75%,
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

.whale-pulse {
    animation: whale-pulse 1.4s ease-in-out infinite;
}

@keyframes whale-pulse {
    50% {
        transform: scale(1.08);
        opacity: 0.7;
    }
}

@media (prefers-reduced-motion: reduce) {
    .whale-pulse,
    .whale-bob,
    .ping {
        animation: none;
    }
}
</style>
