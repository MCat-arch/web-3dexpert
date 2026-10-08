<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public accounts, deliberately NOT in the `users` table (decision D1).
 *
 * `users` backs the Filament panel, and User::canAccessPanel() returns true for
 * role 'editor' -- which is the column default. A public registration written
 * into `users` would therefore hand every signup admin access at /admin, which
 * is exactly the legacy reg.php flaw this rebuild exists to remove ("anyone who
 * registered became a superadmin").
 *
 * A separate table behind a separate guard removes the possibility rather than
 * defending against it with a role check that someone can later get wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Reused by the progress tracker (P7), which verifies a project
            // code against the customer's own phone number.
            $table->string('phone')->nullable()->index();

            // Staff deactivation (P8). Blocks login without deleting history.
            $table->boolean('is_active')->default(true)->index();

            // Support context: "when did this person last sign in?" is the
            // first question asked about a disputed enrollment. Mirrors the
            // same column on `users`.
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            // P6 depends on soft deletes: a deleted customer must leave their
            // job application and its documents readable by staff.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
