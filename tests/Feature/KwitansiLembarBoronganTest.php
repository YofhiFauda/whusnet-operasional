<?php

namespace Tests\Feature;

use App\Enums\ReceiptMatchMethod;
use App\Enums\ReceiptStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\Receipts\PaymentReceiptService;
use App\Services\Receipts\PdfTextNumberReader;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gejala yang dijaga: verifikasi massal tidak jalan karena satu lembar cetak
 * memuat banyak kwitansi.
 *
 * `receipt-print.blade.php` mencetak grid 2 kolom bergaris putus-putus — satu
 * halaman A4 berisi 8 kwitansi untuk digunting. Admin menekan Print lalu
 * "Save as PDF", maka satu berkas mewakili 8 pembayaran. Model lama (satu
 * berkas = satu pembayaran, `checksum` unique global) menolak bentuk itu
 * mentah-mentah: tujuh kwitansi lain tak pernah bisa tercatat.
 *
 * Pembacaannya lewat LAPISAN TEKS, bukan QR hasil raster — pada lembar nyata,
 * pemindaian ubin menemukan 7 dari 8 nomor sementara lapisan teks memberi
 * kedelapan-delapannya.
 *
 * Fixture `tests/Fixtures/kwitansi-lembar-8.pdf`: satu halaman, 8 nomor
 * PAY-202601-0001..0008 sebagai teks.
 */
class KwitansiLembarBoronganTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/kwitansi-lembar-8.pdf');

        if (! app(PdfTextNumberReader::class)->isAvailable()) {
            $this->markTestSkipped('pdftotext (poppler-utils) tidak terpasang di lingkungan ini.');
        }

        $this->seed(InternetPackageSeeder::class);
        Storage::fake('local');
    }

    public function test_seluruh_nomor_terbaca_dari_lapisan_teks(): void
    {
        $numbers = app(PdfTextNumberReader::class)->numbers($this->fixture);

        $this->assertCount(8, $numbers);
        $this->assertSame('PAY-202601-0001', $numbers[0]);
        $this->assertSame('PAY-202601-0008', $numbers[7]);
    }

    public function test_berkas_gambar_tidak_menyentuh_jalur_teks(): void
    {
        // Foto/scan tak punya lapisan teks. Tanpa gerbang PDF, `pdftotext`
        // tetap di-spawn untuk tiap berkas gambar — hasilnya sama-sama kosong,
        // tapi unggah 200 foto berarti 200 proses eksternal percuma.
        $png = tempnam(sys_get_temp_dir(), 'kwt').'.png';
        imagepng(imagecreatetruecolor(20, 20), $png);

        try {
            $reader = app(PdfTextNumberReader::class);

            $this->assertFalse($reader->isPdf($png));
            $this->assertSame([], $reader->numbers($png));
        } finally {
            @unlink($png);
        }
    }

    public function test_satu_lembar_jadi_delapan_kwitansi_tercocokkan(): void
    {
        foreach ($this->nomorLembar() as $number) {
            $this->buatPembayaran($number);
        }

        app(PaymentReceiptService::class)->match($this->buatReceipt());

        $this->assertSame(8, PaymentReceipt::count());
        $this->assertSame(8, PaymentReceipt::where('status', ReceiptStatus::MATCHED->value)->count());

        // Semua baris menunjuk lembar unggahan yang SAMA. Lembar itu arsip
        // kertas yang dicetak & diserahkan; kwitansi satuan pelanggan dirender
        // ulang dari data lewat halaman cetak, bukan disimpan sebagai berkas.
        $this->assertSame(1, PaymentReceipt::distinct()->count('path'));

        // Tiap baris punya pembayarannya sendiri, dan pop_id ikut tersalin
        // supaya POP scope bekerja seperti kwitansi satuan.
        $this->assertSame(8, PaymentReceipt::distinct()->count('payment_id'));
        $this->assertSame(0, PaymentReceipt::whereNull('pop_id')->count());

        // Tercatat sebagai TEXT, bukan QR: kolom ini dipakai audit untuk menilai
        // seberapa jauh sebuah pencocokan layak dipercaya.
        $this->assertSame(8, PaymentReceipt::where('match_method', ReceiptMatchMethod::TEXT->value)->count());
    }

    public function test_kwitansi_satuan_bertekst_juga_lewat_lapisan_teks(): void
    {
        // Berkas satuan tidak lagi dipaksa lewat raster+QR kalau lapisan
        // teksnya ada — jalurnya sama persis dengan lembar borongan, cuma
        // daftarnya berisi satu.
        $this->buatPembayaran('PAY-202601-0009');

        $path = 'receipts/2026/01/satuan.pdf';
        $fixture = base_path('tests/fixtures/kwitansi-satuan-teks.pdf');
        Storage::disk('local')->put($path, file_get_contents($fixture));

        $receipt = PaymentReceipt::create([
            'uploaded_by' => User::factory()->create()->id,
            'original_filename' => 'satuan.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => filesize($fixture),
            'checksum' => hash_file('sha256', $fixture),
            'status' => ReceiptStatus::PENDING->value,
        ]);

        app(PaymentReceiptService::class)->match($receipt);

        $receipt->refresh();

        $this->assertSame(1, PaymentReceipt::count());
        $this->assertSame(ReceiptStatus::MATCHED, $receipt->status);
        $this->assertSame(ReceiptMatchMethod::TEXT, $receipt->match_method);
        $this->assertSame('PAY-202601-0009', $receipt->detected_number);
    }

    public function test_nomor_tanpa_pembayaran_tidak_menggagalkan_sisanya(): void
    {
        // Tujuh dibuatkan pembayarannya, satu sengaja tidak.
        foreach (array_slice($this->nomorLembar(), 0, 7) as $number) {
            $this->buatPembayaran($number);
        }

        app(PaymentReceiptService::class)->match($this->buatReceipt());

        $this->assertSame(8, PaymentReceipt::count());
        $this->assertSame(7, PaymentReceipt::where('status', ReceiptStatus::MATCHED->value)->count());

        $tertinggal = PaymentReceipt::where('status', ReceiptStatus::MISMATCH->value)->sole();

        $this->assertSame('PAY-202601-0008', $tertinggal->detected_number);
        $this->assertNull($tertinggal->payment_id);
    }

    public function test_tiap_baris_menunjuk_pembayaran_yang_nomornya_sama(): void
    {
        // Bukan sekadar "8 baris tercocokkan": baris untuk PAY-…-0003 harus
        // menunjuk pembayaran PAY-…-0003, bukan pembayaran lain. Pemetaan yang
        // tertukar tetap menghasilkan 8 baris hijau, tapi kwitansi pelanggan A
        // menempel pada pembayaran pelanggan B.
        foreach ($this->nomorLembar() as $number) {
            $this->buatPembayaran($number);
        }

        app(PaymentReceiptService::class)->match($this->buatReceipt());

        foreach (PaymentReceipt::with('payment')->get() as $row) {
            $this->assertSame($row->detected_number, $row->payment?->payment_number);
        }
    }

    public function test_jejak_pencocokan_manual_lama_tidak_menghilangkan_nomor(): void
    {
        // Berkas yang pernah dicocokkan manual lalu dilepas menyisakan
        // `detected_number` lama. Kalau nomor itu dipercaya sebagai penanda
        // baris, satu baris direbut dua nomor sekaligus — dan satu pembayaran
        // hilang dari lembar tanpa jejak.
        foreach ($this->nomorLembar() as $number) {
            $this->buatPembayaran($number);
        }

        $receipt = $this->buatReceipt();
        $receipt->update(['detected_number' => 'PAY-202601-0008']);

        app(PaymentReceiptService::class)->match($receipt);

        $this->assertSame(8, PaymentReceipt::count());
        $this->assertSame(8, PaymentReceipt::whereNotNull('payment_id')->distinct()->count('payment_id'));
    }

    public function test_pembacaan_ulang_tidak_melahirkan_baris_kembar(): void
    {
        // Retry queue / berkas dibaca ulang: jumlah barisnya harus tetap 8,
        // bukan 16. Tanpa penguncian per nomor, tiap percobaan menyalin ulang
        // seluruh lembar.
        foreach ($this->nomorLembar() as $number) {
            $this->buatPembayaran($number);
        }

        $service = app(PaymentReceiptService::class);
        $receipt = $this->buatReceipt();

        $service->match($receipt);
        $service->match($receipt->fresh());

        $this->assertSame(8, PaymentReceipt::count());
    }

    /**
     * @return array<int, string>
     */
    private function nomorLembar(): array
    {
        return array_map(fn (int $i) => sprintf('PAY-202601-%04d', $i), range(1, 8));
    }

    private function buatReceipt(): PaymentReceipt
    {
        $path = 'receipts/2026/01/lembar-8.pdf';
        Storage::disk('local')->put($path, file_get_contents($this->fixture));

        return PaymentReceipt::create([
            'uploaded_by' => User::factory()->create()->id,
            'original_filename' => 'lembar-8.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => filesize($this->fixture),
            'checksum' => hash_file('sha256', $this->fixture),
            'status' => ReceiptStatus::PENDING->value,
        ]);
    }

    private function buatPembayaran(string $paymentNumber): Payment
    {
        $package = InternetPackage::query()->firstOrFail();

        $customer = Customer::factory()->create([
            'status' => 'active',
            'internet_package_id' => $package->id,
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 110000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 110000,
            'activation_date' => '2025-12-01',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-LBR-'.substr($paymentNumber, -4),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-01',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'subtotal' => 110000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 110000,
            'paid_amount' => 110000,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);

        return Payment::create([
            'payment_number' => $paymentNumber,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-01-05',
            'payment_method' => 'cash',
            'amount' => 110000,
            'received_by' => User::factory()->create()->id,
            'payment_status' => 'valid',
        ]);
    }
}
