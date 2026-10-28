<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { adminUrl } from '@/lib/adminUrl';

const props = defineProps<{
    loginAlerts: string;
    users: { total: number; today: number; week: number; month: number; verified_pct: number; with_pin: number; with_2fa: number; active_day: number; active_week: number };
    money: { cards: number; transactions_month: number; loans: number; subscriptions: number };
    ai: { receipts_week: number; receipts_failed: number; receipts_stuck: number; tips_week: number };
    health: { digests_today: number; digest_failures_today: number; last_digest_on: string | null; failed_jobs: number; contact_messages: number };
}>();

const n = (v: number) => v.toLocaleString('en-US');

const alertChoices = [
    { value: 'all', label: 'Every sign-in' },
    { value: 'new', label: 'New devices only' },
    { value: 'off', label: 'Off' },
];
const setAlerts = (login_alerts: string) => router.post(adminUrl('/login-alerts'), { login_alerts }, { preserveScroll: true });
</script>

<template>
    <Head title="Admin" />

    <Heading title="Overview" description="Counts across every account. Nothing here shows what anyone bought." />

    <div class="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-4">
        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Users</h2>
            <div class="mt-3 text-4xl font-semibold tracking-tight">{{ n(users.total) }}</div>
            <dl class="mt-4 grid grid-cols-[1fr_auto] gap-y-1.5 text-sm">
                <dt class="text-muted-foreground">New today</dt><dd class="font-medium">{{ users.today }}</dd>
                <dt class="text-muted-foreground">New in 7 days</dt><dd class="font-medium">{{ users.week }}</dd>
                <dt class="text-muted-foreground">New in 30 days</dt><dd class="font-medium">{{ users.month }}</dd>
                <dt class="text-muted-foreground">Verified</dt><dd class="font-medium">{{ users.verified_pct }}%</dd>
                <dt class="text-muted-foreground">With a PIN</dt><dd class="font-medium">{{ users.with_pin }}</dd>
                <dt class="text-muted-foreground">With 2FA</dt><dd class="font-medium">{{ users.with_2fa }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Active</h2>
            <div class="mt-3 text-4xl font-semibold tracking-tight">{{ n(users.active_day) }}</div>
            <p class="text-sm text-muted-foreground">users in the last 24 hours</p>
            <dl class="mt-4 grid grid-cols-[1fr_auto] gap-y-1.5 text-sm">
                <dt class="text-muted-foreground">Last 7 days</dt><dd class="font-medium">{{ users.active_week }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Money data</h2>
            <dl class="mt-3 grid grid-cols-[1fr_auto] gap-y-1.5 text-sm">
                <dt class="text-muted-foreground">Cards</dt><dd class="font-medium">{{ n(money.cards) }}</dd>
                <dt class="text-muted-foreground">Transactions this month</dt><dd class="font-medium">{{ n(money.transactions_month) }}</dd>
                <dt class="text-muted-foreground">Active loans</dt><dd class="font-medium">{{ n(money.loans) }}</dd>
                <dt class="text-muted-foreground">Active subscriptions</dt><dd class="font-medium">{{ n(money.subscriptions) }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">AI and receipts (7 days)</h2>
            <dl class="mt-3 grid grid-cols-[1fr_auto] gap-y-1.5 text-sm">
                <dt class="text-muted-foreground">Receipts scanned</dt><dd class="font-medium">{{ ai.receipts_week }}</dd>
                <dt class="text-muted-foreground">Failed</dt><dd class="font-medium" :class="ai.receipts_failed > 0 && 'text-destructive'">{{ ai.receipts_failed }}</dd>
                <dt class="text-muted-foreground">Stuck right now</dt><dd class="font-medium" :class="ai.receipts_stuck > 0 && 'text-destructive'">{{ ai.receipts_stuck }}</dd>
                <dt class="text-muted-foreground">Tips made</dt><dd class="font-medium">{{ ai.tips_week }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border bg-card p-5 shadow-sm lg:col-span-2 xl:col-span-4">
            <h2 class="text-sm font-semibold">Login alerts</h2>
            <p class="mt-1 text-sm text-muted-foreground">An email to you when a user signs in, with the time, device and IP.</p>
            <div class="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-label="Login alerts">
                <button
                    v-for="c in alertChoices"
                    :key="c.value"
                    type="button"
                    role="radio"
                    :aria-checked="props.loginAlerts === c.value"
                    class="rounded-full border px-4 py-1.5 text-sm transition-colors duration-150 ease-out active:scale-[0.97]"
                    :class="props.loginAlerts === c.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:bg-accent'"
                    @click="setAlerts(c.value)"
                >
                    {{ c.label }}
                </button>
            </div>
        </section>

        <section class="rounded-2xl border bg-card p-5 shadow-sm lg:col-span-2 xl:col-span-4">
            <h2 class="text-sm font-semibold">Health</h2>
            <dl class="mt-3 grid gap-x-8 gap-y-1.5 text-sm sm:grid-cols-2 xl:grid-cols-5">
                <div class="flex justify-between"><dt class="text-muted-foreground">Digests sent today</dt><dd class="font-medium">{{ health.digests_today }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Digest failures today</dt><dd class="font-medium" :class="health.digest_failures_today > 0 && 'text-destructive'">{{ health.digest_failures_today }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Last digest day</dt><dd class="font-medium">{{ health.last_digest_on ?? 'none yet' }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Failed jobs</dt><dd class="font-medium" :class="health.failed_jobs > 0 && 'text-destructive'">{{ health.failed_jobs }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Contact messages</dt><dd class="font-medium">{{ health.contact_messages }}</dd></div>
            </dl>
        </section>
    </div>
</template>
