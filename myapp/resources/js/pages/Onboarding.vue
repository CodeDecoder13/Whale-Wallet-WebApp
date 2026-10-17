<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import OnboardingController from '@/actions/App/Http/Controllers/OnboardingController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import BankPicker from '@/components/BankPicker.vue';
import { Label } from '@/components/ui/label';

defineProps<{ types: string[] }>();

const steps = ['Income', 'Fixed bills', 'First card', 'Savings pod'];
const step = ref(0);
const last = steps.length - 1;

const form = useForm({
    income: '',
    pay_date_1: '15',
    pay_date_2: '30',
    bills: [] as { name: string; amount: string; day: string }[],
    card: { name: '', type: 'ewallet', balance: '0', color: null as string | null },
    pod: { name: '', target_amount: '' },
});

form.transform((d) => ({
    income: d.income,
    pay_dates: [d.pay_date_1, d.pay_date_2].filter(Boolean),
    bills: d.bills,
    card: d.card,
    pod: d.pod,
}));

// Server errors can belong to any step, so list them all instead of guessing.
const errors = computed(() => Object.values(form.errors));

function next() {
    if (step.value < last) {
        step.value++;
    } else {
        form.post(OnboardingController.store.url());
    }
}
</script>

<template>
    <Head title="Set up your budget" />

    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="`Step ${step + 1} of ${steps.length}: ${steps[step]}`"
            description="Amounts are in pesos. You can change everything later."
        />

        <form class="flex flex-col gap-4" @submit.prevent="next">
            <template v-if="step === 0">
                <div class="grid gap-2">
                    <Label for="income">Monthly income (PHP)</Label>
                    <Input
                        id="income"
                        v-model="form.income"
                        type="number"
                        step="0.01"
                        min="0.01"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="pay1">Pay day (day of month)</Label>
                        <Input
                            id="pay1"
                            v-model="form.pay_date_1"
                            type="number"
                            min="1"
                            max="31"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pay2">Second pay day (optional)</Label>
                        <Input
                            id="pay2"
                            v-model="form.pay_date_2"
                            type="number"
                            min="1"
                            max="31"
                        />
                    </div>
                </div>
            </template>

            <template v-else-if="step === 1">
                <p class="text-sm text-muted-foreground">
                    Rent, loans, utilities: anything due every month.
                </p>
                <div
                    v-for="(bill, i) in form.bills"
                    :key="i"
                    class="grid grid-cols-[1fr_7rem_5rem_auto] items-end gap-2"
                >
                    <div class="grid gap-2">
                        <Label :for="`bill-name-${i}`">Name</Label>
                        <Input
                            :id="`bill-name-${i}`"
                            v-model="bill.name"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`bill-amount-${i}`">Amount</Label>
                        <Input
                            :id="`bill-amount-${i}`"
                            v-model="bill.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`bill-day-${i}`">Due day</Label>
                        <Input
                            :id="`bill-day-${i}`"
                            v-model="bill.day"
                            type="number"
                            min="1"
                            max="31"
                            required
                        />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        :aria-label="`Remove bill ${i + 1}`"
                        @click="form.bills.splice(i, 1)"
                    >
                        Remove
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="self-start"
                    @click="form.bills.push({ name: '', amount: '', day: '1' })"
                >
                    Add bill
                </Button>
            </template>

            <template v-else-if="step === 2">
                <div class="grid gap-2">
                    <Label>Pick your bank or wallet</Label>
                    <BankPicker
                        :name="form.card.name"
                        :color="form.card.color"
                        @pick="(b) => Object.assign(form.card, b)"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="card-name">Card name</Label>
                    <Input
                        id="card-name"
                        v-model="form.card.name"
                        placeholder="GCash"
                        @input="form.card.color = null"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="card-type">Type</Label>
                    <SelectNative id="card-type" v-model="form.card.type">
                        <option v-for="t in types" :key="t" :value="t">
                            {{ t }}
                        </option>
                    </SelectNative>
                </div>
                <div class="grid gap-2">
                    <Label for="card-balance">Current balance (PHP)</Label>
                    <Input
                        id="card-balance"
                        v-model="form.card.balance"
                        type="number"
                        step="0.01"
                        required
                    />
                </div>
            </template>

            <template v-else>
                <p class="text-sm text-muted-foreground">
                    Optional. Leave blank to skip.
                </p>
                <div class="grid gap-2">
                    <Label for="pod-name">Pod name</Label>
                    <Input
                        id="pod-name"
                        v-model="form.pod.name"
                        placeholder="Emergency fund"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="pod-target">Target (PHP)</Label>
                    <Input
                        id="pod-target"
                        v-model="form.pod.target_amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :required="!!form.pod.name"
                    />
                </div>
            </template>

            <InputError v-for="e in errors" :key="e" :message="e" />

            <div class="flex gap-2">
                <Button
                    v-if="step > 0"
                    type="button"
                    variant="ghost"
                    @click="step--"
                >
                    Back
                </Button>
                <Button :disabled="form.processing">
                    {{ step === last ? 'Finish' : 'Next' }}
                </Button>
            </div>
        </form>
    </div>
</template>
