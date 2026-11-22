<?php

namespace App\Modules\Collection\Jobs;

use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Services\Sweep\SweepRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RunSweepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $brandId) {}

    public function handle(SweepRunner $runner): void
    {
        $brand = Brand::withoutWorkspaceScope()->findOrFail($this->brandId);

        if (! $brand->is_active) {
            return;
        }

        $runner->runForBrand($brand);
    }
}
