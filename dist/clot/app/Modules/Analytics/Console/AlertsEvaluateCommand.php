<?php

namespace App\Modules\Analytics\Console;

use App\Modules\Analytics\Services\AlertEvaluator;
use Illuminate\Console\Command;

/**
 * The morning check, scheduled every 15 minutes (cheap: the rules short-circuit
 * on small samples; dedup keys make re-evaluation free).
 */
final class AlertsEvaluateCommand extends Command
{
    protected $signature = 'clot:alerts-evaluate';

    protected $description = 'Evaluate alert rules for every active brand';

    public function handle(AlertEvaluator $evaluator): int
    {
        $created = $evaluator->evaluateAll();

        $this->info("Alerts created: {$created}.");

        return self::SUCCESS;
    }
}
