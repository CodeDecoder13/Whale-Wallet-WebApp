<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowUpRight, Plus } from '@lucide/vue';
import { ref } from 'vue';
import CardController from '@/actions/App/Http/Controllers/CardController';
import BankPicker from '@/components/BankPicker.vue';
import BrandLogo from '@/components/BrandLogo.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { bankColor, cardGradient } from '@/lib/banks';
import { formatPeso, toPesos } from '@/lib/currency';
import { index } from '@/routes/cards';
import { index as transactionsIndex } from '@/routes/transactions';

type WalletCard = {
    id: number;
    name: string;
    type: string;
    balance: number;
    monthly_limit: number | null;
    color: string | null;
    spent: number;
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Cards', href: index() }] },
});

defineProps<{ cards: WalletCard[]; types: string[] }>();

const blank = { name: '', type: 'ewallet', balance: '0', monthly_limit: '', color: null as string | null };
const editing = ref<number | null>(null);
const form = useForm({ ...blank });

function edit(c: WalletCard) {
    editing.value = c.id;
    form.defaults({
        name: c.name,
        type: c.type,
        balance: toPesos(c.balance),
        monthly_limit: toPesos(c.monthly_limit),
        color: c.color,
    });
    form.reset();
}

function cancel() {
    editing.value = null;
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
const kind = (t: string) =>
    t === 'credit' ? 'Credit' : t === 'cash' ? 'Cash' : 'Debit';
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
                <Button as-child>
                    <a href="#card-form"><Plus /> Add card</a>
                </Button>
            </div>
        </div>

        <p v-if="!cards.length" class="text-sm text-muted-foreground">
            No cards yet.
        </p>
        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <li
                v-for="(c, i) in cards"
                :key="c.id"
                class="flex min-h-44 flex-col gap-1 rounded-xl bg-linear-to-br p-5 text-white"
                :class="hue(c) && usage(c) < 1 ? '' : tile(c, i)"
                :style="
                    hue(c) && usage(c) < 1
                        ? { background: cardGradient(hue(c)!) }
                        : undefined
                "
            >
                <div class="flex items-center gap-3 text-sm">
                    <BrandLogo :name="c.name" />
                    <span class="min-w-0 flex-1 truncate font-medium">
                        {{ c.name }}
                    </span>
                    <span
                        class="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-medium"
                    >
                        {{ kind(c.type) }}
                    </span>
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

        <form
            id="card-form"
            class="grid max-w-3xl scroll-mt-24 gap-4 rounded-xl border bg-card p-6 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <h3 class="font-medium sm:col-span-2">
                {{ editing ? 'Edit card' : 'Add card' }}
            </h3>
            <div class="grid gap-2 sm:col-span-2">
                <Label>Bank or wallet</Label>
                <BankPicker
                    :name="form.name"
                    :color="form.color"
                    @pick="(b) => Object.assign(form, b)"
                />
            </div>
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required @input="form.color = null" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="type">Type</Label>
                <SelectNative id="type" v-model="form.type">
                    <option v-for="t in types" :key="t" :value="t">
                        {{ typeLabel[t] ?? t }}
                    </option>
                </SelectNative>
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
            <div class="flex gap-2 sm:col-span-2">
                <Button :disabled="form.processing">Save</Button>
                <Button
                    v-if="editing"
                    type="button"
                    variant="ghost"
                    @click="cancel"
                >
                    Cancel
                </Button>
            </div>
        </form>
    </div>
</template>
