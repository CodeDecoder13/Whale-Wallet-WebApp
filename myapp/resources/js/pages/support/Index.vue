<script setup lang="ts">
import { Head, router, useForm, usePoll } from '@inertiajs/vue3';
import { LifeBuoy } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import SupportComposer from '@/components/SupportComposer.vue';
import SupportFile from '@/components/SupportFile.vue';
import { Button } from '@/components/ui/button';
import { ago } from '@/lib/ago';
import { index, store } from '@/routes/support';

defineOptions({ layout: { breadcrumbs: [{ title: 'Support', href: index() }] } });

type Message = { id: number; body: string; from_admin: boolean; at: string; seen: boolean; file: { name: string; size: number; image: boolean } | null };
type Ticket = { id: number; status: string; subject: string | null; closed_by: string | null };

const props = defineProps<{
    ticket: Ticket | null;
    messages: Message[];
    history: { id: number; subject: string | null; status: string; at: string }[];
    templates: { key: string; label: string; text: string }[];
}>();

// the ticket is a conversation, so it refreshes on its own every 4 seconds (the layout's 10 second refresh keeps the badges live)
usePoll(4000, { only: ['ticket', 'messages', 'history'] });

const form = useForm({ body: '', file: null as File | null, ticket: props.ticket?.id ?? null, template: null as string | null });
watch(() => props.ticket?.id, (id) => (form.ticket = id ?? null));

const composer = ref<InstanceType<typeof SupportComposer> | null>(null);
const list = ref<HTMLElement | null>(null);
const closed = computed(() => props.ticket?.status === 'closed');
// "Seen" sits under the last message the user wrote, once the team has opened it
const lastMine = computed(() => [...props.messages].reverse().find((m) => !m.from_admin)?.id);

const toBottom = () => nextTick(() => list.value?.scrollTo({ top: list.value.scrollHeight }));
onMounted(toBottom);
watch(() => props.messages.length, toBottom);

// a template fills the box so the user can add their details; "Something else" is just the empty box
function useTemplate(t: { key: string; text: string }) {
    form.body = t.text;
    form.template = t.key;
    nextTick(() => composer.value?.focus());
}

const send = () => form.post(store.url(), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('body', 'file', 'template') });
const endTicket = () =>
    confirm('End this ticket? You can start a new one any time. Our team keeps a copy of the conversation.') &&
    router.post(`/support/${props.ticket!.id}/close`, {}, { preserveScroll: true });
const newTicket = () => router.get('/support', { new: 1 });
</script>

<template>
    <Head title="Support" />

    <div class="mx-auto flex h-[calc(100svh-6rem)] w-full max-w-2xl flex-col gap-3 px-4 py-4">
        <header class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-full bg-primary/10 text-primary"><LifeBuoy class="size-5" /></span>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-semibold">{{ ticket?.subject || 'Support' }}</h1>
                <p class="text-xs text-muted-foreground">Talk to a person on the Whale Wallet team. We reply here and by email.</p>
            </div>
            <Button v-if="ticket && !closed" variant="outline" size="sm" @click="endTicket">End ticket</Button>
            <Button v-else-if="ticket" size="sm" @click="newTicket">New ticket</Button>
        </header>

        <details v-if="history.length > 1 || (history.length === 1 && !ticket)" class="rounded-xl border bg-card px-4 py-2 text-sm">
            <summary class="cursor-pointer font-medium">Your tickets ({{ history.length }})</summary>
            <ul class="mt-2 grid gap-1">
                <li v-for="h in history" :key="h.id">
                    <a :href="`/support?ticket=${h.id}`" class="flex justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-accent" :class="h.id === ticket?.id && 'bg-accent/60'">
                        <span class="truncate">{{ h.subject || `Ticket #${h.id}` }}</span>
                        <span class="shrink-0 text-xs text-muted-foreground">{{ h.status === 'closed' ? 'closed · ' : '' }}{{ ago(h.at) }}</span>
                    </a>
                </li>
            </ul>
        </details>

        <div ref="list" class="flex-1 overflow-y-auto rounded-2xl border bg-card p-4 shadow-sm" aria-live="polite">
            <div v-if="!ticket" class="grid gap-3">
                <p class="text-sm font-medium">What do you need help with?</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="t in templates"
                        :key="t.key"
                        type="button"
                        class="rounded-xl border bg-background px-3 py-2.5 text-left text-sm transition-colors duration-150 ease-out hover:bg-accent active:scale-[0.98]"
                        :class="form.template === t.key && 'border-primary bg-primary/5'"
                        @click="useTemplate(t)"
                    >
                        {{ t.label }}
                    </button>
                </div>
                <p class="text-sm text-muted-foreground">Not on the list? Write your own message below and attach a picture or PDF if it helps.</p>
            </div>

            <ol v-else class="grid gap-3">
                <li v-for="m in messages" :key="m.id" class="flex flex-col" :class="m.from_admin ? 'items-start' : 'items-end'">
                    <div
                        v-if="m.body || m.file"
                        class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm break-words whitespace-pre-wrap"
                        :class="m.from_admin ? 'rounded-bl-md border bg-background' : 'rounded-br-md bg-primary text-primary-foreground'"
                    >
                        {{ m.body }}
                        <SupportFile v-if="m.file" :file="m.file" :url="`/support/files/${m.id}`" />
                    </div>
                    <span class="mt-1 text-[11px] text-muted-foreground">
                        {{ m.from_admin ? 'Whale Wallet support' : 'You' }} · {{ ago(m.at) }}<template v-if="!m.from_admin && m.id === lastMine && m.seen"> · Seen</template>
                    </span>
                </li>
            </ol>
        </div>

        <p v-if="closed" class="text-center text-sm text-muted-foreground">
            This ticket was closed{{ ticket?.closed_by === 'admin' ? ' by our team' : '' }}. Start a new ticket if you still need help.
        </p>
        <SupportComposer
            v-else
            ref="composer"
            v-model:body="form.body"
            v-model:file="form.file"
            :processing="form.processing"
            :error="form.errors.body || form.errors.file"
            :placeholder="ticket ? 'Write a message' : 'Write your own message, or pick a topic above'"
            @send="send"
        />
    </div>
</template>
