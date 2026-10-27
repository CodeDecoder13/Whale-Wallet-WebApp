<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { ago } from '@/lib/ago';

type Row = {
    id: number; name: string; email: string; joined: string; verified: boolean; suspended: boolean; admin_role: string | null;
    last_active: number | null; cards: number; transactions: number; loans: number; subs: number;
};
type Page = { data: Row[]; links: { url: string | null; label: string; active: boolean }[]; total: number };

const props = defineProps<{ users: Page; filters: { q?: string; filter?: string } }>();

const q = ref(props.filters.q ?? '');
const filters = [
    { value: '', label: 'All' }, { value: 'verified', label: 'Verified' }, { value: 'unverified', label: 'Not verified' },
    { value: 'inactive', label: 'Inactive 30 days' }, { value: 'suspended', label: 'Suspended' },
];

function go(filter = props.filters.filter) {
    router.get('/admin/users', { q: q.value || undefined, filter: filter || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Users" />

    <Heading :title="`Users (${users.total})`" description="Everyone who signed up. Open a row for their account details." />

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <form class="w-full max-w-xs" @submit.prevent="go()">
            <Input v-model="q" type="search" placeholder="Search name or email" aria-label="Search users" />
        </form>
        <button
            v-for="f in filters"
            :key="f.value"
            type="button"
            class="rounded-full border px-3 py-1.5 text-sm transition-colors duration-150 ease-out active:scale-[0.97]"
            :class="(props.filters.filter ?? '') === f.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:bg-accent'"
            @click="go(f.value)"
        >
            {{ f.label }}
        </button>
    </div>

    <div class="mt-4 overflow-x-auto rounded-2xl border bg-card shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b text-xs text-muted-foreground">
                <tr>
                    <th class="p-3.5">User</th><th class="p-3.5">Joined</th><th class="p-3.5">Last active</th>
                    <th class="p-3.5 text-right">Cards</th><th class="p-3.5 text-right">Txns</th><th class="p-3.5 text-right">Loans</th><th class="p-3.5 text-right">Subs</th>
                    <th class="p-3.5">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="u in users.data" :key="u.id" class="border-b last:border-0 hover:bg-accent/40">
                    <td class="p-3.5">
                        <Link :href="`/admin/users/${u.id}`" class="font-semibold hover:underline">{{ u.name }}</Link>
                        <div class="text-xs text-muted-foreground">{{ u.email }}</div>
                    </td>
                    <td class="p-3.5 whitespace-nowrap">{{ u.joined }}</td>
                    <td class="p-3.5 whitespace-nowrap">{{ ago(u.last_active) }}</td>
                    <td class="p-3.5 text-right">{{ u.cards }}</td><td class="p-3.5 text-right">{{ u.transactions }}</td>
                    <td class="p-3.5 text-right">{{ u.loans }}</td><td class="p-3.5 text-right">{{ u.subs }}</td>
                    <td class="p-3.5 whitespace-nowrap">
                        <span v-if="u.admin_role" class="mr-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ u.admin_role }}</span>
                        <span v-if="u.suspended" class="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">suspended</span>
                        <span v-else-if="!u.verified" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">not verified</span>
                        <span v-else class="text-muted-foreground">active</span>
                    </td>
                </tr>
                <tr v-if="!users.data.length"><td colspan="8" class="p-6 text-center text-muted-foreground">No users match.</td></tr>
            </tbody>
        </table>
    </div>

    <nav v-if="users.links.length > 3" class="mt-4 flex flex-wrap gap-1" aria-label="Pages">
        <template v-for="(l, i) in users.links" :key="i">
            <Link v-if="l.url" :href="l.url" preserve-scroll class="rounded-lg border px-3 py-1.5 text-sm" :class="l.active ? 'bg-primary text-primary-foreground' : 'bg-card hover:bg-accent'" v-html="l.label" />
            <span v-else class="rounded-lg border px-3 py-1.5 text-sm text-muted-foreground" v-html="l.label" />
        </template>
    </nav>
</template>
