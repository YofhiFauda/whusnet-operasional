<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop `payments_invoice_date_amount_unique` (invoice_id, payment_date,
     * amount). Index ini dipasang untuk menolak retry/double-submit legacy,
     * tapi ikut menolak cicilan SAH — nominal sama, invoice sama, tanggal
     * sama (mis. dua sesi setoran kolektor di hari yang sama) — dan
     * menghalangi koreksi "void lalu input ulang nominal & tanggal sama".
     *
     * Penggantinya BUKAN index yang lebih longgar (opsi lebarkan dengan
     * payment_batch_id ditolak — MySQL memperlakukan NULL != NULL di unique
     * index, jadi tetap bolong di jalur non-batch), tapi dua guard aplikasi:
     *   1. PaymentObserver::rejectBurstDuplicate() — dobel-submit satu
     *      payment (jalur single-payment), lihat migration burst-dedup.
     *   2. payment_batches.idempotency_key — dobel-submit satu SESI batch
     *      (jalur kolektor, Fase 2), lihat migration create_payment_batches.
     *
     * WAJIB: PaymentObserver::rejectBurstDuplicate() harus SUDAH ada &
     * di-deploy sebelum migration ini jalan — kalau tidak, ada jendela waktu
     * tanpa proteksi dobel-submit sama sekali di jalur single-payment.
     *
     * docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §A-7 #6,
     * §C-2(a), §D-9 no. 2.
     */
    public function up(): void
    {
        // `payments_invoice_date_amount_unique` adalah SATU-SATUNYA index yang
        // menaungi `invoice_id` — MySQL/InnoDB menolak drop-nya selama
        // `payments_invoice_id_foreign` masih bergantung padanya (beda dari
        // SQLite yang dipakai test, yang tak menegakkan ini). Index biasa
        // wajib ada dulu sebelum unique index-nya dicabut, kalau tidak FK
        // constraint kehilangan index pendukung.
        Schema::table('payments', function (Blueprint $table) {
            $table->index('invoice_id', 'payments_invoice_id_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_invoice_date_amount_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['invoice_id', 'payment_date', 'amount'], 'payments_invoice_date_amount_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_invoice_id_idx');
        });
    }
};
