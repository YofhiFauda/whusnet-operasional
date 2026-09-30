<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Exceptions\CustomerRelocationBlockedException;
use App\Models\CustomerBalanceMutation;
use App\Models\Invoice;
use App\Services\CustomerBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPindahPopScenario;
use Tests\TestCase;

/**
 * Pindah Cabang & tagihan (ADHOC-107 R4; keputusan user 2026-09-28, K6/K7/K8):
 * - piutang (tunggakan bulan-bulan sebelumnya) & tagihan yang sudah dicicil
 *   sebagian wajib lunas dulu — dari form Edit MAUPUN import/tinker;
 * - tagihan bulan berjalan yang belum dibayar sama sekali ikut pindah dan
 *   dibayar ke cabang baru;
 * - tagihan periode lalu, pembayaran, dan saldo lama tidak pernah disentuh.
 *
 * Gejala yang dicegah: tagihan cabang lama "yatim" (laporannya di JETIS,
 * kolektornya sudah dilepas) atau laporan dua cabang tidak sinambung.
 */
class PindahPopDitolakSelamaAdaPiutangTest extends TestCase
{
    use BuildsPindahPopScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPindahPop();
    }

    #[Test]
    public function edit_menolak_pindah_cabang_selama_ada_piutang_bulan_lalu(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis();
        $this->tagihan($customer, self::BULAN_LALU, InvoiceStatus::BELUM_DIBAYAR);

        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
            'pop_id' => $this->sandya->id,
            'mini_pop_id' => null,
            'distribution_id' => null,
        ]))->assertSessionHasErrors('pop_id');

        $customer->refresh();
        $this->assertSame($this->jetis->id, (int) $customer->pop_id);
        $this->assertSame('C1X4ARQ000631', $customer->cid);
    }

    #[Test]
    public function tinker_juga_ditolak_selama_ada_piutang_dan_data_tidak_berubah(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        $piutang = $this->tagihan($customer, self::BULAN_LALU, InvoiceStatus::BELUM_DIBAYAR);

        try {
            $customer->update(['pop_id' => $this->sandya->id]);
            $this->fail('Pindah Cabang dengan piutang harus ditolak dari semua jalur.');
        } catch (CustomerRelocationBlockedException $e) {
            $this->assertStringContainsString('wajib lunas dulu', $e->getMessage());
        }

        $this->assertSame($this->jetis->id, (int) $customer->fresh()->pop_id);
        $this->assertSame($this->jetis->id, (int) $piutang->fresh()->pop_id);
    }

    #[Test]
    public function tagihan_bulan_ini_yang_sudah_dicicil_sebagian_wajib_lunas_dulu(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        $cicil = $this->tagihan($customer, self::BULAN_INI, InvoiceStatus::SEBAGIAN, 100000);
        $this->pembayaran($cicil, 50000);

        $this->expectException(CustomerRelocationBlockedException::class);
        $customer->update(['pop_id' => $this->sandya->id]);
    }

    #[Test]
    public function pembayaran_ditolak_tidak_menahan_pelanggan_pindah(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        $tagihan = $this->tagihan($customer, self::BULAN_INI, InvoiceStatus::BELUM_DIBAYAR);
        // Pembayaran yang sudah ditolak bukan uang yang diterima.
        $this->pembayaran($tagihan, 150000, 'ditolak');

        $customer->update(['pop_id' => $this->sandya->id]);

        $this->assertSame($this->sandya->id, (int) $tagihan->fresh()->pop_id);
    }

    #[Test]
    public function tanpa_piutang_pindah_berhasil_dan_cuma_tagihan_bulan_ini_yang_ikut(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis();
        $lunasLalu = $this->tagihan($customer, self::BULAN_LALU, InvoiceStatus::LUNAS, 0);
        $bayarLalu = $this->pembayaran($lunasLalu, 150000);
        $bulanIni = $this->tagihan($customer, self::BULAN_INI, InvoiceStatus::BELUM_DIBAYAR);

        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
            'pop_id' => $this->sandya->id,
            'mini_pop_id' => null,
            'distribution_id' => null,
            'internet_package_id' => $bulanIni->internet_package_id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($this->sandya->id, (int) $customer->fresh()->pop_id);
        // Laporan pembayaran & piutang tetap milik cabang lama.
        $this->assertSame($this->jetis->id, (int) $lunasLalu->fresh()->pop_id);
        $this->assertSame($this->jetis->id, (int) $bayarLalu->fresh()->pop_id);
        // Tagihan bulan berjalan pindah → pembayarannya masuk cabang baru.
        $this->assertSame($this->sandya->id, (int) $bulanIni->fresh()->pop_id);
    }

    #[Test]
    public function tagihan_bulan_depan_yang_belum_dibayar_ikut_pindah(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        $bulanDepan = $this->tagihan($customer, '2026-11', InvoiceStatus::BELUM_DIBAYAR);

        $customer->update(['pop_id' => $this->sandya->id]);

        $this->assertSame($this->sandya->id, (int) $bulanDepan->fresh()->pop_id);
    }

    #[Test]
    public function tagihan_bulan_berikutnya_terbit_di_cabang_baru(): void
    {
        $customer = $this->pelangganAktifDiJetis(['data_completeness_status' => 'siap_billing']);
        $this->layanan($customer);
        $customer->update(['pop_id' => $this->sandya->id]);

        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addHour());
        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-11'])->assertSuccessful();

        $november = Invoice::where('customer_id', $customer->id)->where('billing_period', '2026-11')->first();
        $this->assertNotNull($november, 'Tagihan bulanan berikutnya harus tetap terbit setelah pindah Cabang.');
        $this->assertSame($this->sandya->id, (int) $november->pop_id);
    }

    #[Test]
    public function saldo_lebih_bayar_dari_cabang_lama_terpakai_untuk_tagihan_cabang_baru(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        $balances = app(CustomerBalanceService::class);
        $balances->creditWithoutPayment($customer, 150000, $this->jetis->id, 'Lebih bayar di JETIS');

        $bulanIni = $this->tagihan($customer, self::BULAN_INI, InvoiceStatus::BELUM_DIBAYAR);
        $customer->update(['pop_id' => $this->sandya->id]);

        $balances->applyToOpenInvoices($customer->fresh());

        $this->assertSame(InvoiceStatus::LUNAS, $bulanIni->fresh()->invoice_status);
        // Jejak lintas cabang tetap jelas per baris ledger (K7).
        $this->assertTrue(CustomerBalanceMutation::where('customer_id', $customer->id)->where('type', 'credit')->where('pop_id', $this->jetis->id)->exists());
        $this->assertTrue(CustomerBalanceMutation::where('customer_id', $customer->id)->where('type', 'debit')->where('pop_id', $this->sandya->id)->exists());
    }

    #[Test]
    public function form_edit_memberi_tahu_piutang_dan_tagihan_yang_akan_ikut_pindah(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis();
        $this->tagihan($customer, self::BULAN_INI, InvoiceStatus::BELUM_DIBAYAR);

        $this->get(route('customers.edit', $customer->id))
            ->assertOk()
            ->assertSee('1 tagihan bulan berjalan yang belum dibayar');

        $this->tagihan($customer, self::BULAN_LALU, InvoiceStatus::BELUM_DIBAYAR);

        $this->get(route('customers.edit', $customer->id))
            ->assertOk()
            ->assertSee('Belum bisa pindah Cabang');
    }
}
