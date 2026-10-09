<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerTerminationReason;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * List Putus (ADHOC-69 §2.2/§2.3) — Alasan dibaca dari relasi
 * `termination_reason_id` (bukan lagi AuditLog di memori), Sales & Teknisi
 * Survei dari relasi existing, filter alasan bekerja di level query.
 */
class CustomerTerminatedListReasonSalesSurveyTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->pop = Pop::create([
            'code' => 'POP-LST',
            'pop_code' => 'LST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP List Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function menampilkan_alasan_dan_pelaku_input_dari_creator_atau_registered_by_name(): void
    {
        $this->loginAsAdmin();

        $reason = CustomerTerminationReason::create(['name' => 'Kompetitor']);
        $adminRole = Role::where('code', 'admin')->first();
        $inputter = User::factory()->create(['role_id' => $adminRole->id, 'name' => 'Siti Admin Input', 'status' => 'active']);

        $customer1 = Customer::create([
            'customer_code' => 'C-LST-000001',
            'full_name' => 'Pelanggan List Creator',
            'primary_phone' => '081200000010',
            'registration_date' => now(),
            'pop_id' => $this->pop->id,
            'status' => 'terminated',
            'terminated_at' => now(),
            'termination_reason_id' => $reason->id,
            'created_by' => $inputter->id,
        ]);

        $customer2 = Customer::create([
            'customer_code' => 'C-LST-000002',
            'full_name' => 'Pelanggan List Legacy',
            'primary_phone' => '081200000011',
            'registration_date' => now(),
            'pop_id' => $this->pop->id,
            'status' => 'terminated',
            'terminated_at' => now(),
            'termination_reason_id' => $reason->id,
            'registered_by_name' => 'Rina CS Legacy',
        ]);

        $response = $this->get(route('customers.terminated'));

        $response->assertOk();
        $response->assertSee('Input Oleh');
        $response->assertSee('Kompetitor');
        $response->assertSee('Siti Admin Input');
        $response->assertSee('Rina CS Legacy');
        $response->assertDontSee('<th scope="col" class="py-3.5 px-4">Sales</th>', false);
        $response->assertDontSee('<th scope="col" class="py-3.5 px-4">Teknisi Survei</th>', false);
    }

    #[Test]
    public function pelanggan_tanpa_pelaku_input_tampil_strip(): void
    {
        $this->loginAsAdmin();

        $reason = CustomerTerminationReason::create(['name' => 'Alasan Sepi']);

        Customer::create([
            'customer_code' => 'C-LST-000003',
            'full_name' => 'Pelanggan Tanpa Relasi',
            'primary_phone' => '081200000014',
            'registration_date' => now(),
            'pop_id' => $this->pop->id,
            'status' => 'terminated',
            'terminated_at' => now(),
            'termination_reason_id' => $reason->id,
        ]);

        $response = $this->get(route('customers.terminated'));

        $response->assertOk();
        $response->assertSee('Alasan Sepi');
    }

    #[Test]
    public function filter_alasan_bekerja_di_level_query(): void
    {
        $this->loginAsAdmin();

        $reasonA = CustomerTerminationReason::create(['name' => 'Alasan A']);
        $reasonB = CustomerTerminationReason::create(['name' => 'Alasan B']);

        Customer::create([
            'customer_code' => 'C-LST-000003',
            'full_name' => 'Pelanggan Alasan A',
            'primary_phone' => '081200000012',
            'registration_date' => now(),
            'pop_id' => $this->pop->id,
            'status' => 'terminated',
            'terminated_at' => now(),
            'termination_reason_id' => $reasonA->id,
        ]);

        Customer::create([
            'customer_code' => 'C-LST-000004',
            'full_name' => 'Pelanggan Alasan B',
            'primary_phone' => '081200000013',
            'registration_date' => now(),
            'pop_id' => $this->pop->id,
            'status' => 'terminated',
            'terminated_at' => now(),
            'termination_reason_id' => $reasonB->id,
        ]);

        $response = $this->get(route('customers.terminated', ['termination_reason_id' => $reasonA->id]));

        $response->assertOk();
        $response->assertSee('Pelanggan Alasan A');
        $response->assertDontSee('Pelanggan Alasan B');
    }
}
