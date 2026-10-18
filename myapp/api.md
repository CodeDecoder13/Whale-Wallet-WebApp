# Whale Wallet Mobile API

Base URL: `{APP_URL}/api/v1` (production: your Railway domain + `/api/v1`)

- JSON in, JSON out. Send `Accept: application/json`.
- Auth: `Authorization: Bearer <token>` (Sanctum). Get the token from login or register.
- **Money is integer centavos** (₱250.00 = `25000`).
- Dates: `YYYY-MM-DD`. Validation errors: `422 { message, errors: { field: [..] } }`.
- Most routes need a **verified email** (403 otherwise). Everything is scoped to the signed-in user (another user's id gives 404).
- Accounts with 2FA on cannot log in on mobile yet.
- Throttles: login/register/password 6/min, AI endpoints 6–20/min (429).

## Auth & account

| Method | Link | Body | Returns |
|---|---|---|---|
| POST | `/auth/register` | `name, email, password, password_confirmation, device_name` | 201 `{token, token_type, user}` (sends verification email) |
| POST | `/auth/login` | `email, password, device_name` | `{token, token_type, user}` (same device name replaces its old token) |
| POST | `/auth/logout` | – | 204 |
| GET | `/me` | – | `{data: user}` = `id, name, email, email_verified, ai_consent_at, pay_dates, avatar, keep_receipts` |
| PATCH | `/me` | `name, email` | user |
| PATCH | `/me/avatar` | `avatar` = `data:image/jpeg;base64,...` (≤40KB) or `null` | user |
| PATCH | `/me/payday` | `pay_dates` = `[15,30]` (1–2 days, 1–31) | user |
| PATCH | `/me/receipts` | `keep` = bool (keep receipt images) | user |
| PUT | `/me/password` | `current_password, password, password_confirmation` | 204 |
| DELETE | `/me` | `password` | 204 (deletes the account) |
| POST | `/onboarding` | `income, pay_dates[], bills[{name,amount,day}], card{name,type,balance,color?}, pod?{name,target_amount}` | 201 (409 if already onboarded) |
| GET | `/export` | – | all your data as JSON |
| GET | `/export/transactions.csv` | – | CSV download |
| DELETE | `/chat` | – | 204 (wipes chat history) |

## Dashboard & forecast

| Method | Link | Notes |
|---|---|---|
| GET | `/dashboard` | `safe_to_spend_today, forecast, net_worth{assets,debts,total}, cards, pods, upcoming_bills (14 days), recommendations` |
| GET | `/forecast?skip=<recurring_item_id>` | Balance projection; `skip` simulates cancelling that item |

## Cards (wallets, banks, credit)

`GET /cards` · `POST /cards` · `GET /cards/{id}` · `PATCH /cards/{id}` · `DELETE /cards/{id}`

Body: `name, type, balance, monthly_limit?, color?, account_name?, account_number?, qr?` (`qr` = `data:image/png;base64,...`).

## Pods (savings goals)

`GET /pods` · `POST /pods` · `GET /pods/{id}` · `PATCH /pods/{id}` · `DELETE /pods/{id}`

Body: `name, target_amount, saved_amount, monthly_amount, target_date?`.

## Categories

`GET /categories` · `POST /categories` · `GET /categories/{id}` · `PATCH /categories/{id}` · `DELETE /categories/{id}`

Body: `name` (unique per user).

## Bills, subscriptions, loans (recurring items)

`GET /recurring-items` · `POST /recurring-items` · `GET /recurring-items/{id}` · `PATCH /recurring-items/{id}` · `DELETE /recurring-items/{id}`

Body: `name, amount, cadence, next_due_on, kind` (`bill|subscription|debt|...`), `card_id?, category_id?` (loans: `installments`).

| Method | Link | Body | Returns |
|---|---|---|---|
| POST | `/recurring-items/{id}/pay` | `card_id, amount, paid_on` | 201 transaction; records the expense and moves the item to its next due date (a loan loses one payment) |

## Transactions

| Method | Link | Body / query |
|---|---|---|
| GET | `/transactions` | `q, from, to, card_id, category_id, per_page (≤100), page` (paginated) |
| POST | `/transactions` | `merchant, amount, card_id, category_id?, occurred_at, note?, source` (`manual|quick`) |
| GET | `/transactions/{id}` | – |
| PATCH | `/transactions/{id}` | same as POST |
| DELETE | `/transactions/{id}` | 204 (reverses the card balance) |
| POST | `/transactions/quick` | `text` = `"Jollibee 250 gcash"` → `{merchant, amount, card_id}` draft, nothing saved; confirm with `POST /transactions` |
| POST | `/transfers` | `from, to, amount` where from/to = `card:ID` or `pod:ID` |

## Income (salary received)

| Method | Link | Body |
|---|---|---|
| POST | `/income` | `receipt_id` (a parsed receipt with `purpose=income`), `amount, received_on, reference, name?` → 201 |
| DELETE | `/income/{id}` | 204 |

## Receipts (AI scan)

Needs AI consent. Upload returns 202; poll `GET /receipts/{id}` until parsed.

| Method | Link | Body |
|---|---|---|
| GET | `/receipts` | pending expense receipts |
| POST | `/receipts` | multipart: `image`, `purpose` (`expense|income`) → 202 |
| GET | `/receipts/{id}` | status + parsed fields |
| POST | `/receipts/{id}/confirm` | transaction fields (`merchant, amount, card_id, category_id?, occurred_at, note?, reference?`) |
| DELETE | `/receipts/{id}` | 204 |

## Whale Chat (AI)

| Method | Link | Body |
|---|---|---|
| GET | `/chat` | `{consented, messages[]}` last 50 messages |
| POST | `/chat/messages` | `text` (≤500) and/or multipart `image` → `{reply, draft?, quick_replies?, thread_id}`. Also handles "pay my Converge bill" and loan/income messages |
| POST | `/chat/drafts/{message_id}/confirm` | confirms a draft → `{saved, transaction_id}` |

## Tips (AI recommendations)

| Method | Link | Notes |
|---|---|---|
| POST | `/consent/ai` | grant AI consent (required before receipts, chat, tips) |
| DELETE | `/consent/ai` | withdraw AI consent (chat, receipts, tips then answer 422 with `consent_url`) |
| GET | `/recommendations` | list |
| POST | `/recommendations/generate` | generate new tips (needs consent) |
| POST | `/recommendations/{id}/dismiss` | dismiss |

## Push notifications

| Method | Link | Body |
|---|---|---|
| POST | `/devices` | `expo_push_token` (`ExponentPushToken[...]`), `platform` (`ios|android`) |
| DELETE | `/devices/{id}` | 204 |
| DELETE | `/devices` | `expo_push_token, platform` → 204 (unregister by token, for after an app restart) |

## Typical flows

1. **First launch:** `POST /auth/register` → verify email → `POST /auth/login` → `POST /onboarding` → `GET /dashboard`.
2. **Add expense fast:** `POST /transactions/quick` → show draft → `POST /transactions`.
3. **Pay a bill:** `GET /dashboard` (`upcoming_bills`) → `POST /recurring-items/{id}/pay`.
4. **Scan receipt:** `POST /receipts` → poll `GET /receipts/{id}` → `POST /receipts/{id}/confirm`.
5. **Log salary:** chat or `POST /receipts` with `purpose=income` → `POST /income`.

## Response shapes (verified against the code)

- **Wrapping:** single resources and lists are `{data: ...}` (cards, pods, categories, recurring-items, me, recommendations, transactions). `/dashboard`, `/forecast`, `/chat`, `/receipts` are unwrapped.
- **GET /dashboard:** `safe_to_spend_today, left_this_month, next_payday (date), days_to_payday, net_worth{assets,debts,total}, forecast{payday, days, lowest{date, amount, balance}, at_payday, safe_per_day}, cards[], pods[], upcoming_bills[], recommendations[]`. `lowest.amount` and `lowest.balance` are the same integer centavos.
- **GET /forecast:** the same `forecast` object plus `series[{date, balance, ...}]` (daily points) and `skipped`.
- **Card:** `{id, name, type, balance, monthly_limit, currency, color, spent}` — `spent` is this month's spend (centavos) for the limit bar.
- **Pod:** `{id, name, target_amount, saved_amount, monthly_amount, target_date, currency, progress}` — `progress` is 0–100.
- **Recommendation:** `{id, text, created_at}` (the field is `text`, not `body`).
- **Recurring item:** `{id, name, amount, monthly_amount, currency, card_id, category_id, cadence, next_due_on, kind}`.
- **Transaction:** `{id, type, amount, currency, merchant, card_id, category_id, to_card_id, to_pod_id, from_pod_id, occurred_at, note, source, receipt_id, reference}`. `amount` is **unsigned**; use `type` (`expense|income|transfer`) for direction. `card_name` and `category_name` are included (null if none). Pagination is the Laravel paginator: `{data, links, meta{current_page, last_page, per_page, total}}`.
- **POST /chat/messages:** `{id, reply, draft?, quick_replies?, thread_id}` — `id` is the assistant message id to use in `/chat/drafts/{id}/confirm`. Image sends return `{id, reply, receipt_id, income, thread_id}`.
- **GET /chat:** `{consented, messages[{id, role, content, draft_json, receipt_id, income}]}`.
- **POST /devices:** upsert by token. 201 first time, 200 after; body `{data:{id, ...}}`.
- **Login with 2FA on:** `403 {message}` containing "2FA".
- **Push payloads:** see "Push notifications (server)" below.

## Profile / More screen

| Row | Calls |
|---|---|
| Name, email, avatar | `GET /me`; edit with `PATCH /me`, `PATCH /me/avatar` |
| Ledger | `GET /transactions` |
| Forecast | `GET /forecast` |
| Push notifications on | `POST /devices` |
| Push notifications off | `DELETE /devices` with `{expo_push_token, platform}` (works after a restart), or `DELETE /devices/{id}` |
| Tips | `GET /recommendations`, `POST /recommendations/generate`, `POST /recommendations/{id}/dismiss` |
| Pay schedule | `GET /me` (`pay_dates`); edit with `PATCH /me/payday` `{pay_dates:[15,30]}` |
| Dark mode | device only, no API |
| AI features on / off | `POST /consent/ai` / `DELETE /consent/ai` (state is `ai_consent_at` in `/me`) |
| Manage on web: export | `GET /export` (JSON), `GET /export/transactions.csv` |
| Manage on web: delete account | `DELETE /me` `{password}` |
| Clear chat history | `DELETE /chat` |
| Sign out | `POST /auth/logout` |

## Push notifications (server)

Sent through the Expo Push API to every registered token. Each push has `title`, `body` and `data.type`:

| `data.type` | When | Example |
|---|---|---|
| `bill_due` | 08:00 daily, for bills due today, tomorrow or in 2 days (once per bill and due date) | `Bill due in 2 days` / `Converge: PHP 1,600.00` |
| `payday_near` | 08:00 daily, 2 days and 1 day before payday | `Payday in 2 days` |
| `daily_safe_to_spend` | 07:30 daily | `You can spend PHP 450.00 today` |
| `budget_warning` | hourly check; a card at 80% of its monthly limit, and again when over (once per card and month) | `E-wallet is at 80% of its monthly limit` |

Times are the server's timezone. The server needs `php artisan schedule:work` (or a cron running `schedule:run` every minute) for these to go out.

## Chat, receipts and drafts

- **Draft** (chat `draft` / `draft_json`): `{kind?, merchant, amount, card_id, category_id, occurred_at, note?, missing[], saved?}`. `missing` lists fields still needed; a draft is only confirmable when it is empty. `kind` is absent for an expense, or `loan` / `income`. A bill payment draft also has `recurring_id`.
- **Saved drafts:** after `POST /chat/drafts/{id}/confirm`, the message's `draft_json.saved` is set to the new id, and `GET /chat` returns it, so hide drafts where `saved` is set.
- **POST /transactions/quick** returns `{merchant, amount, card_id}` (`card_id` may be null).
- **Receipt** (`GET /receipts/{id}`, items of `GET /receipts`): `{id, status, error, draft, claimed}`. `status`: `queued` / `parsed` / `failed` / `confirmed` (poll until `parsed` or `failed`; `error` is a user-safe sentence). `draft` is the parsed fields: `merchant, amount (centavos), occurred_at, reference, ...`. `claimed` is the amount the user said in chat (salary proofs).
- **Chat image send** returns `{id, reply, receipt_id, income, thread_id}`. If `income` is false, open the receipt (`GET /receipts/{receipt_id}`) and confirm with `POST /receipts/{id}/confirm`; if true, once it is `parsed` show its `draft` and save with `POST /income` (`receipt_id, amount, received_on, reference`).
