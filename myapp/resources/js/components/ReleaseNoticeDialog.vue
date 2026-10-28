<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Mascot from '@/components/Mascot.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';

// "What's new": the newest release note this user has not dismissed, shown once; the server stops sending it after "Got it"
const page = usePage();
const notice = computed(() => page.props.releaseNotice);
const dismissed = ref<number | null>(null); // hides it at once, before the server round trip finishes
const open = computed(() => !!notice.value && dismissed.value !== notice.value.id);

function gotIt() {
    if (!notice.value) {
        return;
    }

    dismissed.value = notice.value.id;
    router.post(`/notices/${notice.value.id}/seen`, {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v: boolean) => !v && gotIt()">
        <DialogContent v-if="notice" class="max-h-[90svh] gap-4 overflow-y-auto sm:max-w-md">
            <div class="flex items-center gap-3">
                <Mascot pose="excited" bare class="w-14 shrink-0" />
                <div class="min-w-0">
                    <div class="text-xs font-semibold tracking-wide text-primary uppercase">
                        What's new<template v-if="notice.version"> · {{ notice.version }}</template>
                    </div>
                    <DialogTitle class="text-lg leading-snug">{{ notice.title }}</DialogTitle>
                </div>
            </div>
            <DialogDescription as="div" class="grid gap-3 text-sm text-foreground">
                <template v-for="(b, i) in notice.blocks" :key="i">
                    <p v-if="b.type === 'p'">{{ b.text }}</p>
                    <ul v-else class="list-disc space-y-1.5 pl-5">
                        <li v-for="item in b.items" :key="item">{{ item }}</li>
                    </ul>
                </template>
            </DialogDescription>
            <Button class="justify-self-end active:scale-[0.97]" @click="gotIt">Got it</Button>
        </DialogContent>
    </Dialog>
</template>
