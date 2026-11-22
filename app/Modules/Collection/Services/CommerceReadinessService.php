<?php

namespace App\Modules\Collection\Services;

use App\Modules\Collection\Models\Brand;

/**
 * AI Commerce Readiness: how ready the brand's products are to be found,
 * shown and bought through AI assistants (ChatGPT product cards, Google AI
 * Mode shelf). Scored 0-100 across six weighted components.
 */
final class CommerceReadinessService
{
    /** @return array<string, mixed> */
    public function score(Brand $brand): array
    {
        $domain = $brand->domains[0] ?? null;
        $hasStore = $brand->settings['store_url'] ?? null;

        // Six weighted components (build plan §6.1 Shopping row).
        $components = [
            ['name' => 'Product data', 'weight' => 25, 'score' => $this->productDataScore($brand), 'fixes' => $this->productDataFixes($brand)],
            ['name' => 'Structured data', 'weight' => 20, 'score' => $this->structuredDataScore($brand), 'fixes' => []],
            ['name' => 'Crawler access', 'weight' => 15, 'score' => $this->crawlerAccessScore($brand), 'fixes' => []],
            ['name' => 'Price accuracy', 'weight' => 15, 'score' => $this->priceAccuracyScore($brand), 'fixes' => []],
            ['name' => 'Feed availability', 'weight' => 15, 'score' => $hasStore ? 80 : 0, 'fixes' => $hasStore ? [] : ['Connect a store platform (Shopify, WooCommerce, etc.)']],
            ['name' => 'Checkout readiness', 'weight' => 10, 'score' => $hasStore ? 70 : 0, 'fixes' => $hasStore ? [] : ['Add a checkout flow the assistant can link to']],
        ];

        $total = 0;
        foreach ($components as $component) {
            $total += (int) round($component['score'] * $component['weight'] / 100);
        }

        return [
            'score' => $total,
            'components' => $components,
            'store' => [
                'connected' => $hasStore !== null,
                'url' => $hasStore,
            ],
        ];
    }

    private function productDataScore(Brand $brand): int
    {
        $score = 40;
        if ($brand->domains !== []) {
            $score += 30;
        }
        if ($brand->aliases !== []) {
            $score += 15;
        }
        if ($brand->settings['tagline'] ?? false) {
            $score += 15;
        }

        return $score;
    }

    /** @return list<string> */
    private function productDataFixes(Brand $brand): array
    {
        $fixes = [];

        if ($brand->domains === []) {
            $fixes[] = 'Add your domain to the brand settings';
        }
        if ($brand->aliases === []) {
            $fixes[] = 'Add aliases (other names buyers might use)';
        }

        return $fixes;
    }

    private function structuredDataScore(Brand $brand): int
    {
        return $brand->domains !== [] ? 60 : 0;
    }

    private function crawlerAccessScore(Brand $brand): int
    {
        // Would check robots.txt for GPTBot-Shopping and Googlebot-Shopping.
        return 50; // Neutral default until site check runs.
    }

    private function priceAccuracyScore(Brand $brand): int
    {
        // Would compare AI-reported prices with the store's actual prices.
        return 50; // Neutral default.
    }
}
