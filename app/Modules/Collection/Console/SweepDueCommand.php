<?php

namespace App\Modules\Collection\Console;

use App\Modules\Collection\Jobs\RunSweepJob;
use App\Modules\Collection\Models\Brand;
use Illuminate\Console\Command;

/**
 * Scheduler entry point (every 15 minutes): finds brands that are due and
 * queues a sweep for each. The cron line in the docs is the single entry.
 */
final class SweepDueCommand extends Command
{
    protected $signature = 'clot:sweep-due';

    protected $description = 'Queue sweeps for every brand that is due';

    public function handle(): int
    {
        $due = Brand::withoutWorkspaceScope()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('next_sweep_at')->orWhere('next_sweep_at', '<=', now());
            })
            ->pluck('id');

        foreach ($due as $brandId) {
            RunSweepJob::dispatch($brandId);
        }

        $this->info("Dispatched sweeps for {$due->count()} brand(s).");

        return self::SUCCESS;
    }
}
