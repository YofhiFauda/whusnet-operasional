<?php

namespace Tests\Feature;

use App\Enums\CReqVerificationStatus;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CreqBillingVerificationFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Antrean "Verifikasi Biaya C-REQ" — CS (role helpdesk) menyetujui/menolak
 * task C-REQ berbayar sebelum diteruskan ke Tagihan Manual. Approve TIDAK
 * membuat invoice otomatis, cuma menandai verified & redirect ke
 * /invoices/create dengan pelanggan+kategori+catatan sudah terisi.
 *
 * docs/plan/task-teknisi/rancangan-biaya-creq-verifikasi-cs.md
 */
class CreqBillingVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $helpdesk;

    protected Task $task;

    protected TaskCreqDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(CreqBillingVerificationFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'CREQV-TEST', 'pop_code' => 'CQV', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Verifikasi C-REQ', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-CREQV-001',
            'full_name' => 'Pelanggan Verifikasi C-REQ',
            'primary_phone' => '0812340010',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $this->helpdesk = User::factory()->create(['role_id' => $helpdeskRole->id]);
        $this->helpdesk->roleScopes()->create(['role_id' => $helpdeskRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->task = Task::create([
            'task_number' => 'TASK-CREQV-0001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::CREQ->value,
            'title' => 'Customer Request Verifikasi Test',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => now(),
            'created_by' => $this->helpdesk->id,
            'updated_by' => $this->helpdesk->id,
        ]);

        $this->detail = TaskCreqDetail::create([
            'task_id' => $this->task->id,
            'category' => 'lainnya',
            'category_custom_name' => 'Pasang Repeater',
            'is_billable' => true,
            'billing_note' => 'Pasang repeater tambahan, Rp150.000.',
            'verification_status' => CReqVerificationStatus::PENDING->value,
        ]);
    }

    #[Test]
    public function helpdesk_melihat_antrean_pending(): void
    {
        $response = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.index'));

        $response->assertOk();
        $response->assertSee($this->task->task_number);
    }

    #[Test]
    public function role_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);
        $teknisi->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $response = $this->actingAs($teknisi)->get(route('tasks.creq-billing.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function approve_menandai_verified_dan_redirect_ke_tagihan_manual_prefill(): void
    {
        $response = $this->actingAs($this->helpdesk)
            ->put(route('tasks.creq-billing.approve', $this->task));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/invoices/create', $location);
        $this->assertStringContainsString('customer_id='.$this->customer->id, $location);
        $this->assertStringContainsString('manual_category=lainnya', $location);

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::VERIFIED, $this->detail->verification_status);
        $this->assertSame($this->helpdesk->id, $this->detail->verified_by);
        $this->assertNotNull($this->detail->verified_at);
    }

    #[Test]
    public function approve_dua_kali_ditolak(): void
    {
        $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $this->task));

        $response = $this->actingAs($this->helpdesk)
            ->put(route('tasks.creq-billing.approve', $this->task));

        $response->assertStatus(422);
    }

    #[Test]
    public function reject_wajib_alasan(): void
    {
        $response = $this->actingAs($this->helpdesk)
            ->put(route('tasks.creq-billing.reject', $this->task), []);

        $response->assertSessionHasErrors('reason');
        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::PENDING, $this->detail->verification_status);
    }

    #[Test]
    public function reject_dengan_alasan_menandai_rejected(): void
    {
        $response = $this->actingAs($this->helpdesk)
            ->put(route('tasks.creq-billing.reject', $this->task), [
                'reason' => 'Biaya tidak sesuai, sudah termasuk paket berlangganan.',
            ]);

        $response->assertRedirect(route('tasks.creq-billing.index'));

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::REJECTED, $this->detail->verification_status);
        $this->assertSame('Biaya tidak sesuai, sudah termasuk paket berlangganan.', $this->detail->rejection_reason);
    }

    #[Test]
    public function task_creq_tidak_berbayar_tidak_bisa_diakses_lewat_verifikasi(): void
    {
        $nonBillableTask = Task::create([
            'task_number' => 'TASK-CREQV-0002',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::CREQ->value,
            'title' => 'Customer Request Tidak Berbayar',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => now(),
            'created_by' => $this->helpdesk->id,
            'updated_by' => $this->helpdesk->id,
        ]);
        TaskCreqDetail::create([
            'task_id' => $nonBillableTask->id,
            'category' => 'lainnya',
            'category_custom_name' => 'Cek Redaman',
            'is_billable' => false,
            'verification_status' => CReqVerificationStatus::PENDING->value,
        ]);

        $response = $this->actingAs($this->helpdesk)
            ->get(route('tasks.creq-billing.show', $nonBillableTask));

        $response->assertNotFound();
    }
}
