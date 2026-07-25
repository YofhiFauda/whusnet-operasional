<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.2 (ANALISA_INDEX_DATABASE.md §9 F.3) — kolom `notification_type` +
 * index di `notifications`.
 *
 * Filter halaman notifikasi memakai `where('data->type', ...)` di atas kolom
 * `data` bertipe TEXT → MySQL meng-CAST tiap baris ke JSON lalu mengekstrak
 * path = full scan + parsing per baris, dan rapuh (baris JSON tak valid bisa
 * melempar error). Dengan kolom nyata ter-index, filter jadi lookup biasa.
 *
 * MENYIMPANG dari usulan dokumen (generated STORED column): sqlite (dipakai
 * test) TIDAK mengizinkan `ALTER TABLE ADD COLUMN` untuk generated column STORED
 * (hanya VIRTUAL), sedangkan MySQL memerlukan STORED — skema jadi beda antar
 * environment. Kolom NYATA yang diisi via `DatabaseNotification::creating`
 * (lihat AppServiceProvider) + command backfill lebih portabel, indexable
 * normal, dan aman dari drift karena notifikasi immutable setelah dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notification_type', 100)->nullable()->after('type');
            $table->index('notification_type', 'notifications_notification_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notification_type_idx');
            $table->dropColumn('notification_type');
        });
    }
};
