<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public contact form was removed by D12 (contact is WhatsApp-only).
 * Nothing writes contact_submissions any more; the original create migration
 * is left untouched because it is already applied in production.
 *
 * Before deploying, run:
 *   SELECT COUNT(*) FROM contact_submissions;
 * If the count is non-zero, archive the rows first. This migration is
 * intentionally a plain drop so a stale table cannot keep collecting dust.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contact_submissions');
    }

    public function down(): void
    {
        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }
};
