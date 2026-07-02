@php
    $endpoints = [
        ['method' => 'POST', 'path' => '/customers', 'desc' => 'Create a customer.'],
        ['method' => 'GET', 'path' => '/customers', 'desc' => 'List customers (paginated, ?search=&status=).'],
        ['method' => 'GET', 'path' => '/customers/{id}', 'desc' => 'Read a customer.'],
        ['method' => 'PATCH', 'path' => '/customers/{id}', 'desc' => 'Update a customer (partial — send only the fields to change).'],
        ['method' => 'POST', 'path' => '/invoices', 'desc' => 'Create an invoice, optionally with a nested items[] array.'],
        ['method' => 'GET', 'path' => '/invoices/{id}', 'desc' => 'Read an invoice, including its line items.'],
        ['method' => 'POST', 'path' => '/invoices/{id}/items', 'desc' => 'Add one line item to a draft invoice.'],
        ['method' => 'POST', 'path' => '/invoices/{id}/send', 'desc' => 'Send (or resend) an invoice by email and/or SMS.'],
        ['method' => 'GET', 'path' => '/invoices/{id}/status', 'desc' => 'Check an invoice\'s status and its associated payments.'],
        ['method' => 'POST', 'path' => '/recurring-invoices', 'desc' => 'Create a recurring invoice schedule from an existing invoice.'],
        ['method' => 'GET', 'path' => '/payments/{id}', 'desc' => 'Check a single payment\'s status.'],
    ];

    $envGroups = [
        ['group' => 'APP_*', 'purpose' => 'App name, environment, debug mode, key, and public URL — APP_URL must be the exact HTTPS domain in production (Square\'s webhook signature is computed over it).'],
        ['group' => 'DB_*', 'purpose' => 'MySQL connection.'],
        ['group' => 'QUEUE_CONNECTION, CACHE_STORE, SESSION_DRIVER', 'purpose' => 'All default to the "database" driver — no Redis/Memcached required to run this app.'],
        ['group' => 'MAIL_*', 'purpose' => 'Invoice/receipt email delivery. MAIL_MAILER=log writes mail to storage/logs/laravel.log instead of sending — fine for local dev, wrong for production.'],
        ['group' => 'SQUARE_*', 'purpose' => 'Payment link creation and webhook verification — see Square Setup below.'],
        ['group' => 'SMS_PROVIDER, TWILIO_*', 'purpose' => 'SMS delivery, provider-agnostic (only Twilio implemented so far) — see SMS Setup below.'],
        ['group' => 'PORTAL_TOKEN_LENGTH, PORTAL_RATE_LIMIT_PER_MINUTE', 'purpose' => 'Customer portal token entropy and per-IP rate limiting.'],
        ['group' => 'API_TOKEN_LENGTH, API_RATE_LIMIT_PER_MINUTE', 'purpose' => 'External API bearer token entropy and per-token rate limiting.'],
        ['group' => 'INVOICE_NUMBER_PREFIX, INVOICE_NUMBER_PADDING, INVOICE_DEFAULT_DUE_DAYS, RECEIPT_NUMBER_PREFIX, RECEIPT_NUMBER_PADDING', 'purpose' => 'Invoice/receipt numbering format and the default due-date window.'],
    ];

    $toc = [
        ['id' => 'setup', 'label' => 'Setup Steps'],
        ['id' => 'environment-variables', 'label' => 'Environment Variables'],
        ['id' => 'square-setup', 'label' => 'Square Setup'],
        ['id' => 'email-setup', 'label' => 'Email Setup'],
        ['id' => 'sms-setup', 'label' => 'SMS Setup'],
        ['id' => 'recurring-invoices', 'label' => 'Recurring Invoice Setup'],
        ['id' => 'api-usage', 'label' => 'API Usage'],
        ['id' => 'products', 'label' => 'Managing Products'],
        ['id' => 'deployment', 'label' => 'Deployment Notes'],
        ['id' => 'troubleshooting', 'label' => 'Troubleshooting' ],
    ];

    $troubleshooting = [
        [
            'q' => 'Invoice emails or SMS never arrive',
            'a' => 'A queue worker must be running — sending queues the job, it doesn\'t send inline (php artisan queue:work locally, Supervisor in production). Check the invoice\'s Delivery History panel and Activity timeline for an email_failed/sms_failed entry with the reason. Locally, MAIL_MAILER=log means email is written to storage/logs/laravel.log instead of actually sending — that\'s expected, not a bug.',
        ],
        [
            'q' => 'SMS always fails',
            'a' => 'TWILIO_SID, TWILIO_AUTH_TOKEN, and TWILIO_FROM_NUMBER must all be set — until they are, every SMS send fails gracefully per customer (recorded as sms_failed) without blocking email. Also check the customer has a phone number on file.',
        ],
        [
            'q' => 'Recurring invoices, or the overdue sweep, never fire',
            'a' => 'Both depend on the scheduler running continuously (php artisan schedule:work locally, a cron entry running php artisan schedule:run every minute in production) — see Deployment Notes below. They also depend on a queue worker, since both dispatch queued jobs. Also check the recurring profile itself is still Active (not paused) and that next_run_at has actually passed. If one specific schedule stopped firing while others work fine, check whether its locked_at column is stuck set (only possible after a hard server crash mid-run) — clearing it manually unblocks it.',
        ],
        [
            'q' => '"Create Payment Link" fails on an invoice',
            'a' => 'SQUARE_ACCESS_TOKEN and SQUARE_LOCATION_ID must both be set, and must match SQUARE_ENV (sandbox credentials only work with SQUARE_ENV=sandbox, production credentials only with SQUARE_ENV=production — they are never interchangeable). Until configured, this fails with an on-screen message rather than an error page; the rest of the app keeps working.',
        ],
        [
            'q' => 'A customer paid but the invoice is still showing as unpaid',
            'a' => 'Only a verified Square webhook can mark an invoice paid — the customer\'s return to the portal page after paying never does this itself, by design. Confirm the webhook is registered in the Square Developer Dashboard against this exact APP_URL and that SQUARE_WEBHOOK_SIGNATURE_KEY is set (see Square Setup below). Rejected/failed webhook deliveries are logged to storage/logs/external.log — never with the signature key or full payload, just enough to diagnose (event id, order id).',
        ],
        [
            'q' => 'The API returns 401 Unauthorized',
            'a' => 'The bearer token is missing, mistyped, or was revoked — generate a fresh one from API Tokens and check the Authorization: Bearer &lt;token&gt; header is present exactly as shown there.',
        ],
        [
            'q' => 'The API returns 429 Too Many Requests',
            'a' => 'The rate limit (60 requests/minute per token by default) was exceeded — wait a minute, or raise API_RATE_LIMIT_PER_MINUTE if this is a legitimate high-volume integration.',
        ],
        [
            'q' => 'The API or a page returns 403 Forbidden for a record I expect to see',
            'a' => 'This is by design, not a bug — every record (invoice, customer, payment, recurring schedule) is strictly scoped to the account that owns it. There is no way, intentionally, for one account to read or modify another\'s data, including via the API.',
        ],
        [
            'q' => 'CSV product import skips rows or reports errors',
            'a' => 'Every row needs a non-empty name and a numeric unit_price — rows missing either are skipped and listed in the "skipped" report after import, everything else still imports. See Managing Products below for the full column reference.',
        ],
        [
            'q' => 'Where do I look when something fails silently?',
            'a' => 'storage/logs/laravel.log for general application errors; storage/logs/external.log specifically for failed Square/email/SMS provider calls (structured detail only — access tokens, auth tokens, and signature keys are never written to any log). Every customer-facing send is also recorded in message_deliveries, and every significant action in event_logs, both visible in the UI (Delivery History and Activity panels on an invoice).',
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Help') }}</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('On This Page') }}</h3>
            <nav class="mt-3 flex flex-wrap gap-2">
                @foreach ($toc as $item)
                    <a href="#{{ $item['id'] }}" class="rounded-full border border-gray-200 px-3 py-1 text-sm text-gray-600 hover:border-indigo-300 hover:text-indigo-600">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>

        {{-- Setup Steps --}}
        <div id="setup" class="scroll-mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Setup Steps') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('For setting up a new instance of this application (a developer or technical operator\'s task, not something done from inside the app itself):') }}</p>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-600">
                <li>{{ __('Install PHP dependencies (composer install) and frontend assets (npm install && npm run build).') }}</li>
                <li>{{ __('Copy .env.example to .env, run php artisan key:generate, and set database credentials.') }}</li>
                <li>{{ __('Create the MySQL database and run php artisan migrate.') }}</li>
                <li>{{ __('Serve the app (php artisan serve locally, or a real web server pointed at public/ in production) and register the first account.') }}</li>
                <li>{{ __('Start a queue worker — required for any email/SMS/recurring-invoice work to actually run.') }}</li>
                <li>{{ __('Start the scheduler — required for recurring invoices and the overdue sweep to ever fire.') }}</li>
            </ol>
            <p class="mt-3 text-sm text-gray-600">{{ __('The full step-by-step, including production deployment, lives in the project repository\'s README.md ("Local Setup" and "Production Setup") and docs/DEPLOYMENT.md.') }}</p>
        </div>

        {{-- Environment Variables --}}
        <div id="environment-variables" class="scroll-mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Environment Variables') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('Every variable is documented inline in .env.example — that file is the source of truth. Grouped by concern:') }}</p>
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Group') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Purpose') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($envGroups as $row)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs font-medium text-gray-900 align-top">{{ $row['group'] }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $row['purpose'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Square Setup --}}
        <div id="square-setup" class="scroll-mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Square Setup') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('Payment links and payment confirmation both go through Square. Until configured, the app still works fully for invoicing — only "Create Payment Link" is affected.') }}</p>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-600">
                <li>{{ __('Create a Square Developer account and an application at developer.squareup.com.') }}</li>
                <li>{{ __('From the application\'s Sandbox tab, copy the Sandbox Access Token, a Sandbox Location ID, and the Application ID into SQUARE_ACCESS_TOKEN, SQUARE_LOCATION_ID, and SQUARE_APPLICATION_ID.') }}</li>
                <li>{{ __('Leave SQUARE_ENV=sandbox until ready to go live. Switching to production requires swapping the access token and location ID for their production equivalents too — sandbox and production values are never interchangeable.') }}</li>
                <li>{{ __('Register a webhook (see Deployment Notes below) and copy its Signature Key into SQUARE_WEBHOOK_SIGNATURE_KEY.') }}</li>
            </ol>
            <p class="mt-3 text-sm text-gray-600">{{ __('How it works: clicking "Create Payment Link" on an invoice creates a Square-hosted checkout page and stores its URL alongside a separate high-entropy token that secures the branded customer portal page. The customer pays there, then is redirected back — that return page is always read-only; only a verified webhook ever marks an invoice paid.') }}</p>
        </div>

        {{-- Email Setup --}}
        <div id="email-setup" class="scroll-mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Email Setup') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('Invoice and receipt emails are sent via Laravel\'s Mail system — provider-agnostic, so switching providers is a .env change, never a code change.') }}</p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-600">
                <li>{{ __('Locally, MAIL_MAILER=log works out of the box — mail is written to storage/logs/laravel.log instead of actually sending, so you can inspect it without any provider account.') }}</li>
                <li>{{ __('For real delivery, set MAIL_MAILER plus the matching MAIL_HOST/MAIL_PORT/MAIL_USERNAME/MAIL_PASSWORD for SMTP, or the appropriate driver for Mailgun/SES/SendGrid.') }}</li>
                <li>{{ __('Sending is queued — a queue worker must be running or emails just sit queued and never go out.') }}</li>
                <li>{{ __('CC is supported on the email channel (comma-separated addresses on the send form).') }}</li>
                <li>{{ __('Every attempt, success or failure, is recorded in that invoice\'s Delivery History panel and Activity timeline.') }}</li>
            </ul>
        </div>

        {{-- SMS Setup --}}
        <div id="sms-setup" class="scroll-mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900">{{ __('SMS Setup') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('SMS delivery is optional and degrades gracefully — invoicing and email work fully without it.') }}</p>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-600">
                <li>{{ __('Create a Twilio account and get an Account SID, Auth Token, and a phone number (or Messaging Service SID) from the Twilio Console.') }}</li>
                <li>{{ __('Set TWILIO_SID, TWILIO_AUTH_TOKEN, and TWILIO_FROM_NUMBER (E.164 format, e.g. +15551234567).') }}</li>
                <li>{{ __('Choose SMS or Both when sending an invoice — SMS carries a short message plus the payment link, no full invoice detail.') }}</li>
            </ol>
            <p class="mt-3 text-sm text-gray-600">{{ __('Until Twilio is configured, SMS sends fail gracefully per customer (recorded as sms_failed, visible in Delivery History and Activity) without blocking email or breaking the send action.') }}</p>
        </div>

        {{-- Recurring Invoice Setup --}}
        <div id="recurring-invoices" class="scroll-mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Recurring Invoice Setup') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('Turn any non-cancelled invoice into a template that generates new invoices automatically on a schedule:') }}</p>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-600">
                <li>{{ __('Open the invoice you want to use as a template and click "Make Recurring."') }}</li>
                <li>{{ __('Choose a frequency (weekly, monthly, yearly, or a custom number of days), when the first occurrence should run, and optionally an end date or a maximum number of occurrences.') }}</li>
                <li>{{ __('Choose whether generated invoices should send automatically (auto-send) and by which channel.') }}</li>
                <li>{{ __('Manage existing schedules — pause, resume — from Recurring Invoices in the sidebar.') }}</li>
            </ol>
            <p class="mt-3 text-sm text-gray-600">{{ __('Each new invoice snapshots the template\'s current line items at generation time, not what they were when the schedule was created — editing the template later changes future occurrences, not past ones. This all depends on the scheduler and a queue worker running continuously (see Deployment Notes) — without them, schedules just sit and nothing generates.') }}</p>
        </div>

        {{-- API Usage --}}
        <div id="api-usage" class="scroll-mt-6 space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-medium text-gray-900">{{ __('API Usage') }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    {{ __('All API endpoints are prefixed with') }} <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ url('/api/v1') }}</code>.
                    {{ __('Generate a bearer token on the') }} <a href="{{ route('api-tokens.index') }}" class="text-indigo-600 hover:text-indigo-900">{{ __('API Tokens') }}</a> {{ __('page, then send it on every request:') }}
                </p>
                <pre class="mt-3 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100">Authorization: Bearer &lt;your-token&gt;
Content-Type: application/json
Accept: application/json</pre>
                <p class="mt-3 text-sm text-gray-600">{{ __('A token only lets requests act on the account that created it — no endpoint can read or modify another account\'s data.') }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-medium text-gray-900">{{ __('Response Format') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ __('Every response — success or failure — uses the same envelope:') }}</p>
                <pre class="mt-3 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100">{{ '{
  "success": true,
  "message": "Invoice created successfully.",
  "data": { "id": 123, "invoice_number": "INV-000123", "status": "draft", ... }
}' }}</pre>
                <p class="mt-3 text-sm text-gray-600">{{ __('Validation failures return a 422 with field-level errors:') }}</p>
                <pre class="mt-3 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100">{{ '{
  "success": false,
  "message": "The given data was invalid.",
  "data": { "errors": { "customer_id": ["The customer id field is required."] } }
}' }}</pre>
                <p class="mt-3 text-sm text-gray-600">{{ __('List endpoints (e.g. GET /customers) also include a top-level') }} <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">meta</code> {{ __('block with pagination info.') }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-medium text-gray-900">{{ __('Rate Limiting') }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    {{ __('Requests are limited to :limit per minute per token (unauthenticated/invalid-token requests are limited per IP). Exceeding it returns a 429.', ['limit' => config('api.rate_limit_per_minute')]) }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-medium text-gray-900">{{ __('Endpoints') }}</h3>
                <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Method') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Endpoint') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Description') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($endpoints as $endpoint)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs font-medium text-gray-900">{{ $endpoint['method'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-gray-700">{{ $endpoint['path'] }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $endpoint['desc'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ __('Full request/response field reference: see docs/ARCHITECTURE.md and README.md \"External API\" in the project repository.') }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-medium text-gray-900">{{ __('Example: Create and Send an Invoice') }}</h3>
                <pre class="mt-3 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100">curl -X POST {{ url('/api/v1/invoices') }} \
  -H "Authorization: Bearer &lt;your-token&gt;" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "issue_date": "2026-07-01",
    "due_date": "2026-07-15",
    "items": [
      { "name": "Consulting", "quantity": 2, "unit_price": 150 }
    ]
  }'

curl -X POST {{ url('/api/v1/invoices/123/send') }} \
  -H "Authorization: Bearer &lt;your-token&gt;" \
  -H "Content-Type: application/json" \
  -d '{ "channel": "email" }'</pre>
            </div>
        </div>

        {{-- Managing Products --}}
        <div id="products" class="scroll-mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Managing Products') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('Products are optional reusable line items — picking one on an invoice pre-fills the name, price, and tax rate, which can still be edited per invoice.') }}</p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-600">
                <li>{{ __('Add products one at a time from the Products page, or import a batch via CSV.') }}</li>
                <li>{{ __('CSV columns: name and unit_price are required; description, tax_rate, and active are optional (active defaults to yes if omitted). The first header row is required.') }}</li>
                <li>{{ __('Rows missing a name or a numeric unit_price are skipped, not fatal — the import still completes for every valid row, and every skipped row is listed with a reason.') }}</li>
                <li>{{ __('Deactivating a product (rather than deleting it) hides it from the picker on new invoices while keeping it — and every invoice that already used it — intact. Existing invoice line items are always an independent snapshot; changing or deleting a product later never changes an invoice that already used it.') }}</li>
            </ul>
            <p class="mt-3 text-sm text-gray-600">{{ __('For developers: products are a standard resource (App\\Models\\Product, App\\Services\\ProductService, ProductPolicy) — adding a new field follows the same pattern as any other resource in this codebase: a migration, add the column to the model\'s fillable/casts, extend the store/update FormRequests, and surface it in the create/edit views.') }}</p>
        </div>

        {{-- Deployment Notes --}}
        <div id="deployment" class="scroll-mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Deployment Notes') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('The full guide lives in docs/DEPLOYMENT.md, with ready-to-copy Nginx/PHP-FPM/MySQL/Supervisor/cron configs and deploy/backup/rollback scripts in the deploy/ directory, both in the project repository — this is the essential checklist:') }}</p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-600">
                <li>{{ __('Fresh server: deploy/scripts/provision.sh installs Nginx, PHP-FPM, MySQL, Node, Composer, Supervisor, and Certbot.') }}</li>
                <li>{{ __('HTTPS is required in practice, via Certbot (sudo certbot --nginx) — Square webhooks, the customer portal, and Square\'s hosted checkout all expect a real https:// APP_URL.') }}</li>
                <li>{{ __('A queue worker must run continuously under a process manager (deploy/supervisor/invoicing-app-worker.conf) — not a bare terminal command that dies when the terminal closes.') }}</li>
                <li>{{ __('The scheduler needs one crontab entry running php artisan schedule:run every minute (deploy/cron/invoicing-app.cron) — it decides on its own what\'s actually due.') }}</li>
                <li>{{ __('The Square webhook (notification URL {APP_URL}/webhooks/square, at least the payment.updated event) must be registered against the live domain after it\'s reachable over HTTPS — payments never mark an invoice paid until this is done.') }}</li>
                <li>{{ __('After any deploy that changes job/service code, run php artisan queue:restart — a running worker holds old code in memory otherwise. deploy/scripts/deploy.sh does this automatically, and doubles as the rollback command when pointed at a previous release.') }}</li>
                <li>{{ __('storage/ and bootstrap/cache/ must be writable by the web server user (deploy/scripts/fix-permissions.sh), and php artisan storage:link must be run once (needed for uploaded company logos to be publicly reachable).') }}</li>
                <li>{{ __('deploy/scripts/backup.sh, installed via cron, writes a nightly database + storage backup with automatic retention pruning.') }}</li>
            </ul>
        </div>

        {{-- Troubleshooting --}}
        <div id="troubleshooting" class="scroll-mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Troubleshooting') }}</h3>
            <dl class="mt-3 divide-y divide-gray-100">
                @foreach ($troubleshooting as $item)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <dt class="text-sm font-medium text-gray-900">{{ $item['q'] }}</dt>
                        <dd class="mt-1 text-sm text-gray-600">{!! $item['a'] !!}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
</x-app-layout>
