<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name');
            $table->string('description', 255)->nullable();
            /**
             * Feature flags drive the whole UI and the collector:
             * answers_per_month, brands, seats, prompts, engines (list),
             * api_access, white_label, byok, daily_frequency, app_checks.
             */
            $table->json('feature_flags')->default('{}');
            /** {"USD": {"month": 0, "year": 0}, ...} */
            $table->json('prices')->default('{}');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status', 16)->default('active');
            $table->string('provider', 32)->default('fake');
            $table->string('provider_reference', 64)->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_period_end']);
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            /** Gateway event/transaction id: UNIQUE — a replayed webhook lands here and is ignored. */
            $table->string('provider_event_id', 128)->unique();
            $table->string('type', 32)->default('subscription.payment');
            $table->decimal('amount_usd', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 16)->default('received');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });

        $this->seedPlans();
    }

    /**
     * Plans are install-time base data: seeded with the schema so every fresh
     * install (and the test suite) has a default plan. Operators edit them in
     * the admin panel afterwards.
     */
    private function seedPlans(): void
    {
        $plans = [
            ['free', 'Free', 'Try it on one brand before you decide.',
                ['answers_per_month' => 150, 'brands' => 1, 'seats' => 3, 'prompts' => 10],
                ['USD' => ['month' => 0, 'year' => 0]], true, 1],
            ['starter', 'Starter', 'One brand, measured properly.',
                ['answers_per_month' => 2000, 'brands' => 1, 'seats' => 3, 'prompts' => 50,
                    'engines' => ['chatgpt', 'gemini', 'google-ai-overviews', 'google-ai-mode', 'perplexity'],
                    'api_access' => true, 'white_label' => true, 'app_checks' => true],
                ['USD' => ['month' => 49, 'year' => 490]], false, 2],
            ['growth', 'Growth', 'More brands, prompts and engines for a growing team.',
                ['answers_per_month' => 10000, 'brands' => 3, 'seats' => 10, 'prompts' => 150,
                    'engines' => ['chatgpt', 'gemini', 'google-ai-overviews', 'google-ai-mode', 'claude', 'deepseek'],
                    'api_access' => true, 'white_label' => true, 'app_checks' => true],
                ['USD' => ['month' => 149, 'year' => 1490]], false, 3],
            ['agency', 'Agency', 'Client brands in one workspace, under your own theme.',
                ['answers_per_month' => 30000, 'brands' => 25, 'seats' => 25, 'prompts' => 600,
                    'engines' => ['chatgpt', 'gemini', 'google-ai-overviews', 'google-ai-mode', 'claude', 'perplexity', 'grok', 'deepseek', 'le-chat'],
                    'api_access' => true, 'white_label' => true, 'byok' => true, 'app_checks' => true, 'sso' => true],
                ['USD' => ['month' => 399, 'year' => 3990]], false, 4],
        ];

        foreach ($plans as [$slug, $name, $description, $flags, $prices, $isDefault, $sort]) {
            DB::table('plans')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'feature_flags' => json_encode($flags),
                    'prices' => json_encode($prices),
                    'is_default' => $isDefault,
                    'sort_order' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
