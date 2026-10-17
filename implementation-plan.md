# Whale Wallet: Implementation Plan

Source: `plan-task-webapp.md`. App lives in `my-app/` (Laravel 13, PHP 8.3, plain scaffold: no Vue/Inertia yet).
Style: ponytail (lazy = fewest files, reuse before write, one check per money path).

## Roles

| Role | Model | Does |
|---|---|---|
| Guide | Opus | Writes each milestone brief, reviews diffs, owns money/security/AI-boundary decisions, signs off "done" |
| Builder | Sonnet | Generates code, migrations, tests, Vue pages from the brief |

Loop per milestone: Opus brief -> Sonnet implements -> run `php artisan test` + `npm run lint/test` -> Opus review -> commit.

## Ponytail deferrals (PROPOSED items skipped until needed; say so to override)

| Plan item | Lazy default | Add when |
|---|---|---|
| PostgreSQL | SQLite in dev/CI (Laravel default, already set up) | Before beta host; amounts are bigInteger so portable |
| Redis + Horizon | `database` queue driver | Queue throughput or failure dashboards matter (M5) |
| S3/MinIO | `local` private disk | Staging deploy |
| TanStack Table, vee-validate + Zod | Inertia `useForm` + plain table | A table needs sort/virtualise |
| vue-echarts | shadcn-vue/Unovis only | Confidence band required |
| Playwright | Pest feature tests + Vitest | M3 main-journey smoke, one flow only |
| OpenAPI | Pest API tests double as the contract; Bruno collection | Mobile agent asks |
| Reverb | Polling (already in plan) | Later |

Not deferred: user scoping, centavos, consent gate, confirm-before-save, FakeAiProvider, Gemini key server-only, forecast tests.

## Blockers to clear first

1. `whale-wallet-project-plan.md` and `whale-wallet-logo.svg` are referenced but missing. Needed for brand tokens (M0) only; proceed with placeholder tokens.
2. Vue starter kit: `my-app` is the plain scaffold. Re-create with the official Vue kit (`laravel new` with the Vue/Inertia kit) or add Inertia + Vue + shadcn-vue by hand. Recommend re-create (smaller diff than hand-wiring).
3. VERIFY Gemini client supports image + JSON schema, and current free-tier model name/limits.

## M0 Foundations
- Re-scaffold on the Vue starter kit inside `my-app/`; move to repo root afterwards if wanted (one `git mv`).
- Pest, ESLint, Prettier, Vitest (starter kit includes most).
- GitHub Actions: one workflow, php tests + npm lint/test.
- `.env.example`: add `GEMINI_API_KEY=` only.
- Placeholder brand tokens in Tailwind theme; dark mode via starter kit tokens.
- Done: fresh clone runs install, `migrate --seed`, login loads, CI green.

## M1 Budget core
- Migrations/models/factories/policies: income_sources, cards, pods, categories, transactions, recurring_items. Centavos `bigInteger`, `currency` default PHP, `user_id` on all.
- One global scope trait `BelongsToUser` reused by every model (single place for rule 6).
- `app/Actions/*` per mutation; web and `/api/v1` controllers share them.
- Onboarding wizard, cards/pods UI, expense log (search/filter), transfers (type `transfer`, excluded from spend).
- Quick entry: one regex parser (`Jollibee 250`) in an Action.
- Dashboard: safe-to-spend, income vs fixed vs discretionary, category chart, upcoming bills.
- Seeder: income 35,000; loan 9,000 (27th); rent 8,000; subs ~2,156 -> remaining 15,844.
- CSV export (stream). Markdown export deferred (CSV covers it).
- Check: Pest test asserting 15,844 and user A cannot read user B (one test per model via the shared trait).

## M2 Forecast, subscriptions, recommendations
- Subscription tracker = filtered view of `recurring_items`, no new table.
- `app/Services/Forecast/ForecastService` pure; table-driven Pest tests (payday boundary, 27th bills, month-end, zero history, card limits, pod contributions).
- Forecast chart (Unovis).
- `AiProvider` interface (justified: Gemini + Fake needed by plan), `GeminiProvider`, `FakeAiProvider`.
- Recommendation job sends aggregates only; validator rejects any number not in input; disclaimer shown.
- Done: forecast tests pass, fake provider renders tips, one manual real Gemini call works.

## M3 Receipts and Whale Chat
- Consent screen + `POST /consent/ai`; middleware blocks AI routes until `ai_consent_at` set.
- Receipt upload -> `ParseReceipt` job (downscale, JSON schema, validate, duplicate check).
- Confirm card; delete image after confirm (setting to keep).
- Chat: `chat_threads/messages`; intents `log_expense|question|chat`; `question` answered by fixed server functions, Gemini only words it.
- Rate limit per user; 429 -> clear message + manual form link.
- Fixtures: 5-10 receipts in `tests/Fixtures`, run against fake in CI, real model manually.
- Done: sample receipt -> correct draft, nothing saved before Save, duplicate flagged, "Jollibee 250" and "how much food this week?" work.

## M4 Mobile API
- `/api/v1` per section 5 using API Resources + feature tests; reuse M1-M3 Actions.
- Sanctum tokens with device name; revoke in web settings.
- `devices` + scheduled commands for bill/budget push (Expo).
- Bruno collection in `docs/`.
- Done: login, dashboard, log, receipt, chat pass against staging.

## M5 Beta hardening
- Data export, account deletion, delete chat history, secrets-to-client audit.
- Paid Gemini tier before real users.
- Log confirm-card corrections (one column on `receipts`: `corrections_json`).
- Error monitoring + queue failure alerts.

## Open questions (default if unanswered)
1. Product type -> portfolio-grade polish, no billing.
2. Hours/week -> unknown; milestones are sequential and independently shippable.
3. Payment apps -> GCash, Maya first in fixtures.
4. Pricing -> free.
5. Hosting -> Railway (MCP already connected).
