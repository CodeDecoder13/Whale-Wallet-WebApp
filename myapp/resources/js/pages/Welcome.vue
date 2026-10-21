<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BrandLogo from '@/components/BrandLogo.vue';
import ForecastChart from '@/components/ForecastChart.vue';
import type { Point } from '@/components/ForecastChart.vue';
import Mascot from '@/components/Mascot.vue';
import { Button } from '@/components/ui/button';
import { cardGradient } from '@/lib/banks';
import { findBrand } from '@/lib/brandLogos';
import { formatPeso } from '@/lib/currency';
import { dashboard, login, register } from '@/routes';

// Example figures only (centavos), labelled as an example on the card.
const balances = [
    18_400, 17_120, 16_890, 15_240, 14_980, 13_310, 11_870, 11_620, 9_400,
    8_150, 6_980, 5_210, 3_640, 2_150,
].map((n) => n * 100);
const series: Point[] = balances.map((balance, i) => ({
    date: `2026-03-${String(10 + i).padStart(2, '0')}`,
    balance,
}));
const daysToGo = series.length - 1;
const safePerDay = Math.floor((balances[0] - balances[daysToGo]) / daysToGo);

// real marks only: the banks and wallets whose logos are bundled with the app
const partners = [
    'GCash',
    'BDO',
    'BPI',
    'Metrobank',
    'Landbank',
    'PNB',
    'UnionBank',
    'Security Bank',
    'EastWest',
    'PSBank',
    'GoTyme',
    'CIMB',
    'China Bank',
    'RCBC',
    'Maribank',
    'ShopeePay',
    'GrabPay',
].map((name) => ({ name, brand: findBrand(name) }));

const exampleBills = [
    { name: 'Converge - Wifi', due: '21 Oct', amount: 160_000 },
    { name: 'YouTube Premium', due: '23 Oct', amount: 18_900 },
    { name: 'Nmax V3 Standard', due: '27 Oct', amount: 900_000 },
];
const exampleCards = [
    { name: 'GCash', kind: 'Debit', color: '#007CFF', last: '8901' },
    { name: 'BDO', kind: 'Credit', color: '#0A3A8C', last: '4417' },
];

const rise =
    'transition duration-700 ease-out starting:translate-y-4 starting:opacity-0 motion-reduce:transition-none';
const lift =
    'transition duration-200 hover:-translate-y-0.5 motion-reduce:transition-none';
</script>

<template>
    <Head title="Whale Wallet">
        <meta
            name="description"
            content="Whale Wallet shows what is safe to spend each day until payday, with your cards, bills, loans and net worth in one place."
        />
    </Head>

    <div>
        <main>
            <!-- hero: the product's own headline numbers, net worth first -->
            <section
                class="mx-auto grid max-w-6xl grid-cols-[minmax(0,1fr)] items-center gap-12 px-4 pt-10 pb-16 md:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:gap-14 lg:pt-16 lg:pb-24"
            >
                <div :class="rise">
                    <h1
                        class="text-4xl leading-[1.05] font-semibold tracking-tight text-balance md:text-6xl"
                    >
                        Know what is safe to spend before payday.
                    </h1>
                    <p
                        class="mt-5 max-w-md text-base leading-relaxed text-muted-foreground"
                    >
                        Whale Wallet turns your cards, bills, loans and spending
                        into one clear number you can spend today.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Button v-if="$page.props.auth.user" size="lg" as-child>
                            <Link :href="dashboard()">Open dashboard</Link>
                        </Button>
                        <template v-else>
                            <Button size="lg" as-child>
                                <Link :href="register()">Create account</Link>
                            </Button>
                            <Button size="lg" variant="outline" as-child>
                                <Link :href="login()">Log in</Link>
                            </Button>
                        </template>
                    </div>
                </div>

                <div :class="[rise, 'delay-150']" class="relative">
                    <Mascot
                        pose="hero"
                        bare
                        class="pointer-events-none absolute -bottom-8 -left-4 z-10 hidden w-32 sm:block md:-left-10 md:w-40"
                    />
                    <div
                        class="relative overflow-hidden rounded-2xl bg-linear-to-br from-navy to-navy-2 p-6 pb-7 text-white shadow-[0_24px_60px_-24px_rgba(11,37,69,0.55)] sm:pl-28 md:p-8 md:pl-32"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-xs text-white/60">
                                    Safe to spend today
                                </div>
                                <div
                                    class="mt-1 text-2xl font-semibold tabular-nums"
                                >
                                    {{ formatPeso(safePerDay) }}
                                </div>
                                <div class="mt-1 text-xs text-white/60">
                                    Payday in {{ daysToGo }} days
                                </div>
                            </div>
                            <div class="text-right">
                                <div
                                    class="flex items-center justify-end gap-2 text-sm font-medium"
                                >
                                    <span
                                        class="size-2 rounded-full bg-brand-light"
                                    />
                                    Net worth
                                </div>
                                <div
                                    class="mt-1 text-4xl font-bold tracking-tight tabular-nums md:text-5xl"
                                >
                                    {{ formatPeso(4_840_000) }}
                                </div>
                                <div class="mt-1 text-xs text-white/60">
                                    Example numbers
                                </div>
                            </div>
                        </div>
                        <div
                            class="mt-6 min-w-0 rounded-xl bg-white/5 p-3 ring-1 ring-white/10"
                        >
                            <ForecastChart
                                :series="series"
                                compact
                                :height="96"
                                color="var(--color-brand-light)"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- the real preset list: picking one colours the card -->
            <section class="border-y bg-muted/40">
                <div class="mx-auto max-w-6xl px-4 py-12 md:px-8">
                    <p class="max-w-xl text-lg font-medium text-balance">
                        Made for how Filipinos pay. Your banks and e-wallets
                        keep their own logos and colors.
                    </p>
                    <ul class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <li
                            v-for="p in partners"
                            :key="p.name"
                            class="flex h-20 items-center justify-center rounded-xl bg-white p-4 ring-1 ring-black/10"
                        >
                            <img
                                v-if="p.brand?.image"
                                :src="p.brand.image"
                                :alt="p.name"
                                class="max-h-full max-w-full object-contain"
                            />
                            <svg
                                v-else-if="p.brand?.icon"
                                viewBox="0 0 24 24"
                                class="size-9"
                                role="img"
                                :aria-label="p.name"
                            >
                                <path
                                    :d="p.brand.icon.path"
                                    :fill="`#${p.brand.icon.hex}`"
                                />
                            </svg>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-20 md:px-8 lg:py-28">
                <h2
                    class="max-w-2xl text-3xl font-semibold tracking-tight text-balance md:text-4xl"
                >
                    Log money the way you already talk about it.
                </h2>

                <div
                    class="mt-10 grid grid-cols-[minmax(0,1fr)] gap-4 md:grid-cols-6"
                >
                    <!-- Whale Chat -->
                    <article
                        :class="lift"
                        class="flex flex-col justify-between gap-8 rounded-2xl bg-linear-to-br from-navy-2 to-navy p-7 text-white md:col-span-4 md:p-9"
                    >
                        <div>
                            <h3 class="text-xl font-medium">
                                Whale Chat does the typing
                            </h3>
                            <p class="mt-2 max-w-md text-sm text-white/80">
                                Log an expense, add a loan, record your salary
                                or mark a bill as paid. Nothing is saved until
                                you tap Save.
                            </p>
                        </div>
                        <div class="grid gap-2 text-sm">
                            <div class="ml-auto rounded-2xl bg-brand px-4 py-2">
                                paid converge from BDO
                            </div>
                            <div
                                class="flex max-w-md items-start gap-2 rounded-2xl bg-white/10 px-3 py-2"
                            >
                                <Mascot
                                    pose="happy"
                                    round
                                    class="size-7 shrink-0"
                                />
                                Mark Converge - Wifi as paid: ₱1,600.00 from
                                BDO? The next due date moves forward.
                            </div>
                            <div class="ml-auto rounded-2xl bg-brand px-4 py-2">
                                I got my salary
                            </div>
                            <div
                                class="flex max-w-md items-start gap-2 rounded-2xl bg-white/10 px-3 py-2"
                            >
                                <Mascot
                                    pose="happy"
                                    round
                                    class="size-7 shrink-0"
                                />
                                Nice! How much did you receive? Then attach a
                                screenshot of the transfer as proof.
                            </div>
                        </div>
                    </article>

                    <!-- receipts and proof -->
                    <article
                        :class="lift"
                        class="flex flex-col gap-4 rounded-2xl bg-brand-tint p-7 text-navy md:col-span-2 dark:bg-navy-2/50 dark:text-foreground"
                    >
                        <Mascot pose="thinking" round class="size-14" />
                        <div>
                            <h3 class="text-xl font-medium">Snap a receipt</h3>
                            <p class="mt-2 text-sm opacity-80">
                                Upload a GCash or bank screenshot. The merchant,
                                amount, date and reference are read for you to
                                confirm. Salary needs a proof screenshot, and a
                                reference number works only once.
                            </p>
                        </div>
                    </article>

                    <!-- bills, subscriptions, loans -->
                    <article
                        :class="lift"
                        class="rounded-2xl border bg-card p-7 md:col-span-2"
                    >
                        <h3 class="text-xl font-medium">
                            Bills, subscriptions and loans
                        </h3>
                        <ul class="mt-4 divide-y text-sm">
                            <li
                                v-for="b in exampleBills"
                                :key="b.name"
                                class="flex items-center gap-3 py-2.5"
                            >
                                <BrandLogo :name="b.name" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">
                                        {{ b.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ b.due }}
                                    </div>
                                </div>
                                <span class="tabular-nums">{{
                                    formatPeso(b.amount)
                                }}</span>
                            </li>
                        </ul>
                        <p class="mt-4 text-sm text-muted-foreground">
                            Mark one paid and its next due date moves. Loans end
                            after 1, 2, 3, 6 or 12 payments.
                        </p>
                    </article>

                    <!-- cards and net worth -->
                    <article
                        :class="lift"
                        class="grid gap-8 rounded-2xl border bg-card p-7 md:col-span-4 md:grid-cols-2 md:p-9"
                    >
                        <div class="grid content-start gap-3">
                            <div
                                v-for="c in exampleCards"
                                :key="c.name"
                                class="rounded-xl p-4 text-white"
                                :style="{ background: cardGradient(c.color) }"
                            >
                                <div class="flex items-center gap-3 text-sm">
                                    <BrandLogo :name="c.name" />
                                    <span class="flex-1 font-medium">{{
                                        c.name
                                    }}</span>
                                    <span
                                        class="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-medium"
                                    >
                                        {{ c.kind }}
                                    </span>
                                </div>
                                <div class="mt-3 text-xs opacity-90">
                                    Rhuzzel Paramio · •••• {{ c.last }}
                                </div>
                            </div>
                        </div>
                        <div class="self-center">
                            <h3 class="text-xl font-medium">
                                Cards that look like your banks
                            </h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Add the account holder, number and QR so you can
                                share your details in one tap. Net worth adds
                                your cards and savings pods, then subtracts
                                loans and credit card balances.
                            </p>
                        </div>
                    </article>
                </div>

                <dl class="mt-14 grid gap-x-12 gap-y-8 text-sm md:grid-cols-2">
                    <div>
                        <dt class="font-medium">Payday forecast</dt>
                        <dd class="mt-1 text-muted-foreground">
                            Set your payday once. Whale Wallet projects your
                            balance up to it and shows the lowest point.
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium">Savings pods</dt>
                        <dd class="mt-1 text-muted-foreground">
                            Set aside money for a goal and watch each pod fill
                            toward its target.
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium">Whale tips</dt>
                        <dd class="mt-1 text-muted-foreground">
                            Short suggestions based on your totals and forecast,
                            never on made-up numbers.
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium">Welcome back summary</dt>
                        <dd class="mt-1 text-muted-foreground">
                            Each time you sign in, a quick look at what you can
                            spend and which bills are next.
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="border-y bg-muted/40">
                <div
                    class="mx-auto grid max-w-6xl gap-10 px-4 py-16 md:px-8 lg:grid-cols-2 lg:gap-20 lg:py-24"
                >
                    <h2
                        class="text-3xl font-semibold tracking-tight text-balance md:text-4xl"
                    >
                        Nothing saves until you say so.
                    </h2>
                    <div class="grid gap-6 text-sm leading-relaxed">
                        <p>
                            <span class="block font-medium text-foreground">
                                AI drafts, you confirm.
                            </span>
                            <span class="text-muted-foreground">
                                Receipts and chat messages only become records
                                after you tap Save. Tips, receipt reading and
                                chat use Google Gemini, and you agree before the
                                first use.
                            </span>
                        </p>
                        <p>
                            <span class="block font-medium text-foreground">
                                Your data stays yours.
                            </span>
                            <span class="text-muted-foreground">
                                Account numbers are stored encrypted. Export
                                everything as a file, or delete your account and
                                its data.
                            </span>
                        </p>
                        <p>
                            <span class="block font-medium text-foreground">
                                Images do not pile up.
                            </span>
                            <span class="text-muted-foreground">
                                Receipt images are deleted after you confirm
                                unless you choose to keep them. Salary proofs
                                stay until you remove that income entry.
                            </span>
                        </p>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-20 md:px-8 lg:py-28">
                <div
                    class="flex flex-col items-start justify-between gap-8 rounded-2xl bg-linear-to-br from-navy to-navy-2 p-8 text-white md:flex-row md:items-center md:p-12"
                >
                    <div class="flex items-center gap-6">
                        <Mascot
                            pose="excited"
                            class="hidden size-24 shrink-0 sm:block"
                        />
                        <h2
                            class="max-w-md text-3xl font-semibold tracking-tight text-balance md:text-4xl"
                        >
                            Start with your next payday.
                        </h2>
                    </div>
                    <Button
                        v-if="!$page.props.auth.user"
                        size="lg"
                        class="bg-white text-navy hover:bg-white/90"
                        as-child
                    >
                        <Link :href="register()">Create account</Link>
                    </Button>
                    <Button
                        v-else
                        size="lg"
                        class="bg-white text-navy hover:bg-white/90"
                        as-child
                    >
                        <Link :href="dashboard()">Open dashboard</Link>
                    </Button>
                </div>
            </section>
        </main>
    </div>
</template>
