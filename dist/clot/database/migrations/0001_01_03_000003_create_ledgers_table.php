<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ADR-0004: allowance claimed BEFORE the provider call; append-only.
        Schema::create('allowance_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->integer('delta'); // negative = claim, positive = refund
            /** Unique per workspace+period: the same answer cell can never claim twice. */
            $table->string('claim_key', 64)->nullable();
            $table->string('reason', 64);
            $table->timestamps();

            $table->unique(['workspace_id', 'period', 'claim_key']);
            $table->index(['workspace_id', 'period']);
        });

        Schema::create('cost_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $table->string('engine', 32);
            $table->decimal('provider_cost_usd', 10, 4)->default(0);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_ledgers');
        Schema::dropIfExists('allowance_ledgers');
    }
};
