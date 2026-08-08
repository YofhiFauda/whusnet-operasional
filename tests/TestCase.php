<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\EffectiveAccessService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Helper to authenticate as an active admin/owner user in tests.
     */
    protected function loginAsAdmin(?User $user = null): User
    {
        if (! $user) {
            $ownerRole = Role::where('name', 'Owner')->first();
            if (! $ownerRole) {
                $this->seed(RoleSeeder::class);
                $ownerRole = Role::where('name', 'Owner')->first();
            }

            $user = User::factory()->create([
                'status' => 'active',
                'role_id' => $ownerRole ? $ownerRole->id : null,
            ]);
        }

        $this->actingAs($user);

        return $user;
    }

    /**
     * User::factory()->create() sendirian TIDAK bikin baris UserRoleScope —
     * beda dari alur nyata (UserController::store/update, yang mewajibkan
     * scope_type). Tanpa scope, EffectiveAccessService::getAllowedPopIds()
     * balikin array kosong yang di-treat sebagai deny-by-default (bukan akses
     * penuh) oleh applyUserScope()/hasAllPopAccess() — lihat CLAUDE.md § POP
     * Scope. Dipakai fixture test yang butuh actor non-owner tetap punya akses
     * penuh lintas POP (setara `all_pop`), BUKAN buat test yang justru
     * memverifikasi pembatasan scope-nya sendiri.
     */
    protected function giveAllPopScope(User $user): void
    {
        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => 'all_pop',
        ]);

        app(EffectiveAccessService::class)->clearCache($user);
    }
}
