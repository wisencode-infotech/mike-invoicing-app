# Architecture — Laravel Invoicing Application

Phase 1 deliverable. Documentation only — no implementation code. This is the reference all later phases build against; update it if a later phase legitimately changes a decision made here.

Scope confirmed with client: single business / single Square account (not multi-tenant), Blade + Alpine.js admin UI, email/password auth only (Google OAuth deferred), Twilio for SMS behind a provider-agnostic interface.

---

## 1. Folder Structure

```
app/
  Actions/
    Invoice/
      CalculateInvoiceTotalsAction.php
      CreateInvoiceFromSnapshotAction.php     # used by both manual create and recurring generation
  Console/
    Commands/
      ProcessRecurringInvoices.php            # scheduled, dispatches ProcessRecurringInvoicesJob
      MarkOverdueInvoices.php                 # scheduled, sweeps sent/viewed past due_date
  Enums/
    InvoiceStatus.php
    PaymentStatus.php
    PaymentLinkStatus.php
    DeliveryChannel.php
    DeliveryStatus.php
    RecurringFrequency.php
    EventType.php
  # No Events/Listeners directories — superseded during implementation by
  # direct EventLogService::log() calls from the acting service (see
  # section 7). Kept event recording and the action it describes atomic
  # rather than splitting them across a dispatched-event boundary.
  Http/
    Controllers/
      Auth/                                   # Laravel Breeze's auth controllers (email/password) — flat, not Web/
      ProfileController.php                    # Breeze-generated; precedent for flat (non-namespaced) web controllers
      CompanySettingsController.php
      CustomerController.php
      ProductController.php
      ProductCsvImportController.php
      InvoiceController.php
      InvoicePdfController.php                 # preview/download, admin-authenticated
      RecurringInvoiceProfileController.php
      ApiTokenController.php
      HelpController.php
      Portal/
        PortalInvoiceController.php            # show invoice by token, logs portal_accessed
        PortalPaymentController.php            # "Pay" click -> redirect to Square, logs payment_link_clicked
        PortalReceiptController.php            # show/download receipt once paid
      Api/V1/
        CustomerController.php
        InvoiceController.php
        InvoiceItemController.php
        InvoiceSendController.php
        RecurringInvoiceProfileController.php
        PaymentController.php
      Webhooks/
        SquareWebhookController.php
    Requests/
      UpdateCompanySettingsRequest.php          # flat, matching Breeze's ProfileUpdateRequest precedent
      Customer/StoreCustomerRequest.php, UpdateCustomerRequest.php
      Product/StoreProductRequest.php, UpdateProductRequest.php, ImportProductsCsvRequest.php
      Invoice/StoreInvoiceRequest.php, UpdateInvoiceRequest.php, SendInvoiceRequest.php
      RecurringInvoiceProfile/StoreRecurringInvoiceProfileRequest.php, UpdateRecurringInvoiceProfileRequest.php
      Api/V1/... (mirrors web requests; API request classes validate the same rules, may allow nested items array)
    Resources/
      CustomerResource.php
      InvoiceResource.php
      InvoiceItemResource.php
      RecurringInvoiceProfileResource.php
      PaymentResource.php
    Middleware/
      EnsureApiTokenIsValid.php                # resolves api_tokens by hash, sets acting user, updates last_used_at
      ApiRateLimiter.php                       # or route-level throttle:api
      PortalRateLimiter.php                    # throttle on portal + payment click routes
  Jobs/
    SendInvoiceEmailJob.php
    SendInvoiceSmsJob.php
    SendOwnerEventNotificationJob.php
    ProcessRecurringInvoicesJob.php
    GenerateReceiptJob.php
    SendReceiptEmailJob.php
    SyncSquarePaymentStatusJob.php             # fallback reconciliation, in case webhook is missed
    ImportProductsCsvJob.php
  Mail/
    InvoiceMail.php
    ReceiptMail.php
  Models/
    User.php
    CompanySetting.php
    Customer.php
    Product.php
    Invoice.php
    InvoiceItem.php
    RecurringInvoiceProfile.php
    PaymentLink.php
    Payment.php
    Receipt.php
    EventLog.php
    ApiToken.php
    MessageDelivery.php
  Notifications/
    OwnerPortalAccessedNotification.php        # Notification on User (mail channel)
    OwnerPaymentClickedNotification.php
    OwnerPaymentCompletedNotification.php
  Policies/
    CustomerPolicy.php
    ProductPolicy.php
    InvoicePolicy.php
    RecurringInvoiceProfilePolicy.php
  Services/
    CompanySettingsService.php
    CustomerService.php
    ProductService.php
    InvoiceService.php
    InvoiceNumberService.php
    InvoicePdfService.php
    ReceiptService.php
    SquarePaymentService.php
    PortalAccessService.php
    NotificationDispatchService.php
    EmailService.php
    Sms/
      SmsService.php                          # facade-like service, delegates to bound contract
      Contracts/SmsProviderContract.php
      Providers/TwilioSmsProvider.php
    RecurringInvoiceService.php
    ApiTokenService.php
    EventLogService.php
    MessageDeliveryService.php
  Support/
    Money.php                                  # decimal-safe money helper (bcmath-backed)
    PortalTokenGenerator.php                   # high-entropy random token generation
  View/Components/
    Layout/AppLayout.php, PortalLayout.php
    StatusBadge.php
    MoneyDisplay.php
    EmptyState.php
    ConfirmModal.php
config/
  square.php
  invoice.php                                  # numbering format, default tax behavior, due-date defaults
  sms.php
  portal.php                                   # token TTL, rate limit settings
resources/
  views/
    layouts/
    dashboard/
    settings/
    customers/
    products/
    invoices/
    recurring/
    portal/                                    # separate layout, no admin chrome
    pdf/                                        # invoice.blade.php, receipt.blade.php
    help/
    components/
      branding/                                # letterhead.blade.php, receipt-footer.blade.php —
                                                # shared by invoice/portal/receipt/email views, built in Phase 4
  css/, js/ (Alpine.js + light Tailwind, no SPA build complexity)
routes/
  web.php                                       # admin (auth) + portal (token) groups
  api.php                                        # /api/v1
  console.php                                    # scheduler definitions
docs/
  ARCHITECTURE.md                                # this file
  API.md
  DEPLOYMENT.md
  SECURITY.md
  QA_CHECKLIST.md
  AI_PROMPTS_LOG.md
tests/
  Feature/
    Web/... , Portal/... , Api/V1/... , Webhooks/...
  Unit/
    Services/..., Enums/..., Support/...
database/
  migrations/
  factories/
  seeders/
```

---

## 2. Database Tables

All monetary columns are `decimal(12,2)`. All tables use `timestamps()` unless noted. Tables holding user-owned data are scoped by `user_id` (supports multiple admin users even though Square/company config is currently single-business).

| Table | Key Columns | Notes |
|---|---|---|
| `users` | id, name, email unique, password, timestamps | Laravel default. |
| `company_settings` | id, user_id FK unique, company_name, logo_path nullable, brand_color nullable, email nullable, phone nullable, address nullable(text), tax_id nullable, receipt_footer nullable(text), portal_first_access_notify bool default true, payment_click_notify bool default true | One row per user; unique on `user_id`. |
| `customers` | id, user_id FK, name, email nullable, phone nullable, billing_address nullable(text), notes nullable(text), active bool default true, soft_deletes | |
| `products` | id, user_id FK, name, description nullable(text), unit_price decimal, tax_rate decimal(5,2) default 0, active bool default true, soft_deletes | |
| `invoices` | id, user_id FK, customer_id FK, recurring_invoice_profile_id nullable FK, invoice_number string, status string (enum-backed), issue_date date, due_date date, subtotal, tax_total, total, currency char(3) default `USD`, notes nullable(text), terms nullable(text), sent_at/viewed_at/paid_at/cancelled_at nullable timestamps, soft_deletes | `unique(user_id, invoice_number)`. |
| `invoice_items` | id, invoice_id FK, product_id nullable FK, name, description nullable(text), quantity decimal(10,2), unit_price, tax_rate decimal(5,2), subtotal, tax_total, total, sort_order int | Snapshot — never re-reads `products` after creation. |
| `recurring_invoice_profiles` | id, user_id FK, customer_id FK, source_invoice_id FK, frequency string (enum), interval_count int default 1, next_run_at datetime, last_run_at nullable, ends_at nullable date, max_occurrences nullable int, occurrence_count int default 0, auto_send bool default true, delivery_channel string (enum), cc_emails nullable(text, comma-separated or json), active bool default true, locked_at nullable timestamp | `locked_at` is the mutex for scheduler safety. |
| `payment_links` | id, invoice_id FK, provider string default `square`, provider_link_id nullable, provider_order_id nullable, url nullable, token string unique, status string (enum), expires_at nullable, clicked_at nullable | |
| `payments` | id, invoice_id FK, payment_link_id nullable FK, provider string, provider_payment_id nullable unique, provider_order_id nullable, amount, currency char(3), status string (enum), paid_at nullable, raw_payload_json json nullable | Raw payload retained for audit/debug, never rendered to customer. |
| `receipts` | id, invoice_id FK, payment_id FK, receipt_number string unique, pdf_path nullable, sent_at nullable | |
| `event_logs` | id, user_id FK, invoice_id nullable FK, customer_id nullable FK, event_type string, title string, description nullable(text), metadata_json json nullable, provider_event_id nullable string, ip_address nullable, user_agent nullable, created_at only (no updated_at) | Append-only audit trail. |
| `api_tokens` | id, user_id FK, name, token_hash string unique, abilities_json json nullable, last_used_at nullable, active bool default true | Raw token shown once at creation, only hash persisted. |
| `message_deliveries` | id, invoice_id nullable FK, receipt_id nullable FK, channel string (enum), recipient string, cc nullable(text), subject nullable, body_preview nullable(text), provider nullable string, provider_message_id nullable, status string (enum), error_message nullable(text), sent_at nullable | |

### Indexes
- `invoices`: `user_id`, `customer_id`, `status`, `due_date`, unique(`user_id`,`invoice_number`).
- `invoice_items`: `invoice_id`, `product_id`.
- `recurring_invoice_profiles`: `user_id`, `active`, `next_run_at`, `locked_at`.
- `payment_links`: `invoice_id`, unique(`token`), `provider_link_id`.
- `payments`: `invoice_id`, `status`, unique(`provider_payment_id`) where not null.
- `event_logs`: `user_id`, `invoice_id`, `event_type`, unique(`provider_event_id`) where not null, `created_at`.
- `api_tokens`: unique(`token_hash`), `user_id`, `active`.
- `message_deliveries`: `invoice_id`, `channel`, `status`, `sent_at`.

---

## 3. Model Relationships

```
User
  hasOne   CompanySetting
  hasMany  Customer, Product, Invoice, RecurringInvoiceProfile, ApiToken, EventLog

Customer
  belongsTo User
  hasMany   Invoice, RecurringInvoiceProfile, EventLog

Product
  belongsTo User
  hasMany   InvoiceItem   (nullable FK — items survive product deletion)

Invoice
  belongsTo User, Customer
  belongsTo RecurringInvoiceProfile (nullable, "generated by")
  hasMany   InvoiceItem, PaymentLink, Payment, MessageDelivery, EventLog
  hasOne    Receipt (through latest completed Payment)

InvoiceItem
  belongsTo Invoice
  belongsTo Product (nullable)

RecurringInvoiceProfile
  belongsTo User, Customer
  belongsTo Invoice (as "source_invoice", the template)
  hasMany   Invoice (generated invoices, via recurring_invoice_profile_id)

PaymentLink
  belongsTo Invoice
  hasMany   Payment

Payment
  belongsTo Invoice
  belongsTo PaymentLink (nullable)
  hasOne    Receipt

Receipt
  belongsTo Invoice
  belongsTo Payment

EventLog
  belongsTo User
  belongsTo Invoice (nullable), Customer (nullable)

ApiToken
  belongsTo User

MessageDelivery
  belongsTo Invoice (nullable), Receipt (nullable)
```

---

## 4. Enum / Status Design

PHP 8.1 backed (string) enums in `app/Enums`, cast on models via `$casts`.

- **InvoiceStatus**: `draft`, `sent`, `viewed`, `paid`, `overdue`, `cancelled`
  - `overdue` is not a terminal state set at creation — a scheduled command (`MarkOverdueInvoices`) sweeps `sent`/`viewed` invoices past `due_date` and not paid/cancelled, transitions them, and logs an event. Terminal states (`paid`, `cancelled`) lock the invoice from further edits per the dev rules.
- **PaymentStatus** (on `payments`): `pending`, `completed`, `failed`, `refunded`.
- **PaymentLinkStatus**: `pending`, `active`, `expired`, `completed`, `cancelled`.
- **DeliveryChannel**: `email`, `sms` (on `message_deliveries`); recurring profiles additionally allow `both`.
- **DeliveryStatus** (on `message_deliveries`): `queued`, `sent`, `failed`.
- **RecurringFrequency**: `weekly`, `monthly`, `yearly`, `custom` (custom uses `interval_count` in days).
- **EventType** (values stored in `event_logs.event_type`, not DB-enforced since it's a free-text audit trail, but centralized as a PHP enum for consistency): `invoice_created`, `invoice_sent`, `invoice_viewed`, `invoice_cancelled`, `invoice_overdue`, `portal_accessed`, `payment_link_created`, `payment_link_clicked`, `payment_completed`, `payment_failed`, `receipt_generated`, `receipt_sent`, `recurring_invoice_generated`, `delivery_failed`, `api_token_created`, `api_token_revoked`.

### Invoice status transition rules
- `draft` → `sent` (on send action) → `viewed` (on first portal access) → `paid` (only via Square webhook, never via portal return URL) → *(terminal)*.
- `draft`/`sent`/`viewed` → `cancelled` (manual action) → *(terminal)*.
- `sent`/`viewed` → `overdue` (scheduled sweep) — `overdue` is a display/reporting state; the invoice can still transition to `paid` afterward.
- Once `paid` or `cancelled`: line items, totals, and customer/due-date fields become immutable. Only internal notes/metadata may still be edited (per dev rules).

---

## 5. Services

Thin controllers delegate to these. Each service owns one bounded responsibility; all mutating methods that touch money, payment state, or multi-row writes run inside DB transactions.

| Service | Responsibility |
|---|---|
| `CompanySettingsService` | Get-or-create settings for a user, update branding/notification prefs, resolve logo storage path. |
| `CustomerService` | CRUD + soft delete, active/inactive toggling, ownership-scoped queries. |
| `ProductService` | CRUD + soft delete, active/inactive toggling, CSV import — parses and validates every row synchronously in the request (no queued job; catalogs are expected to be small), skipping and reporting invalid rows rather than failing the whole file. |
| `InvoiceService` | Draft creation/update, server-side recalculation of line item and invoice totals (never trusts client-submitted totals), status transitions, cancel, snapshot item creation from products. |
| `InvoiceNumberService` | Generates the next sequential `invoice_number` per user (configurable prefix/format via `config/invoice.php`), collision-safe under transaction. |
| `InvoicePdfService` | Renders invoice/receipt Blade templates to PDF (via a PDF wrapper package), returns storage path or streamed download. |
| `ReceiptService` | Creates `receipts` row + receipt number sequence, invokes `InvoicePdfService`, marks invoice paid-state consistent, dispatches `ReceiptGenerated` event. |
| `SquarePaymentService` | Wraps Square PHP SDK: creates hosted checkout payment link for an invoice, persists `payment_links` row, translates SDK exceptions into safe, non-secret-leaking error logs. |
| `PortalAccessService` | Resolves invoice by portal token, records `portal_accessed`/`payment_link_clicked` events, applies first-access-only notification logic. |
| `NotificationDispatchService` | Central point deciding *whether* to notify the owner (checks `company_settings` preferences + first-time-only logic) before queuing owner notifications. |
| `EmailService` | Thin wrapper around Laravel Mail for invoice/receipt emails; records `message_deliveries` row per attempt. |
| `Sms\SmsService` (+ `Contracts\SmsProviderContract`, `Providers\TwilioSmsProvider`) | Provider-agnostic SMS sending; concrete provider swappable via `config/sms.php` / `.env` without touching callers. |
| `RecurringInvoiceService` | Computes `next_run_at` given frequency/interval, generates a new invoice from a profile's source snapshot inside a transaction, updates `occurrence_count`/`last_run_at`, enforces `ends_at`/`max_occurrences`. |
| `ApiTokenService` | Issues tokens (returns raw token once), hashes for storage, revokes, validates + touches `last_used_at`. |
| `EventLogService` | Single write path for `event_logs` rows; all events funnel through here so the audit trail can't be bypassed. |
| `MessageDeliveryService` | Single write path for `message_deliveries` rows (create on queue, update on provider success/failure). |
| `SquareWebhookService` | Business logic for a verified `POST /webhooks/square` delivery — idempotency (event id + payment id), marks the invoice paid, logs `PaymentCompleted`, triggers owner notification + `GenerateReceiptJob` only after the transaction commits. |
| `ApiTokenGenerator` / `CcEmailList` (support, not services) | Small stateless helpers (`app/Support/`) shared across multiple services/requests — token generation+hashing, and comma/newline CC-list parsing — rather than duplicating the same few lines in each caller. |
| `DashboardService` | Read-only aggregation for the dashboard (unpaid/paid-this-month/overdue totals, active recurring schedules, recent activity) — every query scoped to a single user. |

---

## 6. Jobs (queued, database driver)

**As actually built** — this table originally sketched a few jobs before implementation that were never built this way; corrected here to match reality. Owner notifications (`NotificationDispatchService`) are sent **synchronously**, not via a queued job — they're a small, fast, best-effort side effect of the same request that logs the triggering event, not worth a queue hop. CSV product import runs synchronously in the request (`ProductService::importFromCsv()`) — catalogs are expected to be small, and users benefit from seeing the row-level import result immediately rather than polling. There is no periodic Square-reconciliation fallback job — the webhook (real-time, idempotent) is the only path that marks a payment complete, by deliberate design (see section 11).

| Job | Trigger | Notes |
|---|---|---|
| `SendInvoiceEmailJob` | `InvoiceService::send()` (manual send/resend, or recurring auto-send) | Renders `InvoiceMail`, records `message_deliveries`, supports CC. Not idempotent by design — a deliberate resend must create a new delivery record every time. |
| `SendInvoiceSmsJob` | Same trigger, when the SMS channel is selected | Uses `Sms\SmsService`. Same not-idempotent-by-design reasoning as above. |
| `ProcessRecurringInvoicesJob` | `invoices:process-recurring` console command (scheduled every 5 minutes) | Calls `RecurringInvoiceService::processDueProfiles()` — locks due profiles (`locked_at`), generates each in its own transaction, releases the lock in a `finally` block. `tries = 1`: a whole-job retry only matters for something catastrophic, and the next scheduler tick five minutes later already provides that retry. |
| `MarkOverdueInvoicesJob` | `invoices:mark-overdue` console command (scheduled daily) | Calls `InvoiceService::markOverdueInvoices()` — sweeps `sent`/`viewed` invoices past `due_date` to `overdue`. Naturally idempotent: a re-run only matches invoices still in `sent`/`viewed`, so an already-swept invoice is silently skipped. |
| `GenerateReceiptJob` | `SquareWebhookService`, after a verified completed payment commits | Calls `ReceiptService::generate()` (idempotent per payment — returns the existing receipt if one already exists), then `emailToCustomer()`. |
| `SendReceiptEmailJob` | `ReceiptService::emailToCustomer()`, called by `GenerateReceiptJob` | Calls `EmailService::sendReceipt()`, which is itself idempotent (skips if `receipts.sent_at` is already set) — unlike invoice sends, nothing in this app legitimately re-triggers a receipt send, so a second attempt (e.g. `GenerateReceiptJob` retried) must be a no-op, not a second email. |

All jobs: `tries` + backoff configured (except the two scheduled sweeps, which rely on the next scheduler tick instead), failures logged without leaking provider secrets (per dev rules).

---

## 7. Event Logging

**As actually built** (superseding this section's original real-Laravel-Events sketch below): there is no dispatched `Illuminate\Events` layer for domain events. `EventLogService::log()` is the single write path for `event_logs`, called directly and synchronously from whichever service performs the action — this keeps "did the thing happen" and "was it recorded" atomic (often inside the same DB transaction), with no risk of a queued listener silently failing to record an event that already took effect. Every `App\Enums\EventType` case and its writer:

| EventType | Logged by |
|---|---|
| `customer_created` | `CustomerService::create()` |
| `invoice_created` | `InvoiceService::create()` |
| `invoice_sent` | `InvoiceService::send()` (first send and every resend) |
| `invoice_viewed` (via `portal_accessed`, see below) | — |
| `invoice_cancelled` | `InvoiceService::cancel()` |
| `invoice_overdue` | `MarkOverdueInvoices` command (daily sweep) |
| `portal_accessed` | `PortalAccessService::recordPortalAccess()`, every access |
| `payment_link_created` | `SquarePaymentService::createOrGetPaymentLink()` |
| `payment_link_clicked` | `PortalAccessService::recordPaymentLinkClick()`, every click |
| `payment_completed` | `SquareWebhookService`, on a verified new completed payment |
| `payment_failed` | *(reserved; not currently written — webhook only processes `COMPLETED`)* |
| `receipt_generated` | `ReceiptService::generate()` |
| `receipt_sent` | `EmailService::sendReceipt()`, on successful send |
| `recurring_profile_created` | `RecurringInvoiceService::createProfile()` |
| `recurring_invoice_generated` | `RecurringInvoiceService::processProfile()` |
| `email_failed` | `EmailService`, on provider/validation failure |
| `sms_failed` | `SmsService`, on provider/validation failure |
| `api_token_created` / `api_token_revoked` | `ApiTokenService` |

Owner-facing notifications (see section 8) are dispatched by the same service call that logs the event, not by a separate listener reacting to it — e.g. `PortalAccessService::recordPortalAccess()` both logs `portal_accessed` and calls `NotificationDispatchService::notifyOwnerOfPortalAccess()` in the same method, since "was this the first access" has to be computed before the log write either way.

---

## 8. Notifications

Laravel's `Notification` system is used only for the **owner** (a `User`, which is `Notifiable`) since the admin is an authenticated Eloquent model:

- `OwnerPortalAccessedNotification` (mail channel)
- `OwnerPaymentLinkClickedNotification` (mail channel)
- `OwnerPaymentReceivedNotification` (mail channel)

Customer-facing sends (invoice email/SMS, receipt email) are **not** Notifications — the customer isn't a Notifiable Eloquent model (no login). These go through `Mail` classes (`InvoiceMail`, `ReceiptMail`) and `Sms\SmsService` directly, invoked from jobs, with delivery tracked in `message_deliveries` rather than Laravel's notification tables.

---

## 9. API Routes (`routes/api.php`, prefix `/api/v1`, `EnsureApiTokenIsValid` middleware, throttled)

**Implemented in Phase 13.** Full endpoint/auth/response reference is documented for integrators in-app (Help page, `/help`) and in `README.md`'s "External API" section — this section covers the internal design.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/customers` | Create customer |
| GET | `/api/v1/customers` | List customers (paginated) |
| GET | `/api/v1/customers/{id}` | Read customer |
| PATCH | `/api/v1/customers/{id}` | Update customer (partial) |
| POST | `/api/v1/invoices` | Create invoice (optionally with nested `items[]`) |
| GET | `/api/v1/invoices/{id}` | Read invoice |
| POST | `/api/v1/invoices/{id}/items` | Add invoice item (draft only) |
| POST | `/api/v1/invoices/{id}/send` | Send invoice (email/sms/both) |
| GET | `/api/v1/invoices/{id}/status` | Check invoice/payment status |
| POST | `/api/v1/recurring-invoices` | Create recurring invoice profile |
| GET | `/api/v1/payments/{id}` | Read payment status |

**Authentication.** Bearer tokens, issued/revoked from the admin UI (`/api-tokens`, `ApiTokenService`) — a separate `api_tokens` table, not Laravel Sanctum. Only `token_hash` (SHA-256 of the raw token, `ApiTokenGenerator`) is ever stored; the plaintext is shown once at creation and cannot be retrieved again. `EnsureApiTokenIsValid` resolves the token from the `Authorization: Bearer` header, rejects if missing/unknown/revoked (401), updates `last_used_at`, and calls `Auth::setUser($token->user)` — **not** `$request->setUserResolver()` alone, since `$this->authorize()`/`Gate` resolve the current user via `Auth::guard()->user()` (see `Illuminate\Auth\AuthServiceProvider::registerRequestRebindHandler()`), so only `Auth::setUser()` keeps FormRequest ownership checks, controller Policy calls, and `$request->user()` all consistent.

**Authorization.** Every resource-scoped endpoint reuses the same Policies as the web UI (`InvoicePolicy`, `CustomerPolicy`, `RecurringInvoiceProfilePolicy`, `PaymentPolicy`) — a token can only read/act on its own account's data, enforced both by `Rule::exists(...)->where('user_id', ...)` validation rules (create endpoints) and `$this->authorize()` calls (route-model-bound endpoints).

**Response envelope** (`App\Http\Controllers\Api\Concerns\ApiResponses`, consistent success/error shape for every endpoint):
```json
{ "success": true, "message": "Invoice created successfully.", "data": { "id": 123, "invoice_number": "INV-000123", "status": "draft" } }
```
Validation errors return `success: false`, a `message`, and a `data.errors` map. `App\Http\Requests\Api\ApiFormRequest` (the base class every `Api/*Request` extends) overrides `failedValidation()`/`failedAuthorization()` to enforce this envelope instead of Laravel's default redirect-back-with-errors web behavior. Errors that don't originate from a FormRequest (404 route-model-binding misses, 403 Policy denials via `$this->authorize()`, 429 rate limiting, and an uncaught-exception 500 backstop that never leaks internals) are reshaped into the same envelope by `render()` closures in `bootstrap/app.php`. List endpoints add a top-level `meta` block (`current_page`/`per_page`/`total`/`last_page`) rather than nesting Laravel's own paginator `links`/`meta` shape inside `data`.

**Rate limiting.** `RateLimiter::for('api', ...)` (`AppServiceProvider::boot()`) keys by a hash of the raw bearer token — not the resolved user — so it still applies to invalid/missing-token requests (falls back to IP), deterring credential brute-forcing. Default 60 requests/minute (`API_RATE_LIMIT_PER_MINUTE`).

**Event logging.** Every API-created resource is logged the same way a web-created one would be, since both paths share the same service layer: `CustomerService::create()` logs `CustomerCreated`, `InvoiceService::create()` logs `InvoiceCreated` (already existing from Phase 6), `RecurringInvoiceService::createProfile()` logs `RecurringProfileCreated`.

---

## 10. Web Routes (`routes/web.php`)

**Admin (auth middleware):**
`/dashboard`, `/settings`, `/customers[/create|/{id}|/{id}/edit]`, `/products[/create|/{id}|/{id}/edit|/import]`, `/invoices[/create|/{id}|/{id}/edit]` + `/invoices/{id}/send`, `/invoices/{id}/pdf`, `/invoices/{id}/cancel`, `/invoices/{id}/recurring/create`, `/recurring-invoices[/{id}/toggle]`, `/api-tokens` (create form is inline on the index page) `+ /api-tokens/{id}/revoke`, `/help`.

**Portal (no auth, token-resolved, rate-limited):**
`/portal/{token}` (show invoice), `/portal/{token}/pay` (redirect to Square, logs click), `/portal/return` (Square redirect landing — display-only, never mutates payment state), `/portal/{token}/receipt` (visible once paid).

**Webhook (no auth, signature-verified, CSRF-excluded):**
`POST /webhooks/square`.

---

## 11. Square Payment Flow

**Implemented through Phase 12** (steps 1–10 below). Payment link creation is currently a manual "Create Payment Link" action on the invoice, independent of "Send"; wiring the two together is a natural follow-up once Square credentials are live and the full round trip can be tested end to end in production.

1. Admin creates invoice (draft) → sends it (web action or `POST /api/v1/invoices/{id}/send`). Independently, the admin (or, later, the send action) triggers **Create Payment Link**.
2. `InvoiceService` validates and locks in server-recalculated totals before any Square call is made.
3. `SquarePaymentService` creates a hosted checkout payment link via the Square PHP SDK (`checkout.paymentLinks.create`, one `Order` line item per invoice item using the item's tax-inclusive total at quantity 1 — Square's own per-line tax model isn't replicated, since our invoice/receipt/portal pages already show the correct breakdown before the customer ever reaches Square). A high-entropy `token` (`PortalTokenGenerator`, `bin2hex(random_bytes(48))`) is generated *before* the API call and used both as the Square `checkoutOptions.redirectUrl` (`route('portal.show', $token)`) and the persisted `payment_links.token` — so the customer always lands back on the exact branded page they paid from. `payment_links` row stores `provider_link_id`, `provider_order_id`, `url`, `token`, `status = active`. Returns the existing active link instead of creating a duplicate if one already exists (idempotent per invoice).
4. Customer opens the portal (`GET /portal/{token}`, public, rate-limited, no auth) → sees branded invoice details → clicks "Continue to Payment" (`GET /portal/{token}/pay`) → redirected to the Square-hosted checkout URL. Both routes 404 on an unrecognized token and never resolve invoices by ID.
5. Customer opens the portal → `PortalAccessService::recordPortalAccess()` writes a `portal_accessed` event_log row on **every** access, transitions the invoice `sent` → `viewed` and sets `viewed_at` on first access only, and — only on the invoice's first-ever `portal_accessed` event, and only if `company_settings.portal_first_access_notify` is enabled — emails the owner via `OwnerPortalAccessedNotification`. (Built directly in `PortalAccessService` rather than through Laravel's Event/Listener layer sketched earlier — log-then-maybe-notify is one cohesive operation here, and splitting it across listeners created an event-ordering hazard for the "was this the first access" check.)
6. Customer clicks "Pay" (only on the genuine redirect-to-Square path, not the invalid/expired-link bounce-back) → `PortalAccessService::recordPaymentLinkClick()` sets `payment_links.clicked_at`, writes a `payment_link_clicked` event_log row every time, and — first click only, gated by `payment_click_notify` — emails the owner via `OwnerPaymentLinkClickedNotification`.
7. Square processes the sandbox/live card payment, then redirects the customer back to their portal page — display-only, reflecting whatever the invoice's current status is; it never mutates anything itself.
8. Square calls `POST /webhooks/square` (CSRF-excluded, see `bootstrap/app.php`). `SquareWebhookController` reads the *raw* request body (never `$request->all()`, since even whitespace differences would break the HMAC) and verifies it via `Square\Utils\WebhooksHelper::verifySignature()` against `SQUARE_WEBHOOK_SIGNATURE_KEY` and the exact notification URL (`route('webhooks.square')`, which must match what's registered in the Square Developer Dashboard). **An unconfigured or missing signature key fails closed** — the request is rejected (401), never treated as "trust it anyway" — a deliberate exception to this app's usual "degrade gracefully when a provider isn't configured" rule, since this endpoint can mark invoices paid.
9. `SquareWebhookService::handle()` only acts on `payment.*` event types whose embedded payment object has reached `status = COMPLETED`; anything else (a different event type, a non-completed status) is a silent no-op. Idempotency is enforced two ways: (a) `event_logs.provider_event_id` is unique and checked first, so a redelivered event_id (Square retries until it gets a 2xx) is a no-op; (b) once a `payments` row for a given `provider_payment_id` is `completed`, a *second* completed notification under a *different* event_id (Square can send both `payment.created` and `payment.updated` for the same payment) is also a no-op — it's still logged for audit purposes, it just doesn't reprocess. A `QueryException` unique-constraint violation around the whole operation is caught as a third backstop for a genuine concurrent-delivery race.
10. On a new, verified completed payment: inside one DB transaction, a `payments` row is created/updated (`raw_payload_json` stores Square's payment object, e.g. `card_details` for the receipt's "Visa ending in 4242" line), the invoice → `paid` (`paid_at` set), and a `PaymentCompleted` event is logged with `provider_event_id`. After the transaction commits (never inside it — a rolled-back payment must never trigger a real side effect): the owner is notified via `OwnerPaymentReceivedNotification` (gated by `company_settings.payment_completed_notify`, default on), and `GenerateReceiptJob` is queued — it calls the existing `ReceiptService::generate()` (PDF render + `receipts` row, already idempotent per payment since Phase 7) then `ReceiptService::emailToCustomer()` (queues `SendReceiptEmailJob`, Phase 10's existing email pipeline). A webhook for an order/invoice we don't recognize (`payment_links.provider_order_id` has no match) is logged and ignored rather than erroring, since retrying can never resolve it. `InvoiceService::cancel()` already cancels any active payment link (best-effort remotely via `SquarePaymentService::cancelPaymentLink()`, always locally) so a cancelled invoice can never stay payable.
11. **The portal return page never marks an invoice paid** — only the verified webhook does (explicit dev rule). Square API failures and webhook rejections are caught, translated to safe messages, and logged with structured error detail to the `external` log channel — never the raw access token, the webhook signature key, or full request/response bodies.

---

## 12. Recurring Invoice Flow

**Implemented in Phase 11.** Trigger: "Make Recurring" on any non-cancelled invoice (`InvoicePolicy::makeRecurring`) — the invoice becomes the template new occurrences snapshot from, editable independently afterward (see step 4).

1. Admin creates a `recurring_invoice_profiles` row from an existing invoice (the "source" template) via `RecurringInvoiceService::createProfile()`, setting frequency/interval, `next_run_at`, `auto_send`, delivery channel, CC list, and optional end rules (`ends_at` / `max_occurrences`). A source invoice used this way cannot be deleted (`InvoicePolicy::delete()` checks `Invoice::recurringProfilesAsSource()`).
2. Laravel scheduler (`routes/console.php`) runs `invoices:process-recurring` every 5 minutes → dispatches `ProcessRecurringInvoicesJob` → `RecurringInvoiceService::processDueProfiles()`.
3. `processDueProfiles()` reads profiles where `active = true`, `next_run_at <= now()`, `locked_at IS NULL` (`RecurringInvoiceProfile::due()`), then locks each one individually via an atomic `UPDATE ... WHERE locked_at IS NULL` (the same mutex pattern as `SquarePaymentService`/`InvoiceNumberService`) — only one concurrent caller can ever win the lock for a given profile, so overlapping scheduler ticks can't double-bill. One profile failing is caught and logged; it doesn't block the rest of the batch.
4. For each locked, still-due profile, `RecurringInvoiceService::generateInvoice()` reads the source invoice's *current* line items (not a copy frozen at profile-creation time) and creates a brand-new `invoices` row + independent `invoice_items` rows via `InvoiceService::create()`, inside a DB transaction alongside the profile's schedule update and the `RecurringInvoiceGenerated` event log.
5. `next_run_at` is always recomputed from the profile's *own* previous `next_run_at` (not `now()`, so a missed tick doesn't drift the schedule) using Carbon's `addWeeks`/`addMonthsNoOverflow`/`addYearsNoOverflow`/`addDays` depending on `frequency`. If the new `next_run_at` would fall after `ends_at`, or `occurrence_count` would reach `max_occurrences`, the profile is deactivated (`active = false`) — `next_run_at` is still stored for record-keeping, it just won't be picked up again since `due()` requires `active = true`.
6. If `auto_send` is enabled, `InvoiceService::send()` is called **after** the transaction commits — dispatching `SendInvoiceEmailJob`/`SendInvoiceSmsJob` only for a generation that's actually persisted, never for one that could still roll back.
7. The lock (`locked_at`) is released in a `finally` block regardless of success or failure, so a failed generation retries on the next scheduler tick rather than staying stuck.

---

## 13. Email / SMS Flow

**Implemented in Phase 10.** Trigger: `POST /invoices/{id}/send` (admin picks channel: email/sms/both, email defaults, plus optional CC) or a resend of the same. `InvoiceService::send()` is callable for any non-paid/non-cancelled invoice (not just drafts — resending is the same action), attempts `SquarePaymentService::createOrGetPaymentLink()` best-effort (never blocks sending if Square isn't configured), logs `InvoiceSent`, then dispatches jobs per channel.

1. `EmailService`/`SmsService::sendInvoice()` (called by `SendInvoiceEmailJob`/`SendInvoiceSmsJob`) creates a `message_deliveries` row with `status = queued` *before* attempting the send, so every attempt is tracked even if the process crashes mid-send.
2. On provider success: `status = sent`, `provider_message_id` stored (Twilio message SID; email leaves this null since Laravel's Mail transports don't uniformly expose one), `sent_at` set.
3. On provider failure — including "customer has no email/phone on file" and "provider not configured yet" — `status = failed`, a safe `error_message` is stored (never secrets/tokens), and a `DeliveryFailed` event_log row is written, visible on the invoice's Activity timeline and in a dedicated "Delivery History" panel on the invoice page.
4. CC is supported on the email channel only; the parsed list is stored on the delivery row (`cc`) and passed to `Mail::cc()`.
5. Provider selection is `.env`-driven with no code changes to swap: email via Laravel's native mail config (SMTP/Mailgun/SES/SendGrid/log), SMS via `SmsProviderContract` bound in `AppServiceProvider` per `SMS_PROVIDER` (only Twilio implemented so far — adding another means one new provider class + one new `config/sms.php` block).
6. `ReceiptService::emailToCustomer()` now just dispatches `SendReceiptEmailJob` (previously sent synchronously in Phase 7); `EmailService::sendReceipt()` owns the actual send + delivery tracking + `ReceiptSent` event, mirroring the invoice path.
7. Owner notifications (portal accessed/payment clicked, Phase 9) are a separate, still-synchronous mechanism via Laravel's `Notification` system — deliberately not touched by this phase, which scoped "queued jobs for sending" to customer-facing invoice/receipt delivery only.

---

## 14. Receipt Generation Flow

1. Triggered exclusively by the `PaymentCompleted` event (never by portal access or client-side confirmation).
2. `GenerateReceiptJob` calls `ReceiptService`, which: generates a sequential `receipt_number`, gathers invoice + payment + company-branding data, and calls `InvoicePdfService` to render the receipt Blade template (logo, company details, line items, subtotal/tax/total, paid amount, payment date, provider transaction reference, receipt footer/legal text) to PDF, storing it under `storage/app/receipts/` with the path saved on `receipts.pdf_path`.
3. `ReceiptGenerated` event fires → `SendReceiptEmailJob` emails the customer the receipt with the PDF attached, and the portal's `/portal/{token}/receipt` page becomes viewable.
4. Every step logs to `event_logs` (`receipt_generated`, `receipt_sent`) for the invoice timeline.

---

## 15. Deployment Approach

**Full walkthrough: `docs/DEPLOYMENT.md`. Ready-to-copy config files and scripts: `deploy/`** (superseding this section's original forward-planning sketch — see those for the authoritative, maintained version). Summary:

Target: Ubuntu VPS (DigitalOcean-style) — Nginx + PHP-FPM + MySQL + Supervisor + cron. `deploy/nginx/invoicing-app.conf` and `deploy/php-fpm/invoicing-app-pool.conf` are app-specific (not a generic vhost): a dedicated PHP-FPM pool with `memory_limit`/`max_execution_time` sized for synchronous PDF rendering, upload limits matched to the app's own 2MB validation, and Nginx cache headers for Vite's content-hashed `public/build/` assets.

- **Environment**: `.env` built from `.env.example` on the server; no secrets ever committed. There is no seeder-created admin account — the first real account is created via normal self-registration (Laravel Breeze's register page), and its `company_settings` row is created lazily on first visit to Settings (`CompanySettingsService::getOrCreateForUser()`), not by a seeder. `database/seeders/DatabaseSeeder.php` is Laravel's default boilerplate (one factory-made test user) and is a local-dev convenience, not part of the deploy process.
- **Database**: `php artisan migrate --force` on deploy; `deploy/mysql/setup.sql` creates the database and a scoped (non-root) application user.
- **Storage**: `php artisan storage:link`; `deploy/scripts/fix-permissions.sh` sets correct ownership/permissions for logo uploads and generated PDFs.
- **Queue**: database queue driver (simplest on a single VPS; Redis is a drop-in upgrade later if ever needed), managed by Supervisor (`deploy/supervisor/invoicing-app-worker.conf`) running `php artisan queue:work --sleep=3 --tries=3 --timeout=120`. `php artisan queue:restart` after every deploy that touches job/service code — `deploy/scripts/deploy.sh` does this automatically.
- **Scheduler**: a single cron entry (`deploy/cron/invoicing-app.cron`: `* * * * * php artisan schedule:run`) drives recurring-invoice processing and the overdue sweep — no additional cron entries needed per feature.
- **HTTPS**: required, not optional — Square webhook signature verification and the customer portal both depend on a stable, real `APP_URL`. Certbot (`sudo certbot --nginx -d your-domain.tld`) obtains and auto-renews the certificate; the Square webhook endpoint must be registered against that exact URL in the Square dashboard.
- **Caching**: `config:cache`, `route:cache`, `view:cache` as part of the deploy script; remember to clear/re-cache on every deploy since cached config freezes `.env` values.
- **Backups**: `deploy/scripts/backup.sh` (installed nightly via the same cron file) writes a compressed `mysqldump` + `storage/app` tarball locally with retention pruning — real disaster recovery still requires syncing that off-box, which is deliberately left unconfigured generically (destination depends entirely on what the specific deploy has available).
- **Rollback**: no separate tool — `deploy/scripts/deploy.sh <previous-ref>` re-runs the same install/migrate/cache/restart sequence against an older commit/tag.

---

## 16. Testing Approach

**296 automated tests as of this phase** (`php artisan test`) — see `docs/QA_CHECKLIST.md` for the manual pass that covers what automation structurally can't (a real email landing in a real inbox, a Square sandbox card actually completing checkout, a PDF actually looking right, the UI actually being usable on a real phone).

- **Feature tests** (per module, `tests/Feature/...`): customers/products CRUD + ownership authorization + CSV import (valid/invalid/missing-columns rows); invoice calculation/tax/status transitions/PDF generation/paid-invoice immutability; Square payment link creation with the SDK client mocked (no real network calls in CI); portal token access + `portal_accessed`/`payment_link_clicked` event assertions, including owner-notification first-time-only gating; email/SMS queued-send with `Mail::fake()`/a faked SMS provider + CC + delivery logging + credential-leak checks on failure messages; recurring invoice next-run calculation (including month-end/leap-year edge cases), invoice generation with item snapshotting, and duplicate-run prevention (simulated overlapping scheduler ticks, plus an unfaked end-to-end test of the actual `artisan` command → job → generated-invoice chain for both `invoices:process-recurring` and `invoices:mark-overdue`); webhook tests for valid payment, duplicate webhook (idempotency, including a payment reported completed under two different event IDs), invalid signature, and unknown invoice; receipt generation (sequential numbering, idempotency, PDF storage) and sending; API tests for auth (missing/invalid/revoked token, rate limiting, cross-user 403), validation, and every endpoint including a full create → item → send round trip; cross-user authorization tests throughout (user A can never read/mutate user B's records).
- **Unit tests** (`tests/Unit/...`): `Money`/decimal calculation helpers (including float-precision edge cases like `0.1 + 0.2`), `InvoiceNumberService` sequencing, `PortalTokenGenerator` entropy.
- **Mocking strategy**: Square SDK client bound to a fake/mock in tests via the service container; SMS provider contract swapped for a fake implementation; `Queue::fake()` used to assert dispatch without executing side effects for most tests, but at least one full end-to-end (non-faked, relying on `QUEUE_CONNECTION=sync` in `phpunit.xml`) test per critical flow (recurring generation, overdue sweep) to prove the whole chain — not just each piece in isolation — actually wires together.
- **Manual acceptance QA**: `docs/QA_CHECKLIST.md`, mapped 1:1 to the client's acceptance criteria — HTTPS deployment, real sandbox email/SMS delivery with CC, Square sandbox card completing end-to-end, recurring invoice firing on schedule, help menu completeness, and event/notification proof.

---

## 17. Dashboard & Activity Timeline

**Implemented in Phase 14.** `DashboardService::summaryForUser()` is the single read path for the dashboard — every query scoped to `$user` (never a cross-account figure):

- **Total unpaid** — count + sum of `total` for `Invoice::unpaid()` (not `paid`/`cancelled`).
- **Paid this month** — count + sum of `total` for `status = paid` with `paid_at` inside the current calendar month.
- **Overdue** — count + sum of `total` for `status = overdue`. Invoices only reach `overdue` via the daily `invoices:mark-overdue` sweep (`MarkOverdueInvoicesJob` → `InvoiceService::markOverdueInvoices()`, using the existing `Invoice::pastDue()` scope: `sent`/`viewed` + `due_date` in the past), never a direct transition — mirrors the exact pattern established for `invoices:process-recurring` in Phase 11 (command dispatches a queued job). Still non-terminal: an overdue invoice can go on to be paid or cancelled normally, and a webhook payment always sets `paid` regardless of prior status.
- **Active recurring schedules** — count of `active` `recurring_invoice_profiles`, plus the next 5 by `next_run_at` for the "Upcoming Recurring Invoices" panel.
- **Recent activity** — the user's latest 10 `event_logs` rows across every invoice/customer.

**Activity timeline.** Every `EventType` case now has a `color()` method (`good`/`critical`/`info`/`neutral`) — the single source of truth for activity styling, consumed by a shared `<x-event-log-item>` component (a colored status dot + title + optional description + relative timestamp) used both on the invoice detail page's full "Activity" panel and the dashboard's cross-invoice "Recent Activity" feed (which additionally links each item back to its invoice). `email_failed`/`sms_failed` replace the earlier single `delivery_failed` case (`EmailService`/`SmsService` respectively) so the timeline distinguishes which channel failed.

**Invoice filters.** `InvoiceService::paginateForUser()` takes a `$filters` array (`search`, `status`, `customer_id`, `date_from`, `date_to` — all optional, combinable) instead of positional args, filtering `issue_date` for the date range. The customer filter dropdown intentionally includes inactive customers (unlike the invoice-creation picker) — filtering/viewing historical invoices for a since-deactivated customer must still work.

**Mobile layout.** The KPI row (`grid-cols-2 lg:grid-cols-4`) and the upcoming-recurring/recent-activity panels (`grid-cols-1 lg:grid-cols-2`) collapse to a single column on narrow viewports; the invoice filter form (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-6`) stacks the same way. Verified at a 390px viewport.

---

## Open Items Carried Into Later Phases
- Real Square sandbox credentials — pending from client, needed by Phase 8.
- Twilio account credentials — needed by Phase 10.

## Resolved Decisions (superseding earlier "open items")
- **PDF rendering (Phase 7): `barryvdh/laravel-dompdf`.** Plain-CSS, table-based Blade templates in `resources/views/pdf/` (Tailwind utility classes aren't usable — Dompdf has no flexbox/grid support). Invoice PDFs are always regenerated on demand from current data (invoices remain editable while draft); receipt PDFs are rendered once and persisted to the private `local` disk (`storage/app/private/receipts/`), matching `receipts.pdf_path`. Logo images are embedded via local filesystem path (`Storage::disk('public')->path(...)`), not a remote URL, since Dompdf handles local paths more reliably and without needing `isRemoteEnabled`.
