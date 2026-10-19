<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PodController from '@/actions/App/Http/Controllers/PodController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PodCard from '@/components/PodCard.vue';
import PodProjection from '@/components/PodProjection.vue';
import ProofForm from '@/components/ProofForm.vue';
import type { ProofValues } from '@/components/ProofForm.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPeso, toPesos } from '@/lib/currency';
import { index } from '@/routes/pods';

type Pod = {
    id: number;
    name: string;
    target_amount: number;
    saved_amount: number;
    monthly_amount: number;
    target_date: string | null;
    card_id: number | null;
    transfers: { id: number; amount: number; in: boolean; card: string | null; on: string; reference: string | null }[];
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pods', href: index() }] },
});

const props = defineProps<{
    pods: Pod[];
    cards: { id: number; name: string; balance: number }[];
    consented: boolean;
}>();

// One dialog: the pod's details, and the fund / edit / delete panes slide in beside them.
const panel = ref<'info' | 'fund' | 'edit' | 'delete' | null>(null);
const selectedId = ref<number | null>(null);
const selected = computed(() => props.pods.find((p) => p.id === selectedId.value) ?? null);
const cardName = (id: number | null) => props.cards.find((c) => c.id === id)?.name ?? 'Not set';
const today = () => new Date().toISOString().slice(0, 10);

const blank = { name: '', target_amount: '', saved_amount: '0', monthly_amount: '0', target_date: '', card_id: '' };
const editing = ref<number | null>(null);
const form = useForm({ ...blank });
const fundForm = useForm<ProofValues>({ card_id: 0, amount: '', paid_on: today(), reference: '', receipt_id: null });

// Monthly suggestion: what is left to save, spread over the months until the target date (at least one),
// or over a year when no date is set yet. It fills the Monthly field until the user types their own amount there.
const DEFAULT_MONTHS = 12;
const monthlyTouched = ref(false);
// the projection slides in beside the form once there is a target to chart
const showChart = computed(() => Number(form.target_amount) > 0);
const centavos = (v: string) => Math.round((Number(v) || 0) * 100);
const months = computed(() => {
    if (!form.target_date) {
        return DEFAULT_MONTHS;
    }

    const now = new Date();
    const end = new Date(form.target_date + 'T00:00');
    const m = (end.getFullYear() - now.getFullYear()) * 12 + end.getMonth() - now.getMonth() + (end.getDate() > now.getDate() ? 1 : 0);

    return Math.max(1, m);
});
const suggested = computed(() => {
    const left = Number(form.target_amount) - Number(form.saved_amount || 0);

    return left > 0 ? Math.ceil(left / months.value) : null;
});

watch(suggested, (v) => {
    if (v !== null && !monthlyTouched.value) {
        form.monthly_amount = String(v);
    }
});

function openPod(p: Pod) {
    selectedId.value = p.id;
    panel.value = 'info';
}

function add() {
    selectedId.value = null;
    editing.value = null;
    form.defaults({ ...blank, card_id: props.cards[0] ? String(props.cards[0].id) : '' });
    form.reset();
    monthlyTouched.value = false;
    panel.value = 'edit';
}

function edit(p: Pod) {
    selectedId.value = p.id;
    editing.value = p.id;
    form.defaults({
        name: p.name,
        target_amount: toPesos(p.target_amount),
        saved_amount: toPesos(p.saved_amount),
        monthly_amount: toPesos(p.monthly_amount),
        target_date: p.target_date?.slice(0, 10) ?? '',
        card_id: p.card_id ? String(p.card_id) : '',
    });
    form.reset();
    monthlyTouched.value = true; // an existing monthly amount is only replaced when the user asks
    panel.value = 'edit';
}

// Leaving the form goes back to the details when a pod is open, otherwise the dialog closes.
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
        form.put(PodController.update.url(editing.value), options);
    } else {
        form.post(PodController.store.url(), options);
    }
}

function fund(p: Pod) {
    fundForm.defaults({ card_id: p.card_id ?? props.cards[0]?.id ?? 0, amount: '', paid_on: today(), reference: '', receipt_id: null });
    fundForm.reset();
    fundForm.clearErrors();
    panel.value = 'fund';
}

function submitFund() {
    fundForm.post(PodController.fund.url(selectedId.value!), {
        preserveScroll: true,
        onSuccess: () => (panel.value = 'info'),
    });
}

function remove(p: Pod) {
    panel.value = null;
    router.delete(PodController.destroy.url(p.id), { preserveScroll: true });
}

const percent = (p: Pod) => Math.min(100, Math.round((p.saved_amount / p.target_amount) * 100));
const date = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
</script>

<template>
    <Head title="Pods" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading class="mb-0!" title="Pods" description="Savings goals with a target amount." />
            <Button @click="add"><Plus /> Add pod</Button>
        </div>

        <p v-if="!pods.length" class="text-sm text-muted-foreground">No pods yet. Add one to start saving toward a goal.</p>
        <ul v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <PodCard
                v-for="p in pods"
                :key="p.id"
                :name="p.name"
                :saved="p.saved_amount"
                :target="p.target_amount"
                :target-date="p.target_date"
                @select="openPod(p)"
            />
        </ul>

        <Dialog :open="panel !== null" @update:open="(v) => !v && (panel === 'edit' ? cancel() : (panel = null))">
            <DialogContent
                class="max-h-[90vh] gap-0 overflow-x-hidden overflow-y-auto p-0 transition-[max-width] duration-300 md:flex"
                :class="(panel === 'edit' ? showChart : selected && panel !== 'info') ? 'sm:max-w-3xl' : 'sm:max-w-md'"
            >
                <div v-if="selected && panel !== 'edit'" class="relative min-w-0 md:shrink-0" :class="panel === 'info' ? 'md:w-full' : 'md:w-[26rem]'">
                    <div class="grid gap-5 p-6">
                        <DialogHeader class="flex-row items-center gap-3 p-0 pr-8 text-left">
                            <div class="min-w-0 flex-1">
                                <DialogTitle class="truncate">{{ selected.name }}</DialogTitle>
                                <DialogDescription>
                                    {{ selected.target_date ? `Target by ${date(selected.target_date)}` : 'No target date' }}
                                </DialogDescription>
                            </div>
                            <div class="-mt-4 flex shrink-0 gap-0.5 self-start">
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
                            <div class="text-4xl font-semibold tracking-tight">{{ formatPeso(selected.saved_amount) }}</div>
                            <span class="pb-1 text-sm text-muted-foreground">of {{ formatPeso(selected.target_amount) }}</span>
                        </div>
                        <div class="grid gap-1.5">
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-brand" :style="{ width: `${percent(selected)}%` }" />
                            </div>
                            <div class="text-xs text-muted-foreground">{{ percent(selected) }}% saved</div>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">Still to save</dt>
                                <dd class="font-medium">{{ formatPeso(Math.max(0, selected.target_amount - selected.saved_amount)) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Monthly contribution</dt>
                                <dd class="font-medium">{{ formatPeso(selected.monthly_amount) }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-xs text-muted-foreground">Funded from</dt>
                                <dd class="font-medium">{{ cardName(selected.card_id) }}</dd>
                            </div>
                        </dl>

                        <Button class="w-full" :disabled="!cards.length" @click="fund(selected)">Add money</Button>

                        <div class="grid gap-2">
                            <h3 class="text-sm font-medium">Transactions</h3>
                            <p v-if="!selected.transfers.length" class="text-sm text-muted-foreground">
                                Nothing moved yet. Add money to log the first transfer.
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
                                    <tr v-for="t in selected.transfers" :key="t.id">
                                        <td class="py-2 whitespace-nowrap">{{ date(t.on) }}</td>
                                        <td class="py-2">
                                            {{ t.card ?? '—' }}
                                            <span v-if="t.reference" class="block text-xs text-muted-foreground">Ref {{ t.reference }}</span>
                                        </td>
                                        <td class="py-2 text-right font-medium" :class="t.in ? 'text-green-600' : ''">
                                            {{ t.in ? '+' : '-' }}{{ formatPeso(t.amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <Transition name="slide">
                    <div v-if="panel === 'fund' && selected" class="grid min-w-0 flex-1 content-start gap-5 border-t p-6 md:border-t-0 md:border-l">
                        <DialogHeader class="p-0 pr-8 text-left">
                            <DialogTitle>Add money to {{ selected.name }}</DialogTitle>
                            <DialogDescription>
                                Move money from a card into this pod. Add proof of the transfer.
                            </DialogDescription>
                        </DialogHeader>
                        <ProofForm
                            :form="fundForm"
                            :cards="cards"
                            :consented="consented"
                            card-label="Transfer from"
                            submit-label="Add to pod"
                            @submit="submitFund"
                            @back="panel = 'info'"
                        />
                    </div>
                </Transition>

                <Transition name="slide">
                    <div v-if="panel === 'delete' && selected" class="grid min-w-0 flex-1 content-start gap-5 border-t p-6 md:border-t-0 md:border-l">
                        <DialogHeader class="p-0 pr-8 text-left">
                            <DialogTitle>Delete {{ selected.name }}?</DialogTitle>
                            <DialogDescription>
                                The {{ formatPeso(selected.saved_amount) }} saved in it stops counting in your net worth. Transfers you
                                already logged stay in your transactions.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="flex gap-2">
                            <Button variant="destructive" @click="remove(selected)">Delete</Button>
                            <Button variant="ghost" @click="panel = 'info'">Keep it</Button>
                        </div>
                    </div>
                </Transition>

                <Transition name="slide">
                    <div v-if="panel === 'edit'" class="grid min-w-0 flex-1 content-start gap-5 p-6">
                        <DialogHeader class="p-0 pr-8 text-left">
                            <DialogTitle>{{ editing ? 'Edit pod' : 'Add pod' }}</DialogTitle>
                            <DialogDescription>A savings goal, and the card you fund it from.</DialogDescription>
                        </DialogHeader>
                        <form class="grid gap-4" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="form.name" required />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="card_id">Card used for this pod</Label>
                                <SelectNative id="card_id" v-model="form.card_id">
                                    <option value="">Pick when I add money</option>
                                    <option v-for="c in cards" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                                </SelectNative>
                                <InputError :message="form.errors.card_id" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="target_amount">Target (PHP)</Label>
                                    <Input id="target_amount" v-model="form.target_amount" type="number" step="0.01" min="0.01" required />
                                    <InputError :message="form.errors.target_amount" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="saved_amount">Saved so far (PHP)</Label>
                                    <Input id="saved_amount" v-model="form.saved_amount" type="number" step="0.01" min="0" required />
                                    <InputError :message="form.errors.saved_amount" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="monthly_amount">Monthly (PHP)</Label>
                                    <Input
                                        id="monthly_amount"
                                        v-model="form.monthly_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        @input="monthlyTouched = true"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        <template v-if="suggested !== null">
                                            {{ formatPeso(suggested * 100) }} a month for {{ months }}
                                            {{ months === 1 ? 'month' : 'months' }} reaches it.
                                            <template v-if="!form.target_date">Add a target date to change this.</template>
                                            <button
                                                v-if="monthlyTouched && Number(form.monthly_amount) !== suggested"
                                                type="button"
                                                class="font-medium underline"
                                                @click="((form.monthly_amount = String(suggested)), (monthlyTouched = false))"
                                            >
                                                Use this
                                            </button>
                                        </template>
                                        <template v-else>Enter a target to see the monthly amount.</template>
                                    </p>
                                    <InputError :message="form.errors.monthly_amount" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="target_date">Target date</Label>
                                    <Input id="target_date" v-model="form.target_date" type="date" />
                                    <InputError :message="form.errors.target_date" />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button :disabled="form.processing">Save</Button>
                                <Button type="button" variant="ghost" @click="cancel">Cancel</Button>
                            </div>
                        </form>
                    </div>
                </Transition>
                <Transition name="slide">
                    <div v-if="panel === 'edit' && showChart" class="grid min-w-0 content-start gap-4 border-t p-6 md:w-[24rem] md:shrink-0 md:border-t-0 md:border-l">
                        <DialogHeader class="p-0 text-left">
                            <DialogTitle>Projection</DialogTitle>
                            <DialogDescription>What saving this much each month adds up to.</DialogDescription>
                        </DialogHeader>
                        <PodProjection
                            :saved="centavos(form.saved_amount)"
                            :monthly="centavos(form.monthly_amount)"
                            :target="centavos(form.target_amount)"
                            :target-date="form.target_date || null"
                        />
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
