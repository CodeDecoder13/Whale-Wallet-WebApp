<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Ban, Check, Download, ShieldCheck, Trash2 } from '@lucide/vue';
import Mascot from '@/components/Mascot.vue';
import { Button } from '@/components/ui/button';
import { formatPeso } from '@/lib/currency';
import { dashboard, register } from '@/routes';

const eyebrow = 'text-sm font-bold tracking-widest text-brand-deep uppercase dark:text-brand-light';
const h2 = 'mt-2 max-w-3xl text-3xl font-semibold tracking-tight text-balance md:text-4xl';
const section = 'mx-auto max-w-6xl px-4 py-16 md:px-8 md:py-20';
const card = 'rounded-2xl border bg-card p-6';

const pledges = [
    { icon: ShieldCheck, title: 'We never sell your data', text: 'Your numbers are used to run the app for you, nothing else.' },
    { icon: Ban, title: 'No ads, no trackers', text: 'No advertising or analytics cookies, and no ad networks.' },
    { icon: Check, title: 'AI only with your OK', text: 'Nothing goes to the AI until you agree. You can withdraw any time, and the app still works without it.' },
    { icon: Check, title: 'Nothing saves until you confirm', text: 'Receipts and chat messages become records only after you tap Save.' },
    { icon: Trash2, title: 'Receipt images do not pile up', text: 'They are deleted after you confirm, unless you choose to keep them.' },
    { icon: Download, title: 'Take it all, or delete it all', text: 'Export your data or delete your account and its data from Settings.' },
];
const privacyLinks = [
    { href: '/privacy', label: 'Privacy overview' },
    { href: '/privacy/ai', label: 'AI and your data' },
    { href: '/privacy/security', label: 'Security' },
    { href: '/privacy/processors', label: 'Who handles your data' },
];
const have = [
    ['What you have', 'Banks, e-wallets, credit cards and cash, each with its own logo and color.'],
    ['What you owe', 'Bills, subscriptions and loans, with due dates you can see at a glance.'],
    ['What you save for', 'Savings pods for goals, with a target, a date and a progress ring.'],
];
const who = [
    ['Paid on the 15th and 30th', 'You want to know how many days you must stretch your pay and what each day can cost.'],
    ['Juggling GCash, Maya and banks', 'Money is spread across wallets and cards, and you lose track of where it went.'],
    ['Bills, subscriptions and utang', 'You pay rent, Netflix, internet and loans, and you sometimes forget what is due.'],
    ['Done with spreadsheets', 'You want to log spending by typing a line, talking to Whale or snapping a receipt.'],
];
const problems = [
    ['Where did my money go?', 'Every expense is charged to a card, categorized, searchable and easy to export.'],
    ['Can I afford this today?', 'A daily safe-to-spend number and a balance forecast up to payday, including the lowest point.'],
    ['I forgot a bill again.', 'Color-coded due dates, overdue alerts and a daily summary so nothing sneaks up on you.'],
    ['Logging expenses is tedious.', 'Type "Jollibee 250", send a receipt, or just tell Whale.'],
    ['I do not trust apps with my data.', 'AI only runs after you agree, nothing is saved until you confirm, and you can export or delete everything.'],
];
const features = [
    ['Savings pods', 'Set a goal, a target date and a card to fund it. A projection shows when you will get there.'],
    ['Payday forecast', 'Set your payday once. Whale Wallet projects your balance up to it, counting bills, subscriptions and your recent spending, and shows the lowest point.'],
    ['Whale tips', 'Short daily suggestions based on your own totals. Every number in a tip comes from your data, never from a guess.'],
    ['Cards that look like your banks', 'GCash, Maya, BDO, BPI, Metrobank, Landbank and more keep their own logos and colors. Net worth adds your cards and savings, then subtracts loans and credit card balances.'],
];
const steps = [
    ['Add what you have', 'Pick your banks and e-wallets, add your payday, and list your bills, subscriptions and loans.'],
    ['Log as you go', 'Chat with Whale, use quick entry, or send a receipt. Review each draft and tap Save.'],
    ['Spend with confidence', 'Open Whale Wallet to see what is safe to spend today, what is due next and where your balance is heading.'],
];
const different = [
    ['One number, not a wall of charts', 'Safe to spend per day until payday is the first thing you see.'],
    ['The AI cannot make up numbers', 'Your totals are calculated by the app. The AI understands your request and phrases the answer, and any figure it quotes is checked against your data.'],
    ['Chat that actually does things', 'Add, change, pay, cancel or delete by chatting. Every change shows a confirmation card first, and destructive ones are marked in red.'],
    ['Built for the Philippines', 'Pesos, local banks and wallets, Philippine time, paydays on the 15th and 30th, and Taglish.'],
    ['Your history stays', 'Deleting a bill or cancelling a subscription keeps your past payments in the record.'],
];
const trust = [
    ['AI only with your OK', 'Receipt reading, tips and chat use Google Gemini. Nothing is sent until you agree, and you can withdraw consent.'],
    ['Nothing saves until you say so', 'Receipts and chat messages become records only after you tap Save.'],
    ['Protected accounts', 'Account numbers are stored encrypted. Sign in with two-factor authentication or a passkey.'],
    ['Images do not pile up', 'Receipt images are deleted after you confirm unless you choose to keep them.'],
    ['Export or delete anytime', 'Download everything as a file, or delete your account with its data and images.'],
    ['Tracks money, never holds it', 'Whale Wallet does not hold or move your money. Tips are budgeting guidance, not financial advice.'],
];
const status = [
    ['bg-green-600', 'Ready now', "The web app, which works on your phone's browser too: dashboard, cards, bills, loans, savings pods, forecast, receipts and Whale Chat."],
    ['bg-amber-500', 'In progress', 'A companion mobile app with push notifications for due bills and payday.'],
    ['bg-slate-400', 'Good to know', 'Pesos only for now. Budgets are set per card, and there are no paid plans in the app today.'],
];
const faq = [
    ['Does Whale Wallet connect to my bank?', 'No. You add your cards and log spending by chat, quick entry or receipts. It never asks for your bank passwords.'],
    ['Does it understand Tagalog?', 'Yes. Whale Chat understands English, Tagalog and Taglish, and you can type or use your voice.'],
    ['Which AI does it use?', 'Google Gemini, and only after you agree. Your totals are calculated by the app, and any number in a reply is checked against your data.'],
    ['Is there a mobile app?', "A companion mobile app is being built. The website already works on your phone's browser."],
    ['Can I use other currencies?', 'Not yet. Whale Wallet is built for Philippine pesos.'],
    ['How do I delete my data?', 'Open Settings to export everything or delete your account together with its data and receipt images.'],
];
const bars = [100, 92, 84, 76, 70, 62, 52, 46, 36, 28];
</script>

<template>
    <Head title="About">
        <meta
            name="description"
            content="Whale Wallet is a personal finance app for people in the Philippines. It shows what is safe to spend each day until payday, and lets you manage money by chatting with a friendly whale."
        />
    </Head>

    <!-- hero -->
    <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 pt-10 pb-16 md:px-8 lg:grid-cols-[1.05fr_1fr] lg:gap-14 lg:pt-16 lg:pb-24">
        <div>
            <span :class="eyebrow">About Whale Wallet</span>
            <h1 class="mt-3 mb-4 text-4xl leading-[1.05] font-semibold tracking-tight text-balance md:text-6xl">
                Know what you can spend today, not just what you spent last month.
            </h1>
            <p class="max-w-xl text-lg text-muted-foreground">
                Whale Wallet is a personal finance app for people in the
                Philippines. It brings your cards, bills, loans and savings
                together, then gives you the one number that matters: how much
                is safe to spend each day until payday.
            </p>
            <div class="mt-7 flex flex-wrap gap-3">
                <Button v-if="$page.props.auth.user" size="lg" as-child>
                    <Link :href="dashboard()">Open dashboard</Link>
                </Button>
                <Button v-else size="lg" as-child>
                    <Link :href="register()">Create account</Link>
                </Button>
                <Button size="lg" variant="outline" as-child>
                    <a href="#how">See how it works</a>
                </Button>
            </div>
        </div>
        <div
            class="relative overflow-hidden rounded-3xl bg-linear-to-br from-navy to-navy-2 p-7 text-white shadow-[0_30px_70px_-30px_rgba(11,37,69,0.6)]"
            aria-label="Example of the safe to spend card"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs text-white/65">Safe to spend today</div>
                    <div class="text-2xl font-bold tabular-nums">{{ formatPeso(51_100) }}</div>
                    <div class="text-xs text-white/65">Payday in 11 days</div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-white/65">Net worth</div>
                    <div class="text-4xl font-extrabold tracking-tight tabular-nums">{{ formatPeso(4_840_000) }}</div>
                    <div class="text-xs text-white/65">Example numbers</div>
                </div>
            </div>
            <div class="mt-5 flex h-21 items-end gap-1.5 rounded-xl bg-white/10 p-3" aria-hidden="true">
                <i v-for="(b, i) in bars" :key="i" class="flex-1 rounded-t bg-brand-light" :style="{ height: `${b}%` }" />
            </div>
            <Mascot pose="hero" bare class="pointer-events-none absolute -right-2 -bottom-3 w-28" />
        </div>
    </div>

    <!-- privacy pledges -->
    <section :class="section">
        <span :class="eyebrow">Privacy comes first</span>
        <h2 :class="h2">Your money is personal. We built Whale Wallet that way.</h2>
        <p class="mt-3 max-w-2xl text-muted-foreground">
            Whale Wallet is an independent project, built by one person who
            uses it too. These are the promises we design around.
        </p>
        <div class="mt-8 grid gap-3.5 md:grid-cols-2 lg:grid-cols-3">
            <div v-for="p in pledges" :key="p.title" class="flex items-start gap-3.5 rounded-2xl border bg-card p-5">
                <span class="grid size-9 flex-none place-items-center rounded-lg bg-brand-tint text-brand-deep dark:bg-navy-2 dark:text-brand-light">
                    <component :is="p.icon" class="size-5" aria-hidden="true" />
                </span>
                <div>
                    <h3 class="font-semibold">{{ p.title }}</h3>
                    <p class="text-sm text-muted-foreground">{{ p.text }}</p>
                </div>
            </div>
        </div>
        <p class="mt-6 flex flex-wrap gap-x-5 gap-y-2 font-semibold text-brand-deep dark:text-brand-light">
            <Link v-for="l in privacyLinks" :key="l.href" :href="l.href">{{ l.label }}</Link>
        </p>
    </section>

    <!-- what it is -->
    <section class="border-y bg-muted/40">
        <div :class="section">
            <span :class="eyebrow">What is Whale Wallet?</span>
            <h2 :class="h2">Your whole money picture, boiled down to one number.</h2>
            <p class="mt-3 max-w-2xl text-muted-foreground">
                Most apps show you what already happened. Whale Wallet looks
                ahead. It puts together the money you have, the money you owe
                and the money you are saving for, then works out what you can
                spend each day until your next payday.
            </p>
            <div class="mt-9 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="(h, i) in have" :key="h[0]" :class="card">
                    <div class="mb-3 grid size-10 place-items-center rounded-xl bg-brand-tint font-extrabold text-brand-deep dark:bg-navy-2 dark:text-brand-light">{{ i + 1 }}</div>
                    <h3 class="mb-2 text-lg font-semibold">{{ h[0] }}</h3>
                    <p class="text-sm text-muted-foreground">{{ h[1] }}</p>
                </div>
                <div class="rounded-2xl bg-linear-to-br from-navy to-navy-2 p-6 text-white">
                    <div class="text-3xl font-extrabold tracking-tight">₱ per day</div>
                    <h3 class="mt-1 mb-2 text-lg font-semibold">Safe to spend</h3>
                    <p class="text-sm text-white/80">One clear number until your next payday.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- who -->
    <section :class="section">
        <span :class="eyebrow">Who needs Whale Wallet?</span>
        <h2 :class="h2">Made for people whose money feels thin before payday.</h2>
        <p class="mt-3 max-w-2xl text-muted-foreground">
            Built for salaried workers and freelancers in the Philippines who
            are paid once or twice a month, and for anyone tired of guessing.
        </p>
        <div class="mt-9 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="w in who" :key="w[0]" :class="card">
                <h3 class="mb-2 text-lg font-semibold">{{ w[0] }}</h3>
                <p class="text-sm text-muted-foreground">{{ w[1] }}</p>
            </div>
        </div>
    </section>

    <!-- problems -->
    <section class="border-y bg-muted/40">
        <div :class="section">
            <span :class="eyebrow">The problems it solves</span>
            <h2 :class="h2">Five questions every Filipino budgeter asks.</h2>
            <div class="mt-8 grid gap-3">
                <div v-for="p in problems" :key="p[0]" class="grid gap-1.5 rounded-2xl border bg-card px-6 py-5 md:grid-cols-[1fr_1.4fr] md:items-center md:gap-6">
                    <q class="text-lg font-bold">{{ p[0] }}</q>
                    <span class="text-sm text-muted-foreground">{{ p[1] }}</span>
                </div>
            </div>
        </div>
    </section>

    <!-- what it can do -->
    <section :class="section">
        <span :class="eyebrow">What Whale Wallet can do for you</span>
        <h2 :class="h2">Log money the way you already talk about it.</h2>
        <div class="mt-9 grid gap-4 md:grid-cols-6">
            <article class="rounded-2xl bg-linear-to-br from-navy-2 to-navy p-7 text-white md:col-span-4">
                <h3 class="text-xl font-medium">Whale Chat does the typing</h3>
                <p class="mt-2 max-w-lg text-sm text-white/80">
                    Log an expense, add a loan, record your salary, mark a bill
                    as paid, or ask about your money. Type it or say it, in
                    English, Tagalog or Taglish. Nothing is saved until you tap
                    Save.
                </p>
                <div class="mt-5 grid gap-2 text-sm">
                    <div class="ml-auto rounded-2xl bg-brand px-4 py-2">paid converge from BDO</div>
                    <div class="flex max-w-md items-start gap-2 rounded-2xl bg-white/10 px-3 py-2">
                        <Mascot pose="happy" round class="size-7 shrink-0" />
                        Mark Converge - Wifi as paid: ₱1,600.00 from BDO? The next due date moves forward.
                    </div>
                    <div class="ml-auto rounded-2xl bg-brand px-4 py-2">how much did I spend on food this week?</div>
                </div>
            </article>
            <article class="rounded-2xl bg-brand-tint p-7 text-navy md:col-span-2 dark:bg-navy-2/50 dark:text-foreground">
                <Mascot pose="thinking" round class="mb-3 size-14" />
                <h3 class="text-xl font-medium">Snap a receipt</h3>
                <p class="mt-2 text-sm opacity-80">
                    Upload a GCash or bank screenshot. The merchant, amount,
                    date and reference number are read for you to confirm.
                    Duplicates are flagged.
                </p>
            </article>
            <article v-for="f in features" :key="f[0]" :class="[card, 'md:col-span-3']">
                <h3 class="text-xl font-medium">{{ f[0] }}</h3>
                <p class="mt-2 text-sm text-muted-foreground">{{ f[1] }}</p>
            </article>
        </div>
    </section>

    <!-- how it works -->
    <section id="how" class="border-y bg-muted/40">
        <div :class="section">
            <span :class="eyebrow">How it works</span>
            <h2 :class="h2">Set up in minutes. Check in each day.</h2>
            <ol class="mt-9 grid gap-4 md:grid-cols-3">
                <li v-for="(s, i) in steps" :key="s[0]" :class="[card, 'p-7']">
                    <span class="mb-3 grid size-9 place-items-center rounded-full bg-brand-deep font-extrabold text-white">{{ i + 1 }}</span>
                    <h3 class="mb-1.5 text-lg font-semibold">{{ s[0] }}</h3>
                    <p class="text-sm text-muted-foreground">{{ s[1] }}</p>
                </li>
            </ol>
        </div>
    </section>

    <!-- different -->
    <section :class="[section, 'grid gap-9 lg:grid-cols-[.9fr_1.1fr] lg:gap-16']">
        <div>
            <span :class="eyebrow">Why Whale Wallet is different</span>
            <h2 :class="h2">An app that shows its work.</h2>
            <p class="mt-3 text-muted-foreground">We built Whale Wallet around trust: the numbers you see are the numbers in your data.</p>
        </div>
        <div class="grid gap-5">
            <div v-for="d in different" :key="d[0]" class="border-l-[3px] border-brand pl-4">
                <h3 class="mb-1 font-semibold">{{ d[0] }}</h3>
                <p class="text-sm text-muted-foreground">{{ d[1] }}</p>
            </div>
        </div>
    </section>

    <!-- trust -->
    <section class="border-y bg-muted/40">
        <div :class="section">
            <span :class="eyebrow">Privacy and trust</span>
            <h2 :class="h2">Your data stays yours.</h2>
            <div class="mt-9 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="t in trust" :key="t[0]" :class="card">
                    <h3 class="mb-2 text-lg font-semibold">{{ t[0] }}</h3>
                    <p class="text-sm text-muted-foreground">{{ t[1] }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- status -->
    <section :class="section">
        <span :class="eyebrow">Where we are today</span>
        <h2 :class="h2">Honest about what is ready.</h2>
        <div class="mt-8 grid gap-3 md:grid-cols-3">
            <div v-for="s in status" :key="s[1]" :class="card">
                <h3 class="mb-2 flex items-center gap-2 text-lg font-semibold">
                    <span class="size-2.5 rounded-full" :class="s[0]" />{{ s[1] }}
                </h3>
                <p class="text-sm text-muted-foreground">{{ s[2] }}</p>
            </div>
        </div>
    </section>

    <!-- faq -->
    <section class="border-y bg-muted/40">
        <div :class="[section, 'max-w-3xl']">
            <span :class="eyebrow">Questions</span>
            <h2 :class="h2">Frequently asked</h2>
            <div class="mt-7 grid gap-2.5">
                <details v-for="f in faq" :key="f[0]" class="group rounded-xl border bg-card px-5">
                    <summary class="flex cursor-pointer list-none justify-between gap-3 py-3.5 font-semibold [&::-webkit-details-marker]:hidden">
                        {{ f[0] }}
                        <span class="text-xl leading-none text-brand group-open:hidden">+</span>
                        <span class="hidden text-xl leading-none text-brand group-open:inline">–</span>
                    </summary>
                    <p class="pb-4 text-sm text-muted-foreground">{{ f[1] }}</p>
                </details>
            </div>
        </div>
    </section>

    <!-- cta -->
    <section :class="section">
        <div class="flex flex-col items-start justify-between gap-6 rounded-3xl bg-linear-to-br from-navy to-navy-2 p-8 text-white md:flex-row md:items-center md:p-12">
            <div class="flex items-center gap-5">
                <Mascot pose="excited" class="hidden size-24 shrink-0 sm:block" />
                <h2 class="max-w-sm text-3xl font-semibold tracking-tight text-balance md:text-4xl">Start with your next payday.</h2>
            </div>
            <Button size="lg" class="bg-white text-navy hover:bg-white/90" as-child>
                <Link v-if="$page.props.auth.user" :href="dashboard()">Open dashboard</Link>
                <Link v-else :href="register()">Create account</Link>
            </Button>
        </div>
    </section>
</template>
