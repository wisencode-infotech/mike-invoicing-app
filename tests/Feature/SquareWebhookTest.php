<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentLinkStatus;
use App\Mail\ReceiptMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\OwnerPaymentReceivedNotification;
use App\Support\PortalTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class SquareWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE_KEY = 'test-signature-key';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function invoiceWithPaymentLink(string $orderId = 'order_123'): Invoice
    {
        $user = User::factory()->create();
        $user->companySetting()->create(['company_name' => 'Acme Co']);
        $customer = Customer::factory()->for($user)->create(['email' => 'client@example.test']);
        $invoice = Invoice::factory()->for($user)->for($customer)->sent()->create([
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100,
        ]);
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0,
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'sort_order' => 0,
        ]);
        $invoice->paymentLinks()->create([
            'provider' => 'square',
            'provider_link_id' => 'link_1',
            'provider_order_id' => $orderId,
            'url' => 'https://sq.test/1',
            'token' => PortalTokenGenerator::generate(),
            'status' => PaymentLinkStatus::Active,
        ]);

        return $invoice->fresh(['items', 'customer', 'user', 'paymentLinks']);
    }

    /**
     * @return array<string, mixed>
     */
    private function completedPaymentPayload(
        string $eventId,
        string $orderId,
        string $paymentId,
        string $type = 'payment.updated',
        int $amountCents = 10000,
    ): array {
        return [
            'merchant_id' => 'merchant_1',
            'type' => $type,
            'event_id' => $eventId,
            'created_at' => now()->toIso8601String(),
            'data' => [
                'type' => 'payment',
                'id' => $paymentId,
                'object' => [
                    'payment' => [
                        'id' => $paymentId,
                        'order_id' => $orderId,
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => $amountCents, 'currency' => 'USD'],
                        'card_details' => ['card' => ['card_brand' => 'VISA', 'last_4' => '4242']],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(array $payload, ?string $signatureKey = self::SIGNATURE_KEY, ?string $signatureOverride = null): \Illuminate\Testing\TestResponse
    {
        config(['square.webhook_signature_key' => $signatureKey]);

        $body = json_encode($payload);
        $signature = $signatureOverride ?? ($signatureKey
            ? base64_encode(hash_hmac('sha256', route('webhooks.square').$body, $signatureKey, true))
            : 'irrelevant');

        return $this->postJson('/webhooks/square', $payload, [
            'X-Square-Hmacsha256-Signature' => $signature,
        ]);
    }

    // ---- Successful processing --------------------------------------

    public function test_successful_webhook_marks_invoice_paid_and_records_the_payment(): void
    {
        $invoice = $this->invoiceWithPaymentLink('order_abc');
        $payload = $this->completedPaymentPayload('evt_1', 'order_abc', 'pay_1', amountCents: 10000);

        $response = $this->postWebhook($payload);

        $response->assertNoContent();
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'provider_payment_id' => 'pay_1',
            'status' => 'completed',
            'amount' => '100.00',
            'currency' => 'USD',
        ]);
    }

    public function test_successful_webhook_logs_a_payment_completed_event_with_the_provider_event_id(): void
    {
        $invoice = $this->invoiceWithPaymentLink('order_abc');
        $payload = $this->completedPaymentPayload('evt_2', 'order_abc', 'pay_2');

        $this->postWebhook($payload);

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::PaymentCompleted->value,
            'provider_event_id' => 'evt_2',
        ]);
    }

    public function test_successful_webhook_notifies_the_owner_by_default(): void
    {
        Notification::fake();
        $invoice = $this->invoiceWithPaymentLink('order_abc');
        $payload = $this->completedPaymentPayload('evt_3', 'order_abc', 'pay_3');

        $this->postWebhook($payload);

        Notification::assertSentTo($invoice->user, OwnerPaymentReceivedNotification::class);
    }

    public function test_owner_is_not_notified_when_preference_is_disabled(): void
    {
        Notification::fake();
        $invoice = $this->invoiceWithPaymentLink('order_abc');
        $invoice->user->companySetting()->update(['payment_completed_notify' => false]);
        $payload = $this->completedPaymentPayload('evt_4', 'order_abc', 'pay_4');

        $this->postWebhook($payload);

        Notification::assertNotSentTo($invoice->user, OwnerPaymentReceivedNotification::class);
    }

    public function test_successful_webhook_generates_and_emails_a_receipt_to_the_customer(): void
    {
        Mail::fake();
        $invoice = $this->invoiceWithPaymentLink('order_abc');
        $payload = $this->completedPaymentPayload('evt_5', 'order_abc', 'pay_5');

        $this->postWebhook($payload);

        $this->assertDatabaseHas('receipts', ['invoice_id' => $invoice->id]);
        Mail::assertSent(ReceiptMail::class, fn ($mail) => $mail->hasTo('client@example.test'));
    }

    // ---- Idempotency -------------------------------------------------

    public function test_duplicate_webhook_with_the_same_event_id_is_ignored(): void
    {
        Notification::fake();
        $invoice = $this->invoiceWithPaymentLink('order_abc');
        $payload = $this->completedPaymentPayload('evt_dup', 'order_abc', 'pay_dup');

        $first = $this->postWebhook($payload);
        $eventLogCountAfterFirst = \App\Models\EventLog::count();
        $second = $this->postWebhook($payload);

        $first->assertNoContent();
        $second->assertNoContent();
        $this->assertDatabaseCount('payments', 1);
        // The second delivery of the same event_id must add nothing further
        // (PaymentCompleted + the receipt-generation events from the first
        // call only — see ReceiptService/EmailService).
        $this->assertSame($eventLogCountAfterFirst, \App\Models\EventLog::count());
        Notification::assertSentToTimes($invoice->user, OwnerPaymentReceivedNotification::class, 1);
    }

    public function test_the_same_payment_reported_completed_under_a_different_event_id_is_not_reprocessed(): void
    {
        // Square can send payment.created then payment.updated for the same
        // underlying payment — different event_id, same payment id.
        Notification::fake();
        $invoice = $this->invoiceWithPaymentLink('order_abc');

        $this->postWebhook($this->completedPaymentPayload('evt_created', 'order_abc', 'pay_x', type: 'payment.created'));
        $eventLogCountAfterFirst = \App\Models\EventLog::count();
        $this->postWebhook($this->completedPaymentPayload('evt_updated', 'order_abc', 'pay_x', type: 'payment.updated'));

        $this->assertDatabaseCount('payments', 1);
        // The first event actually processed the payment...
        $this->assertDatabaseHas('event_logs', ['provider_event_id' => 'evt_created']);
        // ...the second, reporting the same already-completed payment under
        // a different event_id, adds nothing further.
        $this->assertSame($eventLogCountAfterFirst, \App\Models\EventLog::count());
        Notification::assertSentToTimes($invoice->user, OwnerPaymentReceivedNotification::class, 1);
    }

    // ---- Invalid webhook -----------------------------------------------

    public function test_invalid_signature_is_rejected_and_does_not_mutate_anything(): void
    {
        $invoice = $this->invoiceWithPaymentLink('order_abc');
        $payload = $this->completedPaymentPayload('evt_bad', 'order_abc', 'pay_bad');

        $response = $this->postWebhook($payload, signatureOverride: 'not-the-right-signature');

        $response->assertStatus(401);
        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        config(['square.webhook_signature_key' => self::SIGNATURE_KEY]);
        $payload = $this->completedPaymentPayload('evt_nohdr', 'order_abc', 'pay_nohdr');

        $response = $this->postJson('/webhooks/square', $payload);

        $response->assertStatus(401);
    }

    public function test_unconfigured_signature_key_rejects_all_webhooks(): void
    {
        $invoice = $this->invoiceWithPaymentLink('order_abc');
        $payload = $this->completedPaymentPayload('evt_unconf', 'order_abc', 'pay_unconf');

        $response = $this->postWebhook($payload, signatureKey: null);

        $response->assertStatus(401);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_signature_failure_is_logged_without_leaking_the_signature_key(): void
    {
        Log::shouldReceive('channel')->with('external')->andReturnSelf();
        Log::shouldReceive('warning')->once()->with(
            Mockery::on(fn ($message) => is_string($message) && ! str_contains($message, self::SIGNATURE_KEY)),
        );

        $payload = $this->completedPaymentPayload('evt_log', 'order_abc', 'pay_log');

        $response = $this->postWebhook($payload, signatureOverride: 'garbage');

        $response->assertStatus(401);
    }

    // ---- Unknown invoice -------------------------------------------------

    public function test_webhook_for_an_unrecognized_order_is_ignored_safely(): void
    {
        // No invoice/payment_link created for this order_id at all.
        $payload = $this->completedPaymentPayload('evt_unknown', 'order_does_not_exist', 'pay_unknown');

        $response = $this->postWebhook($payload);

        $response->assertNoContent();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('event_logs', 0);
    }

    // ---- Non-completed / non-payment events are no-ops -------------------

    public function test_non_completed_payment_status_is_ignored(): void
    {
        $invoice = $this->invoiceWithPaymentLink('order_abc');
        $payload = $this->completedPaymentPayload('evt_pending', 'order_abc', 'pay_pending');
        $payload['data']['object']['payment']['status'] = 'APPROVED';

        $response = $this->postWebhook($payload);

        $response->assertNoContent();
        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unrelated_event_types_are_ignored(): void
    {
        $payload = [
            'merchant_id' => 'merchant_1',
            'type' => 'customer.updated',
            'event_id' => 'evt_other',
            'data' => ['type' => 'customer', 'id' => 'cust_1', 'object' => []],
        ];

        $response = $this->postWebhook($payload);

        $response->assertNoContent();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('event_logs', 0);
    }
}
