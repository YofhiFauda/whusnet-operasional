<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Pencarian di List Review Penarikan Alat menyaring baris (nama / CID /
 * no. HP) tanpa mengubah hitungan tab — hitungan = beban kerja antrian,
 * bukan hasil filter — dan tetap di dalam POP scope.
 */
class DeviceRetrievalReviewListSearchTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function cari_nama_menyaring_baris_tanpa_mengubah_hitungan_tab(): void
    {
        $this->customer->update(['full_name' => 'Sulastri Wibowo']);
        $other = $this->makeTerminatedCustomer($this->popA);
        $other->update(['full_name' => 'Bambang Haryanto']);
        $this->makeAttempts($this->customer, 1);
        $this->makeAttempts($other, 1);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index', ['q' => 'Sulastri']))
            ->assertOk()
            ->assertSee('Sulastri Wibowo')
            ->assertDontSee('Bambang Haryanto')
            ->assertSee('id="pelanggan-'.$this->customer->id.'"', false)
            ->assertDontSee('id="pelanggan-'.$other->id.'"', false)
            // Badge tab Menunggu tetap menghitung 2 pelanggan.
            ->assertSeeInOrder(['Menunggu Review', '2'], false);
    }

    #[Test]
    public function cari_nomor_hp_dan_hasil_kosong_menampilkan_reset(): void
    {
        $this->customer->update(['primary_phone' => '081299887766']);
        $this->makeAttempts($this->customer, 1);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index', ['q' => '99887766']))
            ->assertOk()
            ->assertSee($this->customer->full_name);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index', ['q' => 'TidakAdaNamaIni']))
            ->assertOk()
            ->assertDontSee($this->customer->full_name)
            ->assertSee('Reset Pencarian');
    }

    #[Test]
    public function pencarian_tidak_menembus_pop_scope(): void
    {
        $outside = $this->makeTerminatedCustomer($this->popB);
        $outside->update(['full_name' => 'Pelanggan Cabang Lain']);
        $this->makeAttempts($outside, 1);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index', ['q' => 'Cabang Lain']))
            ->assertOk()
            ->assertDontSee('Pelanggan Cabang Lain');
    }
}
