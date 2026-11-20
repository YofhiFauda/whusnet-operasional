<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan Admin per kasus (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md
 * §21, follow-up 2026-11-19) — Admin memutuskan "Eksekusi Isolir" atau
 * "Toleransi" SEBELUM NOC bisa menjalankan isolir dari kasus ini.
 * `admin_decision` NULL = belum diputuskan (default, SAMA SEPERTI "Toleransi"
 * dari sisi gerbang NOC — NOC cuma boleh isolir yang sudah eksplisit
 * `execute`, lihat `CustomerController::toggleSuspend()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('isolation_cases', function (Blueprint $table) {
            $table->string('admin_decision')->nullable()->after('trigger_type');
            $table->text('admin_decision_note')->nullable()->after('admin_decision');
            $table->date('admin_decision_until')->nullable()->after('admin_decision_note');
            $table->foreignId('admin_decided_by')->nullable()->after('admin_decision_until')->constrained('users')->nullOnDelete();
            $table->timestamp('admin_decided_at')->nullable()->after('admin_decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('isolation_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_decided_by');
            $table->dropColumn(['admin_decision', 'admin_decision_note', 'admin_decision_until', 'admin_decided_at']);
        });
    }
};
