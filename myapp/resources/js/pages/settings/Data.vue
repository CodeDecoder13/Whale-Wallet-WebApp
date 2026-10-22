<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Your data', href: '/settings/data' }],
    },
});

const upload = useForm<{ file: File | null }>({ file: null });

function importFile() {
    upload.post('/settings/data/import', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => upload.reset(),
    });
}

function clearChat() {
    if (confirm('Delete your whole Whale Chat history? This cannot be undone.')) {
        router.delete('/settings/data/chat', { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Your data" />

    <h1 class="sr-only">Your data</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            title="Your data"
            description="Download everything Whale Wallet holds about you, load a download into this or another account, or clear your chat history."
        />

        <div class="flex flex-wrap gap-3">
            <Button as-child>
                <a href="/settings/data/export" download>Download my data (JSON)</a>
            </Button>
            <Button variant="outline" @click="clearChat">
                Delete chat history
            </Button>
        </div>

        <form class="grid max-w-md gap-3" @submit.prevent="importFile">
            <Heading
                variant="small"
                title="Import data"
                description="Choose a file you downloaded from Whale Wallet. Your cards, bills, subscriptions, loans, savings pods, categories, transactions and income are added to this account. Things that are already here are skipped, so importing the same file twice is safe. Receipt images, chat history and tips are not included."
            />
            <div class="grid gap-2">
                <Label for="import-file">Data file (.json)</Label>
                <Input
                    id="import-file"
                    type="file"
                    accept="application/json,.json"
                    required
                    @input="upload.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                />
                <InputError :message="upload.errors.file" />
            </div>
            <div>
                <Button :disabled="upload.processing || !upload.file">Import</Button>
            </div>
        </form>
    </div>
</template>
