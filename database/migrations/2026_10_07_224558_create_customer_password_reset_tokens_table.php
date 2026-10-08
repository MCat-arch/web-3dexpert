<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A separate reset-token table for the `customer` broker.
 *
 * Laravel's default `password_reset_tokens` is keyed by email alone, with no
 * notion of which provider issued the token. Sharing it between `users` and
 * `customers` would let a token issued for one be redeemed against the other
 * wherever an address exists in both tables -- a staff account takeover through
 * the public password-reset form.
 *
 * Same shape as the framework's table; only the name differs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
    }
};
