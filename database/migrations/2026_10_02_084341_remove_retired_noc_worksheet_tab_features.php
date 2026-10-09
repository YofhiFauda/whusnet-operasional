<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dua tab lama Worksheet NOC (noc_worksheet.masuk / noc_worksheet.diproses)
     * sudah dilebur jadi satu halaman (ADHOC-06, 2026-07-29) dan sempat
     * SENGAJA dipertahankan nonaktif di TicketFeatureSeeder supaya role lama
     * yang masih kecentang gak error. Permintaan eksplisit user (2026-10-02):
     * dibersihkan total, bukan cuma didiamkan nonaktif.
     *
     * Hapus di level `features` — `permissions.feature_id` dan
     * `role_permissions.permission_id` sama-sama `cascadeOnDelete()`, jadi
     * baris permission & pivot role yang masih nempel ikut tersapu otomatis.
     * Definisi di TicketFeatureSeeder/RolePermissionSeeder/config/rbac.php
     * juga dihapus di commit yang sama biar seeder idempotent gak
     * menghidupkan ulang baris ini.
     */
    public function up(): void
    {
        DB::table('features')->whereIn('code', [
            'noc_worksheet.masuk',
            'noc_worksheet.diproses',
        ])->delete();
    }

    public function down(): void
    {
        // Tidak dikembalikan: fiturnya sudah dihapus permanen atas permintaan user.
    }
};
