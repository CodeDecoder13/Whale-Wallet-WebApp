<script setup lang="ts">
import { computed } from 'vue';
import { formatPeso } from '@/lib/currency';

const props = defineProps<{
    name: string;
    saved: number;
    target: number;
    targetDate: string | null;
}>();
defineEmits<{ select: [] }>();

const percent = computed(() => Math.min(100, Math.round((props.saved / props.target) * 100)));
const done = computed(() => percent.value >= 100);
// blue while saving, green once the goal is met
const tone = computed(() => (done.value ? '#22c55e' : '#2f8fe6'));
const chip = computed(() => {
    if (done.value) {
        return 'Goal reached';
    }

    if (!props.targetDate) {
        return `${percent.value}% saved`;
    }

    const days = Math.round((new Date(`${props.targetDate.slice(0, 10)}T00:00`).getTime() - new Date().setHours(0, 0, 0, 0)) / 864e5);

    return days < 0 ? `${-days}d past target` : `${days}d to go`;
});
</script>

<template>
    <li
        class="pod-card relative overflow-hidden rounded-xl border bg-card text-sm"
        :style="{ '--tone': tone }"
    >
        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-4 p-4 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
            @click="$emit('select')"
        >
            <div class="relative size-16 shrink-0">
                <svg viewBox="0 0 36 36" class="size-full -rotate-90" aria-hidden="true">
                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--muted)" stroke-width="3.5" />
                    <circle
                        cx="18"
                        cy="18"
                        r="15.9"
                        fill="none"
                        :stroke="tone"
                        stroke-width="3.5"
                        stroke-linecap="round"
                        pathLength="100"
                        :stroke-dasharray="`${percent} 100`"
                    />
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-xs font-semibold">
                    {{ percent }}%
                </span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="truncate font-medium">{{ name }}</span>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :style="{ color: tone, background: `color-mix(in srgb, ${tone} 15%, transparent)` }"
                    >
                        {{ chip }}
                    </span>
                </div>
                <div class="mt-1 text-2xl font-semibold">{{ formatPeso(saved) }}</div>
                <div class="text-xs text-muted-foreground">of {{ formatPeso(target) }}</div>
            </div>
        </button>
    </li>
</template>

<style scoped>
.pod-card {
    border-left: 4px solid var(--tone);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.pod-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px -12px var(--tone);
}
</style>
