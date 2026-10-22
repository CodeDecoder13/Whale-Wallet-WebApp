<script lang="ts">
type Draft = {
    merchant: string | null;
    amount: number | null;
    card: string | null;
    category: string | null;
    occurred_at: string;
    missing: string[];
    kind?: 'loan' | 'income' | 'manage' | 'dues';
    items?: { id: number; name: string; kind: string; amount: number; logo: string | null; due_on: string }[];
    title?: string;
    lines?: string[];
    label?: string;
    destructive?: boolean;
    done?: string;
    recurring_id?: number;
    payments?: number | null;
    saved?: number;
};
export type ChatProps = {
    consented: boolean;
    messages: {
        id: number;
        role: 'user' | 'assistant';
        content: string;
        draft_json: Draft | null;
        receipt_id: number | null;
        income?: boolean;
    }[];
};
</script>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Mic, Paperclip, Plus, SendHorizontal, Sparkles } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { confirm, messages, transcribe } from '@/routes/chat';
import AiConsent from '@/components/AiConsent.vue';
import BrandLogo from '@/components/BrandLogo.vue';
import Mascot from '@/components/Mascot.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { api, ApiError } from '@/lib/api';
import { formatPeso } from '@/lib/currency';
import { dueTone } from '@/lib/dueTone';
import { shrinkImage } from '@/lib/image';
import { dashboard } from '@/routes';
import { index as subscriptions } from '@/routes/subscriptions';
import { index as transactions } from '@/routes/transactions';

type Msg = {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
    draft?: Draft | null;
    quick?: string[];
    receipt_id?: number | null;
    income?: boolean;
    error?: boolean;
    dismissed?: boolean;
};


// page = the full chat page: hero with the mascot and a floating composer. Without it, the small pill window.
const props = defineProps<ChatProps & { logClass?: string; page?: boolean; compact?: boolean; threadId?: number | null }>();
const emit = defineEmits<{ sent: [] }>();

const firstName = computed(
    () =>
        (usePage().props.auth as { user?: { name?: string } } | undefined)?.user?.name?.split(' ')[0] ?? 'there',
);
const field = ref<HTMLInputElement | null>(null);
// chips that start a sentence for the user instead of sending
const starters = [
    { label: 'Log expense', fill: 'Log ' },
    { label: 'Ask about spending', fill: 'How much did I spend on ' },
    { label: 'Change a bill', fill: 'Change ' },
    { label: 'Cancel a subscription', fill: 'Cancel ' },
];
const startWith = (fill: string) => {
    text.value = fill;
    void nextTick(() => field.value?.focus());
};

const suggestions = [
    'Jollibee 250',
    'How much food this week?',
    'What is safe to spend?',
];

const msgs = ref<Msg[]>(
    props.messages.map((m) => ({
        id: m.id,
        role: m.role,
        content: m.content,
        draft: m.draft_json,
        receipt_id: m.receipt_id,
        income: m.income,
    })),
);
const text = ref('');
const sending = ref(false);
const log = ref<HTMLElement | null>(null);
const picker = ref<HTMLInputElement | null>(null);

// voice: record in the browser, Gemini transcribes and detects English, Tagalog or Taglish itself
const listening = ref(false);
const transcribing = ref(false);
let recorder: MediaRecorder | null = null;

async function listen() {
    if (!navigator.mediaDevices || typeof MediaRecorder === 'undefined') {
        toast.error('Voice input is not supported in this browser.');

        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const chunks: Blob[] = [];
        recorder = new MediaRecorder(stream);
        recorder.ondataavailable = (e) => chunks.push(e.data);
        recorder.onstop = async () => {
            stream.getTracks().forEach((t) => t.stop());
            listening.value = false;
            const blob = new Blob(chunks, { type: recorder?.mimeType || 'audio/webm' });

            if (blob.size < 1500) {
                return; // nothing was said
            }

            const form = new FormData();
            form.append('audio', blob, 'voice.webm');
            transcribing.value = true;

            try {
                const r = await api<{ text: string }>(transcribe.url(), form);
                text.value = `${text.value.trim()} ${r.text}`.trim().slice(0, 500);
                void nextTick(() => field.value?.focus());
            } catch (e) {
                toast.error((e as ApiError).message);
            } finally {
                transcribing.value = false;
            }
        };
        recorder.start();
        listening.value = true;
    } catch {
        toast.error('Allow microphone access to use voice.');
    }
}
onBeforeUnmount(() => recorder?.state === 'recording' && recorder.stop());

const toBottom = () =>
    nextTick(() => log.value?.scrollTo({ top: log.value.scrollHeight }));
watch(msgs, toBottom, { deep: true });
onMounted(toBottom);

async function send(body: string | File) {
    const form = new FormData();
    const label = typeof body === 'string' ? body : `Receipt: ${body.name}`;

    if (typeof body === 'string') {
        form.append('text', body);
    } else {
        form.append('image', await shrinkImage(body));
    }

    if (props.threadId) {
        form.append('thread_id', String(props.threadId));
    }

    msgs.value.push({ role: 'user', content: label });
    sending.value = true;

    try {
        type Reply = {
            id: number;
            reply: string;
            draft?: Draft;
            quick_replies?: string[];
            receipt_id?: number;
            income?: boolean;
        };
        const r = await api<Reply & { extra?: Reply[] }>(messages.url(), form);

        // one message can hold several requests: each comes back as its own reply with its own confirm
        for (const x of [r, ...(r.extra ?? [])]) {
            msgs.value.push({
                id: x.id,
                role: 'assistant',
                content: x.reply,
                draft: x.draft,
                quick: x.quick_replies,
                receipt_id: x.receipt_id,
                income: x.income,
            });
        }
    } catch (e) {
        const err = e as ApiError;
        msgs.value.push({
            role: 'assistant',
            content: err.data?.errors?.text?.[0] ?? err.message,
            error: err.status === 429 || err.status === 503,
        });
        emit('sent');
    } finally {
        sending.value = false;
    }
}

function submit() {
    const t = text.value.trim();

    if (t && !sending.value) {
        text.value = '';
        void send(t);
    }
}

const onFile = (e: Event) => {
    const input = e.target as HTMLInputElement;

    if (input.files?.[0]) {
        void send(input.files[0]);
    }

    input.value = '';
};

async function save(m: Msg) {
    try {
        const r = await api<{ transaction_id: number; message?: string }>(confirm.url(m.id!), {});
        m.draft!.saved = r.transaction_id;
        m.draft!.done = r.message;
        toast.success(
            r.message ??
                (m.draft!.kind === 'income'
                ? 'Income recorded.'
                : m.draft!.kind === 'loan'
                ? 'Saved to Bills & Subs.'
                : m.draft!.recurring_id
                  ? 'Bill marked as paid.'
                  : 'Saved to your transactions.'),
        );
    } catch (e) {
        toast.error((e as ApiError).message);
    }
}

const ready = (m: Msg) =>
    m.draft && !m.draft.missing.length && !m.draft.saved && !m.dismissed;
</script>

<template>
    <div class="relative flex min-h-0 flex-col">
    <AiConsent v-if="!consented" />

    <div v-else class="flex min-h-0 flex-1 flex-col gap-3">
        <div
            ref="log"
            :class="
                page
                    ? ['no-scrollbar flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto px-3', compact ? 'pt-3 pb-36' : 'pt-4 pb-64 md:px-6']
                    : [logClass ?? 'min-h-0 flex-1', 'no-scrollbar flex flex-col gap-3 overflow-y-auto rounded-xl border bg-card p-4']
            "
            aria-live="polite"
        >
            <div
                v-if="!msgs.length && page"
                class="m-auto flex w-full max-w-2xl flex-col items-center pb-8 text-center"
            >
                <h2 class="leading-tight font-semibold tracking-tight text-balance" :class="compact ? 'text-xl' : 'text-3xl md:text-4xl'">
                    <span class="text-muted-foreground">Hi {{ firstName }},</span>
                    what are we doing with your money today?
                </h2>
                <div class="relative flex w-full max-w-lg items-end justify-center" :class="compact ? 'mt-5 h-28' : 'mt-10 h-52'">
                    <div v-if="!compact" class="bubble absolute top-6 left-0 sm:left-4">
                        <Mascot pose="happy" round class="size-6" />
                        Tell me what you spent
                    </div>
                    <div v-if="!compact" class="bubble absolute top-0 right-0 sm:right-4">
                        <Mascot pose="thinking" round class="size-6" />
                        Ask me about your money
                    </div>
                    <Mascot pose="hero" round class="whale-float shadow-lg ring-1 ring-black/5" :class="compact ? 'size-24' : 'size-40'" />
                </div>
            </div>

            <div
                v-else-if="!msgs.length"
                class="m-auto text-center text-sm text-muted-foreground"
            >
                <Mascot pose="happy" round class="mx-auto mb-2 size-16" />
                <p>Hi! Try one of these.</p>
                <div class="mt-3 flex flex-wrap justify-center gap-2">
                    <Button
                        v-for="s in suggestions"
                        :key="s"
                        variant="outline"
                        size="sm"
                        :disabled="sending"
                        @click="send(s)"
                    >
                        {{ s }}
                    </Button>
                </div>
            </div>

            <div
                v-for="(m, i) in msgs"
                :key="m.id ?? `t${i}`"
                class="flex flex-col gap-2"
                :class="[m.role === 'user' ? 'items-end' : 'items-start', page && 'mx-auto w-full max-w-4xl']"
            >
                <div
                    class="text-sm whitespace-pre-line lg:text-[15px]"
                    :class="
                        m.role === 'user'
                            ? 'max-w-[85%] rounded-3xl bg-primary px-5 py-2.5 text-primary-foreground sm:max-w-xl lg:max-w-2xl'
                            : page
                              ? 'max-w-full leading-7 lg:text-base' /* like a document, no bubble */
                              : 'max-w-[85%] rounded-2xl bg-muted px-4 py-2.5 text-foreground sm:max-w-xl lg:max-w-2xl'
                    "
                >
                    <Mascot
                        v-if="m.role === 'assistant'"
                        :pose="m.error ? 'worried' : 'happy'"
                        round
                        class="float-left mr-2 size-8"
                    />
                    {{ m.content }}
                    <Link
                        v-if="m.error"
                        :href="transactions()"
                        class="mt-1 block font-medium underline underline-offset-4"
                    >
                        Add the expense manually
                    </Link>
                    <Link
                        v-if="m.receipt_id"
                        :href="
                            m.income
                                ? dashboard({ query: { income: 1 } })
                                : transactions({ query: { scan: 1 } })
                        "
                        class="mt-1 block font-medium underline underline-offset-4"
                    >
                        {{ m.income ? 'Open Add income' : 'Check receipt' }}
                    </Link>
                </div>

                <div
                    v-if="
                        m.draft &&
                        m.draft.kind !== 'dues' &&
                        !m.dismissed &&
                        !m.draft.missing.length
                    "
                    class="w-full max-w-[85%] rounded-xl border bg-background p-4 text-sm sm:max-w-xl lg:max-w-2xl"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-medium">{{
                            m.draft.merchant
                        }}</span>
                        <span v-if="m.draft.kind !== 'manage'" class="text-lg font-semibold">
                            {{ formatPeso(m.draft.amount ?? 0) }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <template v-if="m.draft.kind === 'manage'">
                            <span v-for="l in m.draft.lines" :key="l" class="block">{{ l }}</span>
                        </template>
                        <template v-else-if="m.draft.kind === 'income'">
                            Income · {{ m.draft.occurred_at }}
                        </template>
                        <template v-else-if="m.draft.kind === 'loan'">
                            Loan · {{ m.draft.payments }}
                            {{ m.draft.payments === 1 ? 'payment' : 'payments' }}
                            · first due {{ m.draft.occurred_at }}
                        </template>
                        <template v-else>
                            {{ m.draft.card }} ·
                            {{ m.draft.category ?? 'No category' }} ·
                            {{ m.draft.occurred_at }}
                        </template>
                    </p>
                    <p
                        v-if="m.draft.saved"
                        class="mt-3 text-xs font-medium text-brand-deep dark:text-brand-light"
                    >
                        {{ m.draft.done ?? 'Saved' }}
                    </p>
                    <div v-else-if="ready(m)" class="mt-3 flex gap-2">
                        <Button
                            size="sm"
                            :variant="m.draft.destructive ? 'destructive' : 'default'"
                            @click="save(m)"
                        >
                            {{ m.draft.label ?? 'Save' }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="m.dismissed = true"
                        >
                            Dismiss
                        </Button>
                    </div>
                </div>

                <!-- what is due: bills, subscriptions and loans, each coloured by how soon -->
                <div
                    v-if="m.draft?.kind === 'dues'"
                    class="w-full max-w-2xl overflow-hidden rounded-2xl border bg-background text-sm"
                >
                    <ul class="divide-y">
                        <li v-for="b in m.draft.items" :key="b.id" class="flex items-center gap-3 p-3">
                            <BrandLogo :name="b.name" :logo="b.logo" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ b.name }}</div>
                                <div class="text-xs text-muted-foreground capitalize">
                                    {{ b.kind === 'debt' ? 'loan' : b.kind }}
                                </div>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :style="{
                                    color: dueTone(b.due_on).c,
                                    background: `color-mix(in srgb, ${dueTone(b.due_on).c} 15%, transparent)`,
                                }"
                            >
                                {{ dueTone(b.due_on).text }}
                            </span>
                            <span class="w-24 text-right font-semibold">{{ formatPeso(b.amount) }}</span>
                        </li>
                    </ul>
                    <Link
                        :href="subscriptions()"
                        class="block border-t px-3 py-2 text-xs font-medium text-brand-deep underline-offset-4 hover:underline dark:text-brand-light"
                    >
                        Open Bills & Subs
                    </Link>
                </div>

                <div
                    v-if="m.quick?.length && i === msgs.length - 1"
                    class="flex flex-wrap gap-2"
                >
                    <Button
                        v-for="q in m.quick"
                        :key="q"
                        variant="outline"
                        size="sm"
                        :disabled="sending"
                        @click="send(q)"
                    >
                        {{ q }}
                    </Button>
                </div>
            </div>

            <div
                v-if="sending"
                class="flex items-center gap-2 text-sm text-muted-foreground"
                :class="page && 'mx-auto w-full max-w-4xl'"
            >
                <Mascot pose="thinking" round class="size-8" /> Thinking...
            </div>
        </div>

        <div
            v-if="page"
            class="pointer-events-none absolute inset-x-0 bottom-0 z-10 px-3 pb-3" :class="!compact && 'md:px-6 md:pb-5'"
        >
            <form
                class="pointer-events-auto mx-auto w-full max-w-4xl overflow-hidden rounded-3xl border bg-card/95 shadow-xl ring-1 ring-black/5 backdrop-blur"
                @submit.prevent="submit"
            >
                <input
                    ref="picker"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    class="sr-only"
                    aria-label="Attach a receipt image"
                    @change="onFile"
                />
                <div class="flex items-center gap-1.5 p-2.5 pl-3">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="grid size-10 shrink-0 place-items-center rounded-full border bg-card transition hover:bg-accent"
                                aria-label="Attach or speak"
                                :disabled="sending"
                            >
                                <Plus class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent side="top" align="start">
                            <DropdownMenuItem @select="picker?.click()">
                                <Paperclip class="size-4" /> Attach file
                            </DropdownMenuItem>
                            <DropdownMenuItem @select="listen()"><Mic class="size-4" /> Voice</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="grid size-10 shrink-0 place-items-center rounded-full text-brand-deep transition hover:bg-accent dark:text-brand-light"
                                aria-label="Start a request"
                                :disabled="sending"
                            >
                                <Sparkles class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent side="top" align="start">
                            <DropdownMenuItem v-for="st in starters" :key="st.label" @select="startWith(st.fill)">
                                {{ st.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <input
                        ref="field"
                        v-model="text"
                        class="min-h-10 min-w-0 flex-1 bg-transparent px-1 text-base outline-none placeholder:text-muted-foreground"
                        :placeholder="listening ? 'Listening... tap the mic when done' : transcribing ? 'Transcribing...' : compact ? 'Log, ask, or change a bill' : 'Log an expense, ask about your money, or change a bill'"
                        maxlength="500"
                        aria-label="Message"
                        :disabled="sending"
                    />
                    <button
                        v-if="listening"
                        type="button"
                        class="grid size-10 shrink-0 animate-pulse place-items-center rounded-full bg-red-500 text-white"
                        aria-label="Stop listening"
                        @click="recorder?.stop()"
                    >
                        <Mic class="size-4" />
                    </button>
                    <button
                        class="grid size-10 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground shadow transition active:scale-95 disabled:opacity-40"
                        :disabled="sending || !text.trim()"
                        aria-label="Send"
                    >
                        <SendHorizontal class="size-4" />
                    </button>
                </div>
            </form>
            <div v-if="!msgs.length" class="pointer-events-auto mx-auto mt-3 flex max-w-4xl gap-2 overflow-x-auto pb-1">
                <button
                    v-for="sg in suggestions"
                    :key="sg"
                    type="button"
                    class="chip bg-card/80"
                    :disabled="sending"
                    @click="send(sg)"
                >
                    {{ sg }}
                </button>
            </div>
        </div>

        <form v-else class="flex gap-2" @submit.prevent="submit">
            <input
                ref="picker"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="sr-only"
                aria-label="Attach a receipt image"
                @change="onFile"
            />
            <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label="Attach a receipt"
                :disabled="sending"
                @click="picker?.click()"
            >
                <Paperclip />
            </Button>
            <Input
                v-model="text"
                placeholder='Try "Jollibee 250" or "how much food this week?"'
                maxlength="500"
                aria-label="Message"
                :disabled="sending"
            />
            <Button :disabled="sending || !text.trim()" aria-label="Send">
                <SendHorizontal />
            </Button>
        </form>
    </div>
    </div>
</template>

<style scoped>
.bubble {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 1rem;
    background: var(--card);
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    box-shadow: 0 6px 20px -8px rgb(15 23 42 / 0.25);
    outline: 1px solid rgb(0 0 0 / 0.05);
    animation: bubble-in 0.6s ease both;
}

.bubble:last-of-type {
    animation-delay: 0.15s;
}

.chip {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.375rem;
    border-radius: 9999px;
    border: 1px solid var(--border);
    background: var(--card);
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    transition: background-color 0.15s;
}

.chip:hover:not(:disabled) {
    background: var(--accent);
}

.whale-float {
    animation: whale-float 4s ease-in-out infinite;
}

@keyframes whale-float {
    50% {
        transform: translateY(-8px);
    }
}

@keyframes bubble-in {
    from {
        opacity: 0;
        transform: translateY(6px) scale(0.96);
    }
}

@media (prefers-reduced-motion: reduce) {
    .whale-float,
    .bubble {
        animation: none;
    }
}
</style>
