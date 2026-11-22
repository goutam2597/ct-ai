# Changelog

All notable changes to CLOT are documented here. Semantic versioning.

## 1.0.0 — 2026-11-22

### Added
- **Multi-tenant workspaces** with roles (Owner/Admin/Member/Viewer), teams,
  brand groups, custom roles, tenant-isolation test suite as a release gate
- **Collection pipeline**: sweeps with idempotent cells, per-answer jobs,
  cost ledger (append-only), allowance claimed before spend (DB-enforced),
  dedup (same cell never paid twice), in-sweep retries with partial aggregation
- **9 answer engines**: ChatGPT, Gemini, Google AI Overviews, Google AI Mode,
  Claude, Perplexity, Grok, DeepSeek, Le Chat (Mistral) — developer APIs,
  real-interface collectors (DataForSEO/Bright Data/SerpApi), and AI model
  routers (OpenRouter, Cloudflare AI Gateway, Requesty, LiteLLM)
- **Collector SDK**: one interface, one file per engine; deterministic
  FakeCollector default (free, CI-safe, injectable failures)
- **Wilson-interval metrics**: mention rate, share of voice, citation rate,
  average position, sentiment — every rate carries its 95% range with low/high;
  trends called only when ranges separate; low-confidence badge < 30 answers
- **Brand dashboard**: mention/SOV/citation cards with ranges, by-engine band
  chart, daily chart, prompts-to-win, top sources, action timeline
- **Answers browser**: full text, method+model+cost, failure reasons, filters
- **Opportunities engine**: ranked next steps (rival wins question, source
  favours rivals, weak engine) with evidence and 0-100 scores
- **Perception**: tone by engine, themes, rival themes, fact-check claims vs
  brand-entered facts
- **Answers browser**: full text, method+model+cost, failure reasons, filters
- **Alerts engine**: mention-rate drop, rival pulls ahead (with competitor
  identity), negative mentions rise, collection failing — dedup per
  workspace+rule+period, HMAC-signed webhook channels, Slack support
- **Report PDFs**: dompdf server-rendered, white-label via plan flag
- **Share links**: token-as-credential, public read-only report, noindex,
  revocable, 404 after revoke
- **Billing**: plans with feature flags/prices (Free/Starter/Growth/Agency),
  subscriptions, allowances, credits ledger (append-only), add-ons, coupons,
  multi-gateway abstraction (FakeGateway offline + Stripe adapter with full
  webhook verification matrix), refunds with approval, country routing
- **Self-verifying installer**: requirements check with per-fix guidance,
  database (test-then-write), first operator (refuses overwrite), queue
  canary self-verification, cron heartbeat, seal (404 after seal)
- **REST API v1**: Bearer keys (SHA-256 hashed, shown once), 120 req/min,
  7 read + 1 write endpoint, rates always with low/high
- **MCP server**: JSON-RPC 2.0 over HTTP, 7 read-only tools for Claude/
  ChatGPT/Cursor
- **Marketing website**: landing, pricing (live plans), free AI visibility
  check lead magnet (triple-capped), blog, docs, contact
- **Weekly digest**: markdown mailable to workspace owners (Mondays 08:00)
- **Alerts**: mention-rate drop, rival pulls ahead (competitor identity),
  negative mentions rise, collection failing — sent once via dedup keys
- **Email verification** + **password reset** (framework broker, single-use)
- **Demo commands**: `clot:demo` / `clot:demo --reset` (only demo-* accounts)
- **Browser installer** with self-lock, self-verification (queue canary +
  cron heartbeat)
- **Site check**: AI crawler access (robots.txt), llms.txt draft, 0-100 score
- **CI**: Pest (119 tests / 546 assertions), PHPStan level 6 (0 errors,
  no baselines), Pint, Vite build, package hygiene (paths/secrets/telemetry)

### Security
- CSRF, rate limits (auth + API + registration), SSRF guard on outbound
- Secrets only in .env; password-confirmation before sensitive areas
- HMAC-SHA256 signed webhooks; tenant isolation enforced by scope + policies
- Installer self-seals; rerun cannot overwrite admin or rewrite production
- Audit log for admin + billing actions; impersonation without billing access
- Package hygiene CI job (no local paths, no committed secrets, no telemetry)

### Known limitations
- Interface-level collection requires third-party providers (DataForSEO,
  Bright Data, SerpApi) — operator-configured, billed separately
- Weekly digest and Slack alerts require a configured mail provider
- Site check runs on demand, not scheduled (manual "check again" button)
