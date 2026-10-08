<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            // Translatable (HasTranslations), same convention as portfolios.
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('description')->nullable();

            $table->string('cover_path')->nullable();

            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();

            $table->string('location_type')->default('offline');
            $table->string('venue')->nullable();
            $table->string('online_url')->nullable();

            /*
             * NULL means unlimited. Not 0 -- 0 is a real value meaning "no
             * seats", and conflating the two is how an event silently stops
             * accepting registrations.
             */
            $table->unsignedInteger('capacity')->nullable();

            /*
             * Denormalised seat counter, and that is deliberate.
             *
             * Capacity is a concurrency problem, not a validation problem: a
             * count() read followed by an insert oversells, because two
             * requests both read 49 of 50. This column exists so the claim can
             * be a single conditional UPDATE that the database arbitrates --
             * see EnrollInEvent.
             *
             * Denormalisation has a cost: it can drift. P5a schedules a nightly
             * reconciliation job that compares it against the live count and
             * emits a structured operational alert for any correction. P8 adds
             * the durable audit record.
             */
            $table->unsignedInteger('seats_taken')->default(0);

            // 0 = free. P5b adds the paid path; the column exists now so the
            // free path does not need a schema change later.
            $table->decimal('price', 15, 2)->default(0);

            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();

            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            // The public list filters on published + upcoming and sorts by
            // start date: one composite index rather than three single-column
            // ones, so the sort is covered too.
            $table->index(['is_published', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
