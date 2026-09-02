<?php

namespace App\Http\Resources\CustomerPortal;

use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * @mixin Invoice
 *
 * `GET /me/invoices/{invoice_number}` — sama seperti `InvoiceResource`,
 * plus daftar pembayaran yang menempel ("detail tagihan + pembayaran yang
 * menempel", business-logic.md daftar endpoint). Resource TERPISAH dari
 * `InvoiceResource` (bukan flag/parameter) supaya `index()` tetap ringan
 * dan tidak ada branching kondisional di dalam satu toArray().
 */
class InvoiceDetailResource extends InvoiceResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ]);
    }
}
