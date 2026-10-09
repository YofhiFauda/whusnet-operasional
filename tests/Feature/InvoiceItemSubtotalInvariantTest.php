<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\RevenueCategory;
use App\Models\RevenueSubcategory;
use App\Models\Village;
use App\Services\InitialInvoiceService;
use App\Services\InvoiceItemBuilder;
use App\Support\Money;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Penjaga invarian ADHOC-60:
 *
 *     SUM(invoice_items.amount) == invoices.subtotal
 *
 * Bukan `total_amount` — diskon & PPN berlaku di level tagihan dan sengaja
 * tidak dipecah per baris (PPN bukan pendapatan, itu titipan pajak).
 *
 * Test ini menjaga KETIGA jalur penerbit sekaligus lewat `InvoiceItemBuilder`.
 * Percobaan pertama fitur ini (ADHOC-58, di-rewind) tidak punya invarian
 * seperti ini, akibatnya jumlah baris tidak pernah sama dengan angka mana pun
 * di tagihan dan laporan per kategori tak bisa direkonsiliasi. Kalau test ini
 * gagal, jangan longgarkan assert-nya — perbaiki perakit barisnya.
 */
class InvoiceItemSubtotalInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);
    }

    private function makeInvoice(float $subtotal, float $discount = 0, float $ppn = 0): Invoice
    {
        $pop = Pop::create([
            'code' => 'POP-INV',
            'pop_code' => 'INV',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Invariant',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'WHUS-2026-9001',
            'full_name' => 'Pelanggan Invarian',
            'gender' => 'Laki-laki',
            'primary_phone' => '081200000001',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Invarian No. 1',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $subtotal,
            'discount' => $discount,
            'ppn' => $ppn,
            'total_monthly_bill' => $subtotal,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $afterDiscount = max(0, $subtotal - $discount);
        $total = $afterDiscount + round($afterDiscount * ($ppn / 100), 2);

        return Invoice::create([
            'invoice_number' => 'INV-202606-9001',
            'invoice_type' => InvoiceType::BULANAN->value,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'ppn' => $ppn,
            'total_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ]);
    }

    #[Test]
    public function jumlah_baris_sama_dengan_subtotal_bukan_total_amount(): void
    {
        // Diskon 10.000 + PPN 11% dipasang sengaja: kalau perakit baris ikut
        // menghitung keduanya, jumlah baris akan menyamai total_amount dan
        // assert di bawah gagal.
        $invoice = $this->makeInvoice(subtotal: 200_000, discount: 10_000, ppn: 11);

        app(InvoiceItemBuilder::class)->rebuildFor($invoice, [[
            'category_code' => RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
            'subcategory_code' => RevenueSubcategory::CODE_LANGGANAN_BULANAN,
            'amount' => 200_000,
        ]]);

        $this->assertEqualsWithDelta(200_000, (float) $invoice->items()->sum('amount'), 0.001);
        $this->assertNotEqualsWithDelta((float) $invoice->total_amount, (float) $invoice->items()->sum('amount'), 0.001);
    }

    #[Test]
    public function rincian_tagihan_awal_selalu_seimbang_dengan_subtotalnya(): void
    {
        $invoice = $this->makeInvoice(subtotal: 100_000);

        $billing = app(InitialInvoiceService::class)->calculate(
            $invoice->customerService,
            '2026-06-15',
            [
                'extra_installation_fee' => 250_000,
                'extra_cable_fee' => 75_000,
                'extra_pole_fee' => 50_000,
                'other_fee' => 11_000,
            ]
        );

        // subtotal tagihan awal = lima komponen; disamakan dulu supaya builder
        // menguji daftar barisnya, bukan angka bawaan helper di atas.
        $invoice->update(['subtotal' => $billing['subtotal']]);

        $items = app(InvoiceItemBuilder::class)->rebuildFor(
            $invoice,
            app(InitialInvoiceService::class)->lineSpecs($billing)
        );

        $this->assertEqualsWithDelta(
            (float) $billing['subtotal'],
            (float) $items->sum('amount'),
            0.001,
            'Daftar baris InitialInvoiceService::lineSpecs() menyimpang dari rumus subtotal di calculate().'
        );
    }

    #[Test]
    public function baris_bernominal_nol_tidak_ditulis(): void
    {
        $invoice = $this->makeInvoice(subtotal: 150_000);

        $items = app(InvoiceItemBuilder::class)->rebuildFor($invoice, [
            [
                'category_code' => RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
                'subcategory_code' => RevenueSubcategory::CODE_LANGGANAN_BULANAN,
                'amount' => 150_000,
            ],
            [
                'category_code' => RevenueCategory::CODE_JASA_PERBAIKAN,
                'subcategory_code' => 'tambah_kabel',
                'amount' => 0,
            ],
        ]);

        $this->assertCount(1, $items);
    }

    #[Test]
    public function builder_menolak_baris_yang_tidak_sama_dengan_subtotal(): void
    {
        $invoice = $this->makeInvoice(subtotal: 150_000);

        $this->expectException(InvalidArgumentException::class);

        app(InvoiceItemBuilder::class)->rebuildFor($invoice, [[
            'category_code' => RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
            'subcategory_code' => RevenueSubcategory::CODE_LANGGANAN_BULANAN,
            'amount' => 149_000,
        ]]);

        $this->assertDatabaseCount('invoice_items', 0);
    }

    #[Test]
    public function kategori_lainnya_wajib_punya_nama_ketikan(): void
    {
        $invoice = $this->makeInvoice(subtotal: 11_000);

        $this->expectException(InvalidArgumentException::class);

        app(InvoiceItemBuilder::class)->rebuildFor($invoice, [[
            'category_code' => RevenueCategory::CODE_LAINNYA,
            'custom_name' => '   ',
            'amount' => 11_000,
        ]]);
    }

    #[Test]
    public function sub_kategori_dari_kategori_lain_ditolak(): void
    {
        $invoice = $this->makeInvoice(subtotal: 50_000);

        $this->expectException(InvalidArgumentException::class);

        app(InvoiceItemBuilder::class)->rebuildFor($invoice, [[
            'category_code' => RevenueCategory::CODE_JASA_INSTALASI,
            // milik jasa_perbaikan, bukan jasa_instalasi
            'subcategory_code' => 'tambah_kabel',
            'amount' => 50_000,
        ]]);
    }

    #[Test]
    public function baris_ditulis_ulang_bukan_ditumpuk(): void
    {
        $invoice = $this->makeInvoice(subtotal: 90_000);
        $builder = app(InvoiceItemBuilder::class);

        $spec = [[
            'category_code' => RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
            'subcategory_code' => RevenueSubcategory::CODE_LANGGANAN_BULANAN,
            'amount' => 90_000,
        ]];

        $builder->rebuildFor($invoice, $spec);
        $builder->rebuildFor($invoice, $spec);

        $this->assertSame(1, $invoice->items()->count());
        $this->assertEqualsWithDelta(90_000, Money::of($invoice->items()->sum('amount')), 0.001);
    }

    #[Test]
    public function snapshot_nama_bertahan_meski_master_dinamai_ulang(): void
    {
        $invoice = $this->makeInvoice(subtotal: 75_000);

        app(InvoiceItemBuilder::class)->rebuildFor($invoice, [[
            'category_code' => RevenueCategory::CODE_JASA_PERBAIKAN,
            'subcategory_code' => 'tambah_kabel',
            'amount' => 75_000,
        ]]);

        RevenueSubcategory::where('code', 'tambah_kabel')->update(['name' => 'Nama Baru Setelah Rename']);

        $this->assertSame('Tambah Kabel', $invoice->items()->first()->subcategory_name_snapshot);
    }
}
