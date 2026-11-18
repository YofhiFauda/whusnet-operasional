<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalReviewStatus;
use App\Services\ActionQueueBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Larangan keras #3 CLAUDE.md: pelanggan luar POP scope tidak tampil, tidak
 * bisa dibuka, tidak bisa diputuskan, dan tidak ikut dihitung badge.
 */
class DeviceRetrievalReviewRespectsPopScopeTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function pelanggan_luar_scope_tidak_tampil_dan_tidak_bisa_diakses(): void
    {
        $outside = $this->makeTerminatedCustomer($this->popB);
        $outsideAttempt = $this->makeAttempts($outside, 1);
        $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index'))
            ->assertOk()
            ->assertSee($this->customer->full_name)
            ->assertDontSee($outside->full_name);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.show', $outside))->assertForbidden();
        $this->actingAs($this->fop)->post(route('device-retrieval-reviews.approve', $outsideAttempt), ['review_note' => 'x'])->assertForbidden();
        $this->actingAs($this->fop)->post(route('device-retrieval-reviews.reject', $outsideAttempt), ['review_note' => 'x'])->assertForbidden();
        $this->actingAs($this->cs)->post(route('device-retrieval-reviews.comment', $outsideAttempt), ['body' => 'x'])->assertForbidden();

        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $outsideAttempt->refresh()->review_status);
    }

    #[Test]
    public function badge_hanya_menghitung_laporan_menunggu_dalam_scope(): void
    {
        $this->makeAttempts($this->customer, 3);            // 1 menunggu (2 ditolak)
        $other = $this->makeTerminatedCustomer($this->popA);
        $this->makeAttempt($other);                          // 1 menunggu
        $this->makeAttempt($other, DeviceRetrievalReviewStatus::DISETUJUI);
        $this->makeAttempts($this->makeTerminatedCustomer($this->popB), 1); // luar scope

        $badges = app(ActionQueueBadgeService::class);

        $this->assertSame(2, $badges->pendingRetrievalReviews($this->fop)->count());
        $this->assertSame(3, $badges->pendingRetrievalReviews($this->owner)->count());
        // Tanpa hak review (CS) badge tidak relevan.
        $this->assertSame(0, $badges->pendingRetrievalReviews($this->cs)->count());
    }
}
