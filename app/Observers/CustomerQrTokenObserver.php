<?php

namespace App\Observers;

use App\Models\CustomerQrToken;
use RuntimeException;

/**
 * Invariant: maksimal SATU token dengan `revoked_at IS NULL` per
 * `customer_id` (docs/plan/qr-code/rancangan-qr-pelanggan-final.md §4.1).
 *
 * Ditegakkan di sini — bukan cuma di CustomerQrTokenService::issue() — supaya
 * jalur artisan/tinker/import ikut terkunci, sama prinsipnya dengan
 * PaymentObserver::creating() (CLAUDE.md § Observer).
 */
class CustomerQrTokenObserver
{
    public function creating(CustomerQrToken $qrToken): void
    {
        $hasActiveToken = CustomerQrToken::query()
            ->where('customer_id', $qrToken->customer_id)
            ->whereNull('revoked_at')
            ->exists();

        if ($hasActiveToken) {
            throw new RuntimeException(
                "Pelanggan #{$qrToken->customer_id} sudah punya token QR aktif — cabut dulu sebelum menerbitkan yang baru."
            );
        }
    }
}
