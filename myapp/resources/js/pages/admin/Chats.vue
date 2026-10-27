<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminPager from '@/components/AdminPager.vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { ago } from '@/lib/ago';

type Row = { id: number; title: string | null; messages: number; last_at: string | null; user: { id: number; name: string; email: string } | null };

const props = defineProps<{
    threads: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { q?: string; user?: string };
}>();

const q = ref(props.filters.q ?? '');
const search = () => router.get('/admin/chats', { q: q.value || undefined, user: props.filters.user }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Conversations" />

    <Heading title="Conversations" description="Whale Chat (AI) threads, read-only. Chats a user deleted are not listed. Opening one is written to the audit log." />

    <form class="mt-4 max-w-sm" @submit.prevent="search">
        <Input v-model="q" type="search" placeholder="Search what was said" aria-label="Search messages" />
    </form>

    <div class="mt-4 overflow-x-auto rounded-2xl border bg-card shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b text-xs text-muted-foreground">
                <tr><th class="p-3.5">Conversation</th><th class="p-3.5">User</th><th class="p-3.5 text-right">Messages</th><th class="p-3.5">Last message</th></tr>
            </thead>
            <tbody>
                <tr v-for="t in threads.data" :key="t.id" class="border-b last:border-0 hover:bg-accent/40">
                    <td class="p-3.5"><Link :href="`/admin/chats/${t.id}`" class="font-semibold hover:underline">{{ t.title || `Chat #${t.id}` }}</Link></td>
                    <td class="p-3.5"><Link v-if="t.user" :href="`/admin/users/${t.user.id}`" class="hover:underline">{{ t.user.name }}</Link></td>
                    <td class="p-3.5 text-right">{{ t.messages }}</td>
                    <td class="p-3.5 whitespace-nowrap">{{ ago(t.last_at) }}</td>
                </tr>
                <tr v-if="!threads.data.length"><td colspan="4" class="p-6 text-center text-muted-foreground">No conversations.</td></tr>
            </tbody>
        </table>
    </div>

    <AdminPager :links="threads.links" />
</template>
