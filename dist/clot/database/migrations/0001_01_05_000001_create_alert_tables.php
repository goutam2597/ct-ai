<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule', 48);
            /** Unique per workspace+rule+period: every alert is sent ONCE (VisiGoo parity). */
            $table->string('dedup_key', 96)->unique();
            $table->string('title', 160);
            $table->json('body')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('alert_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // webhook | slack (incoming-webhook URL)
            $table->string('target', 255);
            /** HMAC secret for webhook deliveries (X-Clot-Signature). */
            $table->string('secret', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'type', 'target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_channels');
        Schema::dropIfExists('alert_events');
    }
};
