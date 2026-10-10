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
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan MTN polos (bukan C-REQ) — checkbox "Task ini berbayar" di luar
 * blok `$isCreq`, bikin baris `TaskMaintenanceBillingDetail` (beda tabel
 * dari `TaskCreqDetail`) kalau ditandai berbayar. Setup disalin dari
 * `TaskCreqBillingReportTest` supaya seeders & payload dasar konsisten.
 *
 * .superpowers/sdd/2026-10-10-verifikasi-biaya-creq-mtn-breakdown/task-2-brief.md
 */
class TaskMaintenanceBillingDetailReportTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'MTN-TEST', 'pop_code' => 'MTX', 'registration_prefix' => 'M', 'cid_prefix' => 'E', 'name' => 'Cabang MTN Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-MTN-001',
            'full_name' => 'Pelanggan MTN',
            'primary_phone' => '0812340010',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);
    }

    private function makeMtnTask(): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-MTN-'.random_int(1000, 9999),
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        return $task;
    }

    private function basePayload(): array
    {
        return [
            'kendala_teknis' => 'Perbaikan jaringan pelanggan di lokasi.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
        ];
    }

    #[Test]
    public function mtn_berbayar_wajib_catatan_biaya_dan_masuk_antrean(): void
    {
        $task = $this->makeMtnTask();

        $gagal = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + ['mtn_is_billable' => '1']);
        $gagal->assertSessionHasErrors('mtn_billing_note');

        $ok = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'mtn_is_billable' => '1',
                'mtn_billing_note' => 'Ganti konektor rusak akibat kelalaian pelanggan, dikenakan biaya.',
            ]);

        $ok->assertSessionHasNoErrors();
        $detail = $task->fresh()->maintenanceBillingDetail;
        $this->assertTrue($detail->is_billable);
        $this->assertSame(CReqVerificationStatus::PENDING, $detail->verification_status);
    }

    #[Test]
    public function mtn_tidak_berbayar_tidak_bikin_baris_billing_detail(): void
    {
        $task = $this->makeMtnTask();

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), $this->basePayload());

        $response->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->maintenanceBillingDetail);
    }
}
