<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Ban, LayoutGrid, Pencil, Plus, Table2, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import SubscriptionController from '@/actions/App/Http/Controllers/SubscriptionController';
import BillCard from '@/components/BillCard.vue';
import BrandLogo from '@/components/BrandLogo.vue';
import WhaleAside from '@/components/WhaleAside.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProofForm from '@/components/ProofForm.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { findBrand } from '@/lib/brandLogos';
import { toDataUrl } from '@/lib/image';
import { formatPeso, toPesos } from '@/lib/currency';
import { dueTone } from '@/lib/dueTone';
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
    cancelled: boolean;
    hidden: boolean;
    hidden_monthly: number;
    cancel_reason: string | null;
    payments: { id: number; amount: number; card: string; paid_on: string }[];
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
    consented: boolean;
}>();

const tabs = [
    { key: 'all', label: 'All' },
    { key: 'bill', label: 'Bills' },
    { key: 'subscription', label: 'Subscriptions' },
    { key: 'debt', label: 'Loans' },
    { key: 'archived', label: 'Archived' },
] as const;
const tab = ref<(typeof tabs)[number]['key']>('all');
// A loan that is fully paid is completed: it leaves the active tabs and stays under Archived with its history.
const completed = (i: Item) => i.installments !== null && !i.left;
const shown = computed(() =>
    props.items.filter((i) =>
        tab.value === 'archived'
            ? completed(i)
            : !completed(i) && (tab.value === 'all' || i.kind === tab.value),
    ),
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
            : 'completed';
// No built-in logo for this name: offer an upload. The browser shrinks it to 128px so it stays tiny.
const needsLogo = computed(() => form.name.trim() && !findBrand(form.name));

async function pickLogo(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (file) {
        form.logo = await toDataUrl(file, 128).catch(() => null);
    }
}

// One side panel slides in from the right: details first, then the pay or edit form in the same place.
const panel = ref<'info' | 'pay' | 'edit' | 'cancel' | 'delete' | null>(null);
const selectedId = ref<number | null>(null);
const selected = computed(() => props.items.find((i) => i.id === selectedId.value) ?? null);
const tone = (s: Item) =>
    s.cancelled || s.hidden
        ? { days: null, c: '#94a3b8', text: s.cancelled ? 'Cancelled' : 'Hidden' }
        : dueTone(s.next_due_on, s.installments !== null && !s.left);

// Delete and cancel ask first inside the panel; cancel keeps the subscription so it can be renewed.
const cancelReasons = ['Too expensive', 'Not using it', 'Switched provider', 'Pausing for now'];
const cancelForm = useForm({ reason: '' });

function openItem(s: Item) {
    selectedId.value = s.id;
    cancelForm.reset();
    panel.value = 'info';
}

function submitCancel() {
    cancelForm.post(SubscriptionController.cancel.url(selectedId.value!), {
        preserveScroll: true,
        onSuccess: () => {
            panel.value = 'info';
            cancelForm.reset();
        },
    });
}

const renew = (s: Item) =>
    router.post(SubscriptionController.renew.url(s.id), {}, { preserveScroll: true });

const total = computed(() => shown.value.reduce((n, i) => n + i.monthly, 0));
// what hiding takes off every month (for the items in this tab)
const hiddenTotal = computed(() => shown.value.reduce((n, i) => n + i.hidden_monthly, 0));

// Hide for now: the item stays here but is left out of income left, the forecast, due lists and net worth until shown again.
const toggleHidden = (s: Item) =>
    router.post(s.hidden ? SubscriptionController.show.url(s.id) : SubscriptionController.hide.url(s.id), {}, { preserveScroll: true });
const other = (k: Kind): Kind => (k === 'bill' ? 'subscription' : 'bill');
// the middle stat on a card: how often it repeats, or how many loan payments remain
const repeat = (s: Item): [string, string] =>
    s.kind !== 'debt'
        ? ['Repeats', s.cadence]
        : s.installments === null
          ? ['Repeats', 'monthly']
          : ['Payments', s.left ? `${s.left} of ${s.installments} left` : 'completed'];
const kinds: { value: Kind; label: string }[] = [
    { value: 'bill', label: 'Bill' },
    { value: 'subscription', label: 'Subscription' },
    { value: 'debt', label: 'Loan' },
];
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
const editing = ref<number | null>(null);
const form = useForm({ ...blank });

function edit(s: Item) {
    selectedId.value = s.id;
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
    panel.value = 'edit';
}

// A loan needs a payment count, so open the form with Loan chosen instead of flipping blindly.
function makeLoan(s: Item) {
    edit(s);
    form.kind = 'debt';
}


// Paying logs an expense on the chosen card and moves the item to its next due date.
const payForm = useForm({ card_id: 0, amount: '', paid_on: '', reference: '', receipt_id: null as number | null });
const payable = (s: Item) => !s.cancelled && !(s.installments !== null && !s.left);

function pay(s: Item) {
    selectedId.value = s.id;
    payForm.defaults({
        card_id: s.card_id ?? props.cards[0]?.id ?? 0,
        amount: toPesos(s.amount),
        paid_on: new Date().toISOString().slice(0, 10),
        reference: '',
        receipt_id: null,
    });
    payForm.reset();
    panel.value = 'pay';
}

function submitPay() {
    payForm.post(SubscriptionController.pay.url(selectedId.value!), {
        preserveScroll: true,
        onSuccess: () => (panel.value = 'info'),
    });
}

function add() {
    selectedId.value = null;
    editing.value = null;
    form.defaults({
        ...blank,
        kind: tab.value === 'all' || tab.value === 'archived' ? 'bill' : tab.value,
    });
    form.reset();
    panel.value = 'edit';
}

// Leaving a form goes back to the details when an item is open, otherwise the panel closes.
function cancel() {
    panel.value = editing.value ? 'info' : null;
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
    panel.value = null;
    router.delete(SubscriptionController.destroy.url(s.id), { preserveScroll: true });
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
            <div v-if="hiddenTotal" class="mt-1 text-xs text-muted-foreground">
                Hiding {{ formatPeso(hiddenTotal) }} a month. Not counted in your dashboard until you show it again.
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
                                <button type="button" @click="(openItem(s), (panel = 'delete'))">
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
                :kind="label(s.kind)"
                :repeat="repeat(s)"
                :date="dueDate(s.next_due_on)"
                :amount="s.amount"
                :due="s.next_due_on"
                :done="s.installments !== null && !s.left"
                :cancelled="s.cancelled"
                :hidden="s.hidden"
                @select="openItem(s)"
            />
        </ul>

        <Dialog :open="panel !== null" @update:open="(v) => !v && (panel === 'edit' ? cancel() : (panel = null))">
            <DialogContent
                class="no-scrollbar max-h-[90vh] gap-0 overflow-x-hidden overflow-y-auto p-0 transition-[max-width] duration-300 md:flex"
                :class="(selected && panel !== 'info') || (!selected && panel === 'edit') ? 'sm:max-w-3xl' : 'sm:max-w-md'"
            >
                <WhaleAside
                    v-if="panel === 'edit' && !selected"
                    title="Make room for what matters."
                    body="Add the next charge once. Whale Wallet keeps the rhythm visible for you."
                />
                <div v-if="selected" class="relative min-w-0 md:shrink-0" :class="panel === 'info' ? 'md:w-full' : 'md:w-[26rem]'">
                    <div class="grid gap-5 p-6">
                        <DialogHeader class="flex-row items-center gap-3 p-0 pr-8 text-left">
                            <BrandLogo :name="selected.name" :logo="selected.logo" />
                            <div class="min-w-0 flex-1">
                                <DialogTitle class="truncate">{{ selected.name }}</DialogTitle>
                                <DialogDescription class="capitalize">
                                    {{ label(selected.kind) }} · {{ detail(selected) }}
                                </DialogDescription>
                            </div>
                            <div class="-mt-4 flex shrink-0 gap-0.5 self-start">
                                <Button
                                    v-if="selected.kind === 'subscription' && !selected.cancelled"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    aria-label="Cancel subscription"
                                    title="Cancel subscription"
                                    @click="panel = 'cancel'"
                                >
                                    <Ban class="size-4" />
                                </Button>
                                <Button variant="ghost" size="icon" class="size-8" aria-label="Edit" title="Edit" @click="edit(selected)">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-destructive"
                                    aria-label="Delete"
                                    title="Delete"
                                    @click="panel = 'delete'"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </DialogHeader>

                        <div class="flex items-end justify-between gap-3">
                            <div class="text-4xl font-semibold tracking-tight">{{ formatPeso(selected.amount) }}</div>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :style="{
                                    color: tone(selected).c,
                                    background: `color-mix(in srgb, ${tone(selected).c} 15%, transparent)`,
                                }"
                            >
                                {{ tone(selected).text }}
                            </span>
                        </div>

                        <div v-if="selected.installments !== null" class="grid gap-1.5">
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full"
                                    :style="{
                                        width: `${((selected.installments - (selected.left ?? 0)) / selected.installments) * 100}%`,
                                        background: tone(selected).c,
                                    }"
                                />
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ selected.left ?? 0 }} of {{ selected.installments }} payments left
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">Next due</dt>
                                <dd class="font-medium">{{ dueDate(selected.next_due_on) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Costs per month</dt>
                                <dd class="font-medium">{{ formatPeso(selected.monthly) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Pays from</dt>
                                <dd class="font-medium">
                                    {{ cards.find((c) => c.id === selected!.card_id)?.name ?? 'Picked when you pay' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Last paid</dt>
                                <dd class="font-medium">{{ selected.last_paid ? dueDate(selected.last_paid) : 'Not yet' }}</dd>
                            </div>
                        </dl>

                        <template v-if="selected.cancelled">
                            <div class="grid gap-1 rounded-xl border p-4 text-sm">
                                <div class="text-xs text-muted-foreground">Cancelled because</div>
                                <div class="font-medium">{{ selected.cancel_reason }}</div>
                            </div>
                            <Button class="w-full" @click="renew(selected)">Renew subscription</Button>
                        </template>
                        <Button v-else-if="payable(selected)" class="w-full" @click="pay(selected)">Pay now</Button>

                        <div v-if="!selected.cancelled && !completed(selected)" class="grid gap-2">
                            <Button variant="outline" class="w-full" @click="toggleHidden(selected)">
                                {{ selected.hidden ? 'Show again' : 'Hide for now' }}
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                {{ selected.hidden
                                    ? 'Hidden: left out of your income left, forecast, due list and net worth. Show it again to count it.'
                                    : 'Hiding keeps it on file but leaves it out of your income left, forecast, due list and net worth, so you can see how things look without it.' }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <h3 class="text-sm font-medium">Transactions</h3>
                            <p v-if="!selected.payments.length" class="text-sm text-muted-foreground">
                                No payments yet. Pay now to log the first one.
                            </p>
                            <table v-else class="w-full text-left text-sm">
                                <thead class="border-b text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th class="py-2 font-medium">Date</th>
                                        <th class="py-2 font-medium">Card</th>
                                        <th class="py-2 text-right font-medium">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="p in selected.payments" :key="p.id">
                                        <td class="py-2 whitespace-nowrap">{{ dueDate(p.paid_on) }}</td>
                                        <td class="py-2">{{ p.card }}</td>
                                        <td class="py-2 text-right font-medium">{{ formatPeso(p.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <Transition name="slide">
                <div v-if="panel === 'cancel' && selected" class="grid min-w-0 flex-1 content-start gap-5 border-t p-6 md:border-t-0 md:border-l">
                    <DialogHeader class="p-0 pr-8 text-left">
                        <DialogTitle>Cancel {{ selected.name }}</DialogTitle>
                        <DialogDescription>
                            It stays on file with its payments, so you can renew it any time.
                        </DialogDescription>
                    </DialogHeader>
                    <form class="grid gap-4" @submit.prevent="submitCancel">
                        <div class="grid gap-2">
                            <Label for="cancel-reason">Why are you cancelling?</Label>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="r in cancelReasons"
                                    :key="r"
                                    type="button"
                                    class="rounded-full border px-2.5 py-1 text-xs transition hover:bg-accent"
                                    :class="cancelForm.reason === r && 'border-brand bg-accent'"
                                    @click="cancelForm.reason = r"
                                >
                                    {{ r }}
                                </button>
                            </div>
                            <Input id="cancel-reason" v-model="cancelForm.reason" maxlength="255" placeholder="Or write your own" required />
                            <InputError :message="cancelForm.errors.reason" />
                        </div>
                        <div class="flex gap-2">
                            <Button :disabled="cancelForm.processing">Cancel subscription</Button>
                            <Button type="button" variant="ghost" @click="panel = 'info'">Back</Button>
                        </div>
                    </form>
                </div>
                </Transition>

                <Transition name="slide">
                <div v-if="panel === 'delete' && selected" class="grid min-w-0 flex-1 content-start gap-5 border-t p-6 md:border-t-0 md:border-l">
                    <DialogHeader class="p-0 pr-8 text-left">
                        <DialogTitle>Delete {{ selected.name }}?</DialogTitle>
                        <DialogDescription>
                            Payments you already logged stay in your transactions. Only this {{ label(selected.kind) }} and its schedule are removed.
                        </DialogDescription>
                    </DialogHeader>
                    <p v-if="selected.kind === 'subscription' && !selected.cancelled" class="rounded-lg bg-accent/50 p-3 text-sm">
                        To keep it for later, cancel it instead. You can renew it any time.
                    </p>
                    <div class="flex gap-2">
                        <Button variant="destructive" @click="remove(selected)">Delete</Button>
                        <Button variant="ghost" @click="panel = 'info'">Keep it</Button>
                    </div>
                </div>
                </Transition>

                <Transition name="slide">
                <div v-if="panel === 'pay' && selected" class="grid min-w-0 flex-1 content-start gap-5 border-t p-6 md:border-t-0 md:border-l">
                    <DialogHeader class="p-0 pr-8 text-left">
                        <DialogTitle>Pay {{ selected.name }}</DialogTitle>
                        <DialogDescription>
                            Add proof of payment. It logs an expense and moves the next due date forward.
                        </DialogDescription>
                    </DialogHeader>
                    <ProofForm
                        :form="payForm"
                        :cards="cards"
                        :consented="consented"
                        card-label="Mode of payment"
                        submit-label="Mark as paid"
                        @submit="submitPay"
                        @back="panel = 'info'"
                    />
                </div>
                </Transition>

                <Transition name="slide">
                <div v-if="panel === 'edit'" class="grid min-w-0 flex-1 content-start gap-5 p-6" :class="selected && 'border-t md:border-t-0 md:border-l'">
                    <DialogHeader class="p-0 pr-8 text-left">
                        <DialogTitle>{{ editing ? 'Edit item' : 'Add item' }}</DialogTitle>
                        <DialogDescription>
                            Bills and subscriptions repeat on the schedule you set. Loans are paid monthly and stop after the last payment.
                        </DialogDescription>
                    </DialogHeader>
                    <form class="grid gap-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label id="kind-label">Type</Label>
                            <div role="radiogroup" aria-labelledby="kind-label" class="grid grid-cols-3 gap-1 rounded-xl bg-muted p-1">
                                <button
                                    v-for="k in kinds"
                                    :key="k.value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="form.kind === k.value"
                                    class="rounded-lg px-3 py-2 text-sm font-medium transition active:scale-[0.98]"
                                    :class="form.kind === k.value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                    @click="form.kind = k.value"
                                >
                                    {{ k.label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.kind" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="amount">Amount per charge (PHP)</Label>
                            <Input id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" required />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div v-if="form.kind === 'debt'" class="grid gap-2">
                            <Label for="installments">Pay it off in</Label>
                            <SelectNative id="installments" v-model="form.installments" required>
                                <option value="" disabled>Choose</option>
                                <option v-for="t in terms" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </SelectNative>
                            <InputError :message="form.errors.installments" />
                        </div>
                        <div v-else class="grid gap-2">
                            <Label for="cadence">Repeats</Label>
                            <SelectNative id="cadence" v-model="form.cadence">
                                <option v-for="c in cadences" :key="c" :value="c">{{ c }}</option>
                            </SelectNative>
                            <InputError :message="form.errors.cadence" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="next_due_on">{{ form.kind === 'debt' ? 'First payment' : 'Next charge' }}</Label>
                            <Input id="next_due_on" v-model="form.next_due_on" type="date" required />
                            <InputError :message="form.errors.next_due_on" />
                        </div>
                        <div v-if="form.name.trim()" class="flex items-center gap-3">
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
                        <InputError :message="form.errors.logo" />
                        <div class="flex gap-2">
                            <Button :disabled="form.processing">{{ editing ? 'Save changes' : 'Save item' }}</Button>
                            <Button type="button" variant="ghost" @click="cancel">Cancel</Button>
                        </div>
                    </form>
                </div>
                </Transition>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.slide-enter-active {
    transition:
        transform 0.3s ease,
        opacity 0.3s ease;
}

.slide-enter-from {
    transform: translateX(1.5rem);
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .slide-enter-active {
        transition: none;
    }
}
</style>
