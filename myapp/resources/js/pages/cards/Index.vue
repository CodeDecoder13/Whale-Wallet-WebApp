<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowUpRight, Plus, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CardController from '@/actions/App/Http/Controllers/CardController';
import BankPicker from '@/components/BankPicker.vue';
import BrandLogo from '@/components/BrandLogo.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import WhaleAside from '@/components/WhaleAside.vue';
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
import { bankColor, banks, cardGradient } from '@/lib/banks';
import { findBrand } from '@/lib/brandLogos';
import { formatPeso, toPesos } from '@/lib/currency';
import { toDataUrl } from '@/lib/image';
import { index } from '@/routes/cards';
import { index as transactionsIndex } from '@/routes/transactions';

type WalletCard = {
    id: number;
    name: string;
    type: string;
    balance: number;
    monthly_limit: number | null;
    color: string | null;
    account_name: string | null;
    account_number: string | null;
    qr: string | null;
    spent: number;
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Cards', href: index() }] },
});

defineProps<{ cards: WalletCard[]; types: string[] }>();

const blank = { name: '', type: 'ewallet', balance: '0', monthly_limit: '', color: null as string | null, account_name: '', account_number: '', qr: null as string | null };
const editing = ref<number | null>(null);
const open = ref(false);
const form = useForm({ ...blank });

function edit(c: WalletCard) {
    editing.value = c.id;
    form.defaults({
        name: c.name,
        type: c.type,
        balance: toPesos(c.balance),
        monthly_limit: toPesos(c.monthly_limit),
        color: c.color,
        account_name: c.account_name ?? '',
        account_number: c.account_number ?? '',
        qr: c.qr,
    });
    form.reset();
    open.value = true;
}

function add() {
    editing.value = null;
    form.defaults({ ...blank });
    form.reset();
    open.value = true;
}

// QR codes need more pixels than a logo to stay scannable
async function pickQr(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (file) {
        form.qr = await toDataUrl(file, 480).catch(() => null);
    }
}

const detail = ref<WalletCard | null>(null);
const masked = (n: string) => `•••• ${n.replace(/\D/g, '').slice(-4)}`;
const copy = (text: string) => navigator.clipboard?.writeText(text).catch(() => {});

function cancel() {
    open.value = false;
    editing.value = null;
    form.clearErrors();
    form.defaults({ ...blank });
    form.reset();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: cancel };
    if (editing.value) {
        form.put(CardController.update.url(editing.value), options);
    } else {
        form.post(CardController.store.url(), options);
    }
}

function remove(c: WalletCard) {
    if (confirm(`Delete ${c.name} and its transactions?`)) {
        router.delete(CardController.destroy.url(c.id), {
            preserveScroll: true,
        });
    }
}

// Warn at 80% of the monthly limit, based on this month's expenses.
const usage = (c: WalletCard) =>
    c.monthly_limit ? c.spent / c.monthly_limit : 0;

const gradients = [
    'from-brand-deep to-brand',
    'from-navy to-navy-2',
    'from-brand to-brand-light',
    'from-brand-deep to-brand-light',
];
// colour comes from the card, else from the bank in its name, else a rotating brand gradient
const hue = (c: WalletCard) => c.color ?? bankColor(c.name);
const hasLogo = (c: WalletCard) => {
    const b = findBrand(c.name);

    return !!(b?.image || b?.icon || b?.home);
};
const kind = (t: string) =>
    t === 'credit' ? 'Credit' : t === 'cash' ? 'Cash' : 'Debit';
// the bank or wallet picker names the card, so the Name field only shows when asked for or when the card already has its own name
const own = ref(false);
const preset = computed(() => banks.some((b) => b.name === form.name && b.color === form.color));
const showName = computed(() => own.value || (!!form.name && !preset.value));
watch(open, () => (own.value = false));
const shortType: Record<string, string> = { bank: 'Bank', ewallet: 'E-wallet', credit: 'Credit', cash: 'Cash' };
const typeLabel: Record<string, string> = {
    bank: 'Debit (bank account)',
    ewallet: 'Debit (e-wallet)',
    credit: 'Credit card',
    cash: 'Cash',
};
const tile = (c: WalletCard, i: number) =>
    usage(c) >= 1 ? 'from-warm to-warm/80' : gradients[i % gradients.length];
</script>

<template>
    <Head title="Cards" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                class="mb-0!"
                title="Cards"
                description="Wallets and accounts your expenses are charged to."
            />
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="`${transactionsIndex().url}#transfer`">
                        <ArrowUpRight /> Move money
                    </Link>
                </Button>
                <Button @click="add"><Plus /> Add card</Button>
            </div>
        </div>

        <p v-if="!cards.length" class="text-sm text-muted-foreground">
            No cards yet.
        </p>
        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <li
                v-for="(c, i) in cards"
                :key="c.id"
                class="card-tile relative isolate flex min-h-44 flex-col gap-1 overflow-hidden rounded-xl bg-linear-to-br p-5 text-white"
                :class="hue(c) && usage(c) < 1 ? '' : tile(c, i)"
                :style="
                    hue(c) && usage(c) < 1
                        ? { background: cardGradient(hue(c)!) }
                        : undefined
                "
            >
                <!-- drifting translucent circles behind the content -->
                <span class="card-orb card-orb-a" aria-hidden="true" />
                <span class="card-orb card-orb-b" aria-hidden="true" />
                <div class="flex items-center gap-3 text-sm">
                    <BrandLogo :name="c.name" />
                    <!-- a recognised bank or wallet shows its logo alone; the name stays for screen readers -->
                    <span
                        class="min-w-0 flex-1 truncate font-medium"
                        :class="hasLogo(c) && 'sr-only'"
                    >
                        {{ c.name }}
                    </span>
                    <span v-if="hasLogo(c)" class="flex-1" />
                    <span
                        class="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-medium"
                    >
                        {{ kind(c.type) }}
                    </span>
                </div>
                <div
                    v-if="c.account_name || c.account_number || c.qr"
                    class="mt-3 flex items-center justify-between gap-2 text-xs"
                >
                    <span class="min-w-0 opacity-90">
                        <span v-if="c.account_name" class="block truncate">
                            {{ c.account_name }}
                        </span>
                        <span v-if="c.account_number" class="block">
                            {{ masked(c.account_number) }}
                        </span>
                    </span>
                    <button
                        type="button"
                        class="shrink-0 rounded-full bg-white/20 px-2 py-0.5 font-medium"
                        @click="detail = c"
                    >
                        {{ c.qr ? 'QR' : 'Details' }}
                    </button>
                </div>
                <div class="mt-3 text-3xl font-semibold">
                    {{ formatPeso(c.balance) }}
                </div>
                <div v-if="c.monthly_limit" class="mt-auto text-xs">
                    {{ formatPeso(c.spent) }} of
                    {{ formatPeso(c.monthly_limit) }} this month
                    <span v-if="usage(c) >= 1" class="font-semibold">
                        - over limit
                    </span>
                    <span v-else-if="usage(c) >= 0.8" class="font-semibold">
                        - 80% used
                    </span>
                </div>
                <div v-else class="mt-auto text-xs opacity-80">
                    Available balance
                </div>
                <progress
                    v-if="c.monthly_limit"
                    class="h-1.5 w-full overflow-hidden rounded-full bg-white/25 accent-white [&::-moz-progress-bar]:bg-white [&::-webkit-progress-bar]:bg-white/25 [&::-webkit-progress-value]:bg-white"
                    :value="Math.min(100, usage(c) * 100)"
                    max="100"
                />
                <div class="mt-2 flex gap-3 text-xs font-medium">
                    <button type="button" @click="edit(c)">Edit</button>
                    <button type="button" @click="remove(c)">Delete</button>
                </div>
            </li>
        </ul>

        <Dialog :open="open" @update:open="(v) => !v && cancel()">
            <DialogContent class="max-h-[90svh] gap-0 overflow-hidden p-0 sm:max-w-4xl md:flex">
                <WhaleAside
                    title="Every peso in its place."
                    body="Add a bank, wallet or cash once. Whale Wallet keeps each balance and limit in view."
                />
                <div class="no-scrollbar grid max-h-[90svh] min-w-0 flex-1 content-start gap-5 overflow-y-auto p-6">
                <DialogHeader class="p-0 pr-8 text-left">
                    <DialogTitle>
                        {{ editing ? 'Edit card' : 'Add card' }}
                    </DialogTitle>
                    <DialogDescription>
                        Pick your bank or wallet, or type your own name.
                    </DialogDescription>
                </DialogHeader>
        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <div class="grid gap-2 sm:col-span-2">
                <Label>Bank or wallet</Label>
                <BankPicker
                    :name="form.name"
                    :color="form.color"
                    @pick="(b) => Object.assign(form, b)"
                />
                <button v-if="!showName" type="button" class="w-fit text-xs font-medium underline underline-offset-4" @click="own = true">
                    Not listed? Type your own name
                </button>
                <InputError v-if="!showName" :message="form.errors.name" />
            </div>
            <div v-if="showName" class="grid gap-2 sm:col-span-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required @input="form.color = null" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <Label id="type-label">Type</Label>
                <div role="radiogroup" aria-labelledby="type-label" class="grid grid-cols-2 gap-1 rounded-xl bg-muted p-1 sm:grid-cols-4">
                    <button
                        v-for="t in types"
                        :key="t"
                        type="button"
                        role="radio"
                        :aria-checked="form.type === t"
                        class="rounded-lg px-2 py-2 text-sm font-medium transition active:scale-[0.98]"
                        :class="form.type === t ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="form.type = t"
                    >
                        {{ shortType[t] ?? t }}
                    </button>
                </div>
                <InputError :message="form.errors.type" />
            </div>
            <div class="grid gap-2">
                <Label for="balance">Balance (PHP)</Label>
                <Input
                    id="balance"
                    v-model="form.balance"
                    type="number"
                    step="0.01"
                    required
                />
                <InputError :message="form.errors.balance" />
            </div>
            <div class="grid gap-2">
                <Label for="monthly_limit">Monthly limit (PHP, optional)</Label>
                <Input
                    id="monthly_limit"
                    v-model="form.monthly_limit"
                    type="number"
                    step="0.01"
                    min="0.01"
                />
                <InputError :message="form.errors.monthly_limit" />
            </div>
            <div class="grid gap-2">
                <Label for="account_name">Account holder (optional)</Label>
                <Input id="account_name" v-model="form.account_name" maxlength="80" />
                <InputError :message="form.errors.account_name" />
            </div>
            <div class="grid gap-2">
                <Label for="account_number">Account number (optional)</Label>
                <Input
                    id="account_number"
                    v-model="form.account_number"
                    inputmode="numeric"
                    maxlength="40"
                />
                <InputError :message="form.errors.account_number" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <Label for="qr">QR code (optional)</Label>
                <div class="flex items-center gap-3">
                    <img
                        v-if="form.qr"
                        :src="form.qr"
                        alt="QR code preview"
                        class="size-16 rounded-lg border bg-white object-contain"
                    />
                    <label
                        for="qr"
                        class="flex flex-1 cursor-pointer items-center gap-2 rounded-xl border border-dashed px-3 py-3 text-sm text-muted-foreground transition hover:bg-accent has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                    >
                        <Upload class="size-4" />
                        {{ form.qr ? 'Replace QR code' : 'Upload a QR code' }}
                        <input id="qr" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pickQr" />
                    </label>
                    <button
                        v-if="form.qr"
                        type="button"
                        class="text-xs font-medium underline"
                        @click="form.qr = null"
                    >
                        Remove
                    </button>
                </div>
                <InputError :message="form.errors.qr" />
            </div>
            <div class="flex gap-2 sm:col-span-2">
                <Button :disabled="form.processing || !form.name.trim()">{{ editing ? 'Save changes' : 'Save card' }}</Button>
                <Button type="button" variant="ghost" @click="cancel">
                    Cancel
                </Button>
            </div>
        </form>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog :open="!!detail" @update:open="(v) => !v && (detail = null)">
            <DialogContent v-if="detail" class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ detail.name }}</DialogTitle>
                    <DialogDescription>
                        Share these details to receive money.
                    </DialogDescription>
                </DialogHeader>
                <img
                    v-if="detail.qr"
                    :src="detail.qr"
                    alt="QR code"
                    class="mx-auto size-64 rounded-xl border bg-white object-contain p-2"
                />
                <dl class="grid gap-3 text-sm">
                    <div v-if="detail.account_name">
                        <dt class="text-xs text-muted-foreground">Account holder</dt>
                        <dd class="font-medium">{{ detail.account_name }}</dd>
                    </div>
                    <div v-if="detail.account_number">
                        <dt class="text-xs text-muted-foreground">Account number</dt>
                        <dd class="flex items-center justify-between gap-3 font-medium">
                            {{ detail.account_number }}
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="copy(detail.account_number)"
                            >
                                Copy
                            </Button>
                        </dd>
                    </div>
                </dl>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.card-orb {
    position: absolute;
    z-index: -10;
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.12);
    pointer-events: none;
}

.card-orb-a {
    top: -3rem;
    right: -2rem;
    width: 9rem;
    height: 9rem;
    animation: orb-drift 9s ease-in-out infinite alternate;
}

.card-orb-b {
    bottom: -3.5rem;
    left: 30%;
    width: 7rem;
    height: 7rem;
    background: rgb(255 255 255 / 0.08);
    animation: orb-drift 12s ease-in-out infinite alternate-reverse;
}

.card-tile {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.card-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px -14px rgb(0 0 0 / 0.5);
}

@keyframes orb-drift {
    to {
        transform: translate(-1.5rem, 1.25rem) scale(1.2);
    }
}

@media (prefers-reduced-motion: reduce) {
    .card-orb {
        animation: none;
    }
}
</style>
