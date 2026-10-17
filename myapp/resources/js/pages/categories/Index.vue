<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/categories';

type Category = { id: number; name: string; transactions_count: number };

defineProps<{ categories: Category[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Categories', href: index() }] },
});

const editing = ref<number | null>(null);
const form = useForm({ name: '' });

function edit(c: Category) {
    editing.value = c.id;
    form.defaults({ name: c.name });
    form.reset();
}

function cancel() {
    editing.value = null;
    form.defaults({ name: '' });
    form.reset();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: cancel };
    if (editing.value) {
        form.put(CategoryController.update.url(editing.value), options);
    } else {
        form.post(CategoryController.store.url(), options);
    }
}

function remove(c: Category) {
    if (confirm(`Delete ${c.name}? Its transactions become uncategorized.`)) {
        router.delete(CategoryController.destroy.url(c.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Categories" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <Heading
            class="mb-0!"
            title="Categories"
            description="Group your expenses. Deleting one keeps its transactions."
        />

        <ul class="max-w-xl divide-y rounded-xl border bg-card">
            <li
                v-for="c in categories"
                :key="c.id"
                class="flex items-center justify-between gap-3 p-3 text-sm"
            >
                <span>
                    <span class="font-medium">{{ c.name }}</span>
                    <span class="ml-2 text-xs text-muted-foreground">
                        {{ c.transactions_count }} transactions
                    </span>
                </span>
                <span class="flex gap-3 text-xs font-medium">
                    <button type="button" @click="edit(c)">Rename</button>
                    <button type="button" @click="remove(c)">Delete</button>
                </span>
            </li>
            <li
                v-if="!categories.length"
                class="p-3 text-sm text-muted-foreground"
            >
                No categories yet.
            </li>
        </ul>

        <form
            class="grid max-w-xl gap-2 rounded-xl border bg-card p-6"
            @submit.prevent="submit"
        >
            <Label for="name">
                {{ editing ? 'Rename category' : 'Add category' }}
            </Label>
            <div class="flex gap-2">
                <Input id="name" v-model="form.name" required />
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
            <InputError :message="form.errors.name" />
        </form>
    </div>
</template>
