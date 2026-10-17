<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import PodController from '@/actions/App/Http/Controllers/PodController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pods', href: index() }] },
});

defineProps<{ pods: Pod[] }>();

const blank = {
    name: '',
    target_amount: '',
    saved_amount: '0',
    monthly_amount: '0',
    target_date: '',
};
const editing = ref<number | null>(null);
const form = useForm({ ...blank });

function edit(p: Pod) {
    editing.value = p.id;
    form.defaults({
        name: p.name,
        target_amount: toPesos(p.target_amount),
        saved_amount: toPesos(p.saved_amount),
        monthly_amount: toPesos(p.monthly_amount),
        target_date: p.target_date?.slice(0, 10) ?? '',
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
        form.put(PodController.update.url(editing.value), options);
    } else {
        form.post(PodController.store.url(), options);
    }
}

function remove(p: Pod) {
    if (confirm(`Delete ${p.name}?`)) {
        router.delete(PodController.destroy.url(p.id), {
            preserveScroll: true,
        });
    }
}

const progress = (p: Pod) =>
    Math.min(100, Math.round((p.saved_amount / p.target_amount) * 100));
</script>

<template>
    <Head title="Pods" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                class="mb-0!"
                title="Pods"
                description="Savings goals with a target amount."
            />
            <Button as-child>
                <a href="#pod-form"><Plus /> Add pod</a>
            </Button>
        </div>

        <p v-if="!pods.length" class="text-sm text-muted-foreground">
            No pods yet.
        </p>
        <ul class="grid gap-4 md:grid-cols-2">
            <li
                v-for="p in pods"
                :key="p.id"
                class="flex items-center gap-5 rounded-xl border bg-card p-5"
            >
                <div class="relative size-20 shrink-0">
                    <svg viewBox="0 0 36 36" class="size-full -rotate-90">
                        <circle
                            cx="18"
                            cy="18"
                            r="15.9"
                            fill="none"
                            stroke="var(--muted)"
                            stroke-width="3.5"
                        />
                        <circle
                            cx="18"
                            cy="18"
                            r="15.9"
                            fill="none"
                            stroke="var(--color-brand)"
                            stroke-width="3.5"
                            stroke-linecap="round"
                            pathLength="100"
                            :stroke-dasharray="`${progress(p)} 100`"
                        />
                    </svg>
                    <span
                        class="absolute inset-0 flex items-center justify-center text-sm font-semibold"
                    >
                        {{ progress(p) }}%
                    </span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ p.name }}</div>
                    <div class="text-sm text-muted-foreground">
                        {{ formatPeso(p.saved_amount) }} of
                        {{ formatPeso(p.target_amount) }}
                    </div>
                    <div
                        v-if="p.target_date"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Target by {{ p.target_date.slice(0, 10) }}
                    </div>
                    <div
                        class="mt-2 flex gap-3 text-xs font-medium text-brand-deep dark:text-brand-light"
                    >
                        <button type="button" @click="edit(p)">Edit</button>
                        <button type="button" @click="remove(p)">Delete</button>
                    </div>
                </div>
            </li>
        </ul>

        <form
            id="pod-form"
            class="grid max-w-3xl scroll-mt-24 gap-4 rounded-xl border bg-card p-6 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <h3 class="font-medium sm:col-span-2">
                {{ editing ? 'Edit pod' : 'Add pod' }}
            </h3>
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="target_amount">Target (PHP)</Label>
                <Input
                    id="target_amount"
                    v-model="form.target_amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                />
                <InputError :message="form.errors.target_amount" />
            </div>
            <div class="grid gap-2">
                <Label for="saved_amount">Saved so far (PHP)</Label>
                <Input
                    id="saved_amount"
                    v-model="form.saved_amount"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                />
                <InputError :message="form.errors.saved_amount" />
            </div>
            <div class="grid gap-2">
                <Label for="monthly_amount">Monthly contribution (PHP)</Label>
                <Input
                    id="monthly_amount"
                    v-model="form.monthly_amount"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                />
                <InputError :message="form.errors.monthly_amount" />
            </div>
            <div class="grid gap-2">
                <Label for="target_date">Target date (optional)</Label>
                <Input
                    id="target_date"
                    v-model="form.target_date"
                    type="date"
                />
                <InputError :message="form.errors.target_date" />
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
