<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Tiket batch (kategori is_batch, mis. "ODP LOS") gak menunjuk SATU
            // pelanggan — customer_id jadi nullable. `customer_name` (kolom
            // snapshot yang udah ada) numpang jadi label bebas ("ODP JTS 13
            // LOSS") buat kasus ini, BUKAN kolom baru — lihat
            // TicketService::create().
            $table->foreignId('customer_id')->nullable()->change();

            // No. HP Pelapor — opsional, buat kasus laporan group/komunitas
            // yang HP pelapornya beda dari HP pelanggan di data master.
            // Kosong → List pakai customer_phone (fallback), lihat
            // Ticket::contactPhone().
            $table->string('reporter_phone', 30)->nullable()->after('customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('reporter_phone');
            $table->foreignId('customer_id')->nullable(false)->change();
        });
    }
};
