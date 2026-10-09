<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPindahPopScenario;
use Tests\TestCase;

/**
 * ADHOC-107 R8 (keputusan user 2026-09-28): pelanggan A di JETIS dengan
 * kolektor Wahyu pindah ke SANDYA → lepas dari Wahyu, tidak terikat kolektor
 * siapa pun sampai admin SANDYA meng-assign lewat Worksheet Kolektor. Dulu
 * kolektor cuma dilepas kalau tidak punya akses POP baru.
 */
class KolektorSelaluDilepasSaatPindahPopTest extends TestCase
{
    use BuildsPindahPopScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPindahPop();
    }

    #[Test]
    public function pindah_cabang_lewat_edit_melepas_kolektor_walau_aksesnya_mencakup_cabang_baru(): void
    {
        $this->loginAsAdmin();
        $wahyu = $this->userBerScope('uji_wahyu', [$this->jetis->id, $this->sandya->id]);
        $customer = $this->pelangganAktifDiJetis(['collector_id' => $wahyu->id]);

        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
            'pop_id' => $this->sandya->id,
            'mini_pop_id' => null,
            'distribution_id' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertNull($customer->fresh()->collector_id);
    }

    #[Test]
    public function edit_tanpa_ganti_cabang_tidak_melepas_kolektor(): void
    {
        $this->loginAsAdmin();
        $wahyu = $this->userBerScope('uji_wahyu_tetap', [$this->jetis->id]);
        $customer = $this->pelangganAktifDiJetis(['collector_id' => $wahyu->id]);

        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
            'primary_phone' => '085555555555',
        ]))->assertSessionHasNoErrors();

        $this->assertSame($wahyu->id, (int) $customer->fresh()->collector_id);
    }
}
