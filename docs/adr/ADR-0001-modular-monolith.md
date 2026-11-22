# ADR-0001: Modular monolith (one deployable)

**Status:** Accepted · **Date:** 2026-11-22

## Context
CLOT ships as a source-code product buyers install on shared hosting or a VPS. VisiGoo's audited architecture (one Laravel app) is the correct shape for that market; microservices or separate frontends would exclude the shared-hosting buyer segment.

## Decision
One Laravel 13 application, internal module boundaries under `app/Modules/*`:
Tenancy, Collection, Analytics, Evidence, Action, Billing, Site, Messaging, Support, Ops, ApiPlatform.

Rules:
1. Modules communicate through interfaces and service classes only.
2. Every cross-module write goes through the owning module's service.
3. No module may reach into another module's models for writes.
4. Each module registers its own ServiceProvider in `bootstrap/providers.php`.

## Consequences
+ Upload-and-install remains the buyer experience; queue driver selectable (database default, Redis/Horizon, QStash).
- Requires discipline: enforced by the tenant-isolation test suite and PHPStan boundaries review.
