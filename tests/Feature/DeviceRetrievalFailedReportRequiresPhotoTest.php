<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalOutcome;
use App\Enums\DeviceRetrievalReviewStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\TaskTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Laporan DEAC gagal wajib alasan + foto bukti lokasi (studi kasus), lalu
 * masuk antrian Review Penarikan Alat. Laporan `diambil` tidak (K3).
 */
class DeviceRetrievalFailedReportRequiresPhotoTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();

        $this->task = Task::create([
            'task_number' => 'TASK-RV-FORM',
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'Ambil Modem',
            'pop_id' => $this->popA->id,
            'customer_id' => $this->customer->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);
        TaskTeam::create(['task_id' => $this->task->id, 'user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);
    }

    #[Test]
    public function laporan_gagal_tanpa_foto_ditolak_validasi(): void
    {
        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::DITOLAK->value,
            'notes' => 'Pelanggan menolak menyerahkan modem.',
        ])->assertSessionHasErrors('condition_photo');

        $this->assertNull($this->task->refresh()->deviceRetrieval);
        $this->assertEquals(TaskStatus::IN_PROGRESS, $this->task->status);
    }

    #[Test]
    public function foto_kondisi_modem_dari_laporan_diambil_tidak_dipakai_ulang_sebagai_bukti_lokasi(): void
    {
        // Laporan "diambil" (berfoto) di-reject generik → task kembali
        // In Progress → teknisi kirim ulang sebagai GAGAL tanpa foto baru.
        Storage::disk('local')->put('device-retrieval/modem-lama.jpg', 'jpg');
        $this->task->deviceRetrieval()->create([
            'outcome' => DeviceRetrievalOutcome::DIAMBIL,
            'condition_photo' => 'device-retrieval/modem-lama.jpg',
        ]);

        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::TIDAK_DITEMUKAN->value,
            'notes' => 'Rumah kosong.',
        ])->assertSessionHasErrors('condition_photo');

        $this->assertSame(DeviceRetrievalOutcome::DIAMBIL, $this->task->refresh()->deviceRetrieval->outcome);
    }

    #[Test]
    public function foto_lama_yang_filenya_hilang_tidak_menggugurkan_wajib_foto(): void
    {
        $this->task->deviceRetrieval()->create([
            'outcome' => DeviceRetrievalOutcome::DITOLAK,
            'condition_photo' => 'device-retrieval/sudah-hilang.jpg',
            'notes' => 'Laporan lama',
        ]);

        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::DITOLAK->value,
            'notes' => 'Pelanggan menolak.',
        ])->assertSessionHasErrors('condition_photo');
    }

    #[Test]
    public function kirim_ulang_laporan_gagal_boleh_memakai_foto_bukti_lama(): void
    {
        Storage::disk('local')->put('device-retrieval/lokasi-lama.jpg', 'jpg');
        $this->task->deviceRetrieval()->create([
            'outcome' => DeviceRetrievalOutcome::DITOLAK,
            'condition_photo' => 'device-retrieval/lokasi-lama.jpg',
            'notes' => 'Laporan lama',
        ]);

        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::TIDAK_DITEMUKAN->value,
            'notes' => 'Rumah kosong.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('device-retrieval/lokasi-lama.jpg', $this->task->refresh()->deviceRetrieval->condition_photo);
    }

    #[Test]
    public function laporan_gagal_dengan_foto_masuk_antrian_review_dan_fotonya_privat(): void
    {
        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::DITOLAK->value,
            'notes' => 'Pelanggan menolak menyerahkan modem.',
            'condition_photo' => UploadedFile::fake()->image('lokasi.jpg'),
        ])->assertSessionHasNoErrors();

        $retrieval = $this->task->refresh()->deviceRetrieval;
        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $retrieval->review_status);
        Storage::disk('local')->assertExists($retrieval->condition_photo);
        Storage::disk('public')->assertMissing($retrieval->condition_photo);
    }
}
