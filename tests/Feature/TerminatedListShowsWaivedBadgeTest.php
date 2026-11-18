<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalReviewStatus;
use App\Models\FopTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * List Putus Langganan: badge "Menunggu Review" / "Dibebaskan", dan tombol
 * Ambil Alat hilang di kedua status — juga ditolak server-side
 * (CustomerController::retrieveDevice()), karena percobaan berikutnya harus
 * lahir dari Tolak di halaman review.
 */
class TerminatedListShowsWaivedBadgeTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function menunggu_review_menyembunyikan_ambil_alat_dan_menolak_server_side(): void
    {
        $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->owner)->get(route('customers.terminated'))
            ->assertOk()
            ->assertSee('Menunggu Review')
            ->assertDontSee(route('customers.retrieve-device', $this->customer), false);

        $this->actingAs($this->owner)
            ->post(route('customers.retrieve-device', $this->customer))
            ->assertSessionHas('error');

        $this->assertFalse(FopTask::where('customer_id', $this->customer->id)->exists());
    }

    #[Test]
    public function dibebaskan_menampilkan_badge_dan_menolak_ambil_alat(): void
    {
        $attempt = $this->makeAttempts($this->customer, 5);
        $this->actingAs($this->fop)->post(route('device-retrieval-reviews.approve', $attempt))->assertSessionHasNoErrors();
        $this->assertSame(DeviceRetrievalReviewStatus::DISETUJUI, $attempt->refresh()->review_status);

        $this->actingAs($this->owner)->get(route('customers.terminated'))
            ->assertOk()
            ->assertSee('Dibebaskan')
            ->assertDontSee(route('customers.retrieve-device', $this->customer), false);

        $this->actingAs($this->owner)
            ->post(route('customers.retrieve-device', $this->customer))
            ->assertSessionHas('error');

        $this->assertFalse(FopTask::where('customer_id', $this->customer->id)->exists());
    }

    #[Test]
    public function tanpa_kasus_review_tombol_ambil_alat_tetap_ada(): void
    {
        $this->actingAs($this->owner)->get(route('customers.terminated'))
            ->assertOk()
            ->assertSee(route('customers.retrieve-device', $this->customer), false);
    }
}
