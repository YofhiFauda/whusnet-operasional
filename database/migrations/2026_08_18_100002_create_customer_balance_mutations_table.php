<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger append-only saldo pelanggan (lebih bayar yang bisa dipakai di
     * pembayaran berikutnya). SENGAJA bukan kolom `customers.balance` yang
     * di-increment/decrement — pola itu berhenti benar begitu satu payment
     * sumbernya di-reject (lihat alasan yang sama di CollectorBalanceService
     * & AdminCashBalanceService). Saldo = SUM(credit) - SUM(debit), selalu
     * dihitung ulang dari baris-baris di sini (CustomerBalanceService).
     */
    public function up(): void
    {
        Schema::create('customer_balance_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // 'credit' | 'debit' — App\Enums\CustomerBalanceMutationType.
            $table->string('type', 20);
            // Selalu positif; arah ditentukan `type`, bukan tanda minus.
            $table->decimal('amount', 12, 2);
            // Peran ganda tergantung `type`: kredit → payment SUMBER overpay;
            // debit → payment yang MEMAKAI saldo ini. Nullable untuk baris
            // backfill manual (BackfillCustomerBalanceFromOverpay) yang tak
            // berasal dari satu payment spesifik.
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            // POP scope wajib — konsisten dengan seluruh tabel uang lain di
            // repo ini (collector_deposits, cash_deposits).
            $table->foreignId('pop_id')->constrained('pops')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_balance_mutations');
    }
};
