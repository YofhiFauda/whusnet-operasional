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

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_page_loads_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get('/customers/import');

        $response->assertStatus(200);
        $response->assertSee('Import Pelanggan Baru');
        $response->assertSee('Upload File (Excel / CSV)');
        $response->assertSee('Copy-Paste Data');
    }

    public function test_validate_import_endpoint_performs_db_matching_and_returns_json(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $rows = [
            // Row 1: Valid row matching exact Village & Package
            [
                'no' => '1',
                'id' => '3502181010900008',
                'nama' => 'Joni Wijaya',
                'desa' => $village->name,
                'paket' => $package->package_code,
                'hp' => '08123456701',
                'koordinat' => '-7.86940,111.46210',
            ],
            // Row 2: Invalid row (missing name, misspelled village & package)
            [
                'no' => '2',
                'id' => '', // missing NIK
                'nama' => '', // missing name
                'desa' => 'Desa Fiktif', // unmatched village
                'paket' => 'PAKET-FIKTIF', // unmatched package
                'hp' => '08123456702',
                'koordinat' => 'invalid-coordinate',
            ]
        ];

        $response = $this->postJson('/customers/import/validate', ['rows' => $rows]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        
        $data = $response->json();
        $this->assertCount(2, $data['rows']);

        // Row 1 Assertions (Valid)
        $row1 = $data['rows'][0];
        $this->assertEquals('valid', $row1['status_row']);
        $this->assertEquals($village->id, $row1['village_id']);
        $this->assertEquals($package->id, $row1['internet_package_id']);
        $this->assertEmpty($row1['errors']);
        $this->assertEmpty($row1['warnings']);

        // Row 2 Assertions (Invalid & Unmatched)
        $row2 = $data['rows'][1];
        $this->assertEquals('error', $row2['status_row']);
        $this->assertNull($row2['village_id']);
        $this->assertNull($row2['internet_package_id']);
        $this->assertContains('Nama lengkap wajib diisi.', $row2['errors']);
        $this->assertContains('ID/NIK wajib diisi.', $row2['errors']);
        $this->assertContains("Desa 'Desa Fiktif' tidak ditemukan di database. Silakan pilih manual.", $row2['warnings']);
        $this->assertContains("Paket 'PAKET-FIKTIF' tidak ditemukan di database. Silakan pilih manual.", $row2['warnings']);
        $this->assertContains("Format koordinat tidak valid (harus dipisah koma: lat, long).", $row2['warnings']);
    }

    public function test_confirm_import_endpoint_saves_records_to_database(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $resolvedRows = [
            [
                'full_name' => 'Imported Customer 1',
                'identity_number' => '3502181010900010',
                'gender' => 'Laki-laki',
                'phone' => '087711223344',
                'email' => null,
                'registration_date' => '2026-06-09',
                'address' => 'Alamat Kel. ' . $village->name,
                'city_id' => $city->id,
                'district_id' => $district->id,
                'village_id' => $village->id,
                'village_name' => $village->name,
                'internet_package_id' => $package->id,
                'package_code' => $package->package_code,
                'contract_period_months' => 12,
                'discount_amount' => 0,
                'tax_percent' => 11,
                'status' => 'registered',
                'latitude' => -7.86940,
                'longitude' => 111.46210,
            ],
            [
                'full_name' => 'Imported Customer 2',
                'identity_number' => '3502181010900011',
                'gender' => 'Perempuan',
                'phone' => '087711223345',
                'email' => 'imported2@gmail.com',
                'registration_date' => '2026-06-09',
                'address' => 'Alamat Kel. ' . $village->name,
                'city_id' => $city->id,
                'district_id' => $district->id,
                'village_id' => $village->id,
                'village_name' => $village->name,
                'internet_package_id' => $package->id,
                'package_code' => $package->package_code,
                'contract_period_months' => 12,
                'discount_amount' => 0,
                'tax_percent' => 11,
                'status' => 'registered',
                'latitude' => null,
                'longitude' => null,
            ]
        ];

        $response = $this->post('/customers/import/confirm', ['rows' => json_encode($resolvedRows)]);

        $response->assertRedirect('/customers');
        $response->assertSessionHas('success');

        // Assert database records
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Imported Customer 1',
            'identity_number' => '3502181010900010',
            'phone' => '087711223344',
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
        ]);

        $this->assertDatabaseHas('customers', [
            'full_name' => 'Imported Customer 2',
            'identity_number' => '3502181010900011',
            'phone' => '087711223345',
            'email' => 'imported2@gmail.com',
        ]);

        // Assert customer codes are sequentially generated
        $c1 = Customer::where('full_name', 'Imported Customer 1')->firstOrFail();
        $c2 = Customer::where('full_name', 'Imported Customer 2')->firstOrFail();

        $this->assertMatchesRegularExpression('/^WHUS-\d{4}-\d{4}$/', $c1->customer_code);
        $this->assertMatchesRegularExpression('/^WHUS-\d{4}-\d{4}$/', $c2->customer_code);

        // Code sequence comparison
        $c1Num = intval(substr($c1->customer_code, -4));
        $c2Num = intval(substr($c2->customer_code, -4));
        $this->assertEquals($c1Num + 1, $c2Num);
    }
}
