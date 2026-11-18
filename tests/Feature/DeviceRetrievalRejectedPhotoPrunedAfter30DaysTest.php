<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * K5: foto percobaan DITOLAK dihapus 30 hari setelah ditolak; foto yang
 * DISETUJUI & yang masih MENUNGGU utuh.
 */
class DeviceRetrievalRejectedPhotoPrunedAfter30DaysTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function hanya_foto_ditolak_lebih_dari_30_hari_yang_dihapus(): void
    {
        $oldRejected = $this->makeAttempt($this->customer, DeviceRetrievalReviewStatus::DITOLAK);
        $oldRejected->update(['reviewed_at' => now()->subDays(31)]);

        $freshRejected = $this->makeAttempt($this->customer, DeviceRetrievalReviewStatus::DITOLAK);
        $freshRejected->update(['reviewed_at' => now()->subDays(10)]);

        $oldApproved = $this->makeAttempt($this->customer, DeviceRetrievalReviewStatus::DISETUJUI);
        $oldApproved->update(['reviewed_at' => now()->subDays(90)]);

        $pending = $this->makeAttempt($this->customer);

        $this->artisan('device-retrieval:prune-photos')->assertSuccessful();

        Storage::disk('local')->assertMissing($oldRejected->condition_photo);
        $this->assertNotNull($oldRejected->refresh()->photo_purged_at);
        // Alasan teks tetap ada — cuma file foto yang dihapus.
        $this->assertNotNull($oldRejected->notes);

        foreach ([$freshRejected, $oldApproved, $pending] as $kept) {
            Storage::disk('local')->assertExists($kept->condition_photo);
            $this->assertNull($kept->refresh()->photo_purged_at);
        }
    }

    #[Test]
    public function dry_run_tidak_menghapus_apa_pun(): void
    {
        $rejected = $this->makeAttempt($this->customer, DeviceRetrievalReviewStatus::DITOLAK);
        $rejected->update(['reviewed_at' => now()->subDays(31)]);

        $this->artisan('device-retrieval:prune-photos', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('local')->assertExists($rejected->condition_photo);
        $this->assertNull($rejected->refresh()->photo_purged_at);
    }

    #[Test]
    public function foto_yang_sudah_dihapus_tidak_bisa_dibuka_dan_view_menyebutnya(): void
    {
        $rejected = $this->makeAttempt($this->customer, DeviceRetrievalReviewStatus::DITOLAK);
        $rejected->update(['reviewed_at' => now()->subDays(31)]);
        $this->makeAttempt($this->customer, createdAt: now());
        $this->artisan('device-retrieval:prune-photos')->assertSuccessful();

        $this->actingAs($this->fop)->get(route('device-retrievals.photo', $rejected))->assertNotFound();
        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.show', $this->customer))
            ->assertOk()
            ->assertSee('Foto dihapus (retensi 30 hari)');
    }
}
