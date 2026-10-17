<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import RecommendationController from '@/actions/App/Http/Controllers/RecommendationController';
import { Button } from '@/components/ui/button';

const busy = ref(false);
const agree = () =>
    router.post(
        RecommendationController.consent.url(),
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
</script>

<template>
    <section
        class="max-w-2xl rounded-xl border border-brand/40 bg-accent/40 p-6"
    >
        <h2 class="font-medium">Before the first AI feature</h2>
        <p class="mt-2 text-sm text-muted-foreground">
            Tips, receipt reading and Whale Chat use Google Gemini. Google may
            use free-tier inputs to improve its products. Tips send only
            aggregated numbers (totals, percentages and your forecast). Receipt
            images and chat messages are sent as you upload or type them, so
            they can include merchants and amounts. Nothing is saved until you
            confirm it.
        </p>
        <Button class="mt-4" :disabled="busy" @click="agree">I agree</Button>
    </section>
</template>
