<?php

namespace App\Modules\Billing\Contracts;

/**
 * A normalized, signature-verified gateway event.
 */
final class InboundPayment
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $providerEventId,
        /** e.g. 'subscription.payment', 'subscription.renewed', 'payment.failed' */
        public readonly string $type,
        public readonly float $amountUsd,
        public readonly string $currency,
        /** Gateway-side reference to the subscription being paid for. */
        public readonly ?string $providerReference,
        public readonly array $payload,
    ) {}
}
