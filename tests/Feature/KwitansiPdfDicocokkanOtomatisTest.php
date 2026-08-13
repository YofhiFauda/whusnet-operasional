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
use App\Services\Receipts\PdfPageRasterizer;
use App\Services\Receipts\QrReceiptNumberReader;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gejala yang dijaga: kwitansi PDF tidak pernah tercocokkan otomatis.
 *
 * Halaman cetak kwitansi mengembalikan View HTML, jadi alur paling wajar bagi
 * admin adalah Print → "Save as PDF". Dulu QrReceiptNumberReader melewatkan
 * semua non-gambar dengan alasan "jalur berikutnya yang menangani" — padahal
 * jalur berikutnya (OCR Gemini) mati tanpa API key. Akibatnya SETIAP kwitansi
 * PDF berakhir FAILED dan menunggu kerja manual, walau QR-nya utuh.
 *
 * Fixture `tests/Fixtures/kwitansi-qr.pdf` meniru hasil cetak itu: satu QR
 * berisi PAY-202601-0001 di tengah halaman A4.
 */
class KwitansiPdfDicocokkanOtomatisTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = 'PAY-202601-0001';

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/Fixtures/kwitansi-qr.pdf');

        if (! app(PdfPageRasterizer::class)->isAvailable()) {
            $this->markTestSkipped('pdftoppm (poppler-utils) tidak terpasang di lingkungan ini.');
        }
    }

    public function test_qr_terbaca_dari_berkas_pdf(): void
    {
        $this->assertSame(self::NOMOR, app(QrReceiptNumberReader::class)->read($this->fixture));
    }

    public function test_render_dpi_tinggi_menghasilkan_gambar_lebih_besar(): void
    {
        // Percobaan kedua di 400 DPI ada untuk scan kertas kasar. Kalau
        // parameter dpi diabaikan diam-diam, kedua putaran menghasilkan gambar
        // identik dan eskalasinya cuma membakar waktu tanpa menambah peluang.
        $rasterizer = app(PdfPageRasterizer::class);

        $biasa = $rasterizer->pageToPng($this->fixture, 1, PdfPageRasterizer::DPI);
        $tinggi = $rasterizer->pageToPng($this->fixture, 1, PdfPageRasterizer::DPI_TINGGI);

        try {
            $this->assertNotNull($biasa);
            $this->assertNotNull($tinggi);
            $this->assertGreaterThan(getimagesize($biasa)[0], getimagesize($tinggi)[0]);
        } finally {
            foreach ([$biasa, $tinggi] as $file) {
                if ($file !== null) {
                    @unlink($file);
                }
            }
        }
    }

    public function test_berkas_pdf_tidak_meninggalkan_file_sementara(): void
    {
        // Berkas render dihapus di `finally`. Kalau bocor, upload bulk 100
        // kwitansi menumpuk 100 PNG di /tmp tanpa ada yang membersihkan.
        $before = glob(sys_get_temp_dir().'/kwitansi-*') ?: [];

        app(QrReceiptNumberReader::class)->read($this->fixture);

        $after = glob(sys_get_temp_dir().'/kwitansi-*') ?: [];

        $this->assertSame(count($before), count($after));
    }

    public function test_kwitansi_pdf_tercocokkan_otomatis_ke_pembayarannya(): void
    {
        Storage::fake('local');

        $payment = $this->buatPembayaran(self::NOMOR);

        $receipt = $this->buatReceiptDariFixture();

        app(PaymentReceiptService::class)->match($receipt);

        $receipt->refresh();

        $this->assertSame(ReceiptStatus::MATCHED, $receipt->status);
        $this->assertSame(ReceiptMatchMethod::QR, $receipt->match_method);
        $this->assertSame($payment->id, $receipt->payment_id);
        $this->assertSame($payment->pop_id, $receipt->pop_id);
        $this->assertSame(self::NOMOR, $receipt->detected_number);
    }

    public function test_nomor_terbaca_tapi_pembayarannya_tidak_ada_jadi_mismatch(): void
    {
        // Gerbang kedua tetap berlaku untuk jalur PDF: pola boleh lolos, tapi
        // tanpa payment-nya berkas TIDAK dicocokkan asal.
        Storage::fake('local');

        $receipt = $this->buatReceiptDariFixture();

        app(PaymentReceiptService::class)->match($receipt);

        $receipt->refresh();

        $this->assertSame(ReceiptStatus::MISMATCH, $receipt->status);
        $this->assertSame(self::NOMOR, $receipt->detected_number);
        $this->assertNull($receipt->payment_id);
    }

    private function buatReceiptDariFixture(): PaymentReceipt
    {
        $path = 'receipts/2026/01/kwitansi-qr.pdf';
        Storage::disk('local')->put($path, file_get_contents($this->fixture));

        return PaymentReceipt::create([
            'uploaded_by' => $this->pengunggah()->id,
            'original_filename' => 'kwitansi-qr.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => filesize($this->fixture),
            'checksum' => hash_file('sha256', $this->fixture),
            'status' => ReceiptStatus::PENDING->value,
        ]);
    }

    private function pengunggah(): User
    {
        return User::factory()->create();
    }

    private function buatPembayaran(string $paymentNumber): Payment
    {
        $this->seed(InternetPackageSeeder::class);
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
            'invoice_number' => 'INV-KWT-'.random_int(1000, 9999),
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
            'received_by' => $this->pengunggah()->id,
            'payment_status' => 'valid',
        ]);
    }
}
