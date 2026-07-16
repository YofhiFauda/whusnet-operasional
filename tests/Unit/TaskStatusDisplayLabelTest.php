<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use PHPUnit\Framework\TestCase;

/**
 * TaskStatus::displayLabel() — satu-satunya sumber label yang dilihat user,
 * dipake seragam di /tasks-saya, /fop-tasks, /fop-tasks/history. Lihat
 * docs/project_status_label_unifikasi.md § DESAIN FINAL.
 */
class TaskStatusDisplayLabelTest extends TestCase
{
    public function test_pending_without_report_deferred_shows_pending(): void
    {
        $this->assertEquals('Pending', TaskStatus::PENDING->displayLabel(false));
    }

    public function test_pending_with_report_deferred_shows_lapor_nanti(): void
    {
        $this->assertEquals('Lapor Nanti', TaskStatus::PENDING->displayLabel(true));
    }

    public function test_selesai_always_shows_selesai_regardless_of_report_deferred_flag(): void
    {
        // report_deferred cuma relevan buat PENDING — SELESAI harus tetep "Selesai"
        // gak peduli argumennya apa (flag ini gak relevan buat status lain).
        $this->assertEquals('Selesai', TaskStatus::SELESAI->displayLabel(false));
        $this->assertEquals('Selesai', TaskStatus::SELESAI->displayLabel(true));
    }

    public function test_in_progress_label_is_indonesian_not_english(): void
    {
        $this->assertEquals('Sedang Dikerjakan', TaskStatus::IN_PROGRESS->displayLabel());
        $this->assertEquals('Sedang Dikerjakan', TaskStatus::IN_PROGRESS->label());
    }

    public function test_passthrough_statuses_match_label(): void
    {
        foreach ([TaskStatus::DRAFT, TaskStatus::TERJADWAL, TaskStatus::DIBATALKAN] as $status) {
            $this->assertEquals($status->label(), $status->displayLabel());
        }
    }

    public function test_reschedule_case_no_longer_exists(): void
    {
        $this->assertNull(TaskStatus::tryFrom('reschedule'));
    }

    public function test_pending_is_not_editable(): void
    {
        $this->assertFalse(TaskStatus::PENDING->isEditable());
    }
}
