<script setup lang="ts">
import Doc from '@/components/public/Doc.vue';
</script>

<template>
    <Doc
        title="AI and your data"
        description="Exactly what is sent to Google Gemini, when, and how to say no."
        updated="21 October 2026"
    >
        <h2>What is sent</h2>
        <table>
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>What leaves the app</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Receipt reading</td>
                    <td>The receipt or proof image you upload</td>
                </tr>
                <tr>
                    <td>Whale Chat</td>
                    <td>
                        The message you type, with the context needed to answer
                        it
                    </td>
                </tr>
                <tr>
                    <td>Voice input</td>
                    <td>
                        The audio clip, for transcription. We do not keep the
                        recording.
                    </td>
                </tr>
                <tr>
                    <td>Daily tips</td>
                    <td>Totals only. No merchants and no notes.</td>
                </tr>
            </tbody>
        </table>

        <h2>When</h2>
        <p>
            Only after you agree. Receipts, chat, voice and tips will not run
            until you give consent, and you can withdraw it later.
        </p>

        <h2>Without AI</h2>
        <p>
            The app still works. You can add expenses, bills, loans and pods by
            hand from their pages.
        </p>

        <h2>What the AI does not do</h2>
        <p>
            Your totals and forecast are calculated by Whale Wallet, not by the
            AI. The AI reads your request and phrases the answer, and nothing it
            drafts is saved until you confirm.
        </p>

        <h2>Google's side</h2>
        <p>
            The AI is Google Gemini, so Google processes this data, possibly
            outside the Philippines. Google's own terms for the Gemini API apply
            to that processing. See
            <a href="/privacy/processors">who handles your data</a>.
        </p>
    </Doc>
</template>
