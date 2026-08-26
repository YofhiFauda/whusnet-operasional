<?php

namespace App\Http\Resources\CustomerPortal;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `GET /me/invoices` (docs/api/api-portal-pelanggan/business-logic.md §2) —
 * whitelist kolom persis dokumen. `paid_amount`/`remaining_amount`/
 * `invoice_status` DIBACA APA ADANYA dari kolom, TIDAK dihitung ulang di
 * sini — `Invoice::recalculateFromPayments()` satu-satunya sumber
 * kebenaran ketiga nilai itu.
 */
class InvoiceResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoice_number' => $this->invoice_number,
            'invoice_type' => [
                'value' => $this->invoice_type->value,
                'label' => $this->invoice_type->label(),
            ],
            'billing_period' => $this->billing_period,
            'issue_date' => $this->issue_date?->toIso8601String(),
            'due_date' => $this->due_date?->toIso8601String(),
            'total_amount' => $this->money($this->total_amount),
            'paid_amount' => $this->money($this->paid_amount),
            'remaining_amount' => $this->money($this->remaining_amount),
            'invoice_status' => [
                'value' => $this->invoice_status->value,
                'label' => $this->invoice_status->label(),
            ],
        ];
    }
}
