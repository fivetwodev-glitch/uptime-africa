<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'name']);
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            $table->unsignedSmallInteger('interval_minutes')->default(5);
            $table->unsignedTinyInteger('timeout_seconds')->default(10);
            // Liste de codes HTTP considérés comme « en ligne », ex. [200, 301].
            $table->json('expected_status_codes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_check_at']);
            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
        Schema::dropIfExists('clients');
    }
};
