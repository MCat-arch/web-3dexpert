<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->json('title');
            $table->string('content_type')->default('video');
            $table->string('youtube_url')->nullable();
            $table->string('youtube_id')->nullable();
            $table->json('body')->nullable();
            $table->foreignId('attachment_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_free_preview')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_section_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
    }
};
