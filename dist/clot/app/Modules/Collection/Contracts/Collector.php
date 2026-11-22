<?php

namespace App\Modules\Collection\Contracts;

use App\Modules\Collection\DTO\CollectorResponse;
use App\Modules\Collection\DTO\CollectRequest;

/**
 * The Collector SDK: one interface, one file per engine.
 *
 * Adding an answer engine to CLOT = implement this interface in a single class
 * and register it in config/clot-collection.php. Nothing else changes.
 *
 * Contract:
 * - Must be idempotent per idempotency key (safe retries must not double-bill).
 * - Must NOT throw for expected provider failures; return CollectorResponse::failed().
 * - May throw only for configuration errors (missing key, bad model) — the
 *   manager converts those into failed responses with a config reason.
 * - Must never read or write database state.
 */
interface Collector
{
    public function engineKey(): string;

    public function collect(CollectRequest $request): CollectorResponse;

    /**
     * One short paid test question to validate a key/model in the admin UI.
     * Same contract as collect(); billed like any other answer.
     */
    public function test(): CollectorResponse;
}
