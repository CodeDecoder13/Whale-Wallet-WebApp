<script setup lang="ts">
import { computed } from 'vue';

// a colourful cartoon-style tile for a category; the merchant is a fallback hint when the category is blank or "Other"
const props = defineProps<{ category?: string | null; merchant?: string | null }>();

// first match wins: [pattern, emoji, tile colour]
const looks: [RegExp, string, string][] = [
    [/laundry/, '🧺', 'bg-sky-100 dark:bg-sky-500/20'],
    [/car ?wash/, '🚗🫧', 'bg-cyan-100 dark:bg-cyan-500/20'],
    [/credit|debit|\batm\b|\bcards?\b/, '💳', 'bg-blue-100 dark:bg-blue-500/20'],
    [/motor|moto|scooter|\bbike\b/, '🏍️', 'bg-zinc-200 dark:bg-zinc-500/20'],
    [/coffee|cafe|café|latte|milk ?tea/, '☕', 'bg-amber-100 dark:bg-amber-600/20'],
    [/grocer|market|supermarket/, '🛒', 'bg-lime-100 dark:bg-lime-500/20'],
    [/food|lunch|dinner|breakfast|meal|snack|drink|restaurant|eat/, '🍽️', 'bg-orange-100 dark:bg-orange-500/20'],
    [/car|gas|fuel|parking|toll|vehicle/, '🚗', 'bg-red-100 dark:bg-red-500/20'],
    [/transport|grab|taxi|jeep|bus|fare|commute/, '🚌', 'bg-yellow-100 dark:bg-yellow-500/20'],
    [/bill|electric|water|internet|rent|utilit|load/, '💡', 'bg-amber-100 dark:bg-amber-500/20'],
    [/shop|cloth|mall|gift/, '🛍️', 'bg-pink-100 dark:bg-pink-500/20'],
    [/health|medic|pharma|doctor|clinic|vitamin/, '💊', 'bg-emerald-100 dark:bg-emerald-500/20'],
    [/entertain|movie|game|netflix|music|fun/, '🎮', 'bg-violet-100 dark:bg-violet-500/20'],
    [/school|tuition|book|educ/, '📚', 'bg-indigo-100 dark:bg-indigo-500/20'],
    [/pet|dog|cat/, '🐶', 'bg-stone-200 dark:bg-stone-500/20'],
    [/travel|flight|hotel|trip/, '✈️', 'bg-cyan-100 dark:bg-cyan-500/20'],
];

const look = computed(() => {
    const text = `${props.category ?? ''} ${props.merchant ?? ''}`.toLowerCase();

    return looks.find(([re]) => re.test(text)) ?? ([/./, '💸', 'bg-slate-100 dark:bg-slate-500/20'] as [RegExp, string, string]);
});
</script>

<template>
    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl leading-none" :class="[look[2], look[1].length > 3 ? 'text-xs' : 'text-lg']" aria-hidden="true">
        {{ look[1] }}
    </span>
</template>
