<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import { formatPeso } from '@/lib/currency';
import type { Point } from '@/components/ForecastChart.vue';

// "Your money day by day": green above zero, red below it, labelled start, lowest and payday, and a hover read-out.
// Plain SVG sized to its box, so the text stays crisp and the colours follow the theme.
const props = defineProps<{ series: Point[] }>();

const id = useId();
const box = ref<HTMLElement | null>(null);
const W = ref(800);
const H = 280;
const m = { l: 64, r: 20, t: 28, b: 30 };

let ro: ResizeObserver | null = null;
onMounted(() => {
    ro = new ResizeObserver(([e]) => (W.value = Math.max(320, Math.round(e.contentRect.width))));
    box.value && ro.observe(box.value);
});
onBeforeUnmount(() => ro?.disconnect());

const n = computed(() => props.series.length);
const vals = computed(() => props.series.map((p) => p.balance));
const lo = computed(() => Math.min(0, ...vals.value));
const hi = computed(() => Math.max(0, ...vals.value));
// breathing room above and below; a flat zero line still gets a visible range
const span = computed(() => (hi.value - lo.value || 10000) * 1.16);
const top = computed(() => hi.value + (span.value - (hi.value - lo.value)) / 2);
const bottom = computed(() => top.value - span.value);

const xs = (i: number) => m.l + (n.value < 2 ? 0.5 : i / (n.value - 1)) * (W.value - m.l - m.r);
const ys = (v: number) => m.t + ((top.value - v) / span.value) * (H - m.t - m.b);
const zero = computed(() => ys(0));

const pts = computed(() => props.series.map((p, i) => [xs(i), ys(p.balance)] as const));
const line = computed(() => pts.value.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`).join(' '));
const area = computed(() => {
    const p = pts.value;

    return p.length ? `${line.value} L${p[p.length - 1][0].toFixed(1)} ${zero.value} L${p[0][0].toFixed(1)} ${zero.value} Z` : '';
});

const peso = (v: number) => formatPeso(v).replace(/\.00$/, '');
const day = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
const yTicks = computed(() => [...new Set([hi.value, 0, lo.value])].filter((v) => Number.isFinite(v)));
const xTicks = computed(() => {
    const k = Math.min(n.value, W.value < 520 ? 3 : 5);

    return [...new Set(Array.from({ length: k }, (_, j) => Math.round((j * (n.value - 1)) / Math.max(1, k - 1))))];
});

// the lowest point gets its own marker unless it is an end point, which already carries a label
const low = computed(() => {
    let k = 0;
    vals.value.forEach((v, i) => v < vals.value[k] && (k = i));

    return k;
});
const marks = computed(() => {
    const out = [{ i: 0, name: 'Today' }];
    n.value > 1 && out.push({ i: n.value - 1, name: 'Payday' });
    low.value > 0 && low.value < n.value - 1 && out.push({ i: low.value, name: 'Lowest' });

    return out.map((k) => ({ ...k, x: xs(k.i), y: ys(vals.value[k.i]), v: vals.value[k.i] }));
});

const active = ref<number | null>(null);
function move(e: PointerEvent) {
    const r = (e.currentTarget as SVGElement).getBoundingClientRect();
    const x = ((e.clientX - r.left) / r.width) * W.value;
    active.value = Math.min(n.value - 1, Math.max(0, Math.round(((x - m.l) / (W.value - m.l - m.r)) * (n.value - 1))));
}
const tip = computed(() => {
    const i = active.value;

    return i === null || !props.series[i]
        ? null
        : { x: xs(i), y: ys(vals.value[i]), date: day(props.series[i].date), v: vals.value[i] };
});

const label = computed(() => {
    const e = props.series[n.value - 1];

    return `Projected balance from ${props.series[0] ? day(props.series[0].date) : ''} to ${e ? day(e.date) : ''}. Lowest ${peso(lo.value)}.`;
});
</script>

<template>
    <div ref="box" class="relative w-full select-none">
        <svg
            :viewBox="`0 0 ${W} ${H}`"
            :width="W"
            :height="H"
            class="block touch-pan-y"
            role="img"
            :aria-label="label"
            @pointermove="move"
            @pointerleave="active = null"
        >
            <defs>
                <linearGradient :id="`${id}-up`" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0" stop-color="#22c55e" stop-opacity="0.28" />
                    <stop offset="1" stop-color="#22c55e" stop-opacity="0.02" />
                </linearGradient>
                <linearGradient :id="`${id}-down`" x1="0" x2="0" y1="1" y2="0">
                    <stop offset="0" stop-color="#ef4444" stop-opacity="0.3" />
                    <stop offset="1" stop-color="#ef4444" stop-opacity="0.03" />
                </linearGradient>
                <clipPath :id="`${id}-above`"><rect x="0" y="0" :width="W" :height="Math.max(0, zero)" /></clipPath>
                <clipPath :id="`${id}-below`"><rect x="0" :y="zero" :width="W" :height="Math.max(0, H - zero)" /></clipPath>
            </defs>

            <!-- soft guides at the tick values -->
            <g>
                <template v-for="v in yTicks" :key="v">
                    <line :x1="m.l" :x2="W - m.r" :y1="ys(v)" :y2="ys(v)" :stroke="v === 0 ? 'currentColor' : 'var(--border)'" :stroke-opacity="v === 0 ? 0.35 : 1" :stroke-dasharray="v === 0 ? '5 5' : '0'" />
                    <text :x="m.l - 10" :y="ys(v) + 4" text-anchor="end" class="fill-muted-foreground text-[11px]">{{ v === 0 ? '₱0' : peso(v) }}</text>
                </template>
            </g>

            <g :clip-path="`url(#${id}-above)`">
                <path :d="area" :fill="`url(#${id}-up)`" />
                <path :d="line" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </g>
            <g :clip-path="`url(#${id}-below)`">
                <path :d="area" :fill="`url(#${id}-down)`" />
                <path :d="line" fill="none" stroke="#ef4444" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </g>

            <g v-for="t in xTicks" :key="t">
                <text
                    :x="xs(t)"
                    :y="H - 8"
                    :text-anchor="t === 0 ? 'start' : t === n - 1 ? 'end' : 'middle'"
                    class="fill-muted-foreground text-[11px]"
                >
                    {{ series[t] ? day(series[t].date) : '' }}
                </text>
            </g>

            <g v-for="k in marks" :key="k.name">
                <circle :cx="k.x" :cy="k.y" r="5.5" class="fill-card" :stroke="k.v < 0 ? '#ef4444' : '#16a34a'" stroke-width="3" />
                <text
                    :x="k.x + (k.i === 0 ? 4 : k.i === n - 1 ? -4 : 0)"
                    :y="k.y < m.t + 22 ? k.y + 24 : k.y - 14"
                    :text-anchor="k.i === 0 ? 'start' : k.i === n - 1 ? 'end' : 'middle'"
                    class="fill-foreground text-[12px] font-semibold"
                    style="paint-order: stroke; stroke: var(--card); stroke-width: 4px; stroke-linejoin: round"
                >
                    {{ k.name }} {{ peso(k.v) }}
                </text>
            </g>

            <g v-if="tip" pointer-events="none">
                <line :x1="tip.x" :x2="tip.x" :y1="m.t" :y2="H - m.b" stroke="currentColor" stroke-opacity="0.25" />
                <circle :cx="tip.x" :cy="tip.y" r="6" :fill="tip.v < 0 ? '#ef4444' : '#16a34a'" />
            </g>
        </svg>

        <div
            v-if="tip"
            class="pointer-events-none absolute z-10 -translate-x-1/2 rounded-xl border bg-popover px-3 py-2 text-xs shadow-md"
            :style="{ left: `${Math.min(W - 80, Math.max(80, tip.x))}px`, top: `${Math.max(0, tip.y - 66)}px` }"
        >
            <div class="text-muted-foreground">{{ tip.date }}</div>
            <div class="text-sm font-semibold" :class="tip.v < 0 && 'text-red-500'">{{ peso(tip.v) }}</div>
            <div class="text-muted-foreground">{{ tip.v < 0 ? 'short that day' : 'left that day' }}</div>
        </div>
    </div>
</template>
