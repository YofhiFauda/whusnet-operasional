<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rekonsiliasi `odps`/`odp_ports` (master ODP flat, satu tipe) ke pohon
     * generik `network_nodes`/`network_node_ports`. Satu arah: file create
     * `odps` sudah dihapus dari repo, jadi instalasi baru tidak pernah punya
     * tabel itu — seluruh `up()` dijaga `Schema::hasTable('odps')` dan diam
     * saja di sana.
     *
     * Setiap ODP lama jadi node PUNCAK (`parent_id = null`) yang ditambatkan
     * langsung ke POP-nya (`anchor_pop_id = odps.pop_id`) — sama persis dengan
     * struktur flat lama, cabang hasil `NetworkNode::resolvePop()` tetap POP
     * yang sama. Timestamp disalin apa adanya: ini data historis, bukan baris
     * baru.
     */
    public function up(): void
    {
        if (! Schema::hasTable('odps')) {
            return;
        }

        // Rename DULUAN, di transaksi sendiri — sengaja TIDAK satu transaksi
        // dengan copy data. Seeder baru (`NetworkNodeTypeSeeder`) mendaftarkan
        // Feature `network_nodes` lewat updateOrCreate. Kalau copy di bawah
        // gagal (tipe `odp` belum di-seed) dan ikut menggulung rename, operator
        // yang menuruti pesan error akan menjalankan seeder SEBELUM migrate
        // diulang → seeder membuat baris `network_nodes` baru yang kosong,
        // rename di percobaan kedua di-skip (unique `code`), dan semua grant
        // role di `network_odps` yatim. Rename duluan = baris yang sama sudah
        // bernama `network_nodes` saat seeder jalan, grant ikut selamat.
        DB::transaction(fn () => $this->renameFeatureAndPermissions());

        // Boleh melempar (tipe `odp` belum ada) — transaksinya bergulung bersih,
        // `odps`/`odp_ports` di bawah belum di-drop, migrate tinggal diulang.
        DB::transaction(fn () => $this->copyOdpsToNetworkNodes());

        // Baru tercapai kalau copy sukses: exception di atas sudah keluar dari
        // up() sebelum sampai sini, jadi data lama tidak pernah di-drop duluan.
        Schema::dropIfExists('odp_ports');
        Schema::dropIfExists('odps');

        // Kode permission berubah — cache permission per user (EffectiveAccessService,
        // key `user.{id}.permissions`) masih berisi `network_odps.*` sampai TTL habis.
        // Tanpa ini, semua user non-wildcard kena 403 di /network/nodes sampai cache
        // kedaluwarsa sendiri.
        DB::table('users')->orderBy('id')->pluck('id')->each(
            fn ($userId) => Cache::forget("user.{$userId}.permissions")
        );
    }

    /**
     * Tidak dikembalikan: reshape satu arah. File create `odps` sudah dihapus,
     * dan node yang sudah dipindah/ditambah lewat pohon baru tidak punya
     * padanan di bentuk flat lama — memaksa balik justru berisiko memotong data.
     */
    public function down(): void {}

    private function copyOdpsToNetworkNodes(): void
    {
        if (! DB::table('odps')->exists()) {
            return;
        }

        // Tipe `odp` dibuat NetworkNodeTypeSeeder, bukan migration. Kalau belum
        // ada, berhenti keras — menebak/membuat tipe di sini berarti flag
        // perilakunya (max_ports, red_zone_threshold) jadi dua sumber kebenaran.
        $odpTypeId = DB::table('network_node_types')->where('code', 'odp')->value('id');

        if ($odpTypeId === null) {
            throw new RuntimeException(
                'Tipe node "odp" belum ada di network_node_types. Jalankan '
                .'`php artisan db:seed --class=NetworkNodeTypeSeeder` dulu, lalu ulangi migrate.'
            );
        }

        DB::table('odps')->orderBy('id')->chunkById(200, function ($odps) use ($odpTypeId): void {
            foreach ($odps as $odp) {
                $nodeId = DB::table('network_nodes')->insertGetId([
                    'parent_id' => null,
                    'anchor_pop_id' => $odp->pop_id,
                    'node_type_id' => $odpTypeId,
                    'code' => $odp->code,
                    'name' => $odp->name,
                    'capacity' => null,
                    'status' => 'active',
                    'created_at' => $odp->created_at,
                    'updated_at' => $odp->updated_at,
                ]);

                $ports = DB::table('odp_ports')
                    ->where('odp_id', $odp->id)
                    ->orderBy('number')
                    ->get()
                    ->map(fn ($port) => [
                        'network_node_id' => $nodeId,
                        'number' => $port->number,
                        'is_used' => $port->is_used,
                        'created_at' => $port->created_at,
                        'updated_at' => $port->updated_at,
                    ])
                    ->all();

                foreach (array_chunk($ports, 200) as $chunk) {
                    DB::table('network_node_ports')->insert($chunk);
                }
            }
        });
    }

    /**
     * Rename Feature `network_odps` → `network_nodes` di baris yang SAMA (id
     * tetap), plus kode permission-nya. `role_permissions` menunjuk
     * `permission_id`, jadi role yang tadinya punya `network_odps.view` otomatis
     * punya `network_nodes.view` — tanpa ini PermissionGeneratorService akan
     * bikin permission baru yang kosong dan semua role kehilangan akses.
     */
    private function renameFeatureAndPermissions(): void
    {
        $oldFeatureId = DB::table('features')->where('code', 'network_odps')->value('id');

        if ($oldFeatureId === null) {
            return;
        }

        $newFeatureId = DB::table('features')->where('code', 'network_nodes')->value('id');

        if ($newFeatureId !== null) {
            // Seeder Task 3 can create the new Feature before legacy migration.
            // Copy grants by action before deleting the old Feature.
            $oldPermissions = DB::table('permissions')->where('feature_id', $oldFeatureId)->get();

            foreach ($oldPermissions as $oldPermission) {
                $newPermissionId = DB::table('permissions')
                    ->where('feature_id', $newFeatureId)
                    ->where('action_id', $oldPermission->action_id)
                    ->value('id');

                if ($newPermissionId === null) {
                    $newPermissionId = DB::table('permissions')->insertGetId([
                        'feature_id' => $newFeatureId,
                        'action_id' => $oldPermission->action_id,
                        'code' => 'network_nodes.'.substr($oldPermission->code, strlen('network_odps.')),
                        'name' => null,
                        'module' => $oldPermission->module,
                        'description' => $oldPermission->description,
                        'created_at' => $oldPermission->created_at,
                        'updated_at' => $oldPermission->updated_at,
                    ]);
                }

                $roleIds = DB::table('role_permissions')
                    ->where('permission_id', $oldPermission->id)
                    ->pluck('role_id');

                foreach ($roleIds as $roleId) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $newPermissionId,
                    ]);
                }
            }

            DB::table('features')->where('id', $oldFeatureId)->delete();

            return;
        }

        DB::table('features')->where('id', $oldFeatureId)->update([
            'code' => 'network_nodes',
            'name' => 'Master Pohon Infrastruktur Jaringan',
        ]);

        DB::table('permissions')
            ->where('feature_id', $oldFeatureId)
            ->where('code', 'like', 'network_odps.%')
            ->get(['id', 'code'])
            ->each(fn ($permission) => DB::table('permissions')
                ->where('id', $permission->id)
                ->update(['code' => 'network_nodes.'.substr($permission->code, strlen('network_odps.'))]));
    }
};
