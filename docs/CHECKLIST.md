# CLOT — Release & CodeCanyon Submission Checklist

> Adapted from the Mono Market release checklists (Mono_Market_Flutter_Laravel_Documentation_Checklists.pdf, Nov 2026 review history) to the CLOT product: **no Flutter track** (no mobile apps ship in this package); the Laravel/backend, documentation/listing, and final submission gates apply in full.
>
> **Verification rule (unchanged):** previous replies that fixes were made are not proof the final files contain them. Tick an item only after checking the exact release archives, deployed demo, documentation, and marketplace listing.

**Release information**

| Field | Value |
|---|---|
| Product | CLOT — AI Visibility Tracker SaaS |
| Checklist owner | |
| Release / version | v0.1.0-sprint0 |
| Target upload date | |
| Final archive names / checksums | |

---

## PART A — Laravel backend, panels & installer

### A.1 Runtime and dependency integrity (gate 2.1)
- [ ] composer.json and composer.lock agree on Laravel and PHP versions required by the shipped code
- [ ] Documentation, listing, installer requirements, and preview FAQ state the same verified PHP/Laravel versions
- [ ] Dependency installation is reproducible from the shipped lockfile
- [ ] Release package does NOT ship `vendor/` (or documents the pinned download procedure if it does)
- [ ] No references to nonexistent folders, scripts, or build tools in source, installer, or documentation

### A.2 Installer safety and deployment (gate 2.2)
- [ ] `/setup` refuses to run after installation, including installations completed through the documented manual/developer path
- [ ] APP_KEY / installed-state checks prevent an installer rerun from overwriting the first administrator or rewriting production config
- [ ] Installer seal mechanism works (`install.lock`); seal after web install AND after every documented manual route
- [ ] Fresh installation on a clean environment using only the supplied package and documentation
- [ ] Rerunning the installer cannot reset the first admin account or damage an existing installation
- [ ] Final installer contains no stale installer archives, demo scripts, demo storage, preview content, or local secrets
- [ ] **CLOT self-verification:** cron self-ping job runs and is asserted, queue ping completes, before install lock is written

### A.3 Authorization and account separation (gate 2.3)
- [ ] Workspace-scoped API keys cannot be used across workspaces (keys act as their creator, with that member's permissions)
- [ ] Customer routes reject operator credentials and vice versa
- [ ] Admin-only routes enforce admin authorization, not just authentication
- [ ] Tenant-isolation Pest suite green: for every tenant route × role matrix, cross-workspace IDs return 403 (own-workspace privilege failure) or 404 (other-workspace existence leak)
- [ ] Viewer role receives 403 on edit routes; member cannot reach workspace management; impersonated operators cannot run billing/security actions
- [ ] Logout and token revocation work for every role type

### A.4 Payment verification and financial integrity (gate 2.4)
- [ ] All payment gateways use the centralized server-side payment verification service
- [ ] No mock/session-ID bypass can mark a subscription, add-on, or credit purchase as paid
- [ ] Payment amount, currency, order/reference code, and expected transaction are compared with gateway-side data
- [ ] Payment references are single-use; row locking prevents duplicate credit grants and duplicate order completion
- [ ] Webhooks/IPNs validate signatures (HMAC) and payment details before updating transaction state
- [ ] Success/failure/cancel callbacks restricted to the correct payer/session or authenticated owner
- [ ] A user cannot view another customer's payment-success page, items, or totals by changing an ID
- [ ] Every enabled gateway tested in sandbox/test mode: success, failure, cancellation, duplicate callback, wrong amount, replay
- [ ] **CLOT cost invariants:** no `answers` row exists without an allowance-ledger claim; allowance can never be overdrawn; cost ledger balances against collected answers

### A.5 AI provider calls (CLOT-specific)
- [ ] Every provider call goes through a Collector behind the Collector SDK; no engine adapter touches the database
- [ ] Provider keys are read from encrypted settings/`.env` only; never rendered to the browser
- [ ] Test calls in the admin UI are labeled and billed like answers
- [ ] Provider failures are stored with reasons and never counted as "not named"
- [ ] SSRF guard covers every outgoing request (citation verifier, robots reader, collectors, webhooks)

### A.6 Uploads and file handling (gate 2.5)
- [ ] Uploaded filenames generated server-side; extensions derived from server-detected MIME, not client input
- [ ] Only intended media formats accepted; executable/HTML/SVG/script/oversized/spoofed-MIME uploads rejected
- [ ] Public storage cannot serve an uploaded executable or HTML payload as active content
- [ ] Media library and public upload paths equivalent hardening
- [ ] Upload, replacement, deletion, and access-control behavior tested

### A.7 Account deletion and data retention (gate 2.6)
- [ ] Customer account deletion removes or anonymizes required personal data (answers may be retained anonymized per settings; workspace owner data removed)
- [ ] Deletion uses valid Eloquent relationship/query patterns; full flow succeeds end-to-end
- [ ] Retained financial records do not retain unnecessary personal data
- [ ] Deletion cannot be triggered for another user's account by manipulating request identifiers
- [ ] Closed accounts deleted after the documented grace period

### A.8 Production configuration and operational security (gate 2.8)
- [ ] `.env.production.example` uses APP_ENV=production, APP_DEBUG=false; HTTPS enforced for production web/API endpoints
- [ ] No secrets, payment credentials, or AI keys committed or shipped (CI job enforces)
- [ ] CORS, session, cookie, CSRF, rate-limiting, and API validation settings appropriate for production
- [ ] Cron/scheduler and queue-worker commands in docs match the shipped application and PHP version
- [ ] Migrations and seeders do not overwrite or reset production user/admin data unexpectedly (`clot:demo` is opt-in, labeled, and blocked in production unless demo mode is explicitly enabled)
- [ ] Logs do not expose stack traces, credentials, local paths, or customer data (CI path-hygiene job enforces; log viewer scrubs absolute paths)

### A.9 Backend regression and package verification (gate 2.9)
- [ ] Fresh install → login → workspace create/switch → demo sweep (mock collector) → brand dashboard → report PDF → account deletion
- [ ] API authorization and payment edge cases tested, not only happy paths
- [ ] Live demo runs the same code revision and configuration behavior as the submitted release, apart from secrets/env
- [ ] Release archives scanned for .env files, credentials, local machine paths, stale archives, demo reset scripts, cache/build folders
- [ ] Test results, migration status, release commit/tag, and final archive checksums recorded below

---

## PART B — Documentation, item description & marketplace listing

*(PDF Part 3, Flutter-specific items 1.1–1.8 / 3.2–3.4 dropped; everything else applies.)*

### B.1 Documentation accuracy and completeness (gates 3.1, 3.5)
- [ ] Docs reviewed against the current Laravel implementation and panels
- [ ] No outdated/contradictory/speculative statements ("planned", "blocked", "not wired"); every remaining limitation marked as accepted / workaround / planned
- [ ] Screenshots, filenames, folder paths, version numbers, commands, URLs, and settings labels verified against the final release
- [ ] Clean installation guide: prerequisites, database creation, environment setup, permissions, installer steps
- [ ] Manual/developer installation route documented with installer-seal steps
- [ ] First-run guide: enabling the marketing site, payment gateway, SMTP email, AI provider keys, essential admin settings
- [ ] Cron and queue-worker setup with exact commands; **`/home/username/` clearly marked as a placeholder** with a worked cron path example and the correct PHP binary/version guidance
- [ ] Upgrade/migration instructions explaining which files are replaced and which data/settings are backed up and retained; effects on .env, uploads, migrations, themes, application settings
- [ ] Troubleshooting/FAQ with concrete error symptoms, causes, fixes for installation and first-run problems
- [ ] Methodology documentation: Wilson intervals, collection methods, what the product does NOT promise (no ranking guarantees)

### B.2 API, payment, webhook, and third-party service documentation (gate 3.6)
- [ ] Exact payment webhook/IPN URL and setup steps per supported gateway; manual vs automatic configuration distinguished
- [ ] Test-mode vs live-mode credentials and safe verification steps
- [ ] All external services listed: AI providers (OpenAI, Gemini, Anthropic, Perplexity, xAI, DeepSeek, Mistral, DataForSEO, Bright Data, SerpApi, gateways), payment gateways, mail providers, storage/CDN, OAuth, captcha, Sentry, QStash
- [ ] Statement that third-party services may charge subscription/usage fees and that buyers create their own accounts, configure their own credentials, and pay their own fees
- [ ] AI provider integrations documented as optional buyer-configured integrations using the buyer's own API keys
- [ ] **AI-assistance disclosure included in item description and documentation**, accurately describing the role of AI tools and human review
- [ ] REST API reference with examples and error codes; MCP client setup; every inbound webhook URL; env-var catalogue; roles/permissions matrix; data model; command reference

### B.3 Changelog, versions, and release notes (gate 3.7)
- [ ] CHANGELOG.md ships in the correct package location and reflects the actual release version
- [ ] All docs, README, listing text, changelog, and appbackup version references agree
- [ ] Every changelog claim supported by the final source and archive content

### B.4 CodeCanyon title, description, demo, and tags (gate 3.8)
- [ ] Concise product-focused title; no technology-stack or feature stuffing
- [ ] Semantic HTML in the item description: headings, paragraphs, lists; no nested layout tables
- [ ] No external APK/download links in the description body
- [ ] Demo credentials appear clearly and ONLY in the Demo Instructions section; verified live before upload
- [ ] Demo Instructions state: sandboxed install, sample data labeled, demo mode blocks settings/keys/users, second customer shows isolation
- [ ] Redundant/misleading/brand-stuffed tags removed; remaining tags distinct and accurate
- [ ] Applicable third-party cost disclosures and AI-use disclosure present
- [ ] All description links point to the correct documentation page or live preview
- [ ] Screenshots meet marketplace requirements (≥1280×720 at 16:9 where required); checked on desktop and mobile layouts
- [ ] Old product names, stale version numbers, obsolete URLs, removed folder names, and local developer paths searched and removed (proofread gate 3.9)
- [ ] Every documentation link opens and matches the section title

---

## PART C — Final submission gate

### C.1 Sign-off (gate 4.1 equivalent)

| Area | Verification / evidence | Status / notes |
|---|---|---|
| Title | Final title concise, product-focused | |
| Demo credentials | Credentials only in Demo Instructions; tested live | |
| Tags | Distinct and accurate | |
| Installer guide | Fresh install verified with shipped package only; seal + self-verification pass | |
| Payment docs | Webhook URLs + sandbox test matrix per gateway | |
| AI disclosure | In item description and docs | |
| Final cross-check | Backend + docs/listing tracks reviewed against final archives and listing | |

### C.2 Archive and upload checklist (gate 4.2)
- [ ] Final `clot/` source archive created and inspected (open the ZIP; do not rely on the source folder audit)
- [ ] Final documentation archive created and inspected
- [ ] Archive names, version labels, and checksums verified; superseded ZIPs removed from the upload folder
- [ ] No local secrets, developer paths, generated configs, stale nested ZIPs, demo reset scripts, caches, or build outputs present
- [ ] Deployed demo and linked documentation represent the same release behavior as the submitted package
- [ ] Evidence captured for reviewer-relevant file paths, security config, documentation sections, and demo instructions
- [ ] A copy of the exact submitted archives and listing description saved for future review rounds

### C.3 Final approval

| Track | Reviewed by | Date |
|---|---|---|
| Laravel backend / installer | | |
| Documentation / listing | | |
| Final release | | |

**Release decision:** ☐ Ready to submit ☐ Hold — fixes/evidence still outstanding

**Outstanding items / notes:**
