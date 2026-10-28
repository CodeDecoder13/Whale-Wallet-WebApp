<script setup lang="ts">
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { adminBase, adminUrl } from '@/lib/adminUrl';
import { dashboard } from '@/routes';

const page = usePage();
const owner = computed(() => (page.props.auth.user as { admin_role?: string }).admin_role === 'owner');
const unread = computed(() => page.props.adminSupportUnread);

// the support unread count stays live on every admin page
usePoll(30_000, { only: ['adminSupportUnread'] });

// owners see every area; a support admin sees the support inbox only
const tabs = computed(() => [
    ...(owner.value ? [{ title: 'Overview', href: adminUrl() }] : []),
    { title: 'Support', href: adminUrl('/support'), badge: unread.value },
    ...(owner.value
        ? [
              { title: 'Users', href: adminUrl('/users') },
              { title: 'Transactions', href: adminUrl('/transactions') },
              { title: 'Receipts', href: adminUrl('/receipts') },
              { title: 'Conversations', href: adminUrl('/chats') },
              { title: 'Loans & subs', href: adminUrl('/loans') },
              { title: 'Activity', href: adminUrl('/activity') },
          ]
        : []),
]);

const here = computed(() => page.url.split('?')[0]);
const active = (href: string) => (href === adminUrl() ? here.value === adminUrl() : here.value.startsWith(href));

// on its own host (admin.example.com) "the app" is the main site, not a page of this host
const appUrl = () => (adminBase() === '' ? `${location.protocol}//${location.host.replace(/^admin\./, '')}/dashboard` : dashboard().url);
</script>

<template>
    <div class="min-h-svh bg-background">
        <header class="border-b bg-card">
            <div class="mx-auto flex max-w-screen-2xl items-center gap-4 px-4 py-3 md:px-8">
                <Link :href="owner ? adminUrl() : adminUrl('/support')" class="flex items-center gap-2" aria-label="Admin home">
                    <AppLogo />
                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">Admin</span>
                </Link>
                <nav class="flex flex-1 gap-1 overflow-x-auto" aria-label="Admin">
                    <Link
                        v-for="t in tabs"
                        :key="t.href"
                        :href="t.href"
                        class="flex shrink-0 items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium transition-colors duration-150 ease-out"
                        :class="active(t.href) ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                    >
                        {{ t.title }}
                        <span v-if="t.badge" class="grid min-w-5 place-items-center rounded-full bg-red-500 px-1.5 text-[11px] font-semibold text-white">{{ t.badge }}</span>
                    </Link>
                </nav>
                <a :href="appUrl()" class="text-sm text-muted-foreground underline-offset-4 hover:underline">Back to the app</a>
            </div>
        </header>
        <main class="mx-auto max-w-screen-2xl px-4 py-6 md:px-8">
            <slot />
        </main>
    </div>
</template>
