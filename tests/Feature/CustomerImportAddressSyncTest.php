<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerImportAddressSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_confirm_import_synchronizes_address_to_customer_model(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $pop = Pop::create([
            'code' => 'POP-SYNC-TEST',
            'pop_code' => 'SYT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sync Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $fullAddress = 'Jl. Veteran No. 99, Kediri';

        $sheets = [
            'packages' => [],
            'customers' => [
                [
                    'old_customer_id' => 'CUST-SYNC-1',
                    'full_name' => 'John Sync',
                    'phone' => '081234567890',
                    'full_address' => $fullAddress,
                    'pop_id' => $pop->id,
                    'status_row' => 'valid',
                ],
            ],
            'services' => [],
            'technical_details' => [],
            'invoices' => [],
            'payments' => [],
        ];

        $response = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($sheets),
            'file_name' => 'sync_test.xlsx',
        ]);

        $response->assertRedirect('/customers');

        // Assert Customer imported AND address is synchronized
        $this->assertDatabaseHas('customers', [
            'old_customer_id' => 'CUST-SYNC-1',
            'address' => $fullAddress,
        ]);

        // Also assert CustomerAddress is created as usual
        $customer = Customer::where('old_customer_id', 'CUST-SYNC-1')->firstOrFail();
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'full_address' => $fullAddress,
        ]);
    }
}
