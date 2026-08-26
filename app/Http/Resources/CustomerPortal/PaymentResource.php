<?php

namespace App\Http\Resources\CustomerPortal;

use App\Enums\PaymentStatus;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `GET /me/payments` (docs/api/api-portal-pelanggan/business-logic.md §2).
 *
 * `overpay_amount` WAJIB keluar (kelebihan bayar adalah uang pelanggan).
 * `bank_name`/`account_number` SENGAJA tidak masuk whitelist dokumen —
 * default exclude (dikonfirmasi user 2026-08-25, whitelist ketat).
 * `invoice_number` disertakan sebagai pendamping (dikonfirmasi user) —
 * nomor dokumen publik, bukan data sensitif.
 *
 * Pembayaran `ditolak` TETAP TAMPIL (uang yang sudah diserahkan ke kolektor
 * tidak boleh lenyap dari layar pelanggan tanpa penjelasan) tapi
 * `reject_reason` HARAM keluar — label diganti pesan generik, bukan
 * `PaymentStatus::label()` mentah ("Ditolak").
 */
class PaymentResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'billing_period' => $this->billing_period,
            'invoice_number' => $this->invoice?->invoice_number,
            'amount' => $this->money($this->amount),
            'overpay_amount' => $this->money($this->overpay_amount),
            'payment_method' => $this->payment_method,
            'payment_status' => [
                'value' => $this->payment_status->value,
                'label' => $this->payment_status === PaymentStatus::DITOLAK
                    ? 'belum terverifikasi — hubungi admin'
                    : $this->payment_status->label(),
            ],
            'has_receipt' => $this->payment_status === PaymentStatus::VALID,
        ];
    }
}
