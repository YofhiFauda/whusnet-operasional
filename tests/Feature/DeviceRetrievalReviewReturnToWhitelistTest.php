<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Tujuan redirect dipilih dari token `return_to` (list|detail) — URL
 * dirakit server. Nilai lain (mis. URL luar) ditolak validasi, tidak pernah
 * dipakai sebagai tujuan redirect (bukan open redirect).
 */
class DeviceRetrievalReviewReturnToWhitelistTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function return_to_berupa_url_luar_tidak_dipakai(): void
    {
        $attempt = $this->makeAttempts($this->customer, 1);
        $listUrl = route('device-retrieval-reviews.index');

        $this->actingAs($this->cs)
            ->from($listUrl)
            ->post(route('device-retrieval-reviews.comment', $attempt), [
                'body' => 'Komentar',
                'return_to' => 'https://evil.example.com',
            ])
            ->assertRedirect($listUrl)
            ->assertSessionHasErrors('return_to');

        $this->assertSame(0, $attempt->comments()->count());
        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $attempt->refresh()->review_status);
    }

    #[Test]
    public function tab_tidak_dikenal_jatuh_ke_tab_menunggu(): void
    {
        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index', ['tab' => '../../etc']))
            ->assertOk();
    }
}
