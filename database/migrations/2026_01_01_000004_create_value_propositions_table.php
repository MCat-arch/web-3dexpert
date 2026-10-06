<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces: mengapa_section (the list1/list2 rows).
 *
 * Legacy abused one table as two lists: row 0 was the "main" record and was
 * skipped by index; every later row held a value in either list1 or list2.
 * Deleting an item did `SET $column = NULL`, leaving all-null orphan rows
 * that could never be cleaned up.
 *
 * Here each item is its own row with an explicit group. The old "main"
 * description and video_url move to site_settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('value_propositions', function (Blueprint $table) {
            $table->id();
            // 'benefit' <- legacy list1, 'process' <- legacy list2
            $table->string('group')->default('benefit')->index();
            $table->json('label');  // translatable
            $table->string('icon_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('value_propositions');
    }
};
