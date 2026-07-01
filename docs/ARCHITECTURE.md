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
  Events/
    InvoiceSent.php
    PortalAccessed.php
    PaymentLinkClicked.php
    PaymentCompleted.php
    ReceiptGenerated.php
    RecurringInvoiceGenerated.php
    DeliveryFailed.php
  Listeners/
    LogInvoiceSentEvent.php
    LogPortalAccessedEvent.php  -> NotifyOwnerOfPortalAccess.php (queued)
    LogPaymentLinkClickedEvent.php -> NotifyOwnerOfPaymentClick.php (queued)
    LogPaymentCompletedEvent.php -> NotifyOwnerOfPaymentCompleted.php, DispatchReceiptGeneration.php
    LogReceiptGeneratedEvent.php -> DispatchReceiptEmail.php
    LogRecurringInvoiceGeneratedEvent.php
    LogDeliveryFailedEvent.php
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
| `ProductService` | CRUD + soft delete, active/inactive toggling, CSV parsing/validation (delegates row processing to `ImportProductsCsvJob`). |
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

---

## 6. Jobs (queued, database driver initially)

| Job | Trigger | Notes |
|---|---|---|
| `SendInvoiceEmailJob` | Invoice send action / recurring auto-send | Renders `InvoiceMail`, records `message_deliveries`, supports CC. |
| `SendInvoiceSmsJob` | Same as above when SMS channel selected | Uses `Sms\SmsService`. |
| `SendOwnerEventNotificationJob` | Listener for portal/payment/click events | Wraps owner `Notification` dispatch, respects `NotificationDispatchService` decision. |
| `ProcessRecurringInvoicesJob` | `ProcessRecurringInvoices` console command (scheduler) | Locks due profiles (`locked_at`), generates invoices in a transaction per profile, releases lock. |
| `GenerateReceiptJob` | `PaymentCompleted` event listener | Calls `ReceiptService`, then dispatches `SendReceiptEmailJob`. |
| `SendReceiptEmailJob` | After `GenerateReceiptJob` | Attaches receipt PDF, records delivery. |
| `SyncSquarePaymentStatusJob` | Optional periodic fallback | Reconciles `payment_links`/`payments` status against Square API in case a webhook was missed; not the primary path. |
| `ImportProductsCsvJob` | Product CSV import | Validates rows, upserts products, reports row-level failures. |

All jobs: `tries` + backoff configured, failures logged to `event_logs` without leaking provider secrets (per dev rules), and safe to retry (idempotent where they touch payment/invoice state).

---

## 7. Events & Listeners

Domain events decouple "something happened" from "what we do about it," keeping services/controllers thin and the audit trail centralized.

| Event | Fired By | Listeners |
|---|---|---|
| `InvoiceSent` | `InvoiceService`/send action | Log event; (email/SMS dispatch happens directly, not via this event, since it's the primary action not a side-effect) |
| `PortalAccessed` | `PortalAccessService` | Log event → conditionally queue `SendOwnerEventNotificationJob` (first-access-only logic) |
| `PaymentLinkClicked` | `PortalAccessService` | Log event → conditionally queue `SendOwnerEventNotificationJob` |
| `PaymentCompleted` | `SquareWebhookController` (via `SquarePaymentService`) | Log event → queue `SendOwnerEventNotificationJob` (always) → queue `GenerateReceiptJob` → mark invoice paid |
| `ReceiptGenerated` | `ReceiptService` | Log event → queue `SendReceiptEmailJob` |
| `RecurringInvoiceGenerated` | `RecurringInvoiceService` | Log event → (auto-send handled inline in the job, not via a further event, to keep the transaction boundary clear) |
| `DeliveryFailed` | `EmailService` / `Sms\SmsService` on provider exception | Log event (visible on invoice timeline) |

All listeners that do I/O (notifications, emails) implement `ShouldQueue`; listeners that only write `event_logs` may run sync since that write must not be lost.

---

## 8. Notifications

Laravel's `Notification` system is used only for the **owner** (a `User`, which is `Notifiable`) since the admin is an authenticated Eloquent model:

- `OwnerPortalAccessedNotification` (mail channel)
- `OwnerPaymentClickedNotification` (mail channel)
- `OwnerPaymentCompletedNotification` (mail channel)

Customer-facing sends (invoice email/SMS, receipt email) are **not** Notifications — the customer isn't a Notifiable Eloquent model (no login). These go through `Mail` classes (`InvoiceMail`, `ReceiptMail`) and `Sms\SmsService` directly, invoked from jobs, with delivery tracked in `message_deliveries` rather than Laravel's notification tables.

---

## 9. API Routes (`routes/api.php`, prefix `/api/v1`, `EnsureApiTokenIsValid` middleware, throttled)

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/customers` | Create customer |
| GET | `/api/v1/customers` | List customers |
| GET | `/api/v1/customers/{id}` | Read customer |
| PATCH | `/api/v1/customers/{id}` | Update customer |
| POST | `/api/v1/invoices` | Create invoice (optionally with nested `items[]`) |
| GET | `/api/v1/invoices/{id}` | Read invoice |
| POST | `/api/v1/invoices/{id}/items` | Add invoice item (draft only) |
| POST | `/api/v1/invoices/{id}/send` | Send invoice (email/sms/manual) |
| GET | `/api/v1/invoices/{id}/status` | Check invoice/payment status |
| POST | `/api/v1/recurring-invoices` | Create recurring invoice profile |
| GET | `/api/v1/payments/{id}` | Read payment status |

Response envelope (consistent success/error shape):
```json
{ "success": true, "message": "Invoice created successfully.", "data": { "id": 123, "invoice_number": "INV-000123", "status": "draft" } }
```
Validation errors return `success: false`, a `message`, and a `data.errors` map (standard Laravel FormRequest validation shape). Custom API `FormRequest` subclasses override `failedValidationResponse()` to enforce this envelope.

---

## 10. Web Routes (`routes/web.php`)

**Admin (auth middleware):**
`/dashboard`, `/settings`, `/customers[/create|/{id}|/{id}/edit]`, `/products[/create|/{id}|/{id}/edit|/import]`, `/invoices[/create|/{id}|/{id}/edit]` + `/invoices/{id}/send`, `/invoices/{id}/pdf`, `/invoices/{id}/cancel`, `/recurring-invoices[/create|/{id}|/{id}/edit|/{id}/toggle]`, `/api-tokens[/create|/{id}/revoke]`, `/help`.

**Portal (no auth, token-resolved, rate-limited):**
`/portal/{token}` (show invoice), `/portal/{token}/pay` (redirect to Square, logs click), `/portal/return` (Square redirect landing — display-only, never mutates payment state), `/portal/{token}/receipt` (visible once paid).

**Webhook (no auth, signature-verified, CSRF-excluded):**
`POST /webhooks/square`.

---

## 11. Square Payment Flow

**Implemented in Phase 8:** steps 1–4 below, plus a minimal read-only portal page (full instrumentation in steps 5–6 — access/click event logging, owner notifications — is Phase 9). Payment link creation is currently a manual "Create Payment Link" action on the invoice, independent of "Send"; wiring the two together is a natural follow-up once Square credentials are live and the full round trip can be tested end to end.

1. Admin creates invoice (draft) → sends it (web action or `POST /api/v1/invoices/{id}/send`). Independently, the admin (or, later, the send action) triggers **Create Payment Link**.
2. `InvoiceService` validates and locks in server-recalculated totals before any Square call is made.
3. `SquarePaymentService` creates a hosted checkout payment link via the Square PHP SDK (`checkout.paymentLinks.create`, one `Order` line item per invoice item using the item's tax-inclusive total at quantity 1 — Square's own per-line tax model isn't replicated, since our invoice/receipt/portal pages already show the correct breakdown before the customer ever reaches Square). A high-entropy `token` (`PortalTokenGenerator`, `bin2hex(random_bytes(48))`) is generated *before* the API call and used both as the Square `checkoutOptions.redirectUrl` (`route('portal.show', $token)`) and the persisted `payment_links.token` — so the customer always lands back on the exact branded page they paid from. `payment_links` row stores `provider_link_id`, `provider_order_id`, `url`, `token`, `status = active`. Returns the existing active link instead of creating a duplicate if one already exists (idempotent per invoice).
4. Customer opens the portal (`GET /portal/{token}`, public, rate-limited, no auth) → sees branded invoice details → clicks "Continue to Payment" (`GET /portal/{token}/pay`) → redirected to the Square-hosted checkout URL. Both routes 404 on an unrecognized token and never resolve invoices by ID.
5. *(Phase 9)* Customer opens the portal → `PortalAccessed` event fires (first access also transitions invoice `sent` → `viewed`) → owner notified per preference.
6. *(Phase 9)* Customer clicks "Pay" → `PaymentLinkClicked` event fires → owner notified per preference.
7. Square processes the sandbox/live card payment, then redirects the customer back to their portal page — display-only, reflecting whatever the invoice's current status is; it never mutates anything itself.
8. *(Phase 12)* Square calls `POST /webhooks/square`. `SquareWebhookController` verifies the signature (`SQUARE_WEBHOOK_SIGNATURE_KEY`) and checks `provider_event_id` against `event_logs` for idempotency before processing.
9. *(Phase 12)* On a valid, new `payment.completed`-equivalent event: `payments` row created, invoice status → `paid`, `PaymentCompleted` event fires → owner notified, `GenerateReceiptJob` queued. `InvoiceService::cancel()` already cancels any active payment link (best-effort remotely via `SquarePaymentService::cancelPaymentLink()`, always locally) so a cancelled invoice can never stay payable.
10. **The portal return page never marks an invoice paid** — only the verified webhook does (explicit dev rule). Square API failures are caught, translated to a safe user-facing `SquarePaymentException` message, and logged with structured error detail (category/code/detail, status code) to the `external` log channel — never the raw access token or full request/response bodies.

---

## 12. Recurring Invoice Flow

1. Admin creates a `recurring_invoice_profiles` row from an existing invoice (the "source" template), setting frequency/interval, `next_run_at`, `auto_send`, delivery channel, CC list, and optional end rules (`ends_at` / `max_occurrences`).
2. Laravel scheduler (`routes/console.php`) runs `ProcessRecurringInvoices` every 1–5 minutes → dispatches `ProcessRecurringInvoicesJob`.
3. The job selects profiles where `active = true`, `next_run_at <= now()`, `locked_at IS NULL`, and locks each one (`locked_at = now()`) before processing — preventing duplicate runs from overlapping scheduler ticks.
4. For each due profile, `RecurringInvoiceService` creates a new `invoices` row + snapshotted `invoice_items` from the source, inside a DB transaction.
5. If `auto_send` is enabled, the invoice is sent via the configured channel (reuses `SendInvoiceEmailJob`/`SendInvoiceSmsJob`).
6. `last_run_at`, `next_run_at` (recomputed from frequency/interval), and `occurrence_count` are updated; `locked_at` is cleared; `RecurringInvoiceGenerated` event fires and is logged.
7. If `ends_at` has passed or `max_occurrences` reached, the profile is deactivated (`active = false`) instead of computing a further `next_run_at`.

---

## 13. Email / SMS Flow

1. Trigger: manual invoice send, recurring auto-send, or receipt generation.
2. A `message_deliveries` row is created with `status = queued` *before* the job runs, so every attempt is tracked even if it later fails.
3. `SendInvoiceEmailJob` / `SendInvoiceSmsJob` (or `SendReceiptEmailJob`) executes, calling `EmailService` or `Sms\SmsService`.
4. On provider success: `message_deliveries.status = sent`, `provider_message_id` stored, `sent_at` set.
5. On provider failure: `message_deliveries.status = failed`, `error_message` stored (sanitized — no secrets), `DeliveryFailed` event fires and is visible on the invoice's event timeline.
6. CC recipients are supported on invoice email; `cc` stored on the delivery row for audit.
7. Provider selection (SMTP/Mailgun/SES/SendGrid for email; Twilio for SMS) is entirely `.env`-driven — no code change needed to swap providers.

---

## 14. Receipt Generation Flow

1. Triggered exclusively by the `PaymentCompleted` event (never by portal access or client-side confirmation).
2. `GenerateReceiptJob` calls `ReceiptService`, which: generates a sequential `receipt_number`, gathers invoice + payment + company-branding data, and calls `InvoicePdfService` to render the receipt Blade template (logo, company details, line items, subtotal/tax/total, paid amount, payment date, provider transaction reference, receipt footer/legal text) to PDF, storing it under `storage/app/receipts/` with the path saved on `receipts.pdf_path`.
3. `ReceiptGenerated` event fires → `SendReceiptEmailJob` emails the customer the receipt with the PDF attached, and the portal's `/portal/{token}/receipt` page becomes viewable.
4. Every step logs to `event_logs` (`receipt_generated`, `receipt_sent`) for the invoice timeline.

---

## 15. Deployment Approach

Target: Ubuntu VPS (DigitalOcean-style) — Nginx + PHP-FPM + MySQL + Supervisor + Certbot + cron.

- **Environment**: `.env` built from `.env.example` on the server; no secrets ever committed.
- **Database**: `php artisan migrate --force` on deploy; a seeder creates the initial admin user + default `company_settings` row.
- **Storage**: `php artisan storage:link`; correct ownership/permissions for logo uploads and generated PDFs.
- **Queue**: database queue driver initially (simplest on a single VPS; Redis is a drop-in upgrade later), managed by Supervisor running `php artisan queue:work --sleep=3 --tries=3 --timeout=120`.
- **Scheduler**: a single cron entry (`* * * * * php artisan schedule:run`) drives recurring-invoice processing and the overdue sweep — no additional cron entries needed per feature.
- **HTTPS**: Certbot-issued SSL, forced HTTPS, secure cookies in production. The Square webhook endpoint must be a public HTTPS URL registered in the Square dashboard.
- **Caching**: `config:cache`, `route:cache`, `view:cache` as part of the deploy script; remember to clear/re-cache on every deploy since cached config freezes `.env` values.
- **Backups**: database + `storage/app` backups recommended before go-live and on a recurring schedule.
- Full command list and Nginx/Supervisor config templates land in `docs/DEPLOYMENT.md` (Phase 17).

---

## 16. Testing Approach

- **Feature tests** (per module, `tests/Feature/...`): customers/products CRUD + ownership authorization; invoice calculation/tax/status transitions/PDF generation/paid-invoice immutability; Square payment link creation with the SDK client mocked (no real network calls in CI); portal token access + `portal_accessed`/`payment_link_clicked` event assertions; email/SMS queued-send with `Mail::fake()`/a faked SMS provider + CC + delivery logging; recurring invoice next-run calculation, invoice generation, and duplicate-run prevention (simulate overlapping scheduler ticks); webhook tests for valid payment, duplicate webhook (idempotency), invalid signature, and unknown invoice; API tests for auth, validation, and each endpoint; cross-user authorization tests (user A can never read/mutate user B's records).
- **Unit tests** (`tests/Unit/...`): `Money`/decimal calculation helpers, enum transition helpers, `InvoiceNumberService` sequencing, `RecurringInvoiceService` next-run-date math (including edge cases: month-end dates, leap years, `ends_at`/`max_occurrences` boundaries).
- **Mocking strategy**: Square SDK client bound to a fake/mock in tests via the service container; SMS provider contract swapped for a fake implementation; `Queue::fake()` and `Event::fake()` used to assert dispatch without executing side effects, with at least one full end-to-end (non-faked) test per critical flow (invoice → pay → webhook → receipt) run against local fakes to prove the whole chain wires together.
- **Manual acceptance QA**: tracked separately in `docs/QA_CHECKLIST.md` (Phase 16), mapped 1:1 to the client's acceptance criteria — HTTPS deployment, real sandbox email/SMS delivery with CC, Square sandbox card completing end-to-end, recurring invoice firing on schedule, help menu completeness, and event/notification proof.

---

## Open Items Carried Into Later Phases
- Real Square sandbox credentials — pending from client, needed by Phase 8.
- Twilio account credentials — needed by Phase 10.

## Resolved Decisions (superseding earlier "open items")
- **PDF rendering (Phase 7): `barryvdh/laravel-dompdf`.** Plain-CSS, table-based Blade templates in `resources/views/pdf/` (Tailwind utility classes aren't usable — Dompdf has no flexbox/grid support). Invoice PDFs are always regenerated on demand from current data (invoices remain editable while draft); receipt PDFs are rendered once and persisted to the private `local` disk (`storage/app/private/receipts/`), matching `receipts.pdf_path`. Logo images are embedded via local filesystem path (`Storage::disk('public')->path(...)`), not a remote URL, since Dompdf handles local paths more reliably and without needing `isRemoteEnabled`.
