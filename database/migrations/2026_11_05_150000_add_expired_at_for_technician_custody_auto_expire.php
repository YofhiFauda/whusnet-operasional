<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto-expire barang di tangan teknisi (laporan user 2026-11-05, gap #1
     * dari 3 feature report "Barang di Tangan Teknisi akan hilang dalam 24
     * jam atau ganti hari"). `expired_at` SENGAJA kolom timestamp TERPISAH,
     * BUKAN status enum baru (`CustodyStatus`/`SerialStatus`) — nambah status
     * baru berarti HARUS merelaksasi semua guard `status !== ISSUED` di
     * install/deploy/consume (`InventoryService::installSerial()`,
     * `deploySerialAt()`, dkk), risiko blokir teknisi yang MASIH fisik
     * pegang barang itu buat selesaikan pekerjaan cuma karena lewat tengah
     * malam. Kolom timestamp nullable = sinyal visibilitas/overdue doang —
     * `status` tetap ISSUED/PARTIALLY_USED seperti biasa, SELURUH jalur
     * install/deploy/consume/consumeFromCustody/Reassign-return TIDAK perlu
     * disentuh sama sekali.
     *
     * Keputusan user 2026-11-06 (REVISI dari 2026-11-05): auto-expire
     * langsung mengkredit stok Gudang Cabang TANPA konfirmasi fisik —
     * gak realistis minta PIC/teknisi tap tombol tengah malam. Lihat
     * docblock `TechnicianCustodyExpiryService` buat mitigasi risikonya.
     *
     * `inventory_rolls` ikut ditambah kolom yang sama (2026-11-06) — Roll
     * kabel per-roll juga bisa nyangkut di tangan teknisi kelewat hari sama
     * kayak custody qty & SN, scope awal (2026-11-05) kelewatan nyakup ini.
     */
    public function up(): void
    {
        Schema::table('technician_custody', function (Blueprint $table) {
            $table->timestamp('expired_at')->nullable()->after('issued_at');
        });

        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->timestamp('expired_at')->nullable()->after('status');
        });

        Schema::table('inventory_rolls', function (Blueprint $table) {
            $table->timestamp('expired_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('technician_custody', function (Blueprint $table) {
            $table->dropColumn('expired_at');
        });

        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->dropColumn('expired_at');
        });

        Schema::table('inventory_rolls', function (Blueprint $table) {
            $table->dropColumn('expired_at');
        });
    }
};
