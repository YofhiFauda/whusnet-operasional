<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\PaymentBatch;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CollectorBalanceService;
use App\Services\CollectorPaymentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCollectorMonthlyScenario;
use Tests\TestCase;

/**
 * Laporan Bayar Kolektor — tabel "Bayar Wifi Cash" per kolektor (ADHOC-90).
 */
class CollectorPaymentReportTest extends TestCase
{
    use BuildsCollectorMonthlyScenario;
    use RefreshDatabase;

    private Pop $popA;

    private Pop $popB;

    private User $kolektorA;

    private User $kolektorB;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');
        $this->seedBase();

        $this->popA = $this->makePop('Cabang A');
        $this->popB = $this->makePop('Cabang B');
        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $this->kolektorA = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active', 'name' => 'Kolektor Satu']);
        $this->kolektorB = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active', 'name' => 'Kolektor Dua']);
        $this->owner = $this->ownerUser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function batch(User $collector): PaymentBatch
    {
        return PaymentBatch::create([
            'idempotency_key' => 'key-'.++$this->seq,
            'submitted_by' => $this->owner->id,
            'collector_id' => $collector->id,
            'submitted_at' => now(),
        ]);
    }

    private function build(?int $collectorId = null, string $start = '2026-09-01', string $end = '2026-09-30', ?string $method = null, ?User $viewer = null): array
    {
        return app(CollectorPaymentReportService::class)->build($viewer ?? $this->owner, $collectorId, $start, $end, $method);
    }

    #[Test]
    public function pembayaran_satu_sesi_input_jadi_satu_kelompok_dengan_total_sub(): void
    {
        $batch = $this->batch($this->kolektorA);
        $i1 = $this->makeInvoice($this->popA, '2026-09', 110000);
        $i2 = $this->makeInvoice($this->popA, '2026-09', 150000);
        $i3 = $this->makeInvoice($this->popA, '2026-09', 70000);

        $this->makePayment($i1, 110000, '2026-09-03', $this->kolektorA, batchId: $batch->id);
        $this->makePayment($i2, 150000, '2026-09-03', $this->kolektorA, batchId: $batch->id);
        // Pembayaran tanpa batch = kelompok sendiri.
        $this->makePayment($i3, 70000, '2026-09-08', $this->kolektorA);

        $report = $this->build();

        $this->assertCount(2, $report['groups']);
        $this->assertCount(2, $report['groups'][0]['payments']);
        $this->assertEquals(260000, $report['groups'][0]['subtotal']);
        $this->assertEquals(70000, $report['groups'][1]['subtotal']);
        $this->assertEquals(330000, $report['total']);
        $this->assertSame(3, $report['count']);
        // Urut menurut tanggal: 3 Sep sebelum 8 Sep.
        $this->assertSame('2026-09-03', $report['groups'][0]['date']->toDateString());
    }

    #[Test]
    public function hanya_uang_kolektor_yang_valid_dan_dalam_rentang_tanggal(): void
    {
        $inv = fn (float $t = 100000) => $this->makeInvoice($this->popA, '2026-09', $t);

        $this->makePayment($inv(), 100000, '2026-09-05', $this->kolektorA);                                  // masuk
        $this->makePayment($inv(), 200000, '2026-09-06');                                                   // bayar di kantor
        $this->makePayment($inv(), 300000, '2026-09-07', $this->kolektorA, status: 'ditolak');              // ditolak
        $this->makePayment($inv(), 400000, '2026-08-31', $this->kolektorA);                                 // sebelum rentang
        $this->makePayment($inv(), 500000, '2026-10-01', $this->kolektorA);                                 // sesudah rentang
        $this->makePayment($inv(), 600000, '2026-09-30', $this->kolektorA);                                 // batas akhir — masuk

        $report = $this->build();

        $this->assertEquals(700000, $report['total']);
        $this->assertSame(2, $report['count']);
    }

    #[Test]
    public function tanggal_lapangan_dipakai_bila_ada_dan_jatuh_ke_tanggal_bayar_bila_kosong(): void
    {
        // Diinput admin 2 Okt, tapi uangnya diterima di lapangan 29 Sep → Bulan September.
        $this->makePayment($this->makeInvoice($this->popA, '2026-09', 90000), 90000, '2026-10-02', $this->kolektorA, collectedDate: '2026-09-29');
        // Tanpa collected_date → pakai payment_date.
        $this->makePayment($this->makeInvoice($this->popA, '2026-09', 80000), 80000, '2026-09-10', $this->kolektorA);

        $this->assertEquals(170000, $this->build()['total']);
        $this->assertEquals(0, $this->build(start: '2026-10-01', end: '2026-10-31')['total']);
    }

    #[Test]
    public function filter_kolektor_dan_metode(): void
    {
        $this->makePayment($this->makeInvoice($this->popA, '2026-09', 100000), 100000, '2026-09-05', $this->kolektorA);
        $this->makePayment($this->makeInvoice($this->popA, '2026-09', 110000), 110000, '2026-09-05', $this->kolektorB, method: 'transfer');

        $this->assertEquals(210000, $this->build()['total']);
        $this->assertEquals(100000, $this->build($this->kolektorA->id)['total']);
        $this->assertEquals(110000, $this->build(method: 'transfer')['total']);
        $this->assertEquals(0, $this->build($this->kolektorA->id, method: 'transfer')['total']);
    }

    #[Test]
    public function lebih_bayar_tidak_ikut_jumlah_supaya_sama_dengan_saldo_kolektor(): void
    {
        $this->makePayment($this->makeInvoice($this->popA, '2026-09', 100000), 100000, '2026-09-05', $this->kolektorA, overpay: 20000);

        $this->assertEquals(100000, $this->build()['total']);
        $this->assertEquals(100000, app(CollectorBalanceService::class)->balance($this->kolektorA));
    }

    #[Test]
    public function pop_admin_hanya_melihat_pembayaran_di_pop_scope_nya(): void
    {
        $this->makePayment($this->makeInvoice($this->popA, '2026-09', 100000), 100000, '2026-09-05', $this->kolektorA);
        $this->makePayment($this->makeInvoice($this->popB, '2026-09', 250000), 250000, '2026-09-05', $this->kolektorA);

        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $popAdmin = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $popAdmin->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->popA->id]);

        $this->assertEquals(100000, $this->build(viewer: $popAdmin)['total']);
        $this->assertEquals(350000, $this->build()['total']);
    }

    #[Test]
    public function halaman_menampilkan_tabel_kas_terkumpul_dan_export_terunduh(): void
    {
        $batch = $this->batch($this->kolektorA);
        $this->makePayment($this->makeInvoice($this->popA, '2026-09', 110000), 110000, '2026-09-03', $this->kolektorA, batchId: $batch->id, note: 'lunas ags');

        $this->actingAs($this->owner)
            ->get('/reports/collector-payments?start_date=2026-09-01&end_date=2026-09-30')
            ->assertOk()
            ->assertSee('Kas Terkumpul')
            ->assertSee('Rp 110.000')
            ->assertSee('lunas ags')
            ->assertSee('Kolektor Satu');

        $this->actingAs($this->owner)
            ->get('/reports/collector-payments/export?start_date=2026-09-01&end_date=2026-09-30')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=laporan-bayar-kolektor-2026-09-01_2026-09-30.xlsx');
    }

    #[Test]
    public function rentang_terbalik_ditolak_dan_role_tanpa_permission_403(): void
    {
        $this->actingAs($this->owner)
            ->get('/reports/collector-payments?start_date=2026-09-30&end_date=2026-09-01')
            ->assertSessionHasErrors('end_date');

        $teknisi = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->firstOrFail()->id, 'status' => 'active']);
        $this->actingAs($teknisi)->get('/reports/collector-payments')->assertForbidden();
        $this->actingAs($teknisi)->get('/reports/collector-payments/export')->assertForbidden();
    }
}
