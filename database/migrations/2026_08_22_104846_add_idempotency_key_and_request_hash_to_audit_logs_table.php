<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua kolom baru, dipakai HANYA oleh jalur endpoint API baru
     * (`POST /api/v1/installations/network-assignment`,
     * docs/api/api-pop-distribusi/database-schema.md) — jalur staf
     * (`CustomerNetworkAssignmentController`) tetap NULL di keduanya.
     *
     * Dedup di-scope ke PASANGAN (idempotency_key, request_hash), bukan
     * idempotency_key sendirian: idempotency_key yang sama dipakai ulang
     * kalau assignment Mini POP/Distribusi dan kredensial jaringan
     * (`perangkat`) dikonfirmasi lewat request terpisah (assignment selalu
     * manual, kredensial timing-nya independen — lihat business-logic.md
     * §"Alur nyata"). Cek hanya berdasar key akan salah menganggap request
     * kredensial susulan sebagai retry lalu tidak memprosesnya.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->after('user_agent');
            $table->string('request_hash', 64)->nullable()->after('idempotency_key');

            $table->index(['idempotency_key', 'request_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key', 'request_hash']);
            $table->dropColumn(['idempotency_key', 'request_hash']);
        });
    }
};
