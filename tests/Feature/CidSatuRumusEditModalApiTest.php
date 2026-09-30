<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Services\CustomerCidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPindahPopScenario;
use Tests\TestCase;

/**
 * ADHOC-107 T3/T4/T10 (R3): CID dulu ditulis beberapa jalur dengan rumus
 * berbeda — Edit `sprintf('%s00%s')` kalau distribusi kosong (Mini POP
 * diabaikan), modal/API `generateComplexCid()` — sehingga CID pelanggan yang
 * sama bolak-balik tiap disimpan (43 pelanggan dev), dan pindah POP lewat
 * import/tinker meninggalkan CID basi. Sekarang satu rumus (K3) lewat
 * CustomerCidService, dipicu CustomerObserver::updating().
 *
 * Endpoint API (NetworkAssignmentService) memakai jalur yang sama — dikunci
 * tests/Feature/Api/NetworkAssignmentTest.
 */
class CidSatuRumusEditModalApiTest extends TestCase
{
    use BuildsPindahPopScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPindahPop();
    }

    #[Test]
    public function modal_mini_pop_tanpa_distribusi_lalu_edit_biasa_tidak_membalik_cid(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis(['distribution_id' => null, 'cid' => 'C00RQ000631']);

        $this->put(route('customers.network-assignment.update', $customer->id), [
            'mini_pop_id' => $this->miniJetis->id,
            'distribution_id' => null,
        ])->assertSessionHasNoErrors();

        // Rumus K3: Mini POP C1X → segmen "1X", Distribusi kosong → "0".
        $this->assertSame('C1X0RQ000631', $customer->fresh()->cid);

        // Edit data lain (bukan jaringan) — dulu langkah 1b Edit menghitung
        // `C00RQ…` dan CID berganti tiap simpan.
        $customer->refresh();
        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
            'primary_phone' => '089999999999',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('C1X0RQ000631', $customer->fresh()->cid);
    }

    #[Test]
    public function edit_dan_modal_menghasilkan_cid_yang_sama(): void
    {
        $this->loginAsAdmin();
        $lewatModal = $this->pelangganAktifDiJetis(['mini_pop_id' => null, 'distribution_id' => null, 'cid' => 'C00RQ000631']);
        $this->put(route('customers.network-assignment.update', $lewatModal->id), [
            'mini_pop_id' => $this->miniJetis->id,
            'distribution_id' => $this->distJetis->id,
        ])->assertSessionHasNoErrors();

        $lewatObserver = $this->pelangganAktifDiJetis([
            'customer_code' => 'RQ000632', 'cid' => 'C00RQ000632', 'mini_pop_id' => null, 'distribution_id' => null,
        ]);
        $lewatObserver->update(['mini_pop_id' => $this->miniJetis->id, 'distribution_id' => $this->distJetis->id]);

        $this->assertSame('C1X4ARQ000631', $lewatModal->fresh()->cid);
        $this->assertSame('C1X4ARQ000632', $lewatObserver->fresh()->cid);
        $this->assertSame(CustomerCidService::resolve($lewatModal->fresh()), $lewatModal->fresh()->cid);
    }

    #[Test]
    public function pindah_pop_lewat_tinker_membuat_ulang_cid_dan_tercatat_di_audit(): void
    {
        $customer = $this->pelangganAktifDiJetis();

        $customer->update(['pop_id' => $this->sandya->id]);

        $this->assertSame('D00RQ000631', $customer->fresh()->cid);

        $jejak = AuditLog::where('auditable_type', Customer::class)
            ->where('auditable_id', $customer->id)
            ->get()
            ->first(fn (AuditLog $log) => ($log->old_values['cid'] ?? null) === 'C1X4ARQ000631');
        $this->assertNotNull($jejak, 'CID lama wajib punya jejak di audit_logs.');
        $this->assertSame('D00RQ000631', $jejak->new_values['cid']);
    }

    #[Test]
    public function nomor_olt_teknisi_tidak_lagi_jadi_segmen_cid(): void
    {
        // Fallback olt_number dihapus (K3): isinya teks bebas cabang lama dan
        // jadi sumber CID campuran saat pindah POP.
        $customer = $this->pelangganAktifDiJetis(['mini_pop_id' => null, 'distribution_id' => null, 'cid' => null]);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'olt_number' => '1X']);

        $customer->update(['primary_phone' => '088888888888']);

        $this->assertSame('C00RQ000631', $customer->fresh()->cid);
    }

    #[Test]
    public function pelanggan_non_aktif_cid_tidak_disentuh(): void
    {
        $customer = $this->pelangganAktifDiJetis(['status' => 'installation_in_progress', 'cid' => null]);

        $customer->update(['mini_pop_id' => $this->miniJetis->id, 'distribution_id' => $this->distJetis->id]);

        $this->assertNull($customer->fresh()->cid);
    }

    #[Test]
    public function edit_biasa_tidak_mengubah_cid_legacy(): void
    {
        $this->loginAsAdmin();
        // CID legacy yang dibentuk aturan lama — tetap stabil selama
        // jaringannya tidak disentuh.
        $customer = $this->pelangganAktifDiJetis(['cid' => 'C1X4ARQ000631-LAMA']);

        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
            'primary_phone' => '087777777777',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('C1X4ARQ000631-LAMA', $customer->fresh()->cid);
    }
}
