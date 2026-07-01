<?php

namespace App\Enums;

enum PaymentLinkStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
