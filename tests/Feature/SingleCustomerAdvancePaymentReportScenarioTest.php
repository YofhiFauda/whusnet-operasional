<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\CollectorMonthlyReportService;
use App\Services\CustomerBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsCollectorMonthlyScenario;
use Tests\TestCase;

class SingleCustomerAdvancePaymentReportScenarioTest extends TestCase
{
    use BuildsCollectorMonthlyScenario;
    use RefreshDatabase;

    #[Test]
    public function satu_pelanggan_bayar_empat_bulan_sekaligus_dan_laporan_tidak_menggandakan_kas(): void
    {
        $this->travelTo(Carbon::parse('2026-10-29 10:00:00'));
        $this->seedBase();

        $pop = $this->makePop('Cabang Skenario Bayar Dimuka');
        $customer = $this->makeCustomer($pop);
        $octoberInvoice = $this->makeInvoice($pop, '2026-10', 100000, customer: $customer);
        $this->assertDatabaseCount('customers', 1);

        $this->actingAs($this->ownerUser())
            ->post(route('invoices.payments.store', $octoberInvoice), [
                'payment_date' => '2026-10-29',
                'payment_method' => 'cash',
                'amount' => 400000,
                'is_advance_payment' => '1',
            ])
            ->assertRedirect(route('invoices.show', $octoberInvoice))
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->sole();
        $this->assertEquals(100000, $payment->amount);
        $this->assertEquals(300000, $payment->overpay_amount);
        $this->assertTrue($payment->is_advance_payment);
        $this->assertEquals(300000, app(CustomerBalanceService::class)->balance($customer));

        $report = app(CollectorMonthlyReportService::class);
        $october = $report->figures('2026-10', [$pop->id])[$pop->id];
        $this->assertEquals(400000, $october['tagihan']['bayar_dimuka_diterima']);
        $this->assertSame(1, $october['pelanggan']['bayar_dimuka']);
        $this->assertEquals(100000, $october['tagihan']['total_pembayaran']);
        $this->assertEquals(300000, $october['uang_diterima']['lebih_bayar']);
        $this->assertEquals(400000, $october['uang_diterima']['total']);
        $this->assertCount(1, $report->detail('2026-10', $pop->id, 'tagihan', 'bayar_dimuka_diterima'));
        $this->assertCount(1, $report->detail('2026-10', $pop->id, 'pelanggan', 'bayar_dimuka'));

        $this->get(route('reports.collector-monthly.index', ['period' => '2026-10', 'pop_id' => $pop->id]))
            ->assertOk()
            ->assertSee('Bayar Dimuka Diterima')
            ->assertSee('400.000')
            ->assertSee('Pelanggan unik bayar dimuka');

        foreach (['2026-11', '2026-12', '2027-01'] as $index => $period) {
            $this->travelTo(Carbon::parse($period.'-01 10:00:00'));
            $invoice = $this->makeInvoice($pop, $period, 100000, customer: $customer);
            $applied = app(CustomerBalanceService::class)->applyToOpenInvoices($customer);
            $this->assertCount(1, $applied);
            $this->assertEquals($invoice->id, $applied[0]->invoice_id);
            $this->assertEquals(100000, $applied[0]->balance_used_amount);
            $this->assertEquals(200000 - ($index * 100000), app(CustomerBalanceService::class)->balance($customer));

            $figures = $report->figures($period, [$pop->id])[$pop->id];
            $this->assertEquals(100000, $figures['tagihan']['dimuka']);
            $this->assertEquals(0, $figures['tagihan']['bayar_dimuka_diterima']);
            $this->assertSame(0, $figures['pelanggan']['bayar_dimuka']);
            $this->assertEquals(0, $figures['uang_diterima']['total']);
        }

        $this->assertDatabaseCount('payments', 4);
    }
}
