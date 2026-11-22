<?php

namespace App\Modules\Collection\Services;

use App\Modules\Collection\Contracts\Collector;
use App\Modules\Collection\DTO\CollectorResponse;
use App\Modules\Collection\DTO\CollectRequest;
use InvalidArgumentException;

/**
 * Resolves the collector for an engine and normalizes every outcome into a
 * CollectorResponse. This is the only class the pipeline calls — engine
 * adapters never leak exceptions into collection jobs.
 */
final class CollectorManager
{
    /**
     * @param  array<string, array{class: class-string<Collector>, config?: array<string, mixed>}>  $engines
     */
    public function __construct(
        private readonly array $engines = [],
    ) {}

    public function has(string $engine): bool
    {
        return array_key_exists($engine, $this->engines);
    }

    /** @return list<string> */
    public function engineKeys(): array
    {
        return array_keys($this->engines);
    }

    public function for(string $engine): Collector
    {
        if (! $this->has($engine)) {
            throw new InvalidArgumentException("Unknown engine [{$engine}].");
        }

        $definition = $this->engines[$engine];

        /** @var Collector */
        return app($definition['class'], $definition['config'] ?? []);
    }

    public function collect(CollectRequest $request): CollectorResponse
    {
        try {
            return $this->for($request->engine)->collect($request);
        } catch (InvalidArgumentException $e) {
            return CollectorResponse::failed($e->getMessage());
        } catch (\Throwable $e) {
            // Configuration errors (bad model, missing key) land here with their
            // reason preserved; provider internals are wrapped as generic failures.
            return CollectorResponse::failed('collector error: '.$e->getMessage());
        }
    }
}
