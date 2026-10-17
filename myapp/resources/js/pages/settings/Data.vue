<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Your data', href: '/settings/data' }],
    },
});

function clearChat() {
    if (confirm('Delete your whole Whale Chat history? This cannot be undone.')) {
        router.delete('/settings/data/chat', { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Your data" />

    <h1 class="sr-only">Your data</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Your data"
            description="Download everything Whale Wallet holds about you, or clear your chat history."
        />

        <div class="flex flex-wrap gap-3">
            <Button as-child>
                <a href="/settings/data/export" download>Download my data (JSON)</a>
            </Button>
            <Button variant="outline" @click="clearChat">
                Delete chat history
            </Button>
        </div>
    </div>
</template>
