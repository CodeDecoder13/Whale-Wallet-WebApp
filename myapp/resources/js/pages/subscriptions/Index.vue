<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import SubscriptionController from '@/actions/App/Http/Controllers/SubscriptionController';
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
    left: number | null;
    monthly: number;
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Bills & Subs', href: index() }] },
});

const props = defineProps<{ items: Item[] }>();

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
    });
    form.reset();
    open.value = true;
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

        <div class="flex gap-2 text-sm">
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
        <ul v-else class="divide-y rounded-xl border bg-card text-sm">
            <li
                v-for="s in shown"
                :key="s.id"
                class="flex flex-wrap items-center gap-3 p-4"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-accent font-semibold text-accent-foreground"
                >
                    {{ s.name.charAt(0).toUpperCase() }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="font-medium">
                        {{ s.name }}
                        <span
                            class="ml-1 rounded bg-muted px-1.5 py-0.5 text-[10px] font-normal text-muted-foreground uppercase"
                        >
                            {{ label(s.kind) }}
                        </span>
                    </div>
                    <div class="text-xs text-muted-foreground">
                        <template v-if="s.kind === 'debt'">
                            {{
                                s.installments === null
                                    ? 'Monthly'
                                    : s.left
                                      ? `${s.left} of ${s.installments} payments left, next ${dueDate(s.next_due_on)}`
                                      : 'Paid off'
                            }}
                        </template>
                        <template v-else>
                            Next charge {{ dueDate(s.next_due_on) }},
                            {{ s.cadence }}
                        </template>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-semibold">
                        {{ formatPeso(s.monthly) }}
                        <span class="text-xs font-normal text-muted-foreground">
                            / month
                        </span>
                    </div>
                    <div
                        v-if="s.cadence !== 'monthly'"
                        class="text-xs text-muted-foreground"
                    >
                        {{ formatPeso(s.amount) }} {{ s.cadence }}
                    </div>
                </div>
                <div
                    class="flex gap-3 text-xs font-medium text-brand-deep dark:text-brand-light"
                >
                    <button type="button" @click="edit(s)">Edit</button>
                    <button
                        v-if="s.kind !== 'debt'"
                        type="button"
                        @click="flip(s)"
                    >
                        Make {{ other(s.kind) }}
                    </button>
                    <button type="button" @click="remove(s)">Delete</button>
                </div>
            </li>
        </ul>

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
