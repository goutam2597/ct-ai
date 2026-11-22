# ADR-0003: Workspace scoping — global scope + context, memberships unscoped

**Status:** Accepted · **Date:** 2026-11-22

## Context
SaaS multi-tenancy with roles (owner/admin/member/viewer) and teams limiting brand access. Cross-tenant data leaks are the worst failure class (verified live against VisiGoo: their viewer gets 403, neighbour gets 404 — we mirror and test this).

## Decision
1. Tenant-owned models use `BelongsToWorkspace` (trait): a global scope filters reads to `WorkspaceContext::currentId()`; `creating()` stamps `workspace_id` and refuses to persist when neither context nor explicit id exists.
2. When no context exists (console, operator cross-workspace views), the scope is inert — cross-workspace access in those paths must go through services and is covered by tests.
3. `Membership` is deliberately NOT scoped: users must resolve/switch workspaces; policies resolve roles from memberships anywhere.
4. `WorkspacePolicy` derives capability from the membership row; owner ↔ membership mismatch is treated as data corruption (access denied).
5. Middleware alias `workspace.context` runs AFTER auth in route groups.
6. Tenant routes return 403 for insufficient privilege and 404 for foreign IDs.

## Consequences
+ Isolation has one enforcement point, tested by `tests/Feature/Tenancy/*`.
+ Operator impersonation keeps billing/security actions blocked (policy level).
- Every new tenant model MUST join the isolation suite (release gate).
