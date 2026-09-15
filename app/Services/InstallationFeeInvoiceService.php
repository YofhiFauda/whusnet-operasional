<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RevenueCategory;
use App\Models\RevenueSubcategory;

/**
 * Menerbitkan tagihan "Biaya Instalasi" (kategori Bisnis, divalidasi
 * Business Development/BD) — dipakai DUA pemanggil: `CustomerAcquisitionController::
 * updateInstallationFee()` (pelanggan yang sudah lama ACTIVE, kasus lama
 * sebelum modul `/business-development-verifications` ada / fallback) dan
 * `BusinessDevelopmentVerificationController::verify()` (jalur utama
 * sekarang, sebelum pelanggan ACTIVE). Satu logika, satu tempat — supaya
 * rumus/kategori tagihannya tidak diam-diam menyimpang antara dua pemanggil.
 *
 * SENGAJA menerbitkan `Invoice` BARU & TERPISAH dari Invoice Awal
 * (`extra_installation_fee`, diisi CS di `CustomerVerificationController::
 * finalVerify()`) — lihat catatan panjang di migration
 * `add_installation_fee_invoice_id_to_customer_acquisitions_table` kenapa
 * dua tagihan ini tidak boleh digabung.
 */
class InstallationFeeInvoiceService
{
    public function __construct(private readonly ManualInvoiceService $manualInvoiceService) {}

    public function issue(Customer $customer, float $amount): Invoice
    {
        $subcategory = RevenueSubcategory::where('code', RevenueSubcategory::CODE_BIAYA_AKTIVASI)
            ->whereHas('category', fn ($q) => $q->where('code', RevenueCategory::CODE_JASA_INSTALASI))
            ->firstOrFail();

        return $this->manualInvoiceService->create($customer, [
            'billing_period' => now()->format('Y-m'),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'invoice_type' => '', // biar ManualInvoiceService yang resolve — baris non-langganan otomatis jadi INSIDENTAL
            'lines' => [[
                'revenue_category_id' => $subcategory->revenue_category_id,
                'revenue_subcategory_id' => $subcategory->id,
                'amount' => $amount,
                'description' => 'Biaya Instalasi (divalidasi Busdev) — '.$customer->full_name,
            ]],
        ]);
    }
}
