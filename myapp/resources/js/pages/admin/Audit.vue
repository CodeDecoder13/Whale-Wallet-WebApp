<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminPager from '@/components/AdminPager.vue';
import Heading from '@/components/Heading.vue';
import SelectNative from '@/components/SelectNative.vue';
import { adminBase, adminUrl } from '@/lib/adminUrl';
import { ago } from '@/lib/ago';

type Person = { id: number; name: string; email: string };
type Row = { id: number; action: string; meta: Record<string, unknown> | null; ip: string | null; at: string; admin: Person | null; target: Person | null };

const props = defineProps<{
    entries: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    actions: string[];
    filters: { action?: string; admin?: string; user?: string };
}>();

const label = (a: string) => a.replaceAll('_', ' ');
const detail = (m: Record<string, unknown> | null) => (m ? Object.entries(m).map(([k, v]) => `${k}: ${v}`).join(' · ') : '');
const pick = (action: string) => router.get(adminUrl('/audit'), { action: action || undefined }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Audit log" />

    <Heading title="Audit log" description="Every time an admin opened a user's data, exported it or acted on an account. Rows cannot be edited or deleted." />

    <div v-if="filters.admin || filters.user" class="mt-3 text-sm text-muted-foreground">
        Filtered by {{ filters.admin ? 'admin' : 'user' }} #{{ filters.admin ?? filters.user }}.
        <Link :href="adminUrl('/audit')" class="underline underline-offset-4">Clear</Link>
    </div>

    <div class="mt-4 max-w-xs">
        <SelectNative :model-value="props.filters.action ?? ''" aria-label="Filter by action" @update:model-value="pick($event as string)">
            <option value="">All actions</option>
            <option v-for="a in actions" :key="a" :value="a" class="capitalize">{{ label(a) }}</option>
        </SelectNative>
    </div>

    <div class="mt-4 overflow-x-auto rounded-2xl border bg-card shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b text-xs text-muted-foreground">
                <tr><th class="p-3.5">When</th><th class="p-3.5">Admin</th><th class="p-3.5">Action</th><th class="p-3.5">User</th><th class="p-3.5">Details</th><th class="p-3.5">IP</th></tr>
            </thead>
            <tbody>
                <tr v-for="e in entries.data" :key="e.id" class="border-b last:border-0 hover:bg-accent/40">
                    <td class="p-3.5 whitespace-nowrap" :title="e.at">{{ ago(e.at) }}</td>
                    <td class="p-3.5"><Link v-if="e.admin" :href="`${adminBase()}/audit?admin=${e.admin.id}`" class="hover:underline">{{ e.admin.name }}</Link></td>
                    <td class="p-3.5 font-medium capitalize">{{ label(e.action) }}</td>
                    <td class="p-3.5"><Link v-if="e.target" :href="`${adminBase()}/users/${e.target.id}`" class="hover:underline">{{ e.target.name }}</Link><span v-else class="text-muted-foreground">-</span></td>
                    <td class="max-w-xs truncate p-3.5 text-muted-foreground">{{ detail(e.meta) }}</td>
                    <td class="p-3.5 whitespace-nowrap text-muted-foreground">{{ e.ip ?? '-' }}</td>
                </tr>
                <tr v-if="!entries.data.length"><td colspan="6" class="p-6 text-center text-muted-foreground">Nothing logged yet.</td></tr>
            </tbody>
        </table>
    </div>

    <AdminPager :links="entries.links" />
</template>
