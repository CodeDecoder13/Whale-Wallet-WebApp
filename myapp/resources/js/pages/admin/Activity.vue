<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import SelectNative from '@/components/SelectNative.vue';
import { ago } from '@/lib/ago';

type Row = { id: number; event: string; meta: Record<string, unknown> | null; ip: string | null; at: string; user: { id: number; name: string; email: string } | null };

const props = defineProps<{
    events: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    names: string[];
    filters: { event?: string };
}>();

const label = (e: string) => e.replaceAll('_', ' ');
const detail = (m: Record<string, unknown> | null) => (m ? Object.values(m).filter(Boolean).join(' · ') : '');

function pick(event: string) {
    router.get('/admin/activity', { event: event || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Activity" />

    <Heading title="Activity" description="What users did, newest first. Kept for 90 days." />

    <div class="mt-4 max-w-xs">
        <SelectNative :model-value="props.filters.event ?? ''" aria-label="Filter by event" @update:model-value="pick($event as string)">
            <option value="">All events</option>
            <option v-for="n in names" :key="n" :value="n" class="capitalize">{{ label(n) }}</option>
        </SelectNative>
    </div>

    <div class="mt-4 overflow-x-auto rounded-2xl border bg-card shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b text-xs text-muted-foreground">
                <tr><th class="p-3.5">When</th><th class="p-3.5">Event</th><th class="p-3.5">User</th><th class="p-3.5">Details</th><th class="p-3.5">IP</th></tr>
            </thead>
            <tbody>
                <tr v-for="a in events.data" :key="a.id" class="border-b last:border-0 hover:bg-accent/40">
                    <td class="p-3.5 whitespace-nowrap" :title="a.at">{{ ago(a.at) }}</td>
                    <td class="p-3.5 font-medium capitalize">{{ label(a.event) }}</td>
                    <td class="p-3.5"><Link v-if="a.user" :href="`/admin/users/${a.user.id}`" class="hover:underline">{{ a.user.name }}</Link><span v-else class="text-muted-foreground">-</span></td>
                    <td class="max-w-xs truncate p-3.5 text-muted-foreground">{{ detail(a.meta) }}</td>
                    <td class="p-3.5 whitespace-nowrap text-muted-foreground">{{ a.ip ?? '-' }}</td>
                </tr>
                <tr v-if="!events.data.length"><td colspan="5" class="p-6 text-center text-muted-foreground">Nothing recorded yet.</td></tr>
            </tbody>
        </table>
    </div>

    <nav v-if="events.links.length > 3" class="mt-4 flex flex-wrap gap-1" aria-label="Pages">
        <template v-for="(l, i) in events.links" :key="i">
            <Link v-if="l.url" :href="l.url" preserve-scroll class="rounded-lg border px-3 py-1.5 text-sm" :class="l.active ? 'bg-primary text-primary-foreground' : 'bg-card hover:bg-accent'" v-html="l.label" />
            <span v-else class="rounded-lg border px-3 py-1.5 text-sm text-muted-foreground" v-html="l.label" />
        </template>
    </nav>
</template>
