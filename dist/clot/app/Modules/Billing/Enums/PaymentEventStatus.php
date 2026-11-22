<?php

namespace App\Modules\Billing\Enums;

enum PaymentEventStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Failed = 'failed';
}
