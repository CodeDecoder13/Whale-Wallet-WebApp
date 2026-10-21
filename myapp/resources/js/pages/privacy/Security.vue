<script setup lang="ts">
import Doc from '@/components/public/Doc.vue';
</script>

<template>
    <Doc
        title="Security"
        description="What protects your account, and the honest limits."
        updated="21 October 2026"
    >
        <h2>What we do</h2>
        <ul>
            <li>Account numbers are stored encrypted.</li>
            <li>Passwords are stored hashed, never in plain text.</li>
            <li>Sign in with two-factor authentication or a passkey.</li>
            <li>Each person's data is separated from everyone else's.</li>
            <li>Sign-in attempts are rate limited.</li>
            <li>Mobile and API access tokens can be revoked in Settings.</li>
            <li>
                Receipt images are deleted after you confirm, unless you keep
                them.
            </li>
            <li>
                Browser protections are sent with every page: no MIME sniffing,
                no framing by other sites, a strict referrer policy, and HTTPS
                only where the site is served over HTTPS.
            </li>
        </ul>

        <h2>Honest limits</h2>
        <p>
            No system is perfectly secure. Whale Wallet is built and run by one
            person. The mobile app does not have two-factor sign-in yet.
        </p>

        <h2>Report a vulnerability</h2>
        <p>
            Email
            <a href="mailto:support@whalewallet.cloud"
                >support@whalewallet.cloud</a
            >, or see <a href="/.well-known/security.txt">security.txt</a>.
            Reports made in good faith will not lead to action against you.
            Please do not access other people's data or disrupt the service
            while testing.
        </p>
    </Doc>
</template>
