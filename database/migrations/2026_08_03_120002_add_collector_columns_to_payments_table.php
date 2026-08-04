<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `collected_by` = snapshot BEKU siapa yang faktanya menagih pembayaran
     * ini. SENGAJA tidak disalin otomatis dari `customers.collector_id` —
     * diisi sesuai jalur masuk (batch kolektor = terisi, jalur Tagihan
     * langsung = null). Kalau disalin buta, laporan kolektor mencatat uang
     * yang tak pernah dia tagih.
     *
     * `collected_date` = tanggal uang diterima DI LAPANGAN, terpisah dari
     * `payment_date` (tanggal posting/validasi kantor) — mencegah
     * pendapatan lintas-bulan salah potong saat kolektor telat setor.
     *
     * docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §B-3.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('collected_by')->nullable()->after('received_by')
                ->constrained('users')->nullOnDelete();
            $table->date('collected_date')->nullable()->after('payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collected_by');
            $table->dropColumn('collected_date');
        });
    }
};
