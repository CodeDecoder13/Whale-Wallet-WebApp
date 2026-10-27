<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as tokens } from '@/routes/tokens';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
    {
        title: 'Devices',
        href: tokens(),
    },
    {
        title: 'Your data',
        href: '/settings/data',
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="mx-auto w-full max-w-screen-2xl px-4 py-6">
        <Heading
            title="Account"
            description="Your profile and how you sign in"
        />

        <nav class="-mt-2 mb-6 flex gap-2 overflow-x-auto pb-1" aria-label="Settings">
            <Link
                v-for="item in sidebarNavItems"
                :key="toUrl(item.href)"
                :href="item.href"
                class="shrink-0 rounded-full border px-4 py-2 text-sm font-medium transition-colors duration-150 ease-out active:scale-[0.97]"
                :class="
                    isCurrentOrParentUrl(item.href)
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'bg-card text-muted-foreground hover:bg-accent hover:text-foreground'
                "
            >
                {{ item.title }}
            </Link>
        </nav>

        <!-- every block a page renders becomes its own card; a lone block spans both columns -->
        <section
            class="grid items-start gap-6 lg:grid-cols-2 xl:grid-cols-3 lg:[&>*:only-child]:col-span-full [&>*:not(.sr-only)]:mt-0! [&>*:not(.sr-only)]:rounded-2xl [&>*:not(.sr-only)]:border [&>*:not(.sr-only)]:bg-card [&>*:not(.sr-only)]:p-6 [&>*:not(.sr-only)]:shadow-sm"
        >
            <slot />
        </section>
    </div>
</template>
