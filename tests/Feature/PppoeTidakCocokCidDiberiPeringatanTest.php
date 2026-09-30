<?php

namespace Tests\Feature;

use App\Models\CustomerDevice;
use App\Services\CustomerCidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPindahPopScenario;
use Tests\TestCase;

/**
 * ADHOC-107 R7 (keputusan user 2026-09-28, K2): CID boleh berubah saat pindah
 * POP, tapi PPPoE username (`{CID}_{DESA}_{NAMA}`) SENGAJA tidak diubah
 * otomatis — harus sama persis dengan akun di Mikrotik. Kasus nyata di DB dev:
 * CID `D1X6ARQ002022` dengan PPPoE `C1X4ARQ002022_TURI_WALUYOMBER`. Sistem cukup
 * memberi peringatan supaya NOC menyesuaikan manual.
 */
class PppoeTidakCocokCidDiberiPeringatanTest extends TestCase
{
    use BuildsPindahPopScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPindahPop();
    }

    #[Test]
    public function pppoe_basi_setelah_pindah_pop_diberi_peringatan_di_detail_dan_quick_hub(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis();
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ont', 'pppoe_username' => 'C1X4ARQ000631_TURI_PELANGGAN']);

        $customer->update(['pop_id' => $this->sandya->id]);
        $customer->refresh();
        $this->assertSame('D00RQ000631', $customer->cid);
        // PPPoE tidak diubah otomatis.
        $this->assertSame('C1X4ARQ000631_TURI_PELANGGAN', $customer->customerDevice->pppoe_username);

        $this->get(route('customers.show', $customer->id))
            ->assertOk()
            ->assertSee('PPPoE belum disesuaikan dengan CID D00RQ000631');

        $this->getJson(route('customers.payment-info', $customer->id))
            ->assertOk()
            ->assertJsonPath('technical.pppoe_warning', 'PPPoE belum disesuaikan dengan CID D00RQ000631 — ubah di Mikrotik lalu di Edit Pelanggan.');
    }

    #[Test]
    public function pppoe_yang_sudah_disesuaikan_tidak_diberi_peringatan(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis();
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ont', 'pppoe_username' => 'C1X4ARQ000631_TURI_PELANGGAN']);

        $this->get(route('customers.show', $customer->id))
            ->assertOk()
            ->assertDontSee('PPPoE belum disesuaikan');

        $this->getJson(route('customers.payment-info', $customer->id))
            ->assertOk()
            ->assertJsonPath('technical.pppoe_warning', null);
    }

    #[Test]
    public function pppoe_kosong_atau_pelanggan_belum_aktif_tanpa_peringatan(): void
    {
        $aktif = $this->pelangganAktifDiJetis();
        $this->assertNull(CustomerCidService::pppoeMatchesCid($aktif, null));
        $this->assertNull(CustomerCidService::pppoeMatchesCid($aktif, '-'));

        $calon = $this->pelangganAktifDiJetis(['customer_code' => 'RQ000700', 'status' => 'installation_in_progress']);
        $this->assertNull(CustomerCidService::pppoeMatchesCid($calon, 'X_Y_Z'));
    }
}
