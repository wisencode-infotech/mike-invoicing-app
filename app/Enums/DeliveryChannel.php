<?php

namespace App\Enums;

/**
 * Used by recurring_invoice_profiles.delivery_channel (all three cases) and
 * message_deliveries.channel, where only Email/Sms are ever recorded since a
 * "both" send is logged as two separate delivery rows.
 */
enum DeliveryChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Both = 'both';
}
