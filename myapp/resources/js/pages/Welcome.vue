<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { animate, createScope, onScroll, stagger } from 'animejs';
import { onMounted, onUnmounted, ref, useTemplateRef } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import ForecastChart from '@/components/ForecastChart.vue';
import type { Point } from '@/components/ForecastChart.vue';
import Mascot from '@/components/Mascot.vue';
import { Button } from '@/components/ui/button';
import { cardGradient } from '@/lib/banks';
import { findBrand } from '@/lib/brandLogos';
import { formatPeso } from '@/lib/currency';
import { dashboard, login, register } from '@/routes';

defineProps<{ faq: [string, string][] }>();

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

// hover glow only: anime owns transform and opacity, so no CSS transition on those
const lift =
    'transition-[box-shadow,border-color] duration-300 hover:border-brand/40 hover:shadow-[0_24px_60px_-30px_rgba(30,136,229,0.55)]';

const newFeatures = [
    {
        title: 'Overdue stays overdue',
        body: 'An unpaid bill, subscription or loan stays on your list until you mark it paid. It never slips quietly into next month.',
    },
    {
        title: 'Notes on every item',
        body: 'Keep the plate number, account number or who to call right on the bill.',
    },
    {
        title: 'Side income',
        body: 'Add money earned outside your salary and pick the card that receives it.',
    },
];
const moreFeatures = [
    {
        title: 'Payday forecast',
        body: 'Set your payday once. Whale Wallet projects your balance up to it and shows the lowest point.',
    },
    {
        title: 'Savings pods',
        body: 'Set aside money for a goal and watch each pod fill toward its target.',
    },
    {
        title: 'Whale tips',
        body: 'Short suggestions based on your totals and forecast, never on made-up numbers.',
    },
    {
        title: 'Welcome back summary',
        body: 'Each time you sign in, a quick look at what you can spend and which bills are next.',
    },
    {
        title: 'Daily email digest',
        body: 'Get a summary in your inbox at the time you choose.',
    },
    {
        title: 'Hide for now',
        body: 'Leave an item out of your totals to see how things look without it, then bring it back.',
    },
    {
        title: 'PIN sign-in',
        body: 'Use a 6-digit PIN instead of your password.',
    },
    {
        title: 'Import and export',
        body: 'Take your data with you as a file, or load it back in.',
    },
    { title: 'Support chat', body: 'Message the team from inside the app.' },
];
const lendSteps = [
    'Details emailed when you lend',
    'Reminder before the due date',
    'Reminder on the due date',
    'Every 7 days once overdue',
];

const netWorth = 4_840_000;
const shownNetWorth = ref(netWorth);
const root = useTemplateRef<HTMLElement>('root');
let scope: ReturnType<typeof createScope> | undefined;

onMounted(() => {
    scope = createScope({
        root: root.value!,
        mediaQueries: { still: '(prefers-reduced-motion: reduce)' },
    }).add((self) => {
        if (self!.matches.still) {
            shownNetWorth.value = netWorth;

            return;
        }

        animate('[data-hero]', {
            opacity: [0, 1],
            y: [28, 0],
            delay: stagger(110),
            duration: 900,
            ease: 'outExpo',
        });

        const count = { v: 0 };
        animate(count, {
            v: netWorth,
            duration: 1600,
            delay: 300,
            ease: 'outExpo',
            onUpdate: () => (shownNetWorth.value = Math.round(count.v)),
        });

        animate('[data-float]', {
            y: [0, -10],
            duration: 2600,
            loop: true,
            alternate: true,
            ease: 'inOutSine',
        });
        animate('[data-glow]', {
            scale: [1, 1.2],
            opacity: [0.5, 0.9],
            duration: 5000,
            delay: stagger(1200),
            loop: true,
            alternate: true,
            ease: 'inOutSine',
        });

        root.value!.querySelectorAll<HTMLElement>('[data-reveal]').forEach(
            (el) => {
                animate(el, {
                    opacity: [0, 1],
                    y: [32, 0],
                    duration: 800,
                    ease: 'outExpo',
                    autoplay: onScroll({ target: el, repeat: false }),
                });
            },
        );
    });
});
onUnmounted(() => scope?.revert());
</script>

<template>
    <Head title="Whale Wallet" />

    <div ref="root">
        <main class="overflow-x-clip">
            <!-- hero: the product's own headline numbers, net worth first -->
            <section class="px-3 pt-3 md:px-5">
                <div
                    class="relative isolate overflow-hidden rounded-3xl bg-linear-to-br from-[#06142a] via-navy to-navy-2 text-white ring-1 ring-white/10"
                >
                    <div
                        data-glow
                        aria-hidden="true"
                        class="absolute -top-40 -right-24 -z-10 size-[30rem] rounded-full bg-brand/30 blur-3xl"
                    />
                    <div
                        data-glow
                        aria-hidden="true"
                        class="absolute -bottom-48 -left-32 -z-10 size-[28rem] rounded-full bg-brand-light/20 blur-3xl"
                    />
                    <div
                        class="mx-auto grid max-w-6xl grid-cols-[minmax(0,1fr)] items-center gap-12 px-5 pt-12 pb-16 md:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:gap-14 lg:pt-20 lg:pb-24"
                    >
                        <div>
                            <h1
                                data-hero
                                class="bg-linear-to-b from-white to-brand-tint bg-clip-text text-transparent text-4xl leading-[1.05] font-semibold tracking-tight text-balance md:text-6xl"
                            >
                                Know what is safe to spend before payday.
                            </h1>
                            <p
                                data-hero
                                class="mt-5 max-w-md text-base leading-relaxed text-white/75"
                            >
                                Whale Wallet turns your cards, bills, loans and
                                spending into one clear number you can spend
                                today.
                            </p>
                            <div data-hero class="mt-8 flex flex-wrap gap-3">
                                <Button
                                    v-if="$page.props.auth.user"
                                    size="lg"
                                    as-child
                                >
                                    <Link :href="dashboard()"
                                        >Open dashboard</Link
                                    >
                                </Button>
                                <template v-else>
                                    <Button size="lg" as-child>
                                        <Link :href="register()"
                                            >Create account</Link
                                        >
                                    </Button>
                                    <Button
                                        size="lg"
                                        variant="outline"
                                        class="border-white/25 bg-white/5 text-white hover:bg-white/15 hover:text-white dark:border-white/25 dark:bg-white/5 dark:hover:bg-white/15"
                                        as-child
                                    >
                                        <Link :href="login()">Log in</Link>
                                    </Button>
                                </template>
                            </div>
                        </div>

                        <div data-hero class="relative">
                            <Mascot
                                data-float
                                pose="hero"
                                bare
                                class="pointer-events-none relative z-10 mx-auto -mb-8 w-28 sm:absolute sm:-bottom-8 sm:-left-4 sm:mx-0 sm:mb-0 sm:w-32 md:-left-10 md:w-40"
                            />
                            <div
                                class="relative overflow-hidden rounded-2xl bg-white/[0.07] p-6 pb-7 text-white shadow-[0_32px_80px_-30px_rgba(0,0,0,0.8)] ring-1 ring-white/15 backdrop-blur-xl sm:pl-28 md:p-8 md:pl-32"
                            >
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
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
                                            class="mt-1 text-2xl font-bold tracking-tight tabular-nums min-[420px]:text-4xl md:text-5xl"
                                        >
                                            {{ formatPeso(shownNetWorth) }}
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
                    </div>
                </div>
            </section>

            <!-- the real preset list: picking one colours the card -->
            <section class="mt-16 border-y bg-muted/40">
                <div class="mx-auto max-w-6xl px-4 py-12 md:px-8">
                    <p class="max-w-xl text-lg font-medium text-balance">
                        Made for how Filipinos pay. Your banks and e-wallets
                        keep their own logos and colors.
                    </p>
                </div>
                <!-- a slow carousel: the list is shown twice so the loop has no gap; it pauses on hover and stands still for reduced motion -->
                <div
                    class="group relative overflow-hidden pb-12 [mask-image:linear-gradient(to_right,transparent,black_8%,black_92%,transparent)]"
                >
                    <ul
                        class="flex w-max animate-[marquee_40s_linear_infinite] gap-3 group-hover:[animation-play-state:paused] motion-reduce:w-full motion-reduce:animate-none motion-reduce:flex-wrap motion-reduce:justify-center"
                    >
                        <li
                            v-for="(p, i) in [...partners, ...partners]"
                            :key="i"
                            :aria-hidden="i >= partners.length || undefined"
                            class="flex h-20 w-36 shrink-0 items-center justify-center rounded-xl bg-white p-4 ring-1 ring-black/10 sm:w-44 motion-reduce:[&:nth-child(n+18)]:hidden"
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
                        data-reveal
                        :class="lift"
                        class="flex flex-col justify-between gap-8 rounded-2xl border border-transparent bg-linear-to-br from-navy-2 to-navy p-7 text-white md:col-span-4 md:p-9"
                    >
                        <div>
                            <h3 class="text-xl font-medium">
                                Whale Chat does the typing
                            </h3>
                            <p class="mt-2 max-w-md text-sm text-white/80">
                                Type it, or say it in English, Tagalog or
                                Taglish. Log an expense, add a loan, record your
                                salary or mark a bill as paid. Nothing is saved
                                until you tap Save.
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
                        data-reveal
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
                        data-reveal
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
                            Mark one paid and its next due date moves. Loans
                            take any number of monthly payments.
                        </p>
                    </article>

                    <!-- cards and net worth -->
                    <article
                        data-reveal
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

                <h2
                    data-reveal
                    class="mt-24 max-w-2xl text-3xl font-semibold tracking-tight text-balance md:text-4xl"
                >
                    New in Whale Wallet.
                </h2>

                <div
                    class="mt-10 grid grid-cols-[minmax(0,1fr)] gap-4 md:grid-cols-3"
                >
                    <article
                        data-reveal
                        class="grid items-center gap-8 rounded-2xl bg-linear-to-br from-navy-2 to-navy p-7 text-white ring-1 ring-white/10 md:col-span-3 md:grid-cols-2 md:p-9"
                    >
                        <div>
                            <h3 class="text-xl font-medium">Who Owes You</h3>
                            <p class="mt-2 max-w-md text-sm text-white/80">
                                Record cash you lent, the card it came from and
                                the due date. We email the borrower the details
                                and remind them for you.
                            </p>
                        </div>
                        <ol class="grid gap-2 text-sm">
                            <li
                                v-for="step in lendSteps"
                                :key="step"
                                class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3"
                            >
                                <span
                                    class="size-2 shrink-0 rounded-full bg-brand-light"
                                />
                                {{ step }}
                            </li>
                        </ol>
                    </article>
                    <article
                        v-for="n in newFeatures"
                        :key="n.title"
                        data-reveal
                        :class="lift"
                        class="rounded-2xl border bg-card p-7"
                    >
                        <h3 class="text-lg font-medium">{{ n.title }}</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ n.body }}
                        </p>
                    </article>
                </div>

                <dl
                    class="mt-20 grid gap-x-12 gap-y-8 text-sm sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="m in moreFeatures"
                        :key="m.title"
                        data-reveal
                        class="border-t pt-4"
                    >
                        <dt class="font-medium">{{ m.title }}</dt>
                        <dd class="mt-1 text-muted-foreground">{{ m.body }}</dd>
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
                    <div data-reveal class="grid gap-6 text-sm leading-relaxed">
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

            <section
                class="mx-auto max-w-3xl px-4 pt-20 md:px-8 lg:pt-28"
                aria-labelledby="faq"
            >
                <h2
                    id="faq"
                    data-reveal
                    class="text-3xl font-semibold tracking-tight md:text-4xl"
                >
                    Questions, answered.
                </h2>
                <div class="mt-8 divide-y border-y">
                    <details
                        v-for="[question, answer] in faq"
                        :key="question"
                        class="group py-4"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium"
                        >
                            {{ question }}
                            <span
                                aria-hidden="true"
                                class="text-xl text-muted-foreground group-open:rotate-45"
                                >+</span
                            >
                        </summary>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ answer }}
                        </p>
                    </details>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-20 md:px-8 lg:py-28">
                <div
                    data-reveal
                    class="flex flex-col items-start justify-between gap-8 rounded-2xl bg-linear-to-br from-navy to-navy-2 p-8 text-white md:flex-row md:items-center md:p-12"
                >
                    <div class="flex items-center gap-6">
                        <Mascot
                            pose="excited"
                            class="size-16 shrink-0 sm:size-24"
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
