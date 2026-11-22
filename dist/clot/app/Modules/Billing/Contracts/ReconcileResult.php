<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Enums\SubscriptionStatus;
use Illuminate\Support\Carbon;

final class ReconcileResult
{
    public function __construct(
        public readonly ?SubscriptionStatus $status = null,
        public readonly ?Carbon $periodEnd = null,
    ) {}
}
