<script setup lang="ts">
import { VisDonut, VisSingleContainer } from '@unovis/vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { PiggyBank, Receipt, ShoppingCart, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import ForecastChart from '@/components/ForecastChart.vue';
import BrandLogo from '@/components/BrandLogo.vue';
import HelpTip from '@/components/HelpTip.vue';
import IncomeDialog from '@/components/IncomeDialog.vue';
import Mascot from '@/components/Mascot.vue';
import WelcomeBackDialog from '@/components/WelcomeBackDialog.vue';
import type { Point } from '@/components/ForecastChart.vue';
import { formatPeso } from '@/lib/currency';
import { dueTone } from '@/lib/dueTone';
import { dashboard, forecast as forecastPage } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { index as tipsPage } from '@/routes/tips';
import { index as transactionsPage } from '@/routes/transactions';

type Slice = { name: string; total: number };

const props = defineProps<{
    summary: {
        income: number;
        fixed: number;
        paid_fixed: number;
        next_income: number | null; // salary still to come when not paid yet this month
        spent: number;
        safe: number;
    };
    forecast: {
        days: number;
        payday: string;
        at_payday: number;
        series: Point[];
        lowest: { date: string; amount: number };
        safe_per_day: number;
    };
    net_worth: { assets: number; debts: number; total: number };
    consented: boolean;
    welcome: boolean;
    cards: { id: number; name: string }[];
    income_entries: {
        id: number;
        name: string;
        kind: string;
        amount: number;
        reference: string | null;
        received_on: string | null;
    }[];
    income_proofs: {
        id: number;
        status: 'queued' | 'parsing' | 'parsed' | 'failed' | 'confirmed';
        error: string | null;
        claimed: number | null;
        draft: {
            amount: number;
            occurred_at: string;
            reference: string | null;
            merchant: string;
        } | null;
    }[];
    week: { day: string; amount: number }[];
    tips: { id: number; text: string }[];
    categories: Slice[];
    upcoming: {
        id: number;
        name: string;
        amount: number;
        next_due_on: string;
        logo: string | null;
    }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

const color = (i: number) => `var(--chart-${(i % 5) + 1})`;
const total = computed(() => props.categories.reduce((n, c) => n + c.total, 0));

const pctOfIncome = (n: number) =>
    props.summary.income ? Math.min(100, (n / props.summary.income) * 100) : 0;

type Tile = {
    key: 'income' | 'fixed' | 'safe' | 'spent';
    title: string;
    icon: typeof Wallet;
    color: string; // the tile's accent: icon, bars and status
    amount: number;
    status: string;
    hint: string;
    help: string; // plain-words tooltip on the "?"
    bars?: number[]; // 0 to 1, drawn as columns
    fill?: number; // 0 to 1, drawn as a single bar
    pace?: number; // 0 to 1, a tick on that bar
};

const GREEN = '#22c55e';
const YELLOW = '#eab308';
const RED = '#ef4444';
const BLUE = 'var(--color-brand)';

// what you could have spent by today if you spread the free money evenly over the month
const pace = computed(() => {
    const d = new Date();
    const days = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();

    return Math.max(0, props.summary.income - props.summary.fixed) * (d.getDate() / days);
});
const weekMax = computed(() => Math.max(1, ...props.week.map((w) => w.amount)));
const unit = (n: number) => Math.min(1, Math.max(0, n));

const tiles = computed<Tile[]>(() => {
    const { income, fixed, spent, safe } = props.summary;
    const fixedPct = income ? (fixed / income) * 100 : 0;
    const received = props.income_entries.some((e) => e.kind === 'salary');
    const safeTone =
        safe < 0 ? ['Overspent', RED] : income && safe < income * 0.2 ? ['Tight', YELLOW] : ['On track', GREEN];
    const fixedTone =
        fixedPct < 50 ? ['Healthy', GREEN] : fixedPct < 70 ? ['Heavy', YELLOW] : ['Very heavy', RED];
    const spentTone =
        spent === 0 ? ['Nothing logged yet', BLUE] : spent <= pace.value ? ['Under pace', GREEN] : ['Over pace', RED];

    return [
        {
            key: 'income', title: 'Income', icon: Wallet, color: received ? GREEN : BLUE, amount: income,
            status: received ? 'Received' : props.summary.next_income !== null ? 'Expected next cycle' : 'Expected',
            hint: received ? 'Received this month' : props.summary.next_income !== null ? `${formatPeso(props.summary.next_income)} expected on your next pay` : 'Expected this month',
            help: 'Your pay for this month. It shows as Expected until you log it with + Add income. If you were not paid yet when you signed up, it starts counting next month.',
            fill: received ? 1 : 0,
        },
        {
            key: 'fixed', title: 'Fixed expenses', icon: Receipt, color: fixedTone[1], amount: fixed,
            status: fixedTone[0],
            help: 'Every bill, subscription and loan payment you owe in a month. The bar shows how much of your income they take.',
            hint: `${formatPeso(props.summary.paid_fixed)} paid · ${pctOfIncome(fixed).toFixed(1)}% of income`, fill: unit(fixed / (income || 1)), pace: 0.5,
        },
        {
            key: 'safe', title: 'Left to spend', icon: PiggyBank, color: safeTone[1], amount: safe,
            status: safeTone[0],
            help: 'Your income this month, minus what you spent and the bills still unpaid. It is 0 until your pay counts.',
            hint: props.forecast.safe_per_day > 0 ? `About ${formatPeso(props.forecast.safe_per_day)} a day until payday` : 'Income minus what you spent',
            fill: unit(safe / (income || 1)),
        },
        {
            key: 'spent', title: 'Spent this month', icon: ShoppingCart, color: spentTone[1], amount: spent,
            status: spentTone[0],
            help: 'Everything you logged as an expense this month. Bills you paid are included. The small bars show the last 7 days.',
            hint: `${pctOfIncome(spent).toFixed(1)}% of income`,
            fill: unit(spent / (income || 1)),
            bars: props.week.map((w) => w.amount / weekMax.value),
        },
    ];
});
const panel = ref<Tile['key'] | null>(null);
const panelOpen = computed({ get: () => panel.value !== null, set: (v) => !v && (panel.value = null) });
const panelTile = computed(() => tiles.value.find((t) => t.key === panel.value));

const left = computed(() =>
    Math.max(
        0,
        props.summary.income - props.summary.fixed - (props.summary.spent - props.summary.paid_fixed), // paid bills are in both fixed and spent
    ),
);
const split = computed(() =>
    [
        ['Fixed expenses', props.summary.fixed, 'bg-brand-deep'],
        ['Spending', props.summary.spent, 'bg-brand'],
        ['Left over', left.value, 'bg-brand-light'],
    ].map(([label, amount, bar]) => ({
        label: label as string,
        amount: amount as number,
        bar: bar as string,
        pct: pctOfIncome(amount as number),
    })),
);

const welcomeOpen = ref(props.welcome);

// ?income=1 (from Whale Chat) opens the dialog so a proof sent there can be confirmed
const incomeOpen = ref(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('income'),
);

const user = computed(() => usePage().props.auth.user);
const firstName = computed(() => user.value.name.split(' ')[0]);
const hour = new Date().getHours();
const greeting =
    hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
const dueDate = (d: string) =>
    new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-5 p-4 md:p-8">
        <div>
            <h1 class="text-3xl font-medium tracking-tight">
                {{ greeting }}, {{ firstName }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Here's your money at a glance.
            </p>
        </div>

        <div
            class="grid grid-cols-[minmax(0,1fr)] items-center gap-6 overflow-hidden rounded-2xl bg-linear-to-br from-navy to-navy-2 p-6 text-white sm:grid-cols-[auto_minmax(0,1fr)] md:gap-8 md:p-8"
        >
            <Mascot
                pose="hero"
                bare
                class="pointer-events-none hidden w-36 shrink-0 sm:block md:w-48"
            />

            <div class="min-w-0">
                <!-- net worth leads; safe to spend and payday flank it -->
                <div
                    class="grid items-end gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.7fr)]"
                >
                    <div class="order-2 lg:order-1">
                        <div class="flex items-center gap-1.5 text-xs text-white/60">
                            Safe to spend today
                            <HelpTip text="How much you can spend each day until payday and still pay your bills. It is your income left after spending and unpaid bills, split over the days to payday." />
                        </div>
                        <div class="mt-1 text-3xl font-semibold tracking-tight">
                            {{ formatPeso(forecast.safe_per_day) }}
                        </div>
                        <p class="mt-1 text-xs text-white/60">
                            {{
                                forecast.safe_per_day === 0 && forecast.lowest.amount < 0
                                    ? 'Bills outrun your balance before payday.'
                                    : `Over the next ${forecast.days} ${forecast.days === 1 ? 'day' : 'days'}.`
                            }}
                        </p>
                    </div>

                    <div class="order-1 lg:order-3 lg:text-right">
                        <div class="flex items-center gap-2 text-sm font-medium lg:justify-end">
                            <span class="size-2 rounded-full bg-brand-light" />
                            Net worth
                            <HelpTip text="Everything you have minus everything you owe, today. It counts your whole remaining loan balance, not just this month's payments, so it can start negative. It goes up as you add cash and savings, and as you pay your loans down." />
                        </div>
                        <div
                            class="mt-1 text-5xl font-bold tracking-tight md:text-6xl"
                            :class="net_worth.total < 0 && 'text-warm'"
                        >
                            {{ formatPeso(net_worth.total) }}
                        </div>
                        <p class="mt-2 flex flex-wrap items-center gap-x-1.5 text-xs text-white/60 lg:justify-end">
                            Assets {{ formatPeso(net_worth.assets) }}
                            <HelpTip text="What you own: the balances on your cash, bank and e-wallet cards, your savings pods, and any credit left in credit cards. Add your real balances in Cards to raise it." />
                            · Debts {{ formatPeso(net_worth.debts) }}
                            <HelpTip text="What you owe: the full remaining amount on every active loan (payments left × amount), plus credit card balances you owe. Bills and subscriptions are not debt. Each loan payment you make lowers it." />
                        </p>
                    </div>

                    <div class="order-3 lg:order-2 lg:text-center">
                        <div class="flex items-center gap-1.5 text-xs text-white/60 lg:justify-center">
                            Payday in
                            <HelpTip text="Days until your next pay. Change your pay days in Settings if this is wrong." />
                        </div>
                        <div class="mt-1 text-3xl font-semibold tracking-tight">
                            {{ forecast.days }}
                            {{ forecast.days === 1 ? 'day' : 'days' }}
                        </div>
                        <p class="mt-1 text-xs text-white/60">
                            {{ dueDate(forecast.payday) }} ·
                            <Link
                                :href="`${profileEdit().url}#payday`"
                                class="underline underline-offset-4 hover:text-white"
                            >
                                Change
                            </Link>
                        </p>
                    </div>
                </div>

                <div class="mt-6 rounded-xl bg-white/5 p-4 ring-1 ring-white/10">
                    <div
                        class="mb-2 flex flex-wrap items-baseline justify-between gap-x-4 text-xs text-white/60"
                    >
                        <span class="flex items-center gap-1.5">
                            Projected balance until payday
                            <HelpTip text="Your cash day by day until payday: today's card balances, minus bills as they fall due, minus your safe-to-spend each day. Below 0 means bills outrun your cash." />
                        </span>
                        <span>
                            Lowest
                            <span
                                class="font-medium"
                                :class="forecast.lowest.amount < 0 ? 'text-warm' : 'text-white'"
                            >
                                {{ formatPeso(forecast.lowest.amount) }}
                            </span>
                            on {{ dueDate(forecast.lowest.date) }} · At payday
                            <span class="font-medium text-white">
                                {{ formatPeso(forecast.at_payday) }}
                            </span>
                        </span>
                    </div>
                    <ForecastChart
                        :series="forecast.series"
                        compact
                        :height="96"
                        color="var(--color-brand-light)"
                    />
                </div>

                <Link
                    :href="forecastPage()"
                    class="mt-4 inline-block text-sm font-medium text-white underline underline-offset-4"
                >
                    See the full forecast
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <button
                v-for="t in tiles"
                :key="t.key"
                type="button"
                class="group flex flex-col items-stretch justify-start rounded-2xl border bg-card p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5"
                @click="panel = t.key"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-2 text-sm font-medium">
                        <component :is="t.icon" class="size-4 shrink-0" :style="{ color: t.color }" />
                        <span class="truncate">{{ t.title }}</span>
                        <HelpTip :text="t.help" />
                    </span>
                    <span class="hidden shrink-0 text-xs text-muted-foreground sm:inline">This month</span>
                </div>
                <div class="mt-4 flex min-h-14 items-end justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-xl font-semibold sm:text-2xl">{{ formatPeso(t.amount) }}</div>
                        <div class="mt-1 text-xs font-medium" :style="{ color: t.color }">{{ t.status }}</div>
                    </div>
                    <!-- last 7 days, today solid -->
                    <div v-if="t.bars" class="flex h-14 shrink-0 items-end gap-1.5" role="img" aria-label="Spent per day, last 7 days">
                        <div
                            v-for="(b, i) in t.bars"
                            :key="i"
                            class="flex h-full w-2.5 items-end rounded-full bg-muted"
                            :title="`${week[i].day}: ${formatPeso(week[i].amount)}`"
                        >
                            <div
                                class="w-full rounded-full"
                                :style="{ height: `${Math.max(10, b * 100)}%`, background: t.color, opacity: i === t.bars.length - 1 ? 1 : 0.55 }"
                            />
                        </div>
                    </div>
                </div>
                <div class="mt-auto" />
                <div v-if="t.fill !== undefined" class="relative mt-3 h-1.5 rounded-full bg-muted" aria-hidden="true">
                    <div class="h-full rounded-full" :style="{ width: `${t.fill * 100}%`, background: t.color }" />
                    <div v-if="t.pace" class="absolute -top-0.5 h-2.5 w-0.5 bg-foreground/40" :style="{ left: `${t.pace * 100}%` }" />
                </div>
                <div class="mt-2 text-xs text-muted-foreground">{{ t.hint }}</div>
                <span
                    v-if="t.key === 'income'"
                    role="button"
                    class="mt-2 inline-block text-xs font-medium underline underline-offset-4"
                    @click.stop="incomeOpen = true"
                >
                    + Add income
                </span>
                <Link
                    v-else-if="t.key === 'spent' && t.amount === 0"
                    :href="transactionsPage()"
                    class="mt-2 inline-block text-xs font-medium underline underline-offset-4"
                    @click.stop
                >
                    + Add expense
                </Link>
                <span v-else class="mt-2 block h-4" aria-hidden="true" />
            </button>
        </div>

        <Sheet v-model:open="panelOpen">
            <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{{ panelTile?.title }}</SheetTitle>
                    <SheetDescription>{{ panelTile?.hint }}</SheetDescription>
                </SheetHeader>
                <div v-if="panelTile" class="grid gap-5 px-4 pb-6 text-sm">
                    <div class="text-3xl font-semibold">{{ formatPeso(panelTile.amount) }}</div>
                    <ul v-if="panel === 'income'" class="divide-y rounded-xl border">
                        <li v-for="e in income_entries" :key="e.id" class="flex justify-between p-3">
                            <span>{{ e.name }}</span><span class="font-medium">{{ formatPeso(e.amount) }}</span>
                        </li>
                        <li v-if="!income_entries.length" class="p-3 text-muted-foreground">No income logged this month yet.</li>
                    </ul>
                    <ul v-else-if="panel === 'spent'" class="divide-y rounded-xl border">
                        <li v-for="c in categories" :key="c.name" class="flex justify-between p-3">
                            <span>{{ c.name }}</span><span class="font-medium">{{ formatPeso(c.total) }}</span>
                        </li>
                        <li v-if="!categories.length" class="p-3 text-muted-foreground">Nothing spent yet this month.</li>
                    </ul>
                    <ul v-else class="divide-y rounded-xl border">
                        <li v-for="s in split" :key="s.label" class="flex justify-between p-3">
                            <span>{{ s.label }}</span><span class="font-medium">{{ formatPeso(s.amount) }} · {{ s.pct.toFixed(0) }}%</span>
                        </li>
                    </ul>
                    <Button v-if="panel === 'income'" @click="((panel = null), (incomeOpen = true))">+ Add income</Button>
                    <Button v-else-if="panel === 'spent'" as-child>
                        <Link :href="transactionsPage()">Open transactions</Link>
                    </Button>
                </div>
            </SheetContent>
        </Sheet>

        <WelcomeBackDialog
            v-model:open="welcomeOpen"
            :name="firstName"
            :left="summary.safe"
            :forecast="forecast"
            :upcoming="upcoming"
        />

        <IncomeDialog
            v-model:open="incomeOpen"
            :consented="consented"
            :entries="income_entries"
            :cards="cards"
            :proofs="income_proofs"
        />

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-6">
                <h2 class="flex items-center gap-1.5 font-medium">
                    Where each peso goes
                    <HelpTip text="Splits this month's income into fixed bills, what you spent, and what is left over." />
                </h2>
                <p class="mb-5 text-xs text-muted-foreground">
                    Monthly income allocation
                </p>
                <p v-if="!summary.income" class="text-sm text-muted-foreground">
                    Add income to see your allocation.
                </p>
                <template v-else>
                    <div class="flex h-3 overflow-hidden rounded-full bg-muted">
                        <div
                            v-for="s in split"
                            :key="s.label"
                            :class="s.bar"
                            :style="{ width: `${s.pct}%` }"
                        />
                    </div>
                    <ul class="mt-4 grid gap-3 text-sm">
                        <li
                            v-for="s in split"
                            :key="s.label"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="size-2.5 rounded-full"
                                :class="s.bar"
                            />
                            <span class="flex-1">{{ s.label }}</span>
                            <span class="text-muted-foreground">
                                {{ formatPeso(s.amount) }}
                            </span>
                            <span class="w-12 text-right font-medium">
                                {{ s.pct.toFixed(1) }}%
                            </span>
                        </li>
                    </ul>
                </template>
            </section>

            <section class="rounded-xl border bg-card p-6">
                <h2 class="flex items-center gap-1.5 font-medium">
                    Spending by category
                    <HelpTip text="Your expenses this month grouped by category, so you can see where the money went." />
                </h2>
                <p class="mb-5 text-xs text-muted-foreground">
                    Where your spending goes
                </p>
                <p
                    v-if="!categories.length"
                    class="text-sm text-muted-foreground"
                >
                    No spending logged this month.
                </p>
                <div v-else class="flex flex-wrap items-center gap-6">
                    <VisSingleContainer
                        :data="categories"
                        :height="180"
                        class="w-44"
                    >
                        <VisDonut
                            :value="(d: Slice) => d.total"
                            :color="(_: Slice, i: number) => color(i)"
                            :arc-width="26"
                            :central-label="formatPeso(total)"
                            central-sub-label="spent this month"
                        />
                    </VisSingleContainer>
                    <ul class="min-w-48 flex-1 space-y-2 text-sm">
                        <li
                            v-for="(c, i) in categories"
                            :key="c.name"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="size-2.5 rounded-full"
                                :style="{ background: color(i) }"
                            />
                            <span class="flex-1">{{ c.name }}</span>
                            <span class="font-medium">
                                {{ formatPeso(c.total) }}
                            </span>
                            <span class="w-10 text-right text-muted-foreground">
                                {{ Math.round((c.total / total) * 100) }}%
                            </span>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <section class="rounded-xl border bg-card p-6">
            <h2 class="flex items-center gap-1.5 font-medium">
                Upcoming bills
                <HelpTip text="Bills, subscriptions and loan payments that are overdue or due in the next 14 days. Overdue ones are not counted in the forecast until you pay them." />
            </h2>
            <p class="mb-3 text-xs text-muted-foreground">
                Overdue and due in the next 14 days
            </p>
            <p v-if="!upcoming.length" class="text-sm text-muted-foreground">
                Nothing due soon.
            </p>
            <ul v-else class="divide-y text-sm">
                <li
                    v-for="b in upcoming"
                    :key="b.id"
                    class="flex items-center gap-3 py-3"
                >
                    <BrandLogo :name="b.name" :logo="b.logo" />
                    <div class="flex-1">
                        <div class="font-medium">{{ b.name }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ dueDate(b.next_due_on) }}
                        </div>
                    </div>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :style="{
                            color: dueTone(b.next_due_on).c,
                            background: `color-mix(in srgb, ${dueTone(b.next_due_on).c} 15%, transparent)`,
                        }"
                    >
                        {{ dueTone(b.next_due_on).text }}
                    </span>
                    <span class="w-28 text-right font-semibold">{{
                        formatPeso(b.amount)
                    }}</span>
                </li>
            </ul>
        </section>

        <section class="rounded-xl border bg-card p-6">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-1.5 font-medium">
                    Whale tips
                    <HelpTip text="Short, personal suggestions from Whale based on your own numbers." />
                </h2>
                <Link
                    :href="tipsPage()"
                    class="text-xs font-medium text-brand-deep dark:text-brand-light"
                >
                    All tips
                </Link>
            </div>
            <p v-if="!tips.length" class="mt-3 text-sm text-muted-foreground">
                No tips yet. Open Tips to generate some.
            </p>
            <ul v-else class="mt-3 grid gap-2 text-sm">
                <li v-for="t in tips" :key="t.id" class="flex gap-2">
                    <span
                        class="mt-2 size-1.5 shrink-0 rounded-full bg-brand"
                    />
                    {{ t.text }}
                </li>
            </ul>
            <p class="mt-3 text-xs text-muted-foreground">
                Budgeting guidance, not financial advice.
            </p>
        </section>

        <p class="text-xs text-muted-foreground">
            Budgeting guidance based on the numbers you entered, not licensed
            financial advice.
        </p>
    </div>
</template>
