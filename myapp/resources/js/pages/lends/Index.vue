<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import LendController from '@/actions/App/Http/Controllers/LendController';
import Heading from '@/components/Heading.vue';
import HelpTip from '@/components/HelpTip.vue';
import InputError from '@/components/InputError.vue';
import ProofForm from '@/components/ProofForm.vue';
import type { ProofValues } from '@/components/ProofForm.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPeso } from '@/lib/currency';
import { dueTone } from '@/lib/dueTone';
import { index } from '@/routes/lends';

type Lend = {
    id: number;
    name: string;
    email: string;
    amount: number;
    reference: string | null;
    remind_before: number;
    card_id: number | null;
    lent_on: string;
    due_on: string;
    paid: boolean;
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Who Owes You', href: index() }] },
});

const props = defineProps<{
    lends: Lend[];
    cards: { id: number; name: string; balance: number }[];
    consented: boolean;
}>();

const panel = ref<'who' | 'proof' | 'paid' | 'delete' | null>(null);
const picked = ref<Lend | null>(null);
const today = () => new Date().toISOString().slice(0, 10);
const date = (d: string) => new Date(`${d}T00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
const owed = computed(() => props.lends.filter((l) => !l.paid).reduce((n, l) => n + l.amount, 0));

// step 1 is who and when (the agreement), step 2 is the card and the proof the money went out
const blank = () => ({ name: '', email: '', due_on: '', remind_before: '3', card_id: props.cards[0]?.id ?? 0, amount: '', paid_on: today(), reference: '', receipt_id: null as number | null });
const form = useForm(blank());
const paidForm = useForm({ card_id: 0 });

function add() {
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    panel.value = 'who';
}

function submit() {
    form.post(LendController.store.url(), { preserveScroll: true, onSuccess: () => (panel.value = null) });
}

function paid(l: Lend) {
    picked.value = l;
    paidForm.card_id = l.card_id ?? props.cards[0]?.id ?? 0;
    panel.value = 'paid';
}

function savePaid() {
    paidForm.post(LendController.paid.url(picked.value!.id), { preserveScroll: true, onSuccess: () => (panel.value = null) });
}

function remove(l: Lend) {
    picked.value = l;
    panel.value = 'delete';
}

const proofErrors = computed(() => ['name', 'email', 'due_on', 'remind_before'].map((k) => (form.errors as Record<string, string>)[k]).filter(Boolean));
</script>

<template>
    <Head title="Who Owes You" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading class="mb-0!" title="Who Owes You" description="Cash you lent to people, and when they will pay you back." />
            <Button :disabled="!cards.length" @click="add"><Plus /> Lend cash</Button>
        </div>

        <div class="rounded-2xl border bg-card p-5 shadow-sm">
            <div class="flex items-center gap-1.5 text-sm text-muted-foreground">
                Still owed to you
                <HelpTip text="Cash you lent that has not been paid back yet. It counts as an asset in your net worth until it is paid." />
            </div>
            <div class="mt-1 text-3xl font-semibold tracking-tight">{{ formatPeso(owed) }}</div>
        </div>

        <p v-if="!lends.length" class="text-sm text-muted-foreground">
            Nobody owes you yet. Tap Lend cash to record money you sent, and we will email them the details and remind them when it is due.
        </p>
        <ul v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <li v-for="l in lends" :key="l.id" class="flex flex-col gap-3 rounded-2xl border bg-card p-5 shadow-sm" :class="l.paid && 'opacity-60'">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ l.name }}</div>
                        <div class="truncate text-xs text-muted-foreground">{{ l.email }}</div>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :style="{ color: dueTone(l.due_on, l.paid).c, background: `color-mix(in srgb, ${dueTone(l.due_on, l.paid).c} 15%, transparent)` }"
                    >
                        {{ l.paid ? 'Paid back' : dueTone(l.due_on).text }}
                    </span>
                </div>
                <div class="text-2xl font-semibold">{{ formatPeso(l.amount) }}</div>
                <dl class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <dt class="text-muted-foreground">Lent on</dt>
                        <dd class="font-medium">{{ date(l.lent_on) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Pay back by</dt>
                        <dd class="font-medium">{{ date(l.due_on) }}</dd>
                    </div>
                    <div v-if="l.reference" class="col-span-2">
                        <dt class="text-muted-foreground">Reference</dt>
                        <dd class="font-medium">{{ l.reference }}</dd>
                    </div>
                </dl>
                <div class="mt-auto flex gap-2">
                    <Button v-if="!l.paid" size="sm" class="flex-1" @click="paid(l)"><Check /> Paid back</Button>
                    <Button variant="ghost" size="icon" class="size-8 text-destructive" aria-label="Delete" title="Delete" @click="remove(l)">
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>

        <Dialog :open="panel !== null" @update:open="(v) => !v && (panel = null)">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <template v-if="panel === 'who'">
                    <DialogHeader class="text-left">
                        <DialogTitle>Who did you lend to?</DialogTitle>
                        <DialogDescription>
                            We email them the details now, and remind them by email on the days you pick. Only add someone who knows about this loan.
                        </DialogDescription>
                    </DialogHeader>
                    <form class="grid gap-4" @submit.prevent="panel = 'proof'">
                        <div class="grid gap-2">
                            <Label for="lend-name">Name</Label>
                            <Input id="lend-name" v-model="form.name" maxlength="80" required />
                        </div>
                        <div class="grid gap-2">
                            <Label for="lend-email">Their email</Label>
                            <Input id="lend-email" v-model="form.email" type="email" maxlength="120" required />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="lend-due">Pay back by</Label>
                                <Input id="lend-due" v-model="form.due_on" type="date" :min="today()" required />
                            </div>
                            <div class="grid gap-2">
                                <Label for="lend-remind">Remind them</Label>
                                <SelectNative id="lend-remind" v-model="form.remind_before">
                                    <option value="0">On the due date</option>
                                    <option value="1">1 day before</option>
                                    <option value="3">3 days before</option>
                                    <option value="7">7 days before</option>
                                    <option value="14">14 days before</option>
                                </SelectNative>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">They also get a reminder on the due date, and every week after it if they have not paid.</p>
                        <Button>Next: card and proof</Button>
                    </form>
                </template>

                <template v-else-if="panel === 'proof'">
                    <DialogHeader class="text-left">
                        <DialogTitle>How did you send {{ form.name }} the cash?</DialogTitle>
                        <DialogDescription>Pick the card the money left from and add proof of the transfer.</DialogDescription>
                    </DialogHeader>
                    <InputError v-for="e in proofErrors" :key="e" :message="e" />
                    <ProofForm
                        :form="form as never"
                        :cards="cards"
                        :consented="consented"
                        card-label="Sent from"
                        submit-label="Save and email them"
                        @submit="submit"
                        @back="panel = 'who'"
                    />
                </template>

                <template v-else-if="panel === 'paid' && picked">
                    <DialogHeader class="text-left">
                        <DialogTitle>{{ picked.name }} paid you back?</DialogTitle>
                        <DialogDescription>{{ formatPeso(picked.amount) }} goes onto the card you pick, and the reminders stop.</DialogDescription>
                    </DialogHeader>
                    <form class="grid gap-4" @submit.prevent="savePaid">
                        <div class="grid gap-2">
                            <Label for="paid-card">Received on</Label>
                            <SelectNative id="paid-card" v-model="paidForm.card_id" required>
                                <option v-for="c in cards" :key="c.id" :value="c.id">{{ c.name }} ({{ formatPeso(c.balance) }})</option>
                            </SelectNative>
                            <InputError :message="paidForm.errors.card_id" />
                        </div>
                        <div class="flex gap-2">
                            <Button :disabled="paidForm.processing">Mark as paid back</Button>
                            <Button type="button" variant="ghost" @click="panel = null">Cancel</Button>
                        </div>
                    </form>
                </template>

                <template v-else-if="panel === 'delete' && picked">
                    <DialogHeader class="text-left">
                        <DialogTitle>Delete {{ picked.name }}?</DialogTitle>
                        <DialogDescription>
                            The reminder emails stop. The {{ formatPeso(picked.amount) }} stays out of your card. Delete the transaction too if you want it
                            back.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex gap-2">
                        <Button
                            variant="destructive"
                            @click="router.delete(LendController.destroy.url(picked.id), { preserveScroll: true, onSuccess: () => (panel = null) })"
                        >
                            Delete
                        </Button>
                        <Button variant="ghost" @click="panel = null">Keep it</Button>
                    </div>
                </template>
            </DialogContent>
        </Dialog>
    </div>
</template>
