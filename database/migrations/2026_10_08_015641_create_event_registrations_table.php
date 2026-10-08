<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            /*
             * nullOnDelete, never cascade. Deleting a customer must not erase
             * the attendance record -- the event still happened, and staff
             * still need the list. Same ownership reasoning as P6's job
             * applications.
             */
            $table->foreignId('customer_id')->nullable()
                ->constrained()->nullOnDelete();

            /*
             * Attendee identity snapshotted at registration, so the record
             * stays readable after the account is deleted, and so a later name
             * change does not silently rewrite who attended.
             */
            $table->string('attendee_name');
            $table->string('attendee_email');
            $table->string('attendee_phone')->nullable();

            // pending -> confirmed -> attended, or cancelled.
            // Free registrations go straight to confirmed; pending exists for
            // the paid path in P5b.
            $table->string('status')->default('pending')->index();

            // Random, not derived from the id: the code is shown to attendees
            // and must not expose how many people registered.
            $table->string('ticket_code')->unique();

            $table->timestamp('registered_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('attended_at')->nullable();

            $table->timestamps();

            /*
             * One registration row per customer per event.
             *
             * A plain unique index rather than a partial one: MySQL has no
             * portable partial unique index, so a cancelled registration is
             * REUSED on re-registration rather than inserted alongside. The
             * action handles that; see EnrollInEvent.
             */
            $table->unique(['event_id', 'customer_id']);

            // Staff list an event's attendees filtered by status.
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
