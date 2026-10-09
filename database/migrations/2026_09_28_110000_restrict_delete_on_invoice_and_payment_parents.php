<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan & pembayaran TIDAK boleh ikut terhapus diam-diam karena induknya
 * dihapus (keputusan user 2026-09-28).
 *
 * Sebelumnya semua FK induk → invoices/payments `cascadeOnDelete`:
 * menghapus customer_services (CustomerController::update() dengan paket
 * kosong), pelanggan (tombol Hapus, hard delete), POP, atau paket ikut
 * menyapu SELURUH tagihan + pembayaran di level database — tanpa observer,
 * tanpa audit log. Melanggar "tagihan lunas tidak dihapus sembarangan" &
 * "semua perubahan pembayaran masuk audit".
 *
 * Sekarang `restrictOnDelete`: penghapusan induk yang masih punya tagihan /
 * pembayaran GAGAL, bukan menghapus riwayat keuangan. Pelanggan bertagihan
 * diputus langganan (terminated), bukan dihapus.
 *
 * SENGAJA tidak diubah: invoice → payments / invoice_items (anak dari
 * tagihan itu sendiri; jalur yang menghapus tagihan — mis.
 * CleanupLegacyDuplicateInvoicesCommand — sudah menghapus anaknya eksplisit).
 *
 * Migrasi ini tidak mengubah data apa pun, cuma aturan relasi.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, string>> tabel => [kolom => tabel induk]
     */
    private array $foreignKeys = [
        'invoices' => [
            'customer_id' => 'customers',
            'customer_service_id' => 'customer_services',
            'pop_id' => 'pops',
            'internet_package_id' => 'internet_packages',
        ],
        'payments' => [
            'customer_id' => 'customers',
            'pop_id' => 'pops',
        ],
    ];

    public function up(): void
    {
        $this->rebuild(restrict: true);
    }

    public function down(): void
    {
        $this->rebuild(restrict: false);
    }

    private function rebuild(bool $restrict): void
    {
        foreach ($this->foreignKeys as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $restrict) {
                foreach ($columns as $column => $parent) {
                    $table->dropForeign([$column]);

                    $foreign = $table->foreign($column)->references('id')->on($parent);
                    $restrict ? $foreign->restrictOnDelete() : $foreign->cascadeOnDelete();
                }
            });
        }
    }
};
