# AI Prompts Log

**Project:** Mike's Invoicing App
**Document purpose:** A transparent record of how AI assistance (Claude Code) was used to build this application, for client review.

---

## 1. Project Name & Purpose

**Mike's Invoicing App** is a single-tenant, server-rendered invoicing application built for one business to manage customers, products, invoices, recurring billing, and online payments.

Core flow: a business owner creates **customers** and **products**, builds an **invoice** from line items, sends it by **email and/or SMS** with a secure portal link, the customer pays through a **Square** payment link on the public **portal**, Square notifies the app via **webhook**, and the app marks the invoice paid, generates a **receipt**, and emails it automatically. Invoices can also be scheduled to **recur** (weekly/monthly/yearly/custom), and the entire business record (invoice status, portal access, payment activity, delivery failures) is captured in an **event log** visible on a **dashboard**. A token-authenticated **external API** exposes the same core operations for integration with other systems.

The application was built from the client's own specification document, `Laravel_Invoicing_App_Claude_Code_Implementation_Guide.docx`, which laid out 19 build phases and explicitly instructed that the app be built **phase by phase with Claude Code, not generated in one prompt**. This document is the record of that process.

---

## 2. Technology Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 (PHP 8.3+ required; developed on PHP 8.5.1) |
| Database | MySQL (application and test database both — see [§5](#5-key-architectural-decisions)) |
| Frontend | Blade templates + Alpine.js (no SPA framework, no Livewire) |
| Auth | Laravel Breeze (email/password only) |
| PDF generation | `barryvdh/laravel-dompdf` (invoices and receipts) |
| Payments | Square PHP SDK v45.1 (Fern-generated client), Square-hosted payment links |
| Email | Laravel Mail (Markdown mailables), queued |
| SMS | Twilio PHP SDK v8.11.6, behind a provider-agnostic interface |
| API auth | Custom bearer-token authentication (SHA-256 hashed tokens, not Sanctum) |
| Queues | Database queue driver in production, `sync` in tests |
| Scheduling | Laravel scheduler + cron, Supervisor-managed queue workers |
| Deployment target | VPS / DigitalOcean-style server — Nginx, PHP-FPM, Certbot, Supervisor |

**Codebase size at completion:** 29 controllers, 20 services, 13 models, 6 jobs, 6 policies, 17 migrations, 64 Blade views, 42 test files, **297 tests / 717 assertions, all passing**.

---

## 3. A Note on How This Log Was Compiled

Phases 1–9 (architecture through the customer portal) took place earlier in this project's Claude Code session, before the window covered by this project's detailed conversation record. Their entries below are compiled from the project's own artifacts — `docs/ARCHITECTURE.md`, the delivered code, and the project's stack-decision notes — rather than from a verbatim transcript of the original prompts. They are accurate summaries of what was requested and built, not word-for-word quotes.

Phases 10–19 are quoted **verbatim** from the actual prompts given during this project, exactly as written by the client/developer directing the build.

This distinction is made explicit here rather than presenting a reconstruction as something it isn't — in keeping with the purpose of this document.

---

## 4. Development Log

### Phase 1 — Architecture & Planning
**Requirements (summarized):** Analyze the client's specification document and produce a written plan — folder structure, database schema, model relationships, service boundaries, event-logging design, and route sketches — without writing any application code, pending approval.

**What it generated:** The initial `docs/ARCHITECTURE.md`: proposed database schema (company_settings, customers, products, invoices, invoice_items, recurring_invoice_profiles, payment_links, payments, receipts, event_logs, api_tokens, message_deliveries), model relationship map, enum design, service-layer boundaries, and a high-level plan for Square/email/SMS/webhook/API flows. No code.

### Phase 2 — Project Foundation
**Requirements (summarized):** Stand up the Laravel project and lock in stack decisions: Blade + Alpine (not Livewire), Breeze email/password auth, a provider-agnostic SMS interface for Twilio, single-tenant Square integration.

**What it generated:** Fresh Laravel 13 install, Breeze auth scaffolding, base application layout, `.env`/`.env.example` skeleton, and initial `config/` files for Square, SMS, invoicing, and the portal.

### Phase 3 — Database & Models
**Requirements (summarized):** Build out the core schema and Eloquent models with proper relationships, casts, and scopes.

**What it generated:** Migrations for all core tables, Eloquent models (`CompanySetting`, `Customer`, `Product`, `Invoice`, `InvoiceItem`, `Payment`, `Receipt`, `EventLog`, etc.) using attribute-based conventions (`#[Fillable]`, `#[Hidden]`, `#[UsePolicy]`), and enums (`InvoiceStatus`, `PaymentStatus`, `PaymentLinkStatus`, `DeliveryChannel`, `DeliveryStatus`, `RecurringFrequency`, `EventType`).

### Phase 4 — Company Settings & Branding
**Requirements (summarized):** Business-level settings (logo, brand color, receipt footer, owner notification preferences) and shared layout partials reused across invoice, portal, receipt, and email views.

**What it generated:** `CompanySettingsController`/settings service, logo upload handling, and shared Blade letterhead/branding partials used by invoice PDFs, the portal, receipts, and outgoing emails.

### Phase 5 — Customers & Products
**Requirements (summarized):** Full CRUD for customers and products, including CSV import for products.

**What it generated:** `CustomerController`/`CustomerService`, `ProductController`/`ProductService`, matching policies, search/filter/pagination, active/inactive toggling, and CSV product import.

### Phase 6 — Invoices
**Requirements (summarized):** Draft invoice creation with line items, server-recalculated totals, status lifecycle, and sequential invoice numbering.

**What it generated:** `InvoiceController`/`InvoiceService`/`InvoicePolicy`, `InvoiceNumberService` (atomic sequential numbering), bcmath-backed `Support\Money` value handling, invoice item snapshotting from products, and `InvoiceCreated` event logging.

### Phase 7 — PDFs & Receipts
**Requirements (summarized):** PDF generation for invoices and receipts.

**What it generated:** `InvoicePdfService` and `ReceiptService` using `dompdf`, with receipts generated once and persisted (invoices regenerated on demand), and sequential receipt numbering.

### Phase 8 — Square Integration
**Requirements (summarized):** Create Square payment links for invoices.

**What it generated:** `SquarePaymentService` wrapping the Square PHP SDK, `payment_links` table, safe error translation (`SquarePaymentException`), and structured logging to a dedicated `external` log channel that never logs secrets.

### Phase 9 — Customer Portal
**Requirements (summarized):** A public, token-secured portal page where customers can view an invoice and pay it, with no login required.

**What it generated:** `PortalInvoiceController`/`PortalPaymentController`, `PortalAccessService` (event logging for portal access and payment-link clicks, with first-access-only owner notifications).

### Phase 10 — Email & SMS Delivery

**Prompt (verbatim):**
> Now implement invoice delivery via email and SMS.
> Requirements:
> - email by default
> - SMS optional
> - CC support
> - configurable provider settings
> - email template includes invoice summary and secure portal/payment link
> - SMS template includes short clean message and payment link
> - queued jobs for sending
> - message_deliveries table logs status
> - failed sending should be logged and visible in invoice activity
> - account owner can resend invoice
> - create EmailService, SmsService, NotificationDispatchService
> - tests for successful and failed delivery using fakes/mocks

**What it generated:** `EmailService`, `SmsService` (behind `Sms\Contracts\SmsProviderContract` with a `TwilioSmsProvider` implementation), `NotificationDispatchService`, `MessageDeliveryService`, queued jobs for email/SMS sending, `InvoiceMail`/`ReceiptMail` Markdown mailables, `SendInvoiceRequest`, an invoice-resend action, and a delivery-status panel on the invoice detail page. 24 tests covering success and failure paths using fakes/mocks.

### Phase 11 — Recurring Invoices

**Prompt (verbatim):**
> Now implement recurring invoices.
> Requirements:
> - create recurring invoice profile from invoice/template
> - support weekly, monthly, yearly, and custom interval if simple
> - next_run_at, last_run_at, ends_at, max_occurrences, occurrence_count
> - scheduler command checks due recurring profiles
> - queued job creates invoice and sends it automatically if configured
> - option: auto_send true/false
> - recurring invoices must copy item snapshot from original template
> - safe against duplicate execution
> - uses database transaction/locking where needed
> - create RecurringInvoiceService and ProcessRecurringInvoicesJob
> - tests for schedule calculation, invoice creation, and duplicate prevention

**What it generated:** `RecurringInvoiceService`, `ProcessRecurringInvoicesJob`, an `invoices:process-recurring` Artisan command wired into the scheduler, `RecurringInvoiceProfileController` and views, atomic `UPDATE ... WHERE locked_at IS NULL` locking to prevent duplicate generation, and Carbon `NoOverflow` date math for safe schedule advancement. 32 tests covering schedule calculation, generation, and duplicate-execution prevention.

### Phase 12 — Square Webhooks

**Prompt (verbatim):**
> Now implement Square webhook handling.
> Requirements:
> - receive Square payment completed events
> - validate webhook signature if supported/configured
> - idempotent processing using provider event ID
> - update payment status
> - update invoice status to paid
> - create event_log: payment_completed
> - notify account owner
> - generate receipt
> - email receipt PDF to customer
> - store raw webhook payload safely
> - log failures without exposing secrets
> - tests for successful webhook, duplicate webhook, invalid webhook, and unknown invoice

**What it generated:** `SquareWebhookController` and `SquareWebhookService` with three layers of idempotency protection (unique `provider_event_id` check, "already completed" guard, and a DB unique-constraint race backstop), signature verification via `Square\Utils\WebhooksHelper`, `GenerateReceiptJob`, `OwnerPaymentReceivedNotification`, and a `company_settings.payment_completed_notify` toggle. 14 tests covering the success, duplicate, invalid-signature, and unknown-invoice cases.

### Phase 13 — External API

**Prompt (verbatim):**
> Now implement external API access.
> Requirements:
> - API key or bearer token authentication
> - endpoint to create customer
> - endpoint to create invoice
> - endpoint to add invoice items
> - endpoint to send invoice
> - endpoint to create recurring invoice
> - endpoint to check invoice/payment status
> - endpoint docs in README and Help menu
> - JSON validation errors
> - consistent API response format
> - rate limiting
> - event logs for API-created resources
> - tests for API authentication and all endpoints

**What it generated:** Custom bearer-token authentication (`ApiToken` model, SHA-256 hashed tokens, `EnsureApiTokenIsValid` middleware), `ApiTokenService`/`ApiTokenController` for issuing tokens, `Api\V1` controllers for customers/invoices/payments/recurring profiles, a consistent `{success, message, data, meta}` JSON envelope enforced via a shared `ApiFormRequest` base class, token-keyed rate limiting, and API documentation in both the README and in-app Help menu.

### Phase 14 — Dashboard & Activity Logs

**Prompt (verbatim):**
> Now implement dashboard and activity logs.
> Requirements:
> - dashboard cards for total unpaid, paid, overdue, upcoming recurring invoices, recent activity
> - invoice detail page should show full event timeline
> - event logs for invoice_sent, portal_accessed, payment_link_clicked, payment_completed, receipt_sent, email_failed, sms_failed
> - filters for invoices by status/customer/date
> - clean elegant UI
> - mobile-friendly layout

**What it generated:** `DashboardService`/`DashboardController`, summary stat-tile components, an invoice event timeline component, status/customer/date filtering on the invoice list, and a mobile-responsive dashboard layout.

### Phase 15 — Help Menu & Developer Documentation

**Prompt (verbatim):**
> Now implement the in-app Help menu and developer documentation.
> Help menu must include:
> - setup steps
> - environment variables
> - Square setup
> - email setup
> - SMS setup
> - recurring invoice setup
> - API usage
> - how to extend products
> - deployment notes
> - troubleshooting guide
> Also update README.md with:
> - local setup
> - production setup
> - queue worker
> - scheduler cron
> - webhook setup
> - testing commands
> - deployment checklist

**What it generated:** A rewritten, ten-section in-app Help page (`resources/views/help/index.blade.php`) and a substantially expanded `README.md` covering local and production setup, queue/scheduler operation, webhook registration, testing, and deployment.

### Phase 16 — Test Coverage & QA Checklist

**Prompt (verbatim):**
> Now add full test coverage for critical business flows. Required tests:
> - customer create/update
> - product create/update/import
> - invoice calculation
> - invoice sending
> - Square payment link creation using mock
> - portal accessed event
> - payment link clicked event
> - recurring invoice scheduled generation
> - Square webhook payment completed
> - receipt generation and sending
> - API authentication
> - API create customer/invoice/send invoice
> - failed email/SMS logging
> Also create a manual QA checklist for the acceptance criteria.

**What it generated:** Additional feature tests closing coverage gaps across every listed flow, and `docs/QA_CHECKLIST.md` — a 16-section manual acceptance checklist.

### Phase 17 — Production Deployment Documentation & Scripts

**Prompt (verbatim):**
> Now create production deployment documentation and scripts for a VPS/DigitalOcean-style server. Include:
> - Nginx config
> - PHP-FPM requirements
> - MySQL setup notes
> - SSL/HTTPS using Certbot
> - queue worker using Supervisor
> - Laravel scheduler cron entry
> - storage permissions
> - environment setup
> - backup recommendation
> - deployment command checklist
> - rollback notes
> Keep it simple, repeatable, and production-ready.

**What it generated:** `docs/DEPLOYMENT.md` and a `deploy/` directory: Nginx server block, dedicated PHP-FPM pool config, MySQL setup notes, Supervisor worker config, cron file, and idempotent shell scripts (`provision.sh`, `deploy.sh` — doubles as rollback via a git-ref argument, `backup.sh`, `fix-permissions.sh`).

### Phase 18 — Senior Architect Review

**Prompt (verbatim):**
> Now review the entire codebase as a senior Laravel architect. Check for:
> - security issues
> - missing validation
> - hardcoded secrets
> - controller business logic
> - missing transactions
> - missing indexes
> - duplicate code
> - naming consistency
> - error handling
> - queue retry safety
> - webhook idempotency
> - recurring invoice duplicate prevention
> - UI consistency
> - missing documentation
> - missing tests
> Then fix the issues and provide a final summary.

**What it generated:** A full 15-category audit followed by concrete fixes:
- `EmailService::sendReceipt()` made idempotent against queue-retry double-sends (gated on `receipts.sent_at`), closing a real "at-least-once" delivery bug.
- `DB::transaction()` wrapping added to `CustomerService`, `ApiTokenService`, and `RecurringInvoiceService::createProfile()`.
- Duplicated invoice-item validation extracted into a shared `ValidatesInvoiceItems` trait, used by both web and API form requests.
- `paginateForUser()` signatures unified across services for naming consistency.
- `docs/ARCHITECTURE.md` sections on Services and Jobs rewritten to match the actual, as-built system.
- Full 297-test suite re-verified passing after every fix.

### Phase 19 — AI Prompts Log (this document)

**Prompt (verbatim):**
> Now create AI_PROMPTS_LOG.md. This document must include:
> - project name
> - purpose
> - technology stack
> - every major prompt used during development
> - summary of what each prompt generated
> - key architectural decisions
> - known limitations
> - future improvement ideas
> Make it professional so it can be shared with the client as proof that AI assistance was used responsibly and in a structured way.

**What it generated:** This document.

---

## 5. Key Architectural Decisions

- **Single-tenant by design.** One business, one Square account per installation — simpler and matched the client's actual need; see [§6](#6-known-limitations) for the multi-tenant tradeoff.
- **Blade + Alpine.js, not Livewire or an SPA.** Chosen up front as a deliberate stack decision to keep the app server-rendered and simple to host, deploy, and reason about.
- **Services own business logic; controllers stay thin.** Every non-trivial write goes through a service class; controllers call services and translate results into HTTP responses. FormRequests own validation and, in most cases, authorization.
- **Money is never a float.** All monetary values flow through a bcmath-backed `Support\Money` class to avoid floating-point rounding errors in totals and tax.
- **Invoice line items are snapshotted, not live-linked.** Editing a product later never changes an invoice that already references it — each invoice item stores its own name, price, and tax rate at creation time.
- **`event_logs` is the single source of truth for activity.** All activity (sends, portal access, payments, failures) is written through one `EventLogService` write path and rendered directly on the dashboard and invoice timeline — no separate, divergent audit mechanism.
- **Payment state changes only on a verified Square webhook**, never on the customer's portal redirect back to the app — the redirect is purely informational; only the server-to-server webhook is trusted to mark an invoice paid.
- **Idempotency is enforced at two levels:** application-level atomic `UPDATE ... WHERE column IS NULL` locking (invoice numbering, recurring invoice scheduling) for our own concurrency, and unique database constraints (`provider_event_id`, `provider_payment_id`) as the backstop for webhook redelivery and duplicate job execution.
- **Custom bearer-token API authentication**, not Laravel Sanctum — a deliberately minimal, single-purpose token model (`api_tokens`, SHA-256 hashed) since the API has no need for Sanctum's SPA/session-cookie features.
- **Consistent JSON envelope** (`{success, message, data, meta}`) enforced centrally so every API consumer gets the same response shape for both success and validation/authorization failures.
- **Queued jobs are dispatched only after their triggering DB transaction commits** — a rolled-back invoice or recurring-profile update can never trigger a real email/SMS send.
- **Provider-agnostic delivery abstractions.** `SmsProviderContract` decouples `SmsService` from Twilio specifically, so another SMS provider could be swapped in without touching call sites.
- **MySQL used for both the application and test databases** — `pdo_sqlite` isn't available on this development machine, so a dedicated `invoicing_app_test` MySQL database is used instead of the more common SQLite-in-memory test setup.
- **Built phase-by-phase with explicit approval gates**, mirroring the client's own instruction not to generate the whole application in one prompt — this document is the direct record of that process.

---

## 6. Known Limitations

- **Single-tenant only.** The app supports one business and one Square account per installation; there is no multi-business/multi-tenant data isolation.
- **No Google OAuth.** Authentication is email/password (Breeze) only — Google OAuth was explicitly deferred by client decision during Phase 2.
- **Twilio is the only implemented SMS provider.** The `SmsProviderContract` abstraction supports adding others, but only Twilio is wired in.
- **No partial-payment UI.** The `payments` table supports multiple payments per invoice at the schema level, but there is no user-facing flow for recording partial payments — Square payment links are all-or-nothing.
- **Backups are local-disk only.** `deploy/scripts/backup.sh` produces a local dump; it does not push to off-box/cloud storage. This needs to be wired up (e.g., to S3) by whoever provisions the production server.
- **Single currency.** There is no per-invoice or per-customer currency selection; currency is a single application-level setting.
- **No stale-lock auto-recovery for recurring invoices.** If the server process is hard-killed mid-transaction while a recurring profile is locked, that profile's `locked_at` will remain set and it will silently stop generating invoices until manually cleared. This is a deliberately accepted tradeoff (documented in code) rather than an oversight — see `RecurringInvoiceService::acquireLock()`.
- **No automated CI pipeline.** The test suite (297 tests) is run manually/locally; there is no GitHub Actions or equivalent workflow configured yet.
- **Flat API rate limiting.** All API tokens share the same rate limit; there is no per-token or per-plan tiering.

---

## 7. Future Improvement Ideas

- Multi-tenant support (multiple businesses/Square accounts per installation).
- Google OAuth login, if the client later wants it.
- Additional SMS providers (e.g., Vonage, AWS SNS) via the existing `SmsProviderContract` interface.
- Partial payment / installment support.
- Multi-currency invoicing.
- Automated CI (e.g., GitHub Actions) running the test suite on every push.
- Off-box backup automation (e.g., wiring `backup.sh` to sync to S3 or similar).
- Per-token API rate-limit tiers.
- A stale-lock recovery sweep for recurring invoice profiles (e.g., a scheduled job that clears `locked_at` values older than a safe threshold).
- Customer self-serve portal accounts (currently token-link access only, with no login or invoice history view across visits).

---

*This document was generated as part of Phase 19 of the project's build process, following the same phase-by-phase, human-directed approach used throughout.*
