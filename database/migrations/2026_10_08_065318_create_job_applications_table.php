<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_vacancy_id')->constrained()->cascadeOnDelete();

            /*
             * nullOnDelete, NEVER cascade -- this is the user's stated
             * requirement: "I need the admin to be able to access it anytime
             * even if the user logout or already deleted."
             *
             * That requirement is about ownership, not storage: the
             * application belongs to the vacancy, not to the account. Cascade
             * here would destroy the hiring record along with the account.
             */
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            /*
             * Applicant identity snapshotted at submission.
             *
             * This is what makes a deleted account's application still
             * readable, and it also stops a later profile edit from silently
             * rewriting who applied for what.
             */
            $table->string('applicant_name');
            $table->string('applicant_email');
            $table->string('applicant_phone')->nullable();

            $table->text('cover_letter')->nullable();

            $table->string('status')->default('submitted')->index();
            $table->timestamp('status_changed_at')->nullable();

            // Which staff member last moved it. nullOnDelete: losing an
            // employee must not delete the applications they reviewed.
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // Staff-only. Must never reach an applicant-facing response.
            $table->text('internal_notes')->nullable();

            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();

            /*
             * One application row per customer per vacancy (A4).
             *
             * Plain unique rather than partial: MySQL has no portable partial
             * unique index, so a withdrawn application is REUSED on reapply
             * rather than inserted alongside. SubmitJobApplication handles
             * that -- the same approach as event registrations.
             */
            $table->unique(['job_vacancy_id', 'customer_id']);

            // Staff filter a vacancy's applications by pipeline stage.
            $table->index(['job_vacancy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
