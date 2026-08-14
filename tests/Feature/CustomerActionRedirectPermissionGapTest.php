<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `customers.create` (boleh registrasi) dan `customers.detail.view` (boleh
 * buka Detail Pelanggan) adalah permission INDEPENDEN — RBAC dinamis lewat
 * Role Matrix bisa kasih salah satu tanpa yang lain (mis. Sales yang cuma
 * boleh input pelanggan, gak boleh buka Detail). Sebelumnya
 * `CustomerController::store()` SELALU redirect ke `customers.show` yang
 * digerbangi `customers.detail.view` — actor kombinasi ini submit BERHASIL
 * (data tersimpan) tapi langsung ke-403 dari redirect, dead end yang
 * membingungkan. Fix: `RedirectsToCustomer` trait cek permission dulu,
 * fallback ke route yang eksplisit disediakan pemanggil.
 */
class CustomerActionRedirectPermissionGapTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private InternetPackage $package;

    private City $city;

    private District $district;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->city = City::create(['name' => 'Ponorogo']);
        $this->district = District::create(['city_id' => $this->city->id, 'name' => 'Babadan']);
        $this->village = Village::create(['district_id' => $this->district->id, 'name' => 'Babadan']);
        $this->pop = Pop::create([
            'name' => 'POP Babadan',
            'type' => 'cabang',
            'code' => 'BBD-GAP',
            'cid_prefix' => 'BBD-GAP',
            'registration_prefix' => 'REG-BBD-GAP',
        ]);
        $this->package = InternetPackage::create([
            'name' => 'Paket 1',
            'package_code' => 'PKT1-GAP',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'monthly_price' => 150000,
        ]);
    }

    /**
     * Role kustom: cuma boleh registrasi pelanggan, TIDAK boleh buka Detail
     * Pelanggan — kombinasi yang eksplisit disebut user sebagai kasus nyata
     * (Sales input-only).
     */
    private function makeCreateOnlyUser(): User
    {
        $role = Role::create(['name' => 'Sales Input Only', 'code' => 'sales-input-only-'.uniqid(), 'is_system' => false]);
        $role->permissions()->attach(Permission::where('code', 'customers.create')->firstOrFail());

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    public function test_registrasi_oleh_role_tanpa_detail_view_redirect_ke_form_bukan_403(): void
    {
        Storage::fake('public');

        $user = $this->makeCreateOnlyUser();
        $this->assertFalse($user->hasPermission('customers.detail.view'));
        $this->assertTrue($user->hasPermission('customers.create'));

        $response = $this->actingAs($user)->post('/customers', [
            'full_name' => 'Pelanggan Gap Test',
            'identity_number' => '1234567890123456',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => now()->format('Y-m-d'),
            'pop_id' => $this->pop->id,
            'address' => 'Jl. Test No. 1',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'village_id' => $this->village->id,
            'internet_package_id' => $this->package->id,
            'contract_period_months' => 12,
            'status' => 'registered',
        ]);

        // Data harus tetap tersimpan — bug lama bukan soal gagal simpan,
        // tapi soal redirect abis submit sukses.
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Pelanggan Gap Test',
            'status' => 'waiting_survey',
        ]);

        // Redirect ke form registrasi (bukan customers.show yang bakal 403,
        // dan bukan 403 langsung).
        $response->assertRedirect(route('customers.create'));
        $response->assertSessionHas('success');

        // Follow redirect harus 200, bukan 403 — inilah bug aslinya.
        $follow = $this->actingAs($user)->get(route('customers.create'));
        $follow->assertOk();
    }

    public function test_registrasi_oleh_role_dengan_detail_view_tetap_ke_customers_show(): void
    {
        Storage::fake('public');

        $role = Role::create(['name' => 'Sales Full', 'code' => 'sales-full-'.uniqid(), 'is_system' => false]);
        $role->permissions()->attach(Permission::whereIn('code', ['customers.create', 'customers.detail.view'])->get());
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $response = $this->actingAs($user)->post('/customers', [
            'full_name' => 'Pelanggan Gap Test 2',
            'identity_number' => '1234567890123457',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567891',
            'registration_date' => now()->format('Y-m-d'),
            'pop_id' => $this->pop->id,
            'address' => 'Jl. Test No. 2',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'village_id' => $this->village->id,
            'internet_package_id' => $this->package->id,
            'contract_period_months' => 12,
            'status' => 'registered',
        ]);

        $customer = Customer::where('full_name', 'Pelanggan Gap Test 2')->firstOrFail();

        // Perilaku existing (Bug #4, docs/PRG_REDIRECT_CONVENTION.md) TIDAK
        // boleh berubah buat actor yang beneran punya akses.
        $response->assertRedirect(route('customers.show', $customer->id));
    }
}
