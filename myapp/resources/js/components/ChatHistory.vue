<script lang="ts">
export type Thread = { id: number; title: string | null; updated_at: string };
</script>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MessageSquarePlus, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Mascot from '@/components/Mascot.vue';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api';
import { index } from '@/routes/chat';
import { Input } from '@/components/ui/input';
import { destroy, store, update } from '@/routes/chat/threads';

const props = defineProps<{ threads: Thread[]; activeId: number | null }>();
const emit = defineEmits<{ pick: [] }>();

const busy = ref(false);
const confirming = ref<number | null>(null);
const editing = ref<number | null>(null);
const title = ref('');

function startEdit(t: Thread) {
    confirming.value = null;
    title.value = t.title ?? '';
    editing.value = t.id;
}

async function rename(id: number) {
    const next = title.value.trim();
    editing.value = null;

    if (next) {
        await api(update.url(id), { title: next }, 'PATCH');
        router.reload({ only: ['threads'] });
    }
}

const open = (id: number) => {
    emit('pick');
    router.get(index().url, { thread: id }, { preserveScroll: true });
};

async function startNew() {
    busy.value = true;

    try {
        const t = await api<{ id: number }>(store.url(), {});
        open(t.id);
    } finally {
        busy.value = false;
    }
}

// Soft delete: the chat leaves this list but its rows stay until the user wipes everything in Settings.
async function remove(id: number) {
    await api(destroy.url(id), undefined, 'DELETE');
    confirming.value = null;
    emit('pick');
    router.get(index().url);
}

const when = (d: string) =>
    new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3 p-4">
        <Button class="w-full" :disabled="busy" @click="startNew">
            <MessageSquarePlus /> New chat
        </Button>

        <h2 class="px-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
            History
        </h2>

        <div
            v-if="!props.threads.length"
            class="m-auto flex max-w-48 flex-col items-center gap-2 text-center text-sm text-muted-foreground"
        >
            <Mascot pose="happy" round class="size-14" />
            <p>Your chats land here. Start a new one for each money topic.</p>
        </div>

        <ul v-else class="-mx-1 flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-1">
            <li v-for="t in props.threads" :key="t.id">
                <div
                    v-if="confirming === t.id"
                    class="grid gap-2 rounded-xl border border-destructive/40 bg-destructive/5 p-3 text-sm"
                >
                    <p>Delete this chat? It leaves your history.</p>
                    <div class="flex gap-2">
                        <Button size="sm" variant="destructive" @click="remove(t.id)">Delete</Button>
                        <Button size="sm" variant="ghost" @click="confirming = null">Keep</Button>
                    </div>
                </div>
                <form v-else-if="editing === t.id" class="px-1" @submit.prevent="rename(t.id)">
                    <Input
                        v-model="title"
                        maxlength="80"
                        aria-label="Chat name"
                        autofocus
                        @keydown.esc="editing = null"
                        @blur="rename(t.id)"
                    />
                </form>
                <div
                    v-else
                    class="group flex items-center gap-1 rounded-xl pr-1 transition-colors"
                    :class="t.id === props.activeId ? 'bg-accent' : 'hover:bg-muted'"
                >
                    <button
                        type="button"
                        class="min-w-0 flex-1 rounded-xl px-3 py-2 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :aria-current="t.id === props.activeId ? 'true' : undefined"
                        @click="open(t.id)"
                    >
                        <span class="block truncate text-sm font-medium">
                            {{ t.title ?? 'New chat' }}
                        </span>
                        <span class="block text-xs text-muted-foreground">{{ when(t.updated_at) }}</span>
                    </button>
                    <button
                        type="button"
                        class="grid size-8 shrink-0 place-items-center rounded-lg text-muted-foreground opacity-0 transition hover:bg-background hover:text-foreground focus-visible:opacity-100 group-hover:opacity-100 max-lg:opacity-100"
                        :aria-label="`Rename ${t.title ?? 'chat'}`"
                        @click="startEdit(t)"
                    >
                        <Pencil class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="grid size-8 shrink-0 place-items-center rounded-lg text-muted-foreground opacity-0 transition hover:bg-background hover:text-destructive focus-visible:opacity-100 group-hover:opacity-100 max-lg:opacity-100"
                        :aria-label="`Delete ${t.title ?? 'chat'}`"
                        @click="confirming = t.id"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
