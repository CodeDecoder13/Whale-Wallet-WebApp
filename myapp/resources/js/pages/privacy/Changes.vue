<script setup lang="ts">
import Doc from '@/components/public/Doc.vue';

// Newest first. Add a line here whenever data handling changes.
const log = [
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
        updated="21 October 2026"
    >
        <ul>
            <li v-for="l in log" :key="l[1]">
                <strong>{{ l[0] }}.</strong> {{ l[1] }}
            </li>
        </ul>
    </Doc>
</template>
