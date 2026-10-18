<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImagePlus, ScanLine, TriangleAlert } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import AiConsent from '@/components/AiConsent.vue';
import InputError from '@/components/InputError.vue';
import SelectNative from '@/components/SelectNative.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { api, ApiError } from '@/lib/api';
import { formatPeso, toPesos } from '@/lib/currency';
import { confirm, destroy, keep, show, store } from '@/routes/receipts';

type Draft = {
    merchant: string;
    amount: number;
    occurred_at: string;
    reference: string | null;
    confidence: number;
    category_id: number | null;
    card_id: number | null;
    duplicate_of: number[];
};
type Item = {
    id: number;
    status: 'queued' | 'parsing' | 'parsed' | 'failed' | 'confirmed';
    error: string | null;
    draft: Draft | null;
};
type Option = { id: number; name: string };

/**
 * Receipts are transactions in waiting, so they live on the Transactions page: a banner shows what still
 * needs checking and this dialog scans, reviews and saves them.
 */
const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{
    consented: boolean;
    keepReceipts: boolean;
    receipts: Item[];
    cards: Option[];
    categories: Option[];
}>();
const emit = defineEmits<{ manual: [] }>();

const MAX_BYTES = 8 * 1024 * 1024;

const items = ref<Item[]>([...props.receipts]);
const previews = reactive<Record<number, string>>({}); // local object URLs; the server never serves images
const forms = reactive<Record<number, Record<string, string>>>({});
const errors = reactive<Record<number, Record<string, string[]>>>({});
const busy = reactive<Record<number, boolean>>({});
const uploading = ref(false);
const dragging = ref(false);
const uploadError = ref<{ message: string; manual: boolean } | null>(null);
const picker = ref<HTMLInputElement | null>(null);

const pending = (i: Item) => i.status === 'queued' || i.status === 'parsing';
const waiting = computed(() => items.value.filter(pending).length);
const ready = computed(() => items.value.length - waiting.value);

function hydrate(item: Item) {
    if (item.status !== 'parsed' || !item.draft || forms[item.id]) {
        return;
    }

    const d = item.draft;
    forms[item.id] = {
        merchant: d.merchant,
        amount: toPesos(d.amount),
        card_id: String(d.card_id ?? props.cards[0]?.id ?? ''),
        category_id: d.category_id ? String(d.category_id) : '',
        occurred_at: d.occurred_at,
        reference: d.reference ?? '',
    };
}

items.value.forEach(hydrate);

async function upload(files: FileList | File[]) {
    uploadError.value = null;

    for (const file of Array.from(files)) {
        if (!/^image\/(png|jpe?g|webp)$/.test(file.type)) {
            uploadError.value = {
                message: `${file.name || 'That file'} is not a PNG, JPG or WebP image.`,
                manual: false,
            };
            continue;
        }

        if (file.size > MAX_BYTES) {
            uploadError.value = {
                message: `${file.name || 'That image'} is over 8 MB.`,
                manual: false,
            };
            continue;
        }

        const body = new FormData();
        body.append('image', file);
        uploading.value = true;

        try {
            const item = await api<Item>(store.url(), body);
            previews[item.id] = URL.createObjectURL(file);
            items.value.unshift(item);
            hydrate(item);
        } catch (e) {
            const err = e as ApiError;
            uploadError.value = {
                message: err.data?.errors?.image?.[0] ?? err.message,
                manual: err.status === 429 || err.status === 503,
            };
        } finally {
            uploading.value = false;
        }
    }
}

const onPick = (e: Event) => {
    const input = e.target as HTMLInputElement;
    void upload(input.files ?? []);
    input.value = '';
};
const onDrop = (e: DragEvent) => {
    dragging.value = false;
    void upload(e.dataTransfer?.files ?? []);
};
const onPaste = (e: ClipboardEvent) => {
    if (open.value && props.consented && e.clipboardData?.files.length) {
        void upload(e.clipboardData.files);
    }
};

// the queue worker parses in the background; ask for news every 1.5 seconds while anything is waiting
let timer: ReturnType<typeof setInterval>;
onMounted(() => {
    window.addEventListener('paste', onPaste);
    timer = setInterval(async () => {
        for (const [i, item] of items.value.entries()) {
            if (!pending(item)) {
                continue;
            }

            try {
                const fresh = await api<Item>(show.url(item.id));
                items.value[i] = fresh;
                hydrate(fresh);
            } catch {
                // keep waiting; the next tick tries again
            }
        }
    }, 1500);
});
onBeforeUnmount(() => {
    window.removeEventListener('paste', onPaste);
    clearInterval(timer);
});

const drop = (item: Item) => {
    items.value = items.value.filter((i) => i.id !== item.id);
    delete previews[item.id];
};

async function save(item: Item) {
    busy[item.id] = true;
    errors[item.id] = {};

    try {
        await api(confirm.url(item.id), forms[item.id]);
        drop(item);
        toast.success('Saved to your transactions.');
        router.reload({ only: ['transactions', 'cards'] });
    } catch (e) {
        const err = e as ApiError;
        errors[item.id] = err.data?.errors ?? {};
        toast.error(
            Object.keys(errors[item.id]).length
                ? 'Check the highlighted fields.'
                : err.message,
        );
    } finally {
        busy[item.id] = false;
    }
}

async function discard(item: Item) {
    await api(destroy.url(item.id), undefined, 'DELETE');
    drop(item);
}

const manual = () => {
    open.value = false;
    emit('manual');
};

const toggleKeep = (e: Event) =>
    router.post(
        keep.url(),
        { keep: (e.target as HTMLInputElement).checked },
        { preserveScroll: true },
    );
</script>

<template>
    <div
        v-if="items.length && !open"
        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand/30 bg-accent/50 px-4 py-3"
    >
        <p class="flex items-center gap-2 text-sm">
            <Spinner v-if="waiting" class="size-4 text-brand" />
            <ScanLine v-else class="size-4 text-brand" />
            <span v-if="ready">
                {{ ready }} receipt{{ ready === 1 ? '' : 's' }} to check
                <span v-if="waiting" class="text-muted-foreground">
                    · {{ waiting }} still reading
                </span>
            </span>
            <span v-else>
                Reading {{ waiting }} receipt{{ waiting === 1 ? '' : 's' }}
            </span>
        </p>
        <Button size="sm" @click="open = true">Review</Button>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Scan a receipt</DialogTitle>
                <DialogDescription>
                    Upload a GCash, Maya or bank screenshot. Nothing is saved
                    until you confirm it.
                </DialogDescription>
            </DialogHeader>

            <AiConsent v-if="!consented" />

            <template v-else>
                <div
                    role="button"
                    tabindex="0"
                    class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed p-8 text-center transition-colors"
                    :class="
                        dragging
                            ? 'border-brand bg-accent/60'
                            : 'border-border bg-card hover:border-brand/60'
                    "
                    @click="picker?.click()"
                    @keydown.enter.prevent="picker?.click()"
                    @keydown.space.prevent="picker?.click()"
                    @dragover.prevent="dragging = true"
                    @dragleave="dragging = false"
                    @drop.prevent="onDrop"
                >
                    <Spinner v-if="uploading" class="size-6 text-brand" />
                    <ImagePlus v-else class="size-6 text-brand" />
                    <p class="text-sm font-medium">
                        Drop a receipt here, click to choose, or paste an image
                    </p>
                    <p class="text-xs text-muted-foreground">
                        PNG, JPG or WebP, up to 8 MB
                    </p>
                    <input
                        ref="picker"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        multiple
                        class="sr-only"
                        aria-label="Choose receipt images"
                        @click.stop
                        @change="onPick"
                    />
                </div>

                <div
                    v-if="uploadError"
                    role="alert"
                    class="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm"
                >
                    <TriangleAlert
                        class="mt-0.5 size-4 shrink-0 text-destructive"
                    />
                    <p>
                        {{ uploadError.message }}
                        <button
                            v-if="uploadError.manual"
                            type="button"
                            class="font-medium underline underline-offset-4"
                            @click="manual"
                        >
                            Add the expense manually
                        </button>
                    </p>
                </div>

                <ul v-if="items.length" class="grid gap-3">
                    <li
                        v-for="item in items"
                        :key="item.id"
                        class="rounded-xl border bg-card p-4"
                    >
                        <div class="flex gap-4">
                            <img
                                v-if="previews[item.id]"
                                :src="previews[item.id]"
                                alt="Uploaded receipt"
                                class="h-28 w-20 shrink-0 rounded-md border object-cover object-top"
                            />

                            <div class="min-w-0 flex-1">
                                <div
                                    v-if="pending(item)"
                                    class="flex items-center gap-2 text-sm text-muted-foreground"
                                >
                                    <Spinner class="text-brand" />
                                    {{
                                        item.status === 'queued'
                                            ? 'Waiting in line...'
                                            : 'Reading your receipt...'
                                    }}
                                </div>

                                <div
                                    v-else-if="item.status === 'failed'"
                                    class="grid gap-3"
                                >
                                    <p class="flex items-start gap-2 text-sm">
                                        <TriangleAlert
                                            class="mt-0.5 size-4 shrink-0 text-destructive"
                                        />
                                        {{ item.error }}
                                    </p>
                                    <div class="flex gap-2">
                                        <Button size="sm" @click="manual">
                                            Add manually
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="discard(item)"
                                        >
                                            Dismiss
                                        </Button>
                                    </div>
                                </div>

                                <form
                                    v-else-if="item.draft && forms[item.id]"
                                    class="grid gap-3"
                                    @submit.prevent="save(item)"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <h3 class="font-medium">
                                            Check and save
                                        </h3>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                Math.round(
                                                    item.draft.confidence * 100,
                                                )
                                            }}% sure
                                        </span>
                                    </div>

                                    <p
                                        v-if="item.draft.duplicate_of.length"
                                        role="alert"
                                        class="flex items-start gap-2 rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm"
                                    >
                                        <TriangleAlert
                                            class="mt-0.5 size-4 shrink-0 text-amber-600"
                                        />
                                        <span>
                                            Possible duplicate: you already
                                            logged a
                                            {{ formatPeso(item.draft.amount) }}
                                            expense like this. Check the list
                                            behind this window.
                                        </span>
                                    </p>

                                    <div class="grid gap-1.5">
                                        <Label :for="`m${item.id}`">
                                            Merchant
                                        </Label>
                                        <Input
                                            :id="`m${item.id}`"
                                            v-model="forms[item.id].merchant"
                                            required
                                        />
                                        <InputError
                                            :message="
                                                errors[item.id]?.merchant?.[0]
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="grid gap-1.5">
                                            <Label :for="`a${item.id}`">
                                                Amount (PHP)
                                            </Label>
                                            <Input
                                                :id="`a${item.id}`"
                                                v-model="forms[item.id].amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                required
                                            />
                                            <InputError
                                                :message="
                                                    errors[item.id]?.amount?.[0]
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-1.5">
                                            <Label :for="`d${item.id}`">
                                                Date
                                            </Label>
                                            <Input
                                                :id="`d${item.id}`"
                                                v-model="
                                                    forms[item.id].occurred_at
                                                "
                                                type="date"
                                                required
                                            />
                                            <InputError
                                                :message="
                                                    errors[item.id]
                                                        ?.occurred_at?.[0]
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-1.5">
                                            <Label :for="`c${item.id}`">
                                                Card
                                            </Label>
                                            <SelectNative
                                                :id="`c${item.id}`"
                                                v-model="forms[item.id].card_id"
                                                required
                                            >
                                                <option
                                                    v-for="c in cards"
                                                    :key="c.id"
                                                    :value="String(c.id)"
                                                >
                                                    {{ c.name }}
                                                </option>
                                            </SelectNative>
                                            <InputError
                                                :message="
                                                    errors[item.id]
                                                        ?.card_id?.[0]
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-1.5">
                                            <Label :for="`g${item.id}`">
                                                Category
                                            </Label>
                                            <SelectNative
                                                :id="`g${item.id}`"
                                                v-model="
                                                    forms[item.id].category_id
                                                "
                                            >
                                                <option value="">None</option>
                                                <option
                                                    v-for="c in categories"
                                                    :key="c.id"
                                                    :value="String(c.id)"
                                                >
                                                    {{ c.name }}
                                                </option>
                                            </SelectNative>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button :disabled="busy[item.id]">
                                            Save
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            :disabled="busy[item.id]"
                                            @click="discard(item)"
                                        >
                                            Discard
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </li>
                </ul>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        class="size-4 accent-brand"
                        :checked="keepReceipts"
                        @change="toggleKeep"
                    />
                    Keep receipt images after I save them
                    <span class="text-xs text-muted-foreground">
                        (off: the image is deleted on Save)
                    </span>
                </label>
            </template>

            <p class="text-xs text-muted-foreground">
                Receipt reading can be wrong. Always check the amount and date.
            </p>
        </DialogContent>
    </Dialog>
</template>
