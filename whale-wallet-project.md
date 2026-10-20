# Whale Wallet: project overview

*A personal finance app for people in the Philippines that tells you what you can safely spend, and lets you manage money by chatting with a friendly whale.*

Last updated: 2026-10-19. Written for sharing with marketing, copy and design collaborators. Everything below describes what the product does today. Items that are not live yet are listed under "Status and limits" at the end, so nothing is promised that is not built.

---

## 1. The product in one paragraph

Whale Wallet connects the money a person actually has (bank accounts, e-wallets, credit cards, cash) with the money they owe (bills, subscriptions, loans) and the money they are saving for (goals). From that it works out one number that matters: **how much you can spend each day until payday**. An AI assistant, Whale, answers questions about the user's money, records expenses from a single line of text, reads receipts from photos, and gives practical advice, all based on the user's own numbers. It is built for Philippine pesos (₱), Philippine banks and wallets, and the way Filipinos pay: GCash, Maya, BDO, BPI, Landbank and others, with salary on the 15th and 30th.

**Mascot:** a small blue whale, used in the chat, empty states and loading states.

## 2. Who it is for

- Salaried workers and freelancers in the Philippines who are paid once or twice a month and feel money run out before payday.
- People who juggle several accounts and e-wallets and lose track of where money went.
- People with subscriptions, bills and informal loans ("utang") who forget what is due.
- Anyone who wants budgeting without spreadsheets and without typing in long forms.

## 3. The problems it solves

| Problem | How Whale Wallet answers it |
|---|---|
| "Where did my money go?" | Every expense is charged to a card, categorised, searchable and exportable. |
| "Can I afford this today?" | A daily safe-to-spend number and a balance forecast up to payday. |
| "I forgot a bill again." | Colour-coded due dates, overdue alerts, push notifications and a daily digest. |
| "Logging expenses is tedious." | Type "Jollibee 250", scan a receipt, or tell the chat. |
| "I do not trust apps with my data." | AI only runs after consent, tips use totals only, no fabricated numbers, full data export and deletion. |

## 4. Features

### 4.1 Dashboard
- Net worth (assets minus debts), monthly income, what has been spent and what is left after bills.
- **Safe to spend per day** until the next payday.
- Forecast chart of the balance up to payday, including the lowest point.
- Spending by category (donut chart).
- **Upcoming bills** (overdue and the next 14 days), each with a colour chip: red today or overdue, orange within 3 days, yellow within a week, green beyond.
- **Whale tips**: short AI tips based on the user's real totals, refreshed daily.
- Salary entries with proof, and a guided first-run onboarding.
- Dark and light themes.

### 4.2 Cards (wallets and accounts)
- Banks, e-wallets, credit cards and cash, each shown as a card with the bank or wallet logo and its brand colour (for example MariBank orange).
- Recognises Philippine banks and wallets: GCash, Maya, GrabPay, ShopeePay, BDO, BPI, Metrobank, Landbank, PNB, Security Bank, UnionBank, RCBC, China Bank, EastWest, PSBank, CIMB, GoTyme, MariBank and more.
- Optional monthly spending limit with a warning at 80% and when exceeded.
- Account name, masked account number and a payment QR code kept with the card.
- Animated, softly drifting shapes on each card.
- **Move money** between cards and pods; transfers are never counted as spending.

### 4.3 Transactions
- Add an expense, record a transfer, use quick entry or scan a receipt, each in a pop-up dialog on one page.
- **Quick entry:** type "Jollibee 250 gcash" and the app fills in the merchant, amount and card for the user to confirm.
- Search and filters by text, dates, card and category.
- Edit or delete any expense; card balances adjust automatically and correctly.
- Export all transactions to CSV or Markdown (spreadsheet formula injection is neutralised).
- Custom categories.

### 4.4 Receipt scanning (AI)
- Upload a photo or screenshot of a GCash, Maya or bank receipt. The AI reads the **reference number, date, amount and merchant**.
- The user always reviews and confirms before anything is saved.
- Detects likely duplicates (same amount and merchant within 48 hours, or the same reference number).
- Receipt images can be deleted after saving or kept, the user's choice.

### 4.5 Bills, subscriptions and loans
- One page for recurring money going out, with logos (Netflix, YouTube, Google, Claude and others are recognised automatically; custom logos can be uploaded).
- Repeats weekly, every two weeks, monthly, quarterly or yearly.
- Cards are coloured by how soon the item is due. Clicking one opens a detail dialog with amount, next due date, monthly cost, last payment, the card it is paid from and a transaction history table.
- **Pay** with proof of payment: upload a receipt (reference number, date and amount are read automatically) or enter them by hand. The user picks the card used.
- **Subscriptions can be cancelled with a reason**, and stay on file so they can be renewed later. Cancelled items stop counting toward totals and forecasts.
- **Loans** have a number of payments (1, 2, 3, 6 or 12). A finished loan shows "Completed" and moves to an Archived tab with its history.
- Deleting a bill keeps every past payment in the transaction history.
- Edit, delete, cancel and pay all slide in beside the detail dialog.

### 4.5b Savings pods
- Savings goals shown as cards with a progress ring.
- Each pod has a target, a target date, the amount saved so far and a **card used to fund it**.
- Typing a target suggests the **monthly amount** needed, and a **projection chart** slides in showing when the goal will be reached at that pace.
- **Add money** from a card to a pod with proof of transfer (receipt scan or manual entry), and a table of every transfer into or out of the pod.

### 4.6 Forecast and payday
- Set one or two paydays a month (for example the 15th and 30th).
- Day-by-day balance projection until payday that accounts for bills due, subscriptions and recent spending patterns.
- Safe-to-spend per day and the lowest balance expected.
- A list of subscriptions and what they cost each month.

### 4.7 Whale Chat (AI assistant)
A full-page chat with the mascot, a floating message box, chat history and quick actions.
- **Log expenses:** "spent 250 at Jollibee on GCash". The user confirms with one tap.
- **Loans and bills:** "I borrowed 5000, 3 payments" or "paid Converge".
- **Salary:** tell Whale the amount and send the proof screenshot; it checks the numbers.
- **Questions with exact answers:** spending by category, total spent, safe to spend, monthly subscriptions. Figures come straight from the database, not from the AI.
- **Advice:** net worth, "can I afford it", bills due soon, saving ideas. The assistant sees a full snapshot of the user's finances and may only quote numbers that exist in it; any reply with an invented number is replaced by a safe one.
- **What is due:** "what's my due today?" returns cards for everything due today (overdue included); if nothing is due, the next 3 days; if nothing, the next 7.
- **Change things by chatting:** add a bill or subscription, change an amount or due date, cancel or renew a subscription, delete a bill, edit or delete a past expense. Every change shows a confirmation card first, and destructive ones are marked in red.
- **Several requests in one message** ("cancel Netflix and delete Rent") each get their own confirmation.
- Understands English, Tagalog and Taglish.
- **History panel:** start a new chat, rename chats, delete chats (a soft delete; they leave the list but are not erased until the user wipes everything in Settings).
- Receipt photos can be sent straight from the chat.

### 4.8 Daily Whale tips and email digest
- Tips are written by AI from totals only (no merchants or personal notes) and checked so every number comes from the user's data.
- They refresh every day at a time the user chooses, **9:00 AM Philippine time by default**.
- Users can opt in to receive the same tips by email at that time, and can change the time or switch it off in Settings.

### 4.9 Notifications (mobile)
Push notifications through Expo for:
- A bill due today, tomorrow or in two days.
- Budget limits reached.
- Payday coming up.
- A daily "safe to spend today" message.

### 4.10 Mobile app support
A complete, documented REST API (`/api/v1`) powers the companion mobile app: sign-up and login, profile and avatar, cards, pods (including adding money), categories, transactions and transfers, bills and subscriptions, receipts, income, chat, tips, forecast, dashboard, push device registration, data export and account deletion. Money travels as integer centavos. A single API contract document is maintained for the mobile developers.

### 4.11 Account, security and privacy
- Email and password sign-up with email verification, password reset and login throttling.
- **Two-factor authentication** and **passkeys**.
- **API tokens** that the user can list and revoke.
- **AI consent:** nothing is sent to the AI provider until the user agrees, and consent can be withdrawn.
- Per-user data isolation at the database query level.
- **Export everything** as a download, **wipe chat history**, or **delete the account** with its data and receipt images.
- Profile picture, appearance (light, dark, system) and payday settings.

---

## 5. What makes it different

1. **One number, not a dashboard of charts:** safe to spend per day until payday.
2. **Chat is a real control surface:** log, change, cancel, pay and ask, all in plain language, with confirmation before anything changes.
3. **The AI cannot make up numbers:** facts are computed by the app; the AI only classifies requests and phrases answers, and every figure it quotes is checked.
4. **Built for the Philippines:** peso formatting, local banks and wallets with their logos and colours, Philippine time, paydays on the 15th and 30th, Taglish understanding.
5. **Proof built in:** receipts are read automatically and attached to payments and transfers, with duplicate detection.
6. **Never destroys history:** deleting a bill, cancelling a subscription or archiving a loan keeps the past transactions.

## 6. Tech stack

**Backend**
- PHP 8.3, **Laravel 13**
- Laravel Fortify (authentication, two-factor, passkeys), Laravel Sanctum (mobile API tokens)
- Laravel AI SDK with **Google Gemini** for receipt reading, chat understanding, advice and tips
- Laravel scheduler for daily jobs (tips and digest, push alerts, recurring-item roll-forward)
- Money is stored as integers (centavos) everywhere, never floats
- Eloquent global scopes keep each user's data separate
- **PostgreSQL**
- PHPUnit test suite (about 250 tests)

**Frontend**
- **Vue 3** with **TypeScript** and **Inertia.js 3** (a single-page app without a separate API for the web)
- **Tailwind CSS 4**, shadcn-vue components built on reka-ui, Lucide icons
- Unovis for charts
- Vite 8 build, Laravel Wayfinder for typed routes
- Light and dark themes, responsive down to phone width, motion that respects reduced-motion settings

**Mobile**
- A separate companion app consumes the REST API; push notifications through **Expo**.

**Infrastructure**
- Hosted on **Railway**: a web service (FrankenPHP on Caddy), a scheduled worker service that runs every 5 minutes, and PostgreSQL.
- Deploys automatically from the `main` branch on GitHub.

## 7. Status and limits (be accurate in marketing copy)

- **Email delivery is not connected yet.** The daily digest and verification emails need a mail provider to be configured; until then the digest's tips still refresh on the dashboard but no email is sent.
- The mobile app is built in a separate repository; the API for it is complete and documented, and the mobile screens are being wired to it.
- Two-factor accounts cannot yet sign in on mobile.
- The currency is the Philippine peso only.
- Budgets are per card (monthly limit); per-category budgets are not built yet.
- Loan terms are limited to 1, 2, 3, 6 or 12 payments.
- AI features need Google Gemini availability; when it is down, the app still logs expenses manually and by quick entry.
- No pricing, plans or user numbers exist in the product yet, so none should be quoted.

## 8. Suggested messaging angles

- "Know what you can spend today, not just what you spent last month."
- "Tell Whale. It handles the rest." (chat-first money management)
- "Built for how Filipinos pay: GCash, Maya, BDO and payday on the 15th and 30th."
- "An AI that shows its work: every number comes from your own data."
- "Bills, subscriptions, savings goals and loans in one calm place."

## 9. Quick facts

| | |
|---|---|
| Name | Whale Wallet |
| Platform | Web app (responsive) with a companion mobile app in progress |
| Market | Philippines, in peso, Philippine time |
| AI provider | Google Gemini, only after the user consents |
| Hosting | Railway (auto-deployed from GitHub) |
| Source | GitHub: CodeDecoder13/Whale-Wallet-WebApp |
| Live web address | https://whale-wallet-webapp-production.up.railway.app |
