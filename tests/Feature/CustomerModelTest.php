<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\User;
use App\Models\Role;
use App\Models\InternetPackage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_be_saved_with_s3_t001_fields(): void
    {
        // Seed standard tables
        $this->seed(DatabaseSeeder::class);

        // Setup POP
        $pop = Pop::create([
            'code' => 'POP-PON-01',
            'name' => 'POP Ponorogo Pusat',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Get Owner User and package from seeders
        $user = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $package = InternetPackage::firstOrFail();

        // Create Customer with S3-T001 fields
        $customerData = [
            'customer_code' => 'WHUS-2026-9999',
            'old_customer_id' => 'OLD-9992',
            'cid' => 'CID-10029',
            'full_name' => 'Ahmad Fauzi',
            'identity_number' => '3502181212900001',
            'gender' => 'Laki-laki',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'alternative_phone' => '089988776655',
            'email' => 'ahmad@example.com',
            'registration_date' => '2026-06-11',
            'data_completeness_status' => 'draft',
            'customer_status' => 'calon_pelanggan',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'status' => 'registered', // Keep legacy status compatible
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $customer = Customer::create($customerData);

        // Assert customer exists in database
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'customer_code' => 'WHUS-2026-9999',
            'old_customer_id' => 'OLD-9992',
            'cid' => 'CID-10029',
            'full_name' => 'Ahmad Fauzi',
            'primary_phone' => '081234567890',
            'alternative_phone' => '089988776655',
            'data_completeness_status' => 'draft',
            'customer_status' => 'calon_pelanggan',
            'pop_id' => $pop->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Assert Relationships
        $this->assertNotNull($customer->pop);
        $this->assertEquals($pop->name, $customer->pop->name);

        $this->assertNotNull($customer->creator);
        $this->assertEquals($user->name, $customer->creator->name);

        $this->assertNotNull($customer->updater);
        $this->assertEquals($user->name, $customer->updater->name);

        // Verify status fields
        $this->assertEquals('draft', $customer->data_completeness_status);
        $this->assertEquals('calon_pelanggan', $customer->customer_status);
        $this->assertEquals('OLD-9992', $customer->old_customer_id);
        $this->assertEquals('CID-10029', $customer->cid);
    }
}
