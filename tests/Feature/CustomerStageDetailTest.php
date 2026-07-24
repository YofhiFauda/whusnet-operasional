<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerStageDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);
    }

    protected function createCustomer(string $status): Customer
    {
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $package = InternetPackage::query()->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'D00C0000'.rand(10, 99),
            'full_name' => 'Test Stage Customer',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => $status,
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Raya Test No. 12',
        ]);

        return $customer;
    }

    public function test_detail_view_for_waiting_survey_stage_shows_only_registration(): void
    {
        $this->loginAsAdmin();
        $customer = $this->createCustomer('waiting_survey');

        $response = $this->get(route('customers.verification.admin', $customer));

        $response->assertStatus(200);
        $response->assertSee('Data Registrasi');
        $response->assertDontSee('id="tab-btn-survey"', false);
        $response->assertDontSee('id="tab-btn-pemasangan"', false);
        $response->assertDontSee('id="tab-btn-pengujian"', false);
        $response->assertDontSee('id="tab-btn-verifikasi"', false);
    }

    public function test_detail_view_for_waiting_acc_stage_shows_registration_and_survey(): void
    {
        $this->loginAsAdmin();
        $customer = $this->createCustomer('waiting_acc');

        $response = $this->get(route('customers.verification.admin', $customer));

        $response->assertStatus(200);
        $response->assertSee('Data Registrasi');
        $response->assertSee('id="tab-btn-survey"', false);
        $response->assertDontSee('id="tab-btn-pemasangan"', false);
        $response->assertDontSee('id="tab-btn-pengujian"', false);
        $response->assertDontSee('id="tab-btn-verifikasi"', false);
    }

    public function test_detail_view_for_installed_stage_shows_all_tabs(): void
    {
        $this->loginAsAdmin();
        $customer = $this->createCustomer('installed');

        $response = $this->get(route('customers.verification.admin', $customer));

        $response->assertStatus(200);
        $response->assertSee('Data Registrasi');
        $response->assertSee('id="tab-btn-survey"', false);
        $response->assertSee('id="tab-btn-pemasangan"', false);
        $response->assertSee('id="tab-btn-pengujian"', false);
        $response->assertSee('id="tab-btn-verifikasi"', false);
    }
}
