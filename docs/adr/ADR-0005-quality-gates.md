# ADR-0005: Quality gates derived from the Mono Market checklist

**Status:** Accepted · **Date:** 2026-11-22

## Context
The Mono Market release checklist (source: Mono_Market_Flutter_Laravel_Documentation_Checklists.pdf, Envato review history through 2026-11-19) encodes hard-won CodeCanyon review lessons. CLOT adopts the Laravel, documentation, and submission gates from day one; the Flutter track does not apply.

## Decision (enforced in CI where possible)
1. Pest suite (target 2,000+ tests), tenant-isolation suite as a release gate.
2. PHPStan via Larastan (level 6 at Sprint 0, max over phases) — no baselines, no ignores.
3. Pint style gate.
4. CI jobs: tests on PHP 8.3/8.4, frontend build, **package hygiene** — no author machine paths, no committed secrets, no telemetry/phone-home (gates 2.8/2.9/1.7).
5. Installer ships with self-verification (cron ping, queue ping) and a seal (gate 2.2).
6. Payments must pass the sandbox test matrix incl. wrong-amount and replay before release (gate 2.4).
7. `docs/CHECKLIST.md` tracks all gates with evidence fields; nothing is ticked without checking the exact final archives (verification rule).

## Consequences
+ Review-history lessons are structural, not remembered under deadline pressure.
- CI additions required per phase (payment matrix, archive scan) — tracked in CHECKLIST.md.
