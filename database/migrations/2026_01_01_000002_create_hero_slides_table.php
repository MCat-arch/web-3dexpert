<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces: hero_banner.
 *
 * Legacy stored a bare filename in `photo_name` and served it from the
 * web-readable assets/images/. Here the path points at a private disk and
 * is served through a controlled route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->json('alt_text')->nullable();  // translatable
            $table->json('caption')->nullable();   // translatable
            $table->string('link_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
