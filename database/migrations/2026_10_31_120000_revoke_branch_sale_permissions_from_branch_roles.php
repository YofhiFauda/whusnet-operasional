<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Jual beli SN antar Cabang hanya dijalankan petugas Gudang Pusat (owner +
     * admin_gudang; keputusan user 2026-10-31). RolePermissionSeeder hanya MENAMBAH
     * izin (tidak pernah mencabut), jadi DB yang sudah terlanjur menerimanya dari
     * seeder lama harus dicabut di sini. Hanya tiga role cabang ini yang disentuh;
     * matrix role lain tidak diubah. Bila kelak perlu dibuka lagi, lakukan lewat
     * Role Matrix, bukan dengan membalik migrasi ini.
     */
    public function up(): void
    {
        $permissionIds = Permission::query()->where('code', 'like', 'warehouse_branch_sale%')->pluck('id');

        foreach (['pic_gudang', 'pop_admin'] as $roleCode) {
            Role::query()->where('code', $roleCode)->first()?->permissions()->detach($permissionIds);
        }

        // EffectiveAccessService menyimpan izin per-user selama 1 jam.
        Cache::flush();
    }

    public function down(): void
    {
        // Sengaja kosong: izin yang dicabut tidak dikembalikan otomatis.
    }
};
