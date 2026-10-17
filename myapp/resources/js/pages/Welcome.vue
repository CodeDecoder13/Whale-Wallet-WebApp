<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import ForecastChart from '@/components/ForecastChart.vue';
import type { Point } from '@/components/ForecastChart.vue';
import { Button } from '@/components/ui/button';
import { formatPeso } from '@/lib/currency';
import { dashboard, login, register } from '@/routes';

// Example figures only (centavos), labelled as an example on the card.
const balances = [
    18_400, 17_120, 16_890, 15_240, 14_980, 13_310, 11_870, 11_620, 9_400,
    8_150, 6_980, 5_210, 3_640, 2_150,
].map((n) => n * 100);
const series: Point[] = balances.map((balance, i) => ({
    date: new Date(Date.now() + i * 86_400_000).toISOString(),
    balance,
}));
const lowest = series[series.length - 1];
const daysToGo = series.length - 1;
const safePerDay = Math.floor((balances[0] - lowest.balance) / daysToGo);
const day = (d: string) =>
    new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

const rise =
    'transition duration-700 ease-out starting:translate-y-4 starting:opacity-0 motion-reduce:transition-none';
const lift =
    'transition duration-200 hover:-translate-y-0.5 motion-reduce:transition-none';
</script>

<template>
    <Head title="Whale Wallet">
        <meta
            name="description"
            content="Whale Wallet shows what is safe to spend each day until payday, from your bills, subscriptions and spending."
        />
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 md:px-8"
        >
            <Link
                href="/"
                class="flex items-center"
                aria-label="Whale Wallet home"
            >
                <AppLogo />
            </Link>
            <nav class="flex items-center gap-2 text-sm">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="dashboard()">Dashboard</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Create account</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main>
            <section
                class="mx-auto grid max-w-6xl items-center gap-10 px-4 pt-10 pb-16 md:px-8 lg:grid-cols-[1.05fr_1fr] lg:gap-14 lg:pt-16 lg:pb-24"
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
                        Whale Wallet counts your bills, subscriptions and
                        spending, then tells you how much you can use each day.
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

                <div
                    :class="[rise, 'delay-150']"
                    class="relative overflow-hidden rounded-2xl bg-linear-to-br from-navy to-navy-2 p-7 text-white shadow-[0_24px_60px_-24px_rgba(11,37,69,0.55)] md:p-9"
                >
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">Safe to spend today</span>
                        <span class="text-xs text-white/70">Example</span>
                    </div>
                    <div
                        class="mt-3 text-6xl font-bold tracking-tight tabular-nums md:text-7xl"
                    >
                        {{ formatPeso(safePerDay) }}
                    </div>
                    <p class="mt-4 max-w-sm text-sm text-white/80">
                        Lowest balance before payday:
                        {{ formatPeso(lowest.balance) }} on
                        {{ day(lowest.date) }}, {{ daysToGo }} days to go.
                    </p>
                    <div class="mt-6">
                        <ForecastChart
                            :series="series"
                            compact
                            color="var(--color-brand-light)"
                        />
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 pb-20 md:px-8 lg:pb-28">
                <h2
                    class="max-w-xl text-3xl font-semibold tracking-tight text-balance md:text-4xl"
                >
                    Log money the way you already do.
                </h2>

                <div class="mt-10 grid gap-4 md:grid-cols-6">
                    <article
                        :class="lift"
                        class="flex flex-col justify-between gap-10 rounded-2xl bg-linear-to-br from-navy-2 to-navy p-7 text-white md:col-span-4 md:p-9"
                    >
                        <div>
                            <h3 class="text-xl font-medium">
                                Type it in Whale Chat
                            </h3>
                            <p class="mt-2 max-w-md text-sm text-white/80">
                                Send a short message and it becomes an expense
                                draft. You check the details, then tap Save.
                            </p>
                        </div>
                        <div class="font-mono text-sm">
                            <div class="text-white/60">You</div>
                            <div class="text-2xl font-medium md:text-3xl">
                                Jollibee 250
                            </div>
                            <div class="mt-4 text-white/60">Whale Chat</div>
                            <div class="max-w-md text-white/90">
                                Log ₱250.00 at Jollibee on GCash? Check the
                                details and tap Save.
                            </div>
                        </div>
                    </article>

                    <article
                        :class="lift"
                        class="rounded-2xl bg-brand-tint p-7 text-navy md:col-span-2 dark:bg-navy-2/50 dark:text-foreground"
                    >
                        <h3 class="text-xl font-medium">Snap a receipt</h3>
                        <p class="mt-2 text-sm opacity-80">
                            Upload a GCash or bank screenshot. The merchant,
                            amount, date and reference are read for you to
                            confirm.
                        </p>
                    </article>

                    <article
                        :class="lift"
                        class="rounded-2xl border bg-card p-7 md:col-span-2"
                    >
                        <h3 class="text-xl font-medium">
                            Bills and subscriptions
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            One list shows what is due in the next 14 days. Flip
                            any item between a bill and a subscription.
                        </p>
                    </article>

                    <article
                        :class="lift"
                        class="rounded-2xl bg-brand-deep p-7 text-white md:col-span-4 md:p-9"
                    >
                        <h3 class="text-xl font-medium">
                            Log your salary with proof
                        </h3>
                        <p class="mt-2 max-w-lg text-sm text-white/85">
                            Add a screenshot of the transfer. The reference
                            number, date and amount are read from it, and a
                            reference number can only be used once. What you
                            actually received replaces your expected income for
                            that month.
                        </p>
                    </article>
                </div>
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
                                Export everything as a file, or delete your
                                account and its data.
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
                    <h2
                        class="max-w-md text-3xl font-semibold tracking-tight text-balance md:text-4xl"
                    >
                        Start with your next payday.
                    </h2>
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

        <footer
            class="mx-auto max-w-6xl px-4 pb-10 text-sm text-muted-foreground md:px-8"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t pt-6"
            >
                <span>© {{ new Date().getFullYear() }} Whale Wallet</span>
                <Link
                    v-if="!$page.props.auth.user"
                    :href="login()"
                    class="hover:text-foreground"
                >
                    Log in
                </Link>
            </div>
        </footer>
    </div>
</template>
