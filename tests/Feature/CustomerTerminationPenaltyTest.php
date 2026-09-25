<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ManualInvoiceCategory;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerTerminationReason;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Denda Putus Langganan (ADHOC-69 §3.1/§3.3) — masa langganan <=1 tahun wajib
 * diisi manual (0 sah), masa >1 tahun TIDAK ADA denda sama sekali, apapun
 * `penalty_amount` yang dikirim klien (guard anti tamper di server). Tanpa
 * prorate — invoice Bulanan/tunggakan lama tidak boleh ikut tersentuh.
 */
class CustomerTerminationPenaltyTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->pop = Pop::first() ?? Pop::create([
            'code' => 'POP-PEN',
            'pop_code' => 'PEN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Denda Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function customerWithService(?\DateTimeInterface $activationDate): array
    {
        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'customer_code' => 'C-PEN-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'full_name' => 'Pelanggan Denda '.$seq,
            'primary_phone' => '08123456'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'registration_date' => now()->subYears(2),
            'pop_id' => $this->pop->id,
            'status' => 'active',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => InternetPackage::first()->id ?? 1,
            'service_status' => 'aktif',
            'billing_cycle' => 'monthly',
            'package_name_snapshot' => 'Paket Test',
            'package_price_snapshot' => 150000,
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
            'activation_date' => $activationDate,
        ]);

        return [$customer, $service];
    }

    #[Test]
    public function masa_kurang_dari_1_tahun_wajib_isi_denda_dan_invoice_terbit(): void
    {
        $this->loginAsAdmin();
        [$customer] = $this->customerWithService(now()->subMonths(6));
        $reason = CustomerTerminationReason::create(['name' => 'Kompetitor', 'default_penalty_amount' => 100000]);

        $response = $this->post(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
            'penalty_amount' => 100000,
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('terminated', $customer->fresh()->status);

        $invoice = Invoice::where('customer_id', $customer->id)->where('invoice_type', InvoiceType::MANUAL->value)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('lainnya', $invoice->manual_category instanceof ManualInvoiceCategory ? $invoice->manual_category->value : $invoice->manual_category);
        $this->assertSame('Denda Putus Langganan', $invoice->manual_subtype_name);
        $this->assertEquals(100000, (float) $invoice->total_amount);
        $this->assertSame('belum_dibayar', $invoice->invoice_status instanceof InvoiceStatus ? $invoice->invoice_status->value : $invoice->invoice_status);
        $this->assertSame(0.0, (float) $invoice->paid_amount);
    }

    #[Test]
    public function masa_kurang_dari_1_tahun_tanpa_isi_denda_ditolak(): void
    {
        $this->loginAsAdmin();
        [$customer] = $this->customerWithService(now()->subMonths(6));
        $reason = CustomerTerminationReason::create(['name' => 'Kompetitor']);

        $response = $this->post(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
            // penalty_amount sengaja tidak dikirim.
        ]);

        $response->assertSessionHasErrors('penalty_amount');
        $this->assertSame('active', $customer->fresh()->status);
    }

    #[Test]
    public function denda_final_nol_tidak_menerbitkan_invoice(): void
    {
        $this->loginAsAdmin();
        [$customer] = $this->customerWithService(now()->subMonths(6));
        $reason = CustomerTerminationReason::create(['name' => 'Meninggal']);

        $this->post(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
            'penalty_amount' => 0,
        ])->assertSessionHas('success');

        $this->assertSame('terminated', $customer->fresh()->status);
        $this->assertSame(0, Invoice::where('customer_id', $customer->id)->where('invoice_type', InvoiceType::MANUAL->value)->count());
    }

    #[Test]
    public function masa_lebih_dari_1_tahun_tidak_ada_denda_sama_sekali(): void
    {
        $this->loginAsAdmin();
        [$customer] = $this->customerWithService(now()->subYears(2));
        $reason = CustomerTerminationReason::create(['name' => 'Pindah', 'default_penalty_amount' => 500000]);

        // Guard anti tamper: klien tetap kirim penalty_amount, server WAJIB
        // mengabaikannya sepenuhnya untuk pelanggan >1 tahun.
        $this->post(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
            'penalty_amount' => 999999,
        ])->assertSessionHas('success');

        $this->assertSame('terminated', $customer->fresh()->status);
        $this->assertSame(0, Invoice::where('customer_id', $customer->id)->where('invoice_type', InvoiceType::MANUAL->value)->count());
    }

    #[Test]
    public function tepat_1_tahun_masih_dianggap_kurang_dari_sama_dengan_1_tahun(): void
    {
        $this->loginAsAdmin();

        // Bekukan waktu ke tengah malam — `customer_services.activation_date`
        // di-cast 'date' (jam dibuang saat disimpan), jadi "now" pembanding
        // WAJIB juga jam 00:00:00 supaya activation_date+1 tahun persis sama
        // dengan now(), bukan meleset beberapa jam dan bikin test flaky.
        $frozenNow = now()->startOfDay();
        Carbon::setTestNow($frozenNow);

        [$customer] = $this->customerWithService($frozenNow->copy()->subYear());
        $reason = CustomerTerminationReason::create(['name' => 'Tepat setahun']);

        // Tanpa penalty_amount → ditolak KARENA masih dianggap eligible
        // (inklusif), bukan lolos begitu saja seperti >1 tahun.
        $this->post(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
        ])->assertSessionHasErrors('penalty_amount');

        Carbon::setTestNow();
    }

    #[Test]
    public function activation_date_null_diperlakukan_kurang_dari_sama_dengan_1_tahun(): void
    {
        $this->loginAsAdmin();
        [$customer] = $this->customerWithService(null);
        $reason = CustomerTerminationReason::create(['name' => 'Data Legacy']);

        $this->post(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
        ])->assertSessionHasErrors('penalty_amount');
    }

    #[Test]
    public function invoice_tunggakan_lama_tidak_tersentuh_saat_putus(): void
    {
        $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService(now()->subMonths(6));

        $oldInvoice = Invoice::create([
            'invoice_number' => 'INV-202601-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => '2026-01',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);

        $reason = CustomerTerminationReason::create(['name' => 'Pindah']);

        $this->post(route('customers.terminate', $customer), [
            'termination_reason_id' => $reason->id,
            'penalty_amount' => 0,
        ])->assertSessionHas('success');

        $oldInvoice->refresh();
        $this->assertSame('belum_dibayar', $oldInvoice->invoice_status instanceof InvoiceStatus ? $oldInvoice->invoice_status->value : $oldInvoice->invoice_status);
        $this->assertEquals(150000, (float) $oldInvoice->total_amount);
        $this->assertEquals(0.0, (float) $oldInvoice->paid_amount);
    }
}
