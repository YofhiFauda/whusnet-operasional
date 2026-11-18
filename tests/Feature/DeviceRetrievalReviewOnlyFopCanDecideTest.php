<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalReviewStatus;
use App\Models\DeviceRetrievalComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * K4: hanya FOP yang approve/tolak; CS & BD cuma lihat + komentar.
 */
class DeviceRetrievalReviewOnlyFopCanDecideTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function cs_dan_bd_tidak_bisa_approve_atau_tolak(): void
    {
        $attempt = $this->makeAttempts($this->customer, 5);

        foreach ([$this->cs, $this->bd] as $user) {
            $this->actingAs($user)->post(route('device-retrieval-reviews.approve', $attempt))->assertForbidden();
            $this->actingAs($user)->post(route('device-retrieval-reviews.reject', $attempt), ['review_note' => 'x'])->assertForbidden();
        }

        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $attempt->refresh()->review_status);
    }

    #[Test]
    public function cs_dan_bd_bisa_melihat_dan_berkomentar_tanpa_tombol_keputusan(): void
    {
        $attempt = $this->makeAttempts($this->customer, 2);

        foreach ([$this->cs, $this->bd] as $user) {
            $this->actingAs($user)->get(route('device-retrieval-reviews.index'))
                ->assertOk()
                ->assertSee($this->customer->full_name)
                ->assertDontSee('Konfirmasi Approve')
                ->assertSee('Kirim Komentar');

            $this->actingAs($user)
                ->post(route('device-retrieval-reviews.comment', $attempt), ['body' => 'Pelanggan bilang modem dipinjam tetangga.'])
                ->assertSessionHasNoErrors()
                ->assertRedirect();
        }

        $this->assertSame(2, DeviceRetrievalComment::where('task_device_retrieval_id', $attempt->id)->count());
        $this->assertSame(DeviceRetrievalReviewStatus::MENUNGGU, $attempt->refresh()->review_status);
    }

    #[Test]
    public function teknisi_tidak_bisa_membuka_halaman_review(): void
    {
        $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->teknisi)->get(route('device-retrieval-reviews.index'))->assertForbidden();
    }

    #[Test]
    public function fop_melihat_tombol_keputusan(): void
    {
        $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index'))
            ->assertOk()
            ->assertSee('Konfirmasi Approve')
            ->assertSee('Konfirmasi Tolak');
    }
}
