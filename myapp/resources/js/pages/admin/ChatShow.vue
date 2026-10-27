<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { ago } from '@/lib/ago';
import { formatPeso } from '@/lib/currency';

defineProps<{
    thread: { id: number; title: string | null; user: { id: number; name: string; email: string } | null };
    messages: { id: number; role: 'user' | 'assistant'; content: string; at: string; draft: { merchant: string | null; amount: number | null } | null }[];
}>();
</script>

<template>
    <Head title="Conversation" />

    <Link href="/admin/chats" class="text-sm text-muted-foreground underline-offset-4 hover:underline">Back to conversations</Link>
    <Heading :title="thread.title || `Chat #${thread.id}`" :description="`${thread.user?.name ?? 'Unknown'}. Read-only.`" class="mt-2" />

    <ol class="mx-auto mt-6 grid max-w-3xl gap-3">
        <li v-for="m in messages" :key="m.id" class="flex flex-col" :class="m.role === 'user' ? 'items-end' : 'items-start'">
            <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap" :class="m.role === 'user' ? 'bg-primary text-primary-foreground' : 'border bg-card'">{{ m.content }}</div>
            <div v-if="m.draft" class="mt-1 rounded-lg bg-muted px-3 py-1 text-xs text-muted-foreground">Draft: {{ m.draft.merchant ?? '?' }}{{ m.draft.amount !== null ? ` · ${formatPeso(m.draft.amount)}` : '' }}</div>
            <span class="mt-1 text-xs text-muted-foreground">{{ m.role === 'user' ? thread.user?.name : 'Whale' }} · {{ ago(m.at) }}</span>
        </li>
        <li v-if="!messages.length" class="text-center text-muted-foreground">No messages.</li>
    </ol>
</template>
