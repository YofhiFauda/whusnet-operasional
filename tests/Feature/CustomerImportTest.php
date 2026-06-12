<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\City;
use App\Models\District;
use App\Models\Village;
use App\Models\InternetPackage;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_import_page_loads_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get('/customers/import');

        $response->assertStatus(200);
        $response->assertSee('Import Pelanggan Baru');
        $response->assertSee('Upload File (Excel / CSV)');
        $response->assertSee('Copy-Paste Data');
        $response->assertSee('Download Template CSV');
    }

    public function test_admin_can_download_customer_import_template(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get('/customers/import/template');

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=template-import-pelanggan.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('old_customer_id,full_name,primary_phone,full_address,village,district,city,pop_code,pop_name,package_name,monthly_price,activation_date,due_date,service_status', $content);
        $this->assertStringContainsString('identity_number,alternative_phone,email,latitude,longitude,ont_sn,ip_address,odp_code,olt_code,vlan_id,technical_note', $content);
        $this->assertStringContainsString('OLD-0001', $content);
    }

    public function test_validate_import_endpoint_performs_db_matching_and_returns_json(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Siman',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $rows = [
            [
                'no' => '1',
                'old_customer_id' => 'OLD-1001',
                'full_name' => 'Joni Wijaya',
                'primary_phone' => '08123456701',
                'full_address' => 'Jl. Merdeka No. 1',
                'village' => $village->name,
                'district' => $district->name,
                'city' => $city->name,
                'pop_code' => $pop->pop_code,
                'pop_name' => $pop->name,
                'package_name' => $package->package_code,
                'monthly_price' => (string) (int) $package->monthly_price,
                'activation_date' => '2026-06-01',
                'due_date' => '2026-07-01',
                'service_status' => 'aktif',
                'identity_number' => '3502181010900008',
                'latitude' => '-7.86940',
                'longitude' => '111.46210',
                'ont_sn' => 'ONT1001',
                'ip_address' => '10.10.10.2',
                'odp_code' => 'ODP-SMN-001',
                'olt_code' => 'OLT-SMN-01',
                'vlan_id' => '100',
                'technical_note' => 'Lengkap',
            ],
            [
                'no' => '2',
                'old_customer_id' => '',
                'full_name' => '',
                'primary_phone' => '',
                'full_address' => '',
                'village' => 'Desa Fiktif',
                'district' => '',
                'city' => '',
                'pop_code' => 'POP-FIKTIF',
                'package_name' => 'PAKET-FIKTIF',
                'monthly_price' => 'abc',
                'activation_date' => 'tanggal-salah',
                'due_date' => '2026-01-01',
                'service_status' => 'status-fiktif',
            ]
        ];

        $response = $this->postJson('/customers/import/validate', ['rows' => $rows]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        
        $data = $response->json();
        $this->assertCount(2, $data['rows']);

        $row1 = $data['rows'][0];
        $this->assertEquals('valid', $row1['status_row']);
        $this->assertEquals('OLD-1001', $row1['old_customer_id']);
        $this->assertEquals($pop->id, $row1['pop_id']);
        $this->assertEquals($village->id, $row1['village_id']);
        $this->assertEquals($package->id, $row1['internet_package_id']);
        $this->assertEquals('active', $row1['service_status']);
        $this->assertEmpty($row1['errors']);
        $this->assertEmpty($row1['warnings']);

        $row2 = $data['rows'][1];
        $this->assertEquals('error', $row2['status_row']);
        $this->assertNull($row2['village_id']);
        $this->assertNull($row2['pop_id']);
        $this->assertNull($row2['internet_package_id']);
        $this->assertContains('ID pelanggan lama wajib diisi.', $row2['errors']);
        $this->assertContains('Nama lengkap wajib diisi.', $row2['errors']);
        $this->assertContains('Nomor HP wajib diisi.', $row2['errors']);
        $this->assertContains('Alamat lengkap wajib diisi.', $row2['errors']);
        $this->assertContains("Desa/Kelurahan 'Desa Fiktif' tidak ditemukan di master wilayah.", $row2['errors']);
        $this->assertContains('POP/Cabang tidak ditemukan atau tidak aktif.', $row2['errors']);
        $this->assertContains("Paket 'PAKET-FIKTIF' tidak ditemukan di master paket aktif.", $row2['errors']);
        $this->assertContains('Harga paket harus berupa angka.', $row2['errors']);
        $this->assertContains('Tanggal aktivasi tidak valid.', $row2['errors']);
        $this->assertContains('Status layanan tidak sesuai pilihan sistem.', $row2['errors']);
    }

    public function test_validate_import_marks_duplicate_and_missing_technical_fields(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-SUK',
            'pop_code' => 'SUK',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sukorejo',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        Customer::create([
            'customer_code' => 'C-SUK-000001',
            'old_customer_id' => 'OLD-DUP',
            'full_name' => 'Existing Customer',
            'phone' => '081299999999',
            'primary_phone' => '081299999999',
            'registration_date' => '2026-01-01',
            'status' => 'registered',
        ]);

        $baseRow = [
            'full_name' => 'Valid Name',
            'full_address' => 'Jl. Valid No. 1',
            'village' => $village->name,
            'district' => $district->name,
            'city' => $city->name,
            'pop_code' => $pop->pop_code,
            'package_name' => $package->package_code,
            'monthly_price' => (string) (int) $package->monthly_price,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'registered',
        ];

        $response = $this->postJson('/customers/import/validate', [
            'rows' => [
                $baseRow + [
                    'old_customer_id' => 'OLD-DUP',
                    'primary_phone' => '081299999999',
                ],
                $baseRow + [
                    'old_customer_id' => 'OLD-FILE-DUP',
                    'primary_phone' => '081200000001',
                ],
                $baseRow + [
                    'old_customer_id' => 'OLD-FILE-DUP',
                    'primary_phone' => '081200000001',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $rows = $response->json('rows');

        $this->assertEquals('error', $rows[0]['status_row']);
        $this->assertContains('ID pelanggan lama sudah terdaftar di database.', $rows[0]['errors']);
        $this->assertContains('Nomor HP sudah terdaftar di database.', $rows[0]['errors']);

        $this->assertEquals('warning', $rows[1]['status_row']);
        $this->assertTrue($rows[1]['technical_incomplete']);
        $this->assertContains('ont_sn', $rows[1]['missing_technical_fields']);

        $this->assertEquals('error', $rows[2]['status_row']);
        $this->assertContains('ID pelanggan lama duplikat di file import.', $rows[2]['errors']);
        $this->assertContains('Nomor HP duplikat di file import.', $rows[2]['errors']);
    }

    public function test_confirm_import_endpoint_saves_records_to_database(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();
        $pop = \App\Models\Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $resolvedRows = [
            [
                'pop_id' => $pop->id,
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
                'pop_id' => $pop->id,
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
            'pop_id' => $pop->id,
        ]);

        $c1 = Customer::where('full_name', 'Imported Customer 1')->firstOrFail();

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $c1->id,
            'village_id' => $village->id,
        ]);

        $this->assertDatabaseHas('customer_services', [
            'customer_id' => $c1->id,
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

        $this->assertMatchesRegularExpression('/^[A-Z]-[A-Z]{3}-\d{6}$/', $c1->customer_code);
        $this->assertMatchesRegularExpression('/^[A-Z]-[A-Z]{3}-\d{6}$/', $c2->customer_code);

        // Code sequence comparison
        $c1Num = intval(substr($c1->customer_code, -6));
        $c2Num = intval(substr($c2->customer_code, -6));
        $this->assertEquals($c1Num + 1, $c2Num);
    }
}
