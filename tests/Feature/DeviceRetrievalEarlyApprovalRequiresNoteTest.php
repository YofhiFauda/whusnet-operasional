<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalReviewStatus;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\FopTaskStatusHistory;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * K1 (revisi 2026-11-18) + K6: angka 5 BUKAN kunci — FOP boleh approve di
 * percobaan berapa pun, tapi sebelum percobaan ke-5 catatan wajib.
 */
class DeviceRetrievalEarlyApprovalRequiresNoteTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function approve_di_percobaan_ketiga_tanpa_catatan_ditolak_validasi(): void
    {
        $attempt = $this->makeAttempts($this->customer, 3);

        $this->actingAs($this->fop)
            ->from(route('device-retrieval-reviews.index'))
            ->post(route('device-retrieval-reviews.approve', $attempt), ['review_note' => '  '])
            ->assertSessionHasErrors('review_note');

        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $attempt->refresh()->review_status);
        $this->assertNull($this->customer->customerDevice->refresh()->retrieval_waived_at);
    }

    #[Test]
    public function approve_di_percobaan_ketiga_dengan_catatan_membebaskan_pelanggan(): void
    {
        $attempt = $this->makeAttempts($this->customer, 3);

        $this->actingAs($this->fop)
            ->post(route('device-retrieval-reviews.approve', $attempt), ['review_note' => 'Rumah dibongkar, modem ikut hilang.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame(DeviceRetrievalReviewStatus::DISETUJUI, $attempt->review_status);
        $this->assertSame($this->fop->id, $attempt->reviewed_by);
        $this->assertSame('Rumah dibongkar, modem ikut hilang.', $attempt->review_note);

        $device = $this->customer->customerDevice->refresh();
        $this->assertNotNull($device->retrieval_waived_at);
        $this->assertSame($this->fop->id, $device->retrieval_waived_by);
        // Bukan "sudah diambil": modem memang tidak kembali.
        $this->assertNull($device->device_retrieved_at);

        $this->assertTrue(AuditLog::where('action', 'device_retrieval_review_approved')->exists());
        // Keputusan #5-A: task tidak tertahan "pending" setelah diputuskan.
        $this->assertSame('approved', $attempt->task->refresh()->fop_review_status);
    }

    #[Test]
    public function approve_di_percobaan_kelima_tanpa_catatan_lolos(): void
    {
        $attempt = $this->makeAttempts($this->customer, 5);

        $this->actingAs($this->fop)
            ->post(route('device-retrieval-reviews.approve', $attempt), ['review_note' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame(DeviceRetrievalReviewStatus::DISETUJUI, $attempt->refresh()->review_status);
    }

    #[Test]
    public function keputusan_review_tercatat_satu_kali_di_riwayat_task_fop(): void
    {
        $attempt = $this->makeAttempts($this->customer, 5);
        $fopTask = app(TicketService::class)->createDeviceRetrievalTask($this->customer, $this->fop);
        $fopTask->update(['task_id' => $attempt->task_id, 'status' => TaskStatus::SELESAI]);
        $historyBefore = FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->count();

        $this->actingAs($this->fop)
            ->post(route('device-retrieval-reviews.approve', $attempt))
            ->assertSessionHasNoErrors();

        $this->assertSame($historyBefore + 1, FopTaskStatusHistory::where('fop_task_id', $fopTask->id)->count());
        $this->assertEquals(TaskStatus::SELESAI, $fopTask->refresh()->status);
    }

    #[Test]
    public function laporan_yang_sudah_diputuskan_tidak_bisa_diputuskan_lagi(): void
    {
        $attempt = $this->makeAttempts($this->customer, 5);

        $this->actingAs($this->fop)->post(route('device-retrieval-reviews.approve', $attempt))->assertSessionHasNoErrors();
        $this->actingAs($this->fop)
            ->post(route('device-retrieval-reviews.reject', $attempt), ['review_note' => 'Coba lagi'])
            ->assertSessionHasErrors('review_note');

        $this->assertSame(DeviceRetrievalReviewStatus::DISETUJUI, $attempt->refresh()->review_status);
    }
}
