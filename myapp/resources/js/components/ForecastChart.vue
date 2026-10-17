<script setup lang="ts">
import { VisArea, VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { formatPeso } from '@/lib/currency';

export type Point = { date: string; balance: number };

const props = defineProps<{
    series: Point[];
    compact?: boolean;
    color?: string;
}>();

const x = (_: Point, i: number) => i;
const y = (d: Point) => d.balance;
const color = props.color ?? 'var(--color-brand)';

const day = (d: string) =>
    new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
const tick = (i: number) => {
    const point = props.series[Math.round(i)];
    return point ? day(point.date) : '';
};
</script>

<template>
    <VisXYContainer
        :data="series"
        :height="compact ? 64 : 280"
        :margin="compact ? { top: 4, bottom: 4 } : undefined"
    >
        <VisArea :x="x" :y="y" :color="color" :opacity="0.18" />
        <VisLine :x="x" :y="y" :color="color" :line-width="compact ? 2 : 3" />
        <template v-if="!compact">
            <VisAxis
                type="x"
                :tick-format="tick"
                :num-ticks="Math.min(series.length, 6)"
                :grid-line="false"
            />
            <VisAxis
                type="y"
                :tick-format="(v: number) => formatPeso(v)"
                :num-ticks="4"
            />
        </template>
    </VisXYContainer>
</template>
