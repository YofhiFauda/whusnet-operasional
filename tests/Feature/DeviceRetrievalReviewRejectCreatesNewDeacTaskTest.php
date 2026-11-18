<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalReviewStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Services\DeviceRetrievalReviewService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * K2: tolak → FopTask DEAC BARU (Draft), tanpa batas atas. Percobaan
 * berikutnya terhitung sebagai nomor baru.
 */
class DeviceRetrievalReviewRejectCreatesNewDeacTaskTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function tolak_membuat_task_deac_baru_berstatus_draft(): void
    {
        $attempt = $this->makeAttempts($this->customer, 2);

        $this->actingAs($this->fop)
            ->post(route('device-retrieval-reviews.reject', $attempt), ['review_note' => 'Belum cukup bukti, datangi lagi sore hari.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('device-retrieval-reviews.index', ['tab' => 'menunggu']).'#pelanggan-'.$this->customer->id);

        $attempt->refresh();
        $this->assertSame(DeviceRetrievalReviewStatus::DITOLAK, $attempt->review_status);
        $this->assertSame('Belum cukup bukti, datangi lagi sore hari.', $attempt->review_note);
        // Keputusan #5-A: status review task ikut, tapi task lama tetap SELESAI
        // (ulangannya task baru, bukan task ini dikembalikan ke teknisi).
        $this->assertSame('rejected', $attempt->task->refresh()->fop_review_status);
        $this->assertEquals(TaskStatus::SELESAI, $attempt->task->status);

        $fopTask = FopTask::where('customer_id', $this->customer->id)->where('category', TaskType::AMBIL_MODEM->value)->sole();
        $this->assertSame(TaskStatus::DRAFT, $fopTask->status);
        $this->assertStringStartsWith('TFOP-', $fopTask->task_number);
        $this->assertStringContainsString('percobaan ke-2 ditolak', (string) $fopTask->notes);
    }

    #[Test]
    public function tolak_tanpa_alasan_ditolak_validasi(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->fop)
            ->from(route('device-retrieval-reviews.index'))
            ->post(route('device-retrieval-reviews.reject', $attempt), ['review_note' => ''])
            ->assertSessionHasErrors('review_note');

        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $attempt->refresh()->review_status);
        $this->assertFalse(FopTask::where('customer_id', $this->customer->id)->exists());
    }

    #[Test]
    public function laporan_berikutnya_setelah_ditolak_terhitung_percobaan_baru(): void
    {
        $service = app(DeviceRetrievalReviewService::class);
        $first = $this->makeAttempts($this->customer, 6);

        $this->actingAs($this->fop)
            ->post(route('device-retrieval-reviews.reject', $first), ['review_note' => 'Tetap harus diambil.'])
            ->assertSessionHasNoErrors();

        // Tanpa batas atas: percobaan ke-7 tetap bisa dibuat & direview.
        $next = $this->makeAttempt($this->customer, createdAt: now());
        $this->assertSame(7, $service->attemptNumber($next));
    }

    #[Test]
    public function tolak_ditahan_kalau_masih_ada_task_ambil_alat_berjalan(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);
        app(TicketService::class)->createDeviceRetrievalTask($this->customer, $this->fop);

        $this->actingAs($this->fop)
            ->from(route('device-retrieval-reviews.index'))
            ->post(route('device-retrieval-reviews.reject', $attempt), ['review_note' => 'Ulangi'])
            ->assertSessionHasErrors('review_note');

        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $attempt->refresh()->review_status);
        $this->assertSame(1, FopTask::where('customer_id', $this->customer->id)->count());
    }
}
