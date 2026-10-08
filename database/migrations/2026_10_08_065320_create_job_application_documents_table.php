<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_application_documents', function (Blueprint $table) {
            $table->id();

            // Documents hang off the APPLICATION, not the customer. That is
            // what keeps them readable after an account is deleted.
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();

            $table->string('type')->default('cv');

            /*
             * The disk is recorded per row, not assumed.
             *
             * Hosting is still undecided (README decision #6), so these files
             * may move to S3 later. Storing the disk means a migration can
             * move rows in batches and both locations stay readable during the
             * move -- rather than a flag day where every path is reinterpreted
             * at once.
             */
            $table->string('disk')->default('applications');
            $table->string('path');

            // The name the applicant uploaded, for display and download only.
            // Never used as the on-disk name -- see SubmitJobApplication.
            $table->string('original_filename');

            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            // Lets the retention purge and any future storage migration verify
            // a file is what the row claims before deleting or moving it.
            $table->char('checksum', 64)->nullable()->index();

            $table->timestamps();

            $table->index(['job_application_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_application_documents');
    }
};
