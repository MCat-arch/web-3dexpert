<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces: layanan_section (+ its never-created pdf_blob column).
 *
 * Fixes, versus legacy:
 *  - price/original_price were varchar(50) holding '24.0002' / '98.6002'.
 *    Now decimal(15,2). The migrator flags unparseable values instead of
 *    guessing.
 *  - `features` was a JSON array crammed into a `text` column, with literal
 *    \r\n inside it. Now one row per feature in service_features.
 *  - upload.php wrote PDFs as MySQL BLOBs into a column that did not exist.
 *    Now a path on a private disk, served via a signed route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->json('title');                 // translatable
            $table->string('slug')->unique();
            $table->json('description')->nullable(); // translatable
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('original_price', 15, 2)->nullable();
            $table->json('discount_text')->nullable(); // translatable
            $table->string('brochure_path')->nullable();
            $table->string('icon_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_published')->default(false)->index();
            // Set when the legacy price string did not parse cleanly.
            $table->boolean('needs_review')->default(false);
            $table->timestamps();
        });

        Schema::create('service_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->json('label');  // translatable
            // Legacy lists used a bare "Gambar Kerja Struktur:" entry as a
            // visual heading. This flag preserves that without a second table.
            $table->boolean('is_heading')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_features');
        Schema::dropIfExists('services');
    }
};
