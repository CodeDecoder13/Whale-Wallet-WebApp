<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Doc from '@/components/public/Doc.vue';
import { Button } from '@/components/ui/button';
import { dashboard, register } from '@/routes';

// Early access: no plan or price is shown because the app cannot bill anything yet.
const includes = [
    'Cards, bills, subscriptions, loans and savings pods',
    'Daily safe-to-spend number and payday forecast',
    'Whale Chat, receipt reading and daily tips (AI features need your consent)',
    'Export your data as a file, any time',
];
</script>

<template>
    <Doc
        title="Pricing"
        description="Whale Wallet is free while we are in early access."
    >
        <p>
            We are still learning what is most useful. If we ever add paid
            plans, we will tell you well before anything changes, and you will
            always be able to export your data.
        </p>
        <h2>What you get today</h2>
        <ul>
            <li v-for="i in includes" :key="i">{{ i }}</li>
        </ul>
        <div class="mt-8 flex flex-wrap gap-3">
            <Button v-if="$page.props.auth.user" size="lg" as-child>
                <Link :href="dashboard()">Open dashboard</Link>
            </Button>
            <Button v-else size="lg" as-child>
                <Link :href="register()">Create account</Link>
            </Button>
            <Button size="lg" variant="outline" as-child>
                <Link href="/contact">Tell us what you would pay for</Link>
            </Button>
        </div>
    </Doc>
</template>
