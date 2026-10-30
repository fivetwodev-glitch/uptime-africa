<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('country_code', 2)->default('CI');
            $table->string('timezone', 64)->default('Africa/Abidjan');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            // Marque les données de démonstration (TestDataSeeder) pour les distinguer et les purger.
            $table->boolean('is_test_data')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
