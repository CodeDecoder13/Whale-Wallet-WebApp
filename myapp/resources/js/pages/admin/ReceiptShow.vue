<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { formatPeso } from '@/lib/currency';

defineProps<{
    receipt: {
        id: number; status: string; at: string; error: string | null; confidence: number | null; claimed: number | null;
        draft: Record<string, unknown> | null; corrections: Record<string, unknown> | null; has_image: boolean;
        user: { id: number; name: string; email: string } | null;
    };
    transactions: { id: number; merchant: string | null; amount: number; occurred_at: string; type: string }[];
}>();

const show = (v: unknown) => (typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v));
</script>

<template>
    <Head title="Receipt" />

    <Link href="/admin/receipts" class="text-sm text-muted-foreground underline-offset-4 hover:underline">Back to receipts</Link>
    <Heading :title="`Receipt #${receipt.id}`" :description="`${receipt.user?.name ?? 'Unknown'} · ${receipt.status}`" class="mt-2" />

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border bg-card p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold">Image</h2>
            <img v-if="receipt.has_image" :src="`/admin/receipts/${receipt.id}/image`" alt="Scanned receipt" class="max-h-[70svh] w-full rounded-lg object-contain" />
            <p v-else class="text-sm text-muted-foreground">The image was deleted after it was confirmed.</p>
        </section>

        <div class="grid gap-6">
            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <h2 class="text-sm font-semibold">What the AI read</h2>
                <dl v-if="receipt.draft" class="mt-3 grid grid-cols-[8rem_1fr] gap-x-4 gap-y-1.5 text-sm">
                    <template v-for="(v, k) in receipt.draft" :key="k">
                        <dt class="text-muted-foreground">{{ k }}</dt><dd class="break-words">{{ k === 'amount' && typeof v === 'number' ? formatPeso(v) : show(v) }}</dd>
                    </template>
                </dl>
                <p v-else class="mt-3 text-sm text-muted-foreground">Nothing read yet.</p>
                <p v-if="receipt.error" class="mt-3 text-sm text-destructive">{{ receipt.error }}</p>
                <p v-if="receipt.claimed" class="mt-3 text-sm text-muted-foreground">User typed {{ formatPeso(receipt.claimed) }}.</p>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <h2 class="text-sm font-semibold">Became these transactions</h2>
                <ul class="mt-3 grid gap-1.5 text-sm">
                    <li v-for="t in transactions" :key="t.id" class="flex justify-between"><span>{{ t.merchant }} · {{ t.occurred_at.slice(0, 10) }}</span><span class="font-medium">{{ formatPeso(t.amount) }}</span></li>
                    <li v-if="!transactions.length" class="text-muted-foreground">None. It was not confirmed.</li>
                </ul>
            </section>
        </div>
    </div>
</template>
