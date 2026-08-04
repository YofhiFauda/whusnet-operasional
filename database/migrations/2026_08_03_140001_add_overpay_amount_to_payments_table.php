<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lebih bayar versi INFORMATIF, bukan saldo kredit.
     *
     * `amount` tetap dibatasi `max: remaining_amount` (validasi lama tidak
     * dilonggarkan) supaya matematika invoice tetap utuh: paid_amount tak
     * pernah melebihi total_amount. Kelebihan uang fisik dicatat terpisah di
     * kolom ini — murni catatan "pelanggan menyerahkan lebih sekian", TANPA
     * ledger, TANPA auto-apply ke tagihan berikutnya.
     *
     * Ini SENGAJA bukan implementasi saldo kredit (§D-5 tetap ⛔ DILUAR
     * SCOPE). Kalau nanti kredit sungguhan dibutuhkan, jalurnya tetap
     * `customer_credits` + `payment_allocations` seperti arsip §D-5 —
     * kolom ini tidak boleh dipakai sebagai saldo, karena tak punya sisi
     * debit sama sekali.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('overpay_amount', 12, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('overpay_amount');
        });
    }
};
