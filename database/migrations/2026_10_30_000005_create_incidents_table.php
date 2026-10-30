<?php

use App\Support\SchemaConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('agency_id');
            $table->ulid('site_id');
            $table->string('type', 32);
            $table->string('status', 20)->default('open');
            $table->text('description')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('confirmed_failures')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            // 1 tant que l'incident n'est pas résolu : un seul incident ouvert par site.
            $table->unsignedTinyInteger('open_marker')->nullable()
                ->storedAs('case when resolved_at is null then 1 else null end');
            $table->timestamps();

            $table->foreign(['site_id', 'agency_id'], 'fk_incidents_site_same_agency')->references(['id', 'agency_id'])->on('sites')->cascadeOnDelete();
            $table->unique(['id', 'agency_id']);
            $table->unique(['site_id', 'open_marker'], 'incidents_one_open_per_site');
            $table->index(['site_id', 'started_at']);
            $table->index(['agency_id', 'status', 'started_at']);
        });

        SchemaConstraints::check('incidents', 'chk_incidents_resolution', 'resolved_at is null or resolved_at >= started_at');
        SchemaConstraints::check('incidents', 'chk_incidents_status', "(status = 'resolved') = (resolved_at is not null)");
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
