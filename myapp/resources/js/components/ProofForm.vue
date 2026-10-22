<script lang="ts">
export type ProofValues = {
    card_id: number;
    amount: string;
    paid_on: string;
    reference: string;
    receipt_id: number | null;
};
</script>

<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { ImagePlus, PencilLine, ScanLine, TriangleAlert, X } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import AiConsent from '@/components/AiConsent.vue';
import InputError from '@/components/InputError.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { api, type ApiError } from '@/lib/api';
import { formatPeso, toPesos } from '@/lib/currency';
import { shrinkImage } from '@/lib/image';
import { destroy as receiptDestroy, show as receiptShow, store as receiptStore } from '@/routes/receipts';

/**
 * Proof of a payment or transfer: upload a receipt (read for you) or enter the details by hand.
 * The user always picks the card; the parent owns the form and decides what saving does.
 */
const props = defineProps<{
    form: InertiaForm<ProofValues>;
    cards: { id: number; name: string; balance: number }[];
    consented: boolean;
    cardLabel: string;
    submitLabel: string;
}>();
const emit = defineEmits<{ submit: []; back: [] }>();

type Scan = {
    id: number;
    status: 'queued' | 'parsing' | 'parsed' | 'failed' | 'confirmed';
    error: string | null;
    draft: {
        amount: number;
        occurred_at: string;
        reference: string | null;
        card_id: number | null;
        duplicate_of: number[];
    } | null;
};
const mode = ref<'upload' | 'manual' | null>(null);
const scan = ref<Scan | null>(null);
const scanning = ref(false);
const scanError = ref<string | null>(null);
let timer: ReturnType<typeof setInterval> | undefined;

function stopPolling() {
    clearInterval(timer);
    timer = undefined;
}

// A scan that never became a payment is thrown away with its image.
async function dropScan() {
    stopPolling();
    const old = scan.value;
    scan.value = null;
    scanError.value = null;
    props.form.receipt_id = null;

    if (old && old.status !== 'confirmed') {
        await api(receiptDestroy.url(old.id), undefined, 'DELETE').catch(() => {});
    }
}

async function switchMode(next: 'upload' | 'manual' | null) {
    await dropScan();
    mode.value = next;
}

function applyScan(s: Scan) {
    scan.value = s;

    if (s.status === 'parsed' && s.draft) {
        stopPolling();
        props.form.amount = toPesos(s.draft.amount);
        props.form.paid_on = s.draft.occurred_at;
        props.form.reference = s.draft.reference ?? '';
        props.form.card_id = s.draft.card_id ?? props.form.card_id;
        props.form.receipt_id = s.id;
    } else if (s.status === 'failed') {
        stopPolling();
    }
}

async function pickReceipt(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    scanError.value = null;

    if (file.size > 8 * 1024 * 1024) {
        scanError.value = 'That image is over 8 MB.';

        return;
    }

    const body = new FormData();
    body.append('image', await shrinkImage(file));
    scanning.value = true;

    try {
        applyScan(await api<Scan>(receiptStore.url(), body));
        timer = setInterval(async () => {
            try {
                applyScan(await api<Scan>(receiptShow.url(scan.value!.id)));
            } catch {
                // keep waiting; the next tick tries again
            }
        }, 800);
    } catch (err) {
        const e = err as ApiError;
        scanError.value = e.data?.errors?.image?.[0] ?? e.message;
    } finally {
        scanning.value = false;
    }
}

async function back() {
    await switchMode(null);
    emit('back');
}

// leaving without saving drops the scan; a saved one is already closed by the server
onBeforeUnmount(() => {
    stopPolling();

    if (scan.value && !props.form.wasSuccessful) {
        void dropScan();
    }
});
</script>

<template>
    <div v-if="!mode" class="grid gap-3">
        <button
            type="button"
            class="flex items-center gap-3 rounded-xl border p-4 text-left transition hover:border-brand hover:bg-accent/40"
            @click="mode = 'upload'"
        >
            <ScanLine class="size-5 shrink-0 text-brand-deep dark:text-brand-light" />
            <span>
                <span class="block font-medium">Upload receipt</span>
                <span class="block text-xs text-muted-foreground">
                    We read the reference number, date and amount for you.
                </span>
            </span>
        </button>
        <button
            type="button"
            class="flex items-center gap-3 rounded-xl border p-4 text-left transition hover:border-brand hover:bg-accent/40"
            @click="mode = 'manual'"
        >
            <PencilLine class="size-5 shrink-0 text-brand-deep dark:text-brand-light" />
            <span>
                <span class="block font-medium">Enter manually</span>
                <span class="block text-xs text-muted-foreground">Fill in every detail yourself.</span>
            </span>
        </button>
        <Button type="button" variant="ghost" class="justify-self-start" @click="emit('back')">Back</Button>
    </div>

    <form v-else class="grid gap-4" @submit.prevent="emit('submit')">
        <template v-if="mode === 'upload'">
            <AiConsent v-if="!consented" />
            <template v-else-if="!scan">
                <label
                    class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground transition hover:border-brand hover:bg-accent/40"
                >
                    <ImagePlus class="size-6" />
                    <span v-if="scanning" class="flex items-center gap-2"><Spinner /> Uploading…</span>
                    <span v-else>Choose a receipt image (PNG, JPG or WebP)</span>
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="sr-only"
                        :disabled="scanning"
                        @change="pickReceipt"
                    />
                </label>
                <InputError :message="scanError ?? undefined" />
            </template>
            <div
                v-else-if="scan.status === 'queued' || scan.status === 'parsing'"
                class="flex items-center gap-2 rounded-xl border p-4 text-sm text-muted-foreground"
            >
                <Spinner /> Reading your receipt…
                <button
                    type="button"
                    class="ml-auto grid size-7 place-items-center rounded-md hover:bg-accent hover:text-foreground"
                    aria-label="Cancel"
                    @click="dropScan"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>
            <div v-else-if="scan.status === 'failed'" class="grid gap-3 rounded-xl border border-destructive/40 p-4 text-sm">
                <p class="text-destructive">{{ scan.error }}</p>
                <div class="flex gap-2">
                    <Button type="button" size="sm" variant="outline" @click="dropScan">Try another image</Button>
                    <Button type="button" size="sm" variant="ghost" @click="switchMode('manual')">Enter manually</Button>
                </div>
            </div>
            <p
                v-if="scan?.status === 'parsed' && scan.draft?.duplicate_of.length"
                class="flex items-start gap-2 rounded-lg bg-yellow-500/10 p-3 text-xs"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0 text-yellow-600" />
                This looks like a payment you already logged.
            </p>
        </template>

        <template v-if="mode === 'manual' || scan?.status === 'parsed'">
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="proof-ref">Reference number</Label>
                    <Input id="proof-ref" v-model="form.reference" placeholder="Optional" />
                    <InputError :message="form.errors.reference" />
                </div>
                <div class="grid gap-2">
                    <Label for="proof-date">Date</Label>
                    <Input id="proof-date" v-model="form.paid_on" type="date" required />
                    <InputError :message="form.errors.paid_on" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="proof-amount">Amount (PHP)</Label>
                <Input id="proof-amount" v-model="form.amount" type="number" step="0.01" min="0.01" required />
                <InputError :message="form.errors.amount" />
            </div>
            <div class="grid gap-2">
                <Label for="proof-card">{{ cardLabel }}</Label>
                <SelectNative id="proof-card" v-model="form.card_id" required>
                    <option v-for="c in cards" :key="c.id" :value="c.id">
                        {{ c.name }} ({{ formatPeso(c.balance) }})
                    </option>
                </SelectNative>
                <InputError :message="form.errors.card_id" />
                <InputError :message="form.errors.receipt_id" />
            </div>
            <Button :disabled="form.processing">{{ submitLabel }}</Button>
        </template>
        <Button type="button" variant="ghost" class="justify-self-start" @click="back">Back</Button>
    </form>
</template>
