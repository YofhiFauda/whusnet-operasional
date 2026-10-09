<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ManualInvoiceCategory;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penerbitan Tagihan Manual (ADHOC-70, `InvoiceType::MANUAL`) — Perbaikan /
 * Lainnya / Pindah Lokasi. Murni Invoice `belum_dibayar`, TANPA Payment;
 * pembayarannya lewat jalur normal (keputusan user 2026-09-23).
 *
 * BUKAN `ManualInvoiceService` (ADHOC-60, tagihan berbaris per kategori
 * pendapatan / INSIDENTAL) — dua fitur berbeda yang kebetulan sama-sama
 * disebut "manual".
 *
 * Dulu logikanya tertulis langsung di InvoiceController::store(). Diangkat ke
 * sini (2026-09-28) karena sekarang ada DUA pintu: form /invoices/create dan
 * "Setujui & Terbitkan Tagihan" di Verifikasi Biaya C-REQ — keduanya wajib
 * menghasilkan tagihan yang sama persis.
 */
class ManualCategoryInvoiceService
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    /**
     * @throws ValidationException pelanggan belum punya layanan (POP & paket
     *                             tagihan diambil dari layanan).
     */
    public function issue(
        Customer $customer,
        ManualInvoiceCategory $category,
        ?string $subtypeName,
        string $description,
        float|int|string $amount,
        ?int $actorId,
    ): Invoice {
        $service = $customer->customerService;

        if (! $service) {
            throw ValidationException::withMessages([
                'customer_id' => 'Pelanggan ini belum memiliki layanan aktif — Tagihan Manual butuh layanan aktif untuk menentukan POP & paket.',
            ]);
        }

        return DB::transaction(function () use ($customer, $service, $category, $subtypeName, $description, $amount, $actorId) {
            $billingPeriod = now()->format('Y-m');
            $issueDate = now()->toDateString();

            return Invoice::create([
                'invoice_number' => $this->numbers->nextFor(InvoiceType::MANUAL, $category, $issueDate),
                'invoice_type' => InvoiceType::MANUAL->value,
                'manual_category' => $category->value,
                'manual_subtype_name' => $category->requiresSubtypeName() ? $subtypeName : null,
                'description' => $description,
                'customer_id' => $customer->id,
                'pop_id' => $customer->pop_id,
                'customer_service_id' => $service->id,
                'internet_package_id' => $service->internet_package_id,
                'billing_period' => $billingPeriod,
                'issue_date' => $issueDate,
                'due_date' => $issueDate,
                'subtotal' => $amount,
                'discount' => 0,
                'ppn' => 0,
                'total_amount' => $amount,
                'paid_amount' => 0,
                'remaining_amount' => $amount,
                'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
                'created_by' => $actorId,
            ]);
        });
    }
}
