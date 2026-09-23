<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PeriodClosing;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\CollectorMonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCollectorMonthlyScenario;
use Tests\TestCase;

/**
 * Hapus buku piutang → status Tak Tertagih (ADHOC-90).
 */
class InvoiceWriteOffTest extends TestCase
{
    use BuildsCollectorMonthlyScenario;
    use RefreshDatabase;

    private Pop $pop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');
        $this->seedBase();

        $this->pop = $this->makePop('Cabang A');
        $this->owner = $this->ownerUser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function piutang(float $total = 150000): Invoice
    {
        return $this->makeInvoice($this->pop, '2026-08', $total);
    }

    #[Test]
    public function piutang_bisa_dihapus_buku_dan_keluar_dari_hitungan_piutang(): void
    {
        $invoice = $this->piutang();
        $this->assertTrue(Invoice::query()->piutang()->whereKey($invoice->id)->exists());

        $this->actingAs($this->owner)
            ->post(route('invoices.write-off', $invoice), ['reason' => 'Pelanggan pindah, tidak bisa dihubungi'])
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $invoice->invoice_status);
        $this->assertEquals(150000, $invoice->written_off_amount);
        $this->assertSame($this->owner->id, $invoice->written_off_by);
        $this->assertSame('Pelanggan pindah, tidak bisa dihubungi', $invoice->write_off_reason);
        // remaining_amount sengaja utuh supaya bisa dibatalkan.
        $this->assertEquals(150000, $invoice->remaining_amount);
        $this->assertFalse(Invoice::query()->piutang()->whereKey($invoice->id)->exists());
    }

    #[Test]
    public function alasan_wajib(): void
    {
        $invoice = $this->piutang();

        $this->actingAs($this->owner)
            ->post(route('invoices.write-off', $invoice), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function tagihan_bulan_berjalan_atau_yang_sudah_lunas_tidak_bisa_dihapus_buku(): void
    {
        $berjalan = $this->makeInvoice($this->pop, '2026-09', 150000);
        $lunas = $this->piutang(100000);
        $this->makePayment($lunas, 100000, '2026-09-01');

        foreach ([$berjalan, $lunas] as $invoice) {
            $this->actingAs($this->owner)
                ->post(route('invoices.write-off', $invoice), ['reason' => 'coba'])
                ->assertSessionHasErrors('reason');
        }

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $berjalan->refresh()->invoice_status);
    }

    #[Test]
    public function invoice_tak_tertagih_menolak_pembayaran_dan_tidak_hidup_lagi_lewat_recalculate(): void
    {
        $invoice = $this->piutang();
        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet']);

        $this->actingAs($this->owner)
            ->post(route('invoices.payments.store', $invoice), [
                'amount' => 150000,
                'payment_date' => '2026-09-15',
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);

        $invoice->refresh()->recalculateFromPayments();
        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function batalkan_hapus_buku_mengembalikan_piutang(): void
    {
        $invoice = $this->piutang();
        $this->makePayment($invoice, 40000, '2026-08-20');
        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet']);

        $this->assertEquals(110000, $invoice->refresh()->written_off_amount);

        $this->actingAs($this->owner)
            ->post(route('invoices.write-off.reverse', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::SEBAGIAN, $invoice->invoice_status);
        $this->assertEquals(110000, $invoice->remaining_amount);
        $this->assertNull($invoice->written_off_at);
        $this->assertNull($invoice->written_off_amount);
        $this->assertTrue(Invoice::query()->piutang()->whereKey($invoice->id)->exists());
    }

    #[Test]
    public function nominal_tak_tertagih_masuk_laporan_bulan_penghapusan_dan_mengurangi_sisa_piutang(): void
    {
        $invoice = $this->piutang(150000);
        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet']);

        $service = app(CollectorMonthlyReportService::class);

        // Dihapus buku 15 September → Blok 2 September memuatnya, Oktober tidak lagi.
        $sept = $service->figures('2026-09', [$this->pop->id])[$this->pop->id]['piutang_lalu'];
        $this->assertEquals(150000, $sept['pembuka']);
        $this->assertEquals(150000, $sept['belum_dibayar']);
        $this->assertEquals(150000, $sept['tak_tertagih']);

        $okt = $service->figures('2026-10', [$this->pop->id])[$this->pop->id]['piutang_lalu'];
        $this->assertEquals(0, $okt['pembuka']);
    }

    #[Test]
    public function hapus_buku_ditolak_saat_periode_berjalan_pop_itu_sudah_ditutup(): void
    {
        $invoice = $this->piutang();
        PeriodClosing::create([
            'period' => '2026-09',
            'pop_id' => $this->pop->id,
            'figures' => CollectorMonthlyReportService::emptyFigures(),
            'closed_by' => $this->owner->id,
            'closed_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->post(route('invoices.write-off', $invoice), ['reason' => 'macet'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function batalkan_hapus_buku_ditolak_kalau_periodenya_sudah_ditutup(): void
    {
        $invoice = $this->piutang();
        $this->actingAs($this->owner)->post(route('invoices.write-off', $invoice), ['reason' => 'macet']);

        PeriodClosing::create([
            'period' => '2026-09',
            'pop_id' => $this->pop->id,
            'figures' => CollectorMonthlyReportService::emptyFigures(),
            'closed_by' => $this->owner->id,
            'closed_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->post(route('invoices.write-off.reverse', $invoice))
            ->assertSessionHasErrors('reason');

        $this->assertSame(InvoiceStatus::TAK_TERTAGIH, $invoice->refresh()->invoice_status);
    }

    #[Test]
    public function role_tanpa_permission_approve_invoice_ditolak(): void
    {
        $invoice = $this->piutang();
        $helpdesk = User::factory()->create([
            'role_id' => Role::where('code', 'helpdesk')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($helpdesk)
            ->post(route('invoices.write-off', $invoice), ['reason' => 'coba'])
            ->assertForbidden();
    }
}
