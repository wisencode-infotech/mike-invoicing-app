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

## Initial Setup

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
   - Leave `SQUARE_*` and `TWILIO_*` blank until sandbox credentials are available (see below) — the app runs fine without them until those modules are built.

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

7. **Queue worker** (required once jobs exist — email/SMS sending, recurring invoices, receipts)
   ```bash
   php artisan queue:work --sleep=3 --tries=3 --timeout=120
   ```

8. **Scheduler** (required once recurring invoices are built — Phase 11)
   ```bash
   php artisan schedule:work   # local dev
   # production: cron entry running `php artisan schedule:run` every minute, see docs/DEPLOYMENT.md
   ```

## Environment Variables Reference

All required variables are documented with comments in `.env.example`, grouped by concern:

| Group | Purpose |
|---|---|
| `DB_*` | MySQL connection |
| `MAIL_*` | Invoice/receipt email delivery — provider-agnostic (SMTP/Mailgun/SES/SendGrid) |
| `SQUARE_*` | Payment link creation, sandbox by default — see **Square Setup** below |
| `SMS_PROVIDER`, `TWILIO_*` | SMS delivery — provider-agnostic |
| `PORTAL_*` | Customer portal token length and rate limiting |
| `INVOICE_*` | Invoice numbering format and default due-date window |

Full setup notes per provider (Twilio, email) will be documented in-app under **Help** and mirrored in `docs/` as each module is built.

## Square Setup

Payment links are created via the official [Square PHP SDK](https://github.com/square/square-php-sdk) (`square/square`), wrapped by `App\Services\SquarePaymentService`.

1. Create a Square Developer account and an application at [developer.squareup.com](https://developer.squareup.com).
2. From your application's **Sandbox** tab (use Sandbox until you're ready to go live), copy:
   - **Sandbox Access Token** → `SQUARE_ACCESS_TOKEN`
   - A **Sandbox Location ID** (Locations tab) → `SQUARE_LOCATION_ID`
   - The **Application ID** → `SQUARE_APPLICATION_ID`
3. Leave `SQUARE_ENV=sandbox` until credentials are swapped for production. Switching to `SQUARE_ENV=production` **also requires** swapping `SQUARE_ACCESS_TOKEN`/`SQUARE_LOCATION_ID` for their production equivalents — the sandbox and production values are not interchangeable.
4. `SQUARE_WEBHOOK_SIGNATURE_KEY` is used once webhook handling is wired up (Phase 12); safe to leave blank until then.
5. Until these are set, "Create Payment Link" on an invoice fails gracefully with an on-screen message rather than an error page — the rest of the app works normally without Square configured.

**How it works:** clicking "Create Payment Link" on an invoice calls Square's Checkout API to create a hosted payment page, then stores the returned link ID/order ID/URL locally (`payment_links` table) alongside a separately-generated high-entropy token — never Square's own ID — that secures the branded customer portal page at `/portal/{token}`. The customer reviews the invoice there and clicks through to the actual Square-hosted checkout to pay. After paying, Square redirects them back to that same portal page, which is always **read-only** — it never marks an invoice paid itself; only a verified Square webhook can do that (Phase 12).

Square API errors are caught and logged to the `external` log channel (`storage/logs/external.log`) with structured error detail only — request/response bodies and the access token are never logged or shown to the user.

## Development Conventions

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for folder structure, database design, and module boundaries. In short: thin controllers, business logic in `app/Services`, `FormRequest` validation on every write, DB transactions around invoice/payment/receipt/recurring operations, and queued jobs for all email/SMS/receipt work.

## Testing

```bash
php artisan test
```

## License

Proprietary — built for a specific client engagement.
