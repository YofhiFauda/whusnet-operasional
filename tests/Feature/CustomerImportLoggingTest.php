<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\City;
use App\Models\District;
use App\Models\Village;
use App\Models\InternetPackage;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerImportLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_import_creates_batch_log_and_stores_errors(): void
    {
        $this->withoutMiddleware();
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

        $rows = [
            // Valid row
            [
                'original_no' => '1',
                'status_row' => 'valid',
                'full_name' => 'Valid Customer',
                'phone' => '087700000001',
                'village_id' => $village->id,
                'district_id' => $district->id,
                'city_id' => $city->id,
                'internet_package_id' => $package->id,
                'pop_id' => $pop->id,
                'status' => 'registered',
            ],
            // Row with frontend error
            [
                'original_no' => '2',
                'status_row' => 'error',
                'full_name' => 'Error Customer',
                'errors' => ['ID pelanggan lama wajib diisi.'],
                'raw_data' => ['some' => 'data'],
                'pop_id' => $pop->id,
            ],
            // Row that bypasses frontend error check but fails backend required field check
            [
                'original_no' => '3',
                'status_row' => 'valid',
                'full_name' => '', // missing name
                'phone' => '087700000003',
                'village_id' => $village->id,
                'internet_package_id' => $package->id,
                'pop_id' => $pop->id,
            ]
        ];

        $response = $this->post('/customers/import/confirm', [
            'rows' => json_encode($rows),
            'file_name' => 'test-import.xlsx'
        ]);

        $response->assertRedirect('/customers');
        
        // Assert ImportBatch created
        $this->assertDatabaseHas('import_batches', [
            'file_name' => 'test-import.xlsx',
            'total_rows' => 3,
            'valid_rows' => 2, // Row 1 and 3 (initially marked valid in JSON)
            'invalid_rows' => 1, // Row 2
            'imported_rows' => 1, // Only Row 1
            'status' => 'imported',
        ]);

        $batch = ImportBatch::first();
        $this->assertNotNull($batch->batch_number);

        // Assert ImportErrors created
        // Row 2 error
        $this->assertDatabaseHas('import_errors', [
            'import_batch_id' => $batch->id,
            'row_number' => '2',
            'error_message' => 'ID pelanggan lama wajib diisi.',
        ]);

        // Row 3 error (backend check)
        $this->assertDatabaseHas('import_errors', [
            'import_batch_id' => $batch->id,
            'row_number' => '3',
            'error_message' => 'Data wajib database kosong (Nama/HP/Wilayah/Paket/POP).',
        ]);

        // Assert records created in master tables
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Valid Customer',
            'phone' => '087700000001',
            'old_customer_id' => null,
            'pop_id' => $rows[0]['pop_id'],
        ]);

        $customer = \App\Models\Customer::where('full_name', 'Valid Customer')->first();
        $this->assertNotNull($customer);
        $this->assertNotNull($customer->customer_code);
        
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'village_id' => $village->id,
        ]);

        $this->assertDatabaseHas('customer_services', [
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
        ]);

        // Verify status mapping
        $this->assertEquals('calon_pelanggan', $customer->customer_status);
        
        $this->assertEquals(2, $batch->errors()->count());
    }

    public function test_import_history_page_is_accessible(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->loginAsAdmin();

        ImportBatch::create([
            'batch_number' => 'IMP-20260612-0001',
            'file_name' => 'history-test.csv',
            'uploaded_by' => $user->id,
            'total_rows' => 10,
            'valid_rows' => 8,
            'invalid_rows' => 2,
            'imported_rows' => 8,
            'status' => 'imported',
        ]);

        $response = $this->get('/customers/import/history');

        $response->assertStatus(200);
        $response->assertSee('IMP-20260612-0001');
        $response->assertSee('history-test.csv');
        $response->assertSee('IMPORTED');
    }
}
