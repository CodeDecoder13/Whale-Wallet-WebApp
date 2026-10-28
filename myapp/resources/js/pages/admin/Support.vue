<script setup lang="ts">
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { SendHorizontal } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { adminBase, adminUrl } from '@/lib/adminUrl';
import { ago } from '@/lib/ago';

type Thread = { id: number; status: string; unread: number; at: string | null; preview: string; user: { id: number; name: string; email: string } | null };
type Message = { id: number; body: string; from_admin: boolean; at: string; seen: boolean };

const props = defineProps<{
    threads: Thread[];
    open: { id: number; status: string; user: { id: number; name: string; email: string } | null } | null;
    messages: Message[];
    filter: string;
}>();

// a live inbox: list and open chat refresh every 5 seconds (paused while the tab is hidden)
usePoll(5000, { only: ['threads', 'open', 'messages'] });

const isOwner = computed(() => (usePage().props.auth.user as { admin_role?: string }).admin_role === 'owner');
const form = useForm({ body: '' });
const list = ref<HTMLElement | null>(null);
const filters = [{ value: 'open', label: 'Open' }, { value: 'unread', label: 'Unread' }, { value: 'closed', label: 'Closed' }];

const toBottom = () => nextTick(() => list.value?.scrollTo({ top: list.value.scrollHeight }));
watch(() => [props.open?.id, props.messages.length], toBottom, { immediate: true });

function send() {
    if (!props.open || !form.body.trim() || form.processing) {
        return;
    }

    form.post(adminUrl(`/support/${props.open.id}/messages`), { preserveScroll: true, onSuccess: () => form.reset() });
}

const setStatus = (status: 'open' | 'closed') =>
    router.post(adminUrl(`/support/${props.open!.id}/status`), { status }, { preserveScroll: true, preserveState: true });

function onKey(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}
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
                        <div class="truncate text-muted-foreground">{{ t.preview }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">{{ ago(t.at) }}<template v-if="t.status === 'closed'"> · closed</template></div>
                    </Link>
                </li>
                <li v-if="!threads.length" class="p-6 text-center text-sm text-muted-foreground">No chats here.</li>
            </ul>
        </aside>

        <section class="flex min-h-0 flex-col rounded-2xl border bg-card shadow-sm">
            <p v-if="!open" class="m-auto text-sm text-muted-foreground">Pick a chat on the left.</p>

            <template v-else>
                <header class="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <div class="min-w-0">
                        <Link v-if="isOwner && open.user" :href="`${adminBase()}/users/${open.user.id}`" class="truncate font-semibold hover:underline">{{ open.user.name }}</Link>
                        <span v-else class="truncate font-semibold">{{ open.user?.name }}</span>
                        <div class="truncate text-xs text-muted-foreground">{{ open.user?.email }}</div>
                    </div>
                    <Button v-if="open.status === 'open'" variant="outline" size="sm" @click="setStatus('closed')">Close chat</Button>
                    <Button v-else variant="outline" size="sm" @click="setStatus('open')">Reopen</Button>
                </header>

                <div ref="list" class="min-h-0 flex-1 overflow-y-auto p-4" aria-live="polite">
                    <ol class="grid gap-3">
                        <li v-for="m in messages" :key="m.id" class="flex flex-col" :class="m.from_admin ? 'items-end' : 'items-start'">
                            <div
                                class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm break-words whitespace-pre-wrap"
                                :class="m.from_admin ? 'rounded-br-md bg-primary text-primary-foreground' : 'rounded-bl-md border bg-background'"
                            >
                                {{ m.body }}
                            </div>
                            <span class="mt-1 text-[11px] text-muted-foreground">{{ m.from_admin ? 'Support' : open.user?.name }} · {{ ago(m.at) }}<template v-if="m.from_admin && m.seen"> · Seen</template></span>
                        </li>
                    </ol>
                </div>

                <form class="grid gap-2 border-t p-3" @submit.prevent="send">
                    <div class="flex items-end gap-2">
                        <textarea
                            v-model="form.body"
                            rows="2"
                            maxlength="1000"
                            placeholder="Reply"
                            aria-label="Reply"
                            class="min-h-12 flex-1 resize-none rounded-xl border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @keydown="onKey"
                        />
                        <Button :disabled="form.processing || !form.body.trim()" aria-label="Send" class="h-12 active:scale-[0.97]"><SendHorizontal class="size-4" /></Button>
                    </div>
                    <InputError :message="form.errors.body" />
                </form>
            </template>
        </section>
    </div>
</template>
