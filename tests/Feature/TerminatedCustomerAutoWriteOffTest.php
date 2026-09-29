<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\TerminatedCustomerWriteOffService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCollectorMonthlyScenario;
use Tests\TestCase;

/**
 * Hapus buku otomatis utang pelanggan putus yang masa tenggangnya habis
 * (ADHOC-105). Grace = sisa bulan pemutusan (M) + seluruh bulan M+1; job
 * berjalan tanggal 1 bulan M+2.
 */
class TerminatedCustomerAutoWriteOffTest extends TestCase
{
    use BuildsCollectorMonthlyScenario;
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-01 00:20:00');
        $this->seedBase();
        $this->pop = $this->makePop('Cabang A');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function terminatedCustomer(string $terminatedAt = '2026-09-15 10:00:00', ?Pop $pop = null, ?int $createdBy = null): Customer
    {
        $customer = $this->makeCustomer($pop ?? $this->pop);
        $customer->forceFill([
            'status' => 'terminated',
            'terminated_at' => $terminatedAt,
            'created_by' => $createdBy,
        ])->save();

        return $customer->refresh();
    }

    private function runJob(array $options = []): void
    {
        $this->artisan('billing:write-off-terminated', $options)->assertSuccessful();
    }

    #[Test]
    public function grace_habis_semua_tagihan_belum_lunas_termasuk_denda_dihapus_buku_sebagai_satu_bundel(): void
    {
        $customer = $this->terminatedCustomer('2026-09-15 10:00:00');
        $piutangLama = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $sebagian = $this->makeInvoice($this->pop, '2026-07', 100000, customer: $customer);
        $this->makePayment($sebagian, 40000, '2026-07-20');
        // Denda putus langganan: tipe manual, billing_period = bulan pemutusan.
        $denda = $this->makeInvoice($this->pop, '2026-09', 250000, 'manual', customer: $customer);

        $this->runJob();

        foreach ([$piutangLama, $sebagian, $denda] as $invoice) {
            $invoice->refresh();
            $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $invoice->invoice_status);
            $this->assertSame(TerminatedCustomerWriteOffService::REASON, $invoice->write_off_reason);
            $this->assertNotNull($invoice->written_off_at);
        }
        $this->assertEquals(60000, $sebagian->written_off_amount);
        $this->assertEquals(250000, $denda->written_off_amount);
    }

    #[Test]
    public function batas_tepat_akhir_bulan_kedua_belum_lewat_dan_awal_bulan_ketiga_sudah(): void
    {
        $customer = $this->terminatedCustomer('2026-09-15 10:00:00');
        $invoice = $this->makeInvoice($this->pop, '2026-09', 250000, 'manual', customer: $customer);

        // 31 Oktober 23:59:59 masih di dalam masa tenggang (M+1 = Oktober).
        Carbon::setTestNow('2026-10-31 23:59:59');
        $this->runJob();
        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->refresh()->invoice_status);

        // 1 November 00:00 = bulan M+2, masa tenggang habis.
        Carbon::setTestNow('2026-11-01 00:00:00');
        $this->runJob();
        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function pemutusan_bulan_berjalan_belum_kena_masa_tenggangnya_belum_habis(): void
    {
        // Putus 15 Oktober → M = Oktober, baru kena tanggal 1 Desember.
        $customer = $this->terminatedCustomer('2026-10-15 10:00:00');
        $invoice = $this->makeInvoice($this->pop, '2026-09', 150000, customer: $customer);

        $this->runJob();

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->refresh()->invoice_status);

        Carbon::setTestNow('2026-12-01 00:20:00');
        $this->runJob();

        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function skema_1_lunas_dalam_masa_tenggang_tidak_disentuh(): void
    {
        $customer = $this->terminatedCustomer('2026-09-15 10:00:00');
        $invoice = $this->makeInvoice($this->pop, '2026-09', 250000, 'manual', customer: $customer);
        $this->makePayment($invoice, 250000, '2026-10-28');

        $this->runJob();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LUNAS, $invoice->invoice_status);
        $this->assertNull($invoice->written_off_at);
    }

    #[Test]
    public function pelanggan_yang_sudah_tidak_terminated_dan_pelanggan_aktif_dilewati(): void
    {
        $sudahKembali = $this->terminatedCustomer('2026-09-15 10:00:00');
        $sudahKembali->forceFill(['status' => 'active'])->save();
        $invoiceKembali = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $sudahKembali);

        $aktif = $this->makeCustomer($this->pop);
        $invoiceAktif = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $aktif);

        $this->runJob();

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoiceKembali->refresh()->invoice_status);
        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoiceAktif->refresh()->invoice_status);
    }

    #[Test]
    public function pelanggan_legacy_tanpa_terminated_at_dilewati(): void
    {
        $customer = $this->terminatedCustomer('2026-09-15 10:00:00');
        $customer->forceFill(['terminated_at' => null])->save();
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);

        $this->runJob();

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function idempoten_dijalankan_dua_kali_tidak_menimpa_hapus_buku_pertama(): void
    {
        $customer = $this->terminatedCustomer('2026-09-15 10:00:00');
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);

        $this->runJob();
        $pertama = $invoice->refresh()->written_off_at;

        Carbon::setTestNow('2026-11-01 05:00:00');
        $this->runJob();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $invoice->invoice_status);
        $this->assertTrue($pertama->equalTo($invoice->written_off_at));
    }

    #[Test]
    public function satu_invoice_yang_ditolak_write_off_tidak_menghentikan_invoice_lain(): void
    {
        $customer = $this->terminatedCustomer('2026-09-15 10:00:00');
        $piutang = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        // Invoice manual periode berjalan bukan piutang → writeOff() menolak.
        $berjalan = $this->makeInvoice($this->pop, '2026-11', 90000, 'manual', customer: $customer);

        $this->runJob();

        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $piutang->refresh()->invoice_status);
        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $berjalan->refresh()->invoice_status);
    }

    #[Test]
    public function dry_run_tidak_mengubah_data_dan_tidak_mengirim_notifikasi(): void
    {
        $owner = $this->ownerUser();
        $customer = $this->terminatedCustomer('2026-09-15 10:00:00');
        $invoice = $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);

        $this->artisan('billing:write-off-terminated', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN] 1 tagihan')
            ->assertSuccessful();

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->refresh()->invoice_status);
        $this->assertSame(0, $owner->notifications()->count());
    }

    #[Test]
    public function dry_run_tidak_menghitung_tagihan_yang_sisanya_nol_karena_eksekusi_nyata_menolaknya(): void
    {
        $customer = $this->terminatedCustomer('2026-09-15 10:00:00');
        $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $sisaNol = $this->makeInvoice($this->pop, '2026-07', 100000, customer: $customer);
        $sisaNol->forceFill(['remaining_amount' => 0])->save();

        $this->artisan('billing:write-off-terminated', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN] 1 tagihan dari 1 pelanggan putus dihapus buku (total Rp 150.000). 1 tagihan dilewati')
            ->assertSuccessful();

        // Eksekusi nyata harus konsisten dengan simulasi.
        $this->artisan('billing:write-off-terminated')
            ->expectsOutputToContain('1 tagihan dari 1 pelanggan putus dihapus buku (total Rp 150.000). 1 tagihan dilewati')
            ->assertSuccessful();
    }

    #[Test]
    public function notifikasi_sekali_per_pelanggan_ke_pendaftar_dan_penyetuju_dalam_pop_scope_saja(): void
    {
        $owner = $this->ownerUser();
        $pendaftar = User::factory()->create([
            'role_id' => Role::where('code', 'helpdesk')->firstOrFail()->id,
            'status' => 'active',
        ]);
        $adminDalamScope = $this->adminWithScope($this->pop);
        $adminLuarScope = $this->adminWithScope($this->makePop('Cabang B'));

        $customer = $this->terminatedCustomer('2026-09-15 10:00:00', createdBy: $pendaftar->id);
        $this->makeInvoice($this->pop, '2026-08', 150000, customer: $customer);
        $this->makeInvoice($this->pop, '2026-07', 100000, customer: $customer);

        $this->runJob();

        foreach ([$owner, $pendaftar, $adminDalamScope] as $penerima) {
            $notifikasi = $penerima->notifications()->get();
            $this->assertCount(1, $notifikasi, "Penerima {$penerima->id} harus dapat tepat satu notifikasi (bukan per invoice).");
            $this->assertStringContainsString('Piutang Tak Tertagih', $notifikasi->first()->data['title']);
            $this->assertStringContainsString('2 tagihan', $notifikasi->first()->data['message']);
        }

        $this->assertSame(0, $adminLuarScope->notifications()->count(), 'Admin cabang lain tidak boleh dikabari.');
    }

    private function adminWithScope(Pop $pop): User
    {
        $role = Role::where('code', 'admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    #[Test]
    public function job_dijadwalkan_tanggal_satu_setelah_close_period(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'billing:write-off-terminated'));

        $this->assertCount(1, $events);
        $this->assertSame('20 0 1 * *', $events->first()->expression);
    }
}
