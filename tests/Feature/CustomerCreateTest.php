<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\City;
use App\Models\District;
use App\Models\Village;
use App\Models\InternetPackage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_create_view_loads_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get('/customers/create');

        $response->assertStatus(200);
        $response->assertSee('IDENTITAS PELANGGAN');
        $response->assertSee('UPLOAD DOKUMEN LAMPIRAN');
        $response->assertSee('LAYANAN');
        $response->assertSee('INFORMASI REFERRAL');
        $response->assertSee('PENYETELAN OPERASIONAL');
    }

    public function test_submitting_valid_customer_data_stores_customer_and_redirects(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $data = [
            'full_name' => 'Fajar Pratama',
            'identity_number' => '3502181010900002',
            'gender' => 'Laki-laki',
            'phone' => '08123456789',
            'email' => 'fajar@gmail.com',
            'registration_date' => '2026-06-09',
            'address' => 'Jl. Diponegoro No. 45',
            'latitude' => '-7.8694000',
            'longitude' => '111.4621000',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'contract_period_months' => 12,
            'discount_amount' => 10000,
            'tax_percent' => 11,
            'sales_code' => 'SLS-099',
            'agent_code' => 'AGT-001',
            'referral_customer_code' => 'CID-0002',
            'status' => 'registered',
            'ont_sn' => 'ONT-ZTE-TEST',
            'ip_address' => '10.200.45.99',
            'odp_code' => 'ODP-PON-999',
            'olt_code' => 'OLT-ZTE-C320',
            'vlan_id' => '1024',
        ];

        $response = $this->post('/customers', $data);

        // Assert redirect to customer list
        $response->assertRedirect('/customers');
        $response->assertSessionHas('success');

        // Assert database persistence
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Fajar Pratama',
            'identity_number' => '3502181010900002',
            'phone' => '08123456789',
            'status' => 'registered',
            'sales_code' => 'SLS-099',
            'ont_sn' => 'ONT-ZTE-TEST',
        ]);

        // Assert customer code matches WHUS-YYYY-XXXX
        $customer = Customer::where('full_name', 'Fajar Pratama')->firstOrFail();
        $this->assertMatchesRegularExpression('/^WHUS-\d{4}-\d{4}$/', $customer->customer_code);
    }

    public function test_submitting_invalid_customer_data_fails_validation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        // Submit empty form to trigger validation errors
        $response = $this->post('/customers', []);

        $response->assertSessionHasErrors([
            'full_name',
            'identity_number',
            'gender',
            'phone',
            'registration_date',
            'address',
            'city_id',
            'district_id',
            'village_id',
            'internet_package_id',
            'status',
        ]);
    }

    public function test_customer_creation_with_valid_file_uploads_stores_them_on_disk(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        \Illuminate\Support\Facades\Storage::fake('public');

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $data = [
            'full_name' => 'Fajar Pratama Upload',
            'identity_number' => '3502181010900005',
            'gender' => 'Laki-laki',
            'phone' => '08123456789',
            'email' => 'fajar.upload@gmail.com',
            'registration_date' => '2026-06-09',
            'address' => 'Jl. Diponegoro No. 45',
            'latitude' => '-7.8694000',
            'longitude' => '111.4621000',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'contract_period_months' => 12,
            'discount_amount' => 10000,
            'tax_percent' => 11,
            'status' => 'registered',
            
            // Faked uploads
            'foto_ktp' => \Illuminate\Http\UploadedFile::fake()->image('ktp.jpg'),
            'foto_rumah' => \Illuminate\Http\UploadedFile::fake()->image('rumah.jpg'),
            'foto_kontrak' => \Illuminate\Http\UploadedFile::fake()->create('contract.pdf', 500),
        ];

        $response = $this->post('/customers', $data);

        $response->assertRedirect('/customers');
        $response->assertSessionHas('success');

        $customer = Customer::where('full_name', 'Fajar Pratama Upload')->firstOrFail();
        
        $this->assertNotNull($customer->foto_ktp);
        $this->assertNotNull($customer->foto_rumah);
        $this->assertNotNull($customer->foto_kontrak);

        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($customer->foto_ktp);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($customer->foto_rumah);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($customer->foto_kontrak);
    }
}
