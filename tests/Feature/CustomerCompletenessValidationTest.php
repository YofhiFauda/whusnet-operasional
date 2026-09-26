<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerService;
use App\Models\CustomerTechnicalDetail;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Village;
use App\Services\CustomerValidationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCompletenessValidationTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $this->service = app(CustomerValidationService::class);
    }

    public function test_referral_fields_are_not_evaluated_in_completeness_parameters(): void
    {
        $this->assertArrayNotHasKey('sales_code', CustomerValidationService::REQUIRED_FIELDS);
        $this->assertArrayNotHasKey('sales_code', CustomerValidationService::OPTIONAL_FIELDS);
        $this->assertArrayNotHasKey('agent_code', CustomerValidationService::REQUIRED_FIELDS);
        $this->assertArrayNotHasKey('agent_code', CustomerValidationService::OPTIONAL_FIELDS);
        $this->assertArrayNotHasKey('referral_customer_code', CustomerValidationService::REQUIRED_FIELDS);
        $this->assertArrayNotHasKey('referral_customer_code', CustomerValidationService::OPTIONAL_FIELDS);
    }

    public function test_technical_fields_resolved_from_customer_devices_and_technical_details(): void
    {
        $pop = Pop::first() ?? Pop::create([
            'code' => 'TEST',
            'pop_code' => 'TEST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::first() ?? InternetPackage::create([
            'name' => 'Paket 10M',
            'monthly_price' => 150000,
            'download_speed' => 10,
            'upload_speed' => 10,
            'category' => 'Home',
        ]);

        $city = City::firstOrCreate(['name' => 'KABUPATEN PONOROGO']);
        $district = District::firstOrCreate(['city_id' => $city->id, 'name' => 'BABADAN']);
        $village = Village::firstOrCreate(['district_id' => $district->id, 'name' => 'BABADAN']);

        // Buat customer dengan field wajib lengkap tetapi kolom legacy ont_sn, odp_code, olt_code, vlan_id di customers bernilai null
        $customer = Customer::create([
            'customer_code' => 'C-TEST-0001',
            'full_name' => 'Pelanggan Uji Teknis',
            'primary_phone' => '081234567890',
            'address' => 'Jl. Mawar No 123',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'registration_date' => '2026-09-01',
            'status' => 'active',
            'identity_number' => '3502010101900001',
            'gender' => 'Laki-laki',
            'email' => 'pelanggan@example.com',
            'latitude' => '-7.87123',
            'longitude' => '111.46123',
            'ont_sn' => null,
            'odp_code' => null,
            'olt_code' => null,
            'vlan_id' => null,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 150000,
            'activation_date' => '2026-09-01',
            'due_date' => '2026-10-01',
            'total_monthly_bill' => 150000,
            'service_status' => 'aktif',
        ]);

        // Sebelum ada CustomerDevice / CustomerTechnicalDetail, field teknis missing
        $resultBefore = $this->service->validate($customer->fresh());
        $this->assertArrayHasKey('ont_sn', $resultBefore['missing_optional']);
        $this->assertArrayHasKey('odp_code', $resultBefore['missing_optional']);
        $this->assertArrayHasKey('olt_code', $resultBefore['missing_optional']);
        $this->assertArrayHasKey('vlan_id', $resultBefore['missing_optional']);

        // Sekarang simpan data teknis ke CustomerDevice dan CustomerTechnicalDetail
        CustomerDevice::create([
            'customer_id' => $customer->id,
            'device_type' => 'ont',
            'serial_number' => 'ZTEGC1234567',
            'odp' => 'ODP-BBD-01',
            'vlan_id' => '200',
        ]);

        CustomerTechnicalDetail::create([
            'customer_id' => $customer->id,
            'olt_number' => 'OLT-BBD-01',
            'odp_number' => 'ODP-BBD-01',
            'vlan' => '200',
        ]);

        // Validasi ulang: pastikan ont_sn, odp_code, olt_code, dan vlan_id terdeteksi dan tidak missing
        $resultAfter = $this->service->validate($customer->fresh());
        $this->assertArrayNotHasKey('ont_sn', $resultAfter['missing_optional']);
        $this->assertArrayNotHasKey('odp_code', $resultAfter['missing_optional']);
        $this->assertArrayNotHasKey('olt_code', $resultAfter['missing_optional']);
        $this->assertArrayNotHasKey('vlan_id', $resultAfter['missing_optional']);

        $this->assertEmpty($resultAfter['missing_required']);
        $this->assertEmpty($resultAfter['missing_optional']);
        $this->assertSame(100, $resultAfter['percentage']);
        $this->assertSame('lengkap', $resultAfter['completeness_status']);
    }

    public function test_customer_device_and_technical_detail_triggers_completeness_recalculation(): void
    {
        $pop = Pop::firstOrCreate(['pop_code' => 'TEST2'], [
            'code' => 'TEST2',
            'pop_code' => 'TEST2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $package = InternetPackage::first() ?? InternetPackage::create([
            'name' => 'Paket 10M',
            'monthly_price' => 150000,
            'download_speed' => 10,
            'upload_speed' => 10,
            'category' => 'Home',
        ]);
        $city = City::firstOrCreate(['name' => 'KABUPATEN PONOROGO']);
        $district = District::firstOrCreate(['city_id' => $city->id, 'name' => 'BABADAN']);
        $village = Village::firstOrCreate(['district_id' => $district->id, 'name' => 'BABADAN']);

        $customer = Customer::create([
            'customer_code' => 'C-TEST-0002',
            'full_name' => 'Pelanggan Auto Recalc',
            'primary_phone' => '081234567891',
            'address' => 'Jl. Melati',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'registration_date' => '2026-09-01',
            'status' => 'active',
            'identity_number' => '3502010101900002',
            'gender' => 'Perempuan',
            'email' => 'auto@example.com',
            'latitude' => '-7.87123',
            'longitude' => '111.46123',
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 150000,
            'activation_date' => '2026-09-01',
            'due_date' => '2026-10-01',
            'total_monthly_bill' => 150000,
            'service_status' => 'aktif',
        ]);

        $device = CustomerDevice::create([
            'customer_id' => $customer->id,
            'device_type' => 'ont',
            'serial_number' => 'ZTEGC9999999',
            'odp' => 'ODP-TST-01',
            'vlan_id' => '100',
        ]);

        $detail = CustomerTechnicalDetail::create([
            'customer_id' => $customer->id,
            'olt_number' => 'OLT-TST-01',
        ]);

        $customer->refresh();
        $this->assertSame('lengkap', $customer->data_completeness_status);
    }
}
