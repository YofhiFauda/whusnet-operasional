<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Dua gejala yang dijaga di jalur Tagihan (bukan jalur kolektor).
 *
 * 1. Submit dobel. Jalur kolektor sudah lama punya `idempotency_key`; jalur
 *    Tagihan hanya mengandalkan pola PRG, yang cuma mencegah refresh. Klik
 *    dobel atau retry setelah koneksi putus tetap menyimpan dua payment —
 *    dan untuk cicilan sebagian, keduanya sah menurut sistem sehingga tak ada
 *    yang bisa membedakannya dari dua cicilan yang memang terjadi.
 *
 * 2. Tanggal masa depan. Jalur kolektor melarangnya eksplisit karena merusak
 *    pemotongan pendapatan per periode; jalur Tagihan sempat menerimanya.
 *
 * Bentuk data ujinya mengikuti `PaymentInputTest` — sengaja disalin, bukan
 * diwarisi: mewarisi kelas test membuat seluruh test induk ikut dijalankan
 * ulang di sini.
 */
class PembayaranTagihanTanpaDobelDanTanggalMasaDepanTest extends TestCase
{
    use RefreshDatabase;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    private function admin(): User
    {
        $role = Role::where('name', 'Owner')->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    public function test_submit_ulang_dengan_kunci_sama_tidak_menyimpan_pembayaran_kedua(): void
    {
        $pop = $this->createPop('POP-IDM-1', 'IDM1', 'POP Idempotensi');
        $invoice = $this->createInvoice($pop, 'INV-202606-9001');
        $admin = $this->admin();

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
        ];

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), $payload);
        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), $payload);

        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertSame(50000.0, (float) $invoice->fresh()->paid_amount);
    }

    public function test_cicilan_sah_berikutnya_tetap_bisa_dicatat(): void
    {
        // Penahannya harus membedakan "submit yang sama dikirim dua kali" dari
        // "cicilan kedua yang memang benar-benar terjadi". Nominalnya dibedakan
        // karena unique index lama `payments_invoice_date_amount_unique` sudah
        // menolak dua pembayaran identik pada tagihan & tanggal yang sama.
        $pop = $this->createPop('POP-IDM-2', 'IDM2', 'POP Cicilan');
        $invoice = $this->createInvoice($pop, 'INV-202606-9002');
        $admin = $this->admin();

        foreach ([50000, 60000] as $amount) {
            $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_date' => '2026-06-13',
                'payment_method' => 'cash',
                'amount' => $amount,
            ]);
        }

        $this->assertSame(2, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertSame(110000.0, (float) $invoice->fresh()->paid_amount);
    }

    public function test_duplikat_tanpa_kunci_dijawab_pesan_validasi_bukan_error_mentah(): void
    {
        // Pemanggil tanpa kunci idempotensi jatuh ke unique index lama. Itu
        // kondisi yang diantisipasi, jadi tak boleh muncul sebagai 500.
        $pop = $this->createPop('POP-IDM-4', 'IDM4', 'POP Duplikat');
        $invoice = $this->createInvoice($pop, 'INV-202606-9006');
        $admin = $this->admin();

        $payload = [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
        ];

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), $payload);

        $response = $this->actingAs($admin)
            ->from(route('invoices.payments.create', $invoice->id))
            ->post(route('invoices.payments.store', $invoice->id), $payload);

        $response->assertSessionHasErrors('amount');
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_tanpa_kunci_perilakunya_tidak_berubah(): void
    {
        // Pemanggil JSON yang tak punya form tidak boleh ikut tertolak.
        $pop = $this->createPop('POP-IDM-3', 'IDM3', 'POP Tanpa Kunci');
        $invoice = $this->createInvoice($pop, 'INV-202606-9003');

        $this->actingAs($this->admin())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
        ]);

        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_tanggal_bayar_masa_depan_ditolak(): void
    {
        $pop = $this->createPop('POP-TGL-1', 'TGL1', 'POP Tanggal');
        $invoice = $this->createInvoice($pop, 'INV-202606-9004');

        $response = $this->actingAs($this->admin())
            ->from(route('invoices.payments.create', $invoice->id))
            ->post(route('invoices.payments.store', $invoice->id), [
                'payment_date' => now()->addDay()->format('Y-m-d'),
                'payment_method' => 'cash',
                'amount' => 50000,
            ]);

        $response->assertSessionHasErrors('payment_date');
        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_tanggal_bayar_hari_ini_tetap_diterima(): void
    {
        $pop = $this->createPop('POP-TGL-2', 'TGL2', 'POP Hari Ini');
        $invoice = $this->createInvoice($pop, 'INV-202606-9005');

        $this->actingAs($this->admin())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'amount' => 50000,
        ]);

        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
    }

    private function createPop(string $code, string $popCode, string $name): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $popCode,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => $name,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createInvoice(Pop $pop, string $invoiceNumber): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => 'Customer Payment Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Payment Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Payment Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Test 20 Mbps',
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
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }
}
