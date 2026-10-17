# Whale Wallet: Web App Task Plan (for AI agents)

Read this file first, then `whale-wallet-project-plan.md` for the product context. This file covers the Laravel + Vue 3 web app and the API that the mobile app uses. The mobile app has its own file: `plan-task-mobile.md`.

Status legend: **DECIDED** = chosen by the user, do not change without asking. **PROPOSED** = my suggestion, follow it unless the user says otherwise. **VERIFY** = check current docs before relying on it.

## 1. Rules that must never be broken

1. **All data lives in the web app.** Laravel owns every record, screenshot and chat message. **DECIDED**
2. **The Gemini API key exists only in Laravel's `.env`.** No client (web or mobile) ever receives it, and every AI call goes through Laravel. **DECIDED**
3. **The forecast and all budget math are plain PHP** in a service class with Pest tests. Gemini may explain results but never computes or invents numbers. **DECIDED**
4. **Money is stored and sent as integer centavos.** Never floats. Currency is PHP in v1; keep a `currency` column.
5. **Nothing from AI is saved until the user confirms** (confirm card, then Save).
6. **Every query is scoped to the signed-in user** through policies and scoped queries. No endpoint returns another user's rows.
7. Free-tier Gemini inputs may be used by Google to improve its products. Show a consent notice before the first chat or receipt upload and record the consent. Plan to move to the paid tier before real users onboard. **DECIDED (beta only on free tier)**
8. The app gives budgeting guidance, not licensed financial advice. Show a disclaimer where recommendations appear.

## 2. Stack

| Area           | Choice                                                                                                               | Status                               |
| -------------- | -------------------------------------------------------------------------------------------------------------------- | ------------------------------------ |
| Backend        | Laravel 13, PHP 8.3, Pest                                                                                            | DECIDED (Laravel); versions PROPOSED |
| Frontend       | Vue 3 + TypeScript + Vite, Inertia, Tailwind CSS 4, shadcn-vue (Reka UI), lucide-vue-next                            | DECIDED (Vue 3); libraries PROPOSED  |
| Starter        | Official Laravel Vue starter kit                                                                                     | PROPOSED                             |
| Charts         | shadcn-vue chart components (Unovis) for bars, donuts, sparklines; vue-echarts only for the forecast chart if needed | PROPOSED                             |
| Tables / forms | TanStack Table (Vue), vee-validate + Zod                                                                             | PROPOSED                             |
| Auth           | Starter kit login for web; Laravel Sanctum personal access tokens for mobile                                         | PROPOSED                             |
| Database       | PostgreSQL (MySQL acceptable)                                                                                        | PROPOSED                             |
| Queue          | Redis + Horizon; `ParseReceipt` and chat jobs                                                                        | PROPOSED                             |
| Files          | Private S3-compatible disk, signed temporary URLs                                                                    | PROPOSED                             |
| AI             | Gemini Flash (free tier) via Laravel AI SDK (Gemini provider) or `google-gemini-php/client`                          | DECIDED (Gemini); client VERIFY      |
| Hosting        | Laravel Cloud, Forge on a VPS, or Railway                                                                            | open                                 |

VERIFY before coding: that the chosen Gemini client supports image input together with a JSON response schema, and the current free-tier model names and limits (sources conflict; reports range from about 5 to 15 requests per minute and 100 to 1,500 per day).

## 3. Conventions

- Put business logic in `app/Actions/*` (single-purpose classes). Web controllers and `/api/v1` controllers both call the same actions.
- Forecast code: `app/Services/Forecast/*`. Keep it pure (inputs in, numbers out) so it is easy to test.
- AI code behind one interface, `app/Contracts/AiProvider.php`, with a `GeminiProvider` implementation and a `FakeAiProvider` for tests. Do not call Gemini directly from controllers.
- Use Form Requests for validation, API Resources for responses, Policies for authorization.
- Frontend: Composition API with `<script setup lang="ts">`. Generate TypeScript types from Laravel where possible.
- Format amounts with `Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })`.
- Dark mode support from the start (Tailwind + shadcn-vue tokens).

## 4. Data model (v1, PROPOSED)

All tables have `user_id` (except `users`) and timestamps. Amounts in centavos (`bigInteger`).

| Table | Key fields |
|---|---|
| `users` | name, email, pay schedule (`pay_dates` JSON or `pay_cycle`), `ai_consent_at` |
| `income_sources` | name, amount, schedule |
| `cards` | name, type (`ewallet`, `bank`, `cash`, `credit`), balance, monthly_limit nullable, color |
| `pods` | name, target_amount, saved_amount, target_date nullable, monthly_amount |
| `categories` | name, parent_id nullable, icon |
| `transactions` | card_id, category_id, type (`expense`, `income`, `transfer`), amount, merchant, occurred_at, note, source (`manual`, `quick`, `receipt`, `chat`), receipt_id nullable, reference nullable |
| `recurring_items` | name, amount, card_id, category_id, cadence, next_due_on, kind (`bill`, `subscription`, `installment`, `debt`) |
| `receipts` | status (`queued`, `parsing`, `parsed`, `failed`, `confirmed`), path, parsed_json, confidence, error |
| `chat_threads`, `chat_messages` | role, content, draft_json nullable, receipt_id nullable |
| `recommendations` | kind, text, data_json, dismissed_at |
| `personal_access_tokens` | Sanctum; include device name |
| `devices` | expo_push_token, platform |

Transfers between cards and pods are `transfer` transactions and never count as spending.

## 5. API contract for mobile (`/api/v1`, PROPOSED)

All responses JSON, amounts as integer centavos, auth via `Authorization: Bearer <token>`. Rate-limit AI endpoints per user.

| Method and path | Purpose |
|---|---|
| `POST /auth/login`, `POST /auth/logout`, `GET /me` | Token auth |
| `GET /dashboard` | Safe-to-spend today, cards, pods, upcoming bills, forecast summary, recommendations |
| `GET/POST/PATCH/DELETE /cards`, `/pods`, `/categories` | Wallet cards and savings pods |
| `GET/POST/PATCH/DELETE /transactions` | Ledger (filters: date range, card, category, search) |
| `GET/POST/PATCH/DELETE /recurring-items` | Bills, subscriptions, installments |
| `GET /forecast` | Day-by-day projected balance to next payday |
| `POST /receipts` (multipart image) | Returns `202` with receipt id; parsing runs on a queue |
| `GET /receipts/{id}` | Status and draft transaction once parsed |
| `POST /receipts/{id}/confirm` | User-approved fields; creates the transaction |
| `POST /chat/messages` (text, optional image) | Returns `{ reply, draft?, quick_replies? }` |
| `POST /chat/drafts/{id}/confirm` | Saves a chat-created draft |
| `GET /recommendations`, `POST /recommendations/{id}/dismiss` | AI tips |
| `POST /devices`, `DELETE /devices/{id}` | Push token registration |
| `POST /consent/ai` | Records AI consent |
| `GET /export/transactions.csv` | CSV export |

Receipt status uses polling in v1 (client polls `GET /receipts/{id}` every 1 to 2 seconds). Laravel Reverb is optional later.

## 6. Tasks

Complete milestones in order. Each task has acceptance criteria; do not mark done without running the checks.

### M0: Foundations
- [x] Create the Laravel 13 project from the official Vue starter kit; commit. (in `myapp/`, commit a967ff3)
- [x] Configure PostgreSQL, Vitest (bundled with `vp test`), ESLint/Prettier (`vp check`). Redis/Horizon, S3 disk and Pest moved to M5 by the user: dev uses `database` queue and `local` disk, tests are PHPUnit.
- [x] GitHub Actions: `.github/workflows/tests.yml` from the starter kit.
- [x] Brand tokens from `whale-wallet-logo.svg` (blue #1E88E5 / #1565C0, logo in sidebar) as Tailwind theme variables, light and dark.
- [x] `.env.example` lists every key (including `GEMINI_API_KEY`) with no real values.
- **Done when:** a new clone runs `composer install`, `npm install`, `php artisan migrate --seed`, and the login page loads; CI is green.

### M1: Budget core
- [x] Migrations, models, factories for the tables in section 4 (cards, pods, categories, transactions, recurring_items, income_sources). Ownership uses the `BelongsToUser` global scope (also covers route-model binding) instead of per-model policies. Caveat: the scope only applies when authenticated, so queue jobs must set the user explicitly.
- [x] Seeder with the founder's example and a test that remaining after fixed costs is 15,844 (`BudgetSummary`); cross-user isolation test for all six models.
- [x] Onboarding wizard: income and pay dates, fixed bills with due dates, first card, optional savings pod.
- [x] Wallet cards UI: create/edit spending cards and savings pods, balances, optional limit with 80 percent warning.
- [x] Manual expense log with search and filter; every expense charged to a card; transfers between cards and pods.
- [x] Quick entry: parse text like `Jollibee 250` into a draft (simple rules first; Gemini fallback only after M3).
- [x] Dashboard: safe-to-spend today, income vs fixed vs discretionary, category chart, upcoming bills.
- [x] Seeder using the founder's example: income 35,000, loan 9,000 (due the 27th), rent 8,000, subscriptions about 2,156 per month.
- [x] CSV export. [ ] Markdown export.
- [ ] Edit and delete transactions (delete/edit must reverse or adjust the card balance in a DB transaction).
- [ ] Category management (add, rename, delete categories).
- [ ] Browser check of all M1 pages with data (transactions rows, pod rings, dark mode, onboarding).
- **Done when:** a seeded user sees correct totals (example remaining after fixed costs: 15,844) and all rows are user-scoped (a test proves user A cannot read user B's data).

### M2: Forecast, subscriptions, recommendations
- [ ] Subscription tracker built on `recurring_items`: next charge date, monthly total.
- [ ] `ForecastService`: known outflows by due date + trailing 30-day average per category (recent weeks weighted more) up to the next payday; outputs daily balance, lowest point and safe-to-spend.
- [ ] Pest tests: payday boundary, bills on the 27th, month-end, zero history, cards with limits, savings pod contributions set aside each payday.
- [ ] Forecast chart on the dashboard (Unovis; vue-echarts only if a confidence band is needed).
- [ ] `AiProvider` interface, `GeminiProvider`, `FakeAiProvider`; recommendation job that sends only aggregated numbers (not raw transactions where avoidable) and returns 3 to 5 tips; show disclaimer.
- [ ] Recommendations must cite the user's own numbers; reject output that contains figures not present in the input (simple validator).
- **Done when:** forecast tests pass, recommendations render with the fake provider in tests, and a real Gemini call works locally with the key in `.env`.

### M3: Screenshot intake and Whale Chat (web)
- [ ] Consent screen and `POST /consent/ai`; block AI features until consent exists.
- [ ] Receipt upload (drag and drop, paste, file picker) to the private disk; queue `ParseReceipt`.
- [ ] `ParseReceipt` job: downscale the image, send to Gemini with a strict JSON schema (merchant, amount, currency, occurred_at, reference, category guess, confidence), validate (amount > 0, plausible date), run duplicate check (same amount + merchant within a time window, or same reference).
- [ ] Confirm card UI: pre-filled fields, edit, Save. Delete the image after confirmation by default (setting to keep it).
- [ ] Whale Chat: `chat_threads/messages`; Gemini returns JSON with `intent` (`log_expense`, `question`, `chat`), draft, missing fields and reply. If a field is missing, ask one short follow-up. For `question`, use fixed server functions (for example spending by category for a date range); Gemini only words the answer from returned numbers.
- [ ] Rate limits per user; on quota errors or 429 from Gemini, return a clear message and offer the manual form.
- [ ] Screenshot sample set (5 to 10 receipts from e-wallet and bank apps) kept in `tests/Fixtures` with expected fields, for parsing accuracy checks (run with the real model manually, with the fake provider in CI).
- **Done when:** uploading a sample receipt produces a correct draft, nothing saves before Save, duplicates are flagged, and chat can log and answer the example conversation (Jollibee 250, "how much food this week?").

### M4: Mobile API readiness (work with `plan-task-mobile.md`)
- [ ] Implement every endpoint in section 5 with API Resources and feature tests.
- [ ] Sanctum token auth with device names; token revoke from the web settings page.
- [ ] `POST /devices` and push notifications for bills due and budget warnings (scheduled Laravel commands; Expo push service).
- [ ] OpenAPI or a documented Postman/Bruno collection so the mobile agent can rely on exact shapes.
- **Done when:** the mobile app can complete login, dashboard, log, receipt upload and chat against a staging server.

### M5: Beta hardening
- [ ] Deferred from M0: Redis + Horizon, private S3-compatible disk, Pest, Playwright, first green CI run on GitHub.
- [ ] Privacy: data export, account deletion, delete chat history, audit that no secrets reach the client.
- [ ] Move AI to the paid Gemini tier (or another provider through the same interface) before onboarding real users.
- [ ] Parsing accuracy tracking: log corrections users make on confirm cards.
- [ ] Error monitoring and queue failure alerts.

### Installed libraries (added with the user's approval)

`@unovis/ts`, `@unovis/vue` (charts), `@tanstack/vue-table`, `vee-validate`, `@vee-validate/zod`, `zod`, `@vue/test-utils`, `jsdom`. Vitest comes with `vite-plus` (`vp test`); installing it separately causes a peer conflict. Not yet installed: Horizon/Redis, vue-echarts, Gemini client (VERIFY first), Sanctum (M4).

## 7. Testing and definition of done

- Every task: Pest tests for logic, Vitest for non-trivial components, and one Playwright flow for the main journeys (onboarding, log expense, receipt upload).
- Money and forecast code: table-driven tests with exact centavo expectations.
- No task is done if tests, lint or type checks fail.
- Do not add dependencies beyond section 2 without asking.

## 8. Out of scope for v1

Bank sync, moving money, investment tracking or advice, multiple currencies, offline mode, any data stored on the phone, shared budgets (Phase 3), email-receipt forwarding (Phase 3).

## 9. Open questions to confirm with the user

1. Personal project, portfolio piece or a product to sell (affects billing and polish).
2. Hours per week available (timeline).
3. Which payment apps matter most for screenshots (GCash, Maya, BPI, BDO, UnionBank, Shopee).
4. Free for now or free tier plus paid plan.
5. Hosting choice.