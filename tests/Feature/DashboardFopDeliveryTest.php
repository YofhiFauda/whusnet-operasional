<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Owner Fase 4 (docs/plan/analisa-dashboard-owner-statistik.md
 * §6, Pilar 5): Efisiensi Delivery Lapangan (FOP).
 */
class DashboardFopDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->pop = Pop::create([
            'code' => 'FOP4', 'pop_code' => 'FOP4', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Dashboard Fase4', 'type' => 'cabang', 'status' => 'active',
        ]);

        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);
    }

    /**
     * `created_at` BUKAN fillable di `Task` (dijaga sengaja, cuma
     * `updated_at`/`created_by` yang boleh diisi manual) — Eloquent diam-diam
     * membuang key itu dari `create()` dan menimpanya dengan `now()`. Wajib
     * `forceFill()` + `save()` terpisah kalau test butuh `created_at` masa
     * lalu (buat simulasi antrean overdue / lead time).
     */
    private function makeTask(string $number, TaskType $type, array $extra = []): Task
    {
        $createdAt = $extra['created_at'] ?? null;
        unset($extra['created_at']);

        $task = Task::create(array_merge([
            'task_number' => $number,
            'pop_id' => $this->pop->id,
            'task_type' => $type->value,
            'title' => 'Task '.$number,
            'status' => TaskStatus::TERJADWAL->value,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ], $extra));

        if ($createdAt !== null) {
            $task->forceFill(['created_at' => $createdAt])->save();
        }

        return $task;
    }

    public function test_survey_dan_psb_selesai_dihitung_dari_completed_at_pada_periode(): void
    {
        $this->makeTask('FOP4-SRV-1', TaskType::SURVEY, [
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => '2026-06-10',
            'created_at' => '2026-06-01',
        ]);

        // Survey selesai TAPI di luar periode filter — jangan ikut kehitung.
        $this->makeTask('FOP4-SRV-2', TaskType::SURVEY, [
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => '2026-01-10',
            'created_at' => '2026-01-01',
        ]);

        $this->makeTask('FOP4-PSB-1', TaskType::PEMASANGAN, [
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => '2026-06-15',
            'created_at' => '2026-06-01',
        ]);

        $stats = $this->actingAs($this->owner)
            ->get('/?period_from=2026-06&period_to=2026-06')
            ->viewData('stats');

        $this->assertSame(1, $stats['fop_survey_completed_count']);
        $this->assertSame(1, $stats['fop_psb_completed_count']);
    }

    /**
     * Lead time = rata-rata `completed_at` − `created_at` (dalam hari),
     * dihitung PHP per baris — bukan agregat SQL vendor-specific.
     */
    public function test_lead_time_psb_dihitung_rata_rata_dari_created_at_ke_completed_at(): void
    {
        $this->makeTask('FOP4-PSB-A', TaskType::PEMASANGAN, [
            'status' => TaskStatus::SELESAI->value,
            'created_at' => '2026-06-01 00:00:00',
            'completed_at' => '2026-06-03 00:00:00', // 2 hari
        ]);

        $this->makeTask('FOP4-PSB-B', TaskType::PEMASANGAN, [
            'status' => TaskStatus::SELESAI->value,
            'created_at' => '2026-06-01 00:00:00',
            'completed_at' => '2026-06-05 00:00:00', // 4 hari
        ]);

        $stats = $this->actingAs($this->owner)
            ->get('/?period_from=2026-06&period_to=2026-06')
            ->viewData('stats');

        $this->assertSame(3.0, $stats['fop_psb_avg_lead_time_days']);
    }

    public function test_lead_time_null_saat_belum_ada_psb_selesai(): void
    {
        $stats = $this->actingAs($this->owner)
            ->get('/?period_from=2019-01&period_to=2019-01')
            ->viewData('stats');

        $this->assertNull($stats['fop_psb_avg_lead_time_days']);
    }

    /**
     * Overdue dihitung dari TASK yang masih di antrean (belum selesai/
     * dibatalkan) dan `created_at`-nya sudah lewat ambang — BUKAN dari
     * periode filter (ini snapshot kondisi sekarang, sama seperti
     * "Tagihan Jatuh Tempo"/breach SLA tiket).
     */
    public function test_overdue_survey_dan_psb_dihitung_dari_status_masih_antre(): void
    {
        $this->makeTask('FOP4-OSRV-OVERDUE', TaskType::SURVEY, [
            'status' => TaskStatus::TERJADWAL->value,
            'created_at' => now()->subDays(2),
        ]);

        $this->makeTask('FOP4-OSRV-BARU', TaskType::SURVEY, [
            'status' => TaskStatus::TERJADWAL->value,
            'created_at' => now()->subHours(2),
        ]);

        $this->makeTask('FOP4-OPSB-OVERDUE', TaskType::PEMASANGAN, [
            'status' => TaskStatus::PENDING->value,
            'created_at' => now()->subDays(4),
        ]);

        // Sudah selesai — TIDAK boleh ikut kehitung overdue walau created_at lama.
        $this->makeTask('FOP4-OPSB-SELESAI', TaskType::PEMASANGAN, [
            'status' => TaskStatus::SELESAI->value,
            'created_at' => now()->subDays(10),
            'completed_at' => now(),
        ]);

        $stats = $this->actingAs($this->owner)->get('/')->viewData('stats');

        $this->assertSame(1, $stats['fop_overdue_survey_count']);
        $this->assertSame(1, $stats['fop_overdue_psb_count']);
    }

    /**
     * `teknisi` punya `dashboard.view` TAPI TIDAK punya `task.view.all` —
     * blok Efisiensi Delivery Lapangan harus gak nongol buat dia.
     */
    public function test_user_tanpa_task_view_all_tidak_melihat_blok_fop_delivery(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $response = $this->actingAs($teknisi)->get('/');

        $response->assertOk();
        $this->assertArrayNotHasKey('fop_survey_completed_count', $response->viewData('stats'));
        $response->assertDontSee('Efisiensi Delivery Lapangan', false);
    }
}
