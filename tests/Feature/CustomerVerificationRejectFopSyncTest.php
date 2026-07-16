<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi buat gap "task ditolak nyangkut di antrian FOP aktif" — lihat
 * docs/project_verifikasi_reject_gap.md. Sebelum fix, TaskObserver nganggep
 * Task SELESAI+fop_review_status=rejected sama kayak SELESAI+pending (dua-
 * duanya => FopTaskStatus::PROSES), jadi task ditolak gak pernah keluar dari
 * antrian aktif atau masuk Riwayat.
 *
 * Desain final: Task (kerjaan lapangan) vs keputusan bisnis (customer
 * diterima/ditolak) itu 2 hal beda — begitu `Task.status=selesai`, FopTask
 * SELALU `Selesai` (kerjaan teknisi sukses), gak peduli customer-nya nanti
 * diterima/ditolak. Nasib customer cuma beda di label histori granular
 * (`selesai_menunggu_verifikasi`/`selesai_ditolak_verifikasi`), BUKAN bucket
 * status utama.
 */
class CustomerVerificationRejectFopSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function makeTaskWithFopTask(Customer $customer, TaskType $taskType, string $taskNumber): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'task_type' => $taskType->value,
            'title' => $taskType->value . ' ' . $customer->full_name,
            'status' => TaskStatus::SELESAI->value,
            'fop_review_status' => 'pending',
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        FopTask::create([
            'task_number' => 'TFOP-' . $taskNumber,
            'category' => $taskType->value,
            'task_id' => $task->id,
            'tugas' => $task->title,
            'customer_id' => $customer->id,
            'issue' => 'PSB',
            'status' => TaskStatus::IN_PROGRESS->value,
            'priority' => 'low',
        ]);

        return $task;
    }

    public function test_reject_at_survey_stage_moves_fop_task_to_cancel_not_stuck_in_proses(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::create([
            'customer_code' => 'CUST-SURV-001',
            'full_name' => 'Survey Reject Customer',
            'phone' => '081200000001',
            'status' => 'surveyed',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $surveyTask = $this->makeTaskWithFopTask($customer, TaskType::SURVEY, 'TASK-SURV-0001');

        $response = $this->post(route('customers.verification.reject', $customer->id), [
            'reason' => 'Alamat tidak valid',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertEquals('rejected', $customer->status);

        $surveyTask->refresh();
        $this->assertEquals('rejected', $surveyTask->fop_review_status);
        $this->assertEquals('Alamat tidak valid', $surveyTask->reject_reason);
        $this->assertEquals(TaskStatus::SELESAI->value, $surveyTask->status->value);

        $fopTask = FopTask::where('task_id', $surveyTask->id)->firstOrFail();
        $this->assertEquals(TaskStatus::SELESAI, $fopTask->status);
        $this->assertEquals('rejected', $fopTask->verificationStatus());

        $latestHistory = $fopTask->statusHistories()->first();
        $this->assertNotNull($latestHistory);
        $this->assertEquals('selesai_ditolak_verifikasi', $latestHistory->to_status);
    }

    public function test_reject_at_install_stage_targets_pemasangan_task_not_survey_task(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::create([
            'customer_code' => 'CUST-INST-001',
            'full_name' => 'Install Reject Customer',
            'phone' => '081200000002',
            'status' => 'verification_admin',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        // Survey sudah lama approved sebelumnya — reject() di tahap install TIDAK
        // boleh nyentuh ini lagi.
        $surveyTask = $this->makeTaskWithFopTask($customer, TaskType::SURVEY, 'TASK-SURV-0002');
        $surveyTask->update(['fop_review_status' => 'approved']);

        $installTask = $this->makeTaskWithFopTask($customer, TaskType::PEMASANGAN, 'TASK-INST-0002');

        $response = $this->post(route('customers.verification.reject', $customer->id), [
            'reason' => 'Pelanggan belum melunasi pembayaran',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertEquals('rejected', $customer->status);

        // Task Pemasangan yang harus ke-reject
        $installTask->refresh();
        $this->assertEquals('rejected', $installTask->fop_review_status);
        $this->assertEquals('Pelanggan belum melunasi pembayaran', $installTask->reject_reason);

        $installFopTask = FopTask::where('task_id', $installTask->id)->firstOrFail();
        $this->assertEquals(TaskStatus::SELESAI, $installFopTask->status);
        $this->assertEquals('rejected', $installFopTask->verificationStatus());

        // Task Survey tetap approved, gak ke-overwrite
        $surveyTask->refresh();
        $this->assertEquals('approved', $surveyTask->fop_review_status);

        $surveyFopTask = FopTask::where('task_id', $surveyTask->id)->firstOrFail();
        $this->assertEquals(TaskStatus::SELESAI, $surveyFopTask->status);
    }
}
