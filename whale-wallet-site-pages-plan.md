# Whale Wallet: public site, navigation, footer and legal pages plan

Draft v0.1, 2026-10-21. Written for the web app in `Whale-Wallet-WebApp/myapp` (Laravel 13, Vue 3, Inertia 3, Tailwind 4, shadcn-vue). A visual sample of the About page with the new header and footer is in `about-page-sample.html`.

**Important:** the legal drafts in section 9 are starting points built from what the product does today. They are not legal advice. Have a Philippine lawyer review them before you launch publicly or take payments. Items in `[square brackets]` are facts only you can fill in.

---

## 1. Goal

Turn the single landing page into a small public site that builds trust before sign-up:

1. A consistent **header** with About, Pricing, Contact and Documentation.
2. A fuller **footer** with Privacy Policy, Terms and Conditions and Cookies.
3. The pages those links point to, with honest content that matches the product.
4. The shared building blocks and tests to ship them in the repo.

## 2. Site map

| Page | Path (proposed) | Audience | Status |
|---|---|---|---|
| Home (existing `Welcome`) | `/` | Visitors | Exists |
| About | `/about` | Visitors | Sample built (`about-page-sample.html`) |
| Pricing | `/pricing` | Visitors | Needs a decision (section 5.2) |
| Contact | `/contact` | Visitors, users | To build |
| Documentation | `/docs` | Users | To build (section 5.4) |
| Privacy overview (plain-language hub) | `/privacy` | Everyone | To build (section 12) |
| AI and your data | `/privacy/ai` | Everyone | To build (section 12) |
| Security | `/privacy/security` | Everyone | To build (section 12) |
| Who handles your data | `/privacy/processors` | Everyone | To build (section 12) |
| Privacy changes log | `/privacy/changes` | Everyone | To build (section 12) |
| Privacy Policy (formal document) | `/privacy-policy` | Everyone | To build, needs lawyer review |
| Security contact file | `/.well-known/security.txt` | Researchers | To build (section 12) |
| Terms and Conditions | `/terms` | Everyone | To build, needs lawyer review |
| Cookies | `/cookies` | Everyone | To build |
| Log in, Create account | existing auth routes | Visitors | Exist |
| App (dashboard, cards, bills...) | existing | Signed-in users | Exist, unchanged |

## 3. Header navigation

**Items, in order:** About, Pricing, **Privacy** (with a small lock icon, highlighted in the brand blue), Documentation, Contact, then a divider, Log in, Create account (primary button). Privacy sits in the header because privacy is a core promise of the product (see section 12).

| Rule | Detail |
|---|---|
| Logo | Whale tail icon plus "Whale Wallet" wordmark, links to `/` |
| Signed-in visitors | Replace "Log in" and "Create account" with a single "Dashboard" button |
| Active page | Bold text and `aria-current="page"` |
| Behavior | Sticky at the top with a light translucent background and a bottom border |
| Mobile (under 860px) | Menu button opens a full-width dropdown; button toggles `aria-expanded` and its label (Open menu, Close menu); links are at least 44px tall |
| Accessibility | Visible focus ring, keyboard operable, `nav` landmark labelled "Main", works without JavaScript for the desktop layout |
| Dark mode | Follows the app's appearance setting |

## 4. Footer

**Layout:** a brand column, then three link columns, a disclaimer line and a bottom bar.

| Column | Links |
|---|---|
| Brand | Logo, "Stay Afloat, Spend Smart. A personal finance app for people in the Philippines." |
| Product | About, Pricing, Documentation, Create account |
| Company | Contact, Log in |
| Privacy | Privacy overview, AI and your data, Security, Who handles your data, Report a vulnerability |
| Legal | Privacy Policy, Terms and Conditions, Cookies |

- **Disclaimer line:** "Whale Wallet tracks your money; it does not hold or move it. Tips are budgeting guidance, not financial advice. AI features use Google Gemini and run only after you give consent."
- **Bottom bar:** "© [year] Whale Wallet. All rights reserved." and compact links Privacy, Terms, Cookies.
- Signed-in visitors: hide "Create account" and "Log in", show "Dashboard".
- Optional later: social links, status page, version.

## 5. Page plans

### 5.1 About
Done as a sample. Sections: hero with the daily safe-to-spend card, what it is, who needs it, problems it solves, what it can do, how it works, why it is different, privacy and trust, where we are today (honest status), FAQ, final call to action. Update the "Where we are today" and FAQ blocks whenever the product changes (mobile app, pricing, email).

### 5.2 Pricing (decision needed)
The product has **no pricing or plans today**, so the page must not quote any. Choose one:

| Option | When to use | Page content |
|---|---|---|
| A. Early access, free | You are still testing | "Free during early access. Paid plans may come later; you will be told before anything changes." Short feature list, FAQ, sign-up button |
| B. Free plus Whale Plus | You are ready to charge | A two-column comparison. Prices are your decision. The business model suggests testing ₱79, ₱99 and ₱149 per month |
| C. Waitlist for paid plans | You want to test demand first | Option A plus an "Notify me about Whale Plus" email field |

Recommended now: **Option A with a waitlist field (C)** so you can measure interest. Never put a price on the page that the app cannot actually bill.

Draft copy for A: *"Whale Wallet is free while we are in early access. We are still learning what is most useful. If we ever add paid plans, we will tell you well before anything changes, and you will always be able to export your data."*

### 5.3 Contact
- **Purpose:** support, feedback, privacy requests, press.
- **Content:** short intro, a form (name, email, topic, message), a plain email address, expected reply time ("within [2] business days"), and a link to Documentation first.
- **Topics:** Support, Feedback, Privacy request (access, correction, deletion), Business, Other.
- **Backend:** `ContactController@store`, validation, throttle (for example 5 per minute), a honeypot field, store the message in the database and email it to you.
- **Dependency:** email delivery is **not connected yet**. Until a mail provider is set up, show the support email as a link and save form messages in the database for you to read.
- **Privacy:** one line under the form: "We use your details only to reply. See our Privacy Policy."
- **Support email:** support@whalewallet.cloud. Show it as a `mailto:` link next to the form.
- **Needs from you:** the reply-time promise (for example 2 business days) and whether to show "Metro Manila, Philippines" or a fuller address on the page.

### 5.4 Documentation
Two possible audiences. Plan for the first now.

1. **User guide (build now).** Short how-to articles inside the app's design.
2. **API reference (later, only if public).** `myapp/api.md` documents the mobile API. Keep it private unless you plan a public developer program.

User guide outline:

| Section | Articles |
|---|---|
| Getting started | Create an account; set your payday; add your first card; how the daily number works |
| Cards and money | Add banks, e-wallets, credit cards and cash; spending limits; move money between cards and pods |
| Bills, subscriptions and loans | Add a bill; mark paid with proof; cancel and renew; loans and payment counts; archived loans |
| Savings pods | Create a goal; monthly amount suggestion; add money with proof |
| Logging expenses | Quick entry; add or edit an expense; categories; export to CSV or Markdown |
| Receipts | Upload a receipt; check and confirm; duplicates; keeping or deleting images |
| Whale Chat | What you can ask; logging by chat or voice; confirmation cards; history; English, Tagalog and Taglish |
| Forecast and tips | How the forecast works; lowest balance; daily tips |
| Account and privacy | Two-factor authentication and passkeys; AI consent; export your data; delete your account |
| Troubleshooting | I did not get an email; receipt not read correctly; AI is unavailable |
| FAQ | Short answers to the top questions |

**Format options:** (a) one `/docs` page with a left table of contents and sections; (b) a page per article (`/docs/{slug}`) from Markdown files. Recommended: start with (a), move to (b) when there are more than about 15 articles.

### 5.5 Privacy Policy, Terms and Conditions, Cookies
Plain-language pages with a "Last updated" date, a table of contents, and a contact line. Draft outlines are in section 9. Link them from the footer, the sign-up form ("By creating an account you agree to the Terms and Conditions and acknowledge the Privacy Policy") and the AI consent dialog.

## 6. What the product does with data (for the legal pages)

Based on the project overview and a check of the code. Confirm each line before publishing.

| Data | Where it comes from | Used for | Kept |
|---|---|---|---|
| Name, email, password (hashed) | Sign-up | Account and sign-in | Until the account is deleted |
| Profile picture | User upload | Display | Until removed or account deleted |
| Cards, balances, bills, loans, pods, income, categories, transactions | User entries | The app's features | Until deleted by the user or with the account |
| Account numbers | User entries | Show on cards | Stored encrypted |
| Receipt images and salary proofs | User uploads | Reading amounts, proof | Receipt images deleted after confirm unless the user keeps them; salary proofs stay until the income entry is removed |
| Chat messages and history | User messages | Whale Chat | Soft-deleted from the list; erased when the user wipes history or deletes the account |
| AI consent record | User action | Prove consent | Until the account is deleted |
| Session and security data (session id, IP address, browser details) | Automatic | Keep you signed in, protect the account | While the session lasts (default 120 minutes of inactivity) and as needed for security |
| Push notification token | Mobile app | Send reminders | Until removed on logout or account deletion |

**Third parties (processors):**

| Party | Purpose | Notes |
|---|---|---|
| Google (Gemini API) | Receipt reading, chat understanding, voice transcription, tips | Used only after the user consents; data may be processed outside the Philippines; use the paid tier before public launch because free-tier inputs may be used by Google to improve its products |
| Railway | Hosting of the app and database | Check the region the database runs in |
| Expo | Push notifications for the mobile app | When the mobile app is live |
| Email provider `[to choose]` | Verification, password reset, optional daily digest | Not connected yet |
| rsms.me | Serves the Inter font on the home page | A third-party request that exposes the visitor's IP address. **Recommendation: self-host the font and remove this request** |

## 7. Cookies and similar storage (verified in code)

| Name | Type | Purpose | Duration |
|---|---|---|---|
| Laravel session cookie (name from config) | Essential | Keeps you signed in; session driver is `database` | Session, up to 120 minutes of inactivity by default |
| `XSRF-TOKEN` | Essential | Protects forms and requests from forgery | Session |
| "Remember me" cookie (only if used) | Essential/functional | Keeps you signed in on this device | Long-lived; confirm the setting |
| `appearance` | Preference | Remembers light, dark or system theme | 365 days |
| Sidebar state cookie | Preference | Remembers if the sidebar is open | Per the app setting; confirm the name and duration in `SidebarProvider.vue` |
| `localStorage: appearance` | Preference | Same as above on this device | Until cleared |
| `localStorage: whale-seen` | Preference | Remembers that today's Whale chat nudge was seen | Until cleared |

- **No analytics, advertising or tracking cookies exist today.** Say so on the Cookies page.
- If you add analytics or ads later, you need a consent banner and an updated policy before they load.
- A cookie banner is not needed for essential and preference cookies only, but the Cookies page and footer link are still recommended.
- Passkey sign-in (WebAuthn) does not use cookies.

## 8. Implementation plan for the repo

### 8.1 Shared building blocks
- `resources/js/components/public/PublicHeader.vue` and `PublicFooter.vue`: the header and footer from sections 3 and 4, auth-aware, active link from `$page.url`.
- `resources/js/layouts/PublicLayout.vue`: wraps header, `<slot />`, footer. Used by Welcome, About, Pricing, Contact, Docs and the legal pages.
- Update `resources/js/app.ts`: return `PublicLayout` for the public page names (`Welcome`, `About`, `Pricing`, `Contact`, `Docs`, `Legal/*`) instead of `null` for Welcome only.
- Refactor `Welcome.vue` to use the shared header and footer (it currently has its own).
- Self-host the Inter font and remove the `rsms.me` `<link>` tags.

### 8.2 Routes and pages
```php
Route::inertia('/about', 'About')->name('about');
Route::inertia('/pricing', 'Pricing')->name('pricing');
Route::inertia('/docs', 'Docs')->name('docs');
Route::inertia('/privacy', 'privacy/Overview')->name('privacy');
Route::inertia('/privacy/ai', 'privacy/Ai')->name('privacy.ai');
Route::inertia('/privacy/security', 'privacy/Security')->name('privacy.security');
Route::inertia('/privacy/processors', 'privacy/Processors')->name('privacy.processors');
Route::inertia('/privacy/changes', 'privacy/Changes')->name('privacy.changes');
Route::inertia('/privacy-policy', 'legal/Privacy')->name('privacy.policy');
Route::inertia('/terms', 'legal/Terms')->name('terms');
Route::inertia('/cookies', 'legal/Cookies')->name('cookies');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
```
Regenerate the Wayfinder routes after adding them.

### 8.3 SEO and sharing
- Unique `<Head title>` and meta description per page; Open Graph title, description and image.
- `robots.txt` and a simple `sitemap.xml` for the public pages.
- Canonical URL once the custom domain is set up.

### 8.4 Legal wiring
- Sign-up form: checkbox or sentence linking Terms and Privacy; optionally store `terms_accepted_at`.
- AI consent dialog: link to the Privacy Policy section on AI processing.
- Settings: link to the privacy page and the "export or delete" actions.

### 8.5 Tasks and acceptance criteria

| # | Task | Done when |
|---|---|---|
| 1 | Build `PublicHeader`, `PublicFooter`, `PublicLayout`; wire in `app.ts`; refactor Welcome | All public pages show the same header and footer; mobile menu works; signed-in visitors see "Dashboard" |
| 2 | Add About page from the sample | `/about` renders in the layout; no unbuilt feature is promised |
| 3 | Add the three legal pages from section 9, after lawyer review | Footer links open them; "Last updated" date shown |
| 4 | Self-host the font | No request to `rsms.me` in the network panel |
| 5 | Add Contact page and backend | Form validates, throttles, stores messages; email link shown until a mail provider is connected |
| 6 | Add Documentation page (user guide) | Each section in 5.4 has at least a short article |
| 7 | Add Pricing page (option A or C) | No price shown that the app cannot bill |
| 8 | SEO, sitemap, robots | Each page has a unique title and description |
| 9 | Tests | Pest or PHPUnit tests: each public route returns 200 for guests and signed-in users; footer legal links present; contact validation and throttling |
| 10 | Browser check | Header and footer checked at phone, tablet and desktop widths, light and dark |

Suggested order: 1, 4, 2, 3, 8, 9, 5, 6, 7.

## 9. Draft content for the legal pages

Replace every `[placeholder]`. Keep the language simple. Have a lawyer review all three.

### 9.1 Privacy Policy (draft)

**Last updated:** [date of publication]. **Who we are:** Rhuzzel Paramio, operating Whale Wallet from Metro Manila, Philippines. Contact: support@whalewallet.cloud.

1. **What this policy covers.** How Whale Wallet collects, uses, shares and protects your personal data when you use our website and app.
2. **What we collect.** Account details (name, email, password stored in hashed form), profile picture, the financial information you enter (cards, balances, bills, loans, savings goals, income, categories and transactions), account numbers you choose to add (stored encrypted), receipt images and proofs you upload, your chat messages with Whale, your AI consent record, device tokens for notifications (mobile app), and technical data such as IP address, browser details and session information.
3. **How we use it.** To provide the app, calculate your daily safe-to-spend amount and forecasts, read receipts and answer chat requests, send reminders you ask for, secure your account, answer your messages, and improve the service.
4. **AI features.** Receipt reading, chat, voice transcription and daily tips use Google Gemini. We send your data to Google only after you give consent, and you can withdraw consent in Settings. Google may process data outside the Philippines. [Add the paid-tier and data-use statement that applies to your Google plan.] Totals shown in the app are calculated by Whale Wallet, not by the AI.
5. **Who else receives data.** Our hosting provider (Railway), Google for AI features, [email provider], Expo for push notifications. We do not sell your personal data. We do not use it for advertising.
6. **How long we keep it.** Until you delete it or your account. Receipt images are deleted after you confirm unless you choose to keep them. Salary proofs stay until you remove that income entry. Deleted chats stay out of your list but are erased when you wipe history or delete your account.
7. **Security.** Account numbers are stored encrypted, accounts can use two-factor authentication and passkeys, each user's data is separated from other users, and logins are rate limited. No system is perfectly secure; tell us at once if you suspect a problem.
8. **Your rights.** Under the Data Privacy Act of 2012 (Republic Act No. 10173) you have the right to be informed, to access, to correct, to object, to erase or block, to data portability and to claim damages, and you may complain to the National Privacy Commission. You can export your data and delete your account in Settings, or contact us.
9. **Children.** Whale Wallet is not for children under [18, or the age you decide].
10. **Changes.** We will post changes here and update the date; for important changes we will notify you in the app or by email.
11. **Contact and data protection officer.** Rhuzzel Paramio (data protection contact), support@whalewallet.cloud, Metro Manila, Philippines. [Add a street or registered business address if you have one.]

### 9.2 Terms and Conditions (draft)

**Last updated:** [date].

1. **Acceptance.** By creating an account or using Whale Wallet you agree to these Terms and the Privacy Policy.
2. **What Whale Wallet is.** A tool to track and plan your money. It does not hold, move or invest your money, and it is not a bank, e-wallet or lender.
3. **Not financial advice.** Tips and forecasts are budgeting guidance based on the data you enter. They are not licensed financial, legal or tax advice, and you are responsible for your own decisions.
4. **Your account.** You must provide accurate details, keep your password and devices secure, and tell us about unauthorized use. You are responsible for activity on your account.
5. **Your data.** You own the data you enter. You give us permission to process it to provide the service. You can export or delete it at any time.
6. **AI features.** AI output can be wrong. Check every draft before you tap Save. Nothing is saved until you confirm.
7. **Acceptable use.** No unlawful use, no attempts to break, overload or reverse engineer the service, no uploading content you do not have the right to share, no sharing other people's financial data without permission.
8. **Availability.** We work to keep Whale Wallet available but do not guarantee it is always error free or online. We may change or discontinue features with reasonable notice.
9. **Pricing.** [Whale Wallet is free during early access. If paid plans are introduced, we will tell you before they apply and you may cancel.]
10. **Intellectual property.** The Whale Wallet name, logo, mascot and software belong to Rhuzzel Paramio or its licensors. You may not copy or reuse them without permission.
11. **Limitation of liability.** To the extent the law allows, we are not liable for indirect or consequential losses, or for losses from your reliance on forecasts or tips. Our total liability is limited to [amount or fees paid in the last 12 months]. Nothing here limits rights you cannot waive under Philippine law.
12. **Termination.** You may delete your account at any time. We may suspend or end accounts that break these Terms.
13. **Changes.** We may update these Terms. Continued use after the effective date means you accept them.
14. **Governing law.** The laws of the Republic of the Philippines apply. Disputes go to the courts of [city], Philippines.
15. **Contact.** Rhuzzel Paramio, Metro Manila, Philippines, support@whalewallet.cloud.

### 9.3 Cookies page (draft)

**Last updated:** [date].

- **What cookies are.** Small files a website stores in your browser to remember things.
- **What we use.** Only essential and preference cookies (table in section 7 of this plan, in plain wording): keeping you signed in, protecting forms, remembering light or dark mode, and remembering whether the sidebar is open.
- **What we do not use.** No advertising cookies and no analytics or tracking cookies at this time.
- **Third-party requests.** [Remove this line after self-hosting the font: our home page loads the Inter font from rsms.me, which may see your IP address.]
- **Managing cookies.** You can delete or block cookies in your browser settings. Blocking essential cookies will stop sign-in from working.
- **Changes.** If we add analytics or other cookies, we will ask for your consent first and update this page.
- **Contact.** support@whalewallet.cloud.

## 10. Compliance and admin checklist (confirm with a professional)

- [ ] Lawyer reviews Privacy Policy and Terms.
- [ ] Check National Privacy Commission obligations for your data volume (privacy notice, data protection officer, breach procedure, registration if required).
- [ ] Move AI to a paid Gemini tier, or tell users clearly about free-tier data use, before real users sign up.
- [ ] Confirm hosting region and describe cross-border processing in the policy.
- [ ] Connect an email provider; then enable verification, reset and optional digests and update the policy.
- [ ] Register your business (for example DTI) and the BIR before charging money.
- [ ] Trademark search for "Whale Wallet"; secure a domain and a support email.
- [ ] Decide the retention period for security logs and contact messages.

## 11. Open questions for you

1. Answered: operator Rhuzzel Paramio, Metro Manila, support@whalewallet.cloud. Open: a fuller address or registered business name (a privacy policy normally gives one), and whether you operate as a sole proprietor.
2. Pricing page: option A (free early access), B (free plus Whale Plus) or C (waitlist)?
3. Assumed: you are the data protection contact (Rhuzzel Paramio). Confirm.
4. Minimum age for users?
5. Is the Documentation page for users only, or will you publish the mobile API for developers later?
6. Do you want a cookie banner now, or only after you add analytics?
7. Assumed from your support email: the site domain is whalewallet.cloud. Confirm, and check that it points to the Railway app.

---

## 12. Privacy-first additions (added 2026-10-21)

Decision from you: this is a personal project and **user privacy is the priority**. The rule for everything below: **only claim what is true and checkable.** Each pledge on the site needs a matching line in the code or a documented setting.

### 12.1 Header and footer changes
- Header gets a **Privacy** link (lock icon, brand blue) between Pricing and Documentation.
- Footer gets a **Privacy** column: Privacy overview, AI and your data, Security, Who handles your data, Report a vulnerability. The formal Privacy Policy, Terms and Cookies stay in the Legal column.
- The About page gets a **"Privacy comes first"** section with six pledges (see the sample).

### 12.2 New pages

| Page | What it says |
|---|---|
| Privacy overview (`/privacy`) | The six pledges in plain language, a "what we collect and why" table, and shortcuts to export, delete and consent settings |
| AI and your data (`/privacy/ai`) | Exactly what is sent to Google Gemini (receipt images, chat text, voice clips, totals for tips), when, why, that consent is required, how to withdraw, that the app works without AI, and what the paid or free Gemini plan means for data use |
| Security (`/privacy/security`) | Only verified measures: encrypted account numbers, two-factor authentication, passkeys, per-user data separation, login rate limiting, revocable API tokens, receipt-image deletion. Plus honest limits: no system is perfect |
| Who handles your data (`/privacy/processors`) | A table of every service that touches data: Google (AI), Railway (hosting), Expo (push, when the mobile app is live), email provider (when chosen), with purpose and region |
| Privacy changes (`/privacy/changes`) | A dated log of every change to data handling, newest first |
| Report a vulnerability | A short page and `/.well-known/security.txt` with `support@whalewallet.cloud`, what is in scope, and a promise not to take action against good-faith reports |

### 12.3 Pledges (draft; confirm each before publishing)

| Pledge | Backed by | Status |
|---|---|---|
| We never sell your data | Your own commitment; also state it in the policy | Needs your confirmation |
| No ads, no trackers | No analytics or ad code exists. The home page currently loads a font from `rsms.me`: **self-host it first** | True after the font change |
| AI only with your OK, and the app still works without it | `ai.consent` middleware on receipts, chat and transcription; manual entry works | Verified in routes; test AI-off behavior |
| Nothing saves until you confirm | Confirm cards, receipt confirm flow | Verified in product overview |
| Receipt images are deleted after you confirm unless you keep them | Receipt retention behavior | Verified in product overview |
| Export or delete everything | Settings > Data page, API export and delete | Verify the delete removes images and chat |
| Account numbers are encrypted | Stated on the home page | Verify in the code |

### 12.4 In-app privacy features to add (your repo already has Settings pages for Appearance, Data, Profile, Security and Tokens)

| Priority | Feature | Why |
|---|---|---|
| P0 | **AI consent per feature** (chat, receipts, voice, daily tips) with a clear list of what is sent | Finer control than one switch |
| P0 | **"AI off" mode** check: every screen works with consent withdrawn | Backs the pledge |
| P0 | Verified **account deletion** (data, images, chat, tokens, sessions) with a confirmation email or step | Backs the pledge |
| P1 | **Active sessions and devices** page: see where you are signed in, sign out others | Account safety |
| P1 | **Hide amounts** toggle (blurs balances in public) | Shoulder-surfing protection |
| P1 | **Retention settings**: receipt images (delete after confirm, or keep), chat history auto-delete after N days | Data minimization |
| P1 | Chat **history wipe** with one clear button and a confirmation | Already partly built |
| P2 | **App lock** on mobile (biometrics or PIN) | Lost-phone protection; the phone stores no financial data, but screens could still show it |
| P2 | Privacy-friendly, cookie-free analytics (only if you need analytics) | Avoids tracking |
| P2 | Data export in CSV and JSON including chat | Portability |

### 12.5 Technical hardening checklist
- [ ] Self-host the Inter font; no third-party requests on any page.
- [ ] Security headers on every response: `Strict-Transport-Security`, `Content-Security-Policy` (start in report-only mode), `X-Content-Type-Options`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (microphone only for the chat page), `X-Frame-Options` or `frame-ancestors`. I did not find these configured in the app code; check your Caddy or Railway setup.
- [ ] `SESSION_SECURE_COOKIE=true` and `SameSite=Lax` in production; confirm cookies carry `Secure` and `HttpOnly`.
- [ ] Do not log request bodies, receipt text, chat text or tokens. Review logs and error reports.
- [ ] Private storage for uploads with short-lived signed URLs; no public receipt paths.
- [ ] Rate limits on login, password reset, contact form, AI endpoints (already present on AI endpoints).
- [ ] Backups: encrypted, with a retention period, and deleted-account data removed from backups on schedule.
- [ ] Dependency updates and `composer audit` / `npm audit` in CI.
- [ ] A short **breach response plan** (who you tell, how fast, the National Privacy Commission rules).
- [ ] Two-factor sign-in on the mobile app (currently a known gap).

### 12.6 AI and privacy (the biggest risk)
- Free-tier Gemini inputs may be used by Google to improve its products. For a privacy-first product, **move to a paid Gemini tier before real users sign up**, or keep AI features off by default for them. Check Google's current terms for the exact data-use wording and region.
- Send the minimum: tips already use totals only (no merchants or notes). Keep it that way. Avoid sending full account numbers or names in prompts.
- Show the user, before they consent, exactly what leaves the app for each feature.
- Voice: say that audio goes to Google for transcription, and that you do not keep the recording.

### 12.7 Trust signals you can add cheaply
- Link to the **source code** only if the GitHub repository is public. The repo has an Apache 2.0 license file; confirm the repository visibility first.
- A "Built by one person" line and a way to reach them (support email).
- A privacy changelog and a "last reviewed" date.
- Honest limits: "We cannot promise perfect security. Here is what we do, and how to report a problem."

### 12.8 Updated task list (adds to section 8.5)

| # | Task | Done when |
|---|---|---|
| 11 | Add the Privacy link to the header and the Privacy column to the footer | Both show on every public page |
| 12 | Build the five privacy pages and `security.txt` | All links resolve; every pledge is backed by code or a setting |
| 13 | Self-host the font and add security headers | No third-party requests; headers visible in the browser network panel |
| 14 | Per-feature AI consent and an "AI off" test | Withdrawing consent disables each AI feature and the app still works |
| 15 | Verify account deletion end to end | A test proves data, images, chats, tokens and sessions are gone |
| 16 | Move Gemini to a paid plan (or ship AI off by default) before public launch | Decision recorded in the privacy changes log |

### 12.9 More open questions
1. Is the GitHub repository public, and do you want to show a "view the source" link?
2. Will you commit to a response time for privacy requests (for example within 30 days)?
3. Do you want AI features on by default after consent, or off until the user turns on each one?
4. How long should chat history and security logs be kept by default?
