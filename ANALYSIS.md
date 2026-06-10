# Event Access System — Definitive Technical Explainer

> Single source of truth for the "Event Access System" (EventAccess) codebase: a Laravel 10 REST API + Angular 17 SPA for QR-based event access control. This document is derived entirely from a structured read of the real code at `/Users/mariem/eventaccess`.

---

## 1. What this project is

**EventAccess** is a two-tier web application for running access control at a physical event — specifically an event that has two access tiers: a **fair** ("foire") floor and an upgraded **fair + conference** pass. It exists to take an attendee from online sign-up all the way to walking through a gate, with a personalized on-screen welcome.

The core idea is **signed QR codes as access credentials**:

1. A participant registers on a public web form (`first_name`, `last_name`, `company_name`, `gender`, `phone`, `email`, and an access tier).
2. The backend mints a cryptographically signed QR token (HMAC-SHA256), renders it as a PNG, and emails it to the participant.
3. On event day, a staff member points a camera (or uploads an image) at the attendee's QR. The backend re-validates the signature, checks the participant's access tier, prevents double-entry, and records a scan.
4. A second screen — an animated **avatar kiosk** — speaks a personalized welcome ("Dear Mr./Ms. …, welcome to the fair") using the browser's text-to-speech.

**Who uses it:**
- **Participants / attendees** — use the public registration form and receive a badge/QR by email.
- **Event staff / gate operators** — use the scanner pages (one for the fair gate, one for the conference gate). Note: these pages are reachable behind the client-side `authGuard`, but the scan API endpoints they call are unauthenticated (see §9).
- **Administrators** — use a dashboard (KPIs, charts) and a participant-management list (accept/reject/delete, badge PDF download) protected by Sanctum token auth.

It is, in practice, a **single-event, single-admin MVP**. Despite extensive documentation claiming "95% production ready," the code is an **early prototype in active flux** (see §8). The two most important things an owner must know up front: **real production secrets are committed to git** (§9), and the **scan endpoints are completely unauthenticated** (§9).

---

## 2. Architecture at a glance

### System diagram

```
                          ┌──────────────────────────────────────────────────────────┐
                          │                        BROWSER                           │
                          │                                                          │
  Participant ──────────▶ │  Angular 17 SPA (standalone components, lazy routes)      │
  Staff / Admin ────────▶ │  ───────────────────────────────────────────────────     │
                          │   • Public: home / register / badge                       │
                          │   • Admin:  login / dashboard / participants              │
                          │   • Scanner (fair)  ◀──┐    Scanner (conference) ◀──┐      │
                          │   • Avatar  (fair)  ───┘    Avatar  (conference) ───┘      │
                          │         ▲   BroadcastChannel (same-origin, same-browser)   │
                          │         └── 'eventaccess_fair_scan' / '..._conference_scan'│
                          └───────────────┬──────────────────────────────────────────┘
                                          │  HTTP/JSON  (Bearer token for admin;
                                          │             withCredentials for scans)
                                          ▼
                          ┌──────────────────────────────────────────────────────────┐
                          │           LARAVEL 10 API  (PHP 8.2, `php artisan serve`)   │
                          │   routes/api.php  → /api/health, /api/v1/*                 │
                          │   ┌────────────┬────────────┬───────────┬──────────────┐   │
                          │   │Registration│   Scan     │   Auth    │    Admin     │   │
                          │   │Controller  │ Controller │Controller │  Controller  │   │
                          │   └─────┬──────┴─────┬──────┴─────┬─────┴──────┬───────┘   │
                          │         │            │            │            │           │
                          │   ┌─────▼─────┐ ┌────▼─────┐ ┌────▼──────┐     │           │
                          │   │QrCodeSvc  │ │PdfBadge  │ │Participant│     │           │
                          │   │(HMAC sign)│ │Svc(Dompdf)│ │AccessMail│     │           │
                          │   └─────┬─────┘ └────┬─────┘ └────┬──────┘     │           │
                          └─────────┼────────────┼────────────┼───────────┼───────────┘
                                    │            │            │           │
              ┌─────────────────────┘            │            │           │
              ▼ QR PNG (simple-qrcode)           ▼ PDF        ▼ SMTP       ▼ Eloquent
       ┌────────────┐                      (A4 badge)    ┌─────────────┐  ┌──────────────┐
       │ public disk│                                    │  OVH SMTP   │  │   MySQL 8.0  │
       │ qrcodes/   │                                    │ (or MailHog │  │ event_access │
       │ *.png      │                                    │  :8025 UI)  │  │ participants │
       └────────────┘                                    └─────────────┘  │ scans/admins │
                                                                          │ pat / migr.  │
                                                                          └──────────────┘
```

A nuance the diagram captures but is worth stating plainly: the **scanner page and the avatar page do not talk to each other through the server**. After a scanner POSTs to the API, it relays the result to the avatar screen purely via the browser's `BroadcastChannel` — which only works **between tabs of the same browser on the same machine**. There is no WebSocket and no polling.

### Tech stack (exact versions)

**Backend**
- **Laravel 10** — `backend/composer.json` requires `laravel/framework: ^10.10`; `composer.lock` pins **v10.49.1**. (The README's claim of "Laravel 11" is wrong — see §8.)
- **PHP 8.2** (base image `php:8.2-fpm`)
- **Laravel Sanctum** — personal-access-token auth for admins
- **simplesoftwareio/simple-qrcode ^4.2** (over BaconQrCode 2.0.8) — QR PNG rendering, requires `ext-imagick` for PNG output
- **dompdf/dompdf ^3.1** and **barryvdh/laravel-dompdf ^3.1** — PDF badge rendering (the raw `Dompdf` class is used; the Laravel facade wrapper is installed but unused)
- **MySQL/MariaDB** (utf8mb4, `strict=true`)

**Frontend**
- **Angular 17** (`^17.0.0`) — fully standalone components, no NgModules
- **zone.js 0.14** (default change detection; not zoneless)
- **@zxing/library** — in-browser camera QR decoding
- **Chart.js** — dashboard charts
- Browser **Web Speech API** (`speechSynthesis`) for avatar TTS, **BroadcastChannel** for cross-tab scan delivery

**Infrastructure**
- **docker-compose** schema 3.8 — four services: `mysql:8.0`, `backend` (built), `frontend` (built, `node:20-alpine`), `mailhog/mailhog:latest`
- **GitHub Actions** CI (`.github/workflows/ci.yml`)
- **Make** as the developer entrypoint (`Makefile`)

---

## 3. The data model

The schema lives in MySQL database `event_access` (config default connection `mysql`, `utf8mb4_unicode_ci`, strict mode on — `backend/config/database.php`). There are **three domain tables** — `participants`, `scans`, `admins` — plus the framework tables `personal_access_tokens` (Sanctum) and `migrations`. Eloquent models are in `backend/app/Models/` (`Participant`, `Scan`, `Admin`).

### `participants` (the central table)

Created by `2024_01_01_000001_create_participants_table.php`, then mutated by six later migrations.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | auto-increment |
| `first_name`, `last_name` | varchar(255) NOT NULL | |
| `company_name` | varchar(255) **NULL** | added `2025_11_11_192240`; **app-required** via `RegisterParticipantRequest` though DB-nullable |
| `gender` | **ENUM('male','female','other') NOT NULL** | effective deployed enum after `2025_11_13_145854` (see drift note below) |
| `phone` | varchar(30) NULL | |
| `email` | varchar(255) NOT NULL **UNIQUE** | plus a redundant secondary index |
| `access_type` | **ENUM('fair','fair + conference') NOT NULL** | effective enum after `2025_11_13_131223` |
| `qr_token` | **TEXT NULL** | originally varchar(128); widened by `2025_11_10_101945` |
| `qr_payload` | longText NULL | "MariaDB compatibility"; cast to `array` in the model |
| `qr_image` | longText NULL | base64 data-URI of the QR PNG; added `2025_11_10_155206` |
| `status` | **ENUM('pending','accepted','rejected','scanned') DEFAULT 'pending'** | `'scanned'` added `2025_11_14_000001` |
| `scanned_fair` | tinyint(1) DEFAULT 0 | added `2025_11_16_002403` |
| `scanned_conference` | tinyint(1) DEFAULT 0 | same migration |
| `created_at` / `updated_at` | nullable timestamps | no soft deletes anywhere |

Indexes: unique `email`, plus secondary indexes on `status`, `access_type`, and `email`. There is **no index on `scanned_fair`/`scanned_conference`** even though `AdminController` filters on them.

### `scans` (check-in records)

Created by `2024_01_01_000002`, mutated by `2025_11_23_151406`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `participant_id` | bigint unsigned FK → `participants.id` **ON DELETE CASCADE** | deleting a participant erases their scan history |
| `scan_type` | varchar(255) DEFAULT `'fair'` | added `2025_11_23_151406` for fair-vs-conference analytics |
| `scanned_at` | timestamp NOT NULL | app always supplies `now()`; cast to `datetime` |
| `scanner_user` | varchar(255) NULL | |
| `raw_payload` | longText NOT NULL | cast to `array` in the model, **but written as a plain string** |
| `created_at` / `updated_at` | timestamps | indexed on `participant_id` and `scanned_at` |

### `admins`

`id`; `name`; `email` UNIQUE; `email_verified_at`; `password` (cast `hashed`); `api_token` varchar(80) UNIQUE NULL (**legacy/dead** — Sanctum's `personal_access_tokens` is authoritative); `remember_token`; timestamps. `Admin` uses `HasApiTokens`, `$fillable = [name,email,password]`, hides `password`/`remember_token`/`api_token`.

### `personal_access_tokens`

Standard Sanctum table (`2019_12_14_000001`): polymorphic `tokenable`, `token` varchar(64) UNIQUE, `abilities`, `last_used_at`, `expires_at`.

### Eloquent model contracts (and their gaps)

- **`Participant`**: `$fillable` includes the QR fields and `status` but **omits `scanned_fair`/`scanned_conference`** (set via direct property assignment in `ScanController`). `$casts` has only `qr_payload => array` — the scan booleans are **not cast to `bool`**, so raw reads return tinyint `'0'/'1'`; helper methods (`hasScannedFair`, `hasScannedConference`, `getCurrentBadgeStatusColor`, `getBadgeStatusLabel`) compensate with explicit `(bool)` casts. Has `isAccepted/isPending/isRejected` but **no `isScanned()`** despite the enum value. `hasMany(Scan)`.
- **`Scan`**: `$fillable = [participant_id, scanned_at, scanner_user, raw_payload]` — **omits `scan_type`**, so it can never be mass-assigned. `$casts` has `raw_payload => array`, mismatching the string actually stored.
- **`Admin`**: as above.

### How the schema evolved through migrations

The migration timeline (4 base migrations — one dated 2019-12-14 for `personal_access_tokens` and three dated 2024-01-01 — then 8 incremental migrations dated 2025-11-10 → 2025-11-23) tells the story of a **product pivot**:

1. **2024-01 (MVP)** — participants with a single access axis (fair vs fair+conference), a 128-char QR token + JSON payload, simple `pending/accepted/rejected` workflow, scans table, admin auth.
2. **2025-11-10** — `qr_token` widened varchar(128) → TEXT (tokens outgrew 128 chars once they became signed blobs).
3. **2025-11-10** — `qr_image` added: render the QR PNG once at registration and store it (base64) for emails/badges.
4. **2025-11-11** — `company_name` added (B2B/exhibitor data).
5. **2025-11-13** — `access_type` consolidated from a legacy French set `{fair, both, foire}` to `{fair, 'fair + conference'}` (the `down()` of `2025_11_13_131223` reveals the prior live values).
6. **2025-11-13** — `gender` localized from French `{homme, femme, autre}` to lowercase English `{male, female, other}` (`2025_11_13_145854`).
7. **2025-11-14** — `'scanned'` added to the `status` enum… and then never used.
8. **2025-11-16** — the **real dual-event mechanism**: `scanned_fair`/`scanned_conference` booleans replace the idea of a single `'scanned'` status.
9. **2025-11-23** — `scans.scan_type` for analytics.

(That numbered list spans the 8 incremental migrations; the four base migrations are item 1's MVP foundation.)

**Critical lineage warning:** the base `create_participants_table` migration was **edited after the fact** — it declares `gender` as `['Male','Female','Other']` and `access_type` as `['fair','fair + conference']`, yet the 2025-11-13 change migrations' data-fix/`down()` logic assume the live data was French (`homme/femme/autre`, `foire/both`). This means **`migrate:fresh` produces a different intermediate history than production ever experienced**, and the migration files cannot be trusted to document the true schema lineage. The effective, authoritative deployed enums are:

- `gender` = `ENUM('male','female','other') NOT NULL`
- `access_type` = `ENUM('fair','fair + conference') NOT NULL`
- `status` = `ENUM('pending','accepted','rejected','scanned') DEFAULT 'pending'`

### Participant lifecycle as actually used

`status` only ever moves `pending → accepted` (registration hard-codes `'accepted'`) or `accepted/rejected` (admin actions). The `'scanned'` enum value is **dead** (no code writes it). Real entry tracking lives entirely in the two booleans; `AdminController` synthesizes **in-memory display pseudo-statuses** `'fair_scanned'`/`'conference_scanned'` that are never persisted.

---

## 4. Backend deep dive

### Routing & boot

`App\Providers\RouteServiceProvider::boot()` loads `routes/api.php` under the `api` middleware group with a hard-coded `api` URL prefix, so every route is reachable at `/api/...`. The `api` middleware group (`App\Http\Kernel`) is: Sanctum's `EnsureFrontendRequestsAreStateful`, `ThrottleRequests:api` (60/min keyed by user id or IP), `SubstituteBindings`, and a **no-op `InitializeSessionKeys`** (dead code). `config/auth.php` defines a `sanctum` guard over the `admins` provider, so `auth:sanctum` authenticates `App\Models\Admin`. The custom `Authenticate` middleware returns JSON `{message:"Unauthenticated."}` 401 (appropriate for an API).

### API endpoints

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/api/health` | none | DB ping via `DB::connection()->getPdo()`; returns `{status, db, timestamp}` (always HTTP 200) |
| POST | `/api/v1/register` | none · `throttle:5,1` | Public registration; auto-accepts; mints QR; emails participant |
| POST | `/api/v1/scan-fair` | **none** · `throttle:30,1` | Fair-gate scan (mislabeled "Protected") |
| POST | `/api/v1/scan-conference` | **none** · `throttle:30,1` | Conference-gate scan (mislabeled "Protected") |
| POST | `/api/v1/auth/admin/login` | none · `throttle:5,1` | Admin login; issues Sanctum token |
| POST | `/api/v1/auth/logout` | `auth:sanctum` | Revoke current token |
| GET | `/api/v1/auth/me` | `auth:sanctum` | Current admin id/name/email |
| GET | `/api/v1/admin/participants` | `auth:sanctum` | Filtered, paginated participant list |
| GET | `/api/v1/admin/dashboard` | `auth:sanctum` | KPIs + charts |
| POST | `/api/v1/admin/participants/{id}/accept` | `auth:sanctum` | Accept (regenerates QR, emails) |
| POST | `/api/v1/admin/participants/{id}/reject` | `auth:sanctum` | Reject (emails) |
| DELETE | `/api/v1/admin/participants/{id}` | `auth:sanctum` | Email-then-hard-delete |
| GET | `/api/v1/admin/participants/{id}/badge` | `auth:sanctum` | Stream PDF badge |
| GET | `/api/v1/admin/scans` | `auth:sanctum` | Latest 50 scans (participant eager-loaded) |
| GET | `/` (web.php) | none | Static JSON banner |

Per-route throttles: register 5/min, scans 30/min each, login 5/min — all additionally under the global 60/min `api` limiter. CORS (`config/cors.php`) scopes to `api/*`, single allowed origin from `FRONTEND_URL` (default `http://localhost:4200`), `supports_credentials=true`.

### Controllers

**`AuthController`** — `login()` validates email/password, looks up `Admin`, `Hash::check`s, logs failed attempts with IP, then **deletes all prior tokens** (`$admin->tokens()->delete()` — single active session) and issues a fresh `createToken('admin-token')`. Contains a stray, purposeless `session(['lottery' => []])` write. `logout()` deletes the current access token; `me()` returns the admin.

**`RegistrationController::register`** — validated by `RegisterParticipantRequest` (the **only** FormRequest in the app). Creates the `Participant` with `status => 'accepted'` **hard-coded** ("`// Always accepted`"), builds a QR payload, calls `QrCodeService::generateQrCode()`, writes the PNG to the `public` disk at `qrcodes/participant_{id}.png`, persists `qr_token`/`qr_payload`/`qr_image` (the **base64 string**, despite a comment claiming it stores a URL), and **synchronously** sends `ParticipantAccessMail` (try/catch → `email_sent` flag).

**`ScanController`** — `scanFair`/`scanConference` delegate to private `processScan($request, $type)`. It accepts either a `payload` (the token) or a `qr_image`; the **`qr_image` branch is acknowledged-broken dead code** (it feeds raw PNG base64 to `verifyQrCode`, which expects a token). The working path: verify HMAC → find `Participant` by decoded `id` **and** `email` → require `status === 'accepted'` (else 403) → for conference, require `access_type === 'fair + conference'` (else returns **`ok:true` 200** "not permitted") → reject duplicate via the boolean flags → inside a `DB::transaction` flip the matching flag → `Scan::create(participant_id, scanned_at, scanner_user, raw_payload=$qrToken)` **without `scan_type`**. It then **regenerates the QR** by calling `QrCodeService::generateQrCode($participant->qr_payload)` (redundant HMAC + PNG work on every successful scan, purely to attach a base64 QR) and **queues** `ParticipantAccessMail`. This scan-email block is wrapped in **its own try/catch** (a logged warning on failure), so a mail error does **not** 500 the scan — a small robustness contrast with the accept/reject paths (see §9). `getRecentScans()` returns the latest 50.

**`AdminController`** — `getParticipants()` paginates (20/page) with filters (status with special `fair_scanned`/`conference_scanned` branches, access_type, LIKE search over name/email/company), `withCount('scans')` + `withMax('scans','created_at')`, then overlays in-memory pseudo-statuses. `acceptParticipant`/`rejectParticipant` guard re-accept/re-reject (400), update status, regenerate QR (accept only), **synchronously** email. `deleteParticipant` emails a "deleted" notice then hard-deletes. `downloadBadge` delegates to `PdfBadgeService`. `getDashboardStats` runs ~11 aggregates plus a **fully mocked `system_health` block** (`disk_usage '12%'`, `api_response_time '45ms'`), approximates `email_success_rate` as a constant 99, and groups `daily_scans` by `scan_type` — which is always `'fair'` (see the bug below).

### QR + PDF + email services

**`QrCodeService`** — the heart of the credential system. `generateQrCode($payload)` JSON-encodes the payload `{id, uuid, email, access}`, computes `hash_hmac('sha256', $json, $secret)` (the secret from `config('app.qr_hmac_secret')` ← `env('QR_HMAC_SECRET')`; the constructor throws if empty), then produces a **JWT-shaped but not-a-JWT** token: `base64url(json) . '.' . base64url(hex_signature)`. It renders a 300×300 PNG via `QrCode::format('png')` and returns `['token', 'qr_image'(base64), 'payload']`.

How tokens are **validated**: `verifyQrCode($token)` splits on `'.'`, base64url-decodes both halves, recomputes the HMAC over the decoded JSON, and compares with **`hash_equals`** (constant-time — the one strong control in this path). Validity is decided **purely by the signature** — there is **no expiry, no nonce/jti, no replay check, and no comparison against the stored `qr_token`**. The payload's `uuid` is random per generation and never persisted or checked. The payload is **signed but not encrypted**, so anyone with a generic QR reader can read the participant's `id`, `email`, and `access`.

**`PdfBadgeService`** — `generateBadge` regenerates the QR PNG from `participant->qr_payload` (note: `qr_image` is never read by this path), renders `view('badge.participant')` to HTML, runs it through the raw `Dompdf` class (A4 portrait), and returns base64. `downloadBadge` streams it as `badge-{id}.pdf`. The badge Blade embeds the QR inline as a `data:image/png;base64` URI.

**`ParticipantAccessMail`** — a `Queueable` Mailable taking `(Participant, ?qrImageBase64, emailType)`. The subject is chosen via `match($emailType)`. The view (`emails/participant_access.blade.php`) embeds the QR inline via `$message->embedData(...)` (a CID attachment for reliable cross-client rendering), shown only for accepted participants. Triggered on registration (sync), accept (sync), reject (sync, no QR), delete (sync, before deletion), and scan (**queued**).

A standalone Artisan command `test:pdf-generation` smoke-tests badge generation against the first accepted participant (and misreports base64 length as "bytes").

---

## 5. Frontend deep dive

The frontend is a fully **standalone Angular 17** app (no NgModules), bootstrapped in `main.ts` via `bootstrapApplication(AppComponent, appConfig)`.

### Routing

All routes are **lazy** (`loadComponent`) in `app.routes.ts`:

- **Public:** `''`/`home` → `HomeComponent`, `register` → `RegisterComponent`, `badge` → `BadgeComponent`
- **Admin (guarded by `authGuard`, except login):** `admin/login` → `LoginComponent`, `admin/dashboard` → `DashboardComponent`, `admin/participants` → `AdminListComponent`, `admin/scanner` → `ScannerComponent`, `admin/scanner/conference` → `ScannerConferenceComponent`, `admin/avatar` → `AvatarPageComponent`, `admin/avatar/conference` → `AvatarConferenceComponent`
- `**` → redirect to home

`authGuard` is **client-side only**: it returns true iff `localStorage` holds an `admin_token` — **no signature or expiry validation**. `AppComponent` is a single inline-template/inline-CSS shell that, on every `NavigationEnd`, force-redirects authenticated users off public routes to `/admin/scanner`. Its navbar links to `/about` and `/contact`, which **have no routes** (dead nav).

### Services & state

- **`ApiService`** — central HTTP client. Base URL from `environment.apiUrl`. `getHeaders()` adds `Authorization: Bearer <admin_token>` when present. **Auth inconsistency:** `scanFair()`/`scanConference()` use `withCredentials:true` and send **no bearer token**; every other admin call uses the bearer and **no** credentials. A legacy `scan()` → `/scan` exists but is **unused**.
- **`AuthService`** — `BehaviorSubject<Admin|null>` exposed as `currentAdmin$`; stores `admin_token` + `admin_data` in `localStorage`; `isAuthenticated()` checks only token presence.
- **`ScanBroadcastService`** — owns **two isolated `BroadcastChannel`s** (`eventaccess_fair_scan`, `eventaccess_conference_scan`), each feeding an RxJS Subject. `broadcastFair/broadcastConference` post a `ScanResult`; `getFairScan$/getConferenceScan$` expose the streams. This is the **only** real-time mechanism.
- **`ScanStoreService`** — a `BehaviorSubject` documented as a "fallback for tabs opened after a scan." It is **imported nowhere** — pure dead code, so an avatar tab opened *after* a scan misses it (BroadcastChannel does not replay).
- **`app.config.ts`** calls `provideHttpClient(withInterceptorsFromDi())` but **registers no interceptor** — the pipeline is a no-op; there is no central 401 handling.

Environment config: `environment.ts` (dev) points at `http://localhost:8000`; `environment.prod.ts` uses relative `/api/v1`. But **`angular.json` has no `fileReplacements`**, so a production build never swaps in the prod file — `ApiService` imports `environment.ts` directly, meaning **a prod build ships the hardcoded localhost URL**.

### Feature components

**Scanners** (`ScannerComponent`, `ScannerConferenceComponent`) — near-verbatim duplicates. Each creates a `@zxing/library` `BrowserMultiFormatReader`, enumerates cameras, and on a decoded QR calls `processTextScan()` → POST `/scan-fair` or `/scan-conference`. They also support **manual paste** and **image-file upload** (FileReader → base64 → `qr_image`, though that backend branch is broken). On result they show participant details, play a WebAudio beep (800 Hz success / 400 Hz error), and **broadcast** the result on the matching channel. The only differences between the two: endpoint, channel, and heading text.

**Avatars / TTS** — there are **three** implementations:
- `SpeakerAvatarComponent` — a reusable, `@Input`-driven TTS avatar with robust voice loading. It is **orphaned** (referenced by no route or component).
- `AvatarPageComponent` (route `admin/avatar`) and `AvatarConferenceComponent` (route `admin/avatar/conference`) — the **actual kiosks**, each re-implementing TTS. Both require a one-time "Start System" click to satisfy browser autoplay policy, then subscribe to `getFairScan$`/`getConferenceScan$`. They generate the welcome string **client-side** (ignoring the backend's returned message), swap a silent video for a speaking one, and force a "female" voice by matching only `'samantha'`/`'zira'` voice names (which fails on most non-Apple/Windows platforms, falling back to the default voice). The **conference** variant adds a `determineAccessStatus()` branch: a `access_type === 'fair'` attendee is **denied** at the conference gate ("conference access is not permitted. You have fair-only access."). The fair variant has no such denial branch.

The **"conference" vs standard split** is intentional but minimal: the conference scanner/avatar differ only in endpoint, BroadcastChannel name, video filenames (`*_conf_avatar.mp4`, which appear byte-identical to the fair videos), and the extra fair-only-denied path. This duplication appears four times across the scanner/avatar pairs.

**Register** (`RegisterComponent`) — reactive form: `first_name`/`last_name`/`company_name` required (maxLength 255), `gender` (Male/Female/Other), `phone` (optional, pattern), `email`, and `access_type` driven by a `conferenceAccess` checkbox (`'fair + conference'` vs `'fair'`). On success it navigates to `/badge` passing the QR via router `state`.

**Badge** (`BadgeComponent`) — reads `history.state`; renders the base64 QR, offers `downloadQR()` and `printQR()` (which `document.write`s an unescaped HTML badge into a popup).

**Login** (`LoginComponent`) — reactive form calling `AuthService.login()`; the template **hard-codes the visible default credentials** `admin@example.com / admin123`.

**Admin list** (`AdminListComponent`) — server-side filtered/paginated table (name, email, company, gender, phone, access type, status badge, scan count, last-scan time, copyable truncated `qr_token`, QR thumbnail). Accept/Reject/Delete open a confirmation modal whose message is bound via **`[innerHTML]`** with interpolated participant names (a minor stored-XSS sink). Accept shows only for `pending`, Reject only for `accepted`. Badge PDF and QR PNG download.

**Dashboard** (`DashboardComponent`) — KPI cards plus three Chart.js charts (status doughnut, registrations line with daily/weekly toggle, stacked scan-volume bar). It **polls every 30s via `setInterval`** that is **never cleared** (no `ngOnDestroy`). Its scan chart keys the conference series on `scan_type === 'fair + conference'`, which never matches the backend's `'fair'`/`'conference'` vocabulary.

---

## 6. End-to-end flows

### (a) Participant registers → (auto-accept) → badge/QR emailed

1. **Browser:** On `/register`, `RegisterComponent`'s reactive form collects the participant data; the `conferenceAccess` checkbox sets `access_type`. Submit → `ApiService.register()` POSTs to `{apiUrl}/register`.
2. **API:** Global `api` middleware (CORS, Sanctum-stateful, throttle 60/min) + route `throttle:5,1`. `RegisterParticipantRequest` validates (`authorize()` returns true). `RegistrationController::register`:
   - Creates the `Participant` with **`status => 'accepted'`** (the documented pending→approval workflow is bypassed).
   - Builds payload `{id, uuid, email, access}` → `QrCodeService::generateQrCode()` → signed token + 300×300 PNG.
   - Writes the PNG to `public` disk `qrcodes/participant_{id}.png`.
   - Updates the row with `qr_token` (TEXT), `qr_payload` (JSON array), and `qr_image` (the **base64 PNG**, not a URL).
   - **Synchronously** sends `ParticipantAccessMail` with the QR embedded inline (via OVH SMTP), setting `email_sent`.
3. **DB:** A `participants` row exists with `status='accepted'`, both scan flags `0`.
4. **Browser:** On `ok`, the SPA navigates to `/badge` (via router `state`), rendering the QR with download/print.

> Because registration auto-accepts, the admin **accept/reject** endpoints are largely inert (`pending_approvals` is always 0; `acceptParticipant` short-circuits on an already-accepted row). Admins still moderate via reject/delete and can re-issue QR on accept.

### (b) On-site scan → validation → avatar welcome

1. **Browser (scanner tab, e.g. `/admin/scanner/conference`):** `ScannerConferenceComponent` decodes the QR with ZXing → `processTextScan(token)` → `ApiService.scanConference()` POSTs `{payload: token, scanner_user}` to `/scan-conference` with `withCredentials:true` (and **no auth** is enforced server-side).
2. **API:** `throttle:30,1`, **no `auth:sanctum`**. `ScanController::processScan(request, 'conference')`:
   - `QrCodeService::verifyQrCode(token)` → split on `'.'`, base64url-decode, recompute HMAC, `hash_equals`. (No expiry, no replay, no stored-token comparison.)
   - Loads `Participant` by decoded `id` **and** `email` (403 if mismatch, 404 if missing).
   - Requires `status === 'accepted'` (else 403).
   - **Conference gate:** requires `access_type === 'fair + conference'`; a fair-only attendee gets **`ok:true` 200** "not permitted" (semantically a denial returned as success).
   - Duplicate guard: if `scanned_conference` already true → `ok:true, is_already_scanned:true`.
   - Otherwise, inside a `DB::transaction`, sets `scanned_conference = true` and `save()`.
   - Creates a `Scan` row (`participant_id`, `scanned_at=now()`, `scanner_user`, `raw_payload=$qrToken`) **without `scan_type`** → the column defaults to `'fair'` even for this conference scan.
   - **Regenerates the QR** via `QrCodeService::generateQrCode($participant->qr_payload)` (redundant per-scan HMAC + PNG render) and **queues** `ParticipantAccessMail` (`emailType='conference'`), all inside an inner try/catch so a mail failure logs a warning rather than failing the scan.
   - Returns participant subset + `scan_id` + welcome message.
3. **DB:** `participants.scanned_conference = 1`; a new `scans` row with `scan_type = 'fair'` (the analytics bug).
4. **Browser (scanner tab):** `handleScanResult()` shows the result, beeps, and calls `scanBroadcast.broadcastConference(...)` → `postMessage` on `eventaccess_conference_scan`.
5. **Browser (avatar tab, `/admin/avatar/conference`, same browser/machine):** `AvatarConferenceComponent` (after "Start System" was clicked) receives the message via `getConferenceScan$()` → `determineAccessStatus()` (fair-only ⇒ Denied) → `speak()` via Web Speech API → swaps silent→speaking video. If the avatar tab was opened *after* the scan, the message is lost (no replay; the `ScanStoreService` fallback is dead code).

The fair flow (a) is identical except it uses `/scan-fair`, the `eventaccess_fair_scan` channel, `AvatarPageComponent`, and has no access-tier denial branch.

---

## 7. Infrastructure & deployment

### docker-compose (`docker-compose.yml`, schema 3.8)

Four services on one bridge network `eventaccess_network`, with a named volume `mysql_data`:

1. **`mysql`** (`mysql:8.0`) — `3306:3306`; `MYSQL_DATABASE=event_access`, root password `secret`, user `eventaccess`/`secret`.
2. **`backend`** (built) — `8000:8000`; bind-mounts `./backend:/var/www/html` with anonymous `vendor`/`node_modules` volumes; env sets `APP_ENV=local`, `APP_DEBUG=true`, `DB_HOST=mysql`, and a **full block of real OVH SMTP credentials** (`smtp.mail.ovh.net:587`, `no-reply-eventaccess@futuris.co`). `depends_on: [mysql, mailhog]`.
3. **`frontend`** (built) — `4200:4200`; bind-mounts `./frontend:/app`; `depends_on: [backend]`.
4. **`mailhog`** (`mailhog/mailhog:latest`) — `1025` (SMTP) + `8025` (web UI).

### Dockerfiles

- **`backend/Dockerfile`** — single-stage `php:8.2-fpm`; installs gd/pdo_mysql/mbstring/exif/pcntl/bcmath/zip and **imagick** (via PECL); `composer install --no-dev --optimize-autoloader`; `php artisan key:generate` (bakes an APP_KEY into the image layer); **`chmod -R 777 storage/logs`**; runs **`php artisan serve`** (the dev server), **not** php-fpm. So the FPM toolchain is dead weight and there is no nginx/apache.
- **`frontend/Dockerfile`** — single-stage `node:20-alpine`; `npm install --legacy-peer-deps`; runs **`ng serve`** (the Angular dev server). No multi-stage build, no compiled `dist` served by a static server, no nginx.
- **`backend/Dockerfile.setup`** — throwaway scaffolding image; not wired into compose.

Because the backend bind-mounts host source over `/var/www/html` (plus anonymous `vendor`/`node_modules`), the **careful `--no-dev` build is effectively unused in dev** — the running container uses host files.

### CI (`.github/workflows/ci.yml`, "CI/CD Pipeline")

Triggered on push/PR to `main`/`develop`. Jobs: **backend-tests** (MySQL service, `.env` from example with sed rewrites, `composer install`, `migrate --force`, `php artisan test --coverage`, Codecov upload of `coverage.xml`); **frontend-tests** (`npm ci`, `npm run lint || true`, `npm run test:ci || true`, `npm run build`); **e2e-tests** (`docker-compose up -d`, `sleep 30`, wait on `/api/health` + `:4200`, `npx cypress run`); **docker-build** (buildx, `push:false`); **code-quality** (phpcs PSR12 + phpstan level 5, both `|| true`); **security-scan** (Trivy → SARIF). The **`deploy` job is a placeholder** — only `echo "Deployment would happen here"`.

### Dev vs prod

**Dev** is the only thing that actually exists: `make up` (= `docker-compose up --build -d`), `make seed` (= `migrate:fresh --seed`), app at `:4200`/`:8000`, MailHog UI at `:8025`. The **Make/doc surface is inconsistent** with reality: `make migrate` doesn't exist (only `db-migrate`/`db-fresh`/`seed`), and several docs reference a nonexistent `setup-backend-dirs.ps1` and `docker-compose.prod.yml`.

**Prod is effectively unimplemented:** no `docker-compose.prod.yml`, no nginx, no production image build, no registry push, and the CI deploy step is a stub — despite README/PROJECT_SUMMARY/architecture/MANUAL_INSTALLATION all promising "nginx in production."

A consequential infra contradiction: **MailHog is wired in but bypassed.** `docker-compose.yml` and the committed `backend/.env` point `MAIL_HOST` at the **real OVH SMTP server**, while `.env.example` and the docs assume `mailhog:1025`. As configured, **local development sends real emails through the production OVH account.**

---

## 8. Project status & tests

**Maturity verdict: early-prototype / MVP-in-flux — not production-ready.** The codebase has clearly evolved months past the frozen "1.0.0 / January 7, 2025 / 95%" snapshot that all the prose describes (the migrations are timestamped Nov 2025). The documentation is **comprehensive in volume but substantially untrue in its specifics** — classic generated-doc bloat written once and never reconciled.

### What's tested vs not

- **Backend:** Only `tests/Feature/RegistrationTest.php` (6 tests) is substantive, and it is **stale and currently failing**: it asserts `status => 'pending'` (the controller now writes `'accepted'`), posts capitalized gender and `access_type` `'both'`/`'foire'` (invalid against the current enums), and omits the now-required `company_name`. `tests/Feature/AdminTest.php` is the **untouched Laravel stub** (`test_example()` GETs `/` and expects 200 — meaningless in an API-only app). There are **no unit tests** despite `phpunit.xml` declaring a `tests/Unit` suite that doesn't exist. The committed **`.phpunit.result.cache` is forensic evidence the last run errored** (defect code 8 on `test_registration_prevents_duplicate_email`).
- **Frontend:** Two Cypress E2E specs (`registration.cy.ts`, `admin.cy.ts`) that require a fully running stack + seeded admin and use **stale enum values**. **No Jasmine/Karma unit tests exist** despite `package.json` wiring them up and `deliverable-report.json` claiming they are "configured."
- **CI gives a false green:** the frontend job calls `npm run test:ci` (no such script) and `npm run lint` (no lint config), both masked by `|| true`. phpcs/phpstan are likewise non-blocking. Codecov uploads a `coverage.xml` that `phpunit.xml` never produces.

### Doc-vs-code discrepancies (the headline ones)

- **README claims "Laravel 11"**; `composer.lock` pins **v10.49.1** (every other doc correctly says Laravel 10).
- **"95% production ready / Tested and verified / 100% of features"** is marketing, contradicted by the failing tests and enum-invalid fixtures.
- **OpenAPI spec is wrong:** documents a single `POST /scan` (doesn't exist; real routes are `/scan-fair` and `/scan-conference`), omits `/admin/dashboard`, `DELETE /admin/participants/{id}`, `/badge`, and `/admin/scans`, and uses malformed `$ref`s (`admin/loginRequest`).
- **The documented manual-approval workflow no longer exists** (auto-accept).
- **Enum vocabularies disagree across four layers:** docs/OpenAPI/Cypress use `{foire, conference, both}` and capitalized gender; the DB + validator use `{fair, 'fair + conference'}` and lowercase gender; the factory uses `{fair, conference, both}` — **no two layers agree**.
- The `architecture.md` ERD omits `company_name`, `qr_image`, `scanned_fair`, `scanned_conference`, and the `'scanned'` status, and the badge-PDF / dashboard / delete features are entirely undocumented.
- `DOCUMENTATION_INDEX.md` lists `backend/README.md` and `frontend/README.md` that don't exist; `PROJECT_SUMMARY.md` still contains the original author's Windows path `C:/Users/aidou/Downloads/eventaccess`.

---

## 9. Security & quality findings

Prioritized; the first two are **critical and exploitable today**.

| Severity | Issue | Location | Why it matters |
|---|---|---|---|
| **CRITICAL** | **Real secrets committed to git & pushed to GitHub** — `backend/.env` (tracked, no root `.gitignore`) exposes `APP_KEY`, `DB` root/`secret`, and **live OVH SMTP creds** (`no-reply-eventaccess@futuris.co` / `eventaccessmailpwd`); same creds hardcoded in `docker-compose.yml`. | `backend/.env`; `docker-compose.yml:40-47` | Anyone with repo access can send mail as the org and forge signed data with the APP_KEY. **Rotate everything, `git rm --cached`, add `.gitignore`, purge history.** |
| **CRITICAL** | **QR_HMAC_SECRET is the public placeholder** (`'your-very-long-random-secret-key-…'`, identical to `.env.example`). | `QrCodeService.php:14-19`; `.env:62` | The QR signing key is public, so **anyone can forge a valid access token** for any `{id,email,access}`. Combined with public scan endpoints, no auth is needed to exploit. |
| **CRITICAL** | **Scan endpoints are completely unauthenticated** — `/scan-fair` and `/scan-conference` sit outside `auth:sanctum` (only `throttle:30,1`), despite the "🔐 Protected" comment and the frontend's `withCredentials`. | `routes/api.php:17-22` | Any anonymous client can mark participants scanned, grant access, trigger emails, and **enumerate participant PII** (name, company, email, gender, access_type) in scan responses. |
| **CRITICAL/HIGH** | **Gender enum mismatch breaks `/register` on a fresh DB** — the validator allows `'Male'`/`'Female'`/`'Other'` and the controller inserts `$request->gender` (via the `Participant::create()` array), but the deployed enum is lowercase; under strict mode this throws "Data truncated." | `RegisterParticipantRequest.php:28` (gender rule); `RegistrationController.php:30-39` (create array) | The public registration endpoint **fails on a fully-migrated database**. |
| **HIGH** | **`scan_type` never persisted** — `Scan::create()` omits it and `$fillable` excludes it, so every scan (incl. conference) stores the default `'fair'`. | `ScanController.php:129-134`; `Scan.php:18-23` | The dashboard's fair-vs-conference analytics are **permanently wrong** (all volume attributed to fair). |
| **HIGH** | **Default seeded admin `admin@example.com / admin123`** — and these credentials are **printed in the login UI** and multiple docs. | `AdminSeeder.php:16-21`; `login.component.html` | Trivially guessable full-admin access if seeders run in any deployed env. |
| **HIGH** | **QR tokens never expire, have no replay protection, and aren't compared to the stored token.** | `QrCodeService.php:58-108`; `ScanController.php:48-75` | A photographed/intercepted badge is a **permanent, cloneable bearer credential**; a single QR can't be revoked without changing the global secret (invalidating everyone). |
| **HIGH** | **Registration auto-accepts everyone + emails a working QR** to any submitted address (throttle 5/min). | `RegistrationController.php:38` | Enumeration (email-already-registered leak), spam, and mail abuse through the org SMTP. |
| **HIGH** | **No real deployment + dev servers as the only Docker config.** | `ci.yml` deploy stub; `frontend/Dockerfile`; `backend/Dockerfile` | "Production" is unimplemented; running `ng serve`/`artisan serve` in prod would be slow and insecure. |
| **HIGH** | **Prod build ships hardcoded `localhost:8000`** (no `fileReplacements` in `angular.json`). | `angular.json`; `api.service.ts:22` | A production Angular build calls localhost instead of the relative `/api/v1`. |
| **MEDIUM** | `APP_DEBUG=true`/`APP_ENV=local` committed | `.env:2-4` | Full stack-trace / env disclosure if deployed. |
| **MEDIUM** | Sanctum `expiration => null` — admin tokens never expire | `config/sanctum.php:47` | A leaked token grants indefinite admin access. |
| **MEDIUM** | No authorization policies/gates — any admin can do anything | `AuthServiceProvider.php` | Zero defense-in-depth / audit segregation. |
| **MEDIUM** | `TrustProxies::$proxies = null` while all X-Forwarded headers enabled | `TrustProxies.php` | Behind a load balancer, client IP/proto are wrong → rate-limit keys collapse to one bucket, weakening brute-force protection. |
| **MEDIUM** | Conference-denied returns `ok:true` 200 | `ScanController.php:88-96` | A denial is easily misread by the frontend as a successful admit. |
| **MEDIUM** | Synchronous email on register/accept/reject — register has its own try/catch, but accept/reject do **not** | `RegistrationController.php:65-76`; `AdminController.php:104-106,150-152` | Slow/failing SMTP delays requests; accept/reject can return 500 after the DB already committed → "updated but reported failed." (By contrast, the scan path queues mail inside its own try/catch — `ScanController.php:137-154` — so it degrades gracefully.) |
| **MEDIUM** | `qr_image` stores base64 but is treated as a URL; scan-by-image branch is acknowledged-broken dead code | `RegistrationController.php:54-61`; `ScanController.php:34-57` | Latent correctness landmine; image-based scanning cannot work. |
| **MEDIUM** | Every successful scan **regenerates the QR** (HMAC + PNG render) just to attach it to the queued email | `ScanController.php:139` | Redundant CPU per scan; the already-stored `qr_image` could be reused. Minor, but wasteful at gate throughput. |
| **MEDIUM** | PNG QR depends on `ext-imagick`, not declared in `composer.json require` | `QrCodeService.php:40-43` | Install can pass but QR render fails at runtime, breaking registration/badge/scan-email. |
| **MEDIUM** | Dashboard polling `setInterval` never cleared | `dashboard.component.ts` | Authenticated requests fire forever after navigating away. |
| **MEDIUM** | Committed `backend/test_mail.php` ad-hoc script | `backend/test_mail.php` | Mail/data-probe abuse if web-reachable. |
| **LOW** | `raw_payload` cast `array` but a plain string is stored | `Scan.php:30-33` | The column is effectively unreadable (decodes to null). |
| **LOW** | `[innerHTML]` modal + `printQR` document.write inject unescaped participant names | `admin-list.component.html:170`; `badge.component.ts` | Stored-XSS-adjacent sinks; names should be text-interpolated. |
| **LOW** | Health endpoint returns 200 even when DB is down | `HealthController.php:13-28` | LBs treat an unhealthy instance as healthy. |
| **LOW** | `base64UrlDecode` padding math wrong; redundant indexes; asymmetric `qr_token` `down()`; dead `admins.api_token`; `'lottery'`/`InitializeSessionKeys` dead code; mocked dashboard `system_health`; `chmod 777 storage/logs`; empty `backend/.dockerignore` lets `.env`/`.git` into the image; `mailhog:latest` unpinned | various | Tech debt / minor exposure; individually low-impact. |

---

## 10. Strengths, weaknesses & recommendations

### Strengths

- **The core happy path is coherent and well-factored.** Registration → signed QR → email → scan → avatar is a complete, sensible loop with clean service boundaries (`QrCodeService`, `PdfBadgeService`, `ParticipantAccessMail`).
- **Some real security hygiene exists:** passwords are `hashed` and `Hash::check`ed with IP-logged failures; QR signatures use constant-time `hash_equals`; login revokes prior tokens (single active session); CSRF is correctly excluded for the token API; queries use parameter-bound Eloquent (no SQL injection found); mass assignment is bounded by `$fillable`.
- **Modern, clean frontend foundation:** fully standalone Angular 17, lazy routes, multi-modal scanning (camera/manual/file), and a genuinely clever same-origin BroadcastChannel design for decoupling scanner and kiosk screens.
- **Sensible per-route rate limiting** on the sensitive endpoints.
- **The dual fair/conference access model is correctly enforced in code** (conference requires the upgraded tier; double-entry is blocked per gate).
- **The scan-email path degrades gracefully** — mail is queued inside its own try/catch, so SMTP problems never fail a gate scan (a robustness pattern the accept/reject paths lack).

### Weaknesses

- **Committed live secrets and a public QR signing key** — the single most damaging issue; the entire QR security model is void while the placeholder secret stands.
- **Unauthenticated scan endpoints** — a real access-control hole, not a docs nit.
- **Pervasive schema/code drift:** the gender-enum mismatch breaks registration on a fresh DB; `scan_type` is never written; seeders/factories carry invalid enum values; the migration files don't honestly reflect production lineage.
- **Documentation is largely fiction** relative to the code (wrong framework version, wrong endpoints, wrong workflow, wrong enums, undocumented features), which actively misleads anyone onboarding.
- **No working tests and no real deployment story** — CI is a green light with the bulb removed; "production" infra doesn't exist; QR tokens never expire and can't be revoked.
- **Significant dead/duplicated code:** orphaned `SpeakerAvatarComponent`, dead `ScanStoreService`, broken scan-by-image branch, four-way scanner/avatar duplication, and the unused FPM toolchain.

### Prioritized to-do

**P0 — do immediately (security incident):**
1. **Rotate every secret** (APP_KEY, DB password, OVH mail password, QR secret), `git rm --cached backend/.env`, add a proper root `.gitignore`, and **purge `.env` from git history** (`git filter-repo`).
2. **Set a strong random `QR_HMAC_SECRET`** out of source (rotating it re-issues all QRs).
3. **Put `/scan-fair` and `/scan-conference` behind auth** (a scanner/device token or `auth:sanctum`), or consciously document them as public kiosk endpoints with compensating controls.
4. **Remove the default admin from any deployed seed** (env-driven credentials) and **delete the credentials from the login UI** and `test_mail.php`.

**P1 — make it actually run correctly:**
5. **Fix the gender enum mismatch** (lowercase the validator/factory, or store capitalized) so `/register` works on a fresh DB.
6. **Persist `scan_type`** (add to `Scan::$fillable` and pass it in `processScan`) so analytics are correct.
7. **Add `fileReplacements` to `angular.json`** so prod builds use the relative API URL.
8. **Make the dashboard `setInterval` clearable** (`ngOnDestroy`) and align its scan-chart vocabulary with `'fair'`/`'conference'`.

**P2 — harden & tidy:**
9. Add **token expiry + a nonce/jti** (or device binding) to QR validation; consider comparing against the stored `qr_token` for revocability.
10. Set Sanctum `expiration`, fix `TrustProxies` for the real proxy, return **503** from `/health` on DB failure, and make the conference-denied response a **403 `ok:false`**.
11. Queue all emails (and wrap accept/reject mail in try/catch, mirroring the scan path); declare `ext-imagick` in `composer.json`; reuse the stored `qr_image` instead of regenerating the QR on every scan.
12. **Reconcile the documentation with the code** (framework version, endpoints, enums, the auto-accept workflow, ERD) or delete the stale docs — and **make CI gates blocking** (remove `|| true`, add the missing `test:ci`/lint scripts, fix the failing `RegistrationTest`, write at least smoke unit tests).
13. Build a real **production deployment** (multi-stage Angular build served by nginx; nginx+php-fpm for the backend; a `docker-compose.prod.yml`; a real CI deploy step) before any production use.
14. Remove dead code (`ScanStoreService`, `SpeakerAvatarComponent`, the broken scan-by-image branch, `'lottery'`/`InitializeSessionKeys`, `admins.api_token`) and deduplicate the four scanner/avatar variants behind a shared base or `@Input` mode.