<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\User;
use App\Services\CollectorMonthlyReportService;
use App\Support\BookPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCollectorMonthlyScenario;
use Tests\TestCase;

/**
 * Tutup buku otomatis saat bulan berganti & terkunci permanen. Periode lama
 * tidak bisa diotak-atik; piutang yang dibayar belakangan masuk bulan uang
 * diterima.
 */
class PeriodeTutupBukuTerkunciTest extends TestCase
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

    #[Test]
    public function kunci_periode_diturunkan_dari_kalender(): void
    {
        $this->assertTrue(BookPeriod::isLocked('2026-08'));
        $this->assertFalse(BookPeriod::isLocked('2026-09'));
        $this->assertFalse(BookPeriod::isLocked(null));
        $this->assertSame('2026-09-01', BookPeriod::firstOpenDate());

        Carbon::setTestNow('2026-10-01 00:00:01');
        $this->assertTrue(BookPeriod::isLocked('2026-09'));
    }

    #[Test]
    public function pembayaran_bertanggal_bulan_terkunci_ditolak(): void
    {
        $invoice = $this->makeInvoice($this->pop, '2026-08', 100000);

        $this->actingAs($this->owner)
            ->post(route('invoices.payments.store', $invoice), [
                'payment_date' => '2026-08-31',
                'payment_method' => 'cash',
                'amount' => 100000,
            ])
            ->assertSessionHasErrors('payment_date');

        $this->assertSame(0, $invoice->payments()->count());
    }

    #[Test]
    public function pelunasan_piutang_bulan_lalu_dicatat_di_bulan_berjalan(): void
    {
        $invoice = $this->makeInvoice($this->pop, '2026-08', 100000);
        $this->assertTrue($invoice->isPiutang());

        $this->actingAs($this->owner)
            ->post(route('invoices.payments.store', $invoice), [
                'payment_date' => '2026-09-01',
                'payment_method' => 'cash',
                'amount' => 100000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('lunas', $invoice->refresh()->invoice_status->value);
        $this->assertSame('2026-09-01', $invoice->payments()->first()->payment_date->toDateString());
    }

    #[Test]
    public function pembayaran_bulan_terkunci_bisa_dikembalikan_dan_dibukukan_di_bulan_berjalan(): void
    {
        $report = app(CollectorMonthlyReportService::class);
        $invoice = $this->makeInvoice($this->pop, '2026-08', 100000);
        $payment = $this->makePayment($invoice, 100000, '2026-08-20');
        $report->closePeriod('2026-08');

        $this->actingAs($this->owner)->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Kembalikan Pembayaran')
            ->assertSee('onclick="openRejectModal()"', false);

        $this->actingAs($this->owner)
            ->post(route('payments.reject', $payment), ['reject_reason' => 'Salah input kasir'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PaymentStatus::DITOLAK, $payment->refresh()->payment_status);
        // Tagihan kembali jadi piutang.
        $this->assertTrue(Invoice::find($invoice->id)->isPiutang());

        // Buku Agustus tidak bergeser: hitung-ulang live = snapshot, tanpa ⚠.
        $august = $report->report('2026-08', collect([$this->pop]))['rows'][0];
        $this->assertFalse($august['drift']);
        $this->assertEquals(100000, $august['figures']['uang_diterima']['total']);

        // Pengembaliannya dibukukan di September sebagai pengurang.
        $september = $report->figures('2026-09', [$this->pop->id])[$this->pop->id]['uang_diterima'];
        $this->assertEquals(100000, $september['dikembalikan']);
        $this->assertEquals(-100000, $september['total']);

        $rows = $report->detail('2026-09', $this->pop->id, 'uang_diterima', 'dikembalikan');
        $this->assertCount(1, $rows);
        $this->assertEquals(100000, $rows[0]['nominal']);
        $this->assertEquals(-100000, array_sum(array_column($report->detail('2026-09', $this->pop->id, 'uang_diterima', 'total'), 'nominal')));
    }

    #[Test]
    public function pembayaran_yang_dikembalikan_disembunyikan_dari_daftar(): void
    {
        $invoice = $this->makeInvoice($this->pop, '2026-09', 100000);
        $kept = $this->makePayment($invoice, 40000, '2026-09-05');
        $reversed = $this->makePayment($invoice, 60000, '2026-09-06', status: 'ditolak');

        $this->actingAs($this->owner)->get(route('payments.index'))
            ->assertOk()
            ->assertSee($kept->payment_number)
            ->assertDontSee($reversed->payment_number);

        $this->actingAs($this->owner)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee($kept->payment_number)
            ->assertDontSee($reversed->payment_number);

        // Detail tetap bisa dibuka (dari notifikasi/audit), berlabel Dikembalikan.
        $this->actingAs($this->owner)->get(route('payments.show', $reversed))
            ->assertOk()
            ->assertSee('Pembayaran ini telah dikembalikan');
    }

    #[Test]
    public function pembayaran_bulan_berjalan_bisa_dikembalikan_tanpa_kolom_pengurang(): void
    {
        $invoice = $this->makeInvoice($this->pop, '2026-09', 100000);
        $payment = $this->makePayment($invoice, 100000, '2026-09-10');

        $this->actingAs($this->owner)
            ->post(route('payments.reject', $payment), ['reject_reason' => 'Salah input kasir'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PaymentStatus::DITOLAK, $payment->refresh()->payment_status);

        // Bulan yang sama: pembayaran cukup tidak dihitung, bukan jadi pengurang.
        $september = app(CollectorMonthlyReportService::class)->figures('2026-09', [$this->pop->id])[$this->pop->id]['uang_diterima'];
        $this->assertEquals(0, $september['dikembalikan']);
        $this->assertEquals(0, $september['total']);
    }
}
