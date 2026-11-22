<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            /** Domains that count as the brand's own: subdomains included. */
            $table->json('domains')->default('[]');
            /** Other names (short name, old name, misspellings) counted as the brand. */
            $table->json('aliases')->default('[]');
            /** Engine keys this brand is tracked on, e.g. ["chatgpt","claude"]. */
            $table->json('engines')->default('[]');
            $table->unsignedTinyInteger('runs_per_prompt')->default(3);
            $table->string('frequency', 16)->default('weekly');
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_sweep_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
        });

        Schema::create('brand_markets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('country_code', 2);
            $table->string('language_code', 8);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['brand_id', 'country_code', 'language_code']);
        });

        Schema::create('competitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('domains')->default('[]');
            $table->json('aliases')->default('[]');
            $table->timestamps();

            $table->index('brand_id');
        });

        Schema::create('prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('topic')->nullable();
            $table->string('stage', 16)->nullable();
            $table->string('persona')->nullable();
            $table->string('status', 16)->default('active');
            $table->boolean('is_suggested')->default(false);
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts');
        Schema::dropIfExists('competitors');
        Schema::dropIfExists('brand_markets');
        Schema::dropIfExists('brands');
    }
};
