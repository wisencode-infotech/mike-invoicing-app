<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Safe-to-display message for the user; the underlying Square/network
 * exception (with any provider detail) is chained as $previous and logged
 * separately — never surfaced to the browser.
 */
class SquarePaymentException extends RuntimeException
{
    //
}
