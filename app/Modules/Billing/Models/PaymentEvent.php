<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\PaymentEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only record of gateway money events. The provider_event_id unique
 * index makes a replayed webhook a no-op (Mono Market gate 2.4).
 *
 * @property int $id
 * @property int|null $workspace_id
 * @property int|null $subscription_id
 * @property string $provider
 * @property string $provider_event_id
 * @property string $type
 * @property string $amount_usd
 * @property string $currency
 * @property PaymentEventStatus $status
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 */
class PaymentEvent extends Model
{
    protected $fillable = [
        'workspace_id', 'subscription_id', 'provider', 'provider_event_id', 'type',
        'amount_usd', 'currency', 'status', 'payload', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentEventStatus::class,
            'amount_usd' => 'decimal:2',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
