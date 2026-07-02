<?php

namespace App\Http\Controllers;

use App\Services\SquareWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Square\Utils\WebhooksHelper;
use Throwable;

class SquareWebhookController extends Controller
{
    public function __construct(protected SquareWebhookService $webhooks) {}

    public function __invoke(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signatureHeader = (string) $request->header('x-square-hmacsha256-signature');

        if (! $this->hasValidSignature($rawBody, $signatureHeader)) {
            Log::channel('external')->warning('Square webhook rejected: missing or invalid signature.');

            return response()->noContent(401);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            Log::channel('external')->warning('Square webhook rejected: request body was not valid JSON.');

            return response()->noContent(400);
        }

        $this->webhooks->handle($payload);

        return response()->noContent();
    }

    /**
     * Fails closed: an unconfigured signature key is never treated as
     * "trust everything" — this endpoint can mark invoices paid, so an
     * unverifiable request must be rejected rather than silently accepted,
     * unlike other Square integrations that degrade gracefully when
     * unconfigured (creating a payment link, sending an invoice, etc.).
     */
    protected function hasValidSignature(string $rawBody, string $signatureHeader): bool
    {
        $signatureKey = (string) config('square.webhook_signature_key');

        if ($signatureKey === '' || $signatureHeader === '' || $rawBody === '') {
            return false;
        }

        try {
            return WebhooksHelper::verifySignature($rawBody, $signatureHeader, $signatureKey, route('webhooks.square'));
        } catch (Throwable) {
            return false;
        }
    }
}
