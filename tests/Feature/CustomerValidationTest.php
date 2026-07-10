<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\City;
use App\Models\District;
use App\Models\Village;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Services\CustomerValidationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerValidationTest extends TestCase
{
    use RefreshDatabase;

    private Pop $defaultPop;
    private City $city;
    private District $district;
    private Village $village;
    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->defaultPop = Pop::create([
            'code' => 'DFT',
            'pop_code' => 'DFT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Default POP',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->city = City::firstOrFail();
        $this->district = District::where('city_id', $this->city->id)->firstOrFail();
        $this->village = Village::where('district_id', $this->district->id)->firstOrFail();
        $this->package = InternetPackage::firstOrFail();
    }

    /**
     * Test a customer with minimal data starts as 'draft'.
     */
    public function test_new_customer_with_minimal_data_is_draft(): void
    {
        $customer = Customer::create([
            'customer_code' => 'C-DFT-000001',
            'full_name' => 'John Doe Incomplete',
            'primary_phone' => '08123456789',
            'phone' => '08123456789',
            'registration_date' => '2026-06-11',
            'pop_id' => $this->defaultPop->id,
            'status' => 'registered',
        ]);

        $this->assertEquals('draft', $customer->data_completeness_status);
        $this->assertLessThan(50, $customer->dataCompleteness()['percentage']);
    }

    /**
     * Test a customer with partial required data is marked as 'perlu_dilengkapi'.
     */
    public function test_customer_with_partial_data_is_perlu_dilengkapi(): void
    {
        // Add some required fields: name, phone, pop, package, address, status.
        // Still missing village_id, district_id, city_id and customerService.
        $customer = Customer::create([
            'customer_code' => 'C-DFT-000002',
            'full_name' => 'John Doe Partial',
            'primary_phone' => '08123456789',
            'phone' => '08123456789',
            'registration_date' => '2026-06-11',
            'pop_id' => $this->defaultPop->id,
            'status' => 'registered',
            'address' => 'Jl. Merdeka No. 10',
            'internet_package_id' => $this->package->id,
        ]);

        // More than half of required fields are filled (e.g. 6 out of 12)
        // so it should move from 'draft' to 'perlu_dilengkapi'.
        $this->assertEquals('perlu_dilengkapi', $customer->data_completeness_status);
    }

    /**
     * Test when all required fields are filled, it becomes 'lengkap'.
     */
    public function test_customer_with_all_required_data_is_lengkap(): void
    {
        $customer = Customer::create([
            'customer_code' => 'C-DFT-000003',
            'full_name' => 'John Doe Complete',
            'primary_phone' => '08123456789',
            'phone' => '08123456789',
            'registration_date' => '2026-06-11',
            'pop_id' => $this->defaultPop->id,
            'status' => 'registered',
            'address' => 'Jl. Merdeka No. 10',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'village_id' => $this->village->id,
            'internet_package_id' => $this->package->id,
        ]);

        // Create Address
        $customer->customerAddress()->create([
            'full_address' => $customer->address,
            'city_id' => $customer->city_id,
            'district_id' => $customer->district_id,
            'village_id' => $customer->village_id,
        ]);

        // Create Service
        $customer->customerService()->create([
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $this->package->monthly_price,
            'discount' => 0,
            'ppn' => 11,
            'total_monthly_bill' => $this->package->monthly_price * 1.11,
            'activation_date' => $customer->registration_date,
            'due_date' => \Carbon\Carbon::parse($customer->registration_date)->addMonth(),
            'service_status' => 'calon_pelanggan',
            'billing_status' => 'pending',
        ]);

        // Fetch fresh copy from DB
        $customer = $customer->fresh();

        $this->assertEquals('lengkap', $customer->data_completeness_status);
        $this->assertTrue($customer->dataCompleteness()['is_ready_billing']);
    }

    /**
     * Test incomplete customer cannot be set to 'siap_billing'.
     */
    public function test_incomplete_customer_cannot_be_set_to_siap_billing(): void
    {
        $customer = Customer::create([
            'customer_code' => 'C-DFT-000004',
            'full_name' => 'John Doe Incomplete 2',
            'primary_phone' => '08123456789',
            'phone' => '08123456789',
            'registration_date' => '2026-06-11',
            'pop_id' => $this->defaultPop->id,
            'status' => 'registered',
            'data_completeness_status' => 'siap_billing',
        ]);

        $customer = $customer->fresh();

        // The save event should automatically override 'siap_billing' to 'draft' or 'perlu_dilengkapi'
        $this->assertNotEquals('siap_billing', $customer->data_completeness_status);
        $this->assertEquals('draft', $customer->data_completeness_status);
    }

    /**
     * Test complete customer can be set to 'siap_billing'.
     */
    public function test_complete_customer_can_be_set_to_siap_billing(): void
    {
        $customer = Customer::create([
            'customer_code' => 'C-DFT-000005',
            'full_name' => 'John Doe Complete 2',
            'primary_phone' => '08123456789',
            'phone' => '08123456789',
            'registration_date' => '2026-06-11',
            'pop_id' => $this->defaultPop->id,
            'status' => 'registered',
            'address' => 'Jl. Merdeka No. 10',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'village_id' => $this->village->id,
            'internet_package_id' => $this->package->id,
        ]);

        $customer->customerAddress()->create([
            'full_address' => $customer->address,
            'city_id' => $customer->city_id,
            'district_id' => $customer->district_id,
            'village_id' => $customer->village_id,
        ]);

        $customer->customerService()->create([
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $this->package->monthly_price,
            'discount' => 0,
            'ppn' => 11,
            'total_monthly_bill' => $this->package->monthly_price * 1.11,
            'activation_date' => $customer->registration_date,
            'due_date' => \Carbon\Carbon::parse($customer->registration_date)->addMonth(),
            'service_status' => 'calon_pelanggan',
            'billing_status' => 'pending',
        ]);

        // Manually update data_completeness_status to 'siap_billing'
        $customer->data_completeness_status = 'siap_billing';
        $customer->save();

        $customer = $customer->fresh();
        $this->assertEquals('siap_billing', $customer->data_completeness_status);
    }
}
