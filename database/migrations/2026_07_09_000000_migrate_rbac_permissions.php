<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Feature;
use App\Models\Role;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use App\Services\PermissionGeneratorService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Run seeders to create new features & actions
        (new FeatureSeeder())->run();
            
            // Run the permission generator from config
            app(PermissionGeneratorService::class)->generate();

            // Run the custom task features seeder
            (new TaskFeatureSeeder())->run();

            // 2. Fetch the newly created permissions
            $taskExecute = Permission::query()->where('code', 'task.execute')->first();
            $taskManage = Permission::query()->where('code', 'task.manage')->first();

            $masterWilayahView = Permission::query()->where('code', 'master_wilayah.view')->first();
            $masterWilayahCreate = Permission::query()->where('code', 'master_wilayah.create')->first();
            $masterWilayahUpdate = Permission::query()->where('code', 'master_wilayah.update')->first();
            $masterWilayahDelete = Permission::query()->where('code', 'master_wilayah.delete')->first();

            $masterDistribusiView = Permission::query()->where('code', 'master_distribusi.view')->first();
            $masterDistribusiCreate = Permission::query()->where('code', 'master_distribusi.create')->first();
            $masterDistribusiUpdate = Permission::query()->where('code', 'master_distribusi.update')->first();
            $masterDistribusiDelete = Permission::query()->where('code', 'master_distribusi.delete')->first();

            $masterStatusPelangganView = Permission::query()->where('code', 'master_status_pelanggan.view')->first();
            $masterStatusPelangganCreate = Permission::query()->where('code', 'master_status_pelanggan.create')->first();
            $masterStatusPelangganUpdate = Permission::query()->where('code', 'master_status_pelanggan.update')->first();
            $masterStatusPelangganDelete = Permission::query()->where('code', 'master_status_pelanggan.delete')->first();

            // Old permissions we want to map from
            $oldExecCodes = ['task.status.start', 'task.status.complete', 'task.evidence.upload', 'task.status.pending'];
            $oldManageCodes = ['task.edit', 'task.schedule'];

            // Find all roles
            $roles = Role::all();

            foreach ($roles as $role) {
                $rolePermissionIds = DB::table('role_permissions')
                    ->where('role_id', $role->id)
                    ->pluck('permission_id')
                    ->toArray();

                $permissionCodes = Permission::query()->whereIn('id', $rolePermissionIds, 'and', false)->pluck('code')->toArray();

                // Migrate technician execute permissions
                if ($taskExecute && array_intersect($oldExecCodes, $permissionCodes)) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $role->id,
                        'permission_id' => $taskExecute->id,
                    ]);
                }

                // Migrate FOP manage permissions
                if ($taskManage && array_intersect($oldManageCodes, $permissionCodes)) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $role->id,
                        'permission_id' => $taskManage->id,
                    ]);
                }

                // Migrate master data: wilayah & distribusi based on pops
                if (in_array('pops.view', $permissionCodes)) {
                    if ($masterWilayahView) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterWilayahView->id,
                        ]);
                    }
                    if ($masterDistribusiView) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterDistribusiView->id,
                        ]);
                    }
                }
                if (in_array('pops.create', $permissionCodes)) {
                    if ($masterWilayahCreate) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterWilayahCreate->id,
                        ]);
                    }
                    if ($masterDistribusiCreate) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterDistribusiCreate->id,
                        ]);
                    }
                }
                if (in_array('pops.update', $permissionCodes)) {
                    if ($masterWilayahUpdate) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterWilayahUpdate->id,
                        ]);
                    }
                    if ($masterDistribusiUpdate) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterDistribusiUpdate->id,
                        ]);
                    }
                }
                if (in_array('pops.delete', $permissionCodes)) {
                    if ($masterWilayahDelete) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterWilayahDelete->id,
                        ]);
                    }
                    if ($masterDistribusiDelete) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterDistribusiDelete->id,
                        ]);
                    }
                }

                // Migrate status pelanggan based on packages
                if (in_array('packages.view', $permissionCodes)) {
                    if ($masterStatusPelangganView) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterStatusPelangganView->id,
                        ]);
                    }
                }
                if (in_array('packages.create', $permissionCodes)) {
                    if ($masterStatusPelangganCreate) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterStatusPelangganCreate->id,
                        ]);
                    }
                }
                if (in_array('packages.update', $permissionCodes)) {
                    if ($masterStatusPelangganUpdate) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterStatusPelangganUpdate->id,
                        ]);
                    }
                }
                if (in_array('packages.delete', $permissionCodes)) {
                    if ($masterStatusPelangganDelete) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $masterStatusPelangganDelete->id,
                        ]);
                    }
                }
            }

            // 3. Rename permission names contextually as S1/3.4 recommends.
            // CATATAN (root cause anomali label balik null, lihat
            // docs/post-mvp/rbac/migrasi-mapping-permission.md bagian 9):
            // step ini gak reliable kalau dijalankan lewat migrate:fresh, karena
            // step 1 (PermissionGeneratorService::generate()) di atas jalan
            // SEBELUM ActionSeeder (itu bagian --seed, migrations selesai
            // duluan) — jadi permission fop_tasks/customers.detail.devices
            // belum tentu ke-create di titik ini, update() di bawah bisa kena
            // 0 baris. Fix permanennya udah dipindah ke
            // config/rbac.php > permission_name_overrides +
            // PermissionGeneratorService::generate() (dipasang tiap kali
            // permission itu dibuat/ditemukan, gak peduli timing). Baris di
            // bawah ini dibiarkan sebagai fallback tambahan, tapi BUKAN lagi
            // satu-satunya tempat rename ini diterapkan.
            $renames = [
                'fop_tasks.update_sensitive' => 'Ubah Kategori & Prioritas Tiket',
                'customers.detail.devices.update_sensitive' => 'Ubah Data Sensitif Perangkat',
                'customers.detail.devices.view_sensitive' => 'Lihat Data Sensitif Perangkat',
            ];
            foreach ($renames as $code => $name) {
                Permission::query()->where('code', $code)->update(['name' => $name]);
            }

            // 4. Kode permission lama (task.edit, task.schedule, task.status.*, task.evidence.upload)
            // SENGAJA TIDAK dihapus di sini. Sesuai prosedur di
            // docs/post-mvp/rbac/migrasi-mapping-permission.md bagian 4-5:
            // biarkan kode lama & baru aktif berdampingan (dual-grant) minimal
            // 1 sprint dulu sebelum cleanup, supaya ada window rollback kalau
            // ada role/kode yang belum ketangkep migrasi pivot di step 2.
            // Cleanup-nya ada di migration terpisah:
            // database/migrations/2026_07_16_000000_cleanup_obsolete_rbac_permissions.php
            // — jalankan itu cuma setelah sign-off eksplisit, bukan otomatis.

            // 5. Re-run transition seeder to update transitions permission mappings
            (new WorkflowTransitionPermissionSeeder())->run();

            // NOTE: RolePermissionSeeder sengaja TIDAK dipanggil di sini.
            // RolePermissionSeeder::run() pakai sync() (replace penuh, bukan
            // additive) dan array hardcoded-nya untuk role 'teknisi'/'fop'
            // tidak memuat task.execute/task.manage — kalau dipanggil setelah
            // step 1 (TaskFeatureSeeder), sync() itu akan MENGHAPUS LAGI grant
            // task.execute (teknisi) & task.manage (fop) yang baru saja
            // ditambahkan secara additive. Grant role untuk permission baru
            // sudah tercakup lewat TaskFeatureSeeder (step 1, syncWithoutDetaching)
            // dan migrasi pivot master data (step 2, insertOrIgnore) — keduanya
            // additive dan aman dijalankan di atas data role_permissions existing.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Transition down is no-op or handled by database restores since seeds are modified
    }
};
