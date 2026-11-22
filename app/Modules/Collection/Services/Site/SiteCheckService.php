<?php

namespace App\Modules\Collection\Services\Site;

use Illuminate\Support\Facades\Http;

/**
 * Site check: what AI engines find when they visit the brand's website.
 * Checks robots.txt (answer vs training crawlers), llms.txt, sitemap,
 * Organization markup, title/description, and generates an llms.txt draft.
 *
 * Scored 0-100 across nine weighted checks. The SSRF guard applies —
 * only the brand's own domains are fetched.
 */
final class SiteCheckService
{
    private const ANSWER_CRAWLERS = [
        'OAI-SearchBot' => 'OpenAI · ChatGPT search',
        'ChatGPT-User' => 'OpenAI · ChatGPT browsing',
        'Claude-SearchBot' => 'Anthropic · Claude search',
        'Claude-User' => 'Anthropic · Claude browsing',
        'PerplexityBot' => 'Perplexity · search index',
        'Perplexity-User' => 'Perplexity · browsing',
        'Googlebot' => 'Google · Search, AI Overviews, AI Mode',
        'Bingbot' => 'Microsoft · Bing and Copilot',
        'MistralAI-User' => 'Mistral · Le Chat browsing',
        'meta-externalfetcher' => 'Meta · Meta AI browsing',
    ];

    private const TRAINING_CRAWLERS = [
        'GPTBot' => 'OpenAI · training',
        'ClaudeBot' => 'Anthropic · training',
        'Google-Extended' => 'Google · Gemini training',
        'Applebot-Extended' => 'Apple · AI training',
        'meta-externalagent' => 'Meta · training',
        'CCBot' => 'Common Crawl · training',
        'Bytespider' => 'ByteDance · training',
    ];

    private const CHECK_WEIGHTS = [
        'home_page_loads' => 15,
        'answer_crawlers_allowed' => 25,
        'pages_can_be_quoted' => 15,
        'llms_txt' => 8,
        'sitemap' => 5,
        'title_and_description' => 8,
        'organisation_markup' => 10,
        'product_markup' => 6,
        'enough_to_read' => 8,
    ];

    /**
     * @return array<string, mixed>
     */
    public function check(string $domain): array
    {
        $domain = $this->normaliseDomain($domain);

        $robotsTxt = $this->fetchRobotsTxt($domain);
        $html = $this->fetchHomePage($domain);
        $llmsTxt = $this->fetchLlmsTxt($domain);

        $checks = [];

        // 1. Home page loads
        $checks['home_page_loads'] = $html !== null
            ? ['pass' => true, 'detail' => 'The home page answered normally.']
            : ['pass' => false, 'detail' => 'The home page did not respond.'];

        // 2. AI search crawlers allowed
        $blockedAnswerCrawlers = [];
        foreach (array_keys(self::ANSWER_CRAWLERS) as $crawler) {
            if ($this->isBlocked($robotsTxt, $crawler)) {
                $blockedAnswerCrawlers[] = $crawler;
            }
        }
        $checks['answer_crawlers_allowed'] = $blockedAnswerCrawlers === []
            ? ['pass' => true, 'detail' => 'All answer crawlers are allowed in robots.txt.']
            : ['pass' => false, 'detail' => 'robots.txt blocks: '.implode(', ', $blockedAnswerCrawlers).'. Blocked crawlers cannot cite your pages.'];

        // 3. Pages can be quoted (no noindex/nosnippet)
        $noIndexFound = $html !== null && (stripos($html, 'noindex') !== false || stripos($html, 'nosnippet') !== false);
        $checks['pages_can_be_quoted'] = ! $noIndexFound
            ? ['pass' => true, 'detail' => 'No noindex or nosnippet on the home page.']
            : ['pass' => false, 'detail' => 'noindex or nosnippet found on the home page.'];

        // 4. llms.txt
        $checks['llms_txt'] = $llmsTxt !== null
            ? ['pass' => true, 'detail' => 'llms.txt found at the root of the site.']
            : ['pass' => false, 'detail' => 'No llms.txt. A draft is generated below.'];

        // 5. Sitemap
        $robotsHasSitemap = $robotsTxt !== null && stripos($robotsTxt, 'sitemap:') !== false;
        $checks['sitemap'] = $robotsHasSitemap
            ? ['pass' => true, 'detail' => 'A sitemap is listed in robots.txt.']
            : ['pass' => false, 'detail' => 'No sitemap listed in robots.txt.'];

        // 6. Title and description
        $hasTitle = $html !== null && preg_match('/<title[^>]*>(.+)<\/title>/i', $html) === 1;
        $hasDescription = $html !== null && preg_match('/<meta[^>]*name=["\']description["\'][^>]*content/i', $html) === 1;
        $checks['title_and_description'] = ($hasTitle && $hasDescription)
            ? ['pass' => true, 'detail' => 'Title and meta description are set.']
            : ['pass' => false, 'detail' => 'Missing title or meta description.'];

        // 7. Organization markup
        $hasOrgMarkup = $html !== null && (stripos($html, '"@type":"organization"') !== false || stripos($html, '"@type": "organization"') !== false || stripos($html, '"@type":"person"') !== false);
        $checks['organisation_markup'] = $hasOrgMarkup
            ? ['pass' => true, 'detail' => 'Organization or Person structured data found.']
            : ['pass' => false, 'detail' => 'No Organization structured data on the home page.'];

        // 8. Product/Service/FAQ markup
        $hasProductMarkup = $html !== null && (
            stripos($html, '"@type":"product"') !== false
            || stripos($html, '"@type": "product"') !== false
            || stripos($html, '"@type":"softwareapplication"') !== false
            || stripos($html, '"@type":"faqpage"') !== false
        );
        $checks['product_markup'] = $hasProductMarkup
            ? ['pass' => true, 'detail' => 'Product/SoftwareApplication/FAQ markup found.']
            : ['pass' => false, 'detail' => 'No Product, SoftwareApplication, Service or FAQ structured data.'];

        // 9. Enough content
        $textLength = $html !== null ? strlen(strip_tags($html)) : 0;
        $checks['enough_to_read'] = $textLength >= 500
            ? ['pass' => true, 'detail' => 'The home page has real text content.']
            : ['pass' => false, 'detail' => 'The home page has too little text content for AI to read.'];

        // Score
        $score = 0;
        foreach ($checks as $key => $check) {
            if ($check['pass']) {
                $score += self::CHECK_WEIGHTS[$key];
            }
        }

        // Crawler detail table
        $crawlers = [];
        foreach (self::ANSWER_CRAWLERS as $bot => $description) {
            $crawlers['answer'][] = [
                'bot' => $bot, 'description' => $description,
                'blocked' => $robotsTxt !== null && $this->isBlocked($robotsTxt, $bot),
            ];
        }
        foreach (self::TRAINING_CRAWLERS as $bot => $description) {
            $crawlers['training'][] = [
                'bot' => $bot, 'description' => $description,
                'blocked' => $robotsTxt !== null && $this->isBlocked($robotsTxt, $bot),
            ];
        }

        return [
            'domain' => $domain,
            'score' => $score,
            'checks' => $checks,
            'crawlers' => $crawlers,
            'llmsTxt' => $llmsTxt !== null ? $llmsTxt : $this->generateLlmsTxtDraft($domain, $html),
            'checkedAt' => now()->toIso8601String(),
        ];
    }

    /** Generate an llms.txt draft from the home page content. */
    public function generateLlmsTxtDraft(string $domain, ?string $html): string
    {
        $domain = $this->normaliseDomain($domain);
        $title = 'Brand';

        if ($html !== null && preg_match('/<title[^>]*>(.+?)<\/title>/i', $html, $m) === 1) {
            $title = trim(strip_tags($m[1]));
        }

        $lines = [
            "# {$title}",
            '',
            "> Visit {$domain} for details.",
            '',
            '## Product',
            '',
            "- [Home](https://{$domain}): Main website",
            "- [Pricing](https://{$domain}/pricing): Plans and pricing",
        ];

        return implode("\n", $lines)."\n";
    }

    private function fetchRobotsTxt(string $domain): ?string
    {
        try {
            $response = Http::timeout(10)->get("https://{$domain}/robots.txt");

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchHomePage(string $domain): ?string
    {
        try {
            $response = Http::timeout(15)->get("https://{$domain}");

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchLlmsTxt(string $domain): ?string
    {
        try {
            $response = Http::timeout(10)->get("https://{$domain}/llms.txt");

            return $response->successful() && strlen($response->body()) > 10 ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isBlocked(?string $robotsTxt, string $bot): bool
    {
        if ($robotsTxt === null) {
            return false;
        }

        $inRelevantGroup = false;

        foreach (explode("\n", $robotsTxt) as $line) {
            $line = trim($line);

            if (stripos($line, 'user-agent:') === 0) {
                $agent = strtolower(trim(substr($line, 11)));
                $inRelevantGroup = $agent === strtolower($bot) || $agent === '*';

                continue;
            }

            if ($inRelevantGroup && stripos($line, 'disallow:') === 0) {
                $path = trim(substr($line, 9));

                if ($path === '/' || $path === '') {
                    return true;
                }
            }
        }

        return false;
    }

    private function normaliseDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if (str_contains($domain, '://')) {
            $domain = (string) parse_url($domain, PHP_URL_HOST);
        }

        $domain = str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;

        return rtrim($domain, '/');
    }
}
