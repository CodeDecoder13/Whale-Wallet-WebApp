# Whale Wallet Mobile API

Contract for the mobile app. Everything here is live on production unless marked otherwise.

**Base URL:** `https://whale-wallet-webapp-production.up.railway.app/api/v1`

## Conventions

- JSON in, JSON out. Always send `Accept: application/json`.
- Auth: `Authorization: Bearer <token>` (Laravel Sanctum). The token comes from login or register.
- **Money is integer centavos** (₱250.00 = `25000`). Dates are `YYYY-MM-DD`; timestamps are ISO 8601.
- Everything is scoped to the signed-in user; another user's id answers 404.
- Wrapping: resources and lists are `{data: ...}` (cards, pods, categories, recurring-items, transactions, recommendations, `/me`, `/me/*`). Paginated lists add `links` and `meta`. These are **unwrapped**: `/dashboard`, `/forecast`, `/chat`, `/receipts`, receipt objects, chat message replies, `/transactions/quick`, `/income`, `/export`.

| Status | Meaning |
|---|---|
| 401 | Missing or invalid token. Go back to login. |
| 403 | Email not verified (most routes need a verified email), or login on a 2FA account (see below). |
| 404 | Not found, or not yours. |
| 409 | Conflict (already onboarded, income proof already handled). |
| 422 | Validation: `{message, errors: {field: [..]}}`. Also AI endpoints without consent: `{message, consent_url}`. |
| 429 | Throttled (login/register/password 6/min, AI 6–20/min). |
| 503 | AI provider unavailable: `{message}`. |

**2FA:** an account with two-factor on cannot log in on mobile yet. Login answers `403 {message}`; the message contains "2FA".

---

## 1. Auth & account

| Method | Path | Body | Returns |
|---|---|---|---|
| POST | `/auth/register` | `name, email, password, password_confirmation, device_name` | 201 `{token, token_type, user}`. Sends a verification email; most routes 403 until verified. |
| POST | `/auth/login` | `email, password, device_name` | `{token, token_type, user}`. Logging in again with the same `device_name` replaces that device's token. |
| POST | `/auth/logout` | – | 204 |
| GET | `/me` | – | `{data: user}` |
| PATCH | `/me` | `name, email` | `{data: user}` (changing email un-verifies it) |
| PATCH | `/me/avatar` | `avatar` = `data:image/jpeg;base64,...` (≤40 KB) or `null` to remove | `{data: user}` |
| PATCH | `/me/payday` | `pay_dates` = `[15,30]` (1–2 distinct days, 1–31) | `{data: user}` |
| PATCH | `/me/receipts` | `keep` = bool (keep receipt images) | `{data: user}` |
| PATCH | `/me/digest` | `digest_enabled` = bool, `digest_time` = `HH:MM` in Philippine time (default `09:00`) | `{data: user}` |
| PUT | `/me/password` | `current_password, password, password_confirmation` | 204 |
| DELETE | `/me` | `password` | 204 (deletes the account) |
| POST | `/onboarding` | `income, paid_this_month? (false = salary starts next month), pay_dates[], bills[{name,amount,day}], card{name,type,balance,color?}, pod?{name,target_amount}` | 201 (409 if already onboarded) |
| GET | `/export` | – | All the user's data as JSON |
| GET | `/export/transactions.csv` | – | CSV download |
| DELETE | `/chat` | – | 204 (wipes chat history) |

**User:** `{id, name, email, email_verified, ai_consent_at, pay_dates, avatar, keep_receipts, digest_enabled, digest_time}`. `avatar` is a data URI or null. Once a day at `digest_time` (Philippine time) the server refreshes the user's Whale tips (`GET /recommendations`) and, if `digest_enabled` and the email is verified, emails them. It needs `ai_consent_at`.

## 2. Dashboard & forecast

| Method | Path | Notes |
|---|---|---|
| GET | `/dashboard` | See shape below. |
| GET | `/forecast?skip=<recurring_item_id>` | Same `forecast` object plus `series`; `skip` projects as if that item were cancelled (`skipped` echoes it). |

**`/dashboard`:**
```
{
  safe_to_spend_today, left_this_month, next_payday, days_to_payday,
  net_worth: {assets, debts, total},
  forecast: {payday, days, lowest: {date, amount, balance}, at_payday, safe_per_day},
  cards: [Card], pods: [Pod],
  upcoming_bills: [RecurringItem],   // next 14 days
  recommendations: [Recommendation]  // up to 3
}
```
`lowest.amount` and `lowest.balance` are the same number. `/forecast` adds `series: [{date, balance, ...}]` (daily points; the dashboard omits it).

## 3. Cards, pods, categories, recurring items

Each has `GET /x`, `POST /x`, `GET /x/{id}`, `PATCH /x/{id}`, `DELETE /x/{id}`. PATCH takes the same body as POST (no partial updates).

| Path | Body | Resource |
|---|---|---|
| `/cards` | `name, type, balance, monthly_limit?, color?, account_name?, account_number?, qr?` (`qr` = `data:image/png;base64,...`) | `{id, name, type, balance, monthly_limit, currency, color, spent}`: `spent` is this month's spend, for the limit bar |
| `/pods` | `name, target_amount, saved_amount, monthly_amount, target_date?, card_id?` (the card the pod is funded from) | `{id, name, target_amount, saved_amount, monthly_amount, target_date, card_id, currency, progress}`: `progress` is 0–100 |
| `/categories` | `name` (unique per user) | `{id, name}` |
| `/recurring-items` | `name, amount, cadence, next_due_on, kind, card_id?, category_id?` (loans also `installments`) | `{id, name, amount, monthly_amount, currency, card_id, category_id, cadence, next_due_on, kind}` |

`kind` is `bill`, `subscription` or `debt` (a loan).

**Pay a bill / subscription / loan payment**

`POST /recurring-items/{id}/pay` with `{card_id, amount, paid_on}` returns 201 `{data: Transaction}`. It records an expense on the card and moves the item to its next due date (a loan loses one remaining payment; a paid-off loan answers 422).

**Add money to a pod**

`POST /pods/{id}/fund` adds money to a pod from a card: `{card_id, amount, paid_on, reference?, receipt_id?}` (`amount` in centavos, `paid_on` not in the future) returns 201 `{data: Transaction}` (a transfer with `to_pod_id` set and `reference`/`receipt_id` if given). The card needs enough balance (else 422 on `amount`). For proof of transfer, upload the image as a receipt first (`POST /receipts`), wait until it is `parsed`, then pass its `draft.reference`, `draft.occurred_at` (as `paid_on`) and `draft.amount`, plus the receipt `id` as `receipt_id`; the user always picks `card_id`. A receipt can be used once. A pod also has an optional `card_id`, the card it is normally funded from: default the "Transfer from" choice to it.

## 4. Transactions & transfers

| Method | Path | Body / query |
|---|---|---|
| GET | `/transactions` | `q, from, to, card_id, category_id, per_page (≤100), page`. Paginated `{data, links, meta{current_page, last_page, per_page, total}}` |
| POST | `/transactions` | `merchant, amount, card_id, category_id?, occurred_at, note?, source` (`manual` or `quick`) → 201 |
| GET | `/transactions/{id}` | – |
| PATCH | `/transactions/{id}` | same as POST (only expenses are editable) |
| DELETE | `/transactions/{id}` | 204 (reverses the card balance) |
| POST | `/transactions/quick` | `{text: "Jollibee 250 gcash"}` → `{merchant, amount, card_id}`. Nothing is saved; show it as a draft and confirm with `POST /transactions`. `card_id` may be null; 422 if it can't parse. |
| POST | `/transfers` | `from, to, amount`; `from`/`to` are `card:ID` or `pod:ID` |

**Transaction:** `{id, type, amount, currency, merchant, card_id, card_name, category_id, category_name, to_card_id, to_pod_id, from_pod_id, occurred_at, note, source, receipt_id, reference}`.
`amount` is **unsigned**: use `type` (`expense`, `income`, `transfer`) for direction. `card_name` and `category_name` are null when there is none.

## 5. Income (salary)

A salary needs a proof screenshot. Upload it as a receipt with `purpose=income` (or send it in chat after the salary prompt), wait until it is `parsed`, then save:

| Method | Path | Body |
|---|---|---|
| POST | `/income` | `receipt_id, amount, received_on, reference, name?` → 201 `{id, name, amount, month, reference, received_on}` (422 if the reference was already logged; 409 if the proof was already handled) |
| DELETE | `/income/{id}` | 204 |

## 6. Receipts (AI scan)

Needs AI consent (section 8).

| Method | Path | Body |
|---|---|---|
| GET | `/receipts` | Pending expense receipts (not yet confirmed), newest first, max 10 |
| POST | `/receipts` | multipart: `image`, `purpose` (`expense` default, or `income`) → 202 receipt |
| GET | `/receipts/{id}` | Receipt |
| POST | `/receipts/{id}/confirm` | `merchant, amount, card_id, category_id?, occurred_at, note?, reference?` → `{status: "confirmed"}`; creates the transaction |
| DELETE | `/receipts/{id}` | 204 |

**Receipt:** `{id, status, error, draft, claimed}`
- `status`: `queued` → `parsing` → `parsed` | `failed`, then `confirmed`. Poll `GET /receipts/{id}` until `parsed` or `failed` (parsing usually takes a few seconds).
- `error`: a user-safe sentence when `failed`.
- `draft` (when `parsed`): `{merchant, amount, currency, occurred_at, reference, confidence, category_id, category, card_id, duplicate_of}`. `amount` is centavos. Prefill the confirm form from it.
- `draft.duplicate_of`: list of existing transaction ids that look like the same expense (empty when none). Warn the user before confirming.
- `claimed`: the amount the user typed in chat before sending a salary proof (compare with `draft.amount`).

## 7. Whale Chat (AI)

| Method | Path | Body |
|---|---|---|
| GET | `/chat` | `{consented, messages: [{id, role, content, draft_json, receipt_id, income}]}`: the last 50, oldest first. `role` is `user` or `assistant`. |
| POST | `/chat/messages` | `text` (≤500) and/or multipart `image`; optional `thread_id` |
| POST | `/chat/drafts/{message_id}/confirm` | – → `{saved: true, transaction_id, message?}` (`message` is a ready sentence for `manage` drafts: show it as the toast) |

**Text reply:** `{id, reply, draft?, quick_replies?, extra?, thread_id}`. One message can hold several requests ("cancel Netflix and delete Rent"): the first answers at the top level and each further one comes in `extra` as `[{id, reply, draft?, quick_replies?}]`, each with its own confirm. Show them as separate assistant bubbles. `id` is the assistant message id: use it in `/chat/drafts/{id}/confirm`. The chat also understands "pay my Converge bill", loans ("I borrowed 5000, 3 payments") and salary messages.

**Draft** (`draft` / `draft_json`): `{kind?, merchant, amount, card_id, category_id, occurred_at, note?, missing[], saved?, recurring_id?}`.
- `missing` lists fields still needed; the draft can only be confirmed when it is empty. `quick_replies` suggests answers.
- `kind` is absent for an expense, or `loan` / `income` / `manage`.
- `dues` drafts answer "what's due?" and are never confirmed: `window` (`today`, `in the next 3 days` or `in the next 7 days`; overdue items count as today) and `items[]` `{id, name, kind, amount, logo, due_on, days}` (`days` is negative when overdue). Render them as a list of cards; `kind` is `bill`, `subscription` or `debt`. No draft means nothing is due in 7 days.
- `manage` drafts change bills, subscriptions and expenses and have `action` (`add_recurring`, `edit_recurring`, `cancel_subscription`, `renew_subscription`, `delete_recurring`, `edit_transaction`, `delete_transaction`), `title`, `lines[]` (what changes, ready to show), `label` (button text) and `destructive` (style the button as a warning). Nothing changes until `/confirm`. After confirming, `draft_json.done` holds the result sentence. `recurring_id` is set when the draft pays a bill.
- After confirming, `draft_json.saved` is set to the new id and `GET /chat` returns it: hide the confirm button for drafts where `saved` is set. (`income` drafts are not confirmed here; they go through section 5.)

**Image send** returns `{id, reply, receipt_id, income, thread_id}` and no draft.
- `income: false` → open `GET /receipts/{receipt_id}`; when `parsed`, confirm with `POST /receipts/{id}/confirm`.
- `income: true` → when `parsed`, show `draft` plus `claimed`, then save with `POST /income`.

Errors: 422 without consent (`consent_url`), 429 (quota or throttle), 503 (provider down), each with `{message}`.

## 8. Tips & AI consent

| Method | Path | Notes |
|---|---|---|
| POST | `/consent/ai` | Grants AI consent. Required before receipts, chat and tips. Returns `{ai_consent_at}` |
| DELETE | `/consent/ai` | Withdraws it (the "AI features" toggle). Chat, receipts and tips then answer 422 with `consent_url` |
| GET | `/recommendations` | `{data: [{id, text, created_at}]}`. The field is `text`. |
| POST | `/recommendations/generate` | Replaces the current tips (needs consent; 6/min) |
| POST | `/recommendations/{id}/dismiss` | – |

## 9. Push notifications

**Register the device:**

| Method | Path | Body |
|---|---|---|
| POST | `/devices` | `expo_push_token` (`ExponentPushToken[...]`), `platform` (`ios` or `android`) → `{data: {id, expo_push_token, platform, last_seen_at}}`. An upsert by token: 201 the first time, 200 after. |
| DELETE | `/devices/{id}` | 204 |
| DELETE | `/devices` | `{expo_push_token, platform}` → 204. Use this to turn push off after an app restart (you have the token but not the id). |

**What the server sends** (through the Expo Push API, to every registered token). Every push has `title`, `body` and `data.type`; route the tap on `data.type`:

| `data.type` | When | Example |
|---|---|---|
| `bill_due` | Daily 08:00, for bills due today, tomorrow or in 2 days (once per bill and due date) | `Bill due in 2 days` / `Converge: PHP 1,600.00` |
| `payday_near` | Daily 08:00, 2 days and 1 day before payday | `Payday in 2 days` / `Payday is 2026-10-30.` |
| `daily_safe_to_spend` | Daily 07:30 | `You can spend PHP 450.00 today` |
| `budget_warning` | Hourly check: a card at 80% of its monthly limit, and again when over (once per card per month) | `E-wallet is at 80% of its monthly limit` |

Times are the server's timezone. Suggested taps: `bill_due` and `payday_near` and `daily_safe_to_spend` → Home, `budget_warning` → Cards.

> **Status:** the sender code is live, but the scheduler service that triggers it must be running on Railway (see "Server setup"). Until it is, no push goes out.

## 10. Screen cheat sheet

| Screen / row | Calls |
|---|---|
| Home | `GET /dashboard` |
| Forecast | `GET /forecast` |
| Cards | `GET /cards`, `POST/PATCH/DELETE /cards` |
| Ledger | `GET /transactions` (+ filters) |
| Add expense | `POST /transactions/quick` → `POST /transactions`; or scan: `POST /receipts` → poll → `POST /receipts/{id}/confirm` |
| Upcoming bills / pay | `GET /dashboard` → `POST /recurring-items/{id}/pay` |
| Chat | `GET /chat`, `POST /chat/messages`, `POST /chat/drafts/{id}/confirm` |
| More: profile | `GET /me`, `PATCH /me`, `PATCH /me/avatar` |
| More: pay schedule | `PATCH /me/payday` |
| More: push toggle | `POST /devices` / `DELETE /devices` |
| More: AI features toggle | `POST` / `DELETE /consent/ai` |
| More: tips | `/recommendations*` |
| More: export, delete account | `GET /export`, `DELETE /me` |
| More: sign out | `POST /auth/logout` |
| Dark mode | device only, no API |

## 11. Typical flows

1. **First launch:** `POST /auth/register` → user verifies email → `POST /auth/login` → `POST /onboarding` → `GET /dashboard`.
2. **Quick expense:** `POST /transactions/quick` → show draft → `POST /transactions`.
3. **Pay a bill:** `GET /dashboard` (`upcoming_bills`) → `POST /recurring-items/{id}/pay`.
4. **Scan a receipt:** `POST /receipts` → poll `GET /receipts/{id}` → `POST /receipts/{id}/confirm` (or `DELETE` to discard).
5. **Log salary:** chat the amount, send the proof screenshot (or `POST /receipts` with `purpose=income`) → when `parsed`, `POST /income`.

---

## Server setup (for the backend owner; the app does not need this)

The pushes are Laravel scheduled commands (`push:bills`, `push:payday`, `push:daily`, `push:budgets`), so something must call `php artisan schedule:run` regularly. The repo has `railway.cron.toml` for it:

1. Railway project → **New → GitHub Repo** → `CodeDecoder13/Whale-Wallet-WebApp` (a second service, e.g. `whale-scheduler`).
2. Service **Settings**: Root Directory `/myapp`, Config-as-code path `/myapp/railway.cron.toml`.
3. **Variables:** copy the web service's variables, at least `APP_KEY`, `APP_ENV`, `DB_*`, `CACHE_STORE`, `QUEUE_CONNECTION`, `APP_URL`. `CACHE_STORE` must survive between runs (database or redis, not `array`): it holds the "already sent" markers.
4. Deploy. It runs every 5 minutes (Railway's minimum) and exits; its logs show `Sent N ... alerts.`. Set `APP_TIMEZONE` to change the send times.

Test without waiting: run `php artisan push:daily` in that service (needs a registered device).
