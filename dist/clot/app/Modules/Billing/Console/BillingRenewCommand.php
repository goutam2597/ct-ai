<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Services\BillingService;
use Illuminate\Console\Command;

/**
 * Nightly renewal: subscriptions past their period end either renew through
 * the gateway (payment event recorded) or get reconciled to the gateway's
 * state. Also the hourly reconciliation hook lands here in Phase 3.
 */
final class BillingRenewCommand extends Command
{
    protected $signature = 'clot:billing-renew';

    protected $description = 'Renew due subscriptions and reconcile with the gateway';

    public function handle(BillingService $billing): int
    {
        $renewed = $billing->renewDue();

        $this->info("Renewed {$renewed} subscription(s).");

        return self::SUCCESS;
    }
}
