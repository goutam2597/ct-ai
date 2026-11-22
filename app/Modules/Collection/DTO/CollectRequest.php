<?php

namespace App\Modules\Collection\DTO;

/**
 * Everything a collector needs to ask one engine one question, once.
 *
 * Collectors MUST NOT read database state: the request carries everything so
 * collectors are pure, testable, and mockable.
 */
final class CollectRequest
{
    public function __construct(
        /** The exact question text asked (prompt variant already resolved). */
        public readonly string $prompt,
        /** ISO 3166-1 alpha-2 country code the buyer persona is located in. */
        public readonly string $countryCode,
        /** ISO 639-1 language code the question is asked in. */
        public readonly string $languageCode,
        /** Engine key, e.g. 'chatgpt', 'claude', 'google-ai-overviews'. */
        public readonly string $engine,
        /** Collection method label: 'api' | 'interface' | 'demo'. */
        public readonly string $method,
        /** Explicit model override; null = engine default from engine config. */
        public readonly ?string $model = null,
        /** Unique idempotency key for this answer cell. */
        public readonly string $idempotencyKey = '',
    ) {}
}
