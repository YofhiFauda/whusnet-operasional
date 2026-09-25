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
        // kedua halaman. Sekarang keduanya @include partial yang sama
        // (payments.partials.kwitansi, ADHOC-94).
        $wajib = [
            'Pelanggan Kwitansi Seragam',   // nama
            'Jl. Kwitansi Seragam No. 7',   // alamat, satu baris utuh
            'Agustus',                       // bulan periode (billing_period 2026-08)
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

    /**
     * Keputusan final ADHOC-94: status badge berwarna DIBUANG total dari
     * kwitansi cetak (bukan cuma diwarnai ulang) — kwitansi bukan lagi
     * tempat menampilkan status internal pembayaran.
     */
    public function test_status_pembayaran_tidak_lagi_dicetak_di_lembar_kwitansi(): void
    {
        $this->loginAsAdmin();
        $payment = $this->buatPembayaran(['payment_status' => 'ditolak']);

        $response = $this->get(route('payments.show', $payment->id));

        $response->assertOk();
        $response->assertDontSee('text-rose-700">● Ditolak', false);
        $response->assertDontSee('text-emerald-700">● Ditolak', false);
    }

    /**
     * Keputusan final ADHOC-94: baris "Catatan" (data internal petugas)
     * DIBUANG total dari kwitansi cetak — bukan cuma disembunyikan saat
     * kosong.
     */
    public function test_catatan_tidak_lagi_dicetak_di_lembar_kwitansi(): void
    {
        $this->loginAsAdmin();
        $payment = $this->buatPembayaran(['note' => 'catatan kasir rahasia']);

        $response = $this->get(route('payments.receipt', $payment->id));

        $response->assertOk();
        $response->assertDontSee('catatan kasir rahasia', false);
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

    /**
     * Keputusan final ADHOC-94: alamat kwitansi cetak SATU baris utuh —
     * pemenggalan dua baris di titik "Kec." itu peninggalan struk thermal
     * 80mm sempit, tidak relevan lagi setelah thermal dihapus.
     */
    public function test_alamat_tidak_lagi_dipenggal_dua_baris(): void
    {
        $this->loginAsAdmin();
        $payment = $this->buatPembayaran([], [
            'address' => 'Jl. Veteran Dkh. Joresan III RT. 002/RW. 002, Joresan, Kec. Mlarak, Kabupaten Ponorogo',
        ]);

        $response = $this->get(route('payments.receipt', $payment->id));

        $response->assertOk();
        $response->assertSee(
            'Jl. Veteran Dkh. Joresan III RT. 002/RW. 002, Joresan, Kec. Mlarak, Kabupaten Ponorogo',
            false
        );
        $response->assertDontSee('Joresan<br>Kec. Mlarak', false);
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
