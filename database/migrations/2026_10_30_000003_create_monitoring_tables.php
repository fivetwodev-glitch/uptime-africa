<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->boolean('is_up');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('error_type', 32)->nullable();
            $table->string('error_message', 500)->nullable();

            $table->index(['site_id', 'checked_at']);
            $table->index('checked_at');
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('error_type', 32)->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('summary', 500)->nullable();
            $table->timestamps();

            $table->index(['site_id', 'resolved_at']);
            $table->index(['agency_id', 'started_at']);
        });

        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('checks_total');
            $table->unsignedInteger('checks_up');
            $table->decimal('uptime_percent', 5, 2);
            $table->unsignedInteger('avg_response_ms')->nullable();
            $table->unsignedSmallInteger('incidents_count')->default(0);

            $table->unique(['site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('checks');
    }
};
