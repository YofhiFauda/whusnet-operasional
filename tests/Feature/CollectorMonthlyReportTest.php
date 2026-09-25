<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\PeriodClosing;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CollectorMonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCollectorMonthlyScenario;
use Tests\TestCase;

class CollectorMonthlyReportTest extends TestCase
{
    use BuildsCollectorMonthlyScenario;
    use RefreshDatabase;

    private Pop $branchA;

    private Pop $miniA;

    private Pop $branchB;

    private User $collector;

    protected function setUp(): void
    {
        parent::setUp();
        // Hari "sekarang" dibekukan: Agustus = periode lalu (bisa ditutup),
        // September = periode berjalan.
        Carbon::setTestNow('2026-09-15 10:00:00');
        $this->seedBase();

        $this->branchA = $this->makePop('Cabang A');
        $this->miniA = $this->makePop('Mini A', 'mini_pop', $this->branchA);
        $this->branchB = $this->makePop('Cabang B');
        $this->collector = User::factory()->create(['status' => 'active']);

        $this->buildAugustScenarioForBranchA();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Cabang A, Agustus 2026:
     *  - inv1 100k dibayar langsung ke admin (dimuka), inv2 (di mini-POP) 100k via kolektor,
     *    inv3 100k belum dibayar.
     *  - piutang Juli cust3: 100k, bayar 30k sebelum 1 Agustus, 20k pada Agustus → sisa 50k.
     *  - aktivasi 50k, tagihan lain 25k + lebih bayar 5k.
     */
    private function buildAugustScenarioForBranchA(): void
    {
        $cust3 = $this->makeCustomer($this->branchA);

        $inv1 = $this->makeInvoice($this->branchA, '2026-08', 100000);
        $inv2 = $this->makeInvoice($this->miniA, '2026-08', 100000);
        $this->makeInvoice($this->branchA, '2026-08', 100000, customer: $cust3);

        $this->makePayment($inv1, 100000, '2026-08-05');
        $this->makePayment($inv2, 100000, '2026-08-10', $this->collector);

        $julyInvoice = $this->makeInvoice($this->branchA, '2026-07', 100000, customer: $cust3);
        $this->makePayment($julyInvoice, 30000, '2026-07-20');
        $this->makePayment($julyInvoice, 20000, '2026-08-12');

        $aktivasi = $this->makeInvoice($this->branchA, '2026-08', 50000, 'awal');
        $this->makePayment($aktivasi, 50000, '2026-08-15');

        $lain = $this->makeInvoice($this->branchA, '2026-08', 25000, 'insidental');
        $this->makePayment($lain, 25000, '2026-08-20', overpay: 5000);
    }

    private function service(): CollectorMonthlyReportService
    {
        return app(CollectorMonthlyReportService::class);
    }

    private function figuresA(string $period = '2026-08'): array
    {
        return $this->service()->figures($period, [$this->branchA->id])[$this->branchA->id];
    }

    #[Test]
    public function tagihan_terbit_bulanan_dan_total_pembayaran_terhitung_benar_dan_melipat_mini_pop(): void
    {
        $f = $this->figuresA();

        $this->assertEquals(300000, $f['tagihan']['tagihan_terbit']);
        // Belum ada payment metode `saldo` (ADHOC-92 belum diimplementasikan) —
        // Dimuka wajib 0 sekarang, bukan bug. Lihat docblock fillTagihanDanPelanggan().
        $this->assertEquals(0, $f['tagihan']['dimuka']);
        $this->assertEquals(200000, $f['tagihan']['bulanan']);
        $this->assertEquals(0, $f['tagihan']['diskon']);
        $this->assertEquals(200000, $f['tagihan']['total_pembayaran']);
        $this->assertEquals(100000, $f['tagihan']['piutang']);

        // inv2 ada di mini-POP tapi terhitung di cabang induknya. Admin &
        // kolektor sama-sama masuk 'bulanan' (tunai) sekarang — tidak lagi
        // dibedakan; 'dimuka' Pelanggan khusus lunas lewat saldo.
        $this->assertSame(['total' => 3, 'dimuka' => 0, 'sudah_bayar' => 2, 'belum_bayar' => 1], $f['pelanggan']);
    }

    #[Test]
    public function diskon_ikut_menutup_total_pembayaran_walau_invoicenya_belum_lunas(): void
    {
        // POP terpisah supaya tidak mengganggu total fixture Agustus di atas.
        $branch = $this->makePop('Cabang Diskon');
        // subtotal 100.000, diskon 20.000 → total_amount 80.000, belum dibayar sama sekali.
        $this->makeInvoice($branch, '2026-08', 80000, discount: 20000);

        $f = $this->service()->figures('2026-08', [$branch->id])[$branch->id]['tagihan'];

        $this->assertEquals(80000, $f['tagihan_terbit']);
        $this->assertEquals(20000, $f['diskon']);
        $this->assertEquals(0, $f['bulanan']);
        $this->assertEquals(0, $f['dimuka']);
        // Total Pembayaran = bulanan(0) + dimuka(0) + diskon(20000) — diskon
        // ikut "menutup" tagihan walau belum ada uang tunai/saldo masuk.
        $this->assertEquals(20000, $f['total_pembayaran']);
        $this->assertEquals(80000, $f['piutang']);
    }

    #[Test]
    public function piutang_bulan_lalu_dihitung_dari_posisi_awal_bulan(): void
    {
        $f = $this->figuresA()['piutang_lalu'];

        $this->assertEquals(70000, $f['pembuka']);
        $this->assertEquals(20000, $f['sudah_dibayar']);
        $this->assertEquals(50000, $f['belum_dibayar']);
        $this->assertEquals(0, $f['tak_tertagih']);
    }

    #[Test]
    public function uang_diterima_berbasis_kas_dan_cocok_dengan_total_laporan_pembayaran(): void
    {
        $u = $this->figuresA()['uang_diterima'];

        $this->assertEquals(200000, $u['bulanan']);
        $this->assertEquals(20000, $u['piutang']);
        $this->assertEquals(50000, $u['aktivasi']);
        $this->assertEquals(25000, $u['lainnya']);
        $this->assertEquals(5000, $u['lebih_bayar']);
        $this->assertEquals(300000, $u['total']);

        // Rekonsiliasi: empat kolom selain Lebih Bayar = total /reports/payments Agustus.
        $owner = $this->ownerUser();
        $this->actingAs($owner)
            // Tanpa filter pop_id: laporan pembayaran memfilter pop_id PERSIS,
            // sedangkan Cabang A melipat mini-POP-nya.
            ->get('/reports/payments?start_date=2026-08-01&end_date=2026-08-31')
            ->assertOk()
            ->assertViewHas('totalValidSum', fn ($sum) => (float) $sum === 295000.0);
    }

    #[Test]
    public function pembayaran_bulan_baru_atas_invoice_lama_masuk_piutang_bulan_baru_dan_tidak_menggeser_agustus(): void
    {
        $august = $this->figuresA();

        $inv3 = Invoice::query()->where('billing_period', '2026-08')->where('remaining_amount', 100000)->firstOrFail();
        $this->makePayment($inv3, 100000, '2026-09-03');

        $septemberFigures = $this->figuresA('2026-09');
        $this->assertEquals(100000, $septemberFigures['uang_diterima']['piutang']);
        $this->assertEquals(0, $septemberFigures['uang_diterima']['bulanan']);
        // Pembuka September = sisa Juli 50k + Agustus inv3 100k.
        $this->assertEquals(150000, $septemberFigures['piutang_lalu']['pembuka']);
        $this->assertEquals(100000, $septemberFigures['piutang_lalu']['sudah_dibayar']);

        // Blok Agustus dihitung "per akhir Agustus": bayar 3 September tak ikut.
        $this->assertEquals($august['tagihan'], $this->figuresA()['tagihan']);
        $this->assertEquals($august['uang_diterima'], $this->figuresA()['uang_diterima']);
    }

    #[Test]
    public function persentase_tidak_meledak_saat_pembagi_nol(): void
    {
        $this->assertSame(0.0, CollectorMonthlyReportService::percentage(100, 0));
        $this->assertSame(66.7, CollectorMonthlyReportService::percentage(200000, 300000));
    }

    #[Test]
    public function tutup_periode_membekukan_angka_walau_ada_pembayaran_susulan(): void
    {
        $before = $this->figuresA();

        $this->service()->closePeriod('2026-08');

        $inv3 = Invoice::query()->where('billing_period', '2026-08')->where('remaining_amount', 100000)->firstOrFail();
        // Susulan bertanggal Agustus (mis. impor legacy) — masuk SETELAH ditutup.
        $this->makePayment($inv3, 100000, '2026-08-25');

        $report = $this->service()->report('2026-08', collect([$this->branchA]));

        $this->assertEquals($before['tagihan'], $report['rows'][0]['figures']['tagihan']);
        $this->assertNotNull($report['rows'][0]['closing']);
        $this->assertTrue($report['rows'][0]['drift'], 'Angka live sudah beda dari snapshot → peringatan drift.');
        $this->assertDatabaseHas('audit_logs', ['module' => 'laporan', 'action' => 'periode_ditutup']);
    }

    #[Test]
    public function periode_berjalan_tidak_bisa_ditutup_dan_tutup_ulang_idempoten(): void
    {
        try {
            $this->service()->closePeriod('2026-09');
            $this->fail('Periode berjalan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }

        // Semua POP pusat/cabang (A & B) dibekukan; mini-POP tidak jadi baris sendiri.
        $this->assertSame(2, $this->service()->closePeriod('2026-08'));
        $this->assertEqualsCanonicalizing(
            [$this->branchA->id, $this->branchB->id],
            PeriodClosing::query()->pluck('pop_id')->all(),
        );

        // Jalan ulang (mis. scheduler dobel) tidak menimpa snapshot pertama.
        $this->assertSame(0, $this->service()->closePeriod('2026-08'));
        $this->assertDatabaseCount('period_closings', 2);
    }

    #[Test]
    public function scheduler_tutup_buku_otomatis_tanggal_1_untuk_bulan_lalu(): void
    {
        Carbon::setTestNow('2026-09-01 00:10:00');

        $this->artisan('billing:close-period')->assertSuccessful();

        $this->assertEqualsCanonicalizing(['2026-08'], PeriodClosing::query()->distinct()->pluck('period')->all());
        $log = AuditLog::query()->where('action', 'periode_ditutup')->firstOrFail();
        $this->assertNull($log->user_id, 'Ditutup sistem, bukan orang.');

        // Menambal bulan yang terlewat saat scheduler mati.
        $this->artisan('billing:close-period', ['--period' => '2026-07'])->assertSuccessful();
        $this->assertTrue(PeriodClosing::query()->where('period', '2026-07')->exists());

        // Periode berjalan tetap ditolak.
        $this->artisan('billing:close-period', ['--period' => '2026-09'])->assertFailed();
    }

    #[Test]
    public function migration_menghapus_permission_tutup_dan_buka_ulang_yang_yatim(): void
    {
        // Simulasi DB produksi lama: dua permission masih ada & menempel di role.
        $featureId = DB::table('features')->where('code', 'collector_report')->value('id');
        $admin = Role::where('code', 'admin')->firstOrFail();

        foreach (['approve', 'cancel'] as $actionCode) {
            $permissionId = DB::table('permissions')->insertGetId([
                'feature_id' => $featureId,
                'action_id' => DB::table('actions')->where('code', $actionCode)->value('id'),
                'code' => "collector_report.{$actionCode}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('role_permissions')->insert(['role_id' => $admin->id, 'permission_id' => $permissionId]);
        }

        (require database_path('migrations/2026_09_23_131132_remove_collector_report_close_reopen_permissions.php'))->up();

        $this->assertDatabaseMissing('permissions', ['code' => 'collector_report.approve']);
        $this->assertDatabaseMissing('permissions', ['code' => 'collector_report.cancel']);
        // Permission lain di fitur yang sama tidak ikut terhapus.
        $this->assertDatabaseHas('permissions', ['code' => 'collector_report.view']);
        $this->assertDatabaseHas('permissions', ['code' => 'collector_report.export']);
    }

    #[Test]
    public function tombol_tutup_dan_buka_ulang_manual_sudah_tidak_ada(): void
    {
        $owner = $this->ownerUser();
        $this->service()->closePeriod('2026-08');

        $this->actingAs($owner)->post('/reports/collector-monthly/close', ['period' => '2026-08'])->assertNotFound();
        $this->actingAs($owner)
            ->post('/reports/collector-monthly/reopen', ['period' => '2026-08', 'pop_id' => $this->branchA->id, 'reason' => 'salah hitung'])
            ->assertNotFound();
        $this->assertDatabaseCount('period_closings', 2);

        $this->actingAs($owner)->get('/reports/collector-monthly?period=2026-08')
            ->assertOk()
            ->assertSee('Terkunci')
            ->assertDontSee('Tutup Periode')
            ->assertDontSee('Buka Ulang');
    }

    #[Test]
    public function halaman_menampilkan_empat_blok_dan_export_xlsx_terunduh(): void
    {
        $owner = $this->ownerUser();

        $this->actingAs($owner)->get('/reports/collector-monthly?period=2026-08')
            ->assertOk()
            ->assertSee('Cabang A')
            ->assertSee('Cabang B')
            ->assertDontSee('Mini A')
            ->assertSee('Piutang Bulan Lalu')
            ->assertSee('Uang Diterima');

        $this->actingAs($owner)->get('/reports/collector-monthly/export?period=2026-08')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=laporan-admin-collector-2026-08.xlsx');
    }

    #[Test]
    public function pop_admin_hanya_melihat_pop_sendiri_dan_export_pop_lain_ditolak(): void
    {
        $popAdmin = $this->scopedUser('pop_admin', $this->branchA);

        $this->actingAs($popAdmin)->get('/reports/collector-monthly?period=2026-08')
            ->assertOk()
            ->assertSee('Cabang A')
            ->assertDontSee('Cabang B');

        $this->actingAs($popAdmin)
            ->get('/reports/collector-monthly/export?period=2026-08&pop_id='.$this->branchB->id)
            ->assertForbidden();
    }

    #[Test]
    public function role_tanpa_permission_ditolak(): void
    {
        $teknisi = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($teknisi)->get('/reports/collector-monthly')->assertForbidden();
    }

    private function scopedUser(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }
}
