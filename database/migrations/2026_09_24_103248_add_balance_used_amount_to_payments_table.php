<?php

use App\Enums\BalanceMutationSource;
use App\Enums\CustomerBalanceMutationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADHOC-92 (G4) — porsi `payments.amount` yang sebenarnya dibayar dari
     * Saldo Pelanggan, bukan uang fisik. Tanpa kolom ini, laporan kas
     * (AdminCashBalanceService/CollectorBalanceService) menghitung saldo yang
     * dipakai sebagai uang yang harus disetor — padahal uangnya sudah masuk
     * kas saat kredit pertama kali terjadi (overpay), bukan saat dipakai.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('balance_used_amount', 12, 2)->default(0)->after('amount');
        });

        // Backfill dari ledger debit `pakai_manual` (satu-satunya jalur pakai
        // saldo yang sudah ada sebelum ADHOC-92, keyed ke payment KONSUMEN —
        // lihat CustomerBalanceService::debit()). Debit `pembatalan` SENGAJA
        // tidak dihitung: payment_id di baris itu menunjuk payment SUMBER
        // kredit yang dibalik, bukan payment yang memakai saldo.
        DB::table('payments')
            ->whereIn('id', function ($query) {
                $query->select('payment_id')
                    ->from('customer_balance_mutations')
                    ->where('type', CustomerBalanceMutationType::DEBIT->value)
                    ->where('source', BalanceMutationSource::PAKAI_MANUAL->value)
                    ->whereNotNull('payment_id');
            })
            ->update([
                'balance_used_amount' => DB::raw(
                    '(select coalesce(sum(amount), 0) from customer_balance_mutations '
                    ."where customer_balance_mutations.payment_id = payments.id and type = 'debit' and source = 'pakai_manual')"
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('balance_used_amount');
        });
    }
};
