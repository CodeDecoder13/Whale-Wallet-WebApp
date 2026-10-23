<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Lock, Menu, X } from '@lucide/vue';
import { ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const page = usePage();
const menu = ref(false);

const links = [
    { href: '/about', label: 'About' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/privacy', label: 'Privacy', lock: true },
    { href: '/docs', label: 'Documentation' },
    { href: '/contact', label: 'Contact' },
];

// the page itself, or one below it (/privacy/ai keeps Privacy lit; /privacy-policy does not)
const on = (href: string) =>
    page.url === href ||
    page.url.startsWith(`${href}/`) ||
    page.url.startsWith(`${href}?`);
</script>

<template>
    <div class="sticky top-0 z-30 border-b bg-background/90 backdrop-blur">
        <header
            class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 md:px-8"
        >
            <Link
                href="/"
                class="flex items-center"
                aria-label="Whale Wallet home"
            >
                <AppLogo tagline />
            </Link>

            <nav
                id="public-nav"
                aria-label="Main"
                class="absolute inset-x-0 top-full flex-col gap-0.5 border-b bg-background px-4 pt-3 pb-5 text-base md:static md:flex md:flex-row md:items-center md:gap-1 md:border-0 md:bg-transparent md:p-0 md:text-sm"
                :class="menu ? 'flex' : 'hidden'"
            >
                <Link
                    v-for="l in links"
                    :key="l.href"
                    :href="l.href"
                    :aria-current="on(l.href) ? 'page' : undefined"
                    class="flex min-h-11 items-center gap-1.5 rounded-md px-3 text-muted-foreground hover:bg-muted md:min-h-9"
                    :class="[
                        on(l.href) && 'font-semibold text-foreground',
                        l.lock &&
                            'font-semibold text-brand-deep dark:text-brand-light',
                    ]"
                    @click="menu = false"
                >
                    <Lock v-if="l.lock" class="size-3.5" aria-hidden="true" />
                    {{ l.label }}
                </Link>
                <span
                    class="mx-2 hidden h-5 w-px bg-border md:block"
                    aria-hidden="true"
                />
                <Button
                    v-if="page.props.auth.user"
                    as-child
                    class="mt-2 md:mt-0"
                >
                    <Link :href="dashboard()">Dashboard</Link>
                </Button>
                <template v-else>
                    <Link
                        :href="login()"
                        class="flex min-h-11 items-center rounded-md px-3 text-muted-foreground hover:bg-muted md:min-h-9"
                    >
                        Log in
                    </Link>
                    <Button as-child class="mt-2 md:mt-0">
                        <Link :href="register()">Create account</Link>
                    </Button>
                </template>
            </nav>

            <Button
                variant="outline"
                size="icon"
                class="md:hidden"
                :aria-label="menu ? 'Close menu' : 'Open menu'"
                :aria-expanded="menu"
                aria-controls="public-nav"
                @click="menu = !menu"
            >
                <X v-if="menu" aria-hidden="true" />
                <Menu v-else aria-hidden="true" />
            </Button>
        </header>
    </div>
</template>
