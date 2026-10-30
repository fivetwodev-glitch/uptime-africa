<?php

use App\Support\SchemaConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agrégats journaliers : conservés après la purge des contrôles bruts.
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->ulid('agency_id');
            $table->ulid('site_id');
            $table->date('date');
            $table->unsignedInteger('checks_total');
            $table->unsignedInteger('checks_up');
            $table->decimal('uptime_percent', 5, 2);
            $table->unsignedInteger('avg_response_ms')->nullable();
            $table->unsignedSmallInteger('incidents_count')->default(0);
            $table->unsignedInteger('downtime_seconds')->default(0);

            $table->foreign(['site_id', 'agency_id'], 'fk_daily_stats_site_same_agency')->references(['id', 'agency_id'])->on('sites')->cascadeOnDelete();
            $table->unique(['site_id', 'date']);
        });

        SchemaConstraints::check('daily_stats', 'chk_daily_stats_counts', 'checks_up <= checks_total');
        SchemaConstraints::check('daily_stats', 'chk_daily_stats_percent', 'uptime_percent between 0 and 100');
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
    }
};
