<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual bank-transfer payments (payment gateway not yet available).
     *
     * Additive only: every column is nullable so existing Midtrans rows and
     * the QRIS flow keep working exactly as before.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('paid_at');
            $table->string('payment_proof_original_name')->nullable()->after('payment_proof_path');
            $table->text('payment_proof_note')->nullable()->after('payment_proof_original_name');
            $table->timestamp('payment_proof_uploaded_at')->nullable()->after('payment_proof_note');
            $table->timestamp('validated_at')->nullable()->after('payment_proof_uploaded_at');
            $table->unsignedBigInteger('validated_by')->nullable()->after('validated_at');

            $table->index('validated_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['validated_by']);
            $table->dropColumn([
                'payment_proof_path',
                'payment_proof_original_name',
                'payment_proof_note',
                'payment_proof_uploaded_at',
                'validated_at',
                'validated_by',
            ]);
        });
    }
};
