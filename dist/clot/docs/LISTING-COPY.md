# CLOT — Envato Item Description (HTML for CodeCanyon)

> Paste the HTML below into the CodeCanyon item description field.
> Title: **CLOT — AI Visibility Tracker SaaS**
> Tags: ai visibility, answer engine optimization, saas, laravel, chatgpt, brand monitoring, seo tool, ai search, competitor tracking, citation tracker

---

<h2>Track how often AI assistants name your brand</h2>

<p>Buyers ask AI what to buy. CLOT measures how often ChatGPT, Gemini, Google AI Overviews, Claude, Perplexity, Grok, DeepSeek and Le Chat name a brand when they do — with an honest 95% range on every number.</p>

<h3>Required: your own third-party accounts (read before buying)</h3>

<p>The purchase covers the source code only. No third-party account, key, credit or service is included. The features below require your own accounts with each company, its terms accepted, its keys entered and its fees paid by you.</p>

<ul>
<li><strong>AI providers (hard requirement for real data):</strong> OpenAI, Google Gemini, Anthropic, Perplexity, xAI, DeepSeek, Mistral, or DataForSEO/Bright Data/SerpApi for interface-level collection. Every collected answer is a paid API call on your key. Any engine you don't offer can be switched off. The FakeCollector is free and used for demos and CI.</li>
<li><strong>Payment provider:</strong> Stripe, Paddle, Polar, Lemon Squeezy, Creem, or Dodo Payments — on your own approved seller account.</li>
<li><strong>Email delivery:</strong> SMTP, Resend, Postmark, SendGrid, Mailtrap, Brevo, Amazon SES, Mailchimp, or OneSignal. Signup verification, password resets, invitations and security alerts are sent from your own mail account.</li>
<li><strong>Hosting:</strong> PHP 8.3+, MySQL 8 / MariaDB 10.6 (PostgreSQL and SQLite also work), one cron entry, and a queue worker or Upstash QStash. HTTPS for live payments and webhooks.</li>
</ul>

<h3>AI Disclosure</h3>

<p>AI tools were used to make this item. The source code, documentation and graphics were created with AI assistance (Claude Code with Anthropic's Claude model for code and documentation). Our team reviewed and tested the result and maintains and supports the item.</p>

<p><strong>AI as the subject of the product:</strong> CLOT sends the questions your customers enter, with a country code, to the AI provider you choose for each engine, over the internet, and stores the answers on your server. No personal data about your customers is sent. Answers are machine output: CLOT reports what the engines said, which can be wrong, outdated, or different from what a person sees in the consumer apps. Every answer records how it was collected (developer API or app interface) and which model produced it.</p>

<h3>Ongoing monthly costs</h3>

<p>Your customers pay you; you pay the AI providers for every answer collected. One answer with web search typically costs about US$0.005 to US$0.03 depending on the provider, the model and the answer length. The formula is simple: <code>answers/month = prompts × engines × runs × sweeps/week × 4.33</code>. The product shows the estimate before each sweep and enforces hard caps.</p>

<p>Sample plan prices (Starter $49, Growth $149, Agency $399/month) are a starting point. Check them against your own cost per answer before you launch.</p>

<h2>How it works</h2>

<h4>Four steps, no code</h4>

<ol>
<li><strong>Ask what buyers ask</strong> — write the questions that lead to a purchase, group by topic, choose engines and runs per sweep.</li>
<li><strong>Collect every answer</strong> — each run stores the full answer, the engine, the method, the cost. You can read any response behind any number.</li>
<li><strong>Compare against rivals</strong> — see your mention rate and share of voice beside each competitor, with ranges that show whether a lead is real.</li>
<li><strong>Trace the sources</strong> — see which domains the answers cite, whether they're yours, a competitor's or a third party's. That's where to act.</li>
</ol>

<h2>Key features</h2>

<ul>
<li><strong>9 answer engines</strong> — ChatGPT, Gemini, Google AI Overviews, Google AI Mode, Claude, Perplexity, Grok, DeepSeek, Le Chat (Mistral)</li>
<li><strong>Wilson 95% range on every number</strong> — honest about what the data supports; under 30 answers marked low confidence</li>
<li><strong>Trends that mean something</strong> — called only when the 95% ranges of two periods do not overlap</li>
<li><strong>Full answer storage</strong> — every response kept with the engine, method, model, cost, fan-out searches and cited links</li>
<li><strong>Competitor tracking</strong> — up to 50 rivals per brand, matched by name, alias and domain, with per-engine comparison</li>
<li><strong>Opportunities</strong> — ranked next steps: questions rivals win, sites that cite them but not you, weak engines, blocked crawlers</li>
<li><strong>Perception</strong> — tone by engine, themes, fact-check against brand-entered facts</li>
<li><strong>Site check</strong> — AI crawler access, llms.txt draft, 0–100 score</li>
<li><strong>Alerts</strong> — mention-rate drop, rival pulls ahead, negative mentions, collection failing — by email, in the bell, in Slack, or to a signed webhook</li>
<li><strong>Report PDFs</strong> — dompdf server-rendered, white-label via plan flag</li>
<li><strong>Share links</strong> — read-only public reports, no login, noindex, revocable</li>
<li><strong>REST API + MCP server</strong> — 7 read-only tools for Claude, ChatGPT, Cursor; 120 requests/minute</li>
<li><strong>Multi-tenant</strong> — workspaces, roles (Owner/Admin/Member/Viewer), teams, custom roles, SSO (Agency plan)</li>
<li><strong>Cost governance</strong> — 4-level caps, pre-flight estimates, per-answer cost ledger, no overage charges</li>
<li><strong>Billing</strong> — plans, trials, coupons, credits, add-ons, refunds with approval, 6 payment gateways</li>
<li><strong>Self-verifying installer</strong> — browser installer with requirements check, queue canary, cron heartbeat, self-seal</li>
<li><strong>2 languages</strong> — English + Turkish, in-panel translation editor</li>
<li><strong>11 panel themes</strong> — light/dark, RTL support</li>
</ul>

<h2>Test card</h2>

<p>Plans are paid through the payment provider you configure. Stripe checks out in test mode with card number <code>4242 4242 4242 4242</code>, any future expiry, any CVC.</p>

<h2>Demo Instructions</h2>

<p>Try everything before you buy, no install needed. The live demo is a sandboxed installation with a fictional brand (Northwind) and collected sample answers clearly labelled as sample data; demo mode blocks changes to settings, keys and users.</p>

<ul>
<li><strong>Operator panel:</strong> <code>https://your-demo-domain/login</code> · Email: <code>super@visigoo.test</code> · Password: <code>password</code></li>
<li><strong>Customer panel (workspace "Acme", Growth plan):</strong> <code>https://your-demo-domain/login</code> · Owner: <code>owner@visigoo.test</code> · Admin: <code>admin@visigoo.test</code> · Member: <code>member@visigoo.test</code> · Viewer (read only): <code>viewer@visigoo.test</code> · Password: <code>password</code> for each</li>
<li><strong>A second, separate customer:</strong> <code>neighbour@visigoo.test</code> · <code>password</code> (shows that customers never see each other's data)</li>
</ul>

<h2>Requirements</h2>

<ul>
<li>PHP 8.3+ with the usual Laravel extensions</li>
<li>MySQL 8 / MariaDB 10.6 (PostgreSQL and SQLite also work)</li>
<li>One cron entry every minute; a queue worker or Upstash QStash</li>
<li>HTTPS for live payments and webhooks</li>
<li>Your own API key for at least one AI provider, a payment provider account, and an email service</li>
</ul>

<h2>Important notice</h2>

<p>We are committed to building a reliable, continuous product. Your feedback matters — send us a message through the CodeCanyon messaging system.</p>

<p>OpenAI, ChatGPT, Google, Gemini, Anthropic, Claude, Perplexity, xAI, Grok, DeepSeek, Mistral, Le Chat, DataForSEO, Laravel, Stripe and all other product names mentioned belong to their respective owners. CLOT is an independent product and is not affiliated with or endorsed by any of them.</p>
