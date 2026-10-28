<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            /*
             * A number the customer can read at a glance, maintained by staff
             * rather than derived from the timeline: stages are not equal in
             * size, so counting "done" stages would understate a project that
             * is 90% through its last one.
             */
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('current_stage');

            // Closing explanation shown in the "Hasil Akhir" section of the
            // tracker when the project is completed.
            $table->text('summary')->nullable()->after('progress_percent');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['progress_percent', 'summary']);
        });
    }
};
