<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { PiggyBank, Wallet, TrendingDown } from '@lucide/vue';
import { computed } from 'vue';
import BalanceChart from '@/components/BalanceChart.vue';
import type { Point } from '@/components/ForecastChart.vue';
import Heading from '@/components/Heading.vue';
import SelectNative from '@/components/SelectNative.vue';
import { formatPeso } from '@/lib/currency';
import { forecast as route } from '@/routes';

const props = defineProps<{
    forecast: {
        payday: string;
        days: number;
        series: Point[];
        lowest: { date: string; amount: number };
        at_payday: number;
        safe_per_day: number;
    };
    skipped: number | null;
    subscriptions: { id: number; name: string; amount: number }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Forecast', href: route() }] },
});

const day = (d: string) =>
    new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

// the server recomputes, so all forecast math stays in PHP
function skip(id?: string | number | null) {
    router.get(route.url(), id ? { skip: id } : {}, {
        only: ['forecast', 'skipped'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

// one plain sentence for people who will not read a chart
const verdict = computed(() => {
    const { lowest, payday } = props.forecast;

    return lowest.amount < 0
        ? { bad: true, text: `You will run out of money on ${day(lowest.date)} and be short by ${formatPeso(-lowest.amount)}. Try skipping a bill below to see how it changes.` }
        : { bad: false, text: `You stay above zero until payday on ${day(payday)}. Your lowest point is ${formatPeso(lowest.amount)} on ${day(lowest.date)}.` };
});

const GREEN = '#22c55e';
const RED = '#ef4444';

// same card language as the dashboard: icon, plain-words title, big number, a coloured verdict, one line of why
const tiles = computed(() => {
    const { at_payday, lowest, safe_per_day, days, payday } = props.forecast;

    return [
        {
            title: 'Left when payday comes',
            icon: Wallet,
            value: formatPeso(at_payday),
            status: at_payday < 0 ? 'Running short' : 'You will make it',
            color: at_payday < 0 ? RED : GREEN,
            hint: `On ${day(payday)}, ${days} ${days === 1 ? 'day' : 'days'} away, after bills and spending your safe amount each day`,
        },
        {
            title: 'Tightest day',
            icon: TrendingDown,
            value: formatPeso(lowest.amount),
            status: lowest.amount < 0 ? 'You run out' : 'Stays above zero',
            color: lowest.amount < 0 ? RED : GREEN,
            hint: `Your money is lowest on ${day(lowest.date)}`,
        },
        {
            title: 'Safe to spend',
            icon: PiggyBank,
            value: `${formatPeso(safe_per_day)} / day`,
            status: safe_per_day <= 0 ? 'Hold off spending' : 'Spend up to this',
            color: safe_per_day <= 0 ? RED : GREEN,
            hint: 'A daily limit that keeps you above zero until payday',
        },
    ];
});
</script>

<template>
    <Head title="Forecast" />

    <div class="flex flex-col gap-5 p-4 md:p-8">
        <Heading
            class="mb-0!"
            title="Forecast"
            description="Your projected balance until the next payday."
        />

        <div class="grid gap-3 sm:gap-4 md:grid-cols-3">
            <div
                v-for="t in tiles"
                :key="t.title"
                class="flex flex-col rounded-2xl border bg-card p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-2 text-sm font-medium">
                        <component :is="t.icon" class="size-4 shrink-0" :style="{ color: t.color }" />
                        <span class="truncate">{{ t.title }}</span>
                    </span>
                    <span class="shrink-0 text-xs text-muted-foreground">Until {{ day(forecast.payday) }}</span>
                </div>
                <div class="mt-4 min-h-14">
                    <div class="truncate text-2xl font-semibold">{{ t.value }}</div>
                    <div class="mt-1 text-xs font-medium" :style="{ color: t.color }">{{ t.status }}</div>
                </div>
                <div class="mt-auto pt-3 text-xs text-muted-foreground">{{ t.hint }}</div>
            </div>
        </div>

        <section class="rounded-xl border bg-card p-6">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-medium">Your money day by day</h2>
                    <p class="text-xs text-muted-foreground">
                        Unpaid bills coming up, if you spend your safe amount each day.
                    </p>
                </div>
                <label class="grid gap-1 text-xs text-muted-foreground">
                    What if I skip this bill or subscription?
                    <SelectNative
                        class="w-52"
                        :model-value="skipped ?? ''"
                        @update:model-value="skip"
                    >
                        <option value="">Nothing</option>
                        <option
                            v-for="s in subscriptions"
                            :key="s.id"
                            :value="s.id"
                        >
                            {{ s.name }} ({{ formatPeso(s.amount) }})
                        </option>
                    </SelectNative>
                </label>
            </div>
            <p
                class="mb-4 rounded-xl px-4 py-3 text-sm"
                :class="verdict.bad ? 'bg-red-500/10 text-red-700 dark:text-red-300' : 'bg-green-500/10 text-green-700 dark:text-green-300'"
            >
                {{ verdict.text }}
            </p>
            <BalanceChart :series="forecast.series" />
        </section>

        <p class="text-xs text-muted-foreground">
            Budgeting guidance based on the numbers you entered, not licensed
            financial advice.
        </p>
    </div>
</template>
