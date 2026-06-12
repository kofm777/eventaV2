# Event Access System — Definitive Security & Conformance Audit

**Auditor:** Lead Auditor
**Scope:** `eventaccess` backend (Laravel) + `eventaV2-frontend` (Angular SPA), deployed to Railway
**Date:** 2026-06-12

---

## 1. Executive Summary

The system works in the demo "happy path" but is **not safe to operate as a real paid ticketing platform today.** The good news: the core building blocks are sound — QR signing uses HMAC, the download token is cryptographically strong (`Str::random(64)`), the order/payment scaffolding exists, the frontend models match the API contracts, and the frontend Docker build is a proper production build. The bad news is concentrated in three areas that, together, mean **money, access control, and availability are all currently unguaranteed.**

**The 4 things that matter most:**

1. **Payment is not actually enforced — tickets are free to anyone who can reach the API.** Both the public `confirm` endpoint and the `webhook` are unauthenticated and perform **zero** payment/signature verification. Anyone who learns or guesses an `order_number` can POST it and receive a real, scannable, emailed ticket without paying. This is the single most serious finding. (`confirm-no-auth-free-ticket`, `webhook-no-signature-no-auth`)

2. **The whole paid-events feature is invisible and the gate doesn't scope to events.** There is **no navigation link anywhere** to `/events` (public buy flow) or `/admin/events` (admin CRUD) — the entire e-commerce surface is reachable only by typing URLs. And once a ticket exists, the scanner **ignores `event_id`**, so a ticket bought for Event A admits at Event B. For a multi-event product this is a correctness failure, not a polish gap.

3. **Production runs on `php artisan serve`** — PHP's single-threaded dev server with **no nginx/php-fpm** — while doing heavy synchronous work (imagick QR renders, synchronous SMTP, a 13-query dashboard polled every 30s with a leaked timer). Any real concurrency yields 502s. The deploy also runs `migrate --force --seed` on **every** container boot, re-creating a weak default admin (`admin@example.com` / `admin123`) whose password is **printed in the login UI**, behind **non-expiring** tokens.

4. **The system over-promises on capacity, quantity, and email.** `capacity` is collected, stored, shown in the admin form ("Unlimited" placeholder) — and **never enforced** (unlimited oversell). `quantity > 1` is **billed** (`price × quantity`) but only **one** ticket is ever issued. Email is best-effort, un-queued, swallows failures, and there is **no way to retrieve a lost ticket** and **no admin visibility into orders/revenue at all.**

**On the owner's suspicion of "misunderstandings / non-conforming features":** The owner's instinct is correct, and it has a clear root cause. This codebase is a **single-event registration app that had a multi-event paid-ticketing module bolted on.** The new module reused the old design's assumptions, and that mismatch is exactly where the non-conformance lives:

- **Tickets are tied to a globally-unique participant `email`, not to an order/event.** Re-buying with the same email **overwrites another person's record** (name, event, QR) and carries **stale scan flags** across events — denying legitimate re-attendees entry. Access tiers are a hard-coded global `fair / fair + conference` enum, not per-event ticket types, and a single `ticket_price` covers both tiers.
- **One genuinely-reported "misunderstanding" turned out to be a non-bug.** Multiple analysts flagged a **MySQL strict-mode "Data truncated" 500** from writing capitalized gender (`Male`) into the lowercase `participants.gender` ENUM. The adversarial verification **refuted the crash**: the column inherits the case-insensitive collation `utf8mb4_unicode_ci`, so MySQL silently stores `'Male'` as `'male'` with **no error**. What remains real is (a) a benign **casing drift** (data reads back lowercase), and (b) the **avatar greeting bug** that flows from it — every attendee is greeted "Ms." because the kiosk compares against `'Male'` but receives `'male'`. We have **honestly downgraded the gender-ENUM crash from "critical" to a low-severity drift** and elevated the avatar mismatch as the real user-visible symptom. *(This correction is the most important "the suspected feature actually works differently than reported" item in the audit — see §4.)*

Overall health: **Demo-ready, production-unsafe.** Nothing here is unfixable, and the P0 list below is small and concrete.

---

## 2. Severity Table (counts by category × severity)

> Overlapping findings (the gender-ENUM cluster, the three capacity findings, the three quantity findings, the duplicate nav-link findings, the duplicate scan_type findings, the duplicate health-check and dashboard-poll findings) are **deduplicated** here and counted once under their canonical issue.

| Category | Critical | High | Medium | Low | Total |
|---|:---:|:---:|:---:|:---:|:---:|
| Security / Access-control | 2 | 3 | 2 | 4 | 11 |
| Payment / Order correctness | — | 1 | 3 | 3 | 7 |
| Data integrity (tickets/participants) | — | 2 | 3 | 3 | 8 |
| Missing feature | — | 3 | 4 | — | 7 |
| Non-conformance (spec vs build) | 1 | — | 4 | 5 | 10 |
| Bug (functional) | — | 1 | 4 | 5 | 10 |
| UX / Accessibility | — | 1 | 4 | 9 | 14 |
| Performance / Optimization / Ops | — | 1 | 5 | 4 | 10 |
| **Total (deduped)** | **4** | **12** | **29** | **33** | **78** |

**Critical (4):** unauth `confirm`, unauth `webhook`, `artisan serve` in prod, events feature unreachable (no nav).
**High (12):** scan endpoints unauthenticated + PII leak; weak seeded admin printed in UI; forgeable/never-expiring QR (placeholder secret); cross-event ticket reuse at gate; participant overwrite on re-buy; stale scan flags across events; quantity billed-but-not-issued; capacity never enforced; ticket retrieval missing; admin order/revenue visibility missing; avatar honorific always "Ms."; sync mail with no queue worker; env files swapped (no local dev); realtime is BroadcastChannel-only; admin Events CRUD no nav link; committed `vendor/`+`node_modules`; purchase form has no order summary/payment UI; `scan_type` never persisted.

---

## 3. CRITICAL & HIGH Issues

> Ordered: security/data-loss/correctness first, then availability, then the rest.

### CRITICAL

---

#### C1 — Unauthenticated `confirm` issues a free, real ticket to anyone with an order number
**Category:** Security (access control) · **Severity:** Critical
**Location:** `backend/routes/api.php:38`; `backend/app/Http/Controllers/PurchaseController.php:72-100`; `backend/app/Services/Payments/StubPaymentService.php:40-51`

**What's wrong:** `POST /api/v1/orders/{order_number}/confirm` sits **outside any auth middleware** (only `throttle:10,1`). `confirm()` takes only the `order_number` string, looks up the order, and if not already paid calls `paymentService->confirm($order)` — **no buyer identity check** (no email match, no session, no signed link). The bound `StubPaymentService::confirm()` returns `PaymentConfirmation(paid: true)` **unconditionally** (lines 46-50). `markPaidAndIssueTicket()` then flips the order to PAID, creates a participant + signed QR, and returns the download token and QR image.

**Why it matters:** Any actor who learns or observes a pending `order_number` — referrer leak, shared link, support screenshot, the value is returned to the buyer's own browser — obtains a real, scannable, emailed ticket **without paying.** This is the headline security failure.

**Fix:** Do not issue a paid ticket from an unauthenticated client-triggered confirm against a stub. Either remove the client `confirm` path entirely and issue tickets only from a **signature-verified webhook after a real gateway charge**, or gate `confirm` behind a one-time Laravel **signed URL** bound to the order + buyer email, and have `confirm()` verify the real gateway intent `status === succeeded`. Until a real gateway exists, **disable the public purchase/confirm flow in any deployed environment.**

---

#### C2 — Unauthenticated, unsigned webhook can mark any order PAID
**Category:** Security (access control) · **Severity:** Critical
**Location:** `backend/routes/api.php:44`; `backend/app/Http/Controllers/PaymentWebhookController.php:31-57`; `backend/app/Services/Payments/StubPaymentService.php:59-86`

**What's wrong:** `POST /api/v1/payments/webhook` is public (`throttle:60,1`, API-route CSRF-exempt). `handle()` forwards `$request->all()` to `PaymentService::handleWebhook()`. `StubPaymentService::handleWebhook()` does **no signature verification** (the `// TODO(real gateway): verify the provider signature` is never implemented), resolves an order purely from **attacker-supplied** `payload['order_number']` or `payload['payment_intent_id']`, and returns `paid: true` unconditionally. `handle()` then issues the ticket.

**Why it matters:** An anonymous attacker can `POST {"order_number":"ORD-XXXX"}` and force **any** pending order to PAID, issuing a real ticket. `order_number` is the only "secret," and it is high-entropy (`'ORD-'+Str::random(10)`) so blind guessing is impractical — but it is **not a payment authorization** and is exposed to the buyer's browser/email and returned by `GET /orders/{order_number}`.

**Fix:** Verify a provider **HMAC signature over the RAW request body** (pass the raw body, not `$request->all()`, to the verifier) using a webhook secret **before** trusting any payload; transition to PAID only when the verified event type indicates success **and** amount/currency match the order; reject unsigned requests with 400. While the stub is active, **disable or shared-secret-gate the webhook** (it adds nothing under auto-confirm).

---

#### C3 — Production runs on `php artisan serve` (single-threaded dev server)
**Category:** Non-conformance (availability) · **Severity:** Critical
**Location:** `backend/Dockerfile:51`

**What's wrong:** The container's sole web process is PHP's built-in dev server:
`CMD ["sh","-c","php artisan migrate --force --seed && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]`.
There is **no nginx/apache and no php-fpm** wired in, despite the `php:8.2-fpm` base. The built-in server is single-process/single-request, so concurrent requests serialize and the proxy returns 502/timeout under light load. Multiple hot paths do heavy synchronous work on this one worker: imagick QR renders, synchronous mail, the ~13-query dashboard polled every 30s.

**Why it matters:** Any real concurrency (more than one user) degrades to 502s. This caps the system at effectively single-user.

**Fix:** Serve via **php-fpm + nginx** (the base already ships php-fpm), or FrankenPHP/RoadRunner/Octane with multiple workers. Drop `artisan serve` for any real concurrency. (Also move `migrate --seed` out of the boot CMD into a one-shot release step — see M-Ops below.)

---

#### C4 — The entire paid-events feature is unreachable (no navigation link anywhere)
**Category:** Missing feature · **Severity:** Critical
**Location:** `eventaV2-frontend/src/app/app.component.ts:30-56`; `home.component.html:12`; routes at `app.routes.ts:12-14,22-24`

**What's wrong:** `/events`, `/events/:slug`, `/ticket/:token`, and the admin `/admin/events*` routes **exist and are fully built**, but nothing links to them. The public navbar (`app.component.ts:33-36`) has only Home/About/Contact/Register; the home hero CTA routes to `/register` (`home.component.html:12`). The authenticated admin navbar (`app.component.ts:48-56`) lists Dashboard/Participants/Scanner×2/Avatar×2/Logout — **no Events link.** The newly added guest e-commerce flow and the admin Events manager are reachable only by manually typing the URL.

**Why it matters:** The flagship feature this audit exists to evaluate is, in practice, **shipped dark.** No guest can buy a ticket and no admin can manage events through the UI.

**Fix:** Add a primary **"Events" / "Buy Tickets"** link to the public nav block and a "Browse Events" CTA on the home hero; add an **"Events"** link to the authenticated admin nav block. Consider making `/events` the main public entry now that paid tickets exist.

> *Severity note:* a related finding (`events-feature-unreachable-no-nav`) was adversarially adjusted to **medium** on the grounds that the routes work and are merely unlinked. We keep the canonical issue at **critical** for the owner because, for a product whose entire purpose is selling tickets, a completely unlinked storefront is a launch-blocker, not a discoverability nit.

---

### HIGH

---

#### H1 — Scan endpoints are completely unauthenticated and leak participant PII
**Category:** Security · **Severity:** High
**Location:** `backend/routes/api.php:22-27`; `backend/app/Http/Controllers/ScanController.php:81-84,92-94,108-111,162-165`

`/scan-fair` and `/scan-conference` are declared **outside `auth:sanctum`** (only `throttle:30,1`) despite the "🔐 Protected scan endpoints" comment. Any anonymous client with a valid (or forged — see H3) token can mark participants scanned, grant gate access, trigger queued org-SMTP mail, and **read participant PII** in the response: `id, first_name, last_name, company_name, gender, email, access_type, status` + scan flags. Both an access-control hole and a PII disclosure.
**Fix:** Put both scan routes behind `auth:sanctum` (or a dedicated scanner/device token); strip `email`/`company_name`/`gender` from scan responses to the minimum a kiosk needs (first name + access result). Keep the throttle as defense in depth.

---

#### H2 — Weak default admin (`admin@example.com`/`admin123`) seeded on every deploy and **printed in the login UI**, behind non-expiring tokens
**Category:** Security · **Severity:** High
**Location:** `backend/database/seeders/AdminSeeder.php:18-24`; `DatabaseSeeder.php:14-17`; `Dockerfile:51`; `config/sanctum.php:47`; `eventaV2-frontend/.../login/login.component.html:22,59`

`AdminSeeder` `firstOrCreate`s `admin@example.com` with `Hash::make('admin123')`; `DatabaseSeeder` runs it; the Dockerfile CMD runs `migrate --force --seed` on **every** boot. These credentials are **printed verbatim** in the login template — `login.component.html:59` literally renders `Password: admin123` and line 22 uses `admin@example.com` as the placeholder. Because `config/sanctum.php:47` sets `'expiration' => null`, a single login yields a **non-expiring** admin token granting full admin.
**Fix:** Drive the seeded admin from `ADMIN_EMAIL`/`ADMIN_PASSWORD` env vars and **fail the seed if unset in production**; force a password change on first login. Set Sanctum `expiration` to a finite value (e.g. 480 min). **Remove the printed credentials from the login UI.**

---

#### H3 — QR HMAC secret is a guessable placeholder; tokens have no expiry and cannot be revoked
**Category:** Security · **Severity:** High
**Location:** `backend/.env.example:62`; `backend/app/Services/QrCodeService.php:14-19,30,75-78`; `backend/config/app.php:20`

Every access QR is signed with `hash_hmac('sha256', json, config('app.qr_hmac_secret'))` and validity is decided **purely** by that signature (`hash_equals`). The only committed value for `QR_HMAC_SECRET` is the public placeholder `your-very-long-random-secret-key-for-qr-code-signing`. The constructor throws only when the secret is **empty** — it does **not** reject the known placeholder. If a deploy reuses it, anyone can mint a valid token for any `{id,email,access,event_id}` and pass the gate (scan endpoints are unauthenticated — H1). There is **no `exp`, no `jti`/nonce, no replay check, and no comparison against the stored `qr_token`**, so a forged or photographed QR is a permanent bearer credential.
*(`.env` is git-ignored and not committed, which is why this is High rather than Critical — exploitation is conditional on the operator reusing the documented placeholder. The no-expiry/no-revocation weakness is unconditional.)*
**Fix:** Set a 256-bit random `QR_HMAC_SECRET` in Railway and have `QrCodeService` **reject the known placeholder** (or enforce min entropy) at boot. Add an `exp` claim and a per-token `jti`, and/or compare the presented token to the persisted `qr_token` so individual badges can be revoked without rotating the global secret.

---

#### H4 — Guest ticket is NOT event-scoped at the gate — any valid QR admits at any event
**Category:** Non-conformance (access control) · **Severity:** High
**Location:** `backend/app/Http/Controllers/ScanController.php:18-134`; `backend/app/Services/OrderService.php:99-106`; `backend/routes/api.php:23-27`

`OrderService` writes `event_id` into the QR payload (lines 100-106) and participants carry `event_id`, but **`ScanController::processScan` never reads or checks `event_id`.** It validates the HMAC, loads the participant by `id`+`email`, checks `status==accepted` and the fair/conference tier, flips the scan flag, and admits. The scan routes take no `event_id`. A ticket bought for Event A scans in at Event B's gate. Event scoping today is only the global fair-vs-fair+conference tier, not the specific event.
**Fix:** Make the scanner event-aware: bind the scanner to an `event_id` (device/session) and require `participant.event_id` (or the payload `event_id`) to match before admitting; reject cross-event scans with a clear message.

---

#### H5 — Re-buying with an existing email silently overwrites another person's participant (identity, event, QR)
**Category:** Data integrity · **Severity:** High
**Location:** `backend/app/Services/OrderService.php:80-118`

`markPaidAndIssueTicket` resolves the participant **by email only** (`Participant::where('email', $order->buyer_email)->first()`, line 91). If found, it `->update($participantData)` — overwriting `first_name, last_name, company_name, gender, phone, access_type, status` **and `event_id`** — then **regenerates** `qr_token`/`qr_payload`/`qr_image`. Because `participants.email` is **globally UNIQUE** (not per-event), the same email used for Event A then Event B mutates the single shared row to Event B and **rewrites its QR**, silently invalidating Event A's ticket. The comment acknowledges the reuse was to avoid a duplicate-key 500, but the chosen reuse clobbers identity/event/QR.
**Fix:** Decouple tickets from the globally-unique participant — create a participant per order/event (scope email uniqueness per event), or store the QR/ticket on the **Order** itself. At minimum, never overwrite an existing participant's identity/event/QR on re-buy.

---

#### H6 — Scan flags live on the participant, not per-event — reused participants keep stale scan state and are denied legitimate entry
**Category:** Data integrity · **Severity:** High
**Location:** `backend/app/Services/OrderService.php:80-97`; `ScanController.php:99-116`; migration `2025_11_16_002403_*:11-14`; `2024_01_01_000002_create_scans_table.php` (no `event_id`)

`scanned_fair`/`scanned_conference` are two booleans on the participant row, **with no event scoping**. The guest re-buy path (`$participant->update($participantData)`) reassigns `event_id`, `status`, `access_type`, names — but **does not reset the scan flags.** So someone who attended Event A (`scanned_fair=1`) and buys for Event B gets their row repointed to Event B **still flagged scanned**; at Event B's gate `ScanController` returns `is_already_scanned:true` and **denies legitimate entry.** The `scans` table also has **no `event_id`**, making cross-event attendance analytics impossible.
**Fix:** Reset `scanned_fair=false`/`scanned_conference=false` in `$participantData` whenever a participant is (re)assigned to a new event; add `event_id` to `scans` and derive "already scanned" from a per-(participant, event) lookup. Long-term: model attendance as a per-event check-in row, not per-person booleans.

---

#### H7 — `quantity > 1` is billed (`price × quantity`) but only ONE ticket/QR is ever issued
**Category:** Data integrity / payment · **Severity:** High
**Location:** `backend/app/Services/OrderService.php:32-33,46-47,80-118`; `PurchaseTicketRequest.php:35`; orders migration `2025_12_01_000003:37`

`createOrder` computes `amount_total = round(ticket_price * quantity, 2)` and persists `quantity`. `PurchaseTicketRequest:35` allows `quantity => ['sometimes','integer','min:1']` with **no upper bound and no `in:1`**. But `markPaidAndIssueTicket` issues exactly **one** participant and **one** QR (no loop), and `participants.email` is UNIQUE so multiple tickets per buyer email are impossible anyway. The orders migration comment says `// v1 = always 1` yet nothing enforces it: an API caller can `POST quantity:10`, be charged 10×, and receive **one** admittable QR. The shipped frontend hard-codes `quantity:1` (`event-detail.component.ts:104`), so this is reachable only via direct API calls — but the validator advertises multi-quantity that issuance cannot honor.
**Fix:** Constrain `quantity` to `in:1` (or drop the field and stop multiplying `amount_total`) **until** real multi-ticket issuance exists. Do not bill for tickets that are never issued.

---

#### H8 — Event capacity is stored and editable but **never enforced** — unlimited oversell
**Category:** Missing feature · **Severity:** High
**Location:** `backend/app/Http/Controllers/PurchaseController.php:24-66`; `OrderService.php:30-52`; events migration `2025_12_01_000001:25`; `Event.php:29,44,91`; admin form `admin-event-form.component.html:61-64`

`events.capacity` is a real nullable column, validated `min:1`, exposed via `toPublicArray()`, and shown in the admin form with an "Unlimited" placeholder — implying sales are capped. A whole-app grep shows `capacity` referenced **only** in the model, the two FormRequests, the migration, and the factory — **zero reads** in `PurchaseController::purchase` or `OrderService::createOrder`. `purchase()` gates only on `is_published && allow_guest_checkout`. A capacity-limited event sells unlimited tickets; there is no sold-out state, no remaining-seats count, and no sold-out UI.
**Fix:** Before creating an order (and again under a lock in `markPaidAndIssueTicket`), when `capacity` is non-null count PAID orders / issued participants for the event and reject when `issued + quantity > capacity` with a 409/422 "Sold out". Surface remaining capacity in `toPublicArray()` and render a sold-out state in event-detail.

---

#### H9 — No way to retrieve a ticket if the email link is lost
**Category:** Missing feature · **Severity:** High
**Location:** `backend/routes/api.php:35-51`; `TicketController.php`; `PurchaseController.php:117-143`; orders migration `:55`

The only credential to reach a purchased ticket is the high-entropy `ticket_download_token` in the email link and the post-confirm SPA redirect. There is **no** lookup-by-email, **no** "resend ticket," **no** "find my tickets": grep for `buyer_email` across controllers returns **zero reads**, and grep for `resend/find/lookup` returns nothing. `PurchaseController::show` requires the exact `order_number` (also only delivered to the buyer) and only returns the token when already PAID. The orders table even **indexes `buyer_email`** "for pre-participant lookups" but no controller uses it. Combined with best-effort un-queued email (H11), a guest who closes the post-purchase tab has **no recovery path.**
**Fix:** Add a throttled "resend ticket"/"find my ticket" endpoint keyed on `buyer_email` (+ `order_number`, or an emailed magic link) that re-sends the token email; and/or display the `order_number` on the post-purchase screen.

---

#### H10 — Admin has zero visibility into orders, sales, or revenue
**Category:** Missing feature · **Severity:** High
**Location:** `backend/routes/api.php:64-80`; `AdminController.php:255-358`

No admin endpoint lists orders or reports sales/revenue. The admin route group exposes participants, dashboard, scans, and events CRUD — **no `/admin/orders`.** `getDashboardStats` aggregates only `Participant` and `Scan` and **never references the `Order` model** (the only "order" matches are `orderBy()` SQL calls). Total sales, revenue (`sum amount_total where status=PAID`), paid-vs-failed counts, and per-event sales are all invisible. No frontend orders page and no nav link either.
**Fix:** Add a paginated admin orders index (filterable by event/status, with revenue/sold aggregates) and a revenue section on the dashboard (`sum amount_total where status=PAID` grouped by event and day), plus a frontend page + nav link.

---

#### H11 — `QUEUE_CONNECTION=sync` with no queue worker — every email and "queued" job blocks the request inline
**Category:** Optimization / availability · **Severity:** High
**Location:** `backend/.env.example:21`; `ScanController.php:141`; `OrderService.php:132`; `AdminController.php:104,150`; `Dockerfile:51`

Default queue is `sync` and **no `queue:work`/Horizon/supervisor exists anywhere.** (1) `ScanController`'s `Mail::...->queue()` (line 141) runs **inline** under sync on the single worker. (2) `OrderService` ticket email is a plain synchronous `Mail::...->send()` (line 132) **inside a DB transaction holding a row lock.** (3) Admin accept/reject sends are synchronous; their method bodies sit inside a method-level try/catch, so an SMTP failure **500s the request after the status was already committed** — the admin sees failure for a succeeded action, and the retry hits the "already accepted/rejected" 400 guard. Real SMTP will block the lone worker per send.
**Fix:** Run a real queue driver (database/redis) + a `queue:work` worker (separate Railway service or supervised in-container); make all mail genuinely queued; move the ticket-email send **outside** the transaction (after commit). Wrap accept/reject mail so a mail failure never reverses a committed status change.

---

#### H12 — Avatar kiosk greets **every** attendee as "Ms." (honorific compares to `'Male'`, backend returns `'male'`)
**Category:** Bug · **Severity:** High
**Location:** `avatar-page.component.ts:121-123`; `avatar-conference.component.ts:131-133`; `ScanController.php:82,93,109,163`

Both kiosks compute `honorific()` as `return this.gender === 'Male' ? 'Mr.' : 'Ms.'`. `gender` comes from `scan.participant.gender`, which `ScanController` returns via `$participant->only([...'gender'...])` — the **lowercase** stored enum value `'male'`. `'male' !== 'Male'`, so the branch is always false and **every attendee, including males, is addressed as "Ms." over the loudspeaker.** This is the real, user-visible symptom of the gender casing-drift (see §4).
**Fix:** Compare case-insensitively (`this.gender?.toLowerCase() === 'male'`) in both avatar components, and normalize gender casing across the stack so stored and compared values agree.

---

#### H13 — Dev/prod environment files swapped — `ng serve` hits live production
**Category:** Bug (config) · **Severity:** High
**Location:** `eventaV2-frontend/src/environments/environment.ts:1-5` & `environment.prod.ts:1-5`; `angular.json`

`environment.ts` and `environment.prod.ts` are **byte-identical**: both `production:true` and both point at the live Railway URL. `ApiService` imports `environment.ts` directly. So **`ng serve` (local dev) hits live Railway prod**, and local testing mutates production data. `angular.json` has **no `fileReplacements`** anywhere, so `environment.prod.ts` is never actually swapped in for prod builds either.
**Fix:** Restore `environment.ts` to local dev values (`production:false`, `apiUrl: http://localhost:8000/api/v1`) and keep the Railway URL only in `environment.prod.ts`; add a `fileReplacements` entry under the `production` configuration in `angular.json`.

---

#### H14 — Scanner→avatar realtime is BroadcastChannel-only (same browser/machine) with no replay
**Category:** Missing feature · **Severity:** High
**Location:** `eventaV2-frontend/.../scan-broadcast.service.ts:28-82`; `scan-store.service.ts` (dead code)

Scans reach avatar kiosks **only** via `BroadcastChannel` (`postMessage`). BroadcastChannel works only between contexts of the **same browser, same machine, same origin**, and does not replay. In the realistic deployment where the gate scanner (phone/tablet) and the avatar kiosk (separate PC/screen) are **different devices**, the avatar **never receives the scan** and the welcome never plays; a late-opened tab also misses the last scan. The documented fallback `ScanStoreService` is **imported nowhere** — pure dead code.
**Fix:** Add a server-pushed channel (WebSocket/SSE/Pusher + Laravel Echo) keyed per gate, or have the avatar poll a "latest scan" endpoint. At minimum wire `ScanStoreService`/`sessionStorage` so a late-opened same-browser tab can render the last scan.

---

#### H15 — `scan_type` is never persisted — every scan row stores `'fair'`, breaking fair/conference analytics
**Category:** Bug (data/analytics) · **Severity:** High
**Location:** `ScanController.php:129-134`; `Scan.php:18-23`; `AdminController.php:304-313`; migration `2025_11_23_151406:15`

`Scan::create([...])` **omits** `scan_type`, and `Scan::$fillable` does **not** include it (so it'd be dropped by mass-assignment even if passed). The column takes its DB default `'fair'` for **every** row, including conference scans. The dashboard `daily_scans` aggregate groups by `scan_type`, so conference scan volume is permanently misattributed to "fair." The `$scanType` value is available at the create site. *(The frontend chart compounds this by keying the conference series on `scan_type === 'fair + conference'`, a value the backend never produces — `dashboard.component.ts:154-158`.)*
**Fix:** Add `'scan_type'` to `Scan::$fillable` and pass `'scan_type' => $scanType` in the `Scan::create(...)` call; fix the frontend key to `=== 'conference'`.

---

#### H16 — Purchase form charges with no order summary, no payment fields, misleading "Buy ticket" on free events
**Category:** UX (money path) · **Severity:** High
**Location:** `event-detail.component.html:21-77`; `event-detail.component.ts:88-129`

The "Buy a ticket" section collects buyer identity only — **no order summary** (price × quantity, total, currency), no quantity selector (hard-coded to 1), **no payment input.** Clicking "Buy ticket" immediately creates an order and (stub path) auto-confirms and navigates to `/ticket` with **no review/consent step.** For free events `formatPrice()` returns "Free" yet the button still says "Buy ticket" and errors still say "payment could not be confirmed." The conference checkbox adds access with no displayed price delta.
**Fix:** Add an order summary above submit; show the total on the button ("Pay 25.00 TND" / "Get free ticket"); add a review/confirmation step before charging; reflect the conference upgrade in the displayed total.

---

#### H17 — Admin Events CRUD has no nav link
**Category:** Missing feature · **Severity:** High *(canonical with C4; listed separately because it is the admin half)*
**Location:** `app.component.ts:48-56`
Routes `/admin/events*` are fully built but absent from the authenticated navbar; an admin cannot reach the events manager without typing the URL. **Fix:** add an `<a routerLink="/admin/events">Events</a>` inside the `*ngIf="isAuthenticated"` block.

---

#### H18 — Committed `vendor/` (8,590 files, 84 MB) and `node_modules` (39,682 files) bloat the repo
**Category:** Optimization / ops hygiene · **Severity:** High
**Location:** `/Users/mariem/eventaccess` (git repo)
The repo tracks **50,598 files** — ~96% are `node_modules` + `backend/vendor`. There is **no root `.gitignore`**. This bloats every clone/checkout, the Trivy `scan-ref: '.'`, and the Docker build context (the image ships all of `vendor/` only for `composer install --no-dev` to regenerate it). A stale in-repo `frontend/` copy also exists (deployed frontend is `eventaV2-frontend`).
**Fix:** Add a root `.gitignore` (`backend/vendor/`, `**/node_modules/`, `backend/storage/`, build artifacts); `git rm -r --cached backend/vendor frontend/node_modules` and commit. Consider deleting the stale in-repo `frontend/`.

---

## 4. Non-Conformance & Missing Features — Spec vs Reality

This section answers the owner's core question directly: **what was each feature supposed to do, and what does it actually do?**

| Feature | Supposed to | Actually does | Finding(s) |
|---|---|---|---|
| **Real payment** | Charge the buyer; issue a ticket only on a verified successful charge. | Stub auto-confirms `paid:true` unconditionally; `confirm` and `webhook` are unauthenticated and unsigned, so **tickets are free to anyone who reaches the API**. Failure branches (402, `STATUS_FAILED`) are dead with the stub; no retry/new-intent endpoint. | C1, C2, `no-payment-failure-ux`, `confirm-endpoint-no-pending-state-guard` |
| **Real email delivery** | Email each buyer their ticket reliably. | Synchronous, **un-queued** `Mail::send()` inside a DB transaction; failures **swallowed and only logged**; no `ShouldQueue`, no retry. No real transactional mailer configured in-repo (`config/mail.php` default `smtp`, `.env.example` → `mailhog`, no `.env` shipped). *(The earlier "deployed runs `MAIL_MAILER=log`" claim was **refuted** — no such config exists; default is `smtp`.)* | `no-real-email-sent`, H11 |
| **Capacity / sold-out** | Cap ticket sales at `capacity`; show sold-out. | Stored, validated, shown in admin form ("Unlimited"), **never enforced** → unlimited oversell. No sold-out state or remaining-seats anywhere. | H8, `capacity-column-unused`, `capacity-not-enforced-misleading` |
| **Quantity** | Sell N tickets, issue N. | Bills `price × N` but issues exactly **one** participant/QR; validator allows unbounded N while the schema comment says "v1 = always 1." | H7 |
| **Payment failure handling** | Surface a failed charge with a retry path. | Stub never fails, so the 402 path and `STATUS_FAILED` are untested/unreachable; FE shows a plain text error with **no retry**; a FAILED order is a dead end. | `no-payment-failure-ux` |
| **Ticket retrieval** | Let a buyer recover a lost ticket. | **No** resend / find-my-ticket / lookup-by-email endpoint. Token in the email is the only way back. | H9 |
| **Admin order visibility** | Admin sees orders, sales, revenue. | **No** orders endpoint; dashboard never touches the `Order` model. Zero revenue/sales reporting. | H10 |
| **Event-scoped scanning** | A ticket admits only at its own event. | Scanner **ignores `event_id`** entirely → a Event-A ticket admits at Event B. Scan flags and the `scans` table are not event-scoped either. | H4, H6, `cross-event-ticket-reuse` |
| **Per-event ticket types** | Each event defines its own tiers/prices. | Global hard-coded `fair / fair + conference` enum; one `ticket_price` per event for both tiers; conference gate check hard-codes the literal string. | `per-event-access-types-not-modeled` |
| **Refund / cancellation** | Cancel/refund an order; revoke a ticket. | `STATUS_CANCELLED` defined but **never set**; no refund/cancel endpoint; HMAC-only QR can't be revoked. | `no-refund-or-cancellation` |
| **Event lifecycle** | Renaming/unpublishing/deleting an event behaves predictably. | Rename **regenerates the slug**, 404-ing shared links and in-flight purchases. Unpublish doesn't stop in-flight `confirm()`. Deleting a non-default event **cascade-deletes its orders** while the ticket token still resolves. Past/ended events remain purchasable. | `slug-change-orphans-tickets-and-links`, `unpublish-no-side-effects-on-pending-orders`, `purchase-not-blocked-for-past-events` |

**The headline "misunderstanding" to set straight (gender ENUM):**
Multiple analyses reported that capitalized gender (`Male`) written into the lowercase `participants.gender` ENUM would throw a **MySQL strict-mode "Data truncated" 500**, breaking `/register` and the entire paid purchase-confirm flow. **Adversarial verification refuted the crash mechanism:** the column inherits `utf8mb4_unicode_ci` (case-**insensitive**) collation and there is no `sql_mode`/binary-collation override, so MySQL matches `'Male'` to ENUM element `'male'` and **stores it silently as `'male'` with no error.** What is real:

- **Casing drift (Low):** data round-trips as lowercase; FE/orders use mixed case. Benign today, but it would become a hard failure on a case-sensitive/binary collation. (`purchase-confirm-gender-enum-break`)
- **Avatar honorific bug (High):** the downstream `=== 'Male'` comparison fails → everyone greeted "Ms." (H12). **This is the real user-visible defect**, not a 500.
- **Test/seed fixtures (Low/Medium):** `ParticipantFactory`, `TestParticipantSeeder`, and `RegistrationTest` use `'Male'`/`'conference'`/`'both'`/`'foire'` — values the **`access_type`** ENUM genuinely rejects (`access_type` is `ENUM('fair','fair + conference')`, **not** collation-forgiving like gender). So the **test suite is broken** against the deployed schema. (`factory-seeder-invalid-enum-values`, `factory-invalid-access-type-enum`)

**Recommendation regardless:** normalize gender to one canonical lowercase casing at one layer (`strtolower()` in `OrderService`/`RegistrationController`, or `in:male,female,other` + lowercase the FE option values), and fix the factory/seeder/test to use the live enums. This removes the drift, fixes the avatar greeting, and unbreaks CI — without depending on collation behavior.

---

## 5. Medium / Low + Optimizations (grouped, terse)

### Security & authorization (Medium/Low)
- **No authorization policies/gates** — every authenticated admin can do everything (delete any event/participant); `authorize()` returns `true`; empty `AuthServiceProvider`. Add roles/Policies + audit log for destructive actions. *(M — `no-admin-authorization-policies`)*
- **`APP_DEBUG=true`/`APP_ENV=local` in `.env.example`**; CORS `supports_credentials:true` with a single env-driven origin and `headers:['*']`. Safe code defaults, brittle config. Force `APP_DEBUG=false`/`APP_ENV=production` and require `FRONTEND_URL` in prod; never allow `*` origin with credentials. *(M/L — `app-debug-default-and-cors`, `app-debug-env-driven`)*
- **Client-only `authGuard`** trusts `localStorage` token presence (no expiry/validity); **no HTTP interceptor** for 401→logout. Add an interceptor; optionally validate against `/auth/me`; set Sanctum expiration. *(M — `client-only-authguard`)*
- **Admin modal `[innerHTML]`** binds participant names from the public register endpoint (stored-XSS sink; Angular sanitizer limits real execution). Use `{{ }}` interpolation. *(M — `admin-list-innerhtml-xss`)*
- **`BadgeComponent.printQR` `document.write`s unescaped** participant name/access (self-XSS scope). Build via `createElement`/`textContent`. *(L)*
- **`order_number`/webhook throttles** are the only brute-force control; `order_number` is treated as a bearer secret on public confirm. Stop using it as an auth secret. *(L — `webhook-throttle-and-enumeration`)*
- **Ticket-by-token** exposes buyer PII + PDF with the token as sole credential, **no expiry/revocation** (token is strong + throttled, so brute force is infeasible). Add expiry/revocation. *(L — `ticket-pii-and-pdf-by-token-only`)*
- **`chmod -R 777 storage/logs`** in Dockerfile — use 775 with existing `www-data` ownership. *(L)*
- **`scanFair/scanConference` send `withCredentials:true` + no Bearer** while routes are unauthenticated — credentials are useless (not a CORS breaker given the single-origin config). Drop them, or add `auth:sanctum`. *(L)*

### Data integrity & order/payment correctness (Medium/Low)
- **`confirm()` has no state-machine guard** — a FAILED order can be re-confirmed into PAID; CANCELLED isn't blocked; webhook/`confirm` write `STATUS_FAILED` unconditionally (could flip a PAID order to FAILED on a late event). Guard transitions: only `PENDING_PAYMENT → {PAID,FAILED}`, never overwrite terminal. *(M/L — `confirm-endpoint-no-pending-state-guard`, `order-confirm-failed-status-no-amount-context`, `purchase-redirect-gateway-flow-incomplete`)*
- **Deleting a participant orphans a PAID order** — `orders.participant_id` is `nullOnDelete`, hard delete with no guard, no SoftDeletes → the ticket token still resolves but the page 404s; no refund/audit signal. Block deletion of participants backing a PAID order, or SoftDelete, or snapshot the ticket onto the order. *(M — `participant-nulled-on-delete-orphans-paid-order`)*
- **Scan flag committed before the `scans` row is inserted (outside the transaction)** — a failure between them leaves a flipped flag with no scan record, locking the attendee out. Move `Scan::create` (and the email) into the same transaction. *(M — `scan-flag-saved-then-scan-row-outside-transaction`)*
- **`Scan::$casts['raw_payload'] => 'array'`** but a plain token string is stored → reads decode to `null` (audit/debug data loss). Drop the cast or store JSON. *(M)*
- **`/register` participants never get `event_id`** (omitted from create + QR payload) → belong to no event; "NULL = default event" is UI-only, never resolved server-side, and `Event->participants()` excludes NULL rows (per-event counts undercount). Resolve the target event server-side; backfill legacy rows. *(M/L — `event-id-never-set-on-register`, `event-id-fillable-but-not-public-buyer-controlled`)*
- **`scan-by-image` (qr_image) branch is dead** — feeds raw PNG base64 into the token verifier, always 403s; the FE file-upload affordance actively invokes it. Implement real server-side QR decode or remove the affordance. *(M)*
- **Conference denial returns `ok:true` HTTP 200** — the scanner FE keys success on `response.ok`, so a fair-only attendee at the conference gate gets the **success beep and green screen.** Return `ok:false`/403 with a machine code. *(M — `conference-denied-returns-ok-true-200`)*
- **`is_default` has no DB uniqueness** (latent: nothing creates a second default). Enforce single-default via constraint or a saving hook; remove `is_default` from `$fillable`. *(M)*
- **Orders snapshot gender/access/buyer fields, then re-copy to participant** on re-buy → two divergent sources of truth. Treat the order snapshot as immutable receipt data. *(L)*
- **Enum-change migrations' `down()` restore French legacy vocab** (`homme/femme/autre`, `both/foire`) no validator accepts; base migration was retro-edited. `migrate:fresh` is fine forward; rollback is unsafe/dishonest. Don't retro-edit shipped migrations; fix `down()` or collapse the churn. *(L)*
- **`download_pdf_url` points at the SPA page, not the API PDF** (the FE works because it calls `/badge` directly; only a consumer trusting the field gets HTML). Rename or repoint. *(L)*
- **`decimal:2` casts serialize money as JSON strings**; FE types them `number|string` and must `Number()` everywhere; the `!price` guard conflates 0 with unknown. Use an accessor/Resource for float output or centralize coercion with `isFinite`. *(L)*
- **Missing/redundant indexes:** no `orders.participant_id` index, no `(event_id,status)` composite for capacity counts; **duplicate** `participants.email` index (UNIQUE + secondary). Add the two, drop the duplicate. *(L)*

### Functional bugs (Medium/Low)
- **Dashboard `setInterval` (30s) never cleared** — no `OnDestroy`/`clearInterval`; revisiting stacks intervals, each firing the heavy 13-query stats. Store the handle + `clearInterval` (or `takeUntilDestroyed`). *(M — `dashboard-setinterval-never-cleared`/`dashboard-poll-leak`)*
- **Dead nav `/about` & `/contact`** resolve to home via the `**` wildcard (real content is in-page anchors). Use fragment nav or real routes. *(M/L)*
- **Home "Get in Touch" form submits nowhere** — no `(ngSubmit)`, native GET reload. Wire it or remove it. *(M)*
- **Fair avatar shows "✗ Access denied" on benign re-scan** while the voice says "already granted" — screen/voice contradict. Add explicit granted/already-scanned/denied states. *(M)*
- **`HealthController` returns HTTP 200 when the DB is down** — orchestrator keeps routing to a dead instance. Return 503 when `$dbStatus` is false. *(M/L)*
- **`/events` & `/ticket` missing from the public-route allowlist** → authenticated users see the admin navbar on those public pages. Add them to `publicPaths`. *(L)*
- **`ApiService.scan()` POSTs `/api/v1/scan`** which has no route (404) — dead legacy method; delete it. **`healthCheck()`** is dead and the only method using `apiBaseUrl` directly (URL is correct). *(L)*
- **Delete-flow QR variable named/treated as URL but holds base64** (currently harmless — QR not rendered for deletion emails). Fix the naming/comment. *(L)*
- **`AppComponent` subscriptions + scroll listener have no teardown** (app-lifetime, low real leak). *(L)*
- **`ParticipantFactory`/seeder/`RegistrationTest` use invalid enum values** → test suite broken against the deployed `access_type` enum. *(M/L — see §4)*

### Performance / ops / build (Medium/Low — beyond C3/H11/H18)
- **`migrate --force --seed` on every boot** — bad migration can crash-loop the deploy; concurrent containers race `migrate` with no lock. Move to a one-shot release step. *(M)*
- **No `config:cache`/`route:cache`/`view:cache`** at deploy — Laravel re-parses config/routes per cold request on the single worker. Cache them in the CMD (env present at runtime) before `serve`. *(M)*
- **Empty `backend/.dockerignore` (0 bytes)** — `COPY .` ships the 84 MB `vendor/`, `.git`, logs, risks shipping a local `.env`. Populate it. *(M)*
- **`ext-imagick` not declared in `composer.json`** — QR PNG rendering hard-depends on it; installs succeed without it and fail at runtime on a critical path. Declare `ext-imagick` + other load-bearing exts. *(M)*
- **Per-scan QR re-render** — every scan re-runs HMAC + a 300×300 imagick render purely to attach the QR to the email, though `participants.qr_image` already holds the identical base64. Reuse the stored image (also in `PdfBadgeService`). *(M/L)*
- **Purchase-confirm does QR render + disk write + sync SMTP inside the locked DB transaction** — a slow mailer holds the order lock and blocks the worker. Move mail after commit; move QR render out of the locked section. *(M)*
- **`getDashboardStats` runs ~13 aggregate queries** (some non-sargable: `whereDate()`, `YEARWEEK()`), polled every 30s. `Cache::remember(...)` for 30–60s; replace `whereDate` with `>= today()->startOfDay()`; lengthen the interval. *(L)*
- **QR PNGs written to ephemeral local disk, no `storage:link`** — lost on every redeploy and `/storage/...` 404s (downstream uses base64, so it's dead write-only I/O). Drop the disk write or use durable storage + `storage:link`. *(L)*
- **CI "Deploy" job is a placeholder `echo`** gated on tests that don't reliably run (`|| true` masks lint/test; stale `RegistrationTest` asserts `pending` vs actual `accepted`; OVH SMTP creds hard-coded in `docker-compose.yml`). Railway deploys on push regardless, so a red pipeline doesn't block a broken deploy. Implement a real gated deploy or delete the stub; remove `|| true`; fix the test; move SMTP creds to secrets. *(L)*
- **Frontend `serve` static server** (vs nginx) — fine for MVP, but no gzip/brotli tuning, weak cache headers, no security headers (CSP/HSTS/X-Frame-Options). Optional hardening. *(L)*

### UX / accessibility / content (Low)
Grouped: purchase & event-form **labels lack `for/id`** + no `aria-invalid`/`aria-describedby` (regression vs register form, M); **`<html lang="fr">` but app is English** + TTS hard-codes `en-US` (M); **raw enums shown to users** — `PENDING_PAYMENT`/`PAID`/`fair + conference` on the ticket page (L); **events lists show empty on `ok:false`** with no error surfaced (L); **events list has no spinner/skeleton/retry + unlabeled pagination** (no `aria-live`) (L); **"Guest checkout not available" / published-but-closed events are dead ends** and `onSubmit` mislabels all errors as "Guest checkout is not available" (L); **ticket success screen** lacks "we emailed your ticket" / bookmark guidance, PDF error renders detached at top with no retry (L); **kiosk footer falsely claims "Female voice active"** (voice match only finds `samantha`/`zira`; falls back to platform default elsewhere) (L); **event-form toggles are bare checkboxes** (no `role="switch"`/`aria-checked`), high-stakes "Published" control unemphasized (L); **ticket PDF filename embeds the raw 64-char token** (ugly + leaks the secret) (L); **new events/ticket/admin screens hard-code "Segoe UI"** diverging from the app font/design (L); **purchasing state has no timeout/cancel** and no phase labels (L); **ticket email mislabels access type and omits the event name/date/location** — a real gap for a multi-event system (L).

### Verified-correct (no action needed)
`purchase()` 201 handling, `{order_number}` vs `$orderNumber` positional binding, events pagination shape match, public events pagination, confirm 402/`{ok,message}` envelope, `qr_image` raw-base64 contract — all **confirmed working**; recorded so the owner doesn't re-investigate them.

---

## 6. Prioritized Remediation Plan

### P0 — Do now (block any real/paid deployment)
1. **Lock down payment.** Gate `confirm` behind a signed, buyer-bound one-time URL and/or remove it; verify a webhook HMAC over the raw body before trusting any payload. **Until a real gateway is wired, disable the public purchase + webhook + confirm flow in deployed envs.** *(C1, C2)*
2. **Stop serving on `php artisan serve`.** Switch to php-fpm + nginx (or Octane/FrankenPHP). Move `migrate --seed` to a one-shot release step. *(C3, M-Ops)*
3. **Fix the seeded admin.** Drive from `ADMIN_EMAIL`/`ADMIN_PASSWORD`, fail the seed if unset in prod, force first-login password change; **remove `Password: admin123` from the login UI**; set a finite Sanctum token expiration. *(H2)*
4. **Authenticate the scan endpoints** (`auth:sanctum` or device token) and **strip PII** from scan responses. *(H1)*
5. **Set a real `QR_HMAC_SECRET`** in Railway and reject the placeholder at boot; set `APP_ENV=production`/`APP_DEBUG=false`. *(H3, `app-debug-*`)*
6. **Make the feature reachable** — add public "Events/Buy Tickets" and admin "Events" nav links (else the storefront ships dark). *(C4, H17)*
7. **Constrain `quantity` to `in:1`** (or stop multiplying `amount_total`) so no one is billed for tickets that aren't issued. *(H7)*

### P1 — Before onboarding real events/buyers
8. **Event-scope the gate** — check `event_id` on scan; reset scan flags on re-assignment; add `event_id` to `scans`. *(H4, H6)*
9. **Stop overwriting participants on re-buy** — per-order/per-event ticket model (or store the ticket on the order); scope `email` uniqueness per event. *(H5)*
10. **Enforce capacity** at order creation under a lock; surface sold-out + remaining seats. *(H8)*
11. **Add ticket retrieval** (resend/find-my-ticket) and **admin orders/revenue** visibility (endpoint + dashboard section + nav). *(H9, H10)*
12. **Real queue + worker; queue all mail; move ticket-email after commit; verify a real transactional mailer.** *(H11, `no-real-email-sent`)*
13. **Normalize gender** at one layer + fix the avatar honorific (`toLowerCase()`); fix factory/seeder/`RegistrationTest` enum values to unbreak CI. *(H12, §4 cluster)*
14. **Fix `scan_type` persistence** (+ the FE chart key) so analytics are real. *(H15)*
15. **Restore local-dev env config** + add `angular.json` `fileReplacements`. *(H13)*
16. **Purchase UX:** add order summary/total/review step; map raw enums to friendly labels. *(H16)*
17. **Realtime for multi-device** — server-pushed channel (or polling) for scanner→avatar. *(H14)*

### P2 — Hardening, hygiene, and polish
18. Authorization policies/roles + audit log for destructive actions; HTTP 401 interceptor + token validity check. *(`no-admin-authorization-policies`, `client-only-authguard`)*
19. Order state-machine guards; block participant deletion that backs a PAID order (or SoftDelete); move `Scan::create` into the scan transaction; `raw_payload` cast fix. *(order/data-integrity cluster)*
20. Event lifecycle: stable slug on rename, block purchase for past events, define unpublish/delete semantics for in-flight orders, refund/cancel path. *(lifecycle cluster)*
21. Repo & build hygiene: root `.gitignore` + `git rm --cached vendor/node_modules`; populate `backend/.dockerignore`; declare `ext-imagick`; add `config:cache`/`route:cache`/`view:cache`; `HealthController` → 503 on DB down; reuse stored `qr_image` instead of re-rendering; cache the dashboard payload. *(optimization/ops cluster)*
22. Accessibility & content: `for/id` labels + ARIA on the new forms, `lang="en"`, friendly status/access-type labels, error/empty/loading states with retry, ticket email with event name + delivery confirmation, real CI deploy gate (or delete the stub) and move SMTP creds to secrets. *(UX cluster, `ci-deploy-stub`)*

**Bottom line for the owner:** the architecture is salvageable and the fixes are well-scoped. **Do not take payments or run a public event until P0 is complete** — today the system gives away free tickets, oversells capacity, admits tickets at the wrong event, and can't survive two simultaneous users. Your suspicion that features "don't conform to spec" is accurate and traces almost entirely to a single-event app wearing a multi-event ticketing module; §4 is the map of exactly where that seam leaks.