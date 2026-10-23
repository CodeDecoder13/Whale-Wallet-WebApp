<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Mascot from '@/components/Mascot.vue';

// a static, tilted replica of the real dashboard for the sign-in pages; the numbers are made up
const nav = ['Dashboard', 'Transactions', 'Cards', 'Bills & Subs', 'Forecast'];
const tiles = [
    { label: 'Income', amount: '₱58,895.70', tone: 'bg-emerald-500', fill: 'w-full' },
    { label: 'Fixed expenses', amount: '₱24,987.65', tone: 'bg-emerald-500', fill: 'w-2/5' },
    { label: 'Left to spend', amount: '₱36,175.70', tone: 'bg-emerald-500', fill: 'w-3/5' },
    { label: 'Spent this month', amount: '₱22,720.00', tone: 'bg-brand', fill: 'w-2/5' },
];
const bars = [38, 44, 40, 52, 47, 60, 66, 62, 70, 74, 71, 80];
</script>

<template>
    <div
        aria-hidden="true"
        class="pointer-events-none flex w-[660px] origin-top-left -rotate-6 overflow-hidden rounded-2xl bg-[#f3f6fb] text-navy shadow-[0_40px_80px_-30px_rgba(0,0,0,0.6)] ring-1 ring-white/20"
    >
        <div class="w-40 shrink-0 border-r bg-white p-4">
            <div class="flex items-center gap-2 text-sm font-semibold">
                <AppLogoIcon class="size-6" /> Whale Wallet
            </div>
            <ul class="mt-6 grid gap-1 text-xs">
                <li
                    v-for="(n, i) in nav"
                    :key="n"
                    class="rounded-lg px-3 py-2"
                    :class="i === 0 ? 'bg-brand text-white' : 'text-slate-500'"
                >
                    {{ n }}
                </li>
            </ul>
        </div>

        <div class="min-w-0 flex-1 p-5">
            <div class="text-lg font-semibold">Good afternoon</div>
            <div class="text-xs text-slate-500">Here's your money at a glance.</div>

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-linear-to-br from-navy to-navy-2 p-5 pl-28 text-white">
                <Mascot pose="hero" bare class="absolute bottom-0 left-2 w-24" />
                <div class="flex justify-between">
                    <div>
                        <div class="text-[10px] text-white/60">Safe to spend today</div>
                        <div class="text-xl font-semibold">₱483.94</div>
                        <div class="text-[10px] text-white/60">Over the next 30 days</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] text-white/60">Net worth</div>
                        <div class="text-3xl font-bold tracking-tight">₱1,975.70</div>
                    </div>
                </div>
                <div class="mt-4 flex h-14 items-end gap-1.5 rounded-xl bg-white/10 p-2">
                    <span
                        v-for="(h, i) in bars"
                        :key="i"
                        class="flex-1 rounded-sm bg-brand-light/80"
                        :style="{ height: h + '%' }"
                    />
                </div>
            </div>

            <div class="mt-4 grid grid-cols-4 gap-3">
                <div v-for="t in tiles" :key="t.label" class="rounded-xl bg-white p-3 shadow-sm">
                    <div class="text-[10px] text-slate-500">{{ t.label }}</div>
                    <div class="mt-1 text-sm font-semibold">{{ t.amount }}</div>
                    <div class="mt-2 h-1.5 rounded-full bg-slate-100">
                        <div class="h-full rounded-full" :class="[t.tone, t.fill]" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
