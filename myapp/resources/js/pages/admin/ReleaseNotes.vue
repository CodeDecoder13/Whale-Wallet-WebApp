<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { adminBase, adminUrl } from '@/lib/adminUrl';
import { ago } from '@/lib/ago';

type Note = {
    id: number; title: string; version: string | null; status: string; send_email: boolean; show_popup: boolean;
    published_at: string | null; email_status: string; emailed: number; seen: number;
};

defineProps<{ notes: Note[]; recipients: number }>();

const emailLabel = (n: Note) => (!n.send_email ? 'no email' : n.email_status === 'sending' ? `sending (${n.emailed} sent)` : n.status === 'draft' ? 'will email' : `${n.emailed} emailed`);
</script>

<template>
    <Head title="Release notes" />

    <div class="flex flex-wrap items-start justify-between gap-3">
        <Heading title="Release notes" :description="`What's new, emailed to users and shown once as a popup. A published note reaches ${recipients} users by email.`" />
        <Button as-child><Link :href="adminUrl('/release-notes/create')"><Plus class="size-4" /> New note</Link></Button>
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl border bg-card shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b text-xs text-muted-foreground">
                <tr><th class="p-3.5">Note</th><th class="p-3.5">Status</th><th class="p-3.5">Email</th><th class="p-3.5">Popup</th><th class="p-3.5">Published</th><th class="p-3.5"></th></tr>
            </thead>
            <tbody>
                <tr v-for="n in notes" :key="n.id" class="border-b last:border-0 hover:bg-accent/40">
                    <td class="p-3.5">
                        <span class="font-semibold">{{ n.title }}</span>
                        <span v-if="n.version" class="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ n.version }}</span>
                    </td>
                    <td class="p-3.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="n.status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-muted text-muted-foreground'">{{ n.status }}</span></td>
                    <td class="p-3.5">{{ emailLabel(n) }}</td>
                    <td class="p-3.5">{{ n.show_popup ? (n.status === 'published' ? `${n.seen} seen` : 'will show') : 'off' }}</td>
                    <td class="p-3.5 whitespace-nowrap">{{ n.published_at ? ago(n.published_at) : '-' }}</td>
                    <td class="p-3.5 text-right"><Link :href="`${adminBase()}/release-notes/${n.id}/edit`" class="font-medium underline underline-offset-4">Open</Link></td>
                </tr>
                <tr v-if="!notes.length"><td colspan="6" class="p-6 text-center text-muted-foreground">No release notes yet.</td></tr>
            </tbody>
        </table>
    </div>
</template>
