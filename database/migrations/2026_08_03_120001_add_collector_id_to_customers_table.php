<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rute permanen: pelanggan ini rutin ditagih kolektor siapa. Nullable,
     * reassignable ("selamanya" = menetap tapi bisa dilepas/dipindah, BUKAN
     * terkunci mati). Tiga guard wajib ditegakkan di layer aplikasi, bukan
     * di migration ini: (1) target wajib ber-role `kolektor`, (2) POP
     * pelanggan wajib masuk scope kolektor, (3) kolektor yang masih
     * memegang pelanggan tak boleh dinonaktifkan — lihat
     * CollectorAssignmentController & UserController::update().
     *
     * docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §B-3, §B-4.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('collector_id')->nullable()->after('pop_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collector_id');
        });
    }
};
