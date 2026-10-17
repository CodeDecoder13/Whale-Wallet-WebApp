<script lang="ts">
type Draft = {
    merchant: string | null;
    amount: number | null;
    card: string | null;
    category: string | null;
    occurred_at: string;
    missing: string[];
    kind?: 'loan' | 'income';
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
import { Link } from '@inertiajs/vue3';
import { Paperclip, SendHorizontal } from '@lucide/vue';
import { nextTick, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { confirm, messages } from '@/routes/chat';
import AiConsent from '@/components/AiConsent.vue';
import Mascot from '@/components/Mascot.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { api, ApiError } from '@/lib/api';
import { formatPeso } from '@/lib/currency';
import { dashboard } from '@/routes';
import { index as receipts } from '@/routes/receipts';
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


const props = defineProps<ChatProps & { logClass?: string }>();

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
        form.append('image', body);
    }

    msgs.value.push({ role: 'user', content: label });
    sending.value = true;

    try {
        const r = await api<{
            id: number;
            reply: string;
            draft?: Draft;
            quick_replies?: string[];
            receipt_id?: number;
            income?: boolean;
        }>(messages.url(), form);
        msgs.value.push({
            id: r.id,
            role: 'assistant',
            content: r.reply,
            draft: r.draft,
            quick: r.quick_replies,
            receipt_id: r.receipt_id,
            income: r.income,
        });
    } catch (e) {
        const err = e as ApiError;
        msgs.value.push({
            role: 'assistant',
            content: err.data?.errors?.text?.[0] ?? err.message,
            error: err.status === 429 || err.status === 503,
        });
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
        const r = await api<{ transaction_id: number }>(confirm.url(m.id!), {});
        m.draft!.saved = r.transaction_id;
        toast.success(
            m.draft!.kind === 'loan'
                ? 'Saved to Bills & Subs.'
                : 'Saved to your transactions.',
        );
    } catch (e) {
        toast.error((e as ApiError).message);
    }
}

const ready = (m: Msg) =>
    m.draft && !m.draft.missing.length && !m.draft.saved && !m.dismissed;
</script>

<template>
    <div>
    <AiConsent v-if="!consented" />

    <div v-else class="flex flex-col gap-3">
        <div
            ref="log"
            :class="[logClass ?? 'h-[calc(100svh-22rem)] min-h-72', 'flex flex-col gap-3 overflow-y-auto rounded-xl border bg-card p-4']"
            aria-live="polite"
        >
            <div
                v-if="!msgs.length"
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
                :class="m.role === 'user' ? 'items-end' : 'items-start'"
            >
                <div
                    class="max-w-[85%] rounded-2xl px-4 py-2 text-sm"
                    :class="
                        m.role === 'user'
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-foreground'
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
                                : receipts()
                        "
                        class="mt-1 block font-medium underline underline-offset-4"
                    >
                        {{ m.income ? 'Open Add income' : 'Open Receipts' }}
                    </Link>
                </div>

                <div
                    v-if="
                        m.draft &&
                        m.draft.kind !== 'income' &&
                        !m.dismissed &&
                        !m.draft.missing.length
                    "
                    class="w-full max-w-[85%] rounded-xl border bg-background p-4 text-sm"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-medium">{{
                            m.draft.merchant
                        }}</span>
                        <span class="text-lg font-semibold">
                            {{ formatPeso(m.draft.amount ?? 0) }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <template v-if="m.draft.kind === 'loan'">
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
                        Saved
                    </p>
                    <div v-else-if="ready(m)" class="mt-3 flex gap-2">
                        <Button size="sm" @click="save(m)">Save</Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="m.dismissed = true"
                        >
                            Dismiss
                        </Button>
                    </div>
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
            >
                <Mascot pose="thinking" round class="size-8" /> Thinking...
            </div>
        </div>

        <form class="flex gap-2" @submit.prevent="submit">
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
