<?php

namespace Database\Seeders;

use App\Enums\ScopeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\WarehousePopPic;
use App\Services\EffectiveAccessService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo PIC Gudang per cabang (ADHOC-120,
 * docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md). Role GLOBAL
 * `pic_gudang` dipakai apa adanya — "Jetis"/"Sandya" di nama variabel cuma
 * label pengelompokan seeder, BUKAN role per cabang (dilarang CLAUDE.md RBAC).
 *
 * Tiap user di sini diberi DUA hal terpisah (§5.3):
 *   - Scope POP (`user_role_scopes`)   = data pelanggan/task yang boleh dia lihat.
 *   - Penunjukan PIC (`warehouse_pop_pics`) = gudang cabang yang dia urus.
 * Kebetulan sama-sama cabang masing-masing di sini (kasus dasar skenario 1,
 * §5.3.4) — bukan kasus teknisi keliling scope `all_pop` (skenario 2).
 */
class PicGudangSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('code', 'pic_gudang')->first();

        if (! $role) {
            $this->command->error('Role pic_gudang tidak ditemukan. Pastikan RoleSeeder sudah dijalankan.');

            return;
        }

        $branches = [
            'jetis' => 'Jetis',
            'sandya' => 'Sandya',
        ];

        foreach ($branches as $slug => $popName) {
            $pop = Pop::where('name', $popName)->where('type', 'cabang')->first();

            if (! $pop) {
                $this->command->error("POP Cabang '{$popName}' tidak ditemukan — lewati pic_gudang_{$slug}.");

                continue;
            }

            for ($i = 1; $i <= 2; $i++) {
                $user = User::updateOrCreate(
                    ['email' => "pic.gudang.{$slug}{$i}@whusnet.net"],
                    [
                        'name' => "PIC Gudang {$popName} {$i}",
                        'phone' => '0812'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                        'password' => Hash::make('password'),
                        'status' => 'active',
                        'role_id' => $role->id,
                        'email_verified_at' => now(),
                    ]
                );

                // Scope — selected_pop ke cabangnya sendiri (kasus dasar).
                $scope = UserRoleScope::updateOrCreate(
                    ['user_id' => $user->id, 'role_id' => $role->id],
                    ['scope_type' => ScopeType::SELECTED_POP->value]
                );

                UserRoleScopeTarget::firstOrCreate([
                    'user_role_scope_id' => $scope->id,
                    'pop_id' => $pop->id,
                ]);

                // Penunjukan PIC — TERPISAH dari scope di atas (§5.3). 1 cabang
                // boleh py >1 PIC (keputusan user 2026-09-30) — makanya 2 user
                // di sini bisa dua-duanya ditunjuk PIC cabang yang sama.
                WarehousePopPic::firstOrCreate([
                    'pop_id' => $pop->id,
                    'user_id' => $user->id,
                ]);

                app(EffectiveAccessService::class)->clearCache($user);
            }
        }

        $this->command->info('✅ 4 PIC Gudang berhasil dibuat (2 Jetis, 2 Sandya)!');
    }
}
