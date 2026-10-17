<script setup lang="ts">
import { VisDonut, VisSingleContainer } from '@unovis/vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ForecastChart from '@/components/ForecastChart.vue';
import IncomeDialog from '@/components/IncomeDialog.vue';
import Mascot from '@/components/Mascot.vue';
import WelcomeBackDialog from '@/components/WelcomeBackDialog.vue';
import type { Point } from '@/components/ForecastChart.vue';
import { formatPeso } from '@/lib/currency';
import { dashboard, forecast as forecastPage } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { index as tipsPage } from '@/routes/tips';

type Slice = { name: string; total: number };

const props = defineProps<{
    summary: {
        income: number;
        fixed: number;
        spent: number;
        safe: number;
    };
    forecast: {
        days: number;
        payday: string;
        at_payday: number;
        series: Point[];
        lowest: { date: string; amount: number };
        safe_per_day: number;
    };
    consented: boolean;
    welcome: boolean;
    income_entries: {
        id: number;
        name: string;
        amount: number;
        reference: string | null;
        received_on: string | null;
    }[];
    income_proofs: {
        id: number;
        status: 'queued' | 'parsing' | 'parsed' | 'failed' | 'confirmed';
        error: string | null;
        claimed: number | null;
        draft: {
            amount: number;
            occurred_at: string;
            reference: string | null;
            merchant: string;
        } | null;
    }[];
    tips: { id: number; text: string }[];
    categories: Slice[];
    upcoming: {
        id: number;
        name: string;
        amount: number;
        next_due_on: string;
    }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

const color = (i: number) => `var(--chart-${(i % 5) + 1})`;
const total = computed(() => props.categories.reduce((n, c) => n + c.total, 0));

const pctOfIncome = (n: number) =>
    props.summary.income ? Math.min(100, (n / props.summary.income) * 100) : 0;

const tiles = computed(() => [
    [
        'Income',
        props.summary.income,
        props.income_entries.length
            ? 'Received this month'
            : 'Expected this month',
    ],
    [
        'Fixed expenses',
        props.summary.fixed,
        `${pctOfIncome(props.summary.fixed).toFixed(1)}% of income`,
    ],
    ['Left to spend', props.summary.safe, 'After bills and spending'],
    [
        'Spent this month',
        props.summary.spent,
        `${pctOfIncome(props.summary.spent).toFixed(1)}% of income`,
    ],
]);

const left = computed(() =>
    Math.max(
        0,
        props.summary.income - props.summary.fixed - props.summary.spent,
    ),
);
const split = computed(() =>
    [
        ['Fixed expenses', props.summary.fixed, 'bg-brand-deep'],
        ['Spending', props.summary.spent, 'bg-brand'],
        ['Left over', left.value, 'bg-brand-light'],
    ].map(([label, amount, bar]) => ({
        label: label as string,
        amount: amount as number,
        bar: bar as string,
        pct: pctOfIncome(amount as number),
    })),
);

const welcomeOpen = ref(props.welcome);

// ?income=1 (from Whale Chat) opens the dialog so a proof sent there can be confirmed
const incomeOpen = ref(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('income'),
);

const user = computed(() => usePage().props.auth.user);
const firstName = computed(() => user.value.name.split(' ')[0]);
const hour = new Date().getHours();
const greeting =
    hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
const dueDate = (d: string) =>
    new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-5 p-4 md:p-8">
        <div>
            <h1 class="text-3xl font-medium tracking-tight">
                {{ greeting }}, {{ firstName }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Here's your money at a glance.
            </p>
        </div>

        <div
            class="grid grid-cols-[minmax(0,1fr)] items-center gap-6 overflow-hidden rounded-2xl bg-linear-to-br from-navy to-navy-2 p-6 text-white sm:grid-cols-[auto_minmax(0,1fr)] md:gap-8 md:p-8 lg:grid-cols-[auto_minmax(0,1fr)_minmax(0,1.15fr)]"
        >
            <Mascot
                pose="hero"
                bare
                class="pointer-events-none hidden w-40 shrink-0 sm:block md:w-52"
            />

            <div class="min-w-0">
                <div class="flex items-center gap-2 text-sm font-medium">
                    <span class="size-2 rounded-full bg-brand-light" />
                    Safe to spend today
                </div>
                <div class="mt-2 text-5xl font-bold tracking-tight">
                    {{ formatPeso(forecast.safe_per_day) }}
                </div>
                <p class="mt-2 max-w-md text-sm text-white/75">
                    {{
                        forecast.safe_per_day === 0 && forecast.lowest.amount < 0
                            ? 'Bills and spending outrun your balance before payday, so nothing is safe to spend yet.'
                            : `Spread over the ${forecast.days} ${forecast.days === 1 ? 'day' : 'days'} until payday.`
                    }}
                </p>

                <dl class="mt-5 grid max-w-md grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-white/60">Lowest point</dt>
                        <dd
                            class="mt-0.5 font-semibold"
                            :class="forecast.lowest.amount < 0 && 'text-warm'"
                        >
                            {{ formatPeso(forecast.lowest.amount) }}
                        </dd>
                        <dd class="text-xs text-white/60">
                            {{ dueDate(forecast.lowest.date) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-white/60">At payday</dt>
                        <dd class="mt-0.5 font-semibold">
                            {{ formatPeso(forecast.at_payday) }}
                        </dd>
                        <dd class="text-xs text-white/60">
                            {{ dueDate(forecast.payday) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-white/60">Payday in</dt>
                        <dd class="mt-0.5 font-semibold">
                            {{ forecast.days }}
                            {{ forecast.days === 1 ? 'day' : 'days' }}
                        </dd>
                        <dd>
                            <Link
                                :href="`${profileEdit().url}#payday`"
                                class="text-xs text-white/60 underline underline-offset-4 hover:text-white"
                            >
                                Change payday
                            </Link>
                        </dd>
                    </div>
                </dl>

                <Link
                    :href="forecastPage()"
                    class="mt-5 inline-block text-sm font-medium text-white underline underline-offset-4"
                >
                    See the full forecast
                </Link>
            </div>

            <div
                class="rounded-xl bg-white/5 p-4 ring-1 ring-white/10 sm:col-span-2 lg:col-span-1"
            >
                <div class="mb-2 flex items-baseline justify-between text-xs text-white/60">
                    <span>Projected balance</span>
                    <span>Today to {{ dueDate(forecast.payday) }}</span>
                </div>
                <ForecastChart
                    :series="forecast.series"
                    compact
                    :height="170"
                    color="var(--color-brand-light)"
                />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="[label, amount, note] in tiles"
                :key="label"
                class="rounded-xl border bg-card p-5"
            >
                <div class="text-xs text-muted-foreground">{{ label }}</div>
                <div class="mt-4 text-2xl font-semibold">
                    {{ formatPeso(Number(amount)) }}
                </div>
                <div class="mt-3 text-xs text-brand-deep dark:text-brand-light">
                    {{ note }}
                </div>
                <button
                    v-if="label === 'Income'"
                    type="button"
                    class="mt-2 text-xs font-medium underline underline-offset-4"
                    @click="incomeOpen = true"
                >
                    + Add income
                </button>
            </div>
        </div>

        <WelcomeBackDialog
            v-model:open="welcomeOpen"
            :name="firstName"
            :left="summary.safe"
            :forecast="forecast"
            :upcoming="upcoming"
        />

        <IncomeDialog
            v-model:open="incomeOpen"
            :consented="consented"
            :entries="income_entries"
            :proofs="income_proofs"
        />

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-6">
                <h2 class="font-medium">Where each peso goes</h2>
                <p class="mb-5 text-xs text-muted-foreground">
                    Monthly income allocation
                </p>
                <p v-if="!summary.income" class="text-sm text-muted-foreground">
                    Add income to see your allocation.
                </p>
                <template v-else>
                    <div class="flex h-3 overflow-hidden rounded-full bg-muted">
                        <div
                            v-for="s in split"
                            :key="s.label"
                            :class="s.bar"
                            :style="{ width: `${s.pct}%` }"
                        />
                    </div>
                    <ul class="mt-4 grid gap-3 text-sm">
                        <li
                            v-for="s in split"
                            :key="s.label"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="size-2.5 rounded-full"
                                :class="s.bar"
                            />
                            <span class="flex-1">{{ s.label }}</span>
                            <span class="text-muted-foreground">
                                {{ formatPeso(s.amount) }}
                            </span>
                            <span class="w-12 text-right font-medium">
                                {{ s.pct.toFixed(1) }}%
                            </span>
                        </li>
                    </ul>
                </template>
            </section>

            <section class="rounded-xl border bg-card p-6">
                <h2 class="font-medium">Spending by category</h2>
                <p class="mb-5 text-xs text-muted-foreground">
                    Where your spending goes
                </p>
                <p
                    v-if="!categories.length"
                    class="text-sm text-muted-foreground"
                >
                    No spending logged this month.
                </p>
                <div v-else class="flex flex-wrap items-center gap-6">
                    <VisSingleContainer
                        :data="categories"
                        :height="180"
                        class="w-44"
                    >
                        <VisDonut
                            :value="(d: Slice) => d.total"
                            :color="(_: Slice, i: number) => color(i)"
                            :arc-width="26"
                            :central-label="formatPeso(total)"
                            central-sub-label="spent this month"
                        />
                    </VisSingleContainer>
                    <ul class="min-w-48 flex-1 space-y-2 text-sm">
                        <li
                            v-for="(c, i) in categories"
                            :key="c.name"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="size-2.5 rounded-full"
                                :style="{ background: color(i) }"
                            />
                            <span class="flex-1">{{ c.name }}</span>
                            <span class="font-medium">
                                {{ formatPeso(c.total) }}
                            </span>
                            <span class="w-10 text-right text-muted-foreground">
                                {{ Math.round((c.total / total) * 100) }}%
                            </span>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <section class="rounded-xl border bg-card p-6">
            <h2 class="font-medium">Upcoming bills</h2>
            <p class="mb-3 text-xs text-muted-foreground">Next 14 days</p>
            <p v-if="!upcoming.length" class="text-sm text-muted-foreground">
                Nothing due soon.
            </p>
            <ul v-else class="divide-y text-sm">
                <li
                    v-for="b in upcoming"
                    :key="b.id"
                    class="flex items-center gap-3 py-3"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-lg bg-accent font-semibold text-accent-foreground"
                    >
                        {{ b.name.charAt(0).toUpperCase() }}
                    </span>
                    <div class="flex-1">
                        <div class="font-medium">{{ b.name }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ dueDate(b.next_due_on) }}
                        </div>
                    </div>
                    <span class="font-semibold">{{
                        formatPeso(b.amount)
                    }}</span>
                </li>
            </ul>
        </section>

        <section class="rounded-xl border bg-card p-6">
            <div class="flex items-center justify-between">
                <h2 class="font-medium">Whale tips</h2>
                <Link
                    :href="tipsPage()"
                    class="text-xs font-medium text-brand-deep dark:text-brand-light"
                >
                    All tips
                </Link>
            </div>
            <p v-if="!tips.length" class="mt-3 text-sm text-muted-foreground">
                No tips yet. Open Tips to generate some.
            </p>
            <ul v-else class="mt-3 grid gap-2 text-sm">
                <li v-for="t in tips" :key="t.id" class="flex gap-2">
                    <span
                        class="mt-2 size-1.5 shrink-0 rounded-full bg-brand"
                    />
                    {{ t.text }}
                </li>
            </ul>
            <p class="mt-3 text-xs text-muted-foreground">
                Budgeting guidance, not financial advice.
            </p>
        </section>

        <p class="text-xs text-muted-foreground">
            Budgeting guidance based on the numbers you entered, not licensed
            financial advice.
        </p>
    </div>
</template>
