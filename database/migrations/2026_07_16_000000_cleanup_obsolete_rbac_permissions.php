<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;

/**
 * Cleanup tahap 2 dari migrasi RBAC S5 (lihat 2026_07_09_000000_migrate_rbac_permissions.php
 * dan docs/post-mvp/rbac/migrasi-mapping-permission.md bagian 4-5).
 *
 * JANGAN jalankan migration ini sebelum:
 * 1. Migration 2026_07_09_000000 sudah jalan di semua environment (dev/staging/prod)
 *    minimal 1 sprint.
 * 2. role_permissions dicek — tidak ada lagi role yang bergantung ke kode lama
 *    di bawah ini (semua role sudah punya task.execute / task.manage sebagai
 *    penggantinya).
 * 3. Audit log tidak menunjukkan akses lewat kode lama selama masa transisi.
 * 4. Sign-off eksplisit dari yang bertanggung jawab (bukan auto-deploy).
 *
 * Setelah migration ini jalan, kode lama tidak bisa dipakai lagi — titik tanpa
 * jalan balik (down() sengaja no-op, restore lewat backup DB kalau perlu rollback).
 */
return new class extends Migration
{
    public function up(): void
    {
        $obsoleteCodes = [
            'task.status.start',
            'task.status.complete',
            'task.evidence.upload',
            'task.status.pending',
            'task.edit',
            'task.schedule',
        ];

        Permission::query()->whereIn('code', $obsoleteCodes, 'and', false)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op — kode lama sengaja tidak direstore otomatis, sesuai catatan
        // rollback di docs/post-mvp/rbac/migrasi-mapping-permission.md bagian 5.
        // Restore lewat backup DB kalau benar-benar perlu.
    }
};
