<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\RestrictedPackage;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\BusinessDevelopmentFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skema 1 (2026-09-12) — Restriksi Paket per Role. Sales/Teknisi cuma boleh
 * pilih paket dari `restricted_packages` (diatur Business Development),
 * role lain tetap lihat semua paket aktif. Lihat
 * InternetPackage::scopeAvailableFor().
 */
class PackageRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(BusinessDevelopmentFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function makePackage(string $code, string $name): InternetPackage
    {
        return InternetPackage::create([
            'package_code' => $code,
            'name' => $name,
            'category' => 'Home Broadband',
            'package_group' => 'Net',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
            'is_active' => true,
        ]);
    }

    private function makeSales(): User
    {
        $role = Role::where('code', 'sales')->firstOrFail();

        return User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
    }

    public function test_restricted_role_only_sees_restricted_packages_in_customer_create_dropdown(): void
    {
        $allowed = $this->makePackage('NET138', 'Net138');
        $blocked = $this->makePackage('NET500', 'Net500 Premium');
        RestrictedPackage::create(['package_id' => $allowed->id]);

        $sales = $this->makeSales();
        $this->actingAs($sales);

        $response = $this->get(route('customers.create'));

        $response->assertOk();
        $response->assertSee('Net138');
        $response->assertDontSee('Net500 Premium');
    }

    public function test_non_restricted_role_sees_all_active_packages(): void
    {
        $this->makePackage('NET138', 'Net138');
        $this->makePackage('NET500', 'Net500 Premium');

        $this->loginAsAdmin();

        $response = $this->get(route('customers.create'));

        $response->assertOk();
        $response->assertSee('Net138');
        $response->assertSee('Net500 Premium');
    }

    public function test_submitting_out_of_restriction_package_is_rejected_for_restricted_role(): void
    {
        $allowed = $this->makePackage('NET138', 'Net138');
        $blocked = $this->makePackage('NET500', 'Net500 Premium');
        RestrictedPackage::create(['package_id' => $allowed->id]);

        $sales = $this->makeSales();
        $this->actingAs($sales);

        $payload = $this->validRegistrationPayload($blocked->id);

        $response = $this->post(route('customers.store'), $payload);

        $response->assertSessionHasErrors('internet_package_id');
        $this->assertDatabaseMissing('customers', ['internet_package_id' => $blocked->id]);
    }

    public function test_submitting_allowed_package_succeeds_for_restricted_role(): void
    {
        $allowed = $this->makePackage('NET138', 'Net138');
        RestrictedPackage::create(['package_id' => $allowed->id]);

        $sales = $this->makeSales();
        $this->actingAs($sales);

        $payload = $this->validRegistrationPayload($allowed->id);

        $response = $this->post(route('customers.store'), $payload);

        $response->assertSessionDoesntHaveErrors('internet_package_id');
        $this->assertDatabaseHas('customers', ['internet_package_id' => $allowed->id]);
    }

    public function test_business_development_can_update_restricted_packages_list(): void
    {
        $role = Role::where('code', 'business_development')->firstOrFail();
        $busdev = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        $package = $this->makePackage('NET138', 'Net138');

        $this->actingAs($busdev);

        $response = $this->put(route('business-development.package-restrictions.update'), [
            'package_ids' => [$package->id],
        ]);

        $response->assertRedirect(route('business-development.package-restrictions.index'));
        $this->assertDatabaseHas('restricted_packages', ['package_id' => $package->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(int $packageId): array
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $city = City::create(['name' => 'Kota Uji '.uniqid()]);
        $district = District::create(['city_id' => $city->id, 'name' => 'Kecamatan Uji']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Desa Uji']);

        return [
            'full_name' => 'Pelanggan Uji',
            'identity_number' => '1234567890123456',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => now()->toDateString(),
            'pop_id' => $pop->id,
            'address' => 'Jl. Uji No. 1',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $packageId,
            'contract_period_months' => 12,
        ];
    }
}
