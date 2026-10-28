<script setup lang="ts">
import Doc from '@/components/public/Doc.vue';

// Newest first. Add a line here whenever data handling changes.
const log = [
    [
        '28 October 2026',
        'Support tickets: you can attach pictures or PDFs (up to 5 MB) to support messages. Ending a ticket closes it for you, but we keep the conversation and its files for safekeeping until you delete your account.',
    ],
    [
        '28 October 2026',
        'Added an admin area for staff and a support chat. Named administrators can now see account details, transactions, loans and subscriptions, receipts and receipt images, and Whale Chat conversations to help users and keep the service safe. They sign in with a password, a PIN and an emailed code, and every access is logged. We also keep an activity record (sign-ins, scans, and similar) for 90 days. See section 6 of the Privacy Policy.',
    ],
    [
        '21 October 2026',
        'Published the privacy pages and the contact form. Messages sent through the form are stored in our database until an email provider is connected.',
    ],
    [
        '21 October 2026',
        'The home page no longer loads a font from rsms.me, so no page contacts another company. Added browser security headers.',
    ],
];
</script>

<template>
    <Doc
        title="Privacy changes"
        description="Every change to how we handle data, newest first."
        updated="28 October 2026"
    >
        <ul>
            <li v-for="l in log" :key="l[1]">
                <strong>{{ l[0] }}.</strong> {{ l[1] }}
            </li>
        </ul>
    </Doc>
</template>
