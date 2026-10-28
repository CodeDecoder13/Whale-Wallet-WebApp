<script setup lang="ts">
import { Head, useForm, usePoll } from '@inertiajs/vue3';
import { LifeBuoy, SendHorizontal } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { ago } from '@/lib/ago';
import { index, store } from '@/routes/support';

defineOptions({ layout: { breadcrumbs: [{ title: 'Support', href: index() }] } });

type Message = { id: number; body: string; from_admin: boolean; at: string; seen: boolean };

const props = defineProps<{ status: string; messages: Message[] }>();

// the chat is a conversation, so it refreshes on its own every 4 seconds (the layout's 10 second refresh keeps the badges live)
usePoll(4000, { only: ['messages', 'status'] });

const form = useForm({ body: '' });
const list = ref<HTMLElement | null>(null);
// "Seen" sits under the last message the user wrote, once the team has opened it
const lastMine = computed(() => [...props.messages].reverse().find((m) => !m.from_admin)?.id);

const toBottom = () => nextTick(() => list.value?.scrollTo({ top: list.value.scrollHeight }));
onMounted(toBottom);
watch(() => props.messages.length, toBottom);

function send() {
    if (!form.body.trim() || form.processing) {
        return;
    }

    form.post(store.url(), { preserveScroll: true, onSuccess: () => form.reset() });
}

// Enter sends, Shift+Enter makes a new line
function onKey(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}
</script>

<template>
    <Head title="Support" />

    <div class="mx-auto flex h-[calc(100svh-6rem)] w-full max-w-2xl flex-col gap-3 px-4 py-4">
        <header class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-full bg-primary/10 text-primary"><LifeBuoy class="size-5" /></span>
            <div>
                <h1 class="text-lg font-semibold">Support</h1>
                <p class="text-xs text-muted-foreground">Talk to a person on the Whale Wallet team. We reply here and by email.</p>
            </div>
        </header>

        <div ref="list" class="flex-1 overflow-y-auto rounded-2xl border bg-card p-4 shadow-sm" aria-live="polite">
            <p v-if="!messages.length" class="mt-10 text-center text-sm text-muted-foreground">
                No messages yet. Ask anything about your account, a bill, a loan or a receipt.
            </p>
            <ol class="grid gap-3">
                <li v-for="m in messages" :key="m.id" class="flex flex-col" :class="m.from_admin ? 'items-start' : 'items-end'">
                    <div
                        class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm break-words whitespace-pre-wrap"
                        :class="m.from_admin ? 'rounded-bl-md border bg-background' : 'rounded-br-md bg-primary text-primary-foreground'"
                    >
                        {{ m.body }}
                    </div>
                    <span class="mt-1 text-[11px] text-muted-foreground">
                        {{ m.from_admin ? 'Whale Wallet support' : 'You' }} · {{ ago(m.at) }}<template v-if="!m.from_admin && m.id === lastMine && m.seen"> · Seen</template>
                    </span>
                </li>
            </ol>
        </div>

        <p v-if="status === 'closed'" class="text-center text-xs text-muted-foreground">This chat was closed. Send a message to open it again.</p>

        <form class="grid gap-2" @submit.prevent="send">
            <div class="flex items-end gap-2">
                <textarea
                    v-model="form.body"
                    rows="2"
                    maxlength="1000"
                    placeholder="Write a message"
                    aria-label="Message"
                    class="min-h-12 flex-1 resize-none rounded-xl border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    @keydown="onKey"
                />
                <Button :disabled="form.processing || !form.body.trim()" aria-label="Send" class="h-12 active:scale-[0.97]">
                    <SendHorizontal class="size-4" />
                </Button>
            </div>
            <InputError :message="form.errors.body" />
        </form>
    </div>
</template>
