<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces: job_detail.
 *
 * Legacy had no draft state and no soft delete, so test rows shipped straight
 * to the public careers page -- the production dump contains
 * 'asddddddddddddddMARKETING EXECUTIVE' in department 'Marketingssssssssssss'.
 * is_published defaults to false and the migrator leaves suspicious rows
 * unpublished rather than deleting them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->json('title');        // translatable
            $table->string('slug')->unique();
            $table->string('location')->index();
            $table->string('department')->index();   // <- departemen
            $table->string('employment_type')->index(); // <- type
            $table->json('requirement')->nullable();    // translatable
            $table->json('description')->nullable();    // translatable
            $table->string('contact_url')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('closes_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_vacancies');
    }
};
