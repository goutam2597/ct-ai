# CLOT — Documentation Guide

> Full HTML guide (~115-page class). Architecture first, then screen-by-screen
> workflows, provider connection guides, API reference, and FAQ.

---

## 1. Architecture

### How the system works

CLOT is a **modular monolith** — one Laravel 13 deployable with eleven internal
modules (Tenancy, Collection, Analytics, Evidence, Action, Billing, Site,
Messaging, Support, Ops, ApiPlatform).

**The collection pipeline** is the heart:

1. The scheduler checks every 15 minutes for brands that are due and queues
   their sweep.
2. Each answer is one background job that:
   - checks the installation-wide daily spend cap,
   - claims one answer from the workspace's monthly allowance,
   - calls the AI provider via a Collector (one file per engine),
   - stores the full answer with provenance (method, model, cost, searches),
   - scans for brand mentions (name/alias/domain, position, tone) and
     citations (yours/competitor/third-party),
   - refunds the claim if collection fails — failures are never counted.
3. A repeated sweep on the same day only fills in what is missing: the same
   answer is never paid for twice.

**Cost governance** has four levels: installation daily cap → workspace
monthly allowance → brand runs/markets budget → engine cap. A claim happens
BEFORE the provider call; the DB unique index makes double-claims impossible.

**Statistics**: every rate carries a 95% Wilson interval. A trend is called
"up" or "down" only when the two periods' ranges do not overlap. Under 30
answers a rate is marked low confidence and excluded from trend calls.

### Multi-tenancy

Every workspace-owned model uses the `BelongsToWorkspace` trait: a global
scope filters reads to the current workspace; `creating()` stamps the
workspace_id and refuses to persist when neither context nor explicit id
exists. Memberships are deliberately NOT scoped (users must be able to
switch workspaces). The isolation test suite is a release gate.

### Collector SDK

One interface, one file per engine. Collectors are pure (no DB access),
idempotent per idempotency key, and return `CollectorResponse::failed()`
instead of throwing for expected provider failures. The FakeCollector is
the default for all nine engines so a fresh install runs end-to-end free.

Adding an engine = one class + one line in `config/clot-collection.php`.

---

## 2. Installation

### Prerequisites

| Requirement | Notes |
|---|---|
| PHP 8.3+ | with pdo, mbstring, openssl, curl, fileinfo, gd, zip, intl |
| MySQL 8 / MariaDB 10.6 | PostgreSQL and SQLite also supported |
| One cron entry | every minute |
| Queue worker | or Upstash QStash on shared hosting |
| HTTPS | required for live payments and webhooks |

### Browser installer

1. Upload the package to your web root.
2. Visit `https://your-domain/setup`.
3. Step 1 — server requirements: the installer checks each one and shows
   per-fix guidance for cPanel/Plesk.
4. Step 2 — database: test the connection, write .env, run migrations.
5. Step 3 — first operator account (refuses overwrite).
6. Step 4 — verify & seal: dispatches a queue canary, asserts it processed,
   writes `install.lock`, shows the cron entry to copy.

The installer **seals itself** and returns 404 on every subsequent visit.
Manual route: `php artisan clot:install-seal --name=... --email=... --password=...`

### Cron entry

```
* * * * * php /home/USERNAME/public_html/artisan schedule:run >> /dev/null 2>&1
```

Replace `/home/USERNAME/` with your actual cPanel home directory. The
installer shows the exact line for your server and verifies via a heartbeat
that the cron is actually running.

### Queue worker (VPS)

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Or use Laravel Horizon (`composer require laravel/horizon`). On shared
hosting without a persistent process, configure Upstash QStash to call
`https://your-domain/api/queue/qstash`.

---

## 3. Connecting providers

### AI providers (Admin → AI visibility → Answer engines)

For each engine, pick a data source: developer API, real-interface collector
(DataForSEO / Bright Data / SerpApi), or an AI model router (OpenRouter,
Cloudflare AI Gateway, Requesty, LiteLLM). Enter your API key, model, and
per-call price. The test button sends a short question — billed like an answer.

The default for all engines is the **FakeCollector**: deterministic sample
data, free, and used by CI. Switch to real collectors when you have keys.

### Payment providers (Admin → Billing → Payment providers)

One setting switches the active gateway. Supported: Stripe, Paddle, Polar,
Lemon Squeezy, Creem, Dodo Payments. Each adapter implements the same
`PaymentGateway` contract (checkout, webhook verification, reconciliation).

Configure the webhook URL on the provider's dashboard:
`https://your-domain/webhooks/billing/{provider}`

Test mode: use the provider's sandbox keys. The test matrix (success, failure,
duplicate, wrong amount, replay) is covered by the test suite for the webhook
verification and amount-comparison layers.

### Email delivery (Admin → Email → Providers)

Nine providers with a test button: SMTP, Resend, Postmark, SendGrid, Mailtrap,
Brevo, Amazon SES, Mailchimp, OneSignal. Set the provider and key, click test,
check the delivery log.

---

## 4. Pricing your plans

The plans (Free/Starter/Growth/Agency) are seeded with sample prices and
limits — edit them in Admin → Billing → Plans. Every plan has feature flags
that drive the entire UI: engines offered, BYOK, white-label, SSO, daily
frequency, API access.

**The math**: `answers/month = prompts × engines × runs × sweeps/week × 4.33`

At US$0.01 per answer: 20 prompts × 4 engines × 3 runs × 2 sweeps ≈ 2,080
answers/mo ≈ US$21 provider cost. Price accordingly — the builder's tooling
shows the real cost per answer per engine so you can verify margin.

---

## 5. REST API v1

Bearer key auth (`vg_…`), 120 requests/minute per key. Keys act as their
creator member, with that member's permissions.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/brands` | All brands with summary |
| GET | `/api/v1/brands/{id}` | Overview: rates with low/high |
| GET | `/api/v1/brands/{id}/prompts` | Per-prompt per-engine performance |
| GET | `/api/v1/brands/{id}/competitors` | Brand vs. rivals comparison |
| GET | `/api/v1/brands/{id}/sources` | Cited domains, classified |
| GET | `/api/v1/brands/{id}/answers` | Paged stored answers |
| GET | `/api/v1/brands/{id}/opportunities` | Ranked next steps |
| POST | `/api/v1/brands/{id}/prompts` | Add up to 50 questions |

Every rate response includes `rate`, `low`, `high` (the 95% Wilson interval)
and the sample size. Foreign brands 404.

### MCP server

Add to Claude Desktop / Cursor config:
```json
{"mcpServers": {"clot": {
  "type": "http",
  "url": "https://your-domain/mcp/visibility",
  "headers": {"Authorization": "Bearer vg_…"}
}}}
```

Seven read-only tools: `list_brands`, `brand_overview`, `prompt_performance`,
`competitor_comparison`, `top_sources`, `next_steps`, `search_answers`.

---

## 6. Roles and permissions

| Role | What they do | Can do |
|---|---|---|
| Owner | Everything | Billing, people, workspace settings, brands, delete |
| Admin | Runs the workspace | People, roles, connections, settings, brands, content |
| Member | Does the work | Brands, prompts, competitors, content, ad drafts, reports |
| Viewer | Reads only | Results, reports, exports — changes nothing |

Custom roles are supported (create your own permission sets). Teams limit
members to the brands assigned to their teams.

---

## 7. Upgrade

```bash
php artisan clot:upgrade
```

Backs up DB + media → merges vendor changes → migrates → clears caches →
schema-drift check → prints version notes. Semantic versioning with per-version
upgrade docs in `CHANGELOG.md`.

---

## 8. FAQ

**Why a range instead of one number?** AI answers change run to run. A single
number claims certainty the data doesn't have. The Wilson interval is honest
about what the sample supports.

**Can I guarantee my client will appear in AI answers?** No. Nobody can. The
product measures where you stand and whether your work moves the numbers.

**Is the interface the same as the API?** No — answers from the product UI
differ from developer-API answers. Every number shows which method produced
it, and the two are never silently averaged.

**What happens when the allowance runs out?** Collection pauses. No overage
charges. The workspace upgrades or waits for the next period.

**How is data isolated between workspaces?** A global scope on every tenant
model + a per-request workspace context. Cross-workspace IDs return 404.
The isolation test suite verifies this for every route × role combination.
