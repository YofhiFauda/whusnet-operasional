<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSurvey;
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
    public function menampilkan_alasan_sales_dan_teknisi_survei_dari_relasi(): void
    {
        $this->loginAsAdmin();

        $reason = CustomerTerminationReason::create(['name' => 'Kompetitor']);
        $salesRole = Role::where('code', 'sales')->first();
        $sales = User::factory()->create(['role_id' => $salesRole->id, 'name' => 'Budi Sales', 'status' => 'active']);
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Andi Teknisi', 'status' => 'active']);

        $customer = Customer::create([
            'customer_code' => 'C-LST-000001',
            'full_name' => 'Pelanggan List',
            'primary_phone' => '081200000010',
            'registration_date' => now(),
            'pop_id' => $this->pop->id,
            'status' => 'terminated',
            'terminated_at' => now(),
            'termination_reason_id' => $reason->id,
            'sales_user_id' => $sales->id,
        ]);

        CustomerSurvey::create([
            'customer_id' => $customer->id,
            'technician_id' => $teknisi->id,
            'survey_status' => 'completed',
        ]);

        $response = $this->get(route('customers.terminated'));

        $response->assertOk();
        $response->assertSee('Kompetitor');
        $response->assertSee('Budi Sales');
        $response->assertSee('Andi Teknisi');
    }

    #[Test]
    public function pelanggan_tanpa_sales_atau_survei_tampil_strip(): void
    {
        $this->loginAsAdmin();

        $reason = CustomerTerminationReason::create(['name' => 'Alasan Sepi']);

        Customer::create([
            'customer_code' => 'C-LST-000002',
            'full_name' => 'Pelanggan Tanpa Relasi',
            'primary_phone' => '081200000011',
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
