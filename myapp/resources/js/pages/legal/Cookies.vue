<script setup lang="ts">
import Doc from '@/components/public/Doc.vue';
</script>

<template>
    <Doc
        title="Cookies"
        description="Whale Wallet uses only the cookies it needs to work. No advertising and no tracking."
        updated="21 October 2026"
    >
        <h2>What we use</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Purpose</th>
                    <th>Kind</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Session cookie</td>
                    <td>
                        Keeps you signed in. Ends after 120 minutes of
                        inactivity.
                    </td>
                    <td>Essential</td>
                </tr>
                <tr>
                    <td>XSRF-TOKEN</td>
                    <td>Protects forms and requests from forgery</td>
                    <td>Essential</td>
                </tr>
                <tr>
                    <td>Remember me</td>
                    <td>
                        Keeps you signed in on this device, only if you tick
                        “Remember me”
                    </td>
                    <td>Essential</td>
                </tr>
                <tr>
                    <td>appearance</td>
                    <td>Remembers light, dark or system theme (365 days)</td>
                    <td>Preference</td>
                </tr>
                <tr>
                    <td>sidebar_state</td>
                    <td>Remembers whether the sidebar is open</td>
                    <td>Preference</td>
                </tr>
                <tr>
                    <td>appearance, whale-seen (local storage)</td>
                    <td>
                        Theme on this device, and whether today's Whale chat
                        nudge was seen
                    </td>
                    <td>Preference</td>
                </tr>
            </tbody>
        </table>

        <h2>What we do not use</h2>
        <p>
            No advertising cookies and no analytics or tracking cookies. Pages
            load their own fonts and scripts, so no other company sees your
            visit.
        </p>

        <h2>Managing cookies</h2>
        <p>
            You can delete or block cookies in your browser settings. Blocking
            essential cookies will stop sign-in from working.
        </p>

        <h2>Changes</h2>
        <p>
            If we add analytics or other cookies, we will ask for your consent
            first and update this page.
        </p>

        <h2>Contact</h2>
        <p>
            <a href="mailto:support@whalewallet.cloud"
                >support@whalewallet.cloud</a
            >
        </p>
    </Doc>
</template>
