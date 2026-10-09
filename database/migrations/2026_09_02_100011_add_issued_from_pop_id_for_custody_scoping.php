<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ketahuan pas nulis halaman Custody (Fase 8 UI, rancangan-ui.md §2.6):
     * `pop_admin` WAJIB cuma liat custody teknisi yang barangnya diissue dari
     * cabangnya sendiri (§1.3 matrix), tapi `technician_custody` &
     * `inventory_serials` gak nyimpen "diissue dari gudang mana" — begitu
     * status jadi ISSUED, `current_pop_id` di `inventory_serials` DI-NULL-KAN
     * (barang udah pindah dari gudang), dan `technician_custody` emang dari
     * awal gak punya kolom pop sama sekali.
     *
     * Tanpa ini, scoping POP wajib (CLAUDE.md: "query tanpa scope = kebocoran
     * data lintas cabang") kepaksa pakai join rapuh ke ledger tiap render
     * daftar. Kolom ini pola SAMA `current_pop_id` — diisi SEKALI oleh
     * `InventoryIssueService` pas ISSUE terjadi, gak pernah diubah lagi
     * (custody/serial bisa pindah tangan lewat Reassign, tapi "asal
     * gudangnya" tetap histori yang sama).
     */
    public function up(): void
    {
        Schema::table('technician_custody', function (Blueprint $table) {
            $table->foreignId('issued_from_pop_id')->nullable()->after('technician_id')
                ->constrained('pops')->nullOnDelete();
        });

        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->foreignId('issued_from_pop_id')->nullable()->after('current_technician_id')
                ->constrained('pops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('technician_custody', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_from_pop_id');
        });

        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_from_pop_id');
        });
    }
};
