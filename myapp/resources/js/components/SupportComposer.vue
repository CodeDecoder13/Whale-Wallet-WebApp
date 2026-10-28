<script setup lang="ts">
import { Paperclip, SendHorizontal, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

// the message box shared by the user's support page and the admin inbox: text, one attachment, Enter to send
const body = defineModel<string>('body', { required: true });
const file = defineModel<File | null>('file', { default: null });
const props = defineProps<{ processing?: boolean; placeholder?: string; error?: string }>();
const emit = defineEmits<{ send: [] }>();

const MAX = 5 * 1024 * 1024;
const area = ref<HTMLTextAreaElement | null>(null);
const picker = ref<HTMLInputElement | null>(null);
const tooBig = ref('');
const canSend = computed(() => !props.processing && (body.value.trim() !== '' || file.value !== null));

defineExpose({ focus: () => area.value?.focus() });

function pick(e: Event) {
    const input = e.target as HTMLInputElement;
    const f = input.files?.[0] ?? null;
    tooBig.value = f && f.size > MAX ? 'That file is over 5 MB.' : '';
    file.value = f && f.size <= MAX ? f : file.value;
    input.value = ''; // so choosing the same file again still fires
}

const send = () => canSend.value && emit('send');

// Enter sends, Shift+Enter makes a new line
function onKey(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}
</script>

<template>
    <form class="grid gap-2" @submit.prevent="send">
        <div v-if="file" class="flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-1.5 text-xs">
            <Paperclip class="size-3.5 shrink-0" />
            <span class="truncate">{{ file.name }}</span>
            <button type="button" class="ml-auto rounded p-0.5 hover:bg-accent" aria-label="Remove the file" @click="file = null"><X class="size-3.5" /></button>
        </div>

        <div class="flex items-end gap-2">
            <input ref="picker" type="file" accept="image/png,image/jpeg,image/webp,application/pdf" class="sr-only" tabindex="-1" @change="pick" />
            <Button type="button" variant="outline" class="h-12 active:scale-[0.97]" aria-label="Attach a file" @click="picker?.click()"><Paperclip class="size-4" /></Button>
            <textarea
                ref="area"
                v-model="body"
                rows="2"
                maxlength="1000"
                :placeholder="placeholder ?? 'Write a message'"
                aria-label="Message"
                class="min-h-12 flex-1 resize-none rounded-xl border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @keydown="onKey"
            />
            <Button :disabled="!canSend" aria-label="Send" class="h-12 active:scale-[0.97]"><SendHorizontal class="size-4" /></Button>
        </div>
        <p class="text-[11px] text-muted-foreground">Pictures (JPG, PNG, WebP) or a PDF, up to 5 MB.</p>
        <InputError :message="tooBig || error" />
    </form>
</template>
