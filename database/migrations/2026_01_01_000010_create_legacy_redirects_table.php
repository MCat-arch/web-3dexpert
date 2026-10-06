<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 301 map. Populated by app:migrate-legacy-data for every
 * detail.php?id=N -> /portofolio/{slug} pair, plus the static route pairs.
 *
 * This is the single highest-value table in the rewrite: the site exists to be
 * found on Google, and without these redirects the cutover silently discards
 * every ranking the domain has earned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_redirects', function (Blueprint $table) {
            $table->id();
            // Path plus query, exactly as the old site served it,
            // e.g. "detail.php?id=1"
            $table->string('old_path')->unique();
            $table->string('new_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_redirects');
    }
};
