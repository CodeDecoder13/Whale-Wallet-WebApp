<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Pencil,
    FileText,
    Plus,
    ScanLine,
    Search,
    Trash2,
    Zap,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import WhaleAside from '@/components/WhaleAside.vue';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import InputError from '@/components/InputError.vue';
import ReceiptsDialog from '@/components/ReceiptsDialog.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPeso, toPesos } from '@/lib/currency';
import { store as categoriesStore } from '@/routes/categories';
import { index, report } from '@/routes/transactions';

type Txn = {
    id: number;
    type: 'expense' | 'income' | 'transfer';
    merchant: string | null;
    amount: number;
    occurred_at: string;
    source: string;
    note: string | null;
    reference: string | null;
    card_id: number;
    to_card_id: number | null;
    to_pod_id: number | null;
    from_pod_id: number | null;
    category_id: number | null;
    card: { name: string };
    recurring_item: { logo: string | null } | null;
    category: { name: string } | null;
};
type Option = { id: number; name: string };

const props = defineProps<{
    transactions: {
        data: Txn[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        q?: string;
        from?: string;
        to?: string;
        card_id?: string;
        category_id?: string;
    };
    cards: (Option & { balance: number; type: string })[];
    pods: Option[];
    categories: Option[];
    draft?: { merchant: string; amount: number; card_id: number | null };
    consented: boolean;
    keepReceipts: boolean;
    receipts: InstanceType<typeof ReceiptsDialog>['$props']['receipts'];
}>();

// a transfer shows where the money went: bank logo to bank logo (a pod on either end shows by its name)
function ends(t: Txn): [string, string] {
    const pod = (id: number | null) => props.pods.find((p) => p.id === id)?.name ?? 'Pod';

    if (t.from_pod_id) {
        return [pod(t.from_pod_id), t.card.name];
    }

    return [
        t.card.name,
        t.to_card_id ? (props.cards.find((c) => c.id === t.to_card_id)?.name ?? 'Card') : pod(t.to_pod_id),
    ];
}

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

// 6 rows on a short screen, 7 on a tall one, so the table fits without scrolling the page
const perPage = window.innerHeight < 900 ? 6 : 7;
const query = () => Object.fromEntries(Object.entries(filter).filter(([, v]) => v !== ''));

function applyFilters() {
    router.get(index().url, { ...query(), per_page: perPage }, { preserveState: true, replace: true });
}

function goto(page: number) {
    router.get(index().url, { ...query(), page, per_page: perPage }, { preserveState: true, preserveScroll: true });
}

// 1 ... 4 5 6 ... 99 (0 stands for the gap)
const pages = computed(() => {
    const { current_page: c, last_page: l } = props.transactions;
    const shown = [...new Set([1, c - 1, c, c + 1, l])].filter((p) => p >= 1 && p <= l).sort((a, b) => a - b);

    return shown.flatMap((p, i) => (i && p - shown[i - 1] > 1 ? [0, p] : [p]));
});

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const longDate = (d: string) => MONTHS[Number(d.slice(5, 7)) - 1] + ' ' + Number(d.slice(8, 10)) + ', ' + d.slice(0, 4);
const pill = 'inline-block rounded-full border border-brand/25 bg-brand/10 px-2.5 py-0.5 text-xs font-medium text-brand';

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

// Everything that adds or changes money opens in a dialog; the ledger stays in view behind it.
const expenseOpen = ref(false);
const transferOpen = ref(false);
const quickOpen = ref(false);
const scanOpen = ref(false);

const quick = useForm({ text: '' });
const expense = useForm({
    merchant: '',
    amount: '',
    card_id: props.cards[0] ? String(props.cards[0].id) : '',
    category_id: '',
    occurred_at: new Date().toISOString().slice(0, 10),
    note: '',
    reference: '',
    source: 'manual',
});
// a new category is saved right away and picked for this expense
const addingCategory = ref(false);
const newCategory = ref('');
const categoryError = ref('');
const typeName: Record<string, string> = { bank: 'Bank', ewallet: 'E-wallet', credit: 'Credit', cash: 'Cash' };

function addCategory() {
    const name = newCategory.value.trim();

    if (!name) {
        return;
    }

    router.post(
        categoriesStore().url,
        { name },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['categories'],
            onSuccess: () => {
                const made = props.categories.find((c) => c.name === name);

                if (made) {
                    expense.category_id = String(made.id);
                }

                addingCategory.value = false;
                newCategory.value = '';
                categoryError.value = '';
            },
            onError: (e) => (categoryError.value = e.name ?? 'Could not add that category.'),
        },
    );
}

const transfer = useForm({ from: '', to: '', amount: '' });
const editing = ref<number | null>(null);

// A parsed quick entry arrives as a draft; the expense dialog opens filled in for the user to confirm.
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
        quickOpen.value = false;
        expenseOpen.value = true;
    },
    { immediate: true },
);

watch(expenseOpen, (isOpen) => {
    if (!isOpen) {
        editing.value = null;
        expense.reset();
        expense.clearErrors();
    }
});
watch(transferOpen, (isOpen) => {
    if (!isOpen) {
        transfer.reset();
        transfer.clearErrors();
    }
});
watch(quickOpen, (isOpen) => {
    if (!isOpen) {
        quick.reset();
        quick.clearErrors();
    }
});

// links from elsewhere open the matching dialog: ?scan=1 (old Receipts page), #add-expense (header button), #transfer (Cards)
function openFromUrl() {
    const url = new URL(window.location.href);

    if (url.searchParams.has('scan')) {
        scanOpen.value = true;
        url.searchParams.delete('scan');
    } else if (url.hash === '#add-expense') {
        openExpense();
    } else if (url.hash === '#transfer') {
        transferOpen.value = true;
    } else {
        return;
    }

    url.hash = '';
    window.history.replaceState(window.history.state, '', url);
}

onMounted(() => {
    if (props.transactions.per_page !== perPage) {
        goto(1);
    }

    openFromUrl();
    window.addEventListener('hashchange', openFromUrl);
});
onBeforeUnmount(() => window.removeEventListener('hashchange', openFromUrl));

const openExpense = () => {
    editing.value = null;
    expenseOpen.value = true;
};

const parseQuick = () =>
    quick.post(TransactionController.quick.url(), { preserveScroll: true });

function edit(t: Txn) {
    editing.value = t.id;
    Object.assign(expense, {
        merchant: t.merchant ?? '',
        amount: toPesos(t.amount),
        card_id: String(t.card_id),
        category_id: t.category_id ? String(t.category_id) : '',
        occurred_at: t.occurred_at.slice(0, 10),
        note: t.note ?? '',
        reference: t.reference ?? '',
        source: t.source,
    });
    expenseOpen.value = true;
}

function remove(t: Txn, done?: () => void) {
    if (confirm(`Delete ${t.merchant}? Its amount returns to the balance.`)) {
        router.delete(TransactionController.destroy.url(t.id), {
            preserveScroll: true,
            onSuccess: done,
        });
    }
}

// the delete button lives inside the edit dialog
function removeEditing() {
    const t = props.transactions.data.find((x) => x.id === editing.value);

    if (t) {
        remove(t, () => (expenseOpen.value = false));
    }
}

function saveExpense() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            expenseOpen.value = false;
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
        onSuccess: () => {
            transferOpen.value = false;
        },
    });

const sign = { expense: '-', income: '+', transfer: '' };
</script>

<template>
    <Head title="Transactions" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <div class="flex items-start justify-between gap-3">
            <header class="space-y-0.5">
                <h2 class="flex items-center gap-3 text-2xl font-semibold tracking-tight">
                    Transactions
                    <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-medium text-brand">
                        {{ transactions.total }} total
                    </span>
                </h2>
                <p class="text-sm text-muted-foreground">
                    Every expense is charged to a card. Transfers are not spending.
                </p>
            </header>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
            <Button @click="openExpense">
                <Plus class="size-4" />
                Add expense
            </Button>
            <Button variant="outline" @click="transferOpen = true">
                <ArrowLeftRight class="size-4" />
                Transfer
            </Button>
            <Button variant="outline" @click="quickOpen = true">
                <Zap class="size-4" />
                Quick entry
            </Button>
            <Button variant="outline" @click="scanOpen = true">
                <ScanLine class="size-4" />
                Scan receipt
            </Button>
            <Button variant="outline" as-child>
                <a :href="report.url({ query: query() })" target="_blank" rel="noopener">
                    <FileText class="size-4" />
                    View transactions
                </a>
            </Button>
        </div>

        <ReceiptsDialog
            v-model:open="scanOpen"
            :consented="consented"
            :keep-receipts="keepReceipts"
            :receipts="receipts"
            :cards="cards"
            :categories="categories"
            @manual="openExpense"
        />

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

            <div class="mt-4 overflow-x-auto rounded-xl border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs font-semibold text-foreground">
                        <tr>
                            <th class="p-3.5">Transaction</th>
                            <th class="p-3.5">Category</th>
                            <th class="p-3.5">Date</th>
                            <th class="p-3.5">Card</th>
                            <th class="p-3.5">Source</th>
                            <th class="p-3.5 text-right">Amount</th>
                            <th class="p-3.5 text-center">Tools</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="t in transactions.data"
                            :key="t.id"
                            class="border-b transition-colors last:border-0 hover:bg-accent/40"
                        >
                            <td class="p-3.5">
                                <div class="flex items-center gap-3">
                                    <span
                                        v-if="t.type === 'transfer'"
                                        class="flex shrink-0 items-center gap-1"
                                    >
                                        <BrandLogo :name="ends(t)[0]" class="!size-9" />
                                        <ArrowRight class="size-3.5 text-muted-foreground" aria-hidden="true" />
                                        <BrandLogo :name="ends(t)[1]" class="!size-9" />
                                    </span>
                                    <BrandLogo
                                        v-else
                                        :name="t.merchant ?? '?'"
                                        :logo="t.recurring_item?.logo"
                                        class="!size-9"
                                    />
                                    <div>
                                        <span class="font-semibold">
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
                                        <div
                                            v-if="t.reference"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Ref {{ t.reference }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3.5">
                                <span v-if="t.category" :class="pill">
                                    {{ t.category.name }}
                                </span>
                                <span v-else class="text-muted-foreground">
                                    -
                                </span>
                            </td>
                            <td class="p-3.5 whitespace-nowrap">
                                {{ longDate(t.occurred_at) }}
                            </td>
                            <td class="p-3.5">
                                {{ t.card.name }}
                            </td>
                            <td class="p-3.5 whitespace-nowrap">
                                <span :class="[pill, 'capitalize']">{{ t.source }}</span>
                            </td>
                            <td
                                class="p-3.5 text-right font-semibold whitespace-nowrap"
                            >
                                {{ sign[t.type] }}{{ formatPeso(t.amount) }}
                            </td>
                            <td class="p-3.5 text-center whitespace-nowrap">
                                <button
                                    v-if="t.type === 'expense'"
                                    type="button"
                                    aria-label="Edit"
                                    class="inline-grid size-8 place-items-center rounded-lg border hover:bg-accent"
                                    @click="edit(t)"
                                >
                                    <Pencil class="size-4" />
                                </button>
                                <!-- a transfer has no edit dialog, so it keeps a delete of its own -->
                                <button
                                    v-else
                                    type="button"
                                    aria-label="Delete"
                                    class="inline-grid size-8 place-items-center rounded-lg border hover:bg-accent"
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
                class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
            >
                <nav class="flex items-center gap-1" aria-label="Pages">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 hover:bg-accent disabled:pointer-events-none disabled:opacity-40"
                        :disabled="transactions.current_page <= 1"
                        @click="goto(transactions.current_page - 1)"
                    >
                        <ChevronLeft class="size-4" /> Previous
                    </button>
                    <template v-for="(p, i) in pages" :key="i">
                        <span v-if="p === 0" class="px-1">...</span>
                        <button
                            v-else
                            type="button"
                            class="grid size-8 place-items-center rounded-lg font-medium hover:bg-accent"
                            :class="p === transactions.current_page && 'bg-accent text-brand'"
                            :aria-current="p === transactions.current_page ? 'page' : undefined"
                            @click="goto(p)"
                        >
                            {{ p }}
                        </button>
                    </template>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 hover:bg-accent disabled:pointer-events-none disabled:opacity-40"
                        :disabled="transactions.current_page >= transactions.last_page"
                        @click="goto(transactions.current_page + 1)"
                    >
                        Next <ChevronRight class="size-4" />
                    </button>
                </nav>
                <span>
                    Showing {{ transactions.data.length }} of
                    {{ transactions.total }} results
                </span>
            </div>
        </div>

        <Dialog v-model:open="expenseOpen">
            <DialogContent class="max-h-[90svh] gap-0 overflow-hidden p-0 sm:max-w-4xl md:flex">
                <WhaleAside
                    title="Log it before you forget."
                    body="Pick the card or e-wallet it came from. Whale Wallet updates that balance right away."
                />
                <div class="no-scrollbar grid max-h-[90svh] min-w-0 flex-1 content-start gap-5 overflow-y-auto p-6">
                <DialogHeader class="p-0 pr-8 text-left">
                    <DialogTitle>
                        {{
                            editing
                                ? 'Edit expense'
                                : expense.source === 'quick'
                                  ? 'Confirm quick entry'
                                  : 'Add expense'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            editing
                                ? 'Changing the amount or card updates both balances.'
                                : 'Charged to the card you pick below.'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="saveExpense"
                >
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="category_id">Type of expense</Label>
                        <div v-if="!addingCategory" class="flex gap-2">
                            <SelectNative id="category_id" v-model="expense.category_id" class="flex-1">
                                <option value="">Uncategorized</option>
                                <option v-for="c in categories" :key="c.id" :value="String(c.id)">
                                    {{ c.name }}
                                </option>
                            </SelectNative>
                            <Button type="button" variant="outline" class="shrink-0" @click="addingCategory = true">
                                <Plus class="size-4" /> Add type
                            </Button>
                        </div>
                        <form v-else class="flex gap-2" @submit.prevent="addCategory">
                            <Input
                                v-model="newCategory"
                                class="flex-1"
                                maxlength="60"
                                placeholder="New type, e.g. Food"
                                aria-label="New type of expense"
                                autofocus
                            />
                            <Button :disabled="!newCategory.trim()">Add</Button>
                            <Button type="button" variant="ghost" @click="((addingCategory = false), (categoryError = ''))">Cancel</Button>
                        </form>
                        <InputError :message="categoryError || expense.errors.category_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="merchant">Name of expense</Label>
                        <Input
                            id="merchant"
                            v-model="expense.merchant"
                            placeholder="e.g. Chicken meal, Meralco bill"
                            required
                        />
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
                    <div class="grid gap-2 sm:col-span-2">
                        <Label id="card-label">Card or e-wallet</Label>
                        <!-- up to four cards read best as tiles; more than that is a dropdown -->
                        <SelectNative v-if="cards.length > 4" id="card_id" v-model="expense.card_id" required aria-labelledby="card-label">
                            <option v-for="c in cards" :key="c.id" :value="String(c.id)">
                                {{ c.name }} · {{ typeName[c.type] ?? c.type }} · {{ formatPeso(c.balance) }}
                            </option>
                        </SelectNative>
                        <div v-else role="radiogroup" aria-labelledby="card-label" class="grid grid-cols-2 gap-2">
                            <button
                                v-for="c in cards"
                                :key="c.id"
                                type="button"
                                role="radio"
                                :aria-checked="expense.card_id === String(c.id)"
                                class="flex items-center gap-2.5 rounded-xl border bg-card px-2.5 py-2 text-left text-sm transition hover:bg-accent active:scale-[0.98]"
                                :class="expense.card_id === String(c.id) && 'border-transparent ring-2 ring-primary'"
                                @click="expense.card_id = String(c.id)"
                            >
                                <BrandLogo :name="c.name" class="!size-8" />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ c.name }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">
                                        {{ typeName[c.type] ?? c.type }} · {{ formatPeso(c.balance) }}
                                    </span>
                                </span>
                            </button>
                        </div>
                        <InputError :message="expense.errors.card_id" />
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
                    <div class="grid gap-2">
                        <Label for="reference">Reference number (optional)</Label>
                        <Input id="reference" v-model="expense.reference" maxlength="80" />
                        <InputError :message="expense.errors.reference" />
                    </div>
                    <DialogFooter class="sm:col-span-2">
                        <p
                            v-if="!cards.length"
                            class="self-center text-sm text-muted-foreground sm:mr-auto"
                        >
                            Add a card first.
                        </p>
                        <Button
                            v-if="editing"
                            type="button"
                            variant="destructive"
                            class="sm:mr-auto"
                            @click="removeEditing"
                        >
                            <Trash2 class="size-4" />
                            Delete
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="expenseOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button :disabled="expense.processing || !cards.length">
                            {{ editing ? 'Save changes' : 'Save expense' }}
                        </Button>
                    </DialogFooter>
                </form>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="transferOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Transfer</DialogTitle>
                    <DialogDescription>
                        Move money between your cards and pods. It is not
                        counted as spending.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="saveTransfer"
                >
                    <div class="grid gap-2">
                        <Label for="from">From</Label>
                        <SelectNative
                            id="from"
                            v-model="transfer.from"
                            required
                        >
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
                    <div class="grid gap-2 sm:col-span-2">
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
                    <DialogFooter class="sm:col-span-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="transferOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button :disabled="transfer.processing">
                            Transfer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="quickOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Quick entry</DialogTitle>
                    <DialogDescription>
                        Type what you spent and where, like "Jollibee 250". Add
                        a card name at the end to pick it. You check it before
                        anything is saved.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="parseQuick">
                    <div class="grid gap-2">
                        <Label for="quick">What did you spend?</Label>
                        <Input
                            id="quick"
                            v-model="quick.text"
                            placeholder="Jollibee 250 gcash"
                            autofocus
                            required
                        />
                        <InputError :message="quick.errors.text" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="quickOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button :disabled="quick.processing">Continue</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
