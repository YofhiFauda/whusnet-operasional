<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Village;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerEditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Laravel 11 specific CSRF bypass for tests if actingAs doesn't cover it
        $this->withoutMiddleware([ValidateCsrfToken::class]);
    }

    public function test_customer_edit_view_loads_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $pop = Pop::first() ?? Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-TST-000001',
            'full_name' => 'Budi Santoso',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
        ]);

        $response = $this->get("/customers/{$customer->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('Budi Santoso');
        $response->assertSee('IDENTITAS PELANGGAN');
        $response->assertSee('UPLOAD DOKUMEN LAMPIRAN');
    }

    public function test_submitting_valid_edit_data_updates_customer_and_redirects(): void
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

        $customer = Customer::create([
            'customer_code' => 'C-SMN-000001',
            'full_name' => 'Original Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
        ]);

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $updatedData = [
            'full_name' => 'Updated Name',
            'identity_number' => '3502181010900003',
            'gender' => 'Perempuan',
            'primary_phone' => '08987654321',
            'alternative_phone' => '089988776655',
            'email' => 'updated@gmail.com',
            'registration_date' => '2026-06-09',
            'pop_id' => $pop->id,
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
            'primary_phone' => '08987654321',
            'pop_id' => $pop->id,
            'status' => 'active',
            'ont_sn' => 'ONT-UPD',
        ]);
    }

    public function test_customer_edit_with_valid_file_uploads_stores_them_on_disk(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        Storage::fake('public');

        $pop = Pop::create([
            'code' => 'SMN2',
            'pop_code' => 'SMN2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-SMN-000001',
            'full_name' => 'Original Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
        ]);

        $updatedData = [
            'full_name' => 'File Upload Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
            'foto_ktp' => UploadedFile::fake()->image('ktp.jpg'),
            'foto_rumah' => UploadedFile::fake()->image('rumah.jpg'),
        ];

        $response = $this->put("/customers/{$customer->id}", $updatedData);

        $response->assertRedirect("/customers/{$customer->id}");
        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertNotNull($customer->foto_ktp);
        $this->assertNotNull($customer->foto_rumah);
    }

    public function test_customer_edit_can_replace_existing_file(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        Storage::fake('public');

        $pop = Pop::create([
            'code' => 'SMN3',
            'pop_code' => 'SMN3',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 3',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $oldKtpPath = 'documents/old_ktp.jpg';
        Storage::disk('public')->put($oldKtpPath, 'old ktp');

        $customer = Customer::create([
            'customer_code' => 'C-SMN-000001',
            'full_name' => 'Replace File Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
            'foto_ktp' => $oldKtpPath,
        ]);

        $replaceData = [
            'full_name' => 'Replace File Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
            'foto_ktp' => UploadedFile::fake()->image('new_ktp_2.jpg'),
        ];

        $response = $this->put("/customers/{$customer->id}", $replaceData);

        $response->assertRedirect("/customers/{$customer->id}");
        $customer->refresh();

        $this->assertNotSame($oldKtpPath, $customer->foto_ktp);
    }

    public function test_customer_edit_can_delete_existing_file_using_delete_flags(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        Storage::fake('public');

        $pop = Pop::create([
            'code' => 'SMN4',
            'pop_code' => 'SMN4',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 4',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $oldKtpPath = UploadedFile::fake()->image('ktp_to_del.jpg')->store('documents', 'public');
        $oldRumahPath = UploadedFile::fake()->image('rumah_to_del.jpg')->store('documents', 'public');

        $customer = Customer::create([
            'customer_code' => 'C-SMN-000001',
            'full_name' => 'Delete File Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
            'foto_ktp' => $oldKtpPath,
            'foto_rumah' => $oldRumahPath,
        ]);

        $deleteData = [
            'full_name' => 'Delete File Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
            'delete_foto_ktp' => '1',
            'delete_foto_rumah' => '1',
            'foto_ktp' => null,
            'foto_rumah' => null,
        ];

        $response = $this->put("/customers/{$customer->id}", $deleteData);

        $response->assertRedirect("/customers/{$customer->id}");
        $customer->refresh();

        $this->assertNull($customer->foto_ktp);
        $this->assertNull($customer->foto_rumah);
    }
}
