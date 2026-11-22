<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sweeps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('running');
            $table->unsignedInteger('cells_total')->default(0);
            $table->unsignedInteger('cells_collected')->default(0);
            $table->unsignedInteger('cells_failed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });

        Schema::create('sweep_cells', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sweep_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_market_id')->constrained()->cascadeOnDelete();
            $table->string('engine', 32);
            $table->unsignedTinyInteger('run_no');
            /** hash(prompt_id|engine|market|date|run) — unique across all time. */
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 16)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['sweep_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweep_cells');
        Schema::dropIfExists('sweeps');
    }
};
