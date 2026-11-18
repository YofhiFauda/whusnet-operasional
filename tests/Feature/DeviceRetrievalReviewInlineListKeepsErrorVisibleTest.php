<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Form inline di List (Q7): gagal validasi kembali ke List, baris yang
 * gagal ter-expand dan form yang sama terbuka lagi dengan pesan error —
 * bukan List kosong tanpa pesan (alasan pola #2 CLAUDE.md).
 */
class DeviceRetrievalReviewInlineListKeepsErrorVisibleTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function tolak_tanpa_alasan_dari_list_membuka_lagi_baris_dan_form_dengan_error(): void
    {
        $attempt = $this->makeAttempts($this->customer, 2);
        $listUrl = route('device-retrieval-reviews.index', ['tab' => 'menunggu']);

        $payload = [
            'review_note' => '',
            'return_to' => 'list',
            'tab' => 'menunggu',
            'retrieval_id' => $attempt->id,
            'review_form' => 'reject',
        ];

        $this->actingAs($this->fop)
            ->from($listUrl)
            ->post(route('device-retrieval-reviews.reject', $attempt), $payload)
            ->assertRedirect($listUrl)
            ->assertSessionHasErrors('review_note');

        $this->actingAs($this->fop)
            ->from($listUrl)
            ->followingRedirects()
            ->post(route('device-retrieval-reviews.reject', $attempt), $payload)
            ->assertOk()
            ->assertSee('Alasan penolakan wajib diisi.')
            // Baris pelanggan ter-expand & form tolak terbuka (state Alpine awal).
            ->assertSee('x-data="{ open: true }"', false)
            ->assertSee('x-data="{ form: \'reject\' }"', false);
    }
}
