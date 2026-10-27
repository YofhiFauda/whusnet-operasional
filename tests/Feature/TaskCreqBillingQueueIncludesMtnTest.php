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
use App\Models\TaskMaintenanceBillingDetail;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
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
 * Antrean "Verifikasi Biaya C-REQ" (`TaskCreqBillingController`) sekarang
 * juga menampung task MTN polos berbayar (`TaskMaintenanceBillingDetail`,
 * lihat Task 1-2 dalam
 * docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md). Controller
 * & route-nya TETAP "creq-billing" — nama itu sudah diputuskan tidak diganti.
 *
 * Fokus risiko tertinggi di sini: query gabungan dua tabel detail (C-REQ &
 * MTN) tidak boleh bocor status antar tipe — filter `status=verified` tidak
 * boleh ikut menampilkan task C-REQ yang masih pending hanya karena join
 * salah sasaran (riwayat bug sejenis: ADHOC-144).
 */
class TaskCreqBillingQueueIncludesMtnTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $helpdesk;

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

        $this->pop = Pop::create([
            'code' => 'MTNBILL-TEST',
            'pop_code' => 'MTB',
            'registration_prefix' => 'M',
            'cid_prefix' => 'E',
            'name' => 'Cabang Verifikasi MTN',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-MTNBILL-001',
            'full_name' => 'Pelanggan Verifikasi MTN',
            'primary_phone' => '0812340020',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $this->helpdesk = User::factory()->create(['role_id' => $helpdeskRole->id]);
        $this->helpdesk->roleScopes()->create(['role_id' => $helpdeskRole->id, 'scope_type' => ScopeType::ALL_POP]);
    }

    private function buatTaskCreq(string $taskNumber, CReqVerificationStatus $status, bool $isBillable = true): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::CREQ->value,
            'title' => 'Customer Request Verifikasi Test',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => now(),
            'created_by' => $this->helpdesk->id,
            'updated_by' => $this->helpdesk->id,
        ]);

        TaskCreqDetail::create([
            'task_id' => $task->id,
            'category' => 'lainnya',
            'category_custom_name' => 'Pasang Repeater',
            'is_billable' => $isBillable,
            'billing_note' => 'Pasang repeater tambahan, Rp150.000.',
            'verification_status' => $status->value,
        ]);

        return $task;
    }

    private function buatTaskMtn(string $taskNumber, ?CReqVerificationStatus $status, bool $isBillable = true, ?Pop $pop = null): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'customer_id' => $this->customer->id,
            'pop_id' => ($pop ?? $this->pop)->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Test',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => now(),
            'created_by' => $this->helpdesk->id,
            'updated_by' => $this->helpdesk->id,
        ]);

        if ($status !== null) {
            TaskMaintenanceBillingDetail::create([
                'task_id' => $task->id,
                'is_billable' => $isBillable,
                'billing_note' => 'Ganti kabel drop core, Rp50.000.',
                'verification_status' => $status->value,
            ]);
        }

        return $task;
    }

    #[Test]
    public function antrean_tampilkan_task_creq_dan_mtn_berbayar_sekaligus(): void
    {
        $creqTask = $this->buatTaskCreq('TASK-MTNBILL-0001', CReqVerificationStatus::PENDING);
        $mtnTask = $this->buatTaskMtn('TASK-MTNBILL-0002', CReqVerificationStatus::PENDING);
        $mtnTidakBerbayar = $this->buatTaskMtn('TASK-MTNBILL-0003', null);

        $response = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.index', ['status' => 'all']));

        $response->assertOk();
        $response->assertSee($creqTask->task_number);
        $response->assertSee($mtnTask->task_number);
        $response->assertDontSee($mtnTidakBerbayar->task_number);
    }

    #[Test]
    public function filter_status_verified_tidak_nyampur_antar_tipe(): void
    {
        $creqPending = $this->buatTaskCreq('TASK-MTNBILL-0004', CReqVerificationStatus::PENDING);
        $mtnVerified = $this->buatTaskMtn('TASK-MTNBILL-0005', CReqVerificationStatus::VERIFIED);

        $response = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.index', ['status' => 'verified']));

        $response->assertOk();
        $response->assertSee($mtnVerified->task_number);
        $response->assertDontSee($creqPending->task_number);
    }

    #[Test]
    public function filter_status_pending_tidak_nyampur_antar_tipe(): void
    {
        $creqPending = $this->buatTaskCreq('TASK-MTNBILL-0006', CReqVerificationStatus::PENDING);
        $mtnVerified = $this->buatTaskMtn('TASK-MTNBILL-0007', CReqVerificationStatus::VERIFIED);

        $response = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.index', ['status' => 'pending']));

        $response->assertOk();
        $response->assertSee($creqPending->task_number);
        $response->assertDontSee($mtnVerified->task_number);
    }

    #[Test]
    public function status_counts_sum_benar_lintas_tipe(): void
    {
        // C-REQ: 2 pending + 1 verified
        $this->buatTaskCreq('TASK-MTNBILL-0010', CReqVerificationStatus::PENDING);
        $this->buatTaskCreq('TASK-MTNBILL-0011', CReqVerificationStatus::PENDING);
        $this->buatTaskCreq('TASK-MTNBILL-0012', CReqVerificationStatus::VERIFIED);

        // MTN: 1 pending + 1 rejected
        $this->buatTaskMtn('TASK-MTNBILL-0013', CReqVerificationStatus::PENDING);
        $this->buatTaskMtn('TASK-MTNBILL-0014', CReqVerificationStatus::REJECTED);

        $response = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.index', ['status' => 'all']));

        $response->assertOk();
        $statusCounts = $response->viewData('statusCounts');

        $this->assertSame(3, (int) $statusCounts->pending_count);
        $this->assertSame(1, (int) $statusCounts->verified_count);
        $this->assertSame(1, (int) $statusCounts->rejected_count);
        $this->assertSame(5, (int) $statusCounts->total_count);
    }

    #[Test]
    public function show_task_mtn_berbayar_lolos_otorisasi_pop_scope(): void
    {
        $popLain = Pop::create([
            'code' => 'MTNBILL-LAIN',
            'pop_code' => 'MTL',
            'registration_prefix' => 'L',
            'cid_prefix' => 'F',
            'name' => 'Cabang Lain',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $mtnTask = $this->buatTaskMtn('TASK-MTNBILL-0020', CReqVerificationStatus::PENDING, true, $popLain);

        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $helpdeskPopLain = User::factory()->create(['role_id' => $helpdeskRole->id]);
        $scope = UserRoleScope::create([
            'user_id' => $helpdeskPopLain->id,
            'role_id' => $helpdeskRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        $response = $this->actingAs($helpdeskPopLain)->get(route('tasks.creq-billing.show', $mtnTask));

        $response->assertForbidden();
    }

    #[Test]
    public function index_task_mtn_verified_tampilkan_status_benar_bukan_label_creq(): void
    {
        // Sebelum perbaikan ini, index.blade.php selalu baca $task->creqDetail
        // buat badge status & kolom kategori — task MTN (yang detailnya ada
        // di tabel LAIN, task_maintenance_billing_details) ikut tampil
        // "Menunggu Verifikasi" walau sudah verified, dan kategori "C-REQ"
        // walau bukan C-REQ sama sekali.
        $mtnTask = $this->buatTaskMtn('TASK-MTNBILL-0030', CReqVerificationStatus::VERIFIED);

        $response = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.index', ['status' => 'all']));

        $response->assertOk();
        $response->assertSee('Diverifikasi');

        // Baris task ini sendiri harus TIDAK memuat status badge "Menunggu
        // Verifikasi" — label itu boleh muncul di tempat lain di halaman
        // (mis. judul kartu metrik statis), jadi assertDontSee() atas
        // seluruh halaman akan selalu gagal dan bukan itu yang diuji di
        // sini. Validasi langsung ke potongan HTML di sekitar task_number.
        $content = $response->getContent();
        $taskPos = strpos($content, $mtnTask->task_number);
        $this->assertNotFalse($taskPos);
        $rowSlice = substr($content, $taskPos, 6000);
        $this->assertStringContainsString('Diverifikasi', $rowSlice);
        $this->assertStringNotContainsString('Menunggu Verifikasi', $rowSlice);
        $this->assertStringNotContainsString('>C-REQ<', $rowSlice);
    }
}
