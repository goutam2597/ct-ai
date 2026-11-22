<?php

namespace Database\Seeders;

use App\Modules\Billing\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the four sample plans (Free/Starter/Growth/Agency). Operators edit
 * prices and limits in the admin panel — the marketing pricing page reads
 * these live rows.
 */
final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Try it on one brand before you decide.',
                'flags' => ['answers_per_month' => 150, 'brands' => 1, 'seats' => 3, 'prompts' => 10],
                'prices' => ['USD' => ['month' => 0, 'year' => 0]],
                'default' => true,
                'sort' => 1,
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'One brand, measured properly.',
                'flags' => [
                    'answers_per_month' => 2000, 'brands' => 1, 'seats' => 3, 'prompts' => 50,
                    'engines' => ['chatgpt', 'gemini', 'google-ai-overviews', 'google-ai-mode', 'perplexity'],
                    'api_access' => true, 'white_label' => true, 'app_checks' => true,
                ],
                'prices' => ['USD' => ['month' => 49, 'year' => 490]],
                'default' => false,
                'sort' => 2,
            ],
            [
                'slug' => 'growth',
                'name' => 'Growth',
                'description' => 'More brands, prompts and engines for a growing team.',
                'flags' => [
                    'answers_per_month' => 10000, 'brands' => 3, 'seats' => 10, 'prompts' => 150,
                    'engines' => ['chatgpt', 'gemini', 'google-ai-overviews', 'google-ai-mode', 'perplexity', 'claude', 'deepseek'],
                    'api_access' => true, 'white_label' => true, 'app_checks' => true, 'daily_frequency' => false,
                ],
                'prices' => ['USD' => ['month' => 149, 'year' => 1490]],
                'default' => false,
                'sort' => 3,
            ],
            [
                'slug' => 'agency',
                'name' => 'Agency',
                'description' => 'Client brands in one workspace, under your own theme.',
                'flags' => [
                    'answers_per_month' => 30000, 'brands' => 25, 'seats' => 25, 'prompts' => 600,
                    'engines' => ['chatgpt', 'gemini', 'google-ai-overviews', 'google-ai-mode', 'claude', 'perplexity', 'grok', 'deepseek', 'le-chat'],
                    'api_access' => true, 'white_label' => true, 'byok' => true, 'app_checks' => true, 'sso' => true,
                ],
                'prices' => ['USD' => ['month' => 399, 'year' => 3990]],
                'default' => false,
                'sort' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->withoutGlobalScopes()->updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'feature_flags' => $plan['flags'],
                    'prices' => $plan['prices'],
                    'is_default' => $plan['default'],
                    'sort_order' => $plan['sort'],
                ],
            );
        }
    }
}
