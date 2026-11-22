# ADR-0004: Allowance claimed before spend; append-only ledgers

**Status:** Accepted · **Date:** 2026-11-22

## Context
Every collected answer is a paid provider call. VisiGoo's audited weakness: one install-wide daily cap; a noisy workspace starves all others. Buyers run this as their own SaaS — cost blow-ups are their direct losses.

## Decision
1. Four cap levels: installation (daily), workspace (monthly allowance), brand (runs/market budget), engine (per-engine optional cap).
2. `AnswerJob` claims an allowance-ledger row BEFORE calling the provider; DB constraint + property test: no `answers` row without a matching claim; allowance can never be overdrawn.
3. Idempotency key `hash(prompt_variant, engine, market, sweep, run_no)` with a dedup check: the same cell is never paid for twice.
4. Ledgers (allowance, credits, cost) are append-only: corrections are new rows, never edits.
5. Failed collections refund their claim and are never counted in rates.
6. Pre-flight estimate (prompts × engines × runs × markets) shown before any sweep; sweeps above a configurable threshold require approval.

## Consequences
+ Cost blow-ups structurally impossible; per-answer cost is auditable end-to-end.
- Ledger discipline adds one write per answer — acceptable at this scale.
