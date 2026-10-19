<script setup lang="ts">
import { VisArea, VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';
import { formatPeso } from '@/lib/currency';

type Point = { date: Date; saved: number; target: number };

/**
 * What saving a fixed amount each month adds up to, against the target. All money in centavos.
 * Pure arithmetic on the form values, so it updates as the user types.
 */
const props = defineProps<{
    saved: number;
    monthly: number;
    target: number;
    targetDate: string | null;
}>();

const left = computed(() => Math.max(0, props.target - props.saved));
// whole months until the target is met at this monthly amount; null when nothing is being added
const monthsToGoal = computed(() => (props.monthly > 0 ? Math.ceil(left.value / props.monthly) : null));

const plusMonths = (n: number) => {
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() + n);

    return d;
};

const points = computed<Point[]>(() => {
    const reach = monthsToGoal.value ?? 12;
    const n = Math.min(60, Math.max(2, reach));

    return Array.from({ length: n + 1 }, (_, k) => ({
        date: plusMonths(k),
        saved: props.saved + props.monthly * k,
        target: props.target,
    }));
});

const month = (d: Date) => d.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
const reachDate = computed(() => (monthsToGoal.value === null ? null : plusMonths(monthsToGoal.value)));
// after the chosen target date: say how much more a month it would take
const late = computed(() => {
    if (!props.targetDate || monthsToGoal.value === null || !reachDate.value) {
        return false;
    }

    return reachDate.value > new Date(`${props.targetDate}T00:00`);
});

const x = (_: Point, i: number) => i;
const tick = (i: number) => (points.value[Math.round(i)] ? month(points.value[Math.round(i)].date) : '');
</script>

<template>
    <div class="grid gap-4">
        <VisXYContainer :data="points" :height="220">
            <VisArea :x="x" :y="(d: Point) => d.saved" color="var(--color-brand)" :opacity="0.18" />
            <VisLine :x="x" :y="(d: Point) => d.saved" color="var(--color-brand)" :line-width="3" />
            <VisLine :x="x" :y="(d: Point) => d.target" color="#22c55e" :line-width="2" :line-dash-array="[5, 4]" />
            <VisAxis type="x" :tick-format="tick" :num-ticks="Math.min(points.length, 4)" :grid-line="false" />
            <VisAxis type="y" :tick-format="(v: number) => formatPeso(v)" :num-ticks="4" />
        </VisXYContainer>

        <div class="flex items-center gap-4 text-xs text-muted-foreground">
            <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded bg-brand" /> Saved</span>
            <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded border-t-2 border-dashed border-green-500" /> Target</span>
        </div>

        <p v-if="monthsToGoal === null" class="text-sm text-muted-foreground">
            Add a monthly amount to see when you reach {{ formatPeso(target) }}.
        </p>
        <p v-else-if="left === 0" class="text-sm font-medium">You already have enough for this goal.</p>
        <p v-else class="text-sm">
            At <span class="font-medium">{{ formatPeso(monthly) }}</span> a month you reach
            <span class="font-medium">{{ formatPeso(target) }}</span> by
            <span class="font-medium">{{ month(reachDate!) }}</span>
            ({{ monthsToGoal }} {{ monthsToGoal === 1 ? 'month' : 'months' }}).
        </p>
        <p v-if="late" class="rounded-lg bg-yellow-500/10 p-3 text-xs">
            That is after your target date. Raise the monthly amount to reach it in time.
        </p>
    </div>
</template>
