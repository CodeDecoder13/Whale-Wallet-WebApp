# Admin plan: admin.whale-wallet.cloud

## Progress tracker

Updated after each phase is built, tested and pushed. `[x]` done, `[ ]` not yet.

| # | Phase | Status |
| --- | --- | --- |
| 0 | Foundation | **DONE** |
| 1 | Gate (PIN + emailed code, 2 hours) | **DONE** |
| 2 | Overview + Users | **DONE** |
| 3 | Loans, subs, activity feed | **DONE** |
| 4 | Transactions, receipts, conversations | **DONE** |
| 4b | Admin on its own host, `admin.whale-wallet.cloud` | **DONE** (needs the DNS record and `ADMIN_DOMAIN`, see below) |
| 5 | Support chat | **DONE** |
| 6 | Actions + audit + privacy text | next |
| 7 | Login alerts | todo |
| 8 | Admin management | todo |

### Phase 0: Foundation
- [x] Migration: `users.admin_role`, `users.suspended_at`, tables `activity_logs`, `admin_audit`
- [x] `php artisan admin:make {email} --name= --role=owner|support` (hidden PIN prompt or `ADMIN_PIN`, never in git)
- [x] `admin` middleware: plain 404 for non-admins and suspended admins, `X-Robots-Tag: noindex`
- [x] `routes/admin.php` under `/admin`, `robots.txt` disallows `/admin`
- [x] `AdminLayout` with tabs, placeholder Overview page

### Phase 1: Gate
- [x] `/admin/gate`: email a code, then PIN + code to open; works for 2 hours (`RequireAdminGate`)
- [x] Lockout: 5 wrong tries for 15 minutes, code works once, expires in 10 minutes, throttled
- [x] Admin code email (same blue design, wording "open the admin area")
- [x] Tests: `tests/Feature/AdminGateTest.php` (404, redirect to gate, both PIN and code, lockout, one-time code, 2-hour expiry, suspended admin, `admin:make`)

### Phase 2: Overview + Users
- [x] Overview: users (total, new today/7/30 days, verified %, PIN, 2FA), active 24 h / 7 days, money data counts, receipts and AI (7 days), health panel
- [x] Users list: search by name or email, filters (verified, not verified, inactive 30 days, suspended), last active, counts of cards / transactions / loans / subs, paged at 25
- [x] User page: account flags, counts, sessions, loans and subscriptions, recent activity; each open is written to `admin_audit`
- [x] Tests: `tests/Feature/AdminPagesTest.php`

### Phase 3: Loans, subs, activity feed
- [x] Loans & subs page: every user's loans (total, remaining, left, next due, late), subscriptions and bills; totals (owed, late payments, subscription cost per month); most used subscriptions
- [x] Activity feed: `activity_logs` written for login, registered, verified, password reset, failed login, receipt scanned, loan / subscription / bill added, account reset, digest sent or failed
- [x] Feed page with event filter; pruned after 90 days by a daily scheduled job
- [x] Health panel on the Overview (digests today, digest failures, failed jobs, contact messages, stuck receipts)

### Phase 4: Transactions, receipts, conversations
- [x] Transactions: every user's expenses, income and transfers; filters (search, user, type, source, category, dates, amount range); totals per type; CSV export (formula-safe, capped at 20,000 rows, audited)
- [x] Receipts: list with status / stuck filters; detail with the image beside what the AI read and the transaction it became; the image only through the gated, audited `/admin/receipts/{id}/image` route
- [x] Conversations: all Whale Chat threads, search by message text, read-only thread view with the AI's drafts; threads a user deleted stay hidden
- [x] Every view of these pages and every export or image open is written to `admin_audit`; all of it is owner-only (a support admin gets 404)
- [x] Tests: `tests/Feature/AdminDataTest.php`

### Phase 5: Support chat
- [x] Tables `support_threads` (one per user, open or closed, unread counts) and `support_messages` (text, "seen" time)
- [x] User side: **Support** in the sidebar with an unread badge; messenger page with bubbles, "Seen", Enter to send, 1,000 characters, 20 messages a minute; refreshes every 4 seconds; a closed chat reopens when the user writes
- [x] Admin side: inbox with Open / Unread / Closed filters, list on the left and the chat on the right, reply, Close / Reopen, link to the user's page (owners); refreshes every 5 seconds; unread badge on the Support tab
- [x] Emails (blue design, legal footer): the admins get one email per chat per 10 minutes for new user messages; the user gets one email per 10 minutes for a reply, only when they are not in the app
- [x] Roles: owners and support admins both open the inbox; a support admin sees only Support (no users, transactions, receipts, conversations, loans) and lands there after the gate
- [x] Audit: opening a chat and every reply or close is logged; the user only ever sees their own thread
- [x] Tests: `tests/Feature/SupportChatTest.php`

### Creating the first admin (no default account in the code)
There is **no built-in admin and no default PIN or password in the code**, because the code is in git. An admin exists only after someone runs the seeder on purpose, with the values taken from environment variables (Railway variables), never from a file:

```
ADMIN_EMAIL=you@example.com ADMIN_NAME="Your Name" ADMIN_PIN=<6 digits> ADMIN_PASSWORD=<password, optional> php artisan db:seed --class=AdminSeeder
```
- `ADMIN_PASSWORD` is optional. If set it must pass the app's password rules (in production: 12+ characters, upper and lower case, number, symbol, not breached). Without it a new account gets a random password and you use "Forgot password".

- `database/seeders/AdminSeeder.php` is **not** called by `DatabaseSeeder`, so demo, test and local seeding never creates an admin.
- It does nothing unless `ADMIN_EMAIL` and `ADMIN_PIN` are set, and it rejects easy PINs (000000, 123456, 654321, 121212...).
- The PIN is stored hashed and never written to git. A new account gets a random password; use **Forgot password** to set yours.
- Running it again for the same email only refreshes the PIN and keeps the owner role.
- On Railway: add the three variables to the web service, run the seeder once from its shell, then **delete the three variables**.
- `php artisan admin:make email --name=` does the same with a hidden PIN prompt (no variables needed).

### Phases 4 to 8
Each phase below gets its own checklist here when it starts.

---

## Goal

One private place to run Whale Wallet as it grows: who signed up, who is active, what loans and subscriptions exist, what is failing, and a few safe actions on accounts.

## What the app has today (scanned)

| Area | Today | Matters for admin |
| --- | --- | --- |
| Users | `users`: name, email, password, `pin` (hashed), 2FA columns, digest columns, avatar, `ai_consent_at`. No role or admin flag. | Need an `admin_role` column (owner or support). |
| Data scope | `BelongsToUser` global scope limits every model to the signed-in user (`auth()->id()`). | An admin who is signed in would only see their own rows. Admin queries must use `withoutGlobalScope('user')`, in admin code only. |
| Last seen | `sessions` table (`user_id`, `ip_address`, `user_agent`, `last_activity`), session driver is `database`. | "Last active" and "force sign out" come free. |
| Money data | `cards`, `transactions`, `pods`, `recurring_items` (bills, subscriptions, loans as `kind = debt`, with `total_amount`, `installments`), `income_sources`. | Loans and subs monitoring reads `recurring_items`. |
| Other | `receipts` (status, failures), `chat_threads`, `recommendations`, `devices`, `contact_messages`, `jobs` table. | Health and support views. |
| Activity log | None. | New small table needed. |
| Auth | Fortify, email OTP at sign-up (`otp:{id}` cache), PIN login with lockout, reset-account OTP (`reset-otp:{id}`). | Reuse the same OTP pattern for the admin gate. |
| Routes | `routes/web.php`, `settings.php`. Middleware: `auth`, `verified`, `ai.consent`. | Add an `/admin` group with its own middleware. |
| Mail | Mailtrap SMTP on the web service. The scheduler service still needs the `MAIL_*` variables. | The admin OTP email works from the web service. |

## Access: its own host, `admin.whale-wallet.cloud`

Decision (changed from a `/admin` path): the admin runs on a separate host name, which is safer.

- **Same app, same database, one extra domain.** Set `ADMIN_DOMAIN=admin.whale-wallet.cloud` and the admin routes answer on that host only. On the main site `/admin` becomes a plain 404.
- **Separate sign-in.** The session cookie is host-only (`SESSION_DOMAIN` is empty), so being signed in on whale-wallet.cloud does not sign you in on the admin host. An admin signs in there with email and password (or PIN), then passes the PIN + emailed-code gate. A stolen or leaked normal-site session can never open the admin.
- **Only the admin is served there.** The admin host answers the admin pages and the sign-in pages (`/login`, `/logout`, password reset, two-factor). Anything else (`/dashboard`, `/register`, settings...) redirects back to the admin home, so it is not a second copy of the user app and nobody can sign up there.
- **Not indexed.** Every response on the admin host, including the sign-in page, carries `X-Robots-Tag: noindex, nofollow`.
- **Local and tests** have no `ADMIN_DOMAIN`, so the admin stays at `/admin` on localhost.
- A non-admin, or anyone not signed in, gets a plain **404** (not 403), so the page does not confirm it exists.

### Phase 4b: Admin host
- [x] `ADMIN_DOMAIN` config; admin routes registered on that host (loaded before the main routes, because `/` and `/transactions` exist on both hosts)
- [x] `AdminHostOnly` middleware: admin host serves admin and sign-in pages only, noindex header on every response
- [x] Front end builds every admin link from the current base (`adminUrl`), so one build works at `/admin` locally and at `/` on the admin host
- [x] Own sign-in page on the admin host (`admin/Login`): no sign-up, no marketing, no "remember me"; only accounts with an admin role can sign in there (anyone else fails like a wrong password); the public login page stays on the main site
- [x] Tests: `tests/Feature/AdminDomainTest.php` (host and path, main host has no admin, guest sent to login on the admin host, user app not served there, gate and 404 for normal users)

### Turning it on (you do this once; I cannot touch DNS)
1. **DNS** (where whale-wallet.cloud is managed): add a record for `admin` as Railway tells you in step 2, normally a CNAME `admin` pointing to the value Railway shows (plus a TXT verification record if it asks).
2. **Railway** > Whale-Wallet-WebApp > Settings > Networking > **Custom Domain** > `admin.whale-wallet.cloud`. Wait until the certificate is issued (a few minutes).
3. **Railway variable** on the web service: `ADMIN_DOMAIN=admin.whale-wallet.cloud`. It redeploys.
4. Open `https://admin.whale-wallet.cloud`, sign in, pass the gate.
5. The scheduler service does not need `ADMIN_DOMAIN` (it serves no pages).

### Sign-in: password, then PIN, then emailed code

1. Sign in on the normal login page (email and password, or the existing PIN login).
2. Opening `/admin` as an admin shows the **admin gate**: enter the 6-digit PIN, then the 6-digit code emailed to the admin address.
3. A correct pair stores `admin_verified_at` in the session. The gate asks again after **2 hours** of the session or on a new sign-in.
4. Limits: 5 wrong tries (shared counter for PIN and code) lock the gate for 15 minutes. Code works once, expires in 10 minutes. `throttle:6,1` on both routes.
5. Same code path as the account-reset OTP: `Cache` key `admin-otp:{id}`, hashed, same mail view with different wording.

### The default admin

| Field | Value |
| --- | --- |
| Email | boyparamio@gmail.com |
| Name | Rhuzzel Paramio |
| PIN | the 6 digits you gave me. **Not written in this file or in git.** |

- Created by `php artisan admin:make boyparamio@gmail.com --name="Rhuzzel Paramio"`. It asks for the PIN with a hidden prompt (or reads `ADMIN_PIN` from the environment once), stores it hashed in `users.pin`, and sets `admin_role = 'owner'`.
- `admin_role` is **not** fillable, so no form or API can set it. Only the command and the owner-only Admin management page (section 11) can.
- Your PIN was pasted in chat, so after the first sign-in I recommend changing it in Settings > Security. Anyone who read the chat knows it.

## What the admin can see

### 1. Overview
- Users: total, new today / 7 days / 30 days, verified %, with PIN, with 2FA.
- Active: users seen in the last 24 hours and 7 days (from `sessions.last_activity`).
- Money data: total cards, transactions this month, active loans, active subscriptions.
- AI and receipts: receipts scanned this week, failed or stuck (queued over 2 minutes), AI tips generated.
- Mail: digests sent today, verification codes sent, failed sends.

### 2. Users
- List: name, email, joined, verified, last active, counts of cards / transactions / loans / subs, status. Search by name or email, filter by verified, inactive 30 days, suspended.
- Detail: profile metadata, devices and active sessions, security (PIN set, 2FA on), digest settings, recent activity, their loans and subscriptions.

### 3. Loans and subscriptions
- All loans: owner, name, total, remaining, monthly payment, payments left, next due, overdue flag.
- All subscriptions and bills: owner, name, amount, cadence, next due, cancelled or hidden.
- Totals: money owed across all loans, monthly subscription volume, most common subscriptions (names only, to see what people use).

### 4. Activity feed
- A new `activity_logs` table: `user_id`, `event`, `meta` (json), `ip`, `created_at`.
- Events: registered, verified, logged in, failed PIN, password reset, reset-account, receipt scanned, digest sent or failed, loan added, subscription added.
- Written from Laravel's own events (`Registered`, `Verified`, `Login`, `Failed`, `PasswordReset`) plus a one-line `Activity::log()` in the few actions above. Keep 90 days, prune by a scheduled command.

### 5. System health
- Last `digest:send` run, failed jobs, stuck receipts, contact messages waiting.

### 6. All transactions
- Every type: expenses, income and transfers, across all users, newest first, paged at 25.
- Columns: date, user, merchant, category, card, source (manual, chat, receipt, import), amount, reference.
- Filters: user, type, category, source, date range, amount range. Search by merchant or reference.
- Totals row for the current filter. CSV export of the filtered list (logged in the audit log).
- Also shown inside each user's detail page, filtered to that user.

### 7. Receipts
- Every scanned receipt: user, uploaded time, status (queued, parsing, done, failed, confirmed), parsed merchant and amount, and the original image.
- Filters: status, user, failed only, stuck (queued over 2 minutes). Open one to see the image beside what the AI read, and the transaction it became.
- Images come from the same storage disk (`Storage::disk()`) through an admin-only route that checks the gate, never a public URL.

### 8. Conversations (Whale Chat, read-only)
- All AI chat threads: user, title, message count, last message time.
- Open a thread to read the whole conversation, including drafts the AI made and which ones were confirmed.
- Read-only. Admin cannot edit, delete or write into these; they are the user's chat with the AI.
- Search messages by text (for example to find what users ask the AI that it cannot answer).

### 9. Support chat (messenger between user and admin)
A real two-way chat, separate from the AI Whale Chat, so users can ask a person for help.

**User side**
- A **Support** item in the app (sidebar and the floating whale button menu). It opens one conversation with the admin, shaped like a messenger: bubbles, time, "seen".
- Text only in v1, 1,000 characters per message. Sending is throttled (20 per minute).
- Unread badge on the Support item when the admin replied.

**Admin side**
- `/admin/support`: an inbox. Left list of conversations (user, last message, unread count, open or closed), right the chat. Newest activity on top, filter: open, unread, closed.
- Reply box, **Close** / **Reopen** the conversation, and a link to that user's detail page so the admin sees their account while answering.
- Badge with the total unread count in the admin tabs.

**How it updates:** the app already refreshes pages by polling (`usePoll`). The chat polls every 5 seconds while open and every 30 seconds for the badge. No websocket server is needed for v1. If traffic grows, swap the polling for Laravel Reverb without changing the tables.

**Tables**
| Table | Columns |
| --- | --- |
| `support_threads` | `id`, `user_id` (unique, one thread per user), `status` (open or closed), `last_message_at`, `user_unread`, `admin_unread` |
| `support_messages` | `id`, `thread_id`, `sender_id`, `from_admin` (bool), `body`, `read_at`, `created_at` |

**Notifications:** a new user message emails the admin (at most one email per thread per 10 minutes, so a burst is one email). A new admin reply shows the badge in the app and, if the user is not active in the last 10 minutes, one email. Both use the existing blue mail design.

**Safety:** messages are plain text, escaped on display. A closed thread reopens when the user writes again. The user only ever sees their own thread; the same `BelongsToUser` scope applies on the user side.

### 10. Login alert email to the admin
Every time a user signs in, the admin gets an email: **"Rhuzz Santos just signed in to Whale Wallet"**.

- **What it says:** the user's name and email, the time (Philippine time), the device and browser, the IP address, and a **New device** tag when that device or IP was not seen for that user before. A button opens that user's page in `/admin`.
- **When it fires:** on Laravel's `Login` event, which covers password login, PIN login and passkey login. Admins' own sign-ins are skipped.
- **How it is sent:** `dispatch(fn () => Mail::to($admins)->send(...))->afterResponse()`, the same way as the sign-up code, so login never waits on the mail server and no worker is needed. A mail failure is logged and never blocks the sign-in.
- **Who gets it:** every admin who has "Login alerts" turned on in their admin settings (default on, for owners).
- Each login is also written to the activity feed (section 4) whether or not the email is sent.
- **Volume warning:** one email per login is fine today, but it grows with users. A busy day of 200 logins is 200 emails and may hit the Mailtrap plan limit. So the plan has a setting in admin, **Login alerts: every login / new devices only / off**. It starts on **every login** as you asked, and you can switch to new devices only when it gets noisy.

### 11. Admin management (`/admin/admins`)
For adding more admins later. Only an **owner** can open it.

| Role | Can do |
| --- | --- |
| **Owner** | Everything in this plan, including managing admins. You, from the start. |
| **Support** | Support chat and the basic user page (name, email, last active). No money data, receipts, conversations, loans or admin management. |

- **List:** name, email, role, last gate sign-in, login alerts on or off.
- **Add an admin:** type the email of an existing, verified user and pick a role. They must already have a login PIN (if not, they are told to set one in Settings > Security first). They get an email saying they were made an admin, and the next time they open `/admin` they go through the same PIN and emailed-code gate.
- **Change role or remove:** from the same list.
- **Safeguards:** you cannot remove or demote the last owner, or yourself if you are the only owner. Every change is written to the audit log and emailed to all owners. A removed admin's gate session ends right away.
- **Database:** instead of an `is_admin` yes/no, one column `admin_role` (null for normal users, `owner` or `support`). `admin_role` is not fillable, so only the `admin:make` command and this page can set it.

## What the admin can do (v1)

| Action | Notes |
| --- | --- |
| Suspend / unsuspend a user | New `suspended_at`; a suspended user cannot sign in and their sessions are deleted. |
| Sign a user out everywhere | Delete their rows in `sessions`. |
| Resend verification code | Reuses `sendEmailVerificationNotification()`. |
| Read contact messages | Mark handled. |

| Reply in support chat | Section 9. The only place the admin writes to a user. |

Not in v1: **impersonating a user** (signing in as them), and editing or deleting a user's money data, receipts or Whale Chat. The admin can **see** everything (sections 6 to 8) but only changes accounts through the actions above.

## Privacy and trust

- Decision: the admin can see all transactions, receipts and conversations. That is a lot of personal data, so these rules come with it:
  - Every view of a user's data, every receipt image opened, every conversation opened and every export writes an `admin_audit` row (admin, what, target user, time, IP). The admin can read the log; nobody can edit it.
  - Receipt images and chats are only served through gated admin routes, never public links.
  - Read-only for money data, receipts and Whale Chat.
- **The Privacy Policy and the in-app AI notice must be updated before launch.** Today the public privacy pages say chats and receipts are the user's own. They need to say that the operator can view transactions, receipts and conversations for support and safety, and that every access is logged. Showing real users data that was promised private, without saying so, is a legal and trust problem in the Philippines (Data Privacy Act), so this is a launch blocker, not a nice-to-have.
- Support chat messages are visible to the user and the admin only.

## Build plan

| Phase | Work | Size |
| --- | --- | --- |
| 0 Foundation | Migration (`admin_role`, `suspended_at`, `activity_logs`, `admin_audit`), `admin:make` command (creates you as owner), `admin` middleware with role checks (404 for non-admins), `/admin` route group, noindex + robots | S |
| 1 Gate | PIN + emailed code gate, session window, lockout, tests (wrong PIN, wrong code, reused code, non-admin 404) | S |
| 2 Overview + Users | Overview cards, user list with search and filters, user detail | M |
| 3 Loans, subs, activity | Loans and subs tables, activity log events and feed, health panel | M |
| 4 Transactions, receipts, conversations | Sections 6 to 8 with filters, image route, search | M |
| 5 Support chat | Tables, user Support page, admin inbox, polling, badges, emails | M |
| 6 Actions + audit | Suspend, force sign-out, resend code, audit log page, privacy policy and AI notice text | S |
| 7 Login alerts | `Login` listener, alert email in the blue design, setting (every login / new devices only / off), tests | S |
| 8 Admin management | `/admin/admins` page, owner and support roles, add or remove, safeguards, emails, tests | S |

Support chat (phase 5) is the one to build first after the gate if you want it "for now": it needs only phases 0 and 1 to exist.

### Technical notes
- Controllers in `app/Http/Controllers/Admin/`, pages in `resources/js/pages/admin/`, one `AdminLayout.vue` with tabs (Overview, Users, Loans & subs, Activity, Health).
- Admin queries use `->withoutGlobalScope('user')` in a single `AdminQuery` helper so the bypass lives in one place.
- Counts use `count()` and `sum()` queries, no loading of rows. Paginate lists at 25.
- Tests (one file per phase): non-admin gets 404; admin without the gate is redirected to the gate; the gate needs both PIN and code; a suspended user cannot sign in; the loans view shows other users' loans but a normal user still cannot.

## Decisions (approved)

1. **Gate window: 2 hours.** After the PIN and emailed code, the app remembers it for 2 hours, then asks again (also after sign-out or a new sign-in).
2. **One admin for now (you), with an Admin management page** so more can be added later without code changes. See section 11.
