<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\RevenueCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test Detail Pembayaran merender baris rincian invoice (ADHOC-60)
 * dengan benar. BUKAN regression guard lazy-loading — ditulis awalnya
 * mengira `PaymentController::show()` (eager-load `invoice.customerService`/
 * `invoice.internetPackage` tanpa `invoice.items`) bakal reproduksi
 * `LazyLoadingViolationException` yang dilaporkan user (2026-11-20), tapi
 * TIDAK: dikonfirmasi baca source Laravel (`Builder::hydrate()`), guard
 * `preventsLazyLoading` cuma ditempelkan ke model hasil query yang
 * mengembalikan LEBIH DARI SATU baris (`count($items) > 1`) — `$payment->invoice`
 * via `belongsTo` selalu 1 baris, jadi guard-nya gak pernah nyala di sini,
 * eager-load `invoice.items` yang kurang pun gak pernah ketahuan lewat test
 * ini. Root cause SEBENARNYA + regression guard yang valid ada di
 * `CustomerShowInvoiceItemsLazyLoadingTest` (`CustomerController::show()`,
 * yang me-load koleksi `invoices` — lazim ≥2 baris per pelanggan).
 */
class PaymentShowLazyLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_pembayaran_menampilkan_rincian_invoice_items(): void
    {
        $admin = $this->loginAsAdmin();

        $pop = Pop::create([
            'code' => 'PAY-LL', 'pop_code' => 'PAYLL', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Payment Lazy Load', 'type' => 'cabang', 'status' => 'active',
        ]);
        $package = InternetPackage::create([
            'package_code' => 'PAY-LL', 'name' => 'Paket Lazy Load', 'category' => 'Home', 'package_group' => 'Net',
            'bandwidth_label' => '20 Mbps', 'monthly_price' => 150000, 'is_active' => true,
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-PAY-LL-0001',
            'full_name' => 'Pelanggan Lazy Load',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Lazy Load Test',
        ]);
        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Lazy Load Test',
            'village' => 'Desa Test', 'district' => 'Kecamatan Test', 'city' => 'Kota Test', 'province' => 'Jawa Timur',
        ]);
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => 150000, 'discount' => 0, 'ppn' => 0, 'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01', 'due_date' => '2026-06-15',
            'service_status' => 'aktif', 'billing_status' => 'active',
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PAY-LL-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01', 'due_date' => '2026-06-15',
            'subtotal' => 150000, 'discount' => 0, 'ppn' => 0, 'total_amount' => 150000,
            'paid_amount' => 150000, 'remaining_amount' => 0, 'invoice_status' => 'lunas',
        ]);
        // Baris rincian (ADHOC-60) — WAJIB ada supaya partial line-items
        // benar-benar menyentuh koleksi `items`, bukan kebetulan lolos karena
        // relasinya memang kosong.
        $revenueCategory = RevenueCategory::create(['code' => 'LANGGANAN', 'name' => 'Langganan Bulanan']);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'revenue_category_id' => $revenueCategory->id,
            'category_name_snapshot' => 'Langganan Bulanan',
            'subcategory_name_snapshot' => 'Paket Test',
            'description' => 'Paket Test',
            'amount' => 150000,
            'sort_order' => 1,
        ]);
        $payment = Payment::create([
            'payment_number' => 'PAY-PAY-LL-0001',
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 150000,
            'received_by' => $admin->id,
            'payment_status' => 'valid',
        ]);

        $this->withoutExceptionHandling();

        $response = $this->get(route('payments.show', $payment->id));

        $response->assertOk();
    }
}
