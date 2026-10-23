<?php

namespace Tests\Feature;

use App\Enums\CableCrossingStatus;
use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerCableCrossing;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CustomerCableCrossingFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-205 — daftar pemantauan Kabel Melintang Jalan: butuh permission
 * `customer_cable_crossings.view` dan hanya menampilkan rute di POP scope user.
 */
class CustomerCableCrossingIndexTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popDalam;

    private Pop $popLuar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(CustomerCableCrossingFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }

        $this->popDalam = Pop::create(['code' => 'CCI-DALAM', 'pop_code' => 'CID', 'registration_prefix' => 'C', 'cid_prefix' => 'C', 'name' => 'POP Dalam Scope', 'type' => 'cabang', 'status' => 'active']);
        $this->popLuar = Pop::create(['code' => 'CCI-LUAR', 'pop_code' => 'CIL', 'registration_prefix' => 'C', 'cid_prefix' => 'C', 'name' => 'POP Luar Scope', 'type' => 'cabang', 'status' => 'active']);
    }

    private function makeUser(string $roleCode, string $scopeType = ScopeType::ALL_POP->value, ?Pop $allowedPop = null): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scopeType,
        ]);

        if ($allowedPop !== null) {
            $scope->targets()->create(['pop_id' => $allowedPop->id]);
        }

        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    private function makeCrossing(Pop $pop, string $namaJalan): CustomerCableCrossing
    {
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Pelanggan '.$namaJalan]);

        return CustomerCableCrossing::factory()->create([
            'customer_id' => $customer->id,
            'nama_jalan' => $namaJalan,
            'status' => CableCrossingStatus::TERPASANG,
        ]);
    }

    #[Test]
    public function pengguna_tanpa_permission_tidak_bisa_membuka_daftar_kabel_melintang(): void
    {
        $teknisi = $this->makeUser('teknisi');

        $this->actingAs($teknisi)
            ->get(route('customer-cable-crossings.index'))
            ->assertForbidden();
    }

    #[Test]
    public function menu_kabel_melintang_tampil_di_sidebar_pelanggan_untuk_yang_punya_izin(): void
    {
        $this->actingAs($this->makeUser('owner'))
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee(route('customer-cable-crossings.index'), false)
            ->assertSee('Kabel Melintang Jalan');

        $this->actingAs($this->makeUser('teknisi'))
            ->get(route('customers.index'))
            ->assertDontSee(route('customer-cable-crossings.index'), false);
    }

    #[Test]
    public function owner_melihat_rute_terbuka_dari_semua_pop(): void
    {
        $this->makeCrossing($this->popDalam, 'Jl. Dalam Owner');
        $this->makeCrossing($this->popLuar, 'Jl. Luar Owner');

        $this->actingAs($this->makeUser('owner'))
            ->get(route('customer-cable-crossings.index'))
            ->assertOk()
            ->assertSee('Jl. Dalam Owner')
            ->assertSee('Jl. Luar Owner');
    }

    #[Test]
    public function admin_hanya_melihat_rute_di_pop_scope_nya(): void
    {
        $this->makeCrossing($this->popDalam, 'Jl. Dalam Scope');
        $this->makeCrossing($this->popLuar, 'Jl. Luar Scope');

        $this->actingAs($this->makeUser('admin', ScopeType::SELECTED_POP->value, $this->popDalam))
            ->get(route('customer-cable-crossings.index'))
            ->assertOk()
            ->assertSee('Jl. Dalam Scope')
            ->assertDontSee('Jl. Luar Scope');
    }

    #[Test]
    public function detail_pelanggan_menampilkan_badge_rute_yang_belum_selesai(): void
    {
        $crossing = $this->makeCrossing($this->popDalam, 'Jl. Badge Detail');
        CustomerCableCrossing::factory()->ditarik()->create(['customer_id' => $crossing->customer_id]);

        $this->actingAs($this->makeUser('owner'))
            ->get(route('customers.show', $crossing->customer))
            ->assertOk()
            ->assertSee('Kabel melintang: 1', false);
    }

    #[Test]
    public function tab_pemasangan_menampilkan_rute_lengkap_dengan_titik_dan_status(): void
    {
        $crossing = $this->makeCrossing($this->popDalam, 'Jl. Tab Pemasangan');
        $crossing->points()->create(['urutan' => 1, 'label' => 'Tiang TR-12', 'latitude' => -7.8711000, 'longitude' => 111.4620000]);
        $crossing->points()->create(['urutan' => 2, 'label' => 'Rumah pelanggan', 'latitude' => -7.8730000, 'longitude' => 111.4640000]);
        CustomerCableCrossing::factory()->tidakBisaDitarik()->create([
            'customer_id' => $crossing->customer_id,
            'nama_jalan' => 'Jl. Tidak Bisa Ditarik',
        ]);

        $this->actingAs($this->makeUser('owner'))
            ->get(route('customers.show', $crossing->customer))
            ->assertOk()
            ->assertSee('Kabel Melintang Jalan')
            ->assertSee('Jl. Tab Pemasangan')
            ->assertSee('Tiang TR-12')
            ->assertSee('-7.8711000')
            ->assertSee('111.4640000')
            ->assertSee('Jl. Tidak Bisa Ditarik')
            ->assertSee('Alasan: Kabel tertanam di aspal, tidak bisa ditarik tanpa galian.');
    }

    #[Test]
    public function filter_default_menyembunyikan_rute_yang_sudah_ditarik(): void
    {
        $this->makeCrossing($this->popDalam, 'Jl. Masih Terpasang');
        CustomerCableCrossing::factory()->ditarik()->create([
            'customer_id' => Customer::factory()->create(['pop_id' => $this->popDalam->id])->id,
            'nama_jalan' => 'Jl. Sudah Ditarik',
        ]);

        $this->actingAs($this->makeUser('owner'))
            ->get(route('customer-cable-crossings.index'))
            ->assertSee('Jl. Masih Terpasang')
            ->assertDontSee('Jl. Sudah Ditarik');

        $this->actingAs($this->makeUser('owner'))
            ->get(route('customer-cable-crossings.index', ['status' => 'ditarik']))
            ->assertSee('Jl. Sudah Ditarik')
            ->assertDontSee('Jl. Masih Terpasang');
    }
}
