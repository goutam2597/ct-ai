<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sweep_cell_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->string('engine', 32);
            $table->string('method', 16);
            $table->string('model', 64)->nullable();
            $table->string('status', 16)->default('collected');
            $table->text('answer_text')->nullable();
            $table->text('failure_reason')->nullable();
            /** Denormalized flags so rates need no joins. Failed answers keep them null. */
            $table->boolean('named_you')->nullable();
            $table->boolean('cited_you')->nullable();
            $table->unsignedTinyInteger('position')->nullable();
            $table->string('tone', 8)->nullable();
            $table->decimal('provider_cost_usd', 10, 4)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->json('searches_run')->nullable();
            $table->json('cited_links')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status', 'created_at']);
            $table->index(['brand_id', 'engine', 'status']);
        });

        Schema::create('answer_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            /** The tracked brand this mention belongs to: the brand or one of its competitors. */
            $table->unsignedTinyInteger('position')->nullable();
            $table->string('tone', 8)->nullable();
            $table->string('matched_by', 8);
            $table->boolean('is_you')->default(false);
            $table->timestamps();

            $table->index(['answer_id']);
            $table->index(['brand_id', 'is_you']);
        });

        Schema::create('answer_citations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('domain', 255);
            $table->string('owner_type', 16);
            $table->timestamps();

            $table->index(['answer_id']);
            $table->index(['domain']);
        });

        Schema::create('answer_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('engine', 32);
            $table->date('date');
            $table->unsignedInteger('answers')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('mentions_you')->default(0);
            $table->unsignedInteger('citations_you')->default(0);
            $table->unsignedInteger('named_first')->default(0);
            $table->unsignedInteger('position_sum')->default(0);
            $table->unsignedInteger('positive')->default(0);
            $table->unsignedInteger('neutral')->default(0);
            $table->unsignedInteger('negative')->default(0);
            $table->timestamps();

            $table->unique(['brand_id', 'engine', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_daily_stats');
        Schema::dropIfExists('answer_citations');
        Schema::dropIfExists('answer_mentions');
        Schema::dropIfExists('answers');
    }
};
