<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { ago } from '@/lib/ago';
import { formatPeso } from '@/lib/currency';

type Item = {
    id: number; kind: string; name: string; amount: number; cadence: string; total: number | null; remaining: number | null;
    left: number | null; next_due_on: string | null; overdue: boolean; status: string;
};

defineProps<{
    profile: {
        id: number; name: string; email: string; joined: string; verified: boolean; suspended: boolean; admin_role: string | null;
        has_pin: boolean; has_2fa: boolean; ai_consent: boolean; digest_enabled: boolean; digest_time: string; pay_dates: number[];
    };
    counts: Record<string, number>;
    sessions: { ip_address: string | null; user_agent: string | null; last_activity: number }[];
    items: Item[];
    activity: { event: string; meta: Record<string, unknown> | null; ip: string | null; created_at: string }[];
}>();

const yes = (v: boolean) => (v ? 'Yes' : 'No');
const label = (e: string) => e.replaceAll('_', ' ');
</script>

<template>
    <Head title="User" />

    <Link href="/admin/users" class="text-sm text-muted-foreground underline-offset-4 hover:underline">Back to users</Link>
    <Heading :title="profile.name" :description="profile.email" class="mt-2" />

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-2 xl:grid-cols-3">
        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Account</h2>
            <dl class="mt-3 grid grid-cols-[1fr_auto] gap-y-1.5 text-sm">
                <dt class="text-muted-foreground">Joined</dt><dd class="font-medium">{{ profile.joined }}</dd>
                <dt class="text-muted-foreground">Email verified</dt><dd class="font-medium">{{ yes(profile.verified) }}</dd>
                <dt class="text-muted-foreground">Suspended</dt><dd class="font-medium">{{ yes(profile.suspended) }}</dd>
                <dt class="text-muted-foreground">Login PIN set</dt><dd class="font-medium">{{ yes(profile.has_pin) }}</dd>
                <dt class="text-muted-foreground">2FA on</dt><dd class="font-medium">{{ yes(profile.has_2fa) }}</dd>
                <dt class="text-muted-foreground">AI notice accepted</dt><dd class="font-medium">{{ yes(profile.ai_consent) }}</dd>
                <dt class="text-muted-foreground">Daily digest</dt><dd class="font-medium">{{ profile.digest_enabled ? `on, ${profile.digest_time}` : 'off' }}</dd>
                <dt class="text-muted-foreground">Payday</dt><dd class="font-medium">{{ profile.pay_dates.join(' and ') || 'not set' }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">What they have</h2>
            <dl class="mt-3 grid grid-cols-[1fr_auto] gap-y-1.5 text-sm">
                <template v-for="(v, k) in counts" :key="k">
                    <dt class="text-muted-foreground capitalize">{{ k }}</dt><dd class="font-medium">{{ v }}</dd>
                </template>
            </dl>
        </section>

        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Sessions</h2>
            <ul class="mt-3 grid gap-3 text-sm">
                <li v-for="(s, i) in sessions" :key="i">
                    <div class="font-medium">{{ ago(s.last_activity) }}</div>
                    <div class="truncate text-xs text-muted-foreground">{{ s.ip_address }} · {{ s.user_agent }}</div>
                </li>
                <li v-if="!sessions.length" class="text-muted-foreground">Not signed in anywhere.</li>
            </ul>
        </section>

        <section class="overflow-x-auto rounded-2xl border bg-card p-5 shadow-sm lg:col-span-2 xl:col-span-3">
            <h2 class="text-sm font-semibold">Loans, subscriptions and bills</h2>
            <table class="mt-3 w-full text-left text-sm">
                <thead class="text-xs text-muted-foreground">
                    <tr><th class="py-2 pr-3">Name</th><th class="pr-3">Kind</th><th class="pr-3 text-right">Payment</th><th class="pr-3 text-right">Total</th><th class="pr-3 text-right">Remaining</th><th class="pr-3">Left</th><th class="pr-3">Next due</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr v-for="i in items" :key="i.id" class="border-t">
                        <td class="py-2 pr-3 font-medium">{{ i.name }}</td>
                        <td class="pr-3">{{ i.kind === 'debt' ? 'loan' : i.kind }}</td>
                        <td class="pr-3 text-right">{{ formatPeso(i.amount) }}</td>
                        <td class="pr-3 text-right">{{ i.total ? formatPeso(i.total) : '-' }}</td>
                        <td class="pr-3 text-right">{{ i.remaining !== null ? formatPeso(i.remaining) : '-' }}</td>
                        <td class="pr-3">{{ i.left ?? '-' }}</td>
                        <td class="pr-3 whitespace-nowrap" :class="i.overdue && 'font-semibold text-destructive'">{{ i.next_due_on ?? '-' }}{{ i.overdue ? ' (late)' : '' }}</td>
                        <td>{{ i.status }}</td>
                    </tr>
                    <tr v-if="!items.length"><td colspan="8" class="py-4 text-muted-foreground">None.</td></tr>
                </tbody>
            </table>
        </section>

        <section class="rounded-2xl border bg-card p-5 shadow-sm lg:col-span-2 xl:col-span-3">
            <h2 class="text-sm font-semibold">Recent activity</h2>
            <ul class="mt-3 grid gap-2 text-sm">
                <li v-for="(a, i) in activity" :key="i" class="flex justify-between gap-4">
                    <span class="capitalize">{{ label(a.event) }}</span>
                    <span class="text-muted-foreground">{{ ago(a.created_at) }}{{ a.ip ? ` · ${a.ip}` : '' }}</span>
                </li>
                <li v-if="!activity.length" class="text-muted-foreground">Nothing recorded yet.</li>
            </ul>
        </section>
    </div>
</template>
