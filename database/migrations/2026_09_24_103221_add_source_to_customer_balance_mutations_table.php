<?php

use App\Enums\BalanceMutationSource;
use App\Enums\CustomerBalanceMutationType;
use App\Enums\InvoiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADHOC-92 — sumber baris ledger saldo pelanggan (G2). Dibutuhkan supaya
     * saldo hasil bayar di muka bisa dibedakan dari kelebihan bayar biasa, dan
     * supaya unique index (payment_id, type, source) di bawah tidak bertabrakan
     * saat `reverseCreditForPayment()`/refund saldo menulis baris kedua dengan
     * `payment_id` yang sama (lihat docs/plan/billing/analisa-rancangan-saldo-pelanggan.md §4.1).
     */
    public function up(): void
    {
        Schema::table('customer_balance_mutations', function (Blueprint $table) {
            $table->string('source', 30)->nullable()->after('type');
        });

        // Backfill baris lama (ADHOC-38) berdasar konteks yang tersedia —
        // payment_id null = tidak berasal dari satu payment spesifik (mis.
        // deposit downgrade paket ADHOC-68) → 'backfill'.
        DB::table('customer_balance_mutations')
            ->whereNull('payment_id')
            ->update(['source' => BalanceMutationSource::BACKFILL->value]);

        // Debit lama: baris pembalikan kredit (reject payment) vs pemakaian
        // saldo manual biasa — dibedakan dari format note yang sudah baku
        // sejak CustomerBalanceService::reverseCreditForPayment().
        DB::table('customer_balance_mutations')
            ->where('type', CustomerBalanceMutationType::DEBIT->value)
            ->whereNotNull('payment_id')
            ->where('note', 'like', 'Pembalikan kredit%')
            ->update(['source' => BalanceMutationSource::PEMBATALAN->value]);

        DB::table('customer_balance_mutations')
            ->where('type', CustomerBalanceMutationType::DEBIT->value)
            ->whereNotNull('payment_id')
            ->whereNull('source')
            ->update(['source' => BalanceMutationSource::PAKAI_MANUAL->value]);

        // Kredit lama: overpay dari invoice AWAL = bayar di muka; selain itu
        // kelebihan bayar biasa. Auto-pakai (pakai_otomatis) belum pernah ada
        // sebelum ADHOC-92, jadi tidak ada baris lama bertipe itu.
        $awalPaymentIds = DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('invoices.invoice_type', InvoiceType::AWAL->value)
            ->pluck('payments.id');

        DB::table('customer_balance_mutations')
            ->where('type', CustomerBalanceMutationType::CREDIT->value)
            ->whereNotNull('payment_id')
            ->whereIn('payment_id', $awalPaymentIds)
            ->update(['source' => BalanceMutationSource::BAYAR_DI_MUKA->value]);

        DB::table('customer_balance_mutations')
            ->where('type', CustomerBalanceMutationType::CREDIT->value)
            ->whereNotNull('payment_id')
            ->whereNull('source')
            ->update(['source' => BalanceMutationSource::KELEBIHAN_BAYAR->value]);

        // G10 — idempotensi ledger dijamin skema, bukan cuma kode. `source`
        // wajib ikut kunci: reverseCreditForPayment()/refund saldo menulis
        // baris kedua dengan `payment_id` sama tapi `type` BISA sama juga
        // (dua kredit pada payment yang sama: overpay asli + refund saldo
        // dipakai yang dibalik) — tanpa `source`, (payment_id, type) bertabrakan.
        Schema::table('customer_balance_mutations', function (Blueprint $table) {
            $table->unique(['payment_id', 'type', 'source'], 'customer_balance_mutations_payment_type_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_balance_mutations', function (Blueprint $table) {
            $table->dropUnique('customer_balance_mutations_payment_type_source_unique');
            $table->dropColumn('source');
        });
    }
};
