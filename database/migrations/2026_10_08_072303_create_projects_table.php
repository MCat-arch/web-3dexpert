<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            /*
             * The code the customer types. Random and non-sequential -- never
             * derived from the primary key.
             *
             * A sequential code would turn this feature into an enumeration
             * oracle: type 1, 2, 3 and read every customer's project name and
             * status. That is the whole reason D2 exists.
             */
            $table->string('project_code', 32)->unique();

            /*
             * Customer contact, SNAPSHOTTED on the project rather than joined
             * to `customers`.
             *
             * The tracker works without an account (A6) and the customer may
             * never register, so there is nothing to join to. These columns
             * are also the second factor in the lookup.
             */
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();

            $table->string('title');
            $table->string('current_stage')->nullable();

            $table->string('status')->default('in_progress');

            $table->date('started_at')->nullable();
            $table->date('estimated_completion_at')->nullable();
            $table->date('completed_at')->nullable();

            // Lets staff withhold a project from the tracker without deleting
            // it -- a dispute, or a project not ready to be shown.
            $table->boolean('is_visible')->default(true);

            $table->timestamps();
            $table->softDeletes();

            /*
             * The lookup filters on code + status + visibility in one query,
             * so the index covers all three. The phone/email comparison then
             * runs against the single matched row.
             */
            $table->index(['project_code', 'status', 'is_visible']);

            // Staff search by the customer's contact details.
            $table->index('customer_phone');
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
