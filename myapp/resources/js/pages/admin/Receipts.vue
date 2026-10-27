<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminPager from '@/components/AdminPager.vue';
import Heading from '@/components/Heading.vue';
import SelectNative from '@/components/SelectNative.vue';
import { ago } from '@/lib/ago';
import { formatPeso } from '@/lib/currency';

type Row = {
    id: number; status: string; at: string; error: string | null; merchant: string | null; amount: number | null;
    confidence: number | null; has_image: boolean; user: { id: number; name: string; email: string } | null;
};

const props = defineProps<{
    receipts: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { user?: string; status?: string; stuck?: string };
}>();

const pick = (status: string) => router.get('/admin/receipts', { status: status || undefined, user: props.filters.user }, { preserveState: true, replace: true });
const stuck = () => router.get('/admin/receipts', { stuck: 1 }, { replace: true });
const tone = (s: string) => (s === 'failed' ? 'bg-destructive/10 text-destructive' : s === 'confirmed' ? 'bg-emerald-100 text-emerald-800' : 'bg-muted text-muted-foreground');
</script>

<template>
    <Head title="Receipts" />

    <Heading title="Receipts" description="Every scanned receipt. Opening one, or its image, is written to the audit log." />

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <div class="w-48">
            <SelectNative :model-value="filters.status ?? ''" aria-label="Status" @update:model-value="pick($event as string)">
                <option value="">All statuses</option>
                <option v-for="s in ['queued', 'parsing', 'parsed', 'failed', 'confirmed']" :key="s" :value="s" class="capitalize">{{ s }}</option>
            </SelectNative>
        </div>
        <button type="button" class="rounded-full border bg-card px-3 py-1.5 text-sm hover:bg-accent active:scale-[0.97]" @click="stuck">Stuck over 2 minutes</button>
    </div>

    <div class="mt-4 overflow-x-auto rounded-2xl border bg-card shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b text-xs text-muted-foreground">
                <tr><th class="p-3.5">Scanned</th><th class="p-3.5">User</th><th class="p-3.5">Status</th><th class="p-3.5">Read as</th><th class="p-3.5">Image</th><th class="p-3.5"></th></tr>
            </thead>
            <tbody>
                <tr v-for="r in receipts.data" :key="r.id" class="border-b last:border-0 hover:bg-accent/40">
                    <td class="p-3.5 whitespace-nowrap">{{ ago(r.at) }}</td>
                    <td class="p-3.5"><Link v-if="r.user" :href="`/admin/users/${r.user.id}`" class="hover:underline">{{ r.user.name }}</Link></td>
                    <td class="p-3.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="tone(r.status)">{{ r.status }}</span><div v-if="r.error" class="mt-1 max-w-xs truncate text-xs text-muted-foreground">{{ r.error }}</div></td>
                    <td class="p-3.5">{{ r.merchant ?? '-' }}<span v-if="r.amount !== null"> · {{ formatPeso(r.amount) }}</span></td>
                    <td class="p-3.5">{{ r.has_image ? 'kept' : 'deleted' }}</td>
                    <td class="p-3.5 text-right"><Link :href="`/admin/receipts/${r.id}`" class="font-medium underline underline-offset-4">Open</Link></td>
                </tr>
                <tr v-if="!receipts.data.length"><td colspan="6" class="p-6 text-center text-muted-foreground">No receipts.</td></tr>
            </tbody>
        </table>
    </div>

    <AdminPager :links="receipts.links" />
</template>
