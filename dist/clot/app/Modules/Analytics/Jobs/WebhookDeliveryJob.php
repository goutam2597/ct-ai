<?php

namespace App\Modules\Analytics\Jobs;

use App\Modules\Analytics\Models\AlertChannel;
use App\Modules\Analytics\Models\AlertEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Delivers recent alert events to one webhook channel, signed so the
 * receiver can trust them: HMAC-SHA256 of the raw body in X-Clot-Signature.
 * Failures are left to the queue's retry policy; the alert is never lost
 * (it stays in the bell regardless).
 */
final class WebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(public readonly int $channelId) {}

    public function handle(): void
    {
        $channel = AlertChannel::withoutWorkspaceScope()->findOrFail($this->channelId);

        if (! $channel->is_active || $channel->type !== 'webhook') {
            return;
        }

        $events = AlertEvent::query()
            ->where('workspace_id', $channel->workspace_id)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (AlertEvent $event): array => [
                'id' => $event->id,
                'rule' => $event->rule,
                'title' => $event->title,
                'brand_id' => $event->brand_id,
                'body' => $event->body,
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->all();

        if ($events === []) {
            return;
        }

        $payload = json_encode(['workspace_id' => $channel->workspace_id, 'events' => $events]);
        $signature = hash_hmac('sha256', (string) $payload, (string) ($channel->secret ?? config('app.key')));

        Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'CLOT-Webhook',
                'X-Clot-Signature' => $signature,
            ])
            ->post($channel->target, (array) json_decode((string) $payload, true));
    }
}
