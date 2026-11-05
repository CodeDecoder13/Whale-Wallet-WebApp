<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Mascot from '@/components/Mascot.vue';
import { Button } from '@/components/ui/button';

// One branded page for every error the server renders: the mascot's mood matches how bad it is.
const props = defineProps<{ status: number }>();

const copy: Record<number, { title: string; text: string; pose: 'thinking' | 'worried' | 'checking' | 'excited' }> = {
    403: { title: 'This spot is off limits', text: 'You do not have access to this page. If you think that is a mistake, contact support.', pose: 'worried' },
    404: { title: 'We could not find that page', text: 'The link may be old or mistyped. Your money is safe; let us get you back on track.', pose: 'thinking' },
    419: { title: 'Your session timed out', text: 'For your safety we signed the page out after it sat idle. Refresh and try again.', pose: 'checking' },
    429: { title: 'Easy there, too many requests', text: 'Please wait a minute, then try again.', pose: 'checking' },
    500: { title: 'Something went wrong on our side', text: 'We have been told about it. Nothing you entered was lost. Try again in a moment.', pose: 'worried' },
    503: { title: 'Whale Wallet is taking a short break', text: 'We are doing maintenance or having a brief problem. Please try again soon.', pose: 'checking' },
};

const reload = () => window.location.reload();
const page = usePage();
const c = computed(() => copy[props.status] ?? copy[500]);
const home = computed(() => (page.props.auth?.user ? '/dashboard' : '/'));
</script>

<template>
    <Head :title="`${status} · ${c.title}`" />
    <main class="flex min-h-screen flex-col items-center justify-center gap-6 px-6 py-12 text-center">
        <Mascot :pose="c.pose" class="size-40 sm:size-52" />
        <p class="text-sm font-semibold tracking-widest text-brand-deep uppercase dark:text-brand-light">Error {{ status }}</p>
        <h1 class="max-w-xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{{ c.title }}</h1>
        <p class="max-w-md text-muted-foreground">{{ c.text }}</p>
        <div class="flex flex-wrap justify-center gap-3">
            <Button as-child>
                <Link :href="home">{{ page.props.auth?.user ? 'Back to my dashboard' : 'Back to home' }}</Link>
            </Button>
            <Button variant="outline" @click="reload">Try again</Button>
        </div>
    </main>
</template>
