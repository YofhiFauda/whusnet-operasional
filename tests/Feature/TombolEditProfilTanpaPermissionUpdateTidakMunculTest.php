<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bug 2026-11-16: role yang di Role Matrix cuma boleh "Lihat Detail Pelanggan"
 * (customers.detail.view, tanpa customers.update) tetap melihat tombol
 * "Edit Profil" di Detail Pelanggan — klik langsung 403 karena route
 * customers.edit digerbangi customers.update. Pasangan dari
 * TombolDetailPelangganTanpaPermissionTidakMunculTest.
 */
class TombolEditProfilTanpaPermissionUpdateTidakMunculTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $pop = Pop::create([
            'code' => 'SMN-EDTBTN',
            'pop_code' => 'EDB',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Tombol Edit',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'EDB-'.rand(10000, 99999),
            'full_name' => 'Pelanggan Tombol Edit',
            'primary_phone' => '081234567890',
            'status' => 'active',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    /**
     * Attach langsung (bukan syncPermissions) supaya auto-grant chain tidak
     * menambah permission yang sedang diuji ketiadaannya.
     */
    private function userDengan(string ...$kode): User
    {
        $role = Role::create([
            'code' => 'tombol_edit_test',
            'name' => 'Tombol Edit Test',
            'is_system' => false,
        ]);
        $role->permissions()->attach(Permission::whereIn('code', $kode)->pluck('id'));

        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    #[Test]
    public function detail_pelanggan_tanpa_update_tidak_menampilkan_tombol_edit_profil(): void
    {
        $user = $this->userDengan('customers.detail.view');

        $response = $this->actingAs($user)->get(route('customers.show', $this->customer));

        $response->assertOk();
        $response->assertDontSee('Edit Profil');
        $response->assertDontSee('href="'.route('customers.edit', $this->customer->id).'"', false);
        $this->actingAs($user)->get(route('customers.edit', $this->customer))->assertForbidden();
    }

    #[Test]
    public function detail_pelanggan_dengan_update_tetap_menampilkan_tombol_edit_profil(): void
    {
        $user = $this->userDengan('customers.detail.view', 'customers.update');

        $response = $this->actingAs($user)->get(route('customers.show', $this->customer));

        $response->assertOk();
        $response->assertSee('Edit Profil');
        $response->assertSee('href="'.route('customers.edit', $this->customer->id).'"', false);
    }
}
