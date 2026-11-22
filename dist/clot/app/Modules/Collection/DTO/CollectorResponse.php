<?php

namespace App\Modules\Collection\DTO;

/**
 * What a collector returns for one question.
 *
 * A failed collection must still return a CollectorResponse with status=failed
 * and a human-readable reason — never throw into the pipeline, so callers can
 * store the failure and retry on the next sweep.
 */
final class CollectorResponse
{
    public const STATUS_COLLECTED = 'collected';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<int, string>  $searchesRun  Fan-out web searches the engine ran before answering.
     * @param  array<int, string>  $citedLinks  URLs the answer cited, in order of appearance.
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $answerText = null,
        public readonly ?string $modelUsed = null,
        public readonly ?string $method = null,
        /** Exact cost the provider reported for this single answer, USD. */
        public readonly ?float $providerCostUsd = null,
        public readonly array $searchesRun = [],
        public readonly array $citedLinks = [],
        /** Populated only when status = failed. */
        public readonly ?string $failureReason = null,
        /** Provider-reported token usage where available. */
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
    ) {}

    /**
     * @param  list<string>  $searchesRun
     * @param  list<string>  $citedLinks
     */
    public static function collected(
        string $answerText,
        ?string $modelUsed = null,
        ?string $method = null,
        ?float $providerCostUsd = null,
        array $searchesRun = [],
        array $citedLinks = [],
        ?int $inputTokens = null,
        ?int $outputTokens = null,
    ): self {
        return new self(
            status: self::STATUS_COLLECTED,
            answerText: $answerText,
            modelUsed: $modelUsed,
            method: $method,
            providerCostUsd: $providerCostUsd,
            searchesRun: $searchesRun,
            citedLinks: $citedLinks,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    public static function failed(string $reason): self
    {
        return new self(status: self::STATUS_FAILED, failureReason: $reason);
    }

    public function isCollected(): bool
    {
        return $this->status === self::STATUS_COLLECTED;
    }
}
