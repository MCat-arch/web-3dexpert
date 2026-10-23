<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            // Optional link to a portfolio, personal site, GitHub, or Behance.
            // Stored as submitted so an edited profile does not silently rewrite
            // what the applicant showed at application time.
            $table->string('portfolio_url', 255)->nullable()->after('cover_letter');
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn('portfolio_url');
        });
    }
};
