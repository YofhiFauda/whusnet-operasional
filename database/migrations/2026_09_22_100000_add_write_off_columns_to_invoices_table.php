<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus buku piutang (ADHOC-90): tagihan bulan lalu yang dinyatakan tak
     * bisa ditagih. Statusnya jadi `tak_tertagih` (kolom `invoice_status`
     * string, bukan DB enum), dan jejaknya disimpan di sini.
     *
     * `written_off_amount` = SNAPSHOT `remaining_amount` saat dihapus buku.
     * `remaining_amount` sendiri sengaja tidak disentuh — kalau hapus buku
     * dibatalkan, sisa tagihan yang asli masih utuh. Laporan Bulanan Admin
     * Collector menjumlah kolom ini per `written_off_at`, bukan `remaining_amount`.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('written_off_at')->nullable()->after('invoice_status');
            $table->foreignId('written_off_by')->nullable()->after('written_off_at')
                ->constrained('users')->nullOnDelete();
            $table->decimal('written_off_amount', 15, 2)->nullable()->after('written_off_by');
            $table->string('write_off_reason', 500)->nullable()->after('written_off_amount');

            $table->index('written_off_at', 'invoices_written_off_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_written_off_at_idx');
            $table->dropConstrainedForeignId('written_off_by');
            $table->dropColumn(['written_off_at', 'written_off_amount', 'write_off_reason']);
        });
    }
};
