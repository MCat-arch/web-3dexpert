<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Differentiate online-LMS courses (beginner/intermediate) from
     * real-world mentoring courses (advanced).
     *
     * - has_lms      : true  → show lesson player on the website
     *                  false → show a WhatsApp / contact CTA instead
     * - category     : free-text tag for grouping (e.g. "Desain 3D", "Rendering")
     * - whatsapp_cta : override the default company WhatsApp for advanced courses
     *                  so each course can route to a specific mentor number
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // After 'level' column
            $table->boolean('has_lms')->default(true)->after('level')
                ->comment('true = video/LMS player; false = real-world / WhatsApp CTA');
            $table->string('category')->nullable()->after('has_lms')
                ->comment('Optional category tag shown on the catalog card');
            $table->string('whatsapp_cta')->nullable()->after('category')
                ->comment('Override WhatsApp URL for advanced courses; falls back to company_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['has_lms', 'category', 'whatsapp_cta']);
        });
    }
};
