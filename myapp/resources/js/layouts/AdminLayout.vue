<script setup lang="ts">
import { adminUrl } from '@/lib/adminUrl';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { dashboard } from '@/routes';

// one row of tabs per admin area; phases add to this list as they ship
const tabs = [
    { title: 'Overview', href: adminUrl() },
    { title: 'Users', href: adminUrl('/users') },
    { title: 'Transactions', href: adminUrl('/transactions') },
    { title: 'Receipts', href: adminUrl('/receipts') },
    { title: 'Conversations', href: adminUrl('/chats') },
    { title: 'Loans & subs', href: adminUrl('/loans') },
    { title: 'Activity', href: adminUrl('/activity') },
];

const page = usePage();
const here = computed(() => page.url.split('?')[0]);
const active = (href: string) => (href === adminUrl() ? here.value === adminUrl() : here.value.startsWith(href));
</script>

<template>
    <div class="min-h-svh bg-background">
        <header class="border-b bg-card">
            <div class="mx-auto flex max-w-screen-2xl items-center gap-4 px-4 py-3 md:px-8">
                <Link :href="adminUrl()" class="flex items-center gap-2" aria-label="Admin home">
                    <AppLogo />
                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">Admin</span>
                </Link>
                <nav class="flex flex-1 gap-1 overflow-x-auto" aria-label="Admin">
                    <Link
                        v-for="t in tabs"
                        :key="t.href"
                        :href="t.href"
                        class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors duration-150 ease-out"
                        :class="active(t.href) ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                    >
                        {{ t.title }}
                    </Link>
                </nav>
                <Link :href="dashboard()" class="text-sm text-muted-foreground underline-offset-4 hover:underline">Back to the app</Link>
            </div>
        </header>
        <main class="mx-auto max-w-screen-2xl px-4 py-6 md:px-8">
            <slot />
        </main>
    </div>
</template>
