<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Satu pembayaran, banyak tombol cetak — isinya wajib sama.
 *
 * Gejala yang memicu test ini: kwitansi PAY-202608-0822 yang dicetak dari List
 * Pembayaran (struk thermal) dan dari Detail Pembayaran (lembar A4) berisi data
 * berbeda. Alamat, no. HP, dan kolektor cuma ada di A4; periode & paket cuma
 * ada di thermal. Penyebabnya tiap view membaca `$payment` sendiri-sendiri.
 *
 * Sekarang keduanya lewat `ReceiptPresenter`. Test ini mengunci bahwa BENTUK
 * boleh berbeda, ISI tidak — plus dua cacat lama yang ikut dibereskan (status
 * hijau tanpa syarat, dan catatan yang dikarang saat kosong).
 */
class KwitansiIsiSeragamAntarHalamanTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_struk_thermal_dan_lembar_a4_memuat_field_yang_sama(): void
    {
        $this->loginAsAdmin();
        $payment = $this->buatPembayaran();

        $thermal = $this->get(route('payments.receipt', $payment->id));
        $a4 = $this->get(route('payments.show', $payment->id));

        $thermal->assertOk();
        $a4->assertOk();

        // Identitas, tagihan, dan nominal — dulu tersebar tidak merata antara
        // kedua halaman.
        $wajib = [
            'Pelanggan Kwitansi Seragam',   // nama
            'Jl. Kwitansi Seragam No. 7',   // alamat — dulu cuma di A4
            '081200000822',                 // no. HP — dulu cuma di A4
            'INV-202608-0822',
            '2026-08',                      // periode — dulu cuma di thermal
            'Rp 75.000',                    // dibayar
        ];

        foreach ($wajib as $teks) {
            $thermal->assertSee($teks, false);
            $a4->assertSee($teks, false);
        }

        // Paket & penagih: nama paket ikut seeder, jadi dicek lewat nilainya.
        $thermal->assertSee($this->package->name, false);
        $a4->assertSee($this->package->name, false);
    }

    public function test_pembayaran_ditolak_tidak_dicetak_hijau_di_lembar_a4(): void
    {
        $this->loginAsAdmin();
        $payment = $this->buatPembayaran(['payment_status' => 'ditolak']);

        $response = $this->get(route('payments.show', $payment->id));

        $response->assertOk();
        // Blok status pada lembar cetak: warnanya harus ikut status, bukan
        // emerald tanpa syarat. Kwitansi "resmi" yang mencetak pembayaran
        // ditolak dengan bullet hijau adalah bukti yang menyesatkan.
        $response->assertSee('text-rose-700">● Ditolak', false);
        $response->assertDontSee('text-emerald-700">● Ditolak', false);
    }

    public function test_catatan_kosong_tidak_dikarang_sistem(): void
    {
        $this->loginAsAdmin();
        $payment = $this->buatPembayaran(['note' => null]);

        $response = $this->get(route('payments.show', $payment->id));

        $response->assertOk();
        // Kalimat ini dulu muncul sebagai fallback dan terbaca seperti catatan
        // petugas, padahal tidak pernah ada yang menulisnya.
        $response->assertDontSee('Tagihan Bulanan. Struk ini adalah bukti pembayaran sah', false);
        $response->assertSee('Tanpa catatan.', false);
    }

    public function test_cicilan_sebagian_tidak_tercetak_sebagai_pelunasan(): void
    {
        $this->loginAsAdmin();
        // Bayar 75.000 dari tagihan 150.000 → cicilan ke-1, belum melunasi.
        $payment = $this->buatPembayaran();

        $a4 = $this->get(route('payments.show', $payment->id));
        $thermal = $this->get(route('payments.receipt', $payment->id));

        $a4->assertSee('Cicilan Ke-1', false);
        $a4->assertDontSee('Pelunasan Invoice', false);
        $thermal->assertSee('Cicilan Ke-1', false);
    }

    public function test_alamat_panjang_dipenggal_di_kecamatan(): void
    {
        $this->loginAsAdmin();
        $payment = $this->buatPembayaran([], [
            'address' => 'Jl. Veteran Dkh. Joresan III RT. 002/RW. 002, Joresan, Kec. Mlarak, Kabupaten Ponorogo',
        ]);

        $response = $this->get(route('payments.receipt', $payment->id));

        $response->assertOk();
        // Dua baris, dipisah <br> — bukan satu baris panjang yang melipat di
        // tempat acak pada kolom sempit.
        $response->assertSee(
            'Jl. Veteran Dkh. Joresan III RT. 002/RW. 002, Joresan<br>Kec. Mlarak, Kabupaten Ponorogo',
            false
        );
    }

    public function test_alamat_tanpa_penanda_kecamatan_tidak_dipecah(): void
    {
        $this->loginAsAdmin();
        $payment = $this->buatPembayaran([], ['address' => 'Jl. Melati No. 12, Ponorogo']);

        $response = $this->get(route('payments.receipt', $payment->id));

        $response->assertOk();
        // Membelah di koma sembarang bisa memisahkan nama jalan dari nomornya.
        $response->assertSee('Jl. Melati No. 12, Ponorogo', false);
        $response->assertDontSee('Jl. Melati No. 12<br>', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $customerOverrides
     */
    protected function buatPembayaran(array $overrides = [], array $customerOverrides = []): Payment
    {
        $pop = Pop::create([
            'code' => 'POP-KWS-1',
            'pop_code' => 'KWS1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Kwitansi Seragam',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create(array_merge([
            'customer_code' => 'C-KWS-0822',
            'full_name' => 'Pelanggan Kwitansi Seragam',
            'primary_phone' => '081200000822',
            'registration_date' => '2026-08-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Kwitansi Seragam No. 7',
        ], $customerOverrides));

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-202608-0822',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-08',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 75000,
            'remaining_amount' => 75000,
            'invoice_status' => 'sebagian',
        ]);

        return Payment::create(array_merge([
            'payment_number' => 'PAY-202608-0822',
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => '2026-08-11',
            'payment_method' => 'cash',
            'amount' => 75000,
            'received_by' => null,
            'payment_status' => 'valid',
        ], $overrides));
    }
}
