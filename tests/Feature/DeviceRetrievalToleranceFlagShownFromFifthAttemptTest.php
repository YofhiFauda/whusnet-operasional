<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsDeviceRetrievalReviewScenario;
use Tests\TestCase;

/**
 * Label "boleh ditoleransi" + urutan: kasus dengan percobaan terbanyak naik
 * ke atas. Muncul mulai percobaan ke-5, tidak di ke-4.
 */
class DeviceRetrievalToleranceFlagShownFromFifthAttemptTest extends TestCase
{
    use BuildsDeviceRetrievalReviewScenario;
    use RefreshDatabase;

    private const FLAG = 'percobaan — boleh ditoleransi';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReviewScenario();
    }

    #[Test]
    public function percobaan_keempat_belum_diberi_label(): void
    {
        $this->makeAttempts($this->customer, 4);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index'))
            ->assertOk()
            ->assertSee('Percobaan 4')
            ->assertDontSee(self::FLAG);
    }

    #[Test]
    public function percobaan_kelima_diberi_label_dan_naik_ke_atas(): void
    {
        $fewer = $this->makeTerminatedCustomer($this->popA);
        $this->makeAttempts($fewer, 2);
        $this->makeAttempts($this->customer, 5);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.index'))
            ->assertOk()
            ->assertSee(self::FLAG)
            ->assertSeeInOrder(['pelanggan-'.$this->customer->id, 'pelanggan-'.$fewer->id], false);
    }

    #[Test]
    public function detail_menampilkan_seluruh_percobaan_dan_label(): void
    {
        $this->makeAttempts($this->customer, 5);

        $this->actingAs($this->fop)->get(route('device-retrieval-reviews.show', $this->customer))
            ->assertOk()
            ->assertSee('Percobaan #5')
            ->assertSee('Percobaan #1')
            ->assertSee(self::FLAG);
    }
}
