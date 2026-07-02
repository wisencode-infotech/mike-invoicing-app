<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Safe-to-store message (goes into message_deliveries.error_message and
 * event_logs); the underlying provider exception is chained as $previous
 * and logged separately.
 */
class SmsDeliveryException extends RuntimeException
{
    //
}
