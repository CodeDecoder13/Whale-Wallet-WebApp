<script setup lang="ts">
import { adminBase } from '@/lib/adminUrl';
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { formatPeso } from '@/lib/currency';

type Row = {
    id: number; kind: string; name: string; amount: number; cadence: string; total: number | null; remaining: number | null;
    left: number | null; next_due_on: string | null; overdue: boolean; status: string; user: { id: number; name: string; email: string } | null;
};

const props = defineProps<{
    kind: string;
    items: { data: Row[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    totals: { loans: number; owed: number; overdue_loans: number; subscriptions: number; subs_monthly: number };
    popular: { name: string; users: number }[];
}>();

const tabs = [{ value: 'debt', label: 'Loans' }, { value: 'subscription', label: 'Subscriptions' }, { value: 'bill', label: 'Bills' }];
const loans = () => props.kind === 'debt';
</script>

<template>
    <Head title="Loans and subscriptions" />

    <Heading title="Loans and subscriptions" description="Every user's loans, subscriptions and bills." />

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border bg-card p-4 shadow-sm"><div class="text-xs text-muted-foreground">Active loans</div><div class="mt-1 text-2xl font-semibold">{{ totals.loans }}</div></div>
        <div class="rounded-2xl border bg-card p-4 shadow-sm"><div class="text-xs text-muted-foreground">Owed on loans</div><div class="mt-1 text-2xl font-semibold">{{ formatPeso(totals.owed) }}</div></div>
        <div class="rounded-2xl border bg-card p-4 shadow-sm"><div class="text-xs text-muted-foreground">Late loan payments</div><div class="mt-1 text-2xl font-semibold" :class="totals.overdue_loans > 0 && 'text-destructive'">{{ totals.overdue_loans }}</div></div>
        <div class="rounded-2xl border bg-card p-4 shadow-sm"><div class="text-xs text-muted-foreground">Active subscriptions</div><div class="mt-1 text-2xl font-semibold">{{ totals.subscriptions }}</div></div>
        <div class="rounded-2xl border bg-card p-4 shadow-sm"><div class="text-xs text-muted-foreground">Subscriptions per month</div><div class="mt-1 text-2xl font-semibold">{{ formatPeso(totals.subs_monthly) }}</div></div>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        <Link
            v-for="t in tabs"
            :key="t.value"
            :href="`${adminBase()}/loans?kind=${t.value}`"
            class="rounded-full border px-4 py-1.5 text-sm font-medium transition-colors duration-150 ease-out"
            :class="kind === t.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:bg-accent'"
        >
            {{ t.label }}
        </Link>
    </div>

    <div class="mt-4 grid items-start gap-6 xl:grid-cols-[1fr_18rem]">
        <div class="overflow-x-auto rounded-2xl border bg-card shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-xs text-muted-foreground">
                    <tr>
                        <th class="p-3.5">Name</th><th class="p-3.5">Owner</th><th class="p-3.5 text-right">{{ loans() ? 'Payment' : 'Amount' }}</th>
                        <template v-if="loans()"><th class="p-3.5 text-right">Total</th><th class="p-3.5 text-right">Remaining</th><th class="p-3.5">Left</th></template>
                        <th v-else class="p-3.5">Repeats</th>
                        <th class="p-3.5">Next due</th><th class="p-3.5">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in items.data" :key="r.id" class="border-b last:border-0 hover:bg-accent/40">
                        <td class="p-3.5 font-semibold">{{ r.name }}</td>
                        <td class="p-3.5"><Link v-if="r.user" :href="`${adminBase()}/users/${r.user.id}`" class="hover:underline">{{ r.user.name }}</Link></td>
                        <td class="p-3.5 text-right">{{ formatPeso(r.amount) }}</td>
                        <template v-if="loans()">
                            <td class="p-3.5 text-right">{{ r.total ? formatPeso(r.total) : '-' }}</td>
                            <td class="p-3.5 text-right">{{ r.remaining !== null ? formatPeso(r.remaining) : '-' }}</td>
                            <td class="p-3.5">{{ r.left ?? '-' }}</td>
                        </template>
                        <td v-else class="p-3.5 capitalize">{{ r.cadence }}</td>
                        <td class="p-3.5 whitespace-nowrap" :class="r.overdue && 'font-semibold text-destructive'">{{ r.next_due_on ?? '-' }}{{ r.overdue ? ' (late)' : '' }}</td>
                        <td class="p-3.5">{{ r.status }}</td>
                    </tr>
                    <tr v-if="!items.data.length"><td colspan="8" class="p-6 text-center text-muted-foreground">None yet.</td></tr>
                </tbody>
            </table>
        </div>

        <aside class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Most used subscriptions</h2>
            <ol class="mt-3 grid gap-1.5 text-sm">
                <li v-for="p in popular" :key="p.name" class="flex justify-between capitalize"><span>{{ p.name }}</span><span class="font-medium">{{ p.users }}</span></li>
                <li v-if="!popular.length" class="text-muted-foreground">None yet.</li>
            </ol>
        </aside>
    </div>

    <nav v-if="items.links.length > 3" class="mt-4 flex flex-wrap gap-1" aria-label="Pages">
        <template v-for="(l, i) in items.links" :key="i">
            <Link v-if="l.url" :href="l.url" preserve-scroll class="rounded-lg border px-3 py-1.5 text-sm" :class="l.active ? 'bg-primary text-primary-foreground' : 'bg-card hover:bg-accent'" v-html="l.label" />
            <span v-else class="rounded-lg border px-3 py-1.5 text-sm text-muted-foreground" v-html="l.label" />
        </template>
    </nav>
</template>
