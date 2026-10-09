<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('stage_name');
            $table->string('status')->default('done');

            /*
             * CUSTOMER-VISIBLE note.
             *
             * There is deliberately no internal-notes column here. If staff
             * need private commentary on a project, add an explicitly separate
             * column and exclude it from the lookup payload -- the way P6
             * handles job_applications.internal_notes. Sharing one field for
             * both audiences is how private remarks reach a customer.
             */
            $table->text('note')->nullable();

            $table->date('occurred_at');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
    }
};
