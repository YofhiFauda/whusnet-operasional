<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ManualInvoiceCategory;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RevenueCategory;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Menerbitkan tagihan "Biaya Instalasi" (kategori Bisnis, divalidasi
 * Business Development/BD) — dipakai DUA pemanggil: `CustomerAcquisitionController::
 * updateInstallationFee()` (pelanggan yang sudah lama ACTIVE, kasus lama
 * sebelum modul `/business-development-verifications` ada / fallback) dan
 * `BusinessDevelopmentVerificationController::verify()` (jalur utama
 * sekarang, sebelum pelanggan ACTIVE).
 *
 * Menerbitkan Tagihan Manual (`InvoiceType::MANUAL`, kategori `lainnya` / `Biaya Instalasi`).
 */
class InstallationFeeInvoiceService
{
    /**
     * Satu literal, dipakai di `manual_subtype_name` DAN baris rincian
     * (`custom_name`) — dua tempat itu dulu masing-masing ketik ulang
     * `'Biaya Instalasi'` sendiri; typo di satu tempat bikin laporan yang
     * menyaring `manual_subtype_name` (lihat ReceiptPresenter) berbeda dari
     * nama yang tampil di rincian tagihan.
     */
    public const SUBTYPE_NAME = 'Biaya Instalasi';

    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly InvoiceItemBuilder $items,
    ) {}

    /**
     * Wajib dipanggil di dalam transaksi pemanggil: invoice dan baris rinciannya
     * harus commit bersama, dan lockForUpdate() di generator nomor bermakna
     * hanya selama transaksinya hidup.
     *
     * @throws ValidationException pelanggan belum punya layanan (POP, paket,
     *                             dan PPN tagihan diambil dari layanan).
     */
    public function issue(Customer $customer, float $amount, ?int $actorId): Invoice
    {
        $service = $customer->customerService;

        if (! $service) {
            throw ValidationException::withMessages([
                'customer_id' => 'Pelanggan ini belum memiliki layanan aktif — Biaya Instalasi butuh layanan aktif untuk menentukan POP, paket, dan PPN.',
            ]);
        }

        $period = now()->format('Y-m');

        // PPN mengikuti layanan pelanggan, sama seperti ManualInvoiceService
        // (persen dari subtotal, ditambahkan di atas). Dulu PPN dipaksa 0, jadi
        // pelanggan Bisnis yang kena PPN ditagih tanpa pajak diam-diam.
        $ppnRate = max(0, (float) ($service->ppn ?? 0));
        $ppnAmount = Money::of($amount * ($ppnRate / 100));
        $total = Money::add($amount, $ppnAmount);

        $invoice = Invoice::create([
            'invoice_number' => $this->numbers->nextFor(InvoiceType::MANUAL, ManualInvoiceCategory::LAINNYA, now()),
            'invoice_type' => InvoiceType::MANUAL->value,
            'manual_category' => ManualInvoiceCategory::LAINNYA->value,
            'manual_subtype_name' => self::SUBTYPE_NAME,
            'description' => self::SUBTYPE_NAME.' (divalidasi Busdev) — '.$customer->full_name,
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service?->id,
            'internet_package_id' => $service?->internet_package_id,
            'billing_period' => $period,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => $amount,
            'discount' => 0,
            'ppn' => $ppnRate,
            'total_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
            'created_by' => $actorId,
        ]);

        // Tanpa baris rincian, tagihan tampil lewat fallback "Langganan Internet"
        // di invoices/show — seolah biaya instalasi adalah paket bulanan. Satu
        // baris kategori Lainnya dengan nama tetap, seperti jalur Tagihan Manual.
        $this->items->rebuildFor($invoice, [[
            'category_code' => RevenueCategory::CODE_LAINNYA,
            'custom_name' => self::SUBTYPE_NAME,
            'description' => null,
            'amount' => $amount,
        ]]);

        return $invoice;
    }
}
