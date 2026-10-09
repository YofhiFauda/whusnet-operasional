<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hapus izin dari skema lama yang tidak punya `code` (manage_users, view_invoices, dst.).
     *
     * Izin ini tidak pernah dicocokkan oleh EffectiveAccessService::getPermissions()
     * (yang membaca `code`), jadi tidak memberi akses apa pun — hanya menumpuk di
     * matriks role dan membuat pengecekan "izin ini dipegang?" bergantung pada
     * nilai null. Kunci ganda (code kosong DAN feature_id kosong) supaya izin RBAC
     * yang sah tidak pernah tersapu.
     *
     * `role_permissions` dibersihkan dulu; satu-satunya tabel yang merujuk `permissions`.
     */
    public function up(): void
    {
        $legacyIds = DB::table('permissions')
            ->where(fn ($q) => $q->whereNull('code')->orWhere('code', ''))
            ->whereNull('feature_id')
            ->pluck('id');

        if ($legacyIds->isEmpty()) {
            return;
        }

        DB::table('role_permissions')->whereIn('permission_id', $legacyIds)->delete();
        DB::table('permissions')->whereIn('id', $legacyIds)->delete();
    }

    /**
     * Tidak bisa dibalik otomatis. Kalau perlu dikembalikan, restore dari dump
     * `permissions` + `role_permissions` yang dibuat sebelum migrasi ini dijalankan.
     */
    public function down(): void
    {
        // No down needed
    }
};
