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
 * Task (kerjaan lapangan teknisi) vs keputusan bisnis (customer
 * diterima/ditolak) itu 2 hal beda, sengaja gak dicampur. Sejak unifikasi
 * enum (2026-07-20), FopTask.status share vocab persis TaskStatus — begitu
 * Task.status=selesai, FopTask SELALU 'selesai' (task_type apapun), nasib
 * customer (approved/pending/rejected) & nuansa "perlu review" cuma jadi
 * label histori/badge overlay (`FopTask::verificationStatus()` +
 * `FopTaskStatusHistory::label()`), gak pernah ngubah status utama. Lihat
 * docs/project_verifikasi_reject_gap.md & docs/project_status_label_unifikasi.md.
 */
class FopTaskVerificationOverlayTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;
    protected \App\Models\User $adminUser;

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

        $this->adminUser = $this->loginAsAdmin();
    }

    /**
     * Bikin Task masih `in_progress` + FopTask terkait (in_progress), lalu return
     * keduanya BELUM di-transisi ke selesai — caller yang manggil
     * `$task->update([...])` biar observer bener-bener ke-trigger (observer
     * cuma listen event `updated`, `create()` gak fire dia).
     */
    protected function makeTaskWithFopTask(?Customer $customer, TaskType $taskType, string $taskNumber): array
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'pop_id' => $this->pop->id,
            'customer_id' => $customer?->id,
            'task_type' => $taskType->value,
            'title' => $taskType->value . ' ' . $taskNumber,
            'status' => 'in_progress',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-' . $taskNumber,
            'category' => $taskType->value,
            'task_id' => $task->id,
            'tugas' => $task->title,
            'customer_id' => $customer?->id,
            'issue' => 'issue',
            'status' => TaskStatus::IN_PROGRESS->value,
            'priority' => 'low',
        ]);

        return [$task, $fopTask];
    }

    public function test_pemasangan_task_selesai_pending_review_maps_to_selesai_bucket(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-PSB-001',
            'full_name' => 'Perlu Verifikasi Customer',
            'phone' => '081200000010',
            'status' => 'verification_admin',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        [$task, $fopTask] = $this->makeTaskWithFopTask($customer, TaskType::PEMASANGAN, 'TASK-PSB-0001');
        $task->update(['status' => TaskStatus::SELESAI->value, 'fop_review_status' => 'pending']);

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $fopTask->status);
        $this->assertEquals('pending', $fopTask->verificationStatus());

        $latestHistory = $fopTask->statusHistories()->first();
        $this->assertEquals('selesai_menunggu_verifikasi', $latestHistory->to_status);
    }

    public function test_mtn_task_selesai_pending_review_stays_in_selesai_bucket(): void
    {
        [$task, $fopTask] = $this->makeTaskWithFopTask(null, TaskType::MAINTENANCE, 'TASK-MTN-0001');
        $task->update(['status' => TaskStatus::SELESAI->value, 'fop_review_status' => 'pending']);

        $fopTask->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $fopTask->status);
        $this->assertNull($fopTask->verificationStatus());

        $latestHistory = $fopTask->statusHistories()->first();
        $this->assertEquals('selesai_menunggu_verifikasi', $latestHistory->to_status);
    }

    public function test_selesai_pemasangan_task_shows_in_riwayat_regardless_of_review_outcome(): void
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-PSB-002',
            'full_name' => 'Riwayat Customer',
            'phone' => '081200000011',
            'status' => 'verification_admin',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        [$task, $fopTask] = $this->makeTaskWithFopTask($customer, TaskType::PEMASANGAN, 'TASK-PSB-0002');
        $task->update(['status' => TaskStatus::SELESAI->value, 'fop_review_status' => 'pending']);

        // UI Riwayat gak nampilin badge/filter "Verifikasi" terpisah (di-drop
        // biar gak ambigu) — cukup pastiin tiketnya nongol di Riwayat sebagai
        // baris biasa, titik. `tugas` yang dipakai buat cek karena itu yang
        // beneran ditampilin di kolom tabel (task_number gak pernah dirender).
        $response = $this->get(route('fop-tasks.history'));
        $response->assertOk();
        $response->assertSee($fopTask->fresh()->tugas);

        // Task ini tetap gak nangkring di antrian aktif (naturally excluded,
        // gak butuh query khusus — Selesai emang gak pernah masuk whereNotIn(selesai,dibatalkan)).
        $activeResponse = $this->get(route('fop-tasks.index'));
        $activeResponse->assertOk();
        $activeResponse->assertDontSee($fopTask->fresh()->tugas);
    }
}
