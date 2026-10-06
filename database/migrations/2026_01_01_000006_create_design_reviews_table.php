<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces: review_design.
 *
 * Deliberately NOT merged into testimonials -- your decision (#1). Given its
 * own columns so it can grow independently (legacy had only youtube_url).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('youtube_url');
            $table->string('youtube_id')->nullable()->index();
            $table->json('title')->nullable();       // translatable
            $table->json('description')->nullable(); // translatable
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_reviews');
    }
};
