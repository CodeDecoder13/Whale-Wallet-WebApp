# Whale Wallet: partial, missing and risk plan

Source: Fable read-only audit (2026-10-19). The agent did not run the tests, so confirm each item in the code before fixing it. Paths are relative to `myapp/`.
Order: risks first (they can hurt users), then missing, then partial. Each task is small; do one commit per phase. Run `php artisan test --compact` after every phase.

## Phase 0: Unblock production
- [ ] Clear the Custom Start Command (`php artisan schedule:run`) on the `Whale-Wallet-WebApp` web service (Settings → Deploy), then check `GET /up`.
- [ ] Confirm the `whale-scheduler` service runs at 07:30 and 08:00, and that a push arrives once a device is registered.

## Phase 1: High-severity risks
1. **Device token shared across accounts**
   - Files: `app/Http/Controllers/Api/DeviceController.php`, `app/Http/Controllers/Api/AuthController.php`, new migration.
   - [ ] Make `expo_push_token` unique on its own, not per user.
   - [ ] On register, reassign the row to the current user (query without the user scope).
   - [ ] Delete the current user's devices on logout and on account delete.
   - [ ] Test: user B registers user A's token; only B receives alerts.
2. **Deleting a card destroys its transactions**
   - Files: migration `2026_01_01_000001`, `CardController` (web), `Api/ResourceController.php`.
   - [ ] Add a new migration that changes `transactions.card_id` to `restrictOnDelete`.
   - [ ] Return a clear 422 ("Move or delete its transactions first") from web and API.
   - [ ] Web: a confirm dialog that states the transaction count.
   - [ ] Test: card with transactions cannot be deleted; empty card can.

## Phase 2: Medium risks
3. **Sanctum tokens never expire**
   - Files: `config/sanctum.php`, `routes/api.php`, `app/Http/Controllers/Api/AuthController.php`.
   - [ ] Set `expiration` (e.g. 60 × 24 × 30 minutes) and document it in `api.md` (mobile handles 401 → login).
   - [ ] Add a second login throttle keyed by email, not only by IP.
4. **Receipt files**
   - Files: `config/filesystems.php`, `routes/console.php`, `Settings/ProfileController.php`, `Api/AuthController.php`.
   - [ ] Move the receipts disk to S3 or a Railway bucket (the web and worker services must share it).
   - [ ] Delete the user's receipt files on account delete (web and API).
   - [ ] Add a scheduled prune of unconfirmed or failed receipts older than 7 days.
5. **Missing indexes**
   - [ ] New migration: `transactions(user_id, occurred_at)`, `transactions(card_id, type, occurred_at)`, `recurring_items(user_id, next_due_on)`.
6. **Validation gaps**
   - Files: `Requests/SubscriptionRequest.php`, `TransactionRequest.php`, `CardRequest.php`.
   - [ ] Web `SubscriptionRequest`: check `card_id` and `category_id` belong to the user (copy from the API `RecurringItemRequest`).
   - [ ] Cap `occurred_at` (not later than today + 1 day) and set sane min/max for card `balance` and pod `saved_amount`.
   - [ ] `PayRecurring`: decide whether the amount must match the item, and say so in `api.md`.

## Phase 3: Missing features
7. **API endpoints the web already has** (`routes/api.php`, `api.md`)
   - [ ] Resend verification email (unverified users get 403 on most routes today).
   - [ ] Forgot and reset password.
   - [ ] Token list and revoke.
   - [ ] Markdown export.
8. **Mobile 2FA login**: replace the 403 in `Api/AuthController.php` with a challenge step (`POST auth/2fa` with a code or recovery code).
9. **Per-category budgets**: new `budgets` table (user, category, monthly limit), `BudgetSummary` includes them, endpoints and a web UI. Needs a design decision first (see Open questions).
10. **Pod-to-pod transfer**: lift the block in `TransferRequest` and cover it in `TransferBetween` tests.
11. **Receipt prune job**: done under task 4.

## Phase 4: Partial items
12. **Loan terms**: `SubscriptionRequest` only allows 1, 2, 3, 6 or 12 installments. Widen to 1–60, or keep as is and say so in `api.md`.
13. **Push de-duplication** is cache-only, so a cache flush repeats alerts. Store the marker in the DB (e.g. `push_logs`) if that matters.
14. **PushAlerts performance**: batch the forecast per user and chunk the device query when users grow.

## Phase 5: Tests to add
- [ ] `PushAlerts` markers and payload (own test file).
- [ ] `DeleteTransaction` reverses a transfer on both balances.
- [ ] Card-delete guard (Phase 1).
- [ ] CSV formula-injection guard (`safeCell`).
- [ ] Forecast edge cases: the 31st clamped to a short month, loan that ends, yearly cadence.
- [ ] `TransferBetween` with concurrent requests (lock check).

## Phase 6: Docs
- [ ] Update `api.md` for every API change above (devices, 2FA, token expiry, new endpoints, card-delete 422).
- [ ] Remove `railway.cron.toml` if the scheduler is configured in the dashboard.

## Open questions
- Token lifetime: 30 days, or longer for mobile?
- Budgets: per category only, or per category per card?
- Card delete: block it, or archive (soft delete) the card?
