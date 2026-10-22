<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AiConsent from '@/components/AiConsent.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SelectNative from '@/components/SelectNative.vue';
import { Spinner } from '@/components/ui/spinner';
import { api, ApiError } from '@/lib/api';
import { formatPeso, toPesos } from '@/lib/currency';
import { shrinkImage } from '@/lib/image';
import { destroy as removeIncome, store as storeIncome } from '@/routes/income';
import { destroy, show, store } from '@/routes/receipts';

type Proof = {
    id: number;
    status: 'queued' | 'parsing' | 'parsed' | 'failed' | 'confirmed';
    error: string | null;
    claimed: number | null;
    draft: {
        amount: number;
        occurred_at: string;
        reference: string | null;
        merchant: string;
    } | null;
};
type Entry = {
    id: number;
    name: string;
    amount: number;
    reference: string | null;
    received_on: string | null;
};

const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{
    consented: boolean;
    entries: Entry[];
    cards: { id: number; name: string }[];
    proofs: Proof[];
}>();

const MAX_BYTES = 8 * 1024 * 1024;
const items = ref<Proof[]>([...props.proofs]);
const uploading = ref(false);
const uploadError = ref<string | null>(null);
const active = computed(() => items.value.find((i) => i.status === 'parsed'));
const waiting = computed(() =>
    items.value.some((i) => i.status === 'queued' || i.status === 'parsing'),
);
const failed = computed(() => items.value.filter((i) => i.status === 'failed'));

const manual = ref(false); // typed in by hand: no screenshot, no reference needed

const form = useForm({
    receipt_id: null as number | null,
    kind: 'salary',
    card_id: props.cards[0] ? String(props.cards[0].id) : '',
    amount: '',
    received_on: '',
    reference: '',
    name: '',
});

// Fill the confirm card from what the parser read; the user can still correct it.
watch(
    active,
    (p) => {
        if (!p?.draft) {
            return;
        }

        form.defaults({
            receipt_id: p.id,
            amount: toPesos(p.draft.amount),
            received_on: p.draft.occurred_at,
            reference: p.draft.reference ?? '',
            name: '',
        });
        form.reset();
    },
    { immediate: true },
);

function startManual() {
    form.defaults({
        receipt_id: null,
        amount: '',
        received_on: new Date().toLocaleDateString('en-CA'),
        reference: '',
        name: '',
    });
    form.reset();
    manual.value = true;
}

let cancelled = false; // set by the X: an upload still in flight is thrown away when it lands

async function upload(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    uploadError.value = null;

    if (!file) {
        return;
    }

    if (!/^image\/(png|jpe?g|webp)$/.test(file.type)) {
        uploadError.value = 'Use a PNG, JPG or WebP screenshot.';

        return;
    }

    if (file.size > MAX_BYTES) {
        uploadError.value = 'That image is over 8 MB.';

        return;
    }

    cancelled = false;
    uploading.value = true;
    const body = new FormData();
    body.append('purpose', 'income');

    try {
        body.append('image', await shrinkImage(file));
        const proof = await api<Proof>(store.url(), body);

        if (cancelled) {
            await discard(proof);
        } else {
            items.value.unshift(proof);
        }
    } catch (err) {
        const e = err as ApiError;
        uploadError.value = cancelled ? null : (e.data?.errors?.image?.[0] ?? e.message);
    } finally {
        uploading.value = false;
    }
}

// the X on "Reading your screenshot": stop waiting, delete what is still being read, and show the upload box again
function cancelReading() {
    cancelled = true;
    uploading.value = false;
    uploadError.value = null;

    for (const p of items.value.filter((i) => i.status === 'queued' || i.status === 'parsing')) {
        discard(p).catch(() => drop(p));
    }
}

// the queue worker parses in the background; ask for news every 0.8 seconds
let timer: ReturnType<typeof setInterval>;
onMounted(() => {
    timer = setInterval(async () => {
        for (const [i, item] of items.value.entries()) {
            if (item.status !== 'queued' && item.status !== 'parsing') {
                continue;
            }

            try {
                items.value[i] = await api<Proof>(show.url(item.id));
            } catch {
                // keep waiting; the next tick tries again
            }
        }
    }, 800);
});
onBeforeUnmount(() => clearInterval(timer));

const drop = (p: Proof) =>
    (items.value = items.value.filter((i) => i.id !== p.id));

async function discard(p: Proof) {
    await api(destroy.url(p.id), undefined, 'DELETE');
    drop(p);
}

function save() {
    const id = form.receipt_id;
    form.post(storeIncome.url(), {
        preserveScroll: true,
        onSuccess: () => {
            items.value = items.value.filter((i) => i.id !== id);
            form.reset('kind');
            manual.value = false;
            open.value = false;
        },
    });
}

const day = (d: string | null) =>
    d
        ? new Date(d).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
          })
        : '';
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add income</DialogTitle>
                <DialogDescription>
                    Type in what you received, or upload a screenshot and I read
                    the reference number, date and amount from it. What you
                    actually received as salary replaces your expected income
                    for that month. Side income is added on top. The card you pick receives the money, so your net worth goes up.
                </DialogDescription>
            </DialogHeader>

            <ul
                v-if="entries.length"
                class="divide-y rounded-lg border text-sm"
            >
                <li
                    v-for="e in entries"
                    :key="e.id"
                    class="flex items-center justify-between gap-3 p-3"
                >
                    <div class="min-w-0">
                        <div>{{ e.name }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ day(e.received_on) }} · {{ e.reference ? 'Ref ' + e.reference : 'Typed in' }}
                        </div>
                    </div>
                    <span class="font-medium">{{ formatPeso(e.amount) }}</span>
                    <button
                        type="button"
                        class="text-xs text-brand-deep dark:text-brand-light"
                        @click="
                            router.delete(removeIncome.url(e.id), {
                                preserveScroll: true,
                            })
                        "
                    >
                        Remove
                    </button>
                </li>
            </ul>

            <Button
                v-if="!manual && !active && !waiting && !uploading"
                type="button"
                @click="startManual"
            >
                Enter it manually
            </Button>

            <AiConsent v-if="!consented && !manual" />

            <template v-else>
                <form v-if="active || manual" class="grid gap-4" @submit.prevent="save">
                    <p class="text-xs text-muted-foreground">
                        {{ active ? 'Check what I read from your screenshot, then save.' : 'No screenshot needed.' }}
                    </p>
                    <p
                        v-if="
                            active?.claimed &&
                            active.draft &&
                            active.claimed !== active.draft.amount
                        "
                        class="rounded-md border border-warm/50 bg-warm/10 p-2 text-xs"
                    >
                        You told Whale Chat {{ formatPeso(active.claimed) }},
                        but the proof reads {{ formatPeso(active.draft.amount) }}.
                        Check the amount before saving.
                    </p>
                    <div class="grid gap-2">
                        <Label for="income-kind">Type</Label>
                        <SelectNative id="income-kind" v-model="form.kind">
                            <option value="salary">Salary</option>
                            <option value="side">Side income (added on top of your salary)</option>
                        </SelectNative>
                    </div>
                    <div v-if="cards.length" class="grid gap-2">
                        <Label for="income-card">Received in</Label>
                        <SelectNative id="income-card" v-model="form.card_id">
                            <option v-for="c in cards" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                        </SelectNative>
                        <InputError :message="form.errors.card_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="income-amount">Amount (PHP)</Label>
                        <Input
                            id="income-amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="income-date">Date received</Label>
                        <Input
                            id="income-date"
                            v-model="form.received_on"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.received_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="income-ref">Reference number{{ active ? '' : ' (optional)' }}</Label>
                        <Input
                            id="income-ref"
                            v-model="form.reference"
                            :required="!!active"
                        />
                        <InputError :message="form.errors.reference" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="income-name">Note (optional)</Label>
                        <Input
                            id="income-name"
                            v-model="form.name"
                            :placeholder="form.kind === 'side' ? 'Side income' : 'Salary'"
                        />
                    </div>
                    <InputError :message="form.errors.receipt_id" />
                    <div class="flex gap-2">
                        <Button :disabled="form.processing">Save income</Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="active ? discard(active) : (manual = false)"
                        >
                            Discard
                        </Button>
                    </div>
                </form>

                <div
                    v-else-if="waiting || uploading"
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Spinner /> Reading your screenshot…
                    <button
                        type="button"
                        class="ml-auto grid size-7 place-items-center rounded-md hover:bg-accent hover:text-foreground"
                        aria-label="Cancel"
                        @click="cancelReading"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>

                <div v-else class="grid gap-2">
                    <Label for="income-proof">Screenshot of the transfer</Label>
                    <Input
                        id="income-proof"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        required
                        @change="upload"
                    />
                </div>

                <p v-if="uploadError" class="text-sm text-destructive">
                    {{ uploadError }}
                </p>
                <div
                    v-for="p in failed"
                    :key="p.id"
                    class="rounded-lg border border-destructive/40 p-3 text-sm"
                >
                    {{ p.error }}
                    <button
                        type="button"
                        class="ml-2 font-medium underline underline-offset-4"
                        @click="discard(p)"
                    >
                        Try another screenshot
                    </button>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
