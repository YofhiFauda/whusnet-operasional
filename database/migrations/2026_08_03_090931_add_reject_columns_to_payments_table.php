<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalur resmi membatalkan payment yang salah input. `Payment.php` sudah
     * lama mengantisipasi `payment_status → DITOLAK` (audit action 'cancel'
     * di booted()), tapi belum ada jalur yang benar-benar menulisnya —
     * jebakan laten (docs/plan/analisa-billing-tagihan-pembayaran-
     * kolektor.md §A-6, §A-7 #7).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('reject_reason')->nullable()->after('payment_status');
            $table->timestamp('rejected_at')->nullable()->after('reject_reason');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['reject_reason', 'rejected_at']);
        });
    }
};
