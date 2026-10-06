<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces: testimonial_section.
 *
 * Kept separate from design_reviews by your decision (#1) -- they are
 * different features, not two shapes of the same thing.
 *
 * Business rule carried over from edit.php: at least 2 rows must be
 * highlighted. Enforced in the form request / admin validation, not here,
 * because a DB constraint cannot express it portably.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('youtube_url');
            // Cached on save so views never parse a URL at render time
            // (legacy did this per-iframe inside the page loop).
            $table->string('youtube_id')->nullable()->index();
            $table->json('title')->nullable();  // translatable
            $table->json('client_name')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->boolean('is_highlighted')->default(false)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
