<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import SubscriptionController from '@/actions/App/Http/Controllers/SubscriptionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPeso, toPesos } from '@/lib/currency';
import { index } from '@/routes/subscriptions';

type Subscription = {
    id: number;
    name: string;
    amount: number;
    cadence: string;
    next_due_on: string | null;
    monthly: number;
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Subscriptions', href: index() }] },
});

defineProps<{ subscriptions: Subscription[]; monthly_total: number }>();

const cadences = ['weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];
const blank = { name: '', amount: '', cadence: 'monthly', next_due_on: '' };
const editing = ref<number | null>(null);
const form = useForm({ ...blank });

function edit(s: Subscription) {
    editing.value = s.id;
    form.defaults({
        name: s.name,
        amount: toPesos(s.amount),
        cadence: s.cadence,
        next_due_on: s.next_due_on ?? '',
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
        form.put(SubscriptionController.update.url(editing.value), options);
    } else {
        form.post(SubscriptionController.store.url(), options);
    }
}

function remove(s: Subscription) {
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
    <Head title="Subscriptions" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                class="mb-0!"
                title="Subscriptions"
                description="Recurring charges and what they cost each month."
            />
            <Button as-child>
                <a href="#subscription-form"><Plus /> Add subscription</a>
            </Button>
        </div>

        <div class="rounded-xl border bg-card p-5">
            <div class="text-xs text-muted-foreground">Monthly total</div>
            <div class="mt-3 text-3xl font-semibold">
                {{ formatPeso(monthly_total) }}
            </div>
            <div class="mt-2 text-xs text-brand-deep dark:text-brand-light">
                {{ subscriptions.length }}
                {{
                    subscriptions.length === 1
                        ? 'subscription'
                        : 'subscriptions'
                }}
            </div>
        </div>

        <p v-if="!subscriptions.length" class="text-sm text-muted-foreground">
            No subscriptions yet.
        </p>
        <ul v-else class="divide-y rounded-xl border bg-card text-sm">
            <li
                v-for="s in subscriptions"
                :key="s.id"
                class="flex flex-wrap items-center gap-3 p-4"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-accent font-semibold text-accent-foreground"
                >
                    {{ s.name.charAt(0).toUpperCase() }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ s.name }}</div>
                    <div class="text-xs text-muted-foreground">
                        Next charge {{ dueDate(s.next_due_on) }},
                        {{ s.cadence }}
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
                    <button type="button" @click="remove(s)">Delete</button>
                </div>
            </li>
        </ul>

        <form
            id="subscription-form"
            class="grid max-w-3xl scroll-mt-24 gap-4 rounded-xl border bg-card p-6 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <h3 class="font-medium sm:col-span-2">
                {{ editing ? 'Edit subscription' : 'Add subscription' }}
            </h3>
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
            <div class="grid gap-2">
                <Label for="cadence">Repeats</Label>
                <SelectNative id="cadence" v-model="form.cadence">
                    <option v-for="c in cadences" :key="c" :value="c">
                        {{ c }}
                    </option>
                </SelectNative>
                <InputError :message="form.errors.cadence" />
            </div>
            <div class="grid gap-2">
                <Label for="next_due_on">Next charge</Label>
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
