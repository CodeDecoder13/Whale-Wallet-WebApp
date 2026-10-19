<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { findBrand } from '@/lib/brandLogos';
import { formatPeso } from '@/lib/currency';

const props = defineProps<{
    name: string;
    logo: string | null;
    meta: string;
    amount: number;
    due: string | null;
    done: boolean;
}>();

// Due tone: red today or late, orange within 3 days, yellow within a week, green beyond.
const days = computed(() => {
    if (!props.due || props.done) {
        return null;
    }

    const t = new Date();
    const today = new Date(t.getFullYear(), t.getMonth(), t.getDate());

    return Math.round(
        (new Date(`${props.due.slice(0, 10)}T00:00`).getTime() -
            today.getTime()) /
            864e5,
    );
});
const tone = computed(() =>
    days.value === null
        ? { c: '#94a3b8', text: props.done ? 'Paid off' : 'No date' }
        : days.value <= 0
          ? { c: '#ef4444', text: days.value === 0 ? 'Due today' : `${-days.value}d overdue` }
          : days.value <= 3
            ? { c: '#f97316', text: `Due in ${days.value}d` }
            : days.value <= 7
              ? { c: '#eab308', text: `Due in ${days.value}d` }
              : { c: '#22c55e', text: `Due in ${days.value}d` },
);

// The logo's own colour tints the card: a brand icon gives it directly, an image is averaged on a 1px canvas.
const vibe = ref(`#${findBrand(props.name)?.icon?.hex ?? '64748b'}`);
onMounted(() => {
    const src = props.logo || findBrand(props.name)?.image;

    if (!src) {
        return;
    }

    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        try {
            const ctx = document.createElement('canvas').getContext('2d')!;
            ctx.canvas.width = ctx.canvas.height = 1;
            ctx.drawImage(img, 0, 0, 1, 1);
            const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;

            if (a > 40) {
                vibe.value = `rgb(${r} ${g} ${b})`;
            }
        } catch {
            // tainted canvas: keep the neutral tint
        }
    };
    img.src = src;
});
</script>

<template>
    <li
        class="bill-card relative flex flex-col gap-3 overflow-hidden rounded-xl border bg-card p-4 text-sm"
        :style="{ '--vibe': vibe, '--tone': tone.c }"
    >
        <span class="bill-glow" aria-hidden="true" />
        <div class="relative flex items-center gap-3">
            <BrandLogo :name="name" :logo="logo" />
            <div class="min-w-0 flex-1">
                <div class="truncate font-medium">{{ name }}</div>
                <div class="text-xs text-muted-foreground capitalize">
                    {{ meta }}
                </div>
            </div>
            <span
                class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                :style="{
                    color: tone.c,
                    background: `color-mix(in srgb, ${tone.c} 15%, transparent)`,
                }"
            >
                {{ tone.text }}
            </span>
        </div>
        <div class="relative text-2xl font-semibold">
            {{ formatPeso(amount) }}
        </div>
        <div
            class="relative mt-auto flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium text-brand-deep dark:text-brand-light"
        >
            <slot />
        </div>
    </li>
</template>

<style scoped>
.bill-card {
    border-color: color-mix(in srgb, var(--tone) 45%, transparent);
    border-left: 4px solid var(--tone);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.bill-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px -12px var(--vibe);
}

.bill-glow {
    position: absolute;
    top: -2.5rem;
    left: -2.5rem;
    width: 9rem;
    height: 9rem;
    border-radius: 9999px;
    background: radial-gradient(
        closest-side,
        color-mix(in srgb, var(--vibe) 30%, transparent),
        transparent
    );
    animation: bill-drift 6s ease-in-out infinite alternate;
}

@keyframes bill-drift {
    to {
        transform: translate(1.5rem, 1rem) scale(1.25);
        opacity: 0.6;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bill-glow {
        animation: none;
    }
}
</style>
