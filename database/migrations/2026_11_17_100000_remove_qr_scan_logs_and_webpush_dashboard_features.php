<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Halaman Riwayat Scan QR (`qr_scan_logs`) & Dashboard Delivery Push
     * Notification (`webpush_dashboard`) dihapus atas keputusan user
     * 2026-11-17: absen QR & web push masih tahap uji, angka mentahnya
     * belum akurat dan pembaca halamannya (owner/atasan/admin/FOP) tidak
     * paham fungsinya. Diganti artisan `qr:scan-logs` &
     * `webpush:delivery-stats` + file log per fitur.
     *
     * Hapus di level `features` — `permissions.feature_id` dan
     * `role_permissions.permission_id` sama-sama cascade, jadi permission &
     * grant role ikut tersapu (pola sama
     * `remove_retired_noc_worksheet_tab_features`). Definisi di seeder &
     * config/rbac.php dihapus di commit yang sama supaya seeder idempotent
     * tidak menghidupkan ulang baris ini.
     *
     * Tabel datanya (`qr_scan_logs`, `push_deliveries`) TIDAK disentuh —
     * yang dihapus cuma pintu UI-nya.
     */
    public function up(): void
    {
        DB::table('features')->whereIn('code', [
            'qr_scan_logs',
            'webpush_dashboard',
        ])->delete();
    }

    public function down(): void
    {
        // Tidak dikembalikan: kalau halamannya dibangun ulang nanti, feature
        // & permission-nya ikut dibuat ulang lewat seeder bersama halamannya.
    }
};
