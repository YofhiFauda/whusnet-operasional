<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADHOC-95 §3: badge status TIDAK dirender sama sekali di halaman "Tagihan
 * Belum Lunas" (`status_group=belum_lunas`) — untuk Belum Dibayar maupun
 * Sebagian. Daftar Tagihan umum & filter dropdown Status tetap tampil.
 */
class InvoiceBelumLunasHidesStatusBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $unpaid;

    private Invoice $partial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $pop = Pop::create([
            'code' => 'POP-BDG',
            'pop_code' => 'BDG',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Badge',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->unpaid = $this->createInvoice($pop, 'INV-BDG-0001', 'belum_dibayar', 0);
        $this->partial = $this->createInvoice($pop, 'INV-BDG-0002', 'sebagian', 50000);
    }

    public function test_badge_tidak_dirender_di_halaman_tagihan_belum_lunas(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('invoices.belum-lunas'));

        $response->assertOk();
        $response->assertSee('INV-BDG-0001');
        $response->assertSee('INV-BDG-0002');
        $response->assertDontSee('id="invoice-status-badge-'.$this->unpaid->id.'"', false);
        $response->assertDontSee('id="invoice-status-badge-'.$this->partial->id.'"', false);
        $response->assertSee('const HIDE_INVOICE_STATUS_BADGE = true', false);
    }

    public function test_badge_tetap_tampil_di_daftar_tagihan_umum(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('invoices.index'));

        $response->assertOk();
        $response->assertSee('id="invoice-status-badge-'.$this->unpaid->id.'"', false);
        $response->assertSee('id="invoice-status-badge-'.$this->partial->id.'"', false);
        $response->assertSee('const HIDE_INVOICE_STATUS_BADGE = false', false);
    }

    public function test_badge_tetap_tampil_saat_filter_manual_status_belum_dibayar(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('invoices.index', ['status' => 'belum_dibayar']));

        $response->assertOk();
        $response->assertSee('id="invoice-status-badge-'.$this->unpaid->id.'"', false);
    }

    private function createInvoice(Pop $pop, string $number, string $status, int $paid): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $number),
            'full_name' => 'Pelanggan '.$number,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'pop_id' => $pop->id,
            'address' => 'Jl. Badge',
        ]);

        $package = InternetPackage::query()->firstOrFail();

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => 'Paket Test 20 Mbps',
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => $number,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => $paid,
            'remaining_amount' => 150000 - $paid,
            'invoice_status' => $status,
        ]);
    }
}
