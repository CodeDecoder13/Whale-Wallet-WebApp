<script setup lang="ts">
import Doc from '@/components/public/Doc.vue';

// User guide: one page, one section per topic. Split into /docs/{slug} when it passes about 15 articles.
const sections: { id: string; title: string; items: [string, string][] }[] = [
    {
        id: 'start',
        title: 'Getting started',
        items: [
            [
                'Create an account',
                'Choose Create account, then follow the short setup: your payday, your income, your first cards and your bills.',
            ],
            [
                'Set your payday',
                'Your payday decides how many days your money must last. Whale Wallet counts the days left and shows how much is safe to spend each day.',
            ],
            [
                'How the daily number works',
                'It takes the money you have, subtracts bills, subscriptions and loan payments due before payday, then divides what is left by the days to payday.',
            ],
        ],
    },
    {
        id: 'cards',
        title: 'Cards and money',
        items: [
            [
                'Add a card',
                'Pick a bank, e-wallet or credit card from the list, or add cash. Each keeps its own logo and color. Account numbers are stored encrypted.',
            ],
            [
                'Spending limits',
                'Budgets are set per card. The card shows how much of its limit you have used.',
            ],
            [
                'Move money',
                'Transfer between cards, or fund a savings pod from a card.',
            ],
        ],
    },
    {
        id: 'bills',
        title: 'Bills, subscriptions and loans',
        items: [
            [
                'Add a bill or subscription',
                'Enter the name, amount, due date and how often it repeats. Due dates are color-coded.',
            ],
            [
                'Mark as paid',
                'Marking a bill paid moves its next due date forward. You can attach proof.',
            ],
            [
                'Cancel, renew and archive',
                'Cancelling a subscription keeps your past payments in the record. A cancelled item can be renewed.',
            ],
            [
                'Loans',
                'Choose how many payments the loan has. It ends after the last one and moves to the archive.',
            ],
        ],
    },
    {
        id: 'pods',
        title: 'Savings pods',
        items: [
            [
                'Create a goal',
                'Give the pod a name, a target and a date. A suggested monthly amount and a projection show when you will get there.',
            ],
            [
                'Add money',
                'Fund a pod from a card. You can attach proof of the transfer.',
            ],
        ],
    },
    {
        id: 'expenses',
        title: 'Logging expenses',
        items: [
            [
                'Quick entry',
                'Type a line such as “Jollibee 250” to add an expense.',
            ],
            [
                'Edit and categorize',
                'Open any expense to change the amount, card or category.',
            ],
            [
                'Export',
                'Download your transactions as CSV or Markdown from the Transactions page.',
            ],
        ],
    },
    {
        id: 'receipts',
        title: 'Receipts',
        items: [
            [
                'Upload a receipt',
                'Upload a PNG, JPG or WebP screenshot. The merchant, amount, date and reference are read for you to check. This uses AI and needs your consent.',
            ],
            [
                'Confirm',
                'Nothing is saved until you tap Save. Duplicate reference numbers are flagged. After you confirm, the image is deleted unless you choose to keep it.',
            ],
        ],
    },
    {
        id: 'chat',
        title: 'Whale Chat',
        items: [
            [
                'What you can ask',
                'Log an expense, add a loan, record your salary, mark a bill paid, or ask about your money. English, Tagalog and Taglish work.',
            ],
            [
                'Voice',
                'Tap the microphone to speak. Audio goes to Google for transcription and the recording is not kept.',
            ],
            [
                'Confirmation cards',
                'Every change shows a card first. Nothing happens until you confirm. Destructive actions are marked in red.',
            ],
            [
                'History',
                'Chats are listed in the history. Wipe them from Settings, Data.',
            ],
        ],
    },
    {
        id: 'forecast',
        title: 'Forecast and tips',
        items: [
            [
                'Forecast',
                'Projects your balance up to payday using your bills, subscriptions and recent spending, and shows the lowest point.',
            ],
            [
                'Tips',
                'Short suggestions based on your totals. Every number in a tip comes from your data.',
            ],
        ],
    },
    {
        id: 'account',
        title: 'Account and privacy',
        items: [
            [
                'Sign-in security',
                'Turn on two-factor authentication or add a passkey in Settings, Security.',
            ],
            [
                'AI consent',
                'AI features run only after you agree. You can see what is sent on the AI and your data page.',
            ],
            [
                'Export or delete',
                'Settings, Data lets you download everything as a file. Settings, Profile lets you delete your account together with its data and receipt images.',
            ],
        ],
    },
    {
        id: 'help',
        title: 'Troubleshooting',
        items: [
            [
                'Email did not arrive',
                'Email delivery is not connected yet, so verification and reset emails may not be sent. Contact us and we will help.',
            ],
            [
                'A receipt was read wrongly',
                'Correct the amount, date or reference in the confirm card before you save, or discard it and try a clearer screenshot.',
            ],
            [
                'AI is unavailable',
                'Everything also works by hand: add expenses from the Transactions page. Try the AI again in a minute.',
            ],
        ],
    },
];
</script>

<template>
    <Doc
        title="Documentation"
        description="Short how-to guides for every part of Whale Wallet."
    >
        <nav
            aria-label="On this page"
            class="mt-6 rounded-xl border bg-muted/40 p-4 text-sm"
        >
            <ul class="grid gap-x-6 sm:grid-cols-2">
                <li v-for="s in sections" :key="s.id">
                    <a :href="`#${s.id}`">{{ s.title }}</a>
                </li>
            </ul>
        </nav>
        <section v-for="s in sections" :id="s.id" :key="s.id">
            <h2>{{ s.title }}</h2>
            <template v-for="i in s.items" :key="i[0]">
                <h3>{{ i[0] }}</h3>
                <p class="text-muted-foreground">{{ i[1] }}</p>
            </template>
        </section>
        <p class="mt-10">Still stuck? <a href="/contact">Contact us</a>.</p>
    </Doc>
</template>
