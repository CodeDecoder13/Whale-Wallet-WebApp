<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminPager from '@/components/AdminPager.vue';
import Heading from '@/components/Heading.vue';
import SelectNative from '@/components/SelectNative.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPeso } from '@/lib/currency';

type Row = {
    id: number; date: string; type: string; source: string; merchant: string | null; amount: number; reference: string | null;
    note: string | null; category: string | null; card: string | null; user: { id: number; name: string; email: string } | null;
};
type F = { q?: string; user?: string; type?: string; source?: string; category?: string; from?: string; to?: string; min?: string; max?: string };

const props = defineProps<{
    rows: { data: Row[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    totals: Record<string, { count: number; total: number }>;
    filters: F;
    types: string[];
    sources: string[];
    owner: { id: number; name: string } | null;
}>();

const f = reactive<F>({ ...props.filters });
const clean = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v));
const apply = () => router.get('/admin/transactions', clean(), { preserveState: true, replace: true });
const clear = () => router.get('/admin/transactions', {}, { replace: true });
const exportUrl = () => `/admin/transactions/export?${new URLSearchParams(clean() as Record<string, string>)}`;
const sign: Record<string, string> = { expense: '-', income: '+', transfer: '' };
</script>

<template>
    <Head title="Transactions" />

    <Heading
        :title="`Transactions (${rows.total})`"
        :description="owner ? `Only ${owner.name}'s transactions.` : 'Every expense, income and transfer, newest first. Opening this page is written to the audit log.'"
    />

    <form class="mt-4 grid gap-3 rounded-2xl border bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="apply">
        <Input v-model="f.q" type="search" placeholder="Merchant, note or reference" aria-label="Search" />
        <Input v-model="f.user" type="number" min="1" placeholder="User id" aria-label="User id" />
        <SelectNative v-model="f.type" aria-label="Type"><option value="">All types</option><option v-for="t in types" :key="t" :value="t" class="capitalize">{{ t }}</option></SelectNative>
        <SelectNative v-model="f.source" aria-label="Source"><option value="">All sources</option><option v-for="s in sources" :key="s" :value="s" class="capitalize">{{ s }}</option></SelectNative>
        <Input v-model="f.category" placeholder="Category name" aria-label="Category" />
        <Input v-model="f.from" type="date" aria-label="From" />
        <Input v-model="f.to" type="date" aria-label="To" />
        <div class="flex gap-2">
            <Input v-model="f.min" type="number" min="0" step="0.01" placeholder="Min ₱" aria-label="Minimum amount" />
            <Input v-model="f.max" type="number" min="0" step="0.01" placeholder="Max ₱" aria-label="Maximum amount" />
        </div>
        <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4">
            <Button>Filter</Button>
            <Button type="button" variant="ghost" @click="clear">Clear</Button>
            <Button as-child variant="outline"><a :href="exportUrl()">Export CSV</a></Button>
        </div>
    </form>

    <dl class="mt-4 flex flex-wrap gap-3 text-sm">
        <div v-for="(t, k) in totals" :key="k" class="rounded-xl border bg-card px-4 py-2 shadow-sm">
            <dt class="text-xs text-muted-foreground capitalize">{{ k }} ({{ t.count }})</dt>
            <dd class="font-semibold">{{ formatPeso(t.total) }}</dd>
        </div>
    </dl>

    <div class="mt-4 overflow-x-auto rounded-2xl border bg-card shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b text-xs text-muted-foreground">
                <tr>
                    <th class="p-3.5">Date</th><th class="p-3.5">User</th><th class="p-3.5">Merchant</th><th class="p-3.5">Category</th>
                    <th class="p-3.5">Card</th><th class="p-3.5">Source</th><th class="p-3.5 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in rows.data" :key="r.id" class="border-b last:border-0 hover:bg-accent/40">
                    <td class="p-3.5 whitespace-nowrap">{{ r.date }}</td>
                    <td class="p-3.5"><Link v-if="r.user" :href="`/admin/users/${r.user.id}`" class="hover:underline">{{ r.user.name }}</Link></td>
                    <td class="p-3.5">
                        <span class="font-semibold">{{ r.merchant ?? '-' }}</span>
                        <span v-if="r.type === 'transfer'" class="ml-1 rounded-full bg-muted px-2 py-0.5 text-xs">transfer</span>
                        <div v-if="r.reference" class="text-xs text-muted-foreground">Ref {{ r.reference }}</div>
                        <div v-if="r.note" class="max-w-xs truncate text-xs text-muted-foreground">{{ r.note }}</div>
                    </td>
                    <td class="p-3.5">{{ r.category ?? '-' }}</td>
                    <td class="p-3.5">{{ r.card ?? '-' }}</td>
                    <td class="p-3.5 capitalize">{{ r.source }}</td>
                    <td class="p-3.5 text-right font-semibold whitespace-nowrap">{{ sign[r.type] }}{{ formatPeso(r.amount) }}</td>
                </tr>
                <tr v-if="!rows.data.length"><td colspan="7" class="p-6 text-center text-muted-foreground">No transactions match.</td></tr>
            </tbody>
        </table>
    </div>

    <AdminPager :links="rows.links" />
</template>
