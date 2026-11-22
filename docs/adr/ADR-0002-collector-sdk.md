# ADR-0002: Collector SDK — one interface, one file per engine

**Status:** Accepted · **Date:** 2026-11-22

## Context
Nine engines with two collection methods (developer API vs real interface via data providers), plus AI gateways and a demo source. Engine APIs change frequently; providers bill differently.

## Decision
`App\Modules\Collection\Contracts\Collector` is the only pipeline touchpoint:
- `collect(CollectRequest): CollectorResponse` and `test(): CollectorResponse`.
- Collectors are pure: no DB reads/writes; the request carries everything.
- Expected provider failures return `CollectorResponse::failed(reason)` — never throw.
- Only configuration errors may throw; `CollectorManager` converts them to failed responses.
- Engines register in `config/clot-collection.php`; keys live in encrypted settings/`.env`.
- FakeCollector is the default for all engines: deterministic per idempotency key, supports injected failures; CI never makes live paid calls.

## Consequences
+ Adding an engine = one class + one config line.
+ Idempotency contract enables safe retries without double-billing.
+ Recorded-fixture contract tests become trivial (collectors are pure functions).
