<?php

namespace Tests\Unit;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi — `started_via`/`started_distance_meters` dari absen QR udah
 * tersimpan sejak Fase 3 QR Attendance tapi gak pernah ditampilkan di mana
 * pun (ADHOC audit dashboard, 2026-10-12). `Task::attendanceFlag()` baru
 * (bucket ulang jarak yang SUDAH tersimpan terhadap config threshold yang
 * sama, BUKAN recompute live terhadap koordinat pelanggan sekarang).
 */
class TaskAttendanceFlagTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function null_kalau_task_tidak_pernah_dimulai_lewat_qr_scan(): void
    {
        $task = new Task(['started_via' => null]);

        $this->assertNull($task->attendanceFlag());
    }

    #[Test]
    public function tanpa_koordinat_kalau_qr_scan_tapi_jarak_tidak_tersimpan(): void
    {
        $task = new Task(['started_via' => 'qr_scan', 'started_distance_meters' => null]);

        $this->assertSame('tanpa_koordinat', $task->attendanceFlag());
    }

    #[Test]
    public function ok_kalau_jarak_dalam_radius_normal(): void
    {
        $task = new Task(['started_via' => 'qr_scan', 'started_distance_meters' => 50]);

        $this->assertSame('ok', $task->attendanceFlag());
    }

    #[Test]
    public function perlu_review_kalau_jarak_antara_radius_normal_dan_hard_limit(): void
    {
        $task = new Task(['started_via' => 'qr_scan', 'started_distance_meters' => 300]);

        $this->assertSame('perlu_review', $task->attendanceFlag());
    }

    #[Test]
    public function out_of_radius_kalau_jarak_melewati_hard_limit(): void
    {
        $task = new Task(['started_via' => 'qr_scan', 'started_distance_meters' => 1000]);

        $this->assertSame('out_of_radius', $task->attendanceFlag());
    }
}
