<script setup lang="ts">
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import SupportComposer from '@/components/SupportComposer.vue';
import SupportFile from '@/components/SupportFile.vue';
import { Button } from '@/components/ui/button';
import { adminBase, adminUrl } from '@/lib/adminUrl';
import { ago } from '@/lib/ago';

type Thread = { id: number; status: string; unread: number; at: string | null; subject: string | null; preview: string; user: { id: number; name: string; email: string } | null };
type Message = { id: number; body: string; from_admin: boolean; at: string; seen: boolean; file: { name: string; size: number; image: boolean } | null };

const props = defineProps<{
    threads: Thread[];
    open: { id: number; status: string; subject: string | null; closed_by: string | null; user: { id: number; name: string; email: string } | null } | null;
    messages: Message[];
    filter: string;
}>();

// a live inbox: list and open ticket refresh every 5 seconds (paused while the tab is hidden)
usePoll(5000, { only: ['threads', 'open', 'messages'] });

const isOwner = computed(() => (usePage().props.auth.user as { admin_role?: string }).admin_role === 'owner');
const form = useForm({ body: '', file: null as File | null });
const list = ref<HTMLElement | null>(null);
const filters = [{ value: 'open', label: 'Open' }, { value: 'unread', label: 'Unread' }, { value: 'closed', label: 'Closed' }];

const toBottom = () => nextTick(() => list.value?.scrollTo({ top: list.value.scrollHeight }));
watch(() => [props.open?.id, props.messages.length], toBottom, { immediate: true });

const send = () =>
    props.open && form.post(adminUrl(`/support/${props.open.id}/messages`), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });

const setStatus = (status: 'open' | 'closed') =>
    router.post(adminUrl(`/support/${props.open!.id}/status`), { status }, { preserveScroll: true, preserveState: true });
</script>

<template>
    <Head title="Support" />

    <div class="grid h-[calc(100svh-8rem)] gap-4 lg:grid-cols-[20rem_1fr]">
        <aside class="flex min-h-0 flex-col rounded-2xl border bg-card shadow-sm">
            <div class="flex gap-1 border-b p-2">
                <Link
                    v-for="f in filters"
                    :key="f.value"
                    :href="`${adminBase()}/support?filter=${f.value}`"
                    class="rounded-full px-3 py-1 text-sm transition-colors duration-150 ease-out"
                    :class="filter === f.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent'"
                >
                    {{ f.label }}
                </Link>
            </div>
            <ul class="min-h-0 flex-1 overflow-y-auto">
                <li v-for="t in threads" :key="t.id">
                    <Link
                        :href="`${adminBase()}/support?filter=${filter}&thread=${t.id}`"
                        preserve-scroll
                        class="block border-b px-4 py-3 text-sm transition-colors duration-150 ease-out hover:bg-accent/50"
                        :class="open?.id === t.id && 'bg-accent/60'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate font-semibold">{{ t.user?.name ?? 'Unknown' }}</span>
                            <span v-if="t.unread" class="grid min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-semibold text-white">{{ t.unread }}</span>
                        </div>
                        <div v-if="t.subject" class="truncate text-xs font-medium text-primary">{{ t.subject }}</div>
                        <div class="truncate text-muted-foreground">{{ t.preview }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">{{ ago(t.at) }}<template v-if="t.status === 'closed'"> · closed</template></div>
                    </Link>
                </li>
                <li v-if="!threads.length" class="p-6 text-center text-sm text-muted-foreground">No tickets here.</li>
            </ul>
        </aside>

        <section class="flex min-h-0 flex-col rounded-2xl border bg-card shadow-sm">
            <p v-if="!open" class="m-auto text-sm text-muted-foreground">Pick a ticket on the left.</p>

            <template v-else>
                <header class="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <div class="min-w-0">
                        <Link v-if="isOwner && open.user" :href="`${adminBase()}/users/${open.user.id}`" class="truncate font-semibold hover:underline">{{ open.user.name }}</Link>
                        <span v-else class="truncate font-semibold">{{ open.user?.name }}</span>
                        <div class="truncate text-xs text-muted-foreground">{{ open.user?.email }}<template v-if="open.subject"> · {{ open.subject }}</template></div>
                    </div>
                    <Button v-if="open.status === 'open'" variant="outline" size="sm" @click="setStatus('closed')">Close ticket</Button>
                    <Button v-else variant="outline" size="sm" @click="setStatus('open')">Reopen</Button>
                </header>

                <div ref="list" class="min-h-0 flex-1 overflow-y-auto p-4" aria-live="polite">
                    <ol class="grid gap-3">
                        <li v-for="m in messages" :key="m.id" class="flex flex-col" :class="m.from_admin ? 'items-end' : 'items-start'">
                            <div
                                v-if="m.body || m.file"
                                class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm break-words whitespace-pre-wrap"
                                :class="m.from_admin ? 'rounded-br-md bg-primary text-primary-foreground' : 'rounded-bl-md border bg-background'"
                            >
                                {{ m.body }}
                                <SupportFile v-if="m.file" :file="m.file" :url="`${adminBase()}/support/files/${m.id}`" />
                            </div>
                            <span class="mt-1 text-[11px] text-muted-foreground">{{ m.from_admin ? 'Support' : open.user?.name }} · {{ ago(m.at) }}<template v-if="m.from_admin && m.seen"> · Seen</template></span>
                        </li>
                    </ol>
                    <p v-if="open.status === 'closed'" class="mt-4 text-center text-xs text-muted-foreground">
                        Closed{{ open.closed_by ? ` by ${open.closed_by === 'user' ? 'the user' : 'support'}` : '' }}. We keep this conversation. A reply reopens it.
                    </p>
                </div>

                <div class="border-t p-3">
                    <SupportComposer v-model:body="form.body" v-model:file="form.file" :processing="form.processing" :error="form.errors.body || form.errors.file" placeholder="Reply" @send="send" />
                </div>
            </template>
        </section>
    </div>
</template>
