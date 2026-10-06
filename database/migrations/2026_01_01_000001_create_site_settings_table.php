<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces: features_section, alur_section, location_section, and the
 * "main" row of mengapa_section.
 *
 * Legacy code addressed those as singleton rows by magic id
 * (edit.php: $features_id = 1; "UPDATE alur_section ... LIMIT 1").
 * A key/value store removes the magic number entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // 'string' | 'text' | 'integer' | 'boolean' | 'json'
            $table->string('type')->default('string');
            // Non-translatable scalar value.
            $table->text('value')->nullable();
            // Translatable value: {"id": "...", "en": "..."}
            $table->json('value_translations')->nullable();
            $table->boolean('is_translatable')->default(false);
            $table->string('group')->default('general')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
