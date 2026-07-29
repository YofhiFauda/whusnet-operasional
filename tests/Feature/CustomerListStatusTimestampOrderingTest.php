<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Pop;
use App\Services\CustomerWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5.1 — kolom nyata rejected_at/terminated_at menggantikan subquery JSON
 * berkorelasi di ORDER BY daftar pelanggan tab Gagal/Putus.
 *
 * Menjaga: (a) kolom terisi saat transisi/terminate, (b) command backfill
 * mengisi data lama dari audit_logs, (c) tab Gagal/Putus mengurut pakai kolom
 * dan render 200.
 */
class CustomerListStatusTimestampOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function pop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    public function test_transisi_ke_rejected_mengisi_rejected_at(): void
    {
        $this->loginAsAdmin(); // audit transisi butuh user_id valid
        $customer = Customer::factory()->create([
            'status' => 'verification_admin',
            'pop_id' => $this->pop()->id,
        ]);

        app(CustomerWorkflowService::class)->transition($customer, 'rejected', 'Alasan uji');

        $this->assertNotNull($customer->fresh()->rejected_at);
    }

    public function test_terminate_mengisi_terminated_at(): void
    {
        $admin = $this->loginAsAdmin();
        $customer = Customer::factory()->create([
            'status' => 'active',
            'pop_id' => $this->pop()->id,
        ]);

        $this->actingAs($admin)
            ->post("/customers/{$customer->id}/terminate", ['reason' => 'Pindah rumah'])
            ->assertRedirect();

        $this->assertNotNull($customer->fresh()->terminated_at);
    }

    public function test_backfill_mengisi_kolom_dari_audit_untuk_data_lama(): void
    {
        $customer = Customer::factory()->create([
            'status' => 'rejected',
            'rejected_at' => null, // data lama, kolom belum terisi
            'pop_id' => $this->pop()->id,
        ]);

        $when = now()->subDays(10)->startOfSecond();
        AuditLog::create([
            'user_id' => null,
            'module' => 'Customer Workflow',
            'action' => 'status_transition',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'old_values' => ['status' => 'verification_admin'],
            'new_values' => ['status' => 'rejected', 'note' => 'Ditolak: uji'],
            'created_at' => $when,
        ]);

        $this->artisan('customers:backfill-status-timestamps')->assertSuccessful();

        $this->assertEquals(
            $when->toDateTimeString(),
            $customer->fresh()->rejected_at?->toDateTimeString()
        );
    }

    public function test_backfill_idempoten_tidak_menimpa_nilai_yang_sudah_ada(): void
    {
        $existing = now()->subDays(2)->startOfSecond();
        $customer = Customer::factory()->create([
            'status' => 'rejected',
            'rejected_at' => $existing,
            'pop_id' => $this->pop()->id,
        ]);

        AuditLog::create([
            'user_id' => null,
            'module' => 'Customer Workflow',
            'action' => 'status_transition',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'old_values' => ['status' => 'verification_admin'],
            'new_values' => ['status' => 'rejected'],
            'created_at' => now()->subDays(99),
        ]);

        $this->artisan('customers:backfill-status-timestamps')->assertSuccessful();

        $this->assertEquals(
            $existing->toDateTimeString(),
            $customer->fresh()->rejected_at?->toDateTimeString(),
            'Backfill tidak boleh menimpa rejected_at yang sudah terisi.'
        );
    }

    public function test_tab_gagal_mengurut_terbaru_dulu_tanpa_error(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->pop();

        $lama = Customer::factory()->create([
            'status' => 'rejected', 'rejected_at' => now()->subDays(30),
            'full_name' => 'Reject Lama', 'pop_id' => $pop->id,
        ]);
        $baru = Customer::factory()->create([
            'status' => 'rejected', 'rejected_at' => now()->subDay(),
            'full_name' => 'Reject Baru', 'pop_id' => $pop->id,
        ]);

        // Route + permission sendiri sekarang — lihat CustomerFailedController.
        $response = $this->actingAs($admin)->get(route('customers.failed'));
        $response->assertStatus(200);

        // Yang paling baru ditolak muncul lebih dulu di halaman.
        $pos = fn ($name) => strpos($response->getContent(), $name);
        $this->assertLessThan($pos($lama->full_name), $pos($baru->full_name));
    }
}
