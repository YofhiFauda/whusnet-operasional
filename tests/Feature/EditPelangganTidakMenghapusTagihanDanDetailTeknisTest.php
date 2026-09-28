<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerTechnicalDetail;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi 2026-09-28 (temuan code review, keputusan user A/B/C):
 *
 * A. Edit Pelanggan dengan paket kosong dulu menghapus customer_services, dan
 *    FK invoices `cascadeOnDelete` ikut menyapu SEMUA tagihan + pembayaran —
 *    tanpa audit. Sekarang paket wajib kalau layanan sudah pernah ditagih.
 * B. FK induk → invoices/payments jadi `restrictOnDelete`: penghapusan induk
 *    yang masih punya riwayat keuangan gagal, bukan menghapus riwayatnya.
 *    Tombol Hapus Pelanggan menolak dengan pesan (pakai Putus Langganan).
 * C. Detail teknis/perangkat yang tidak dikirim tidak dikosongkan lagi.
 */
class EditPelangganTidakMenghapusTagihanDanDetailTeknisTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();

        $this->pop = Pop::factory()->create(['pop_code' => 'C', 'cid_prefix' => 'C', 'name' => 'JETIS']);
        $this->package = InternetPackage::create([
            'package_code' => 'PKT-AMAN',
            'name' => 'Paket Aman',
            'category' => 'Home Broadband',
            'package_group' => 'Net',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
            'is_active' => true,
        ]);
    }

    private function makeCustomer(string $status): Customer
    {
        return Customer::create([
            'customer_code' => 'RQ'.fake()->unique()->numerify('######'),
            'full_name' => 'Pelanggan Aman',
            'primary_phone' => '081234567891',
            'registration_date' => '2026-08-01',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Aman No. 1',
        ]);
    }

    private function makeService(Customer $customer): CustomerService
    {
        return CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
    }

    private function makeInvoice(Customer $customer, CustomerService $service): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-AMAN-'.fake()->unique()->numerify('####'),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-08',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => InvoiceStatus::LUNAS->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'full_name' => $customer->full_name,
            'primary_phone' => $customer->primary_phone,
            'registration_date' => $customer->registration_date->toDateString(),
            'pop_id' => $customer->pop_id,
            'status' => $customer->status,
            'internet_package_id' => $this->package->id,
        ], $overrides);
    }

    // ── A ────────────────────────────────────────────────────────────────────

    #[Test]
    public function edit_tanpa_paket_ditolak_kalau_layanan_sudah_pernah_ditagih(): void
    {
        $customer = $this->makeCustomer('active');
        $service = $this->makeService($customer);
        $invoice = $this->makeInvoice($customer, $service);

        $this->put(route('customers.update', $customer->id), $this->payload($customer, [
            'internet_package_id' => null,
        ]))->assertSessionHasErrors('internet_package_id');

        $this->assertNotNull($service->fresh());
        $this->assertNotNull($invoice->fresh());
    }

    #[Test]
    public function calon_pelanggan_belum_ditagih_masih_boleh_melepas_paket(): void
    {
        $customer = $this->makeCustomer('waiting_survey');
        $service = $this->makeService($customer);

        $this->put(route('customers.update', $customer->id), $this->payload($customer, [
            'internet_package_id' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertNull($service->fresh());
    }

    // ── B ────────────────────────────────────────────────────────────────────

    #[Test]
    public function menghapus_layanan_bertagihan_ditolak_database_dan_tagihan_utuh(): void
    {
        $customer = $this->makeCustomer('active');
        $service = $this->makeService($customer);
        $invoice = $this->makeInvoice($customer, $service);

        try {
            $service->delete();
            $this->fail('Layanan bertagihan seharusnya tidak bisa dihapus.');
        } catch (QueryException) {
            // restrictOnDelete — yang diharapkan.
        }

        $this->assertNotNull($invoice->fresh());
    }

    #[Test]
    public function hapus_pelanggan_bertagihan_ditolak_dengan_pesan_dan_data_utuh(): void
    {
        $customer = $this->makeCustomer('active');
        $service = $this->makeService($customer);
        $invoice = $this->makeInvoice($customer, $service);

        $this->from(route('customers.index'))
            ->delete(route('customers.destroy', $customer->id))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('error');

        $this->assertNotNull($customer->fresh());
        $this->assertNotNull($invoice->fresh());
    }

    #[Test]
    public function hapus_pelanggan_tanpa_riwayat_keuangan_tetap_bisa(): void
    {
        $customer = $this->makeCustomer('registered');

        $this->delete(route('customers.destroy', $customer->id))->assertSessionHas('success');

        $this->assertNull($customer->fresh());
        $this->assertSame(0, Payment::count());
    }

    // ── C ────────────────────────────────────────────────────────────────────

    #[Test]
    public function field_teknis_yang_tidak_dikirim_tidak_dikosongkan(): void
    {
        $customer = $this->makeCustomer('active');
        $this->makeService($customer);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'olt_number' => '1X', 'odp_number' => 'ODP-07']);
        $customer->customerDevice()->create(['device_type' => 'ont', 'serial_number' => 'ZTEG-123', 'pppoe_username' => 'aman01']);

        $this->put(route('customers.update', $customer->id), $this->payload($customer, [
            'primary_phone' => '089999999999',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('089999999999', $customer->fresh()->primary_phone);
        $this->assertSame('1X', $customer->customerTechnicalDetail()->first()->olt_number);
        $this->assertSame('ODP-07', $customer->customerTechnicalDetail()->first()->odp_number);
        $this->assertSame('ZTEG-123', $customer->customerDevice()->first()->serial_number);
        $this->assertSame('aman01', $customer->customerDevice()->first()->pppoe_username);
    }

    #[Test]
    public function field_teknis_yang_dikirim_kosong_tetap_bisa_dikosongkan(): void
    {
        $customer = $this->makeCustomer('active');
        $this->makeService($customer);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'olt_number' => '1X', 'odp_number' => 'ODP-07']);

        $this->put(route('customers.update', $customer->id), $this->payload($customer, [
            'odp_number' => '',
        ]))->assertSessionHasNoErrors();

        $detail = $customer->customerTechnicalDetail()->first();
        $this->assertNull($detail->odp_number);
        $this->assertSame('1X', $detail->olt_number);
    }
}
