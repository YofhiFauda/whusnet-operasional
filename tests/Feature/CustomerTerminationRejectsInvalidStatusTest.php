<?php

namespace Tests\Feature;

use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerStatusLog;
use App\Models\CustomerTerminationReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Terminasi dulu update() status langsung tanpa state machine: POST manual
 * bisa memutus pelanggan yang belum aktif / sudah putus, old_values audit
 * di-hardcode 'active', dan customer_status_logs tidak terisi. Sekarang lewat
 * CustomerWorkflowService::transition().
 */
class CustomerTerminationRejectsInvalidStatusTest extends TestCase
{
    use RefreshDatabase;

    private function customerWithStatus(string $status, array $overrides = []): Customer
    {
        return Customer::factory()->create(array_merge(['status' => $status], $overrides));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonTerminableStatuses(): array
    {
        return [
            'belum disurvey' => ['waiting_survey'],
            'ditolak' => ['rejected'],
            'sudah putus' => ['terminated'],
        ];
    }

    #[Test]
    #[DataProvider('nonTerminableStatuses')]
    public function terminasi_ditolak_kalau_status_bukan_active_atau_suspended(string $status): void
    {
        $this->loginAsAdmin();
        $terminatedAt = $status === 'terminated' ? now()->subMonth()->startOfSecond() : null;
        $customer = $this->customerWithStatus($status, ['terminated_at' => $terminatedAt]);
        $reason = CustomerTerminationReason::create(['name' => 'Coba paksa']);

        $this->post(route('customers.terminate', $customer), ['termination_reason_id' => $reason->id, 'penalty_amount' => 0])
            ->assertSessionHas('error');

        $customer->refresh();
        $this->assertSame($status, $customer->status);
        $this->assertEquals($terminatedAt, $customer->terminated_at);
        $this->assertSame(0, AuditLog::where('action', 'terminate')->where('auditable_id', $customer->id)->count());
        $this->assertSame(0, CustomerStatusLog::where('customer_id', $customer->id)->count());
    }

    #[Test]
    public function terminasi_dari_suspended_mencatat_status_asal_yang_benar(): void
    {
        $user = $this->loginAsAdmin();
        $customer = $this->customerWithStatus('suspended');
        $reason = CustomerTerminationReason::create(['name' => 'Pindah kota']);

        $this->post(route('customers.terminate', $customer), ['termination_reason_id' => $reason->id, 'penalty_amount' => 0])
            ->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame('terminated', $customer->status);
        $this->assertNotNull($customer->terminated_at);

        // Sebelumnya old_values selalu 'active' walau pelanggan sedang isolir.
        $audit = AuditLog::where('action', 'terminate')->where('auditable_id', $customer->id)->firstOrFail();
        $this->assertSame('suspended', $audit->old_values['status']);
        $this->assertSame('Pindah kota', $audit->new_values['reason']);

        // Sebelumnya terminasi tidak menyentuh customer_status_logs sama sekali.
        $this->assertDatabaseHas('customer_status_logs', [
            'customer_id' => $customer->id,
            'from_status' => 'suspended',
            'to_status' => 'terminated',
            'changed_by' => $user->id,
            'note' => 'Pindah kota',
        ]);

        $this->assertSame($reason->id, $customer->fresh()->termination_reason_id);
    }

    #[Test]
    public function terminasi_dari_active_tetap_menulis_audit_terminate_yang_dibaca_list_putus(): void
    {
        $this->loginAsAdmin();
        $customer = $this->customerWithStatus('active');
        $reason = CustomerTerminationReason::create(['name' => 'Kompetitor']);

        $this->post(route('customers.terminate', $customer), ['termination_reason_id' => $reason->id, 'penalty_amount' => 0])
            ->assertSessionHas('success');

        // RendersCustomerList membaca alasan dari baris ini — jangan dihapus.
        $audit = AuditLog::where('module', 'customers')
            ->where('action', 'terminate')
            ->where('auditable_id', $customer->id)
            ->firstOrFail();
        $this->assertSame('active', $audit->old_values['status']);
        $this->assertSame('Kompetitor', $audit->new_values['reason']);
    }

    #[Test]
    public function terminated_hanya_boleh_kembali_ke_active(): void
    {
        $allowed = WorkflowTransition::TERMINATED->allowedNextTransitions();

        $this->assertSame([WorkflowTransition::ACTIVE], $allowed);
    }
}
