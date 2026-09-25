<?php

namespace App\Enums;

/**
 * Sumber pembebasan tagihan periode (ADHOC-87) — satu kemampuan
 * (`BillingPeriodWaiverService::waive()`), dua pintu masuk yang beda efek ke
 * status pelanggan:
 * - `TERMINATION`: dari form Request Putus Langganan, dipakai bareng
 *   transisi `terminated` dalam satu transaksi.
 * - `LEAVE`: dari aksi "Cuti Berlangganan", status pelanggan TIDAK berubah.
 */
enum BillingWaiverSource: string
{
    case TERMINATION = 'termination';
    case LEAVE = 'leave';

    public function label(): string
    {
        return match ($this) {
            self::TERMINATION => 'Request Putus Langganan',
            self::LEAVE => 'Cuti Berlangganan',
        };
    }
}
