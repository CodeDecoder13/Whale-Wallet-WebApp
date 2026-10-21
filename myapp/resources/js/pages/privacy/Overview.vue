<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Doc from '@/components/public/Doc.vue';
</script>

<template>
    <Doc
        title="Privacy overview"
        description="Whale Wallet is an independent project, built by one person who uses it too. Your money is personal, and these are the promises we design around."
        updated="21 October 2026"
    >
        <h2>Six promises</h2>
        <ol>
            <li>
                <strong>We never sell your data.</strong> Your numbers are used
                to run the app for you, nothing else.
            </li>
            <li>
                <strong>No ads, no trackers.</strong> No advertising or
                analytics cookies, and no ad networks.
            </li>
            <li>
                <strong>AI only with your OK.</strong> Nothing goes to the AI
                until you agree. You can withdraw any time, and the app still
                works without it.
            </li>
            <li>
                <strong>Nothing saves until you confirm.</strong> Receipts and
                chat messages become records only after you tap Save.
            </li>
            <li>
                <strong>Receipt images do not pile up.</strong> They are deleted
                after you confirm, unless you choose to keep them.
            </li>
            <li>
                <strong>Take it all, or delete it all.</strong> Export your data
                or delete your account and its data from Settings.
            </li>
        </ol>

        <h2>What we collect and why</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Why</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Name, email, password (stored hashed)</td>
                    <td>Your account and sign-in</td>
                </tr>
                <tr>
                    <td>
                        Cards, balances, bills, loans, pods, income, categories,
                        transactions
                    </td>
                    <td>The app's features</td>
                </tr>
                <tr>
                    <td>Account numbers you add (stored encrypted)</td>
                    <td>Shown on your cards</td>
                </tr>
                <tr>
                    <td>Receipt images and salary proofs</td>
                    <td>Reading amounts and keeping proof</td>
                </tr>
                <tr>
                    <td>Chat messages</td>
                    <td>Whale Chat and its history</td>
                </tr>
                <tr>
                    <td>
                        Session and security data (IP address, browser details)
                    </td>
                    <td>Keep you signed in and protect your account</td>
                </tr>
            </tbody>
        </table>

        <h2>Your controls</h2>
        <ul>
            <li>
                Export everything:
                <Link href="/settings/data">Settings, Data</Link> (sign in
                first).
            </li>
            <li>
                Delete your account:
                <Link href="/settings/profile">Settings, Profile</Link>.
            </li>
            <li>AI consent: you are asked before the first use.</li>
        </ul>

        <h2>Read more</h2>
        <ul>
            <li><Link href="/privacy/ai">AI and your data</Link></li>
            <li><Link href="/privacy/security">Security</Link></li>
            <li>
                <Link href="/privacy/processors">Who handles your data</Link>
            </li>
            <li><Link href="/privacy/changes">Privacy changes</Link></li>
            <li>
                <Link href="/privacy-policy">Privacy Policy</Link> (the formal
                document)
            </li>
        </ul>
    </Doc>
</template>
