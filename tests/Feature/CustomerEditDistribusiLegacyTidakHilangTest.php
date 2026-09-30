<?php

namespace Tests\Feature;

use App\Models\Distribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPindahPopScenario;
use Tests\TestCase;

/**
 * ADHOC-107 T1 (regresi berat): migrasi legacy (MigrateLegacyDataCommand &
 * import) menempelkan Distribusi langsung ke CABANG kalau pelanggan belum
 * punya Mini POP. Dropdown Edit cuma memuat Distribusi anak Mini POP, jadi
 * select terkirim kosong dan `distribution_id` + CID hilang diam-diam hanya
 * karena admin mengganti nomor HP.
 *
 * Aturan (keputusan user no. 8 + ADHOC-109): nilai jaringan yang tidak disentuh
 * dibiarkan apa adanya walau di luar hierarki; Mini POP & Distribusi di Edit
 * cuma ditulis kalau Cabang ikut dipindah — dan saat itu nilai legacy milik
 * cabang lama memang dilepas.
 */
class CustomerEditDistribusiLegacyTidakHilangTest extends TestCase
{
    use BuildsPindahPopScenario, RefreshDatabase;

    private Distribution $distLegacyDiCabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPindahPop();

        // Persis pola MigrateLegacyDataCommand: tanpa Mini POP → Distribusi
        // menempel ke Cabang.
        $this->distLegacyDiCabang = Distribution::create(['pop_id' => $this->jetis->id, 'code' => '9Z', 'name' => 'Legacy Jetis']);
    }

    #[Test]
    public function edit_nomor_hp_tidak_menghapus_distribusi_legacy_di_cabang(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis([
            'mini_pop_id' => null,
            'distribution_id' => $this->distLegacyDiCabang->id,
            'cid' => 'C09ZRQ000631',
        ]);

        // Form mengirim dropdown kosong (opsi legacy terfilter) — dulu ini
        // meng-NULL-kan distribusinya.
        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
            'primary_phone' => '086666666666',
            'distribution_id' => null,
        ]))->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame($this->distLegacyDiCabang->id, (int) $customer->distribution_id);
        $this->assertSame('C09ZRQ000631', $customer->cid);
    }

    #[Test]
    public function form_edit_tetap_menampilkan_distribusi_legacy(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis([
            'mini_pop_id' => null,
            'distribution_id' => $this->distLegacyDiCabang->id,
        ]);

        $this->get(route('customers.edit', $customer->id))
            ->assertOk()
            ->assertSee('9Z - Legacy Jetis');
    }

    #[Test]
    public function pindah_cabang_melepas_distribusi_legacy_milik_cabang_lama(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis([
            'mini_pop_id' => null,
            'distribution_id' => $this->distLegacyDiCabang->id,
            'cid' => 'C09ZRQ000631',
        ]);

        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
            'pop_id' => $this->sandya->id,
            'mini_pop_id' => null,
            'distribution_id' => null,
        ]))->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertNull($customer->distribution_id);
        $this->assertSame('D00RQ000631', $customer->cid);
    }
}
