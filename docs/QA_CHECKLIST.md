# Manual QA Checklist

This is the manual acceptance pass — everything here is either impossible to fully verify by an automated test (a real email actually arriving in a real inbox, a Square sandbox card actually completing checkout, a PDF actually looking right, the UI actually being usable on a real phone) or is worth a human double-check before calling a release done. The automated suite (`php artisan test`, 296 tests as of writing) is the first gate; this is the second.

Check items off in order — most later sections assume earlier ones passed (e.g. you need a customer before you can invoice them). Use a fresh sandbox account (Square sandbox, a real inbox you control, a Twilio trial number) — never real customer data or live payment credentials for a QA pass.

**Environment for this pass:** ☐ Local ☐ Staging ☐ Production — record which, and the date/tester below.

| Date | Tester | Environment | Commit/Tag | Result |
|---|---|---|---|---|
| | | | | ☐ Pass ☐ Pass with notes ☐ Fail |

---

## 1. Environment & Pre-flight

- [ ] `.env` has real (not placeholder) values for `MAIL_*`, `SQUARE_*` (sandbox is fine), and `TWILIO_*` (if SMS is in scope for this pass)
- [ ] Queue worker is running (`php artisan queue:work` or the Supervisor service) — confirm with `ps`/`supervisorctl status`, not just "I started it once"
- [ ] Scheduler is running (`php artisan schedule:work` locally, or confirm the cron entry is installed in production: `crontab -l`)
- [ ] Migrations are up to date (`php artisan migrate:status` shows nothing pending)
- [ ] `php artisan test` passes in full before starting manual QA — don't manually chase a bug the automated suite would have caught faster

## 2. Authentication & Account

- [ ] Register a new account — lands on the dashboard, a `company_settings` row exists (visit Settings, it's pre-filled with the account name)
- [ ] Log out, log back in with the same credentials
- [ ] Wrong password is rejected with a clear error, not a stack trace
- [ ] Password reset email arrives (or appears in `storage/logs/laravel.log` if `MAIL_MAILER=log`) and the reset link works
- [ ] Update company settings (name, logo upload, brand color, receipt footer) — logo appears on the next invoice PDF and in the email header

## 3. Customers

- [ ] Create a customer with all fields filled in; create a second with only the required `name`
- [ ] Edit a customer, confirm the change is saved and reflected on their existing invoices' "Bill To"
- [ ] Search/filter the customer list by name
- [ ] Deactivate a customer — confirm they no longer appear in the customer picker on **New Invoice**, but their existing invoices are untouched
- [ ] Delete a customer with no invoices — succeeds
- [ ] Attempt to delete a customer that has invoices — confirm the expected behavior (soft delete; invoices remain intact and still show the customer's name)

## 4. Products

- [ ] Create a product manually with a tax rate
- [ ] Build a CSV with: one fully valid row, one row missing `name`, one row with a non-numeric `unit_price`, one row with an extra unlisted column — import it and confirm the valid row imported, the two bad rows were skipped and listed with reasons, and the import didn't error out on the whole file
- [ ] Deactivate a product — confirm it no longer appears in the product picker on invoice line items, but an invoice that already used it is unaffected
- [ ] Pick a product on a new invoice line item and confirm name/price/tax rate pre-fill, then edit them per-line and confirm the edit doesn't change the underlying product

## 5. Invoices — Creation, Editing, Calculation

- [ ] Create an invoice with 3+ line items, mixed tax rates (some 0%, some non-zero), and confirm the displayed subtotal/tax/total match hand-calculated figures to the cent
- [ ] Edit a **draft** invoice — change quantities/prices, confirm totals recalculate correctly
- [ ] Attempt to edit a **sent** invoice's line items — confirm it's blocked (only internal notes remain editable)
- [ ] Download the invoice PDF — logo, company details, line items, totals, and terms all render correctly; no layout overlap
- [ ] Cancel a draft or sent invoice — status updates, it drops out of "unpaid" filters appropriately
- [ ] Delete a draft invoice — succeeds; attempt to delete a sent invoice — blocked

## 6. Sending Invoices (Email & SMS)

- [ ] Send a draft invoice by **Email** to a real inbox you control — it arrives, subject/body/branding look right, the "View Invoice" / pay link works and opens the customer portal
- [ ] Send by **SMS** to a real phone (Twilio) — the text arrives, is short, includes the invoice number/amount and a working portal link
- [ ] Send by **Both** — both arrive
- [ ] Send with a **CC** address on the email channel — the CC recipient receives it too
- [ ] Resend an already-sent invoice — succeeds, Delivery History shows a second entry, the invoice's Activity timeline shows both sends
- [ ] Send to a customer with no email on file (email channel) — fails gracefully with a clear reason in Delivery History and the Activity timeline; the app doesn't crash
- [ ] Stop the queue worker, send an invoice, confirm it stays "queued" and never arrives; restart the worker, confirm it then goes out — proves the queue dependency is real, not just documented

## 7. Square Payment Links & Sandbox Checkout

- [ ] With `SQUARE_ACCESS_TOKEN`/`SQUARE_LOCATION_ID` unset, confirm "Create Payment Link" fails gracefully with an on-screen message (not a 500 page) and the rest of the invoice page still works
- [ ] With sandbox credentials set, create a payment link on an invoice — it appears in the Payment Link panel with a working URL
- [ ] Open the payment link in an incognito window (simulating the customer) — the branded portal page shows correct invoice details, no login required
- [ ] Click through to Square's hosted checkout — line items and total match the invoice
- [ ] **Pay with a Square sandbox test card** (e.g. `4111 1111 1111 1111`, any future expiry/CVV) and complete checkout
- [ ] Confirm the customer is redirected back to the portal page after paying

## 8. Square Webhooks — End-to-End Payment

- [ ] Confirm the webhook is registered in the Square Developer Dashboard against this environment's exact `APP_URL`, subscribed to `payment.updated`
- [ ] After completing a sandbox payment (section 7), confirm **within a few seconds**:
  - [ ] The invoice status flips to **Paid** without any manual action
  - [ ] A `payments` row exists with the correct amount and a `provider_payment_id`
  - [ ] The invoice's Activity timeline shows a `payment_completed` entry
  - [ ] A receipt was generated and appears on the invoice (downloadable PDF)
  - [ ] The receipt email was sent to the customer (check the real inbox or `storage/logs/laravel.log`)
  - [ ] If **Notify me when a payment is completed** is enabled in Settings, the owner received an email too
- [ ] Send the same webhook event again from the Square Dashboard's "Test" button (or trigger a natural retry) — confirm it does **not** double-create a payment, double-mark-paid, or double-send a receipt
- [ ] Temporarily break `SQUARE_WEBHOOK_SIGNATURE_KEY` (wrong value) and confirm a real webhook delivery is rejected (check `storage/logs/external.log` for a rejection, and confirm the invoice does *not* get marked paid) — then restore the correct value

## 9. Customer Portal Instrumentation

- [ ] Open a payment link for the first time — invoice status flips `sent` → `viewed`, an Activity entry appears, and (if enabled) the owner gets a "first portal access" email
- [ ] Open the same link again — another Activity entry is logged, but no second owner email
- [ ] Click "Continue to Payment" — an Activity entry logs the click, and (if enabled) the owner gets a "customer started paying" email on the first click only
- [ ] Open an invalid/expired portal token — a clean 404, not an error page, and nothing is logged

## 10. Recurring Invoices

- [ ] Turn a sent invoice into a recurring schedule (try each frequency: weekly, monthly, yearly, and a custom day interval)
- [ ] Set `next_run_at` a few minutes in the future, leave the scheduler running, and confirm a new invoice is generated automatically once it's due — without touching anything manually
- [ ] Confirm the generated invoice's line items match the template's *current* items (edit the template first, then confirm the next occurrence reflects the edit)
- [ ] With `auto_send` on, confirm the generated invoice is actually sent; with it off, confirm it's created as a draft and not sent
- [ ] Pause a recurring schedule — confirm it stops generating; resume it — confirm it picks back up
- [ ] Let a schedule reach its `max_occurrences` or `ends_at` — confirm it deactivates itself afterward

## 11. Overdue Sweep & Dashboard

- [ ] Create an invoice with a due date in the past, leave it `sent`, and confirm the daily sweep (or a manual `php artisan invoices:mark-overdue` run) flips it to `overdue`
- [ ] Confirm the Dashboard's KPI tiles (Total Unpaid, Paid This Month, Overdue, Active Recurring Schedules) match what you'd count by hand from the Invoices list
- [ ] Confirm Recent Activity and Upcoming Recurring Invoices panels show real, current data
- [ ] Confirm the invoice list's status/customer/date filters narrow results correctly, individually and combined

## 12. External API

- [ ] Generate an API token from **API Tokens** — the raw token is shown once; refresh the page and confirm it's no longer visible anywhere
- [ ] Using a real HTTP client (curl/Postman, not the automated test suite): create a customer, create an invoice with items, add another item, send it, and check its status via the API — using the exact examples on the in-app Help page
- [ ] Confirm a request with no token, a garbage token, and a revoked token all get `401`
- [ ] Confirm a request for another account's resource (a real record ID from a different test account) gets `403`
- [ ] Revoke the token from the UI mid-session and confirm the next API call with it fails immediately

## 13. Help Menu & Documentation

- [ ] Every anchor link in the Help page's "On This Page" nav actually scrolls to the right section
- [ ] Read through each Help section once as if you were a new user with no prior context — confirm nothing references a feature that isn't actually there, and every command/env var name matches what's really in `.env.example`
- [ ] Cross-check README.md's setup steps by following them literally on a clean checkout (or at least read through for anything obviously stale)

## 14. Mobile / Responsive

On a real phone (not just a resized desktop browser window) or an emulator at ~390px width:

- [ ] Dashboard KPI tiles and panels stack cleanly, nothing overlaps or requires horizontal scrolling
- [ ] Invoice list filters are usable (dropdowns/date pickers work with touch)
- [ ] Creating/editing an invoice with several line items is usable — no field is unreachable or clipped
- [ ] The customer portal page (what an actual customer sees) is fully usable on mobile — this one matters most, since customers overwhelmingly open payment links from their phone

## 15. Security & Authorization Spot-Checks

- [ ] Log in as User A, note a few record IDs (a customer, an invoice, a payment, a recurring profile). Log in as User B and try to load each by URL directly — every one is a `403`/`404`, never the real data
- [ ] Confirm `storage/logs/*.log` never contains a raw Square access token, Twilio auth token, or API signature key — grep for a known credential value after exercising the flows above
- [ ] Confirm a cancelled invoice cannot be sent, paid, or have its payment link reactivated

## 16. Deployment Sign-off (production only)

Cross-reference with `docs/DEPLOYMENT.md`'s full checklist — this is the short confirmation:

- [ ] HTTPS is enforced, `APP_URL` matches the real domain exactly
- [ ] `APP_DEBUG=false` — trigger a deliberate error (e.g. a bad API request) and confirm no stack trace or file path leaks to the response
- [ ] Queue worker and scheduler are both running under a process manager, confirmed to survive a server reboot (or at least confirmed configured to `autostart`)
- [ ] Square webhook is registered against the production domain, not a staging/sandbox URL
- [ ] Database backups are actually configured and a restore has been tested at least once, not just assumed to work

---

## Recording Results

For any failed item: note it here (or in the issue tracker) with enough detail to reproduce — which environment, which step, what happened vs. what was expected, and a screenshot/log excerpt if relevant. A checklist item marked "Pass with notes" should still explain the note.
