<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CalendarDays, Moon, Plus, Search, Sun } from '@lucide/vue';
import { computed, ref } from 'vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useAppearance } from '@/composables/useAppearance';
import { useInitials } from '@/composables/useInitials';
import { usePage } from '@inertiajs/vue3';
import { index } from '@/routes/transactions';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const title = computed(() => props.breadcrumbs.at(-1)?.title ?? '');
const user = computed(() => usePage().props.auth.user);
const today = new Date();
const dateLine = today.toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});
const monthLabel = today.toLocaleDateString('en-GB', {
    month: 'long',
    year: 'numeric',
});

const { getInitials } = useInitials();
const { resolvedAppearance, updateAppearance } = useAppearance();
const q = ref('');
const search = () => router.get(index().url, q.value ? { q: q.value } : {});
</script>

<template>
    <header
        class="flex h-20 shrink-0 items-center gap-3 border-b bg-card px-4 md:px-8"
    >
        <SidebarTrigger class="-ml-1 md:hidden" />
        <div class="min-w-0 flex-1">
            <div class="truncate text-xs text-muted-foreground">
                {{ dateLine }}
            </div>
            <div class="truncate text-lg font-medium">{{ title }}</div>
        </div>
        <div
            class="hidden h-10 items-center gap-2 rounded-lg border px-3 text-sm lg:flex"
        >
            <CalendarDays class="size-4 text-muted-foreground" />
            {{ monthLabel }}
        </div>
        <form
            class="hidden h-10 w-56 items-center gap-2 rounded-lg border px-3 text-sm md:flex"
            @submit.prevent="search"
        >
            <Search class="size-4 text-muted-foreground" />
            <input
                v-model="q"
                type="search"
                placeholder="Search anything"
                aria-label="Search transactions"
                class="w-full bg-transparent outline-none placeholder:text-muted-foreground"
            />
        </form>
        <Link
            :href="`${index().url}#add-expense`"
            class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-brand-deep"
        >
            <Plus class="size-4" />
            <span class="hidden sm:inline">Add expense</span>
        </Link>
        <button
            type="button"
            class="rounded-lg p-2 text-muted-foreground hover:bg-accent"
            aria-label="Toggle dark mode"
            @click="
                updateAppearance(
                    resolvedAppearance === 'dark' ? 'light' : 'dark',
                )
            "
        >
            <Sun v-if="resolvedAppearance === 'dark'" class="size-5" />
            <Moon v-else class="size-5" />
        </button>
        <Avatar class="size-9">
            <AvatarFallback
                class="bg-sidebar-accent text-sm text-sidebar-accent-foreground"
            >
                {{ getInitials(user.name) }}
            </AvatarFallback>
        </Avatar>
    </header>
</template>
