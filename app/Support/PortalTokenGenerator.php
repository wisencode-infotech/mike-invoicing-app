<?php

namespace App\Support;

final class PortalTokenGenerator
{
    /**
     * High-entropy random token used to authorize customer portal access.
     * Never derived from or predictable via the invoice/payment link ID
     * (see docs/ARCHITECTURE.md section 7).
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes((int) config('portal.token_length', 48)));
    }
}
