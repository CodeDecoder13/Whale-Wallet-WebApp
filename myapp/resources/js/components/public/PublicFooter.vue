<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { dashboard, login, register } from '@/routes';

const page = usePage();

type Item = { href: string; label: string; plain?: boolean };

const columns = (): { title: string; links: Item[] }[] => {
    const signedIn = !!page.props.auth.user;

    return [
        {
            title: 'Product',
            links: [
                { href: '/about', label: 'About' },
                { href: '/pricing', label: 'Pricing' },
                { href: '/docs', label: 'Documentation' },
                signedIn
                    ? { href: dashboard().url, label: 'Dashboard' }
                    : { href: register().url, label: 'Create account' },
            ],
        },
        {
            title: 'Company',
            links: [
                { href: '/contact', label: 'Contact' },
                ...(signedIn ? [] : [{ href: login().url, label: 'Log in' }]),
            ],
        },
        {
            title: 'Privacy',
            links: [
                { href: '/privacy', label: 'Privacy overview' },
                { href: '/privacy/ai', label: 'AI and your data' },
                { href: '/privacy/security', label: 'Security' },
                { href: '/privacy/processors', label: 'Who handles your data' },
                {
                    href: '/.well-known/security.txt',
                    label: 'Report a vulnerability',
                    plain: true,
                },
            ],
        },
        {
            title: 'Legal',
            links: [
                { href: '/privacy-policy', label: 'Privacy Policy' },
                { href: '/terms', label: 'Terms and Conditions' },
                { href: '/cookies', label: 'Cookies' },
            ],
        },
    ];
};

const linkClass = 'block py-1 hover:text-foreground hover:underline';
</script>

<template>
    <footer class="mt-6 border-t bg-muted/40 text-sm text-muted-foreground">
        <div class="mx-auto max-w-6xl px-4 pt-14 pb-7 md:px-8">
            <div
                class="grid grid-cols-2 gap-8 lg:grid-cols-[1.5fr_1fr_1fr_1.1fr_1.1fr]"
            >
                <div class="col-span-2 lg:col-span-1">
                    <Link
                        href="/"
                        class="flex items-center text-foreground"
                        aria-label="Whale Wallet home"
                    >
                        <AppLogo />
                    </Link>
                    <p class="mt-3 max-w-xs">
                        Stay Afloat, Spend Smart. A personal finance app for
                        people in the Philippines.
                    </p>
                    <p class="mt-2">
                        Operated by Rhuzzel Paramio · Metro Manila, Philippines
                    </p>
                </div>
                <nav v-for="c in columns()" :key="c.title" :aria-label="c.title">
                    <h4
                        class="mb-3 text-xs font-semibold tracking-widest text-foreground uppercase"
                    >
                        {{ c.title }}
                    </h4>
                    <template v-for="l in c.links" :key="l.href">
                        <a v-if="l.plain" :href="l.href" :class="linkClass">{{ l.label }}</a>
                        <Link v-else :href="l.href" :class="linkClass">{{ l.label }}</Link>
                    </template>
                    <a
                        v-if="c.title === 'Company'"
                        href="mailto:support@whalewallet.cloud"
                        :class="linkClass"
                    >
                        support@whalewallet.cloud
                    </a>
                </nav>
            </div>

            <p class="mt-9 max-w-3xl border-t pt-5 text-xs">
                Whale Wallet tracks your money; it does not hold or move it.
                Tips are budgeting guidance, not financial advice. AI features
                use Google Gemini and run only after you give consent.
            </p>
            <div
                class="mt-4 flex flex-wrap justify-between gap-x-6 gap-y-2 text-xs"
            >
                <span
                    >© {{ new Date().getFullYear() }} Whale Wallet. All rights
                    reserved.</span
                >
                <span class="flex gap-4">
                    <Link href="/privacy" class="hover:underline">Privacy</Link>
                    <Link href="/terms" class="hover:underline">Terms</Link>
                    <Link href="/cookies" class="hover:underline">Cookies</Link>
                </span>
            </div>
        </div>
    </footer>
</template>
