<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Village;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $response->assertSee('LAYANAN');
    }

    public function test_submitting_valid_customer_data_stores_customer_and_redirects(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $data = [
            'full_name' => 'Fajar Pratama',
            'identity_number' => '3502181010900002',
            'gender' => 'Laki-laki',
            'primary_phone' => '08123456789',
            'alternative_phone' => '089988776655',
            'email' => 'fajar@gmail.com',
            'registration_date' => '2026-06-09',
            'pop_id' => $pop->id,
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
            'odp_code' => 'ODP-PON-999',
            'olt_code' => 'OLT-ZTE-C320',
            'vlan_id' => '1024',
        ];

        $response = $this->post('/customers', $data);

        // Registrasi mengarahkan ke halaman detail pelanggan baru (bukan list).
        $newCustomer = Customer::where('full_name', 'Fajar Pratama')->firstOrFail();
        $response->assertRedirect("/customers/{$newCustomer->id}");
        $response->assertSessionHas('success');

        // Assert database persistence
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Fajar Pratama',
            'identity_number' => '3502181010900002',
            'primary_phone' => '08123456789',
            'pop_id' => $pop->id,
            'status' => 'waiting_survey',
            'sales_code' => 'SLS-099',
            'ont_sn' => 'ONT-ZTE-TEST',
        ]);

        // Assert customer code matches new format: {registration_prefix}{######}
        // POP ini: registration_prefix='C' → C000001
        $customer = Customer::where('full_name', 'Fajar Pratama')->firstOrFail();
        $this->assertMatchesRegularExpression('/^C\d{6}$/', $customer->customer_code);

        // Assert address record created
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Diponegoro No. 45',
            'city_id' => $city->id,
        ]);

        // Assert service record created
        $this->assertDatabaseHas('customer_services', [
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
        ]);
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
            'primary_phone',
            'registration_date',
            'pop_id',
            'address',
            'city_id',
            'district_id',
            'village_id',
            'internet_package_id',
        ]);
    }

    public function test_customer_creation_with_valid_file_uploads_stores_them_on_disk(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        Storage::fake('public');

        $pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $data = [
            'full_name' => 'Fajar Pratama Upload',
            'identity_number' => '3502181010900005',
            'gender' => 'Laki-laki',
            'primary_phone' => '08123456789',
            'email' => 'fajar.upload@gmail.com',
            'registration_date' => '2026-06-09',
            'pop_id' => $pop->id,
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
            'foto_rumah' => UploadedFile::fake()->image('rumah.jpg'),
            'foto_kontrak' => UploadedFile::fake()->create('contract.pdf', 500),
        ];

        $response = $this->post('/customers', $data);

        $customer = Customer::where('full_name', 'Fajar Pratama Upload')->firstOrFail();
        $response->assertRedirect("/customers/{$customer->id}");
        $response->assertSessionHas('success');

        $this->assertNotNull($customer->foto_rumah);
        $this->assertNotNull($customer->foto_kontrak);

        Storage::disk('public')->assertExists($customer->foto_rumah);
        Storage::disk('public')->assertExists($customer->foto_kontrak);
    }
}
