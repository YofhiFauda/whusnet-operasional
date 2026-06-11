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

class CustomerEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_edit_view_loads_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(\Database\Seeders\CustomerSeeder::class);
        $this->loginAsAdmin();

        $customer = Customer::query()->firstOrFail();

        $response = $this->get("/customers/{$customer->id}/edit");

        $response->assertStatus(200);
        $response->assertSee($customer->full_name);
        $response->assertSee('IDENTITAS PELANGGAN');
        $response->assertSee('UPLOAD DOKUMEN LAMPIRAN');
    }

    public function test_submitting_valid_edit_data_updates_customer_and_redirects(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(\Database\Seeders\CustomerSeeder::class);
        $this->loginAsAdmin();

        $customer = Customer::query()->firstOrFail();
        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $updatedData = [
            'full_name' => 'Updated Name',
            'identity_number' => '3502181010900003',
            'gender' => 'Perempuan',
            'phone' => '08987654321',
            'email' => 'updated@gmail.com',
            'registration_date' => '2026-06-09',
            'address' => 'Updated Address',
            'latitude' => '-7.12345',
            'longitude' => '111.12345',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'contract_period_months' => 24,
            'discount_amount' => 15000,
            'tax_percent' => 11,
            'sales_code' => 'SLS-UPD',
            'agent_code' => 'AGT-UPD',
            'referral_customer_code' => 'CID-UPD',
            'status' => 'active',
            'ont_sn' => 'ONT-UPD',
            'ip_address' => '10.200.45.111',
            'odp_code' => 'ODP-UPD',
            'olt_code' => 'OLT-UPD',
            'vlan_id' => '1025',
        ];

        $response = $this->put("/customers/{$customer->id}", $updatedData);

        $response->assertRedirect("/customers/{$customer->id}");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'full_name' => 'Updated Name',
            'identity_number' => '3502181010900003',
            'status' => 'active',
            'ont_sn' => 'ONT-UPD',
        ]);
    }

    public function test_customer_edit_with_valid_file_uploads_stores_them_on_disk(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(\Database\Seeders\CustomerSeeder::class);
        $this->loginAsAdmin();
        \Illuminate\Support\Facades\Storage::fake('public');

        $customer = Customer::query()->firstOrFail();
        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $updatedData = [
            'full_name' => 'Updated Upload Name',
            'identity_number' => '3502181010900003',
            'gender' => 'Perempuan',
            'phone' => '08987654321',
            'email' => 'updated@gmail.com',
            'registration_date' => '2026-06-09',
            'address' => 'Updated Address',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'contract_period_months' => 24,
            'discount_amount' => 15000,
            'tax_percent' => 11,
            'status' => 'active',

            // Upload files
            'foto_ktp' => \Illuminate\Http\UploadedFile::fake()->image('new_ktp.jpg'),
            'foto_rumah' => \Illuminate\Http\UploadedFile::fake()->image('new_rumah.jpg'),
            'foto_kontrak' => \Illuminate\Http\UploadedFile::fake()->create('new_contract.pdf', 500),
        ];

        $response = $this->put("/customers/{$customer->id}", $updatedData);

        $response->assertRedirect("/customers/{$customer->id}");
        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertNotNull($customer->foto_ktp);
        $this->assertNotNull($customer->foto_rumah);
        $this->assertNotNull($customer->foto_kontrak);

        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($customer->foto_ktp);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($customer->foto_rumah);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($customer->foto_kontrak);
    }

    public function test_customer_edit_can_replace_existing_file(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(\Database\Seeders\CustomerSeeder::class);
        $this->loginAsAdmin();
        \Illuminate\Support\Facades\Storage::fake('public');

        $customer = Customer::query()->firstOrFail();
        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        // 1. Upload initial files
        $initialData = [
            'full_name' => 'Initial Name',
            'identity_number' => '3502181010900003',
            'gender' => 'Perempuan',
            'phone' => '08987654321',
            'registration_date' => '2026-06-09',
            'address' => 'Initial Address',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'contract_period_months' => 12,
            'discount_amount' => 0,
            'tax_percent' => 11,
            'status' => 'registered',
            'foto_ktp' => \Illuminate\Http\UploadedFile::fake()->image('old_ktp.jpg'),
        ];
        $this->put("/customers/{$customer->id}", $initialData);
        $customer->refresh();
        $oldKtpPath = $customer->foto_ktp;
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($oldKtpPath);

        // 2. Replace file with new upload
        $replaceData = array_merge($initialData, [
            'foto_ktp' => \Illuminate\Http\UploadedFile::fake()->image('new_ktp_2.jpg'),
        ]);
        $response = $this->put("/customers/{$customer->id}", $replaceData);

        $response->assertRedirect("/customers/{$customer->id}");
        $customer->refresh();

        // New file should exist, old file should be deleted from disk
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($customer->foto_ktp);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldKtpPath);
    }

    public function test_customer_edit_can_delete_existing_file_using_delete_flags(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(\Database\Seeders\CustomerSeeder::class);
        $this->loginAsAdmin();
        \Illuminate\Support\Facades\Storage::fake('public');

        $customer = Customer::query()->firstOrFail();
        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        // 1. Upload initial files
        $initialData = [
            'full_name' => 'Initial Name',
            'identity_number' => '3502181010900003',
            'gender' => 'Perempuan',
            'phone' => '08987654321',
            'registration_date' => '2026-06-09',
            'address' => 'Initial Address',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'contract_period_months' => 12,
            'discount_amount' => 0,
            'tax_percent' => 11,
            'status' => 'registered',
            'foto_ktp' => \Illuminate\Http\UploadedFile::fake()->image('old_ktp.jpg'),
            'foto_rumah' => \Illuminate\Http\UploadedFile::fake()->image('old_rumah.jpg'),
        ];
        $this->put("/customers/{$customer->id}", $initialData);
        $customer->refresh();
        $oldKtpPath = $customer->foto_ktp;
        $oldRumahPath = $customer->foto_rumah;
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($oldKtpPath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($oldRumahPath);

        // 2. Submit with delete flags
        $deleteData = array_merge($initialData, [
            'delete_foto_ktp' => '1',
            'delete_foto_rumah' => '1',
            'foto_ktp' => null,
            'foto_rumah' => null,
        ]);
        $response = $this->put("/customers/{$customer->id}", $deleteData);

        $response->assertRedirect("/customers/{$customer->id}");
        $customer->refresh();

        // Columns should be null, and files should be deleted from disk
        $this->assertNull($customer->foto_ktp);
        $this->assertNull($customer->foto_rumah);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldKtpPath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldRumahPath);
    }
}
