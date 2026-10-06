<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces BOTH legacy portfolio modules:
 *   - properties + property_images + property_details  (the newer one)
 *   - desain_rumah + galeri_rumah                      (the older one)
 *
 * You confirmed (#2) that desain_rumah/galeri_rumah exist in production, so
 * they are migrated in rather than dropped.
 *
 * Why unify on the key/value spec shape: desain_rumah stored specs as fixed
 * columns (style, ukuran_tanah, jumlah_kamar_tidur, jumlah_lantai, ...) while
 * properties stored the same information as rows in property_details -- the
 * dump literally contains ('Style', 'Classic-Tropis'). The row shape wins
 * because adding a spec later needs no migration.
 *
 * `likes` is deliberately NOT carried over. detail.php incremented it on every
 * GET page render, so it was a bot-inflated page-view counter with no user
 * identity and no dedup -- it could never have been a real like count. It is
 * split into:
 *   - view_count   : incremented outside the render path (see Portfolio model)
 *   - is_featured  : an editorial flag, which is what the homepage actually
 *                    needs. The migrator seeds it from the current top row so
 *                    nothing visibly changes at launch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->json('title');                   // translatable
            $table->string('slug')->unique();        // replaces detail.php?id=N
            $table->json('description')->nullable(); // translatable <- keterangan
            $table->decimal('price', 15, 2)->nullable();
            $table->string('location')->nullable()->index();

            // Promoted to real columns because they are filtered/sorted on.
            // Everything else lives in portfolio_specs.
            $table->string('style')->nullable()->index();
            $table->unsignedTinyInteger('floors')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->unsignedInteger('land_size')->nullable()->comment('m2');
            $table->unsignedInteger('building_size')->nullable()->comment('m2');

            $table->unsignedBigInteger('view_count')->default(0);
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();

            // Provenance, so you can tell where a row came from after the
            // migration and audit anything that looks wrong.
            $table->string('legacy_source')->nullable()
                ->comment('properties | desain_rumah');
            $table->unsignedBigInteger('legacy_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['legacy_source', 'legacy_id']);
        });

        Schema::create('portfolio_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            // A path on a private disk for uploads...
            $table->string('image_path')->nullable();
            // ...or an absolute URL, because legacy property_images rows hold
            // external Unsplash URLs alongside local filenames.
            $table->string('external_url')->nullable();
            $table->json('alt_text')->nullable();  // translatable
            $table->boolean('is_cover')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('portfolio_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->json('label');  // translatable, e.g. {"id":"Gaya","en":"Style"}
            $table->json('value');  // translatable, e.g. {"id":"Klasik-Tropis",...}
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_specs');
        Schema::dropIfExists('portfolio_images');
        Schema::dropIfExists('portfolios');
    }
};
