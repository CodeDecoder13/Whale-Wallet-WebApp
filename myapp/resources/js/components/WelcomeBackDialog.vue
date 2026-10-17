<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ForecastChart from '@/components/ForecastChart.vue';
import type { Point } from '@/components/ForecastChart.vue';
import Mascot from '@/components/Mascot.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatPeso } from '@/lib/currency';
import { forecast as forecastPage } from '@/routes';

const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{
    name: string;
    left: number;
    forecast: {
        days: number;
        series: Point[];
        lowest: { date: string; amount: number };
        safe_per_day: number;
    };
    upcoming: { id: number; name: string; amount: number; next_due_on: string }[];
}>();

const short = computed(() => props.forecast.lowest.amount < 0);
const day = (d: string) =>
    new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-4">
                    <Mascot
                        :pose="short ? 'worried' : 'checking'"
                        class="size-20 shrink-0"
                    />
                    <div>
                        <DialogTitle>Welcome back, {{ name }}!</DialogTitle>
                        <DialogDescription>
                            {{
                                short
                                    ? 'Heads up: your balance may run short before payday.'
                                    : 'Here is how your wallet looks today.'
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl border bg-card p-3">
                    <div class="text-xs text-muted-foreground">
                        Safe to spend today
                    </div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ formatPeso(forecast.safe_per_day) }}
                    </div>
                </div>
                <div class="rounded-xl border bg-card p-3">
                    <div class="text-xs text-muted-foreground">
                        Left this month
                    </div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ formatPeso(left) }}
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-3 text-xs">
                <p class="text-muted-foreground">
                    Lowest balance before payday:
                    <span class="font-medium text-foreground">
                        {{ formatPeso(forecast.lowest.amount) }}
                    </span>
                    on {{ day(forecast.lowest.date) }}, {{ forecast.days }}
                    {{ forecast.days === 1 ? 'day' : 'days' }} to go.
                </p>
                <ForecastChart :series="forecast.series" compact />
            </div>

            <ul v-if="upcoming.length" class="divide-y rounded-xl border text-sm">
                <li
                    v-for="u in upcoming.slice(0, 3)"
                    :key="u.id"
                    class="flex items-center justify-between gap-3 px-3 py-2"
                >
                    <span class="min-w-0 truncate">
                        {{ u.name }}
                        <span class="text-xs text-muted-foreground">
                            due {{ day(u.next_due_on) }}
                        </span>
                    </span>
                    <span class="font-medium">{{ formatPeso(u.amount) }}</span>
                </li>
            </ul>

            <div class="flex items-center justify-between">
                <Link
                    :href="forecastPage()"
                    class="text-sm font-medium underline underline-offset-4"
                >
                    Full forecast
                </Link>
                <Button @click="open = false">Got it</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
