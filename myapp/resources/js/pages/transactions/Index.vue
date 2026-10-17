<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Search, Trash2 } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPeso, toPesos } from '@/lib/currency';
import { index as categoriesIndex } from '@/routes/categories';
import { exportMd, exportMethod, index } from '@/routes/transactions';

type Txn = {
    id: number;
    type: 'expense' | 'income' | 'transfer';
    merchant: string | null;
    amount: number;
    occurred_at: string;
    source: string;
    note: string | null;
    card_id: number;
    category_id: number | null;
    card: { name: string };
    category: { name: string } | null;
};
type Option = { id: number; name: string };

const props = defineProps<{
    transactions: {
        data: Txn[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
        total: number;
    };
    filters: {
        q?: string;
        from?: string;
        to?: string;
        card_id?: string;
        category_id?: string;
    };
    cards: Option[];
    pods: Option[];
    categories: Option[];
    draft?: { merchant: string; amount: number; card_id: number | null };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Transactions', href: index() }] },
});

const filter = reactive({
    q: props.filters.q ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    card_id: props.filters.card_id ?? '',
    category_id: props.filters.category_id ?? '',
});

function applyFilters() {
    const query = Object.fromEntries(
        Object.entries(filter).filter(([, v]) => v !== ''),
    );
    router.get(index().url, query, { preserveState: true, replace: true });
}

function clearFilters() {
    Object.assign(filter, {
        q: '',
        from: '',
        to: '',
        card_id: '',
        category_id: '',
    });
    applyFilters();
}

const quick = useForm({ text: '' });
const expense = useForm({
    merchant: '',
    amount: '',
    card_id: props.cards[0] ? String(props.cards[0].id) : '',
    category_id: '',
    occurred_at: new Date().toISOString().slice(0, 10),
    note: '',
    source: 'manual',
});
const transfer = useForm({ from: '', to: '', amount: '' });

// A parsed quick entry arrives as a draft; the user confirms or edits it in the expense form.
watch(
    () => props.draft,
    (d) => {
        if (!d) {
            return;
        }

        expense.merchant = d.merchant;
        expense.amount = toPesos(d.amount);
        expense.card_id = d.card_id ? String(d.card_id) : expense.card_id;
        expense.source = 'quick';
    },
    { immediate: true },
);

const parseQuick = () =>
    quick.post(TransactionController.quick.url(), {
        preserveScroll: true,
        onSuccess: () => quick.reset(),
    });
const editing = ref<number | null>(null);

function edit(t: Txn) {
    editing.value = t.id;
    Object.assign(expense, {
        merchant: t.merchant ?? '',
        amount: toPesos(t.amount),
        card_id: String(t.card_id),
        category_id: t.category_id ? String(t.category_id) : '',
        occurred_at: t.occurred_at.slice(0, 10),
        note: t.note ?? '',
        source: t.source,
    });
    document.getElementById('add-expense')?.scrollIntoView();
}

function cancelEdit() {
    editing.value = null;
    expense.reset();
}

function remove(t: Txn) {
    if (confirm(`Delete ${t.merchant}? Its amount returns to the balance.`)) {
        router.delete(TransactionController.destroy.url(t.id), {
            preserveScroll: true,
        });
    }
}

function saveExpense() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
            expense.reset('merchant', 'amount', 'note', 'source');
        },
    };
    if (editing.value) {
        expense.put(TransactionController.update.url(editing.value), options);
    } else {
        expense.post(TransactionController.store.url(), options);
    }
}
const saveTransfer = () =>
    transfer.post(TransactionController.transfer.url(), {
        preserveScroll: true,
        onSuccess: () => transfer.reset(),
    });

const sign = { expense: '-', income: '+', transfer: '' };
</script>

<template>
    <Head title="Transactions" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                class="mb-0!"
                title="Transactions"
                description="Every expense is charged to a card. Transfers are not spending."
            />
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline">
                    <Link :href="categoriesIndex().url">Categories</Link>
                </Button>
                <Button as-child variant="outline">
                    <a :href="exportMethod.url()" download>Export CSV</a>
                </Button>
                <Button as-child variant="outline">
                    <a :href="exportMd.url()" download>Export Markdown</a>
                </Button>
            </div>
        </div>

        <div class="rounded-xl border bg-card p-5">
            <form
                class="flex flex-wrap items-center gap-2"
                @submit.prevent="applyFilters"
            >
                <div class="relative min-w-52 flex-1">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                    />
                    <Input
                        v-model="filter.q"
                        placeholder="Search transactions"
                        class="pl-9"
                    />
                </div>
                <Input
                    v-model="filter.from"
                    type="date"
                    aria-label="From date"
                    class="w-auto"
                />
                <Input
                    v-model="filter.to"
                    type="date"
                    aria-label="To date"
                    class="w-auto"
                />
                <SelectNative
                    v-model="filter.card_id"
                    aria-label="Card"
                    class="w-auto"
                >
                    <option value="">All cards</option>
                    <option
                        v-for="c in cards"
                        :key="c.id"
                        :value="String(c.id)"
                    >
                        {{ c.name }}
                    </option>
                </SelectNative>
                <SelectNative
                    v-model="filter.category_id"
                    aria-label="Category"
                    class="w-auto"
                >
                    <option value="">All categories</option>
                    <option
                        v-for="c in categories"
                        :key="c.id"
                        :value="String(c.id)"
                    >
                        {{ c.name }}
                    </option>
                </SelectNative>
                <Button>Filter</Button>
                <Button type="button" variant="ghost" @click="clearFilters">
                    Clear
                </Button>
            </form>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-xs text-muted-foreground">
                        <tr>
                            <th class="p-3 font-normal">Transaction</th>
                            <th class="p-3 font-normal">Category</th>
                            <th class="p-3 font-normal">Date</th>
                            <th class="p-3 font-normal">Card</th>
                            <th class="p-3 font-normal">Source</th>
                            <th class="p-3 text-right font-normal">Amount</th>
                            <th class="p-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="t in transactions.data"
                            :key="t.id"
                            class="border-b last:border-0"
                        >
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-xs font-semibold text-accent-foreground"
                                    >
                                        {{
                                            (t.merchant ?? '?')
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </span>
                                    <div>
                                        <span class="font-medium">
                                            {{ t.merchant }}
                                        </span>
                                        <Badge
                                            v-if="t.type === 'transfer'"
                                            variant="secondary"
                                            class="ml-1"
                                        >
                                            transfer
                                        </Badge>
                                        <div
                                            v-if="t.note"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t.note }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3">
                                <span
                                    v-if="t.category"
                                    class="rounded-md bg-accent px-2 py-0.5 text-xs text-accent-foreground"
                                >
                                    {{ t.category.name }}
                                </span>
                                <span v-else class="text-muted-foreground">
                                    -
                                </span>
                            </td>
                            <td
                                class="p-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ t.occurred_at.slice(0, 10) }}
                            </td>
                            <td class="p-3 text-muted-foreground">
                                {{ t.card.name }}
                            </td>
                            <td
                                class="p-3 whitespace-nowrap text-muted-foreground"
                            >
                                <span
                                    class="mr-1.5 inline-block size-1.5 rounded-full bg-brand"
                                />
                                <span class="capitalize">{{ t.source }}</span>
                            </td>
                            <td
                                class="p-3 text-right font-semibold whitespace-nowrap"
                            >
                                {{ sign[t.type] }}{{ formatPeso(t.amount) }}
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                <button
                                    v-if="t.type === 'expense'"
                                    type="button"
                                    aria-label="Edit"
                                    class="rounded-md p-1.5 hover:bg-accent"
                                    @click="edit(t)"
                                >
                                    <Pencil class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    aria-label="Delete"
                                    class="rounded-md p-1.5 hover:bg-accent"
                                    @click="remove(t)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!transactions.data.length">
                            <td
                                colspan="7"
                                class="p-6 text-center text-muted-foreground"
                            >
                                No transactions found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="mt-4 flex items-center justify-between text-xs text-muted-foreground"
            >
                <span>
                    Showing {{ transactions.data.length }} of
                    {{ transactions.total }} transactions
                </span>
                <div class="flex items-center gap-2">
                    <Link
                        v-if="transactions.prev_page_url"
                        :href="transactions.prev_page_url"
                        aria-label="Previous page"
                        class="rounded-md p-1.5 hover:bg-accent"
                    >
                        <ChevronLeft class="size-4" />
                    </Link>
                    <span
                        class="rounded-md bg-primary px-2.5 py-1 font-medium text-primary-foreground"
                    >
                        {{ transactions.current_page }}
                    </span>
                    <Link
                        v-if="transactions.next_page_url"
                        :href="transactions.next_page_url"
                        aria-label="Next page"
                        class="rounded-md p-1.5 hover:bg-accent"
                    >
                        <ChevronRight class="size-4" />
                    </Link>
                </div>
            </div>
        </div>

        <form class="flex max-w-xl flex-col gap-2" @submit.prevent="parseQuick">
            <Label for="quick">Quick entry</Label>
            <div class="flex gap-2">
                <Input
                    id="quick"
                    v-model="quick.text"
                    placeholder="Jollibee 250"
                />
                <Button :disabled="quick.processing">Fill form</Button>
            </div>
            <InputError :message="quick.errors.text" />
        </form>

        <div class="grid gap-4 xl:grid-cols-2">
            <form
                id="add-expense"
                class="grid scroll-mt-24 gap-4 rounded-xl border bg-card p-6 sm:grid-cols-2"
                @submit.prevent="saveExpense"
            >
                <h3 class="font-medium sm:col-span-2">
                    {{
                        editing
                            ? 'Edit expense'
                            : expense.source === 'quick'
                              ? 'Confirm quick entry'
                              : 'Add expense'
                    }}
                </h3>
                <div class="grid gap-2">
                    <Label for="merchant">Merchant</Label>
                    <Input id="merchant" v-model="expense.merchant" required />
                    <InputError :message="expense.errors.merchant" />
                </div>
                <div class="grid gap-2">
                    <Label for="amount">Amount (PHP)</Label>
                    <Input
                        id="amount"
                        v-model="expense.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        required
                    />
                    <InputError :message="expense.errors.amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="card_id">Card</Label>
                    <SelectNative
                        id="card_id"
                        v-model="expense.card_id"
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
                    <InputError :message="expense.errors.card_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="category_id">Category</Label>
                    <SelectNative
                        id="category_id"
                        v-model="expense.category_id"
                    >
                        <option value="">Uncategorized</option>
                        <option
                            v-for="c in categories"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{ c.name }}
                        </option>
                    </SelectNative>
                    <InputError :message="expense.errors.category_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="occurred_at">Date</Label>
                    <Input
                        id="occurred_at"
                        v-model="expense.occurred_at"
                        type="date"
                        required
                    />
                    <InputError :message="expense.errors.occurred_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="note">Note</Label>
                    <Input id="note" v-model="expense.note" />
                    <InputError :message="expense.errors.note" />
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                    <Button :disabled="expense.processing || !cards.length">
                        Save expense
                    </Button>
                    <Button
                        v-if="editing"
                        type="button"
                        variant="ghost"
                        @click="cancelEdit"
                    >
                        Cancel
                    </Button>
                    <p
                        v-if="!cards.length"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        Add a card first.
                    </p>
                </div>
            </form>

            <form
                id="transfer"
                class="grid scroll-mt-24 content-start gap-4 rounded-xl border bg-card p-6 sm:grid-cols-2"
                @submit.prevent="saveTransfer"
            >
                <h3 class="font-medium sm:col-span-2">Transfer</h3>
                <div class="grid gap-2">
                    <Label for="from">From</Label>
                    <SelectNative id="from" v-model="transfer.from" required>
                        <option value="" disabled>Choose</option>
                        <option
                            v-for="c in cards"
                            :key="`c${c.id}`"
                            :value="`card:${c.id}`"
                        >
                            Card: {{ c.name }}
                        </option>
                        <option
                            v-for="p in pods"
                            :key="`p${p.id}`"
                            :value="`pod:${p.id}`"
                        >
                            Pod: {{ p.name }}
                        </option>
                    </SelectNative>
                    <InputError :message="transfer.errors.from" />
                </div>
                <div class="grid gap-2">
                    <Label for="to">To</Label>
                    <SelectNative id="to" v-model="transfer.to" required>
                        <option value="" disabled>Choose</option>
                        <option
                            v-for="c in cards"
                            :key="`c${c.id}`"
                            :value="`card:${c.id}`"
                        >
                            Card: {{ c.name }}
                        </option>
                        <option
                            v-for="p in pods"
                            :key="`p${p.id}`"
                            :value="`pod:${p.id}`"
                        >
                            Pod: {{ p.name }}
                        </option>
                    </SelectNative>
                    <InputError :message="transfer.errors.to" />
                </div>
                <div class="grid gap-2">
                    <Label for="transfer_amount">Amount (PHP)</Label>
                    <Input
                        id="transfer_amount"
                        v-model="transfer.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        required
                    />
                    <InputError :message="transfer.errors.amount" />
                </div>
                <div class="self-end">
                    <Button :disabled="transfer.processing">Transfer</Button>
                </div>
            </form>
        </div>
    </div>
</template>
