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

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_confirm_import_creates_batch_log_and_stores_errors(): void
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

        $sheets = [
            'customers' => [
                // Valid row
                [
                    'original_no' => '1',
                    'status_row' => 'valid',
                    'old_customer_id' => 'CUST-LEG-1',
                    'full_name' => 'Valid Customer',
                    'phone' => '087700000001',
                    'primary_phone' => '087700000001',
                    'village_id' => $village->id,
                    'district_id' => $district->id,
                    'city_id' => $city->id,
                    'pop_id' => $pop->id,
                ],
                // Row with frontend error
                [
                    'original_no' => '2',
                    'status_row' => 'error',
                    'old_customer_id' => '',
                    'full_name' => 'Error Customer',
                    'pop_id' => $pop->id,
                ],
                // Row legacy incomplete that bypasses frontend error check and is still imported for review
                [
                    'original_no' => '3',
                    'status_row' => 'valid',
                    'old_customer_id' => 'CUST-LEG-3',
                    'full_name' => '', // missing name
                    'phone' => '087700000003',
                    'village_id' => $village->id,
                    'pop_id' => $pop->id,
                ]
            ],
            'packages' => [],
            'services' => [],
            'technical_details' => [],
            'invoices' => [],
            'payments' => [],
        ];

        $response = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($sheets),
            'file_name' => 'test-import.xlsx'
        ]);

        $response->assertRedirect('/customers');
        
        // Assert ImportBatch created
        $this->assertDatabaseHas('import_batches', [
            'file_name' => 'test-import.xlsx',
            'total_rows' => 3,
            'valid_rows' => 2, // Row 1 and 3 (initially marked valid in JSON)
            'invalid_rows' => 1, // Row 2
            'imported_rows' => 2, // Row 1 and incomplete legacy Row 3 are imported
            'status' => 'imported',
        ]);

        $batch = ImportBatch::first();
        $this->assertNotNull($batch->batch_number);

        // Assert ImportErrors created
        // Row 2 error
        $this->assertDatabaseHas('import_errors', [
            'import_batch_id' => $batch->id,
            'row_number' => '2',
            'error_message' => '[Customers] Baris error pada sheet Customers.',
        ]);

        // Assert records created in master tables
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Valid Customer',
            'phone' => '087700000001',
            'old_customer_id' => 'CUST-LEG-1',
            'pop_id' => $pop->id,
        ]);

        $customer = Customer::where('full_name', 'Valid Customer')->first();
        $this->assertNotNull($customer);
        $this->assertNotNull($customer->customer_code);
        
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'village_id' => $village->id,
        ]);

        $this->assertDatabaseHas('customers', [
            'full_name' => 'CUST-LEG-3',
            'phone' => '087700000003',
            'old_customer_id' => 'CUST-LEG-3',
            'data_completeness_status' => 'perlu_dilengkapi',
        ]);

        $this->assertEquals(1, $batch->errors()->count());
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
