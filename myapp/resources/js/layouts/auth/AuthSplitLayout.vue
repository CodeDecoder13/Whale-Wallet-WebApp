<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import DashboardPreview from '@/components/DashboardPreview.vue';
import { home } from '@/routes';

const name = usePage().props.name as string;

// where the dashboard preview sits: Register puts it on the right, Login on the left
withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        side?: 'left' | 'right';
    }>(),
    { side: 'right' },
);
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-2">
        <div
            class="flex flex-col px-6 py-8 sm:px-12"
            :class="side === 'left' && 'lg:order-2'"
        >
            <Link :href="home()" class="flex items-center gap-2 text-lg font-semibold">
                <AppLogoIcon class="size-8" />
                {{ name }}
            </Link>
            <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-8 py-10">
                <div class="space-y-2">
                    <h1 v-if="title" class="text-3xl font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
                </div>
                <slot />
            </div>
        </div>

        <div
            class="relative hidden overflow-hidden bg-linear-to-br from-navy to-navy-2 p-12 text-white lg:block"
            :class="side === 'left' && 'lg:order-1'"
        >
            <h2 class="max-w-md text-4xl leading-tight font-semibold tracking-tight text-balance">
                Know what is safe to spend before payday.
            </h2>
            <p class="mt-3 max-w-md text-sm text-white/70">
                Your cards, bills, loans and spending in one clear number.
            </p>
            <div class="absolute top-56 left-16">
                <DashboardPreview />
            </div>
        </div>
    </div>
</template>
