<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * TaskStatus — label yang dilihat user + dua penentu perilaku Lapor Nanti
 * (`acceptsReport()`, `isLockedFromFop()`). Lapor Nanti status sendiri sejak
 * 2026-09-26, bukan lagi `pending` + flag.
 */
class TaskStatusDisplayLabelTest extends TestCase
{
    public function test_pending_and_lapor_nanti_have_different_labels(): void
    {
        $this->assertEquals('Pending', TaskStatus::PENDING->label());
        $this->assertEquals('Lapor Nanti', TaskStatus::LAPOR_NANTI->label());
    }

    public function test_in_progress_label_is_indonesian_not_english(): void
    {
        $this->assertEquals('Sedang Dikerjakan', TaskStatus::IN_PROGRESS->label());
    }

    public function test_reschedule_case_no_longer_exists(): void
    {
        $this->assertNull(TaskStatus::tryFrom('reschedule'));
    }

    public function test_pending_and_lapor_nanti_are_not_editable(): void
    {
        $this->assertFalse(TaskStatus::PENDING->isEditable());
        $this->assertFalse(TaskStatus::LAPOR_NANTI->isEditable());
    }

    /**
     * Seluruh case wajib diputuskan — kalau nambah case baru dan lupa
     * memetakan di acceptsReport()/isLockedFromFop(), `match` tanpa default
     * meledak di sini, bukan diam-diam di produksi.
     */
    #[DataProvider('everyStatus')]
    public function test_every_status_has_explicit_report_and_lock_decision(TaskStatus $status): void
    {
        $this->assertIsBool($status->acceptsReport());
        $this->assertIsBool($status->isLockedFromFop());
        $this->assertNotSame('', $status->label());
        $this->assertNotSame('', $status->displayBadgeClasses());
    }

    public static function everyStatus(): array
    {
        return array_combine(
            array_map(fn (TaskStatus $s) => $s->value, TaskStatus::cases()),
            array_map(fn (TaskStatus $s) => [$s], TaskStatus::cases())
        );
    }

    public function test_only_in_progress_and_lapor_nanti_accept_report(): void
    {
        $this->assertEqualsCanonicalizing(
            [TaskStatus::IN_PROGRESS->value, TaskStatus::LAPOR_NANTI->value],
            TaskStatus::reportableValues()
        );

        // Pending = kerja berhenti & balik ke antrian FOP — bukan tempat lapor.
        $this->assertFalse(TaskStatus::PENDING->acceptsReport());
    }

    public function test_only_lapor_nanti_is_locked_from_fop(): void
    {
        $locked = array_filter(TaskStatus::cases(), fn (TaskStatus $s) => $s->isLockedFromFop());

        $this->assertSame([TaskStatus::LAPOR_NANTI], array_values($locked));
    }
}
