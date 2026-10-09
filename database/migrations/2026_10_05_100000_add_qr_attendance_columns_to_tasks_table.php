<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §6.3 — Fase 3 (absen teknisi).
 *
 * Jejak absen disimpan di task itu sendiri supaya laporan "mulai via QR vs
 * manual" bisa dibaca tanpa join ke qr_scan_logs. `started_via` NULL = mulai
 * manual (jalur lama, tidak diubah); 'qr_scan' = lewat absen QR.
 *
 * Koordinat & jarak dicatat apa adanya walau lolos, supaya audit radius
 * (150–500 m → perlu_review) bisa ditelusuri setelah kejadian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('started_via', 20)->nullable()->after('started_at');
            $table->decimal('started_latitude', 10, 7)->nullable()->after('started_via');
            $table->decimal('started_longitude', 10, 7)->nullable()->after('started_latitude');
            $table->unsignedInteger('started_accuracy_meters')->nullable()->after('started_longitude');
            $table->unsignedInteger('started_distance_meters')->nullable()->after('started_accuracy_meters');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'started_via',
                'started_latitude',
                'started_longitude',
                'started_accuracy_meters',
                'started_distance_meters',
            ]);
        });
    }
};
