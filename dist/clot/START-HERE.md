# CLOT — AI Visibility Tracker SaaS (source-code product)

Track how often AI assistants name a brand, who they pick instead, and which sources they trust — with an honest 95% range on every number.

Built with Laravel 13 + Inertia 3 + Vue 3 + TypeScript. Sold as a **source-code script**: buyers install on their own hosting (PHP 8.3+/MySQL/SQLite) and run it as their own SaaS.

---

## What you need before installing

1. **Hosting:** PHP 8.3+, MySQL 8 / MariaDB 10.6 (or SQLite for local), one cron entry, a queue worker (or Upstash QStash on shared hosting), HTTPS for live payments.
2. **AI provider keys** (at least one): OpenAI, Gemini, Anthropic, Perplexity, xAI, DeepSeek, Mistral — or DataForSEO/Bright Data/SerpApi for interface-level collection. Every collected answer is a paid API call on **your** account.
3. **A payment provider** (one of): Stripe, Paddle, Polar, Lemon Squeezy, Creem, Dodo Payments. Keys on your own approved seller account.
4. **Email delivery** (one of): SMTP, Resend, Postmark, SendGrid, Mailtrap, Brevo, Amazon SES, Mailchimp, OneSignal.

Every credential in the package is blank or placeholder — you replace them with your own.

---

## Install (browser installer)

1. Upload the `clot/` folder to your web root.
2. Visit `https://your-domain/setup` — the browser installer walks through: requirements check → database → first operator account → queue self-verification → seal (self-locks).
3. Add the cron entry shown at the end:
   ```
   * * * * * php /home/username/artisan schedule:run >> /dev/null 2>&1
   ```
4. Configure your AI provider keys and payment gateway in Admin → AI visibility / Billing.

The installer **refuses to run again** once sealed. Manual/developer route: `php artisan clot:install-seal`.

---

## Demo data

```bash
php artisan clot:demo            # seeds a fictional workspace with 45 free collected answers
php artisan clot:demo --reset    # removes demo-*@clot.test accounts (never your operator)
```

Sign in as `demo-owner@clot.test` / `demo-password`.

---

## Key commands

| Command | What it does |
|---|---|
| `clot:sweep-due` | Scheduler entry (every 15 min): queues sweeps for due brands |
| `clot:alerts-evaluate` | Evaluates alert rules (every 15 min) |
| `clot:billing-renew` | Renews due subscriptions (nightly) |
| `clot:digest-send` | Sends the weekly digest (Mondays 08:00) |
| `clot:demo` / `--reset` | Seeds or removes demo data |
| `clot:install-seal` | Manual install: first account + self-verify + seal |
| `clot:install-cron-ping` | Cron heartbeat (every minute, health check reads it) |

---

## Test suite

```bash
composer install && npm ci && npm run build
php artisan test          # 111+ tests
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

CI (GitHub Actions) runs all four gates on PHP 8.3/8.4 plus a package-hygiene job (no local paths, no committed secrets, no telemetry).

---

## Documentation

Full HTML + PDF guide: `docs/` — architecture first, then screen-by-screen workflows, every provider's connection guide, API reference, webhook catalogue, env vars, roles matrix, data model, upgrade, FAQ.

Quality gates checklist: `docs/CHECKLIST.md` (release + CodeCanyon submission).

Architecture decisions: `docs/adr/` (ADR-0001 through ADR-0005).

---

## Important notes

- The purchase covers **source code only**. AI usage, payment fees, email delivery, hosting are paid by you on your own accounts.
- Extended Licence required to charge end users as a SaaS.
- No telemetry, no licence phone-home. Data stays on your server.
- Every number carries its 95% Wilson interval — no ranking guarantees.
