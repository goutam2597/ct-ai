<?php

namespace App\Modules\Collection\Services\Analysis;

use App\Modules\Collection\Enums\MatchedBy;
use App\Modules\Collection\Enums\Tone;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Competitor;

/**
 * Immutable result of scanning one answer.
 */
final class ScanResult
{
    /**
     * @param  list<array{brand: Brand|Competitor, isYou: bool, position: int, tone: Tone, matched_by: MatchedBy}>  $mentioned
     */
    public function __construct(
        public readonly array $mentioned,
        public readonly bool $namedYou,
        public readonly bool $citedYou,
        public readonly ?int $yourPosition,
        public readonly ?Tone $yourTone,
    ) {}
}
