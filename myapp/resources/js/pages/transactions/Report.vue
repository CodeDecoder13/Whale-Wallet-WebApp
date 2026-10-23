<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Ellipsis, Printer } from '@lucide/vue';
import { computed } from 'vue';
import Mascot from '@/components/Mascot.vue';
import { formatPeso } from '@/lib/currency';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as categoriesIndex } from '@/routes/categories';
import { exportMd, exportMethod, index } from '@/routes/transactions';

type Row = {
    id: number;
    type: 'expense' | 'income' | 'transfer';
    merchant: string | null;
    amount: number;
    occurred_at: string;
    card: string;
    category: string | null;
    source: string;
    reference: string | null;
};

const props = defineProps<{
    rows: Row[];
    name: string;
    generated: string;
    filters: { q?: string; from?: string; to?: string };
}>();

// this page has no app sidebar (see app.ts), so "Save as PDF" in the print dialog gives a clean file
const print = () => window.print();

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const longDate = (d: string) => MONTHS[Number(d.slice(5, 7)) - 1] + ' ' + Number(d.slice(8, 10)) + ', ' + d.slice(0, 4);
const sign = { expense: '-', income: '+', transfer: '' };

const spent = computed(() => props.rows.filter((r) => r.type === 'expense').reduce((n, r) => n + r.amount, 0));
const received = computed(() => props.rows.filter((r) => r.type === 'income').reduce((n, r) => n + r.amount, 0));
const range = computed(() => {
    const { from, to } = props.filters;

    return from || to ? `${from ? longDate(from) : 'Start'} to ${to ? longDate(to) : 'today'}` : 'All time';
});
</script>

<template>
    <Head title="Transactions report" />

    <div class="min-h-svh bg-[#f3f6fb] px-4 py-8 print:bg-white print:p-0">
        <div class="mx-auto mb-4 flex max-w-4xl items-center justify-between print:hidden">
            <Link :href="index().url" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                <ArrowLeft class="size-4" /> Back to transactions
            </Link>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-brand-deep"
                    @click="print"
                >
                    <Printer class="size-4" /> Download PDF
                </button>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button type="button" aria-label="More" class="grid size-10 place-items-center rounded-lg border bg-white text-slate-700 hover:bg-slate-50">
                            <Ellipsis class="size-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem as-child>
                            <a :href="exportMethod.url()" download>Export CSV</a>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <a :href="exportMd.url()" download>Export Markdown</a>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="categoriesIndex().url">Categories</Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <article class="mx-auto max-w-4xl overflow-hidden rounded-2xl bg-white text-slate-900 shadow-sm ring-1 ring-slate-200 print:rounded-none print:shadow-none print:ring-0 [print-color-adjust:exact]">
            <header class="flex items-center gap-4 bg-gradient-to-r from-[#0b2a55] to-[#1d6fe0] px-8 py-6 text-white">
                <Mascot pose="happy" round class="size-16 shrink-0 ring-2 ring-white/40" />
                <div class="min-w-0 flex-1">
                    <div class="text-2xl font-semibold tracking-tight">Whale Wallet</div>
                    <div class="text-sm text-white/75">Transactions report</div>
                </div>
                <div class="text-right text-sm text-white/80">
                    <div class="font-medium text-white">{{ name }}</div>
                    <div>Generated {{ longDate(generated) }}</div>
                </div>
            </header>

            <section class="grid grid-cols-3 gap-px border-b bg-slate-200 text-center">
                <div class="bg-white p-4">
                    <div class="text-xs text-slate-500">Period</div>
                    <div class="text-sm font-semibold">{{ range }}</div>
                </div>
                <div class="bg-white p-4">
                    <div class="text-xs text-slate-500">Spent</div>
                    <div class="text-sm font-semibold text-red-600">{{ formatPeso(spent) }}</div>
                </div>
                <div class="bg-white p-4">
                    <div class="text-xs text-slate-500">Received</div>
                    <div class="text-sm font-semibold text-emerald-600">{{ formatPeso(received) }}</div>
                </div>
            </section>

            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-600">
                    <tr>
                        <th class="px-6 py-3">Date</th>
                        <th class="py-3">Transaction</th>
                        <th class="py-3">Category</th>
                        <th class="py-3">Card</th>
                        <th class="py-3">Source</th>
                        <th class="px-6 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id" class="break-inside-avoid border-t border-slate-100">
                        <td class="px-6 py-2.5 whitespace-nowrap text-slate-600">{{ longDate(r.occurred_at) }}</td>
                        <td class="py-2.5 pr-3">
                            <div class="font-medium">{{ r.merchant }}</div>
                            <div v-if="r.reference" class="text-xs text-slate-500">Ref {{ r.reference }}</div>
                        </td>
                        <td class="py-2.5 pr-3">{{ r.category ?? '-' }}</td>
                        <td class="py-2.5 pr-3">{{ r.card }}</td>
                        <td class="py-2.5 pr-3 capitalize">{{ r.source }}</td>
                        <td class="px-6 py-2.5 text-right font-semibold whitespace-nowrap">{{ sign[r.type] }}{{ formatPeso(r.amount) }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="p-8 text-center text-slate-500">No transactions to show.</td>
                    </tr>
                </tbody>
            </table>

            <footer class="border-t px-8 py-4 text-center text-xs text-slate-500">
                {{ rows.length }} transactions · Whale Wallet
            </footer>
        </article>
    </div>
</template>
