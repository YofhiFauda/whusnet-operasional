<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pop;
use App\Models\RevenueCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug (laporan user 2026-11-20): `Illuminate\Database\LazyLoadingViolationException
 * … Attempted to lazy load [items] on model [App\Models\Invoice] but lazy
 * loading is disabled` saat buka Detail Pelanggan. Root cause dikonfirmasi
 * baca source Laravel (`Builder::hydrate()`): guard lazy-loading cuma
 * ditempelkan ke model hasil query yang mengembalikan LEBIH DARI SATU baris
 * (`count($items) > 1`) — jadi baru ketahuan kalau pelanggan punya ≥ 2
 * invoice (lazim, tagihan bulanan rutin), bukan di pelanggan baru dengan 1
 * tagihan. `CustomerController::show()` eager-load `invoices.creator` tapi
 * TIDAK `invoices.items`, padahal blade (`customers/show.blade.php` §Rincian
 * Tagihan Awal) mengakses `$initialInvoice->items`.
 */
class CustomerShowInvoiceItemsLazyLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_pelanggan_tidak_lazy_load_invoice_items_saat_punya_lebih_dari_satu_tagihan(): void
    {
        $this->loginAsAdmin();

        $pop = Pop::create([
            'code' => 'CUST-LL', 'pop_code' => 'CUSTLL', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Customer Lazy Load', 'type' => 'cabang', 'status' => 'active',
        ]);
        $package = InternetPackage::create([
            'package_code' => 'CUST-LL', 'name' => 'Paket Lazy Load', 'category' => 'Home', 'package_group' => 'Net',
            'bandwidth_label' => '20 Mbps', 'monthly_price' => 150000, 'is_active' => true,
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-CUST-LL-0001',
            'full_name' => 'Pelanggan Lazy Load Invoice',
            'primary_phone' => '081234567891',
            'registration_date' => '2026-05-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Lazy Load Customer Test',
        ]);
        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Lazy Load Customer Test',
            'village' => 'Desa Test', 'district' => 'Kecamatan Test', 'city' => 'Kota Test', 'province' => 'Jawa Timur',
        ]);
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => 150000, 'discount' => 0, 'ppn' => 0, 'total_monthly_bill' => 150000,
            'activation_date' => '2026-05-01', 'due_date' => '2026-05-15',
            'service_status' => 'aktif', 'billing_status' => 'active',
        ]);

        // Tagihan AWAL (dengan baris invoice_items, ADHOC-60) + tagihan BULANAN
        // — DUA baris, bukan satu. Guard lazy-loading Laravel cuma menempel ke
        // hasil query >1 baris; dengan 1 invoice saja bug ini TIDAK kena.
        $awal = Invoice::create([
            'invoice_number' => 'INV-CUST-LL-0001', 'invoice_type' => 'awal',
            'customer_id' => $customer->id, 'pop_id' => $pop->id,
            'customer_service_id' => $service->id, 'internet_package_id' => $package->id,
            'billing_period' => '2026-05', 'issue_date' => '2026-05-01', 'due_date' => '2026-05-15',
            'subtotal' => 150000, 'discount' => 0, 'ppn' => 0, 'total_amount' => 150000,
            'paid_amount' => 150000, 'remaining_amount' => 0, 'invoice_status' => 'lunas',
        ]);
        $revenueCategory = RevenueCategory::create(['code' => 'LANGGANAN', 'name' => 'Langganan Bulanan']);
        InvoiceItem::create([
            'invoice_id' => $awal->id,
            'revenue_category_id' => $revenueCategory->id,
            'category_name_snapshot' => 'Langganan Bulanan',
            'subcategory_name_snapshot' => 'Paket Test',
            'description' => 'Paket Test',
            'amount' => 150000,
            'sort_order' => 1,
        ]);
        Invoice::create([
            'invoice_number' => 'INV-CUST-LL-0002', 'invoice_type' => 'bulanan',
            'customer_id' => $customer->id, 'pop_id' => $pop->id,
            'customer_service_id' => $service->id, 'internet_package_id' => $package->id,
            'billing_period' => '2026-06', 'issue_date' => '2026-06-01', 'due_date' => '2026-06-15',
            'subtotal' => 150000, 'discount' => 0, 'ppn' => 0, 'total_amount' => 150000,
            'paid_amount' => 0, 'remaining_amount' => 150000, 'invoice_status' => 'belum_dibayar',
        ]);

        $this->withoutExceptionHandling();

        $response = $this->get(route('customers.show', $customer->id));

        $response->assertOk();
    }
}
