<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy had a single flat users table with NO role column, and edit.php /
 * editkarir.php gated only on isset($_SESSION["user_id"]). Combined with
 * reg.php being open self-registration, that meant anyone who registered
 * became a superadmin.
 *
 * `role` is the minimum fix and needs no package. If you later install
 * spatie/laravel-permission for per-resource permissions, keep this column as
 * the coarse tier and let the package handle granularity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('editor')->after('password')->index();
            $table->boolean('is_active')->default(true)->after('role')->index();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'last_login_at']);
        });
    }
};
