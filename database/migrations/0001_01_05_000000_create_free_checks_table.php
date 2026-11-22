<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The public lead magnet: one row per run, with the grade the visitor
        // saw. No workspace — free checks happen before anyone signs up.
        Schema::create('free_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('brand_name');
            $table->string('website');
            $table->string('offering');
            $table->string('grade', 2);
            $table->unsignedTinyInteger('mention_rate');
            $table->unsignedSmallInteger('answers_count');
            $table->unsignedSmallInteger('named_count');
            $table->decimal('cost_usd', 10, 4)->default(0);
            /** Salted hash of the IP — rate limiting without storing the IP. */
            $table->string('ip_hash', 64);
            $table->timestamps();

            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_checks');
    }
};
