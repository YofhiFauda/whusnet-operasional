<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regression: SLA Deadline khusus SURVEY (semua paket) = akhir hari jadwal
 * (23:59:59), bukan started_at + sla_minutes tetap. Laporan survey wajib
 * diinput hari itu juga, gak peduli jam berapa dijadwalkan/dimulai — kecuali
 * task di-pending/ditolak.
 */
class TaskSurveySlaDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected User $tech;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->tech = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeSurveyTask(Carbon $scheduledAt, ?Carbon $startedAt = null): Task
    {
        return Task::create([
            'task_number' => 'TASK-'.uniqid(),
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Pelanggan',
            'status' => $startedAt ? TaskStatus::IN_PROGRESS->value : TaskStatus::TERJADWAL->value,
            'scheduled_at' => $scheduledAt,
            'started_at' => $startedAt,
            'sla_minutes' => TaskType::SURVEY->slaMinutes(),
            'created_by' => $this->tech->id,
            'updated_by' => $this->tech->id,
        ]);
    }

    /**
     * Skenario 1 dari user: FOP jadwalkan 19 Agustus jam 09:00 (dibuat malam
     * sebelumnya 18 Agustus 22:00) → deadline 19 Agustus 23:59:59, BUKAN
     * 19 Agustus 09:00 + 120 menit (11:00).
     */
    public function test_survey_deadline_is_end_of_scheduled_day_not_fixed_duration(): void
    {
        $scheduledAt = Carbon::parse('2026-08-19 09:00:00');
        $task = $this->makeSurveyTask($scheduledAt, startedAt: Carbon::parse('2026-08-19 09:15:00'));

        $deadline = $task->slaDeadline();

        $this->assertNotNull($deadline);
        $this->assertTrue($deadline->isSameDay($scheduledAt));
        $this->assertSame('2026-08-19 23:59:59', $deadline->toDateTimeString());
    }

    /**
     * Skenario 2 dari user: dijadwalkan & dibuat di hari yang sama (20 Agustus
     * 07:00 → jadwal 20 Agustus 09:30) → deadline tetap 20 Agustus 23:59:59.
     */
    public function test_survey_deadline_same_day_schedule_still_ends_at_23_59(): void
    {
        $scheduledAt = Carbon::parse('2026-08-20 09:30:00');
        $task = $this->makeSurveyTask($scheduledAt, startedAt: Carbon::parse('2026-08-20 09:45:00'));

        $this->assertSame('2026-08-20 23:59:59', $task->slaDeadline()->toDateTimeString());
    }

    public function test_survey_not_over_sla_before_end_of_scheduled_day(): void
    {
        $scheduledAt = Carbon::parse('2026-08-19 09:00:00');
        $task = $this->makeSurveyTask($scheduledAt, startedAt: Carbon::parse('2026-08-19 09:15:00'));

        Carbon::setTestNow(Carbon::parse('2026-08-19 23:00:00'));
        $this->assertFalse($task->isOverSla());
    }

    public function test_survey_over_sla_once_scheduled_day_passes_even_if_duration_under_sla_minutes(): void
    {
        $scheduledAt = Carbon::parse('2026-08-19 09:00:00');
        // Mulai jam 22:30 — durasi kerja jauh di bawah 120 menit SLA lama,
        // tapi begitu lewat tengah malam tetap wajib dianggap telat.
        $task = $this->makeSurveyTask($scheduledAt, startedAt: Carbon::parse('2026-08-19 22:30:00'));

        Carbon::setTestNow(Carbon::parse('2026-08-20 00:05:00'));
        $this->assertTrue($task->isOverSla());
    }

    /**
     * Klarifikasi user: SLA-nya "berjalan" sesuai jam jadwal, bukan jam
     * teknisi tekan Mulai. slaWindowStart() (dipakai buat budget countdown)
     * harus ikut scheduled_at walau task belum di-start (masih terjadwal).
     */
    public function test_survey_sla_window_starts_from_scheduled_at_not_started_at(): void
    {
        $scheduledAt = Carbon::parse('2026-08-19 09:00:00');
        $task = $this->makeSurveyTask($scheduledAt); // belum di-start, masih terjadwal

        $this->assertSame('2026-08-19 09:00:00', $task->slaWindowStart()->toDateTimeString());

        // Walau nanti teknisi baru mulai jam 09:15, window start tetap 09:00 (jam jadwal).
        $task->update(['started_at' => Carbon::parse('2026-08-19 09:15:00')]);
        $task->refresh();
        $this->assertSame('2026-08-19 09:00:00', $task->slaWindowStart()->toDateTimeString());
    }

    public function test_non_survey_task_type_still_uses_started_at_plus_sla_minutes(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-'.uniqid(),
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance',
            'status' => TaskStatus::IN_PROGRESS->value,
            'scheduled_at' => Carbon::parse('2026-08-19 09:00:00'),
            'started_at' => Carbon::parse('2026-08-19 09:00:00'),
            'sla_minutes' => 180,
            'created_by' => $this->tech->id,
            'updated_by' => $this->tech->id,
        ]);

        $this->assertSame('2026-08-19 12:00:00', $task->slaDeadline()->toDateTimeString());
        $this->assertSame('2026-08-19 09:00:00', $task->slaWindowStart()->toDateTimeString());
    }
}
