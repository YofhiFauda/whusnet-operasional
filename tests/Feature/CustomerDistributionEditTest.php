<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\Distribution;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDistributionEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    public function test_customer_edit_view_shows_distribution_field(): void
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

        $distribution = Distribution::create([
            'pop_id' => $pop->id,
            'code' => 'DIST-01',
            'name' => 'Distribution 01',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-TST-000001',
            'full_name' => 'Budi Santoso',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
        ]);

        $response = $this->get("/customers/{$customer->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('KODE DISTRIBUSI');
        $response->assertSee('distribution_id');
        $response->assertSee($distribution->code);
    }

    public function test_submitting_edit_with_distribution_id_updates_customer(): void
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

        $distribution = Distribution::create([
            'pop_id' => $pop->id,
            'code' => 'DIST-SOOKO',
            'name' => 'Distribution Sooko',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-SMN-000001',
            'full_name' => 'Original Name',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'registered',
        ]);

        $updatedData = [
            'full_name' => 'Updated Name',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'distribution_id' => $distribution->id,
            'status' => 'registered',
        ];

        $response = $this->put("/customers/{$customer->id}", $updatedData);

        $response->assertRedirect("/customers/{$customer->id}");
        
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'distribution_id' => $distribution->id,
        ]);
    }
}
