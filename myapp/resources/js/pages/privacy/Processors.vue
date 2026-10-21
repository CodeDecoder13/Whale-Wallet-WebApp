<script setup lang="ts">
import Doc from '@/components/public/Doc.vue';
</script>

<template>
    <Doc
        title="Who handles your data"
        description="Every service that touches your data, and why."
        updated="21 October 2026"
    >
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Purpose</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Google (Gemini API)</td>
                    <td>Receipt reading, chat, voice transcription, tips</td>
                    <td>
                        Only after you consent. May be processed outside the
                        Philippines.
                    </td>
                </tr>
                <tr>
                    <td>Railway</td>
                    <td>Hosting of the app and database</td>
                    <td>
                        We are confirming and will publish the hosting region.
                    </td>
                </tr>
                <tr>
                    <td>Expo</td>
                    <td>Push notifications for the mobile app</td>
                    <td>Used once the mobile app is live.</td>
                </tr>
                <tr>
                    <td>Email provider</td>
                    <td>Verification, password reset, optional digest</td>
                    <td>
                        Not connected yet. This page will name the provider when
                        it is.
                    </td>
                </tr>
            </tbody>
        </table>
        <p>
            We do not sell your data and we do not use ad or analytics services.
            Pages are served with their own fonts and scripts, so visiting the
            site does not contact other companies.
        </p>
    </Doc>
</template>
