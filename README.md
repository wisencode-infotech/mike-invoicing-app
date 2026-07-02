# Invoicing App

A Laravel + MySQL invoicing application: customers, products, one-off and recurring invoices, Square payment links, a branded no-login customer portal, receipts, email/SMS delivery, and an external API.

Built phase-by-phase — see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full technical architecture and [docs/AI_PROMPTS_LOG.md](docs/AI_PROMPTS_LOG.md) (added at project completion) for the build history.

## Stack

- Laravel 13, PHP 8.3+
- MySQL
- Blade + Alpine.js (no SPA build complexity)
- Database-driven queue + Laravel scheduler
- Square PHP SDK (sandbox/live) for payment links
- Twilio for SMS, swappable via `.env`

## Requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `bcmath`, `fileinfo`, `openssl` extensions
- Composer 2.x
- MySQL 8.x (or MariaDB equivalent)
- Node.js + npm (for building frontend assets)

## Local Setup

1. **Install PHP dependencies**
   ```bash
   composer install
   ```

2. **Install frontend dependencies and build assets**
   ```bash
   npm install
   npm run build   # or `npm run dev` while developing
   ```

3. **Environment file**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Then edit `.env`:
   - Set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` for your MySQL instance.
   - Leave `SQUARE_*` and `TWILIO_*` blank until sandbox credentials are available (see below) — the app runs fine without them, degrading gracefully feature-by-feature (see the in-app Help page's Troubleshooting section once running).

4. **Create the database** (MySQL)
   ```sql
   CREATE DATABASE invoicing_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Serve the app**
   ```bash
   php artisan serve
   ```
   Visit `http://localhost:8000`, register the first (owner) account, and log in.

7. **Queue worker** (required for invoice/receipt email, SMS, recurring-invoice generation, and the overdue sweep to actually run — see **Queue Worker** below)
   ```bash
   php artisan queue:work --sleep=3 --tries=3 --timeout=120
   ```

8. **Scheduler** (required for recurring invoices and the overdue sweep to ever fire — see **Scheduler / Cron** below)
   ```bash
   php artisan schedule:work
   ```

## Production Setup

The short version — see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) and the ready-to-copy configs/scripts in [`deploy/`](deploy/README.md) (Nginx, PHP-FPM, MySQL setup, Supervisor, cron, Certbot, backups) for the full walkthrough:

1. Fresh server: `sudo bash deploy/scripts/provision.sh` installs Nginx, PHP-FPM 8.3, MySQL, Node, Composer, Supervisor, and Certbot.
2. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, a real HTTPS `APP_URL`, production `MAIL_*`/`SQUARE_*` values, `SQUARE_ENV=production`.
4. `php artisan migrate --force` (the `--force` flag is required in production — a deliberate guardrail against running migrations by accident), then `php artisan config:cache route:cache view:cache`.
5. Install the [`deploy/nginx`](deploy/nginx/invoicing-app.conf) and [`deploy/php-fpm`](deploy/php-fpm/invoicing-app-pool.conf) configs, get an SSL certificate (`sudo certbot --nginx -d your-domain.tld`), fix storage permissions (`sudo deploy/scripts/fix-permissions.sh`), and run `php artisan storage:link`.
6. Install the **queue worker** under Supervisor (not `queue:work` directly in a terminal — it must survive reboots and crashes) and the **scheduler** cron entry — both described below.
7. Register the **Square webhook** against the live `APP_URL` (see **Square Webhooks** below) — until this is done, payments will never mark an invoice paid.
8. Work through the full [deployment checklist](docs/DEPLOYMENT.md#14-full-deployment-checklist) before calling it done.

## Queue Worker

Every invoice/receipt email, every SMS, recurring-invoice generation, and the overdue-invoice sweep are dispatched onto the queue (`QUEUE_CONNECTION=database` by default) — **nothing in any of those happens without a worker running.** Locally, a plain `php artisan queue:work` in a terminal is enough. In production, run it under Supervisor (or systemd) so it restarts on crash and survives reboots — see the ready-to-use config at [`deploy/supervisor/invoicing-app-worker.conf`](deploy/supervisor/invoicing-app-worker.conf).

After any deploy that changes job/service code, run `php artisan queue:restart` — a running worker holds old code in memory and won't pick up changes on its own otherwise. This is the single easiest step to forget.

## Scheduler / Cron

Two scheduled commands live in `routes/console.php`:

| Command | Frequency | What it does |
|---|---|---|
| `invoices:process-recurring` | every 5 minutes | Dispatches `ProcessRecurringInvoicesJob`, which generates (and optionally sends) any due recurring invoices |
| `invoices:mark-overdue` | daily | Dispatches `MarkOverdueInvoicesJob`, sweeping `sent`/`viewed` invoices past their due date to `overdue` |

Locally, `php artisan schedule:work` runs both on their configured cadence for as long as it's left running. In production there is no `schedule:work` process — instead, one crontab entry drives everything:

```bash
sudo cp deploy/cron/invoicing-app.cron /etc/cron.d/invoicing-app
```

```cron
* * * * * www-data cd /var/www/invoicing-app && php artisan schedule:run >> /dev/null 2>&1
```

Laravel's scheduler itself decides what's actually due each minute, so this one line never needs to change even if more scheduled commands are added later. See [`deploy/cron/invoicing-app.cron`](deploy/cron/invoicing-app.cron) for the exact file (it also installs the nightly backup job).

## Environment Variables Reference

Every variable is documented with an inline comment in `.env.example` — that file is the source of truth. Grouped by concern:

| Group | Purpose |
|---|---|
| `APP_*` | App name, environment, debug mode, key, and public URL — `APP_URL` must be the exact HTTPS domain in production (Square's webhook signature is computed over it) |
| `DB_*` | MySQL connection |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | All default to the `database` driver — no Redis/Memcached required to run this app |
| `MAIL_*` | Invoice/receipt email delivery — provider-agnostic (SMTP/Mailgun/SES/SendGrid); `MAIL_MAILER=log` locally writes mail to `storage/logs/laravel.log` instead of sending |
| `SQUARE_*` | Payment link creation and webhook verification — see **Square Setup** and **Square Webhooks** below |
| `SMS_PROVIDER`, `TWILIO_*` | SMS delivery — provider-agnostic, only Twilio implemented so far — see **Email & SMS Delivery** below |
| `PORTAL_TOKEN_LENGTH`, `PORTAL_RATE_LIMIT_PER_MINUTE` | Customer portal token entropy and per-IP rate limiting |
| `API_TOKEN_LENGTH`, `API_RATE_LIMIT_PER_MINUTE` | External API bearer token entropy and per-token rate limiting — see **External API** below |
| `INVOICE_NUMBER_PREFIX`, `INVOICE_NUMBER_PADDING`, `INVOICE_DEFAULT_DUE_DAYS`, `RECEIPT_NUMBER_PREFIX`, `RECEIPT_NUMBER_PADDING` | Invoice/receipt numbering format and the default due-date window |

The same reference, formatted for a non-technical reader, is also available in-app under **Help → Environment Variables**.

## Square Setup

Payment links are created via the official [Square PHP SDK](https://github.com/square/square-php-sdk) (`square/square`), wrapped by `App\Services\SquarePaymentService`.

1. Create a Square Developer account and an application at [developer.squareup.com](https://developer.squareup.com).
2. From your application's **Sandbox** tab (use Sandbox until you're ready to go live), copy:
   - **Sandbox Access Token** → `SQUARE_ACCESS_TOKEN`
   - A **Sandbox Location ID** (Locations tab) → `SQUARE_LOCATION_ID`
   - The **Application ID** → `SQUARE_APPLICATION_ID`
3. Leave `SQUARE_ENV=sandbox` until credentials are swapped for production. Switching to `SQUARE_ENV=production` **also requires** swapping `SQUARE_ACCESS_TOKEN`/`SQUARE_LOCATION_ID` for their production equivalents — the sandbox and production values are not interchangeable.
4. `SQUARE_WEBHOOK_SIGNATURE_KEY` secures `POST /webhooks/square` (see **Square Webhooks** below) — copy it from the webhook subscription you create in the Developer Dashboard, pointed at `{APP_URL}/webhooks/square`. **Payments will never be marked paid without this set** — an unconfigured or invalid signature is rejected outright, it does not degrade gracefully like the other Square settings.
5. Until `SQUARE_ACCESS_TOKEN`/`SQUARE_LOCATION_ID` are set, "Create Payment Link" on an invoice fails gracefully with an on-screen message rather than an error page — the rest of the app works normally without Square configured.

**How it works:** clicking "Create Payment Link" on an invoice calls Square's Checkout API to create a hosted payment page, then stores the returned link ID/order ID/URL locally (`payment_links` table) alongside a separately-generated high-entropy token — never Square's own ID — that secures the branded customer portal page at `/portal/{token}`. The customer reviews the invoice there and clicks through to the actual Square-hosted checkout to pay. After paying, Square redirects them back to that same portal page, which is always **read-only** — it never marks an invoice paid itself; only a verified Square webhook can do that.

## Square Webhooks

`POST /webhooks/square` receives Square's payment events and is the *only* thing that can mark an invoice paid.

- **Register it** in the Square Developer Dashboard against your application: notification URL `{APP_URL}/webhooks/square`, subscribed to at least the `payment.updated` event type. Copy the resulting **Signature Key** into `SQUARE_WEBHOOK_SIGNATURE_KEY`. `APP_URL` must exactly match what's registered — Square's signature is computed over `notification_url + request_body`, so a mismatch (e.g. `http://` vs `https://`, or a different host) makes every webhook fail verification.
- On a verified `payment.*` event whose payment has reached `COMPLETED`: a `payments` row is recorded (with Square's raw payment object, safe for internal use — never rendered to the customer beyond a human-readable "Visa ending in 4242"-style summary), the invoice is marked `paid`, a `payment_completed` event is logged, the owner is optionally emailed (toggle in Settings → Owner Notifications), and a receipt PDF is generated and emailed to the customer automatically — reusing the same receipt/email pipeline as a manual resend.
- Idempotent by design: Square is allowed to redeliver the same event, or send multiple events for the same underlying payment, without double-processing (double-marking paid, double-emailing a receipt, etc.) — see `docs/ARCHITECTURE.md` section 11 for the exact guarantees.
- Failures (bad signature, malformed body, an event for an order we don't recognize) are logged to `storage/logs/external.log` and rejected/ignored safely — never the signature key or raw payload contents beyond safe identifiers (event id, order id).

Square API errors are caught and logged to the `external` log channel (`storage/logs/external.log`) with structured error detail only — request/response bodies and the access token are never logged or shown to the user.

## Email & SMS Delivery

Sending or resending an invoice queues delivery via `App\Services\EmailService` and/or `App\Services\SmsService`, depending on the channel chosen on the invoice page (Email, SMS, or Both — Email is the default). Every attempt — success or failure — is recorded in `message_deliveries` and shown in a "Delivery History" panel on the invoice; failures also appear in the invoice's Activity timeline. **A queue worker must be running** for these jobs to actually process (see setup step 7); without one, sends just sit queued.

- **Email** works out of the box locally with `MAIL_MAILER=log` (writes to `storage/logs/laravel.log` instead of sending). Configure real `MAIL_*` values for SMTP/Mailgun/SES/SendGrid in production — this was already set up in Phase 2 and needs no code changes to switch.
- **SMS** requires Twilio credentials (see `.env.example`'s `TWILIO_*` block: Account SID, Auth Token, and a From number from the [Twilio Console](https://console.twilio.com)). Until set, SMS sends fail gracefully per customer — recorded as a failed delivery with a clear on-screen reason — without blocking email or breaking the send action.
- Both invoice and receipt emails include the branded customer portal link when a Square payment link exists for that invoice; if Square isn't configured, the email/SMS still sends, just without a pay link.
- CC is supported for the email channel only (comma-separated addresses on the send form).

## Recurring Invoices

Any non-cancelled invoice can be turned into a recurring template via "Make Recurring" on the invoice page. A `recurring_invoice_profiles` row stores the schedule (weekly/monthly/yearly/custom-days, an optional `ends_at` or `max_occurrences`, `auto_send`, delivery channel, and CC list) and points back at the source invoice.

- `php artisan invoices:process-recurring` dispatches `ProcessRecurringInvoicesJob`, which finds due profiles and generates a new invoice from each — snapshotting the source invoice's *current* line items every time (so editing the template later changes future occurrences, not past ones). The scheduler (`routes/console.php`) runs this every five minutes; **`schedule:work`/cron must be running** for schedules to actually fire (see setup step 8), same as the queue worker requirement for delivery.
- Overlapping runs can't double-bill a customer: each due profile is locked (`locked_at`) with an atomic update before processing and unlocked afterward, and a profile that's already past its schedule (`next_run_at` advanced) simply won't be picked up again.
- If `auto_send` is on, the generated invoice is sent immediately via the same email/SMS pipeline as a manual send, only after the invoice is safely committed to the database.
- Manage existing schedules (pause/resume) from the **Recurring Invoices** page in the sidebar.

## Dashboard & Activity

The dashboard (first page after login) gives an at-a-glance summary, scoped entirely to your own account:

- **Total unpaid**, **Paid this month**, **Overdue**, and **Active recurring schedules** as four KPI tiles, each with the underlying invoice count.
- **Overdue** requires the daily sweep to be running: `php artisan invoices:mark-overdue` (scheduled, see setup step 8) — an invoice only ever becomes `overdue` via this sweep, never a direct action, and can still go on to be paid or cancelled normally afterward.
- **Upcoming Recurring Invoices** and **Recent Activity** panels below the KPI row — the latter pulls the last 10 events across every invoice/customer you own.
- Every invoice's own detail page has a full **Activity** timeline (all events for that invoice specifically) using the same color-coded styling as the dashboard feed: green for good news (payment completed, receipt sent), red for anything needing attention (overdue, a failed email/SMS, cancelled), blue for customer-facing engagement (sent, viewed, portal opened, pay button clicked), gray for everything else.
- The **Invoices** list supports filtering by status, customer, and issue-date range (combinable) — useful for e.g. "everything overdue for this customer" or "what did I bill last quarter."

## External API

A token-authenticated JSON API lets external systems create customers/invoices, add line items, send invoices, create recurring schedules, and check invoice/payment status — everything an accounting or e-commerce integration typically needs. The same reference below is also available in-app under **Help** once you're logged in.

### Authentication

Generate a bearer token from **API Tokens** in the sidebar — copy it immediately, it's shown once and only its hash is ever stored. Send it on every request:

```
Authorization: Bearer <your-token>
Content-Type: application/json
Accept: application/json
```

A token only ever acts on the account that created it. Revoke a token any time from the same page — revoked tokens stop working immediately. An invalid, missing, or revoked token gets a `401`.

### Response format

Every response shares one envelope, success or failure:

```json
{ "success": true, "message": "Invoice created successfully.", "data": { "id": 123, "invoice_number": "INV-000123", "status": "draft" } }
```

Validation errors return `422` with `success: false` and field errors under `data.errors`:

```json
{ "success": false, "message": "The given data was invalid.", "data": { "errors": { "customer_id": ["The customer id field is required."] } } }
```

List endpoints add a top-level `meta` block (`current_page`, `per_page`, `total`, `last_page`).

### Rate limiting

60 requests/minute per token by default (`API_RATE_LIMIT_PER_MINUTE`), falling back to per-IP limiting for invalid/missing-token requests. Exceeding it returns `429`.

### Endpoints

All paths are relative to `{APP_URL}/api/v1`.

| Method | Endpoint | Notes |
|---|---|---|
| `POST` | `/customers` | `name` required; `email`, `phone`, `billing_address`, `notes`, `active` (defaults `true`) optional. |
| `GET` | `/customers` | Paginated. `?search=` and `?status=active\|inactive` supported. |
| `GET` | `/customers/{id}` | |
| `PATCH` | `/customers/{id}` | Partial update — only send the fields you want to change. |
| `POST` | `/invoices` | `customer_id`, `issue_date`, `due_date` required; `notes`, `terms`, and an optional nested `items[]` (each: `name`, `quantity`, `unit_price` required, `product_id`/`description`/`tax_rate` optional). Created as a draft. |
| `GET` | `/invoices/{id}` | Includes line items. |
| `POST` | `/invoices/{id}/items` | Appends one line item to a **draft** invoice — same item fields as above. |
| `POST` | `/invoices/{id}/send` | `channel` required (`email`\|`sms`\|`both`), `cc_emails` optional (email channel only). Works for first send and resends. |
| `GET` | `/invoices/{id}/status` | Returns the invoice plus every associated payment. |
| `POST` | `/recurring-invoices` | `source_invoice_id` (must be one of your own, non-cancelled invoices), `frequency` (`weekly`\|`monthly`\|`yearly`\|`custom`), `interval_count`, `next_run_at` required; `ends_at`, `max_occurrences`, `auto_send` (default `true`), `delivery_channel`, `cc_emails` optional. |
| `GET` | `/payments/{id}` | |

Every resource is scoped to your account — reading or writing another account's data returns `403` (or `422` at create time, when the ownership check is part of validation, e.g. an unrecognized `customer_id`).

### Example

```bash
curl -X POST {APP_URL}/api/v1/invoices \
  -H "Authorization: Bearer <your-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "issue_date": "2026-07-01",
    "due_date": "2026-07-15",
    "items": [{ "name": "Consulting", "quantity": 2, "unit_price": 150 }]
  }'

curl -X POST {APP_URL}/api/v1/invoices/123/send \
  -H "Authorization: Bearer <your-token>" \
  -H "Content-Type: application/json" \
  -d '{ "channel": "email" }'
```

### Auditing

Every resource created through the API is logged in the same `event_logs` audit trail as its web-UI equivalent (`customer_created`, `invoice_created`, `recurring_profile_created`), visible on the relevant customer/invoice's Activity panel.

## Development Conventions

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for folder structure, database design, and module boundaries. In short: thin controllers, business logic in `app/Services`, `FormRequest` validation on every write, DB transactions around invoice/payment/receipt/recurring operations, and queued jobs for all email/SMS/receipt work.

## Testing

Tests run against a real MySQL database, not SQLite — create it once before running tests the first time:

```sql
CREATE DATABASE invoicing_app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`phpunit.xml` points at it (`DB_DATABASE=invoicing_app_test`) and sets `QUEUE_CONNECTION=sync` (queued jobs run inline during tests, so a feature test can assert on a job's side effects without a running worker) and `MAIL_MAILER=array` (mail is captured in-memory, not sent). Migrations run automatically per-test via `RefreshDatabase`.

```bash
# Full suite
php artisan test

# A single file
php artisan test tests/Feature/InvoiceTest.php

# A single test method (matches by name, substring is fine)
php artisan test --filter=test_invoices_can_be_filtered_by_status

# Everything under a directory (e.g. all API tests)
php artisan test tests/Feature/Api

# Verbose per-test output
php artisan test --testdox
```

The automated suite covers business logic, calculations, and authorization exhaustively — it can't verify that a real email actually lands in a real inbox, a Square sandbox card actually completes checkout, or the UI is actually usable on a real phone. See [docs/QA_CHECKLIST.md](docs/QA_CHECKLIST.md) for the manual pass that covers that gap before any release.

## Deployment Checklist

The condensed version — see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md#14-full-deployment-checklist) for the full one with explanations:

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` is the real HTTPS domain
- [ ] Database created and migrated (`php artisan migrate --force`)
- [ ] `MAIL_*` set to a real provider (not `log`)
- [ ] `SQUARE_*` set to production values, `SQUARE_ENV=production`
- [ ] Square webhook registered against the live `APP_URL`, `SQUARE_WEBHOOK_SIGNATURE_KEY` set
- [ ] `TWILIO_*` set, if SMS delivery is wanted
- [ ] Nginx + PHP-FPM configs installed, SSL certificate obtained via Certbot and auto-renewal confirmed
- [ ] Queue worker running under Supervisor/systemd (not a bare terminal `queue:work`)
- [ ] Cron entry installed for `php artisan schedule:run`
- [ ] `storage/` and `bootstrap/cache/` writable by the web server user; `php artisan storage:link` run
- [ ] End-to-end smoke test: create → send → pay (sandbox card) → webhook fires → receipt emailed
- [ ] Nightly backup cron entry installed (`deploy/scripts/backup.sh`) and confirmed to produce a file

## License

Proprietary — built for a specific client engagement.
