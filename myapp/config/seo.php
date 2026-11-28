<?php

// The indexable pages, keyed by route name: drives the server-rendered meta tags and the sitemap. Anything not listed is noindex.
return [
    'pages' => [
        'home' => ['Whale Wallet: know what is safe to spend before payday', 'Whale Wallet is a personal finance app for the Philippines. See what is safe to spend until payday, with cards, bills, loans and net worth in one place.'],
        'about' => ['About Whale Wallet', 'Whale Wallet is a personal finance app for people in the Philippines. It shows what is safe to spend each day until payday and lets you manage money by chatting.'],
        'pricing' => ['Pricing - Whale Wallet', 'Whale Wallet is free while we are in early access.'],
        'docs' => ['Documentation - Whale Wallet', 'Short how-to guides for every part of Whale Wallet.'],
        'privacy' => ['Privacy overview - Whale Wallet', 'The privacy promises Whale Wallet is designed around.'],
        'privacy.ai' => ['AI and your data - Whale Wallet', 'Exactly what is sent to Google Gemini, when, and how to say no.'],
        'privacy.security' => ['Security - Whale Wallet', 'What protects your account, and the honest limits.'],
        'privacy.processors' => ['Who handles your data - Whale Wallet', 'Every service that touches your data, and why.'],
        'privacy.changes' => ['Privacy changes - Whale Wallet', 'Every change to how we handle data, newest first.'],
        'privacy.policy' => ['Privacy Policy - Whale Wallet', 'How Whale Wallet collects, uses, shares and protects your personal data.'],
        'terms' => ['Terms and Conditions - Whale Wallet', 'The rules for using Whale Wallet.'],
        'cookies' => ['Cookies - Whale Wallet', 'Whale Wallet uses only the cookies it needs to work. No advertising and no tracking.'],
        'contact' => ['Contact - Whale Wallet', 'Questions, feedback or a privacy request? Write to the Whale Wallet team.'],
    ],

    // shown on the home page and published as FAQPage data, so keep every answer true
    'faq' => [
        ['What is Whale Wallet?', 'A personal finance app for people in the Philippines. It shows what is safe to spend each day until payday and keeps your cards, bills, subscriptions, loans, savings pods and net worth in one place.'],
        ['Is Whale Wallet free?', 'Yes, it is free while it is in early access. If paid plans are ever added, you will be told well before anything changes.'],
        ['Does Whale Wallet hold or move my money?', 'No. It only tracks your money. It does not hold it, move it or give financial advice.'],
        ['Which banks and e-wallets can I add?', 'You can add cards and wallets such as GCash, BDO, BPI, Metrobank, UnionBank, GoTyme and Maribank, each with its own logo and colors.'],
        ['Can I track money other people owe me?', 'Yes. Who Owes You records cash you lent and emails the borrower the details and reminders.'],
        ['What happens when a bill is not paid on time?', 'It stays overdue until you mark it paid. It does not move to the next month on its own.'],
        ['Does it use AI?', 'Receipt reading, chat and tips use Google Gemini. You agree before the first use, and nothing is saved until you tap Save.'],
        ['Can I take my data with me?', 'Yes. You can export your data as a file, or delete your account and its data.'],
    ],
];
