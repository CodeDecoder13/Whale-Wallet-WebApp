<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import TokenController from '@/actions/App/Http/Controllers/Settings/TokenController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/tokens';

type Token = {
    id: number;
    name: string;
    created_at: string;
    last_used_at: string | null;
};

defineProps<{ tokens: Token[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Devices', href: index() }],
    },
});

const when = (iso: string) =>
    new Date(iso).toLocaleString('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

function revoke(t: Token) {
    if (confirm(`Sign out ${t.name}? It will need to log in again.`)) {
        router.delete(TokenController.destroy.url(t.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Devices" />

    <h1 class="sr-only">Devices</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Devices"
            description="Mobile devices signed in to your account. Revoke one to sign it out."
        />

        <ul class="divide-y rounded-xl border bg-card">
            <li
                v-for="t in tokens"
                :key="t.id"
                class="flex items-center justify-between gap-3 p-3 text-sm"
            >
                <span class="min-w-0">
                    <span class="block truncate font-medium">{{ t.name }}</span>
                    <span class="block text-xs text-muted-foreground">
                        Signed in {{ when(t.created_at) }} · Last used
                        {{ t.last_used_at ? when(t.last_used_at) : 'never' }}
                    </span>
                </span>
                <Button variant="outline" size="sm" @click="revoke(t)">
                    Revoke
                </Button>
            </li>
            <li
                v-if="!tokens.length"
                class="p-3 text-sm text-muted-foreground"
            >
                No devices signed in.
            </li>
        </ul>
    </div>
</template>
