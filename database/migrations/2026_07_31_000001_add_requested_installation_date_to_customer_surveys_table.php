<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanggal pemasangan yang diminta pelanggan saat survey.
     *
     * Nullable — mayoritas pelanggan tidak minta tanggal tertentu. Kosong berarti
     * "secepatnya", dan task PSB jatuh ke perilaku SLA normal. Kolom ini jadi
     * SATU-SATUNYA sumber kebenaran; fop_tasks.client_request_date untuk kategori
     * Pemasangan cuma turunannya (lihat FopTaskController::autoSyncAndCalculatePriority).
     */
    public function up(): void
    {
        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->date('requested_installation_date')->nullable()->after('nearest_odp');
        });
    }

    public function down(): void
    {
        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->dropColumn('requested_installation_date');
        });
    }
};
