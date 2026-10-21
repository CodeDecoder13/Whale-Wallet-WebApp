<script setup lang="ts">
import Doc from '@/components/public/Doc.vue';
</script>

<template>
    <Doc
        title="Privacy Policy"
        description="How Whale Wallet collects, uses, shares and protects your personal data."
        updated="21 October 2026"
    >
        <p class="rounded-lg border bg-muted/40 p-3 text-sm">
            Draft notice: this policy has not been reviewed by a lawyer yet and
            may change. We will say so here when it has.
        </p>
        <p>
            <strong>Who we are.</strong> Rhuzzel Paramio, operating Whale Wallet
            from Metro Manila, Philippines. Contact:
            <a href="mailto:support@whalewallet.cloud"
                >support@whalewallet.cloud</a
            >.
        </p>

        <h2>1. What this policy covers</h2>
        <p>
            How Whale Wallet collects, uses, shares and protects your personal
            data when you use our website and app.
        </p>

        <h2>2. What we collect</h2>
        <p>
            Account details (name, email, password stored in hashed form), your
            profile picture, the financial information you enter (cards,
            balances, bills, loans, savings goals, income, categories and
            transactions), account numbers you choose to add (stored encrypted),
            receipt images and proofs you upload, your chat messages with Whale,
            your AI consent record, device tokens for notifications (mobile
            app), messages you send through the contact form, and technical data
            such as IP address, browser details and session information.
        </p>

        <h2>3. How we use it</h2>
        <p>
            To provide the app, calculate your daily safe-to-spend amount and
            forecasts, read receipts and answer chat requests, send reminders
            you ask for, secure your account, answer your messages, and improve
            the service.
        </p>

        <h2>4. AI features</h2>
        <p>
            Receipt reading, chat, voice transcription and daily tips use Google
            Gemini. We send your data to Google only after you give consent, and
            you can withdraw consent. Google may process data outside the
            Philippines, and Google's terms for the Gemini API apply. Totals
            shown in the app are calculated by Whale Wallet, not by the AI. See
            <a href="/privacy/ai">AI and your data</a>.
        </p>

        <h2>5. Who else receives data</h2>
        <p>
            Our hosting provider (Railway), Google for AI features, and Expo for
            push notifications once the mobile app is live. An email provider
            will be added when email is connected. We do not sell your personal
            data and we do not use it for advertising. See
            <a href="/privacy/processors">who handles your data</a>.
        </p>

        <h2>6. How long we keep it</h2>
        <p>
            Until you delete it or your account. Receipt images are deleted
            after you confirm unless you choose to keep them. Salary proofs stay
            until you remove that income entry. Deleted chats stay out of your
            list but are erased when you wipe history or delete your account.
            Contact messages are kept until we have answered them and for as
            long as needed to follow up.
        </p>

        <h2>7. Security</h2>
        <p>
            Account numbers are stored encrypted, accounts can use two-factor
            authentication and passkeys, each user's data is separated from
            other users, and sign-ins are rate limited. No system is perfectly
            secure; tell us at once if you suspect a problem. See
            <a href="/privacy/security">Security</a>.
        </p>

        <h2>8. Your rights</h2>
        <p>
            Under the Data Privacy Act of 2012 (Republic Act No. 10173) you have
            the right to be informed, to access, to correct, to object, to erase
            or block, to data portability and to claim damages, and you may
            complain to the National Privacy Commission. You can export your
            data and delete your account in Settings, or contact us.
        </p>

        <h2>9. Children</h2>
        <p>Whale Wallet is not for anyone under 18.</p>

        <h2>10. Changes</h2>
        <p>
            We will post changes here and in the
            <a href="/privacy/changes">privacy changes log</a> and update the
            date. For important changes we will notify you in the app or by
            email.
        </p>

        <h2>11. Contact and data protection contact</h2>
        <p>
            Rhuzzel Paramio,
            <a href="mailto:support@whalewallet.cloud"
                >support@whalewallet.cloud</a
            >, Metro Manila, Philippines.
        </p>
    </Doc>
</template>
