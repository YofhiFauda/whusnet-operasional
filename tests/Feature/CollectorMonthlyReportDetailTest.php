<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CollectorMonthlyReportService;
use App\Services\InvoiceWriteOffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCollectorMonthlyScenario;
use Tests\TestCase;

/**
 * Rincian per sel (drill-down) Laporan Bulanan Admin Collector — jawaban
 * atas pertanyaan real case user: "dari angka Bulanan 6.359.936, siapa saja
 * yang sudah membayar?" (2026-09-22).
 */
class CollectorMonthlyReportDetailTest extends TestCase
{
    use BuildsCollectorMonthlyScenario;
    use RefreshDatabase;

    private Pop $pop;

    private User $owner;

    private User $kolektor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');
        $this->seedBase();

        $this->pop = $this->makePop('Cabang Jetis');
        $this->owner = $this->ownerUser();
        $this->kolektor = User::factory()->create(['status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): CollectorMonthlyReportService
    {
        return app(CollectorMonthlyReportService::class);
    }

    #[Test]
    public function tagihan_terbit_mencantumkan_semua_pelanggan_bertagihan_periode_ini(): void
    {
        $cust1 = $this->makeCustomer($this->pop);
        $cust1->update(['full_name' => 'Budi']);
        $inv1 = $this->makeInvoice($this->pop, '2026-09', 150000, customer: $cust1);

        $rows = $this->service()->detail('2026-09', $this->pop->id, 'tagihan', 'tagihan_terbit');

        $this->assertCount(1, $rows);
        $this->assertSame('Budi', $rows[0]['pelanggan']);
        $this->assertSame($inv1->invoice_number, $rows[0]['referensi']);
        $this->assertEquals(150000, $rows[0]['nominal']);
    }

    #[Test]
    public function bulanan_mencantumkan_transaksi_bayar_admin_dan_kolektor_digabung(): void
    {
        $custAdmin = $this->makeCustomer($this->pop);
        $custAdmin->update(['full_name' => 'Dibayar Admin']);
        $custKolektor = $this->makeCustomer($this->pop);
        $custKolektor->update(['full_name' => 'Dibayar Kolektor']);

        $inv1 = $this->makeInvoice($this->pop, '2026-09', 100000, customer: $custAdmin);
        $inv2 = $this->makeInvoice($this->pop, '2026-09', 130000, customer: $custKolektor);
        $this->makePayment($inv1, 100000, '2026-09-05');
        $this->makePayment($inv2, 130000, '2026-09-08', $this->kolektor);

        $rows = collect($this->service()->detail('2026-09', $this->pop->id, 'tagihan', 'bulanan'));

        $this->assertCount(2, $rows);
        $this->assertEquals(230000, $rows->sum('nominal'));
        $this->assertTrue($rows->contains(fn ($r) => $r['pelanggan'] === 'Dibayar Admin' && (float) $r['nominal'] === 100000.0));
        $this->assertTrue($rows->contains(fn ($r) => $r['pelanggan'] === 'Dibayar Kolektor' && (float) $r['nominal'] === 130000.0));
    }

    #[Test]
    public function dimuka_kosong_karena_metode_saldo_belum_ada_adhoc_92(): void
    {
        $inv = $this->makeInvoice($this->pop, '2026-09', 100000);
        $this->makePayment($inv, 100000, '2026-09-05');

        $this->assertSame([], $this->service()->detail('2026-09', $this->pop->id, 'tagihan', 'dimuka'));
        $this->assertSame([], $this->service()->detail('2026-09', $this->pop->id, 'pelanggan', 'dimuka'));
    }

    #[Test]
    public function diskon_hanya_mencantumkan_invoice_yang_punya_diskon(): void
    {
        $cust = $this->makeCustomer($this->pop);
        $cust->update(['full_name' => 'Pakai Diskon']);
        $this->makeInvoice($this->pop, '2026-09', 100000, customer: $this->makeCustomer($this->pop));
        $this->makeInvoice($this->pop, '2026-09', 80000, discount: 20000, customer: $cust);

        $rows = $this->service()->detail('2026-09', $this->pop->id, 'tagihan', 'diskon');

        $this->assertCount(1, $rows);
        $this->assertSame('Pakai Diskon', $rows[0]['pelanggan']);
        $this->assertEquals(20000, $rows[0]['nominal']);
    }

    #[Test]
    public function piutang_tagihan_mencantumkan_pelanggan_yang_masih_bersisa(): void
    {
        $cust = $this->makeCustomer($this->pop);
        $cust->update(['full_name' => 'Belum Bayar']);
        $this->makeInvoice($this->pop, '2026-09', 150000, customer: $cust);
        $lunas = $this->makeInvoice($this->pop, '2026-09', 100000);
        $this->makePayment($lunas, 100000, '2026-09-05');

        $rows = $this->service()->detail('2026-09', $this->pop->id, 'tagihan', 'piutang');

        $this->assertCount(1, $rows);
        $this->assertSame('Belum Bayar', $rows[0]['pelanggan']);
        $this->assertEquals(150000, $rows[0]['nominal']);
    }

    #[Test]
    public function piutang_bulan_lalu_pembuka_sudah_dan_belum_dibayar_konsisten(): void
    {
        $cust = $this->makeCustomer($this->pop);
        $cust->update(['full_name' => 'Piutang Juli']);
        $julyInvoice = $this->makeInvoice($this->pop, '2026-07', 100000, customer: $cust);
        $this->makePayment($julyInvoice, 30000, '2026-07-20');
        $this->makePayment($julyInvoice, 20000, '2026-08-12');

        $pembuka = $this->service()->detail('2026-08', $this->pop->id, 'piutang_lalu', 'pembuka');
        $this->assertCount(1, $pembuka);
        $this->assertSame('Piutang Juli', $pembuka[0]['pelanggan']);
        $this->assertEquals(70000, $pembuka[0]['nominal']);

        $sudahDibayar = $this->service()->detail('2026-08', $this->pop->id, 'piutang_lalu', 'sudah_dibayar');
        $this->assertCount(1, $sudahDibayar);
        $this->assertEquals(20000, $sudahDibayar[0]['nominal']);

        $belumDibayar = $this->service()->detail('2026-08', $this->pop->id, 'piutang_lalu', 'belum_dibayar');
        $this->assertCount(1, $belumDibayar);
        $this->assertEquals(50000, $belumDibayar[0]['nominal']);
    }

    #[Test]
    public function tak_tertagih_mencantumkan_invoice_yang_dihapus_buku_beserta_alasan(): void
    {
        $cust = $this->makeCustomer($this->pop);
        $cust->update(['full_name' => 'Macet']);
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $cust);

        app(InvoiceWriteOffService::class)->writeOff($invoice, $this->owner, 'pelanggan kabur');

        $rows = $this->service()->detail('2026-09', $this->pop->id, 'piutang_lalu', 'tak_tertagih');

        $this->assertCount(1, $rows);
        $this->assertSame('Macet', $rows[0]['pelanggan']);
        $this->assertEquals(150000, $rows[0]['nominal']);
        $this->assertSame('pelanggan kabur', $rows[0]['keterangan']);
    }

    #[Test]
    public function uang_diterima_lebih_bayar_mencantumkan_payment_yang_overpay(): void
    {
        $cust = $this->makeCustomer($this->pop);
        $cust->update(['full_name' => 'Lebih Bayar']);
        $invoice = $this->makeInvoice($this->pop, '2026-09', 100000, customer: $cust);
        $this->makePayment($invoice, 100000, '2026-09-05', overpay: 25000);

        $rows = $this->service()->detail('2026-09', $this->pop->id, 'uang_diterima', 'lebih_bayar');

        $this->assertCount(1, $rows);
        $this->assertSame('Lebih Bayar', $rows[0]['pelanggan']);
        $this->assertEquals(25000, $rows[0]['nominal']);
    }

    #[Test]
    public function kombinasi_blok_kolom_yang_tidak_valid_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->detail('2026-09', $this->pop->id, 'tagihan', 'total_pembayaran');
    }

    #[Test]
    public function rincian_selalu_live_walau_periode_sudah_ditutup(): void
    {
        Carbon::setTestNow('2026-09-15');
        $invoice = $this->makeInvoice($this->pop, '2026-08', 100000);
        $this->service()->close('2026-08', $this->pop, $this->owner);

        // Transaksi susulan bertanggal Agustus, masuk SETELAH ditutup.
        $this->makePayment($invoice, 100000, '2026-08-25');

        $rows = $this->service()->detail('2026-08', $this->pop->id, 'tagihan', 'bulanan');
        $this->assertCount(1, $rows);
        $this->assertEquals(100000, $rows[0]['nominal']);
    }

    #[Test]
    public function endpoint_http_mengikuti_pop_scope_dan_permission(): void
    {
        $cust = $this->makeCustomer($this->pop);
        $cust->update(['full_name' => 'Http Test']);
        $invoice = $this->makeInvoice($this->pop, '2026-09', 100000, customer: $cust);
        $this->makePayment($invoice, 100000, '2026-09-05');

        $this->actingAs($this->owner)
            ->getJson('/reports/collector-monthly/detail?period=2026-09&pop_id='.$this->pop->id.'&block=tagihan&column=bulanan')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('total', 100000)
            ->assertJsonPath('rows.0.pelanggan', 'Http Test');

        // Kolom komposit (bukan hasil satu query) → 422, bukan 500/data kosong diam-diam.
        $this->actingAs($this->owner)
            ->getJson('/reports/collector-monthly/detail?period=2026-09&pop_id='.$this->pop->id.'&block=tagihan&column=total_pembayaran')
            ->assertStatus(422);

        // POP di luar scope pop_admin → 403.
        $popB = $this->makePop('Cabang Lain');
        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $popAdmin->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $popB->id]);

        $this->actingAs($popAdmin)
            ->getJson('/reports/collector-monthly/detail?period=2026-09&pop_id='.$this->pop->id.'&block=tagihan&column=bulanan')
            ->assertForbidden();

        // Role tanpa permission collector_report.view → 403.
        $teknisi = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->firstOrFail()->id, 'status' => 'active']);
        $this->actingAs($teknisi)
            ->getJson('/reports/collector-monthly/detail?period=2026-09&pop_id='.$this->pop->id.'&block=tagihan&column=bulanan')
            ->assertForbidden();
    }

    #[Test]
    public function export_xlsx_per_sel_terunduh_dengan_nama_file_sesuai_sel(): void
    {
        $cust = $this->makeCustomer($this->pop);
        $cust->update(['full_name' => 'Untuk Ditagih']);
        $this->makeInvoice($this->pop, '2026-09', 150000, customer: $cust);

        $this->actingAs($this->owner)
            ->get('/reports/collector-monthly/detail/export?period=2026-09&pop_id='.$this->pop->id.'&block=tagihan&column=piutang')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename=rincian-tagihan-piutang-2026-09.xlsx');

        // Permission export terpisah dari view — pop_admin tanpa
        // `collector_report.export` tetap bisa lihat modal tapi tidak bisa unduh.
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $noExport = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $noExport->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP])
            ->targets()->create(['pop_id' => $this->pop->id]);
        $this->actingAs($noExport)
            ->get('/reports/collector-monthly/detail/export?period=2026-09&pop_id='.$this->pop->id.'&block=tagihan&column=piutang')
            ->assertForbidden();
    }
}
