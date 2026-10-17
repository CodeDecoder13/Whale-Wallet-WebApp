<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Sparkles, X } from '@lucide/vue';
import { ref } from 'vue';
import RecommendationController from '@/actions/App/Http/Controllers/RecommendationController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/tips';

defineProps<{
    tips: { id: number; text: string }[];
    consented: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tips', href: index() }] },
});

const busy = ref(false);
const post = (url: string) =>
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
</script>

<template>
    <Head title="Whale tips" />

    <div class="flex flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                class="mb-0!"
                title="Whale tips"
                description="Short tips written from your own numbers."
            />
            <Button
                v-if="consented"
                :disabled="busy"
                @click="post(RecommendationController.store.url())"
            >
                <Sparkles /> {{ busy ? 'Thinking...' : 'Generate tips' }}
            </Button>
        </div>

        <section
            v-if="!consented"
            class="max-w-2xl rounded-xl border border-brand/40 bg-accent/40 p-6"
        >
            <h2 class="font-medium">Before the first tip</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Tips are written by Google Gemini. Google may use free-tier
                inputs to improve its products. Only aggregated numbers are sent
                (totals, percentages and your forecast), never merchants or
                individual transactions.
            </p>
            <Button
                class="mt-4"
                :disabled="busy"
                @click="post(RecommendationController.consent.url())"
            >
                I agree
            </Button>
        </section>

        <template v-else>
            <p v-if="!tips.length" class="text-sm text-muted-foreground">
                No tips yet. Generate a few from this month's numbers.
            </p>
            <ul v-else class="grid max-w-3xl gap-3">
                <li
                    v-for="t in tips"
                    :key="t.id"
                    class="flex items-start gap-3 rounded-xl border bg-card p-5 text-sm"
                >
                    <Sparkles class="mt-0.5 size-4 shrink-0 text-brand" />
                    <p class="flex-1">{{ t.text }}</p>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground"
                        aria-label="Dismiss tip"
                        @click="
                            post(RecommendationController.dismiss.url(t.id))
                        "
                    >
                        <X class="size-4" />
                    </button>
                </li>
            </ul>
        </template>

        <p class="text-xs text-muted-foreground">
            Budgeting guidance, not financial advice.
        </p>
    </div>
</template>
