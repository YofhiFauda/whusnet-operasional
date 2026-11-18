<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status akhir "Dibebaskan": FOP menyetujui alasan modem tidak bisa
     * diambil, kasus penarikan ditutup TANPA modem kembali. Sengaja kolom
     * terpisah dari `device_retrieved_at` — mengisi kolom itu berarti badge
     * "Sudah Diambil" berbohong (alat tidak pernah kembali ke gudang).
     * Dikosongkan lagi saat Langganan Lagi, sama seperti `device_retrieved_at`.
     *
     * docs/plan/warehouse/analisa-review-penarikan-deac.md §3.1 (D5)
     */
    public function up(): void
    {
        Schema::table('customer_devices', function (Blueprint $table) {
            $table->timestamp('retrieval_waived_at')->nullable()->after('device_retrieved_at');
            $table->foreignId('retrieval_waived_by')->nullable()->after('retrieval_waived_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retrieval_waived_by');
            $table->dropColumn('retrieval_waived_at');
        });
    }
};
