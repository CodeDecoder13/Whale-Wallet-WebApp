<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ForecastChart from '@/components/ForecastChart.vue';
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

const tiles = computed(() => [
    [
        `Balance on ${day(props.forecast.payday)}`,
        formatPeso(props.forecast.at_payday),
        `Expected before pay arrives, ${props.forecast.days} ${props.forecast.days === 1 ? 'day' : 'days'} away`,
    ],
    [
        'Lowest point',
        formatPeso(props.forecast.lowest.amount),
        `On ${day(props.forecast.lowest.date)}`,
    ],
    [
        'Safe to spend',
        `${formatPeso(props.forecast.safe_per_day)} / day`,
        'Lowest balance spread over the days left',
    ],
]);
</script>

<template>
    <Head title="Forecast" />

    <div class="flex flex-col gap-5 p-4 md:p-8">
        <Heading
            class="mb-0!"
            title="Forecast"
            description="Your projected balance until the next payday."
        />

        <div class="grid gap-4 md:grid-cols-3">
            <div
                v-for="[label, value, note] in tiles"
                :key="label"
                class="rounded-xl border bg-card p-5"
            >
                <div class="text-xs text-muted-foreground">{{ label }}</div>
                <div class="mt-3 text-2xl font-semibold">{{ value }}</div>
                <div class="mt-2 text-xs text-brand-deep dark:text-brand-light">
                    {{ note }}
                </div>
            </div>
        </div>

        <section class="rounded-xl border bg-card p-6">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-medium">Projected balance</h2>
                    <p class="text-xs text-muted-foreground">
                        Known bills plus your recent average spending.
                    </p>
                </div>
                <label class="grid gap-1 text-xs text-muted-foreground">
                    What if I skip...
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
            <ForecastChart :series="forecast.series" />
        </section>

        <p class="text-xs text-muted-foreground">
            Budgeting guidance based on the numbers you entered, not licensed
            financial advice.
        </p>
    </div>
</template>
