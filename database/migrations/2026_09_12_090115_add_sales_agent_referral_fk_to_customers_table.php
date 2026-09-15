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
        // Skema 3 (2026-09-12) — ID Sales/Agent/Referral naik level dari
        // varchar bebas jadi FK asli, supaya bisa dipakai hitung komisi &
        // agregasi omset (Skema 2) dengan akurat. Kolom lama
        // (`sales_code`/`agent_code`/`referral_customer_code`) SENGAJA
        // TIDAK dihapus — data pelanggan lama yang sudah terlanjur diisi
        // manual tetap tampil apa adanya (fallback read-only di halaman
        // Detail), cuma pendaftaran BARU yang wajib lewat FK ini.
        Schema::table('customers', function (Blueprint $table) {
            // Sales yang mendaftarkan — FK ke users (role sales). Autofill
            // dari user login kalau yang input Sales sendiri.
            $table->foreignId('sales_user_id')->nullable()->after('referral_customer_code')
                ->constrained('users')->nullOnDelete();

            // Agent yang mendaftarkan — FK ke master agents. Agent gak
            // pernah login, jadi field ini cuma diisi lewat form yang
            // dibuka Business Development atas nama Agent.
            $table->foreignId('agent_id')->nullable()->after('sales_user_id')
                ->constrained('agents')->nullOnDelete();

            // Pelanggan existing yang jadi sumber referral — self-referential,
            // BUKAN string CID bebas lagi.
            $table->foreignId('referral_customer_id')->nullable()->after('agent_id')
                ->constrained('customers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_customer_id');
            $table->dropConstrainedForeignId('agent_id');
            $table->dropConstrainedForeignId('sales_user_id');
        });
    }
};
