# Correction & Rebuild Plan — EventAccess → Marketplace Ticketing SaaS

**Date:** 2026-06-12 · **Inputs:** [AUDIT.md](AUDIT.md) (78 verified findings) + product clarification (multi-tenant marketplace: futuris sells to organizers; organizers manage events/tickets; sell/share to their public — marketplace money flow, per-ticket commission, hybrid onboarding, both storefronts).

## Guiding principle

The audit's worst items (free tickets, no order visibility, cross-event admit, participant-overwrite, "single admin") are **not isolated bugs — they are symptoms of a single-tenant app being used as a multi-tenant marketplace.** So the plan has two tracks:

1. **Track A — Stabilize now:** neutralize live abuse/security risks on the deployed app + cheap fixes that survive the rebuild. Kept deliberately minimal — we do **not** gold-plate code that the rebuild replaces.
2. **Phases 0–5 — Rebuild into multi-tenant marketplace:** each phase structurally resolves a *cluster* of audit findings instead of patching them one by one.

> **Do not take real payments or run a public paid event until Track A + Phases 0–3 are done.** Today the live app gives away free tickets, oversells, and admits at the wrong event.

---

## Track A — Stabilize the live app (now, ~2–4 days)

Only things that are (a) live risks or (b) cheap and carry over unchanged.

| # | Fix | Audit ref |
|---|---|---|
| A1 | **Disable the public purchase/confirm/webhook routes in prod** until the real gateway exists (kills the free-ticket exploit) | C1, C2 |
| A2 | **Replace `php artisan serve` with php-fpm + nginx** (needed regardless of tenancy) | C3 |
| A3 | **Auth the scan endpoints + strip PII** (email/company/gender) from scan responses | H1 |
| A4 | **Remove `admin123` from the login UI**; set a strong admin password via env; set a finite Sanctum token expiry | H2 (partial) |
| A5 | Set a real `QR_HMAC_SECRET` in Railway + **reject the placeholder at boot**; `APP_ENV=production`, `APP_DEBUG=false` | H3 (partial) |
| A6 | Carry-over bug fixes: avatar honorific case-insensitive (H12); restore local-dev `environment.ts` + `angular.json` fileReplacements (H13); persist `scan_type` + fix FE chart key (H15); `HealthController` → 503 on DB down; declare `ext-imagick` | H12, H13, H15, +M/L |
| A7 | Repo hygiene: root `.gitignore` + `git rm --cached vendor node_modules`; populate `backend/.dockerignore` | H18 |
| A8 | Move `migrate --seed` out of the boot `CMD` into a one-shot release step; add `config:cache`/`route:cache` | M-Ops |

**Done when:** the deployed app no longer issues free tickets, survives concurrency, scans are authenticated, and no secrets sit in the UI or repo.

---

## Phase 0 — Multi-tenant foundation

**Goal:** introduce organizers (tenants), the user/role model, and hard data isolation.
**Build:** `organizers` table (status pending→approved→active→suspended); `users` table (`organizer_id` nullable for platform staff, `role`: superadmin/owner/admin/staff); separate auth guards; `organizer_id` on events/orders/scans; **global tenant query-scope + middleware**; migrate the seeded admin → platform super-admin; convert the existing demo data into one demo organizer.
**Resolves:** H2 (real accounts replace the single seeded admin); the foundation H1/H4/H10/H17 depend on.
**Done when:** Org A can never read Org B's data; the super-admin can cross tenants; existing flows still work under a default organizer.

## Phase 1 — Organizer console (hybrid onboarding + RBAC)

**Goal:** organizers self-sign-up (with your approval) and manage their events in a real UI.
**Build:** signup + email verification + approval workflow; organizer dashboard; event CRUD scoped to the organizer; staff/scanner invites + role enforcement; organizer order/attendee lists + nav.
**Resolves:** C4 & H17 (the feature becomes reachable via a real console + nav), H10 (organizer order/sales visibility), the "admin visibility" missing-feature gaps.
**Done when:** an approved organizer creates an event end-to-end in the UI, invites a scanner, and isolation holds.

## Phase 2 — Ticketing correctness (the data-model fix)

**Goal:** a proper ticket model that replaces the overloaded `participant`.
**Build:** `ticket_types` per event (name/price/**inventory**/sales window) — **replaces the global `fair / fair + conference` enum**; `order_items`; **`tickets`** (one row per sold seat; QR with `exp`+`jti`, revocable, status); organizer-and-event-scoped scanning; per-ticket check-in state.
**Resolves (a whole cluster):** H3 (revocable, expiring QR), H4 (event-scoped gate), H5 (no participant overwrite on re-buy), H6 (per-ticket scan state, no stale flags), H7 (buy N → issue N), H8 (capacity/inventory enforced + sold-out), per-event ticket types, `event_id` on scans, `raw_payload`, conference-denied-returns-200.
**Done when:** buying N yields N tickets; capacity is enforced; a ticket admits **only** at its event/organizer; re-buy never clobbers; tickets are revocable.

## Phase 3 — Payments & payouts (marketplace)  ⚠ gated on gateway choice

**Goal:** real money — collect, take commission, pay organizers out.
**Build:** Tunisian gateway integration (Konnect / Flouci / Paymee / Clictopay — **decision needed**) behind the existing `PaymentGateway` seam; **signature-verified webhook** (properly fixes C2); order → PAID only on a **verified charge** (fixes C1); `platform_fee` per order (your commission); **payout ledger** + organizer balances + payout runs; refund/cancel → void ticket + reverse credit; real transactional mailer + queue worker.
**Resolves:** C1, C2, payment-failure UX, refund/cancellation, no-real-email (H11 + missing-feature), partial ticket-retrieval.
**Done when:** a real charge issues a ticket, you keep your commission, the organizer's balance accrues, a refund voids the ticket, and the webhook rejects unsigned calls.

## Phase 4 — Storefront, marketplace & attendee experience

**Goal:** discovery + branded storefronts + attendee self-service.
**Build:** shared marketplace `/discover`; per-organizer **branded storefront** (`/o/{slug}` now, subdomains/custom domains later); polished checkout with **order summary/total/review** (fixes H16); attendee **"my tickets" via magic link** (fixes H9); shareable event links; **server-pushed scanner→avatar channel** (fixes H14); accessibility + i18n pass.
**Resolves:** C4 (public side), H16, H9, H14, and the UX/accessibility cluster.
**Done when:** an attendee discovers an event (marketplace or organizer page), buys with no account, receives + retrieves their ticket; the organizer can share a link.

## Phase 5 — Platform control plane & go-live hardening

**Goal:** your operations console + final hardening.
**Build:** super-admin console (approve/suspend organizers, set commission, see all sales, manage payouts/refunds/disputes); authorization policies/roles + audit log; order state-machine guards; event lifecycle (stable slug on rename, block past-event purchase, soft-deletes, unpublish semantics); real CI gate; security headers.
**Resolves:** platform-admin gap + the remaining medium/low audit cluster (§5 of the audit).
**Done when:** you can operate the marketplace; the audit's P2 hardening is complete.

---

## Coverage matrix — every critical/high is accounted for

| Audit | Addressed in |
|---|---|
| C1 free-ticket confirm | A1 (interim) → **Phase 3** |
| C2 unsigned webhook | A1 (interim) → **Phase 3** |
| C3 `artisan serve` | **Track A2** |
| C4 feature unreachable | **Phase 1** (admin) + **Phase 4** (public) |
| H1 scan unauth + PII | **Track A3** → tightened in Phase 0/2 |
| H2 weak printed admin | A4 → **Phase 0** (real accounts) |
| H3 QR secret/expiry/revoke | A5 → **Phase 2** (exp/jti/revoke) |
| H4 not event-scoped gate | **Phase 2** |
| H5 participant overwrite | **Phase 2** |
| H6 per-person scan flags | **Phase 2** |
| H7 quantity billed≠issued | **Phase 2** |
| H8 capacity unenforced | **Phase 2** |
| H9 no ticket retrieval | **Phase 4** (magic link) |
| H10 no order visibility | **Phase 1** (organizer) + Phase 5 (platform) |
| H11 sync queue/no worker | **Phase 3** (queue+worker+mailer) |
| H12 avatar honorific | **Track A6** |
| H13 env files swapped | **Track A6** |
| H14 broadcastchannel realtime | **Phase 4** |
| H15 scan_type not persisted | **Track A6** |
| H16 purchase UX | **Phase 4** |
| H17 admin events no nav | **Phase 1** |
| H18 committed vendor/node_modules | **Track A7** |
| Medium/Low clusters (§5) | folded into the phase that owns the subsystem; ops/hygiene in Track A + Phase 5 |

---

## Sequencing, milestones & dependencies

- **Track A** runs immediately and in parallel (stabilize the live app).
- **Core path:** Phase 0 → 1 → 2 (0 unblocks everything; 2 needs 0/1's data model).
- **Phase 3** can be *built against the stub interface now* and switched to the real gateway once procured — so gateway procurement doesn't block coding.
- **Phases 2 and 4** can partly overlap.

**Suggested milestones:**
- **M1 (safe + isolated):** Track A + Phase 0 + Phase 1 → approved organizers manage events in an isolated, safe app. *No real money yet.*
- **M2 (correct ticketing):** + Phase 2 → tickets/inventory/scanning are correct.
- **M3 (revenue):** + Phase 3 → real charges, commission, payouts.
- **M4 (public launch):** + Phase 4 + Phase 5 → marketplace + storefronts + control plane; production-hardened.

## Outstanding decisions (block specific phases)

1. **Payment gateway** (Konnect / Flouci / Paymee / Clictopay) — blocks Phase 3 go-live (not the coding).
2. **Storefront addressing** — subdomain vs `/o/{slug}` path for organizers (Phase 4).
3. **Data migration** — convert the current demo data to a demo organizer, or start the schema fresh (Phase 0).
