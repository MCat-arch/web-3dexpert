<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            /*
             * Optional link to a stage. An attachment with an update shows up
             * inside that timeline step; one without it is a project-level
             * progress file.
             */
            $table->foreignId('project_update_id')->nullable()->constrained()->cascadeOnDelete();

            // 'progress' (visible while the project runs) or 'deliverable'
            // (the finished files, only shown once the project is completed).
            $table->string('kind', 20)->default('progress');

            $table->string('title')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_kb')->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['project_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachments');
    }
};
