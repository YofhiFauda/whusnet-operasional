<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\City;
use App\Models\Customer;
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
 * Skema 3 (2026-09-12) — ID Sales/Agent/Referral naik level dari varchar
 * bebas jadi FK asli (`sales_user_id`/`agent_id`/`referral_customer_id`).
 * Sales yang login TIDAK BOLEH mendaftarkan pelanggan atas nama Sales lain
 * (autofill dipaksa ke dirinya sendiri, mengabaikan submission klien).
 */
class CustomerReferralFkTest extends TestCase
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

    private function makePackage(): InternetPackage
    {
        return InternetPackage::create([
            'package_code' => 'NET138',
            'name' => 'Net138',
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

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $city = City::create(['name' => 'Kota Uji '.uniqid()]);
        $district = District::create(['city_id' => $city->id, 'name' => 'Kecamatan Uji']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Desa Uji']);
        $package = $this->makePackage();
        // Sales itu role restricted (Skema 1) — tanpa ini submit paket
        // apa pun ditolak, padahal fokus test ini bukan restriksi paket.
        RestrictedPackage::create(['package_id' => $package->id]);

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
            'internet_package_id' => $package->id,
            'contract_period_months' => 12,
        ];
    }

    public function test_sales_role_registration_autofills_own_sales_user_id_ignoring_spoofed_value(): void
    {
        $sales = $this->makeSales();
        $otherSales = $this->makeSales();
        $this->actingAs($sales);

        // Coba spoofing — kirim sales_user_id milik Sales lain.
        $payload = $this->basePayload() + ['sales_user_id' => $otherSales->id];

        $response = $this->post(route('customers.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Pelanggan Uji',
            'sales_user_id' => $sales->id,
        ]);
    }

    /**
     * Koreksi user (2026-09-12): logic autofill/dropdown ID Sales gak boleh
     * hardcode role code 'sales' — harus ikut flag `is_package_restricted`
     * (Skema 1), biar role restricted APA PUN (mis. Teknisi begitu nanti
     * diberi akses registrasi) otomatis ikut tanpa ubah kode.
     */
    public function test_non_sales_role_marked_package_restricted_also_autofills_and_appears_in_dropdown(): void
    {
        // Reuse role 'sales' YANG SUDAH ADA permission `customers.create`-nya
        // (dari RolePermissionSeeder) tapi ganti kode/nama-nya — buktinya
        // dropdown/autofill gak lagi terikat literal code 'sales', cuma ikut
        // flag `is_package_restricted` role manapun.
        $role = Role::where('code', 'sales')->firstOrFail();
        $role->update(['code' => 'reseller', 'name' => 'Reseller']);
        $reseller = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);

        $this->actingAs($reseller);

        $payload = $this->basePayload();
        $response = $this->post(route('customers.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Pelanggan Uji',
            'sales_user_id' => $reseller->id,
        ]);

        // Muncul juga di dropdown ID Sales form create (dilihat admin).
        $admin = $this->loginAsAdmin();
        $createPage = $this->get(route('customers.create'));
        $createPage->assertSee($reseller->name);
    }

    /**
     * Pesan penjelas (2026-09-14, permintaan user) — actor DI LUAR role yang
     * ditandai restricted (mis. Admin/Busdev) lihat pesan kenapa ID Sales
     * gak auto-terisi; Sales (role restricted) TIDAK lihat pesan itu (dia
     * dapat autofill langsung, bukan dropdown).
     */
    public function test_non_restricted_actor_sees_explanatory_message_restricted_actor_does_not(): void
    {
        $admin = $this->loginAsAdmin();
        $adminPage = $this->get(route('customers.create'));
        $adminPage->assertSee('Auto-deteksi ID Sales cuma berlaku untuk role');
        $adminPage->assertSee('Sales');

        $sales = $this->makeSales();
        $this->actingAs($sales);
        $salesPage = $this->get(route('customers.create'));
        $salesPage->assertDontSee('Auto-deteksi ID Sales cuma berlaku untuk role');
    }

    public function test_admin_can_choose_sales_user_id_from_dropdown(): void
    {
        $admin = $this->loginAsAdmin();
        $sales = $this->makeSales();

        $payload = $this->basePayload() + ['sales_user_id' => $sales->id];

        $response = $this->post(route('customers.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('customers', ['sales_user_id' => $sales->id]);
    }

    public function test_agent_id_ignored_when_actor_has_no_agents_permission(): void
    {
        $sales = $this->makeSales();
        $this->actingAs($sales);

        $agent = Agent::create(['code' => 'AGT-01', 'name' => 'Agent Uji', 'is_active' => true]);

        $payload = $this->basePayload() + ['agent_id' => $agent->id];

        $response = $this->post(route('customers.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('customers', ['full_name' => 'Pelanggan Uji', 'agent_id' => null]);
    }

    public function test_business_development_can_register_customer_on_behalf_of_agent(): void
    {
        $role = Role::where('code', 'business_development')->firstOrFail();
        $busdev = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        $this->actingAs($busdev);

        $agent = Agent::create(['code' => 'AGT-01', 'name' => 'Agent Uji', 'is_active' => true]);

        $payload = $this->basePayload() + ['agent_id' => $agent->id];

        $response = $this->post(route('customers.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('customers', ['agent_id' => $agent->id]);
    }

    public function test_referral_customer_cannot_reference_itself(): void
    {
        $admin = $this->loginAsAdmin();
        $existing = Customer::factory()->create();

        $payload = $this->basePayload() + ['referral_customer_id' => $existing->id];
        $response = $this->post(route('customers.store'), $payload);
        $response->assertSessionDoesntHaveErrors();
        $newCustomer = Customer::where('full_name', 'Pelanggan Uji')->firstOrFail();
        $this->assertEquals($existing->id, $newCustomer->referral_customer_id);

        // Sekarang coba update pelanggan itu sendiri jadi referral dirinya sendiri.
        $updatePayload = [
            'full_name' => $newCustomer->full_name,
            'primary_phone' => $newCustomer->primary_phone,
            'registration_date' => now()->toDateString(),
            'pop_id' => $newCustomer->pop_id,
            'status' => $newCustomer->status,
            'referral_customer_id' => $newCustomer->id,
        ];
        $response = $this->put(route('customers.update', $newCustomer), $updatePayload);
        $response->assertSessionHasErrors('referral_customer_id');
    }
}
