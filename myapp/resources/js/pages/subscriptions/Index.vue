<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { LayoutGrid, Plus, Table2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import SubscriptionController from '@/actions/App/Http/Controllers/SubscriptionController';
import BillCard from '@/components/BillCard.vue';
import BrandLogo from '@/components/BrandLogo.vue';
import Heading from '@/components/Heading.vue';
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
import { findBrand } from '@/lib/brandLogos';
import { toDataUrl } from '@/lib/image';
import { formatPeso, toPesos } from '@/lib/currency';
import { index } from '@/routes/subscriptions';

type Kind = 'bill' | 'subscription' | 'debt';
type Item = {
    id: number;
    name: string;
    amount: number;
    cadence: string;
    kind: Kind;
    next_due_on: string | null;
    installments: number | null;
    card_id: number | null;
    last_paid: string | null;
    logo: string | null;
    left: number | null;
    monthly: number;
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Bills & Subs', href: index() }] },
});

const props = defineProps<{
    items: Item[];
    cards: { id: number; name: string; balance: number }[];
}>();

const tabs = [
    { key: 'all', label: 'All' },
    { key: 'bill', label: 'Bills' },
    { key: 'subscription', label: 'Subscriptions' },
    { key: 'debt', label: 'Loans' },
] as const;
const tab = ref<(typeof tabs)[number]['key']>('all');
const shown = computed(() =>
    props.items.filter((i) => tab.value === 'all' || i.kind === tab.value),
);
const sort = ref<'due' | 'high' | 'low' | 'name'>('due');
const view = ref<'table' | 'cards'>('cards');
const sorted = computed(() =>
    [...shown.value].sort((a, b) =>
        sort.value === 'high'
            ? b.monthly - a.monthly
            : sort.value === 'low'
              ? a.monthly - b.monthly
              : sort.value === 'name'
                ? a.name.localeCompare(b.name)
                : (a.next_due_on ?? '9999').localeCompare(b.next_due_on ?? '9999'),
    ),
);
const paidOn = (s: Item) =>
    s.last_paid ? ` · paid ${dueDate(s.last_paid)}` : '';
const detail = (s: Item) =>
    s.kind !== 'debt'
        ? s.cadence + paidOn(s)
        : s.installments === null
          ? 'monthly'
          : s.left
            ? `${s.left} of ${s.installments} payments left`
            : 'paid off';
// No built-in logo for this name: offer an upload. The browser shrinks it to 128px so it stays tiny.
const needsLogo = computed(() => form.name.trim() && !findBrand(form.name));

async function pickLogo(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (file) {
        form.logo = await toDataUrl(file, 128).catch(() => null);
    }
}

const total = computed(() => shown.value.reduce((n, i) => n + i.monthly, 0));
const other = (k: Kind): Kind => (k === 'bill' ? 'subscription' : 'bill');
const label = (k: Kind) => (k === 'debt' ? 'loan' : k);
// a loan is paid off in 1, 2 or 3 payments, or over 6 or 12 months
const terms = [
    { value: '1', label: 'Pay once' },
    { value: '2', label: '2 payments' },
    { value: '3', label: '3 payments' },
    { value: '6', label: '6 months' },
    { value: '12', label: '12 months' },
];

const cadences = ['weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];
const blank = {
    name: '',
    amount: '',
    cadence: 'monthly',
    next_due_on: '',
    kind: 'bill' as Kind,
    installments: '',
    logo: null as string | null,
};
const open = ref(false);
const editing = ref<number | null>(null);
const form = useForm({ ...blank });

function edit(s: Item) {
    editing.value = s.id;
    form.defaults({
        name: s.name,
        amount: toPesos(s.amount),
        cadence: s.cadence,
        next_due_on: s.next_due_on ?? '',
        kind: s.kind,
        installments: s.installments?.toString() ?? '',
        logo: s.logo,
    });
    form.reset();
    open.value = true;
}

// A loan needs a payment count, so open the form with Loan chosen instead of flipping blindly.
function makeLoan(s: Item) {
    edit(s);
    form.kind = 'debt';
}

// Paying logs an expense on the chosen card and moves the item to its next due date.
const paying = ref<Item | null>(null);
const payForm = useForm({ card_id: 0, amount: '', paid_on: '' });
const payable = (s: Item) => !(s.installments !== null && !s.left);

function pay(s: Item) {
    paying.value = s;
    payForm.defaults({
        card_id: s.card_id ?? props.cards[0]?.id ?? 0,
        amount: toPesos(s.amount),
        paid_on: new Date().toISOString().slice(0, 10),
    });
    payForm.reset();
}

function submitPay() {
    payForm.post(SubscriptionController.pay.url(paying.value!.id), {
        preserveScroll: true,
        onSuccess: () => (paying.value = null),
    });
}

function add() {
    editing.value = null;
    form.defaults({
        ...blank,
        kind: tab.value === 'all' ? 'bill' : tab.value,
    });
    form.reset();
    open.value = true;
}

function cancel() {
    open.value = false;
    editing.value = null;
    form.defaults({ ...blank });
    form.reset();
    form.clearErrors();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: cancel };
    if (editing.value) {
        form.put(SubscriptionController.update.url(editing.value), options);
    } else {
        form.post(SubscriptionController.store.url(), options);
    }
}

// One click moves an item between bills and subscriptions.
function flip(s: Item) {
    router.put(
        SubscriptionController.update.url(s.id),
        {
            name: s.name,
            amount: toPesos(s.amount),
            cadence: s.cadence,
            next_due_on: s.next_due_on,
            kind: other(s.kind),
        },
        { preserveScroll: true },
    );
}

function remove(s: Item) {
    if (confirm(`Delete ${s.name}?`)) {
        router.delete(SubscriptionController.destroy.url(s.id), {
            preserveScroll: true,
        });
    }
}

const dueDate = (d: string | null) =>
    d
        ? new Date(d).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
          })
        : 'No date';
</script>

<template>
    <Head title="Bills & Subscriptions" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                class="mb-0!"
                title="Bills & Subscriptions"
                description="Recurring charges and what they cost each month."
            />
            <Button @click="add"><Plus /> Add new</Button>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <Button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                size="sm"
                :variant="tab === t.key ? 'default' : 'outline'"
                @click="tab = t.key"
            >
                {{ t.label }}
            </Button>
            <SelectNative
                v-model="sort"
                class="ml-auto w-auto"
                aria-label="Sort by"
            >
                <option value="due">Due soonest</option>
                <option value="high">Highest amount</option>
                <option value="low">Lowest amount</option>
                <option value="name">Name</option>
            </SelectNative>
            <Button
                type="button"
                size="icon"
                variant="outline"
                :aria-label="view === 'table' ? 'Show as cards' : 'Show as table'"
                @click="view = view === 'table' ? 'cards' : 'table'"
            >
                <LayoutGrid v-if="view === 'table'" />
                <Table2 v-else />
            </Button>
        </div>

        <div class="rounded-xl border bg-card p-5">
            <div class="text-xs text-muted-foreground">Monthly total</div>
            <div class="mt-3 text-3xl font-semibold">
                {{ formatPeso(total) }}
            </div>
            <div class="mt-2 text-xs text-brand-deep dark:text-brand-light">
                {{ shown.length }} {{ shown.length === 1 ? 'item' : 'items' }}
            </div>
        </div>

        <p v-if="!shown.length" class="text-sm text-muted-foreground">
            Nothing here yet.
        </p>
        <div
            v-else-if="view === 'table'"
            class="overflow-x-auto rounded-xl border bg-card"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b text-xs text-muted-foreground uppercase">
                    <tr>
                        <th class="p-3 font-medium">Name</th>
                        <th class="p-3 font-medium">Type</th>
                        <th class="p-3 font-medium">Next due</th>
                        <th class="p-3 font-medium">Repeats</th>
                        <th class="p-3 text-right font-medium">Amount</th>
                        <th class="p-3 text-right font-medium">Monthly</th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="s in sorted" :key="s.id">
                        <td class="p-3">
                            <div class="flex items-center gap-3 font-medium">
                                <BrandLogo :name="s.name" :logo="s.logo" /> {{ s.name }}
                            </div>
                        </td>
                        <td class="p-3">
                            <span
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground uppercase"
                            >
                                {{ label(s.kind) }}
                            </span>
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            {{ dueDate(s.next_due_on) }}
                        </td>
                        <td class="p-3 capitalize">{{ detail(s) }}</td>
                        <td class="p-3 text-right">
                            {{ formatPeso(s.amount) }}
                        </td>
                        <td class="p-3 text-right font-semibold">
                            {{ formatPeso(s.monthly) }}
                        </td>
                        <td class="p-3">
                            <div
                                class="flex gap-3 text-xs font-medium text-brand-deep dark:text-brand-light"
                            >
                                <button v-if="payable(s)" type="button" class="font-semibold" @click="pay(s)">
                                    Pay
                                </button>
                                <button type="button" @click="edit(s)">
                                    Edit
                                </button>
                                <button
                                    v-if="s.kind !== 'debt'"
                                    type="button"
                                    @click="flip(s)"
                                >
                                    Make {{ other(s.kind) }}
                                </button>
                                <button v-if="s.kind !== 'debt'" type="button" @click="makeLoan(s)">
                                    Make loan
                                </button>
                                <button type="button" @click="remove(s)">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ul v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <BillCard
                v-for="s in sorted"
                :key="s.id"
                :name="s.name"
                :logo="s.logo"
                :meta="`${label(s.kind)} · ${detail(s)} · ${dueDate(s.next_due_on)}`"
                :amount="s.amount"
                :due="s.next_due_on"
                :done="s.installments !== null && !s.left"
            >
                <button v-if="payable(s)" type="button" class="font-semibold" @click="pay(s)">
                    Pay
                </button>
                <button type="button" @click="edit(s)">Edit</button>
                <button v-if="s.kind !== 'debt'" type="button" @click="flip(s)">
                    Make {{ other(s.kind) }}
                </button>
                <button v-if="s.kind !== 'debt'" type="button" @click="makeLoan(s)">
                    Make loan
                </button>
                <button type="button" @click="remove(s)">Delete</button>
            </BillCard>
        </ul>

        <Dialog :open="!!paying" @update:open="(v) => !v && (paying = null)">
            <DialogContent v-if="paying" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Pay {{ paying.name }}</DialogTitle>
                    <DialogDescription>
                        This logs an expense on the card you pick and moves the
                        next due date forward.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitPay">
                    <div class="grid gap-2">
                        <Label for="pay-card">Paid from</Label>
                        <SelectNative id="pay-card" v-model="payForm.card_id" required>
                            <option v-for="c in cards" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ formatPeso(c.balance) }})
                            </option>
                        </SelectNative>
                        <InputError :message="payForm.errors.card_id" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="pay-amount">Amount (PHP)</Label>
                            <Input
                                id="pay-amount"
                                v-model="payForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                            />
                            <InputError :message="payForm.errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pay-date">Paid on</Label>
                            <Input id="pay-date" v-model="payForm.paid_on" type="date" required />
                            <InputError :message="payForm.errors.paid_on" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button :disabled="payForm.processing">Mark as paid</Button>
                        <Button type="button" variant="ghost" @click="paying = null">
                            Cancel
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="open" @update:open="(v) => !v && cancel()">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? 'Edit item' : 'Add item' }}
                    </DialogTitle>
                    <DialogDescription>
                        Bills and subscriptions repeat on the schedule you set. Loans are paid monthly and stop after the last payment.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="kind">Type</Label>
                        <SelectNative id="kind" v-model="form.kind">
                            <option value="bill">Bill</option>
                            <option value="subscription">Subscription</option>
                            <option value="debt">Loan</option>
                        </SelectNative>
                        <InputError :message="form.errors.kind" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="amount">Amount per charge (PHP)</Label>
                        <Input
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>
                    <div v-if="form.kind === 'debt'" class="grid gap-2">
                        <Label for="installments">Pay it off in</Label>
                        <SelectNative
                            id="installments"
                            v-model="form.installments"
                            required
                        >
                            <option value="" disabled>Choose</option>
                            <option
                                v-for="t in terms"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </SelectNative>
                        <InputError :message="form.errors.installments" />
                    </div>
                    <div v-else class="grid gap-2">
                        <Label for="cadence">Repeats</Label>
                        <SelectNative id="cadence" v-model="form.cadence">
                            <option v-for="c in cadences" :key="c" :value="c">
                                {{ c }}
                            </option>
                        </SelectNative>
                        <InputError :message="form.errors.cadence" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="next_due_on">
                            {{ form.kind === 'debt' ? 'First payment' : 'Next charge' }}
                        </Label>
                        <Input
                            id="next_due_on"
                            v-model="form.next_due_on"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.next_due_on" />
                    </div>
                    <div v-if="form.name.trim()" class="flex items-center gap-3 sm:col-span-2">
                        <BrandLogo :name="form.name" :logo="form.logo" />
                        <template v-if="form.logo">
                            <button type="button" class="text-xs font-medium underline" @click="form.logo = null">
                                Remove logo
                            </button>
                        </template>
                        <label v-else-if="needsLogo" class="grid gap-1 text-xs text-muted-foreground">
                            No logo found for this name. Upload one (optional)
                            <input type="file" accept="image/png,image/jpeg,image/webp" @change="pickLogo" />
                        </label>
                        <span v-else class="text-xs text-muted-foreground">Logo found automatically</span>
                    </div>
                    <InputError class="sm:col-span-2" :message="form.errors.logo" />
                    <div class="flex gap-2 sm:col-span-2">
                        <Button :disabled="form.processing">Save</Button>
                        <Button type="button" variant="ghost" @click="cancel">
                            Cancel
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
