<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_traffic', function (Blueprint $table) {
            $table->id();

            // Path only -- no query string, so URLs with tokens/emails never
            // reach this table.
            $table->string('path', 255);
            $table->string('method', 10)->default('GET');
            $table->unsignedSmallInteger('status_code')->nullable();

            // Same measurement the browser sees: millisecond response time.
            $table->unsignedSmallInteger('duration_ms')->nullable();

            $table->string('locale', 5)->nullable();

            // Daily-rotated hash of the visitor IP. The raw IP is never
            // stored: unique visitors can be counted per day, but one person
            // cannot be followed across days.
            $table->char('visitor_hash', 64)->nullable()->index();

            $table->boolean('is_bot')->default(false);
            $table->boolean('is_staff')->default(false);

            $table->timestamp('created_at')->nullable()->index();

            $table->index(['created_at', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_traffic');
    }
};
