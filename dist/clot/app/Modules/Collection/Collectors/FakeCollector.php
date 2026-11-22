<?php

namespace App\Modules\Collection\Collectors;

use App\Modules\Collection\Contracts\Collector;
use App\Modules\Collection\DTO\CollectorResponse;
use App\Modules\Collection\DTO\CollectRequest;

/**
 * Deterministic demo collector. Runs everywhere with zero provider cost.
 *
 * - Answer text is generated from a stable hash of prompt+run context, so the
 *   same request always yields the same answer (idempotent).
 * - Prompt variants listed in `fail_on` return failed responses — used by the
 *   test suite and by admin "simulate a failure" demos.
 * - Seeds the demo dataset used by clot:demo (Phase 2).
 */
final class FakeCollector implements Collector
{
    private const BRANDS = ['Northwind', 'Rivalsoft', 'Contoso', 'Fabrikam', 'Lumen'];

    public function __construct(
        /** @var list<string> */
        private readonly array $failOn = [],
        /** Multiplier applied to the computed sample cost. */
        private readonly float $costPerAnswerUsd = 0.01,
    ) {}

    public function engineKey(): string
    {
        return 'fake';
    }

    public function collect(CollectRequest $request): CollectorResponse
    {
        foreach ($this->failOn as $needle) {
            if ($needle !== '' && str_contains($request->prompt, $needle)) {
                return CollectorResponse::failed(
                    'fake: simulated failure for prompt containing "'.$needle.'"'
                );
            }
        }

        $seed = crc32($request->idempotencyKey !== '' ? $request->idempotencyKey : $request->prompt.'|'.$request->engine);
        $brands = $this->pickBrands($seed, $request->prompt);

        $text = sprintf(
            'For "%s", %s are strong options right now. Consider trying a free trial before deciding.',
            $request->prompt,
            implode(' and ', array_map(fn (string $b): string => $b, $brands))
        );

        $cited = array_map(
            fn (string $b): string => strtolower($b).'.example/pricing',
            array_slice($brands, 0, 2)
        );

        return CollectorResponse::collected(
            answerText: $text,
            modelUsed: 'fake-'.str_replace(' ', '-', $request->engine),
            method: $request->method,
            providerCostUsd: $this->costPerAnswerUsd,
            searchesRun: [$request->prompt.' reviews', strtolower($request->engine).' best of '.$request->countryCode],
            citedLinks: $cited,
        );
    }

    public function test(): CollectorResponse
    {
        return CollectorResponse::collected(
            answerText: 'The fake collector is working. This test call would have been billed.',
            modelUsed: 'fake-test',
            providerCostUsd: 0.0,
        );
    }

    /**
     * Deterministic brand ordering: hash decides which of the five brands are
     * named and in what order, so mention rates vary across prompts but never
     * across retries of the same request.
     *
     * @return list<string>
     */
    private function pickBrands(int $seed, string $prompt): array
    {
        $brands = self::BRANDS;
        $rot = $seed % count($brands);
        $rotated = array_slice($brands, $rot, null, true) + array_slice($brands, 0, $rot, true);
        $rotated = array_values($rotated);

        $count = 2 + (abs($seed) % 2); // 2 or 3 brands named
        $named = array_slice($rotated, 0, $count);

        // A prompt that names a tracked brand always names it back.
        foreach ($brands as $brand) {
            if (str_contains($prompt, $brand) && ! in_array($brand, $named, true)) {
                array_unshift($named, $brand);
                $named = array_slice($named, 0, $count);
                break;
            }
        }

        return $named;
    }
}
