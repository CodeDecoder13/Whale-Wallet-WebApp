<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineProps<{ tagline?: boolean }>();
</script>

<template>
    <AppLogoIcon class="size-9 shrink-0" />
    <span class="ml-1 grid min-w-0 leading-tight">
        <span class="truncate text-xl font-semibold tracking-tight">
            Whale<span class="text-brand-deep dark:text-brand-light">Wallet</span>
        </span>
        <span
            v-if="tagline"
            class="truncate text-[11px] font-normal tracking-normal text-muted-foreground"
        >
            Stay Afloat, Spend Smart
        </span>
    </span>
</template>
