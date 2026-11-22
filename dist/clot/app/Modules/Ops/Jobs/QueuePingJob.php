<?php

namespace App\Modules\Ops\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

/**
 * Installer/health canary: when processed through the queue it touches a
 * marker file. The installer and health checks read the marker's freshness —
 * if it never appears, the queue worker is not running.
 */
final class QueuePingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        File::ensureDirectoryExists(dirname((string) config('clot-installer.queue_ping_path')));
        File::put((string) config('clot-installer.queue_ping_path'), now()->toIso8601String());
    }
}
