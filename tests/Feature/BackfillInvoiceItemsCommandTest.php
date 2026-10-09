<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `billing:backfill-invoice-items` (ADHOC-60).
 *
 * Regresi terpenting: tagihan hasil migrasi legacy TIDAK boleh dipetakan
 * kolom-per-kolom. Kolom biaya tambahannya terisi tapi tidak pernah ikut
 * ditagihkan, dan memetakannya menggelembungkan laporan pendapatan sampai 4×.
 */
class BackfillInvoiceItemsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    private CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);

        $this->pop = Pop::create([
            'code' => 'POP-BF',
            'pop_code' => 'BF1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Backfill',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();

        $this->customer = Customer::create([
            'customer_code' => 'WHUS-2026-8001',
            'full_name' => 'Pelanggan Backfill',
            'gender' => 'Laki-laki',
            'primary_phone' => '081288880001',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
        ]);

        $this->service = CustomerService::create([
            'customer_id' => $this->customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 200_000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 200_000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeInvoice(array $attributes): Invoice
    {
        return Invoice::create(array_merge([
            'invoice_type' => InvoiceType::BULANAN->value,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $this->service->id,
            'internet_package_id' => $this->service->internet_package_id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'discount' => 0,
            'ppn' => 0,
            'paid_amount' => 0,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ], $attributes));
    }

    #[Test]
    public function tagihan_legacy_jadi_satu_baris_senilai_subtotal(): void
    {
        // Bentuk persis kasus INV-IN000011-AWAL dari data produksi:
        // subtotal = total = prorate, sementara kolom lain terisi tapi tidak
        // pernah ikut ditagihkan.
        $invoice = $this->makeInvoice([
            'invoice_number' => 'INV-IN000011-AWAL',
            'invoice_type' => InvoiceType::AWAL->value,
            'old_invoice_id' => 11,
            'subtotal' => 110_000,
            'total_amount' => 110_000,
            'remaining_amount' => 110_000,
            'prorate_amount' => 110_000,
            'extra_installation_fee' => 250_000,
            'other_fee' => 11_000,
        ]);

        $this->artisan('billing:backfill-invoice-items')->assertSuccessful();

        $items = $invoice->items()->get();

        $this->assertCount(1, $items);
        $this->assertEqualsWithDelta(110_000, (float) $items->sum('amount'), 0.01);
        $this->assertSame('Prorata', $items->first()->subcategory_name_snapshot);

        // Regresi penggelembungan: jangan sampai 371.000.
        $this->assertNotEqualsWithDelta(371_000, (float) $items->sum('amount'), 0.01);
    }

    #[Test]
    public function tagihan_legacy_bulanan_dipetakan_ke_langganan_bulanan(): void
    {
        $invoice = $this->makeInvoice([
            'invoice_number' => 'INV-IN001825-202507',
            'old_invoice_id' => 1825,
            'subtotal' => 76_452,
            'total_amount' => 76_452,
            'remaining_amount' => 76_452,
            'other_fee' => 72_000,
        ]);

        $this->artisan('billing:backfill-invoice-items')->assertSuccessful();

        $items = $invoice->items()->get();

        $this->assertCount(1, $items);
        $this->assertSame('Langganan Bulanan', $items->first()->subcategory_name_snapshot);
        $this->assertEqualsWithDelta(76_452, (float) $items->first()->amount, 0.01);
    }

    #[Test]
    public function tagihan_non_legacy_dipetakan_per_kolom_dengan_residual(): void
    {
        $invoice = $this->makeInvoice([
            'invoice_number' => 'INV-202606-0001',
            'invoice_type' => InvoiceType::AWAL->value,
            'subtotal' => 400_000,
            'total_amount' => 400_000,
            'remaining_amount' => 400_000,
            'prorate_amount' => 100_000,
            'extra_installation_fee' => 250_000,
            'extra_cable_fee' => 39_000,
            'other_fee' => 11_000,
        ]);

        $this->artisan('billing:backfill-invoice-items')->assertSuccessful();

        $items = $invoice->items()->get();

        // Empat kolom terisi, residual 0 ⇒ tidak ada baris langganan tambahan.
        $this->assertCount(4, $items);
        $this->assertEqualsWithDelta(400_000, (float) $items->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(
            11_000,
            (float) $items->firstWhere('subcategory_name_snapshot', 'Materai / Biaya Lain')->amount,
            0.01
        );
    }

    #[Test]
    public function tagihan_bulanan_polos_jadi_satu_baris_langganan(): void
    {
        $invoice = $this->makeInvoice([
            'invoice_number' => 'INV-202606-0002',
            'subtotal' => 200_000,
            'total_amount' => 200_000,
            'remaining_amount' => 200_000,
        ]);

        $this->artisan('billing:backfill-invoice-items')->assertSuccessful();

        $items = $invoice->items()->get();

        $this->assertCount(1, $items);
        $this->assertSame('Langganan Bulanan', $items->first()->subcategory_name_snapshot);
        $this->assertEqualsWithDelta(200_000, (float) $items->first()->amount, 0.01);
    }

    #[Test]
    public function tagihan_yang_kolomnya_melebihi_subtotal_dilewati(): void
    {
        $invoice = $this->makeInvoice([
            'invoice_number' => 'INV-202606-0003',
            'subtotal' => 100_000,
            'total_amount' => 100_000,
            'remaining_amount' => 100_000,
            'extra_cable_fee' => 150_000,
        ]);

        $this->artisan('billing:backfill-invoice-items')->assertSuccessful();

        $this->assertSame(0, $invoice->items()->count());
    }

    #[Test]
    public function dry_run_tidak_menulis_apa_pun(): void
    {
        $invoice = $this->makeInvoice([
            'invoice_number' => 'INV-202606-0004',
            'subtotal' => 200_000,
            'total_amount' => 200_000,
            'remaining_amount' => 200_000,
        ]);

        $this->artisan('billing:backfill-invoice-items', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, $invoice->items()->count());
        $this->assertDatabaseCount('invoice_items', 0);
    }

    #[Test]
    public function idempotent_tagihan_yang_sudah_punya_rincian_dilewati(): void
    {
        $invoice = $this->makeInvoice([
            'invoice_number' => 'INV-202606-0005',
            'subtotal' => 200_000,
            'total_amount' => 200_000,
            'remaining_amount' => 200_000,
        ]);

        $this->artisan('billing:backfill-invoice-items')->assertSuccessful();
        $firstItemId = $invoice->items()->value('id');

        $this->artisan('billing:backfill-invoice-items')->assertSuccessful();

        $this->assertSame(1, $invoice->items()->count());
        // Baris yang sama, bukan ditulis ulang.
        $this->assertSame($firstItemId, $invoice->items()->value('id'));
    }

    #[Test]
    public function filter_periode_membatasi_cakupan(): void
    {
        $juni = $this->makeInvoice([
            'invoice_number' => 'INV-202606-0006',
            'billing_period' => '2026-06',
            'subtotal' => 200_000,
            'total_amount' => 200_000,
            'remaining_amount' => 200_000,
        ]);

        $juli = $this->makeInvoice([
            'invoice_number' => 'INV-202607-0001',
            'billing_period' => '2026-07',
            'subtotal' => 200_000,
            'total_amount' => 200_000,
            'remaining_amount' => 200_000,
        ]);

        $this->artisan('billing:backfill-invoice-items', ['--period' => '2026-06'])->assertSuccessful();

        $this->assertSame(1, $juni->items()->count());
        $this->assertSame(0, $juli->items()->count());
    }

    #[Test]
    public function format_periode_salah_ditolak(): void
    {
        $this->artisan('billing:backfill-invoice-items', ['--period' => 'Juni 2026'])->assertFailed();
    }
}
