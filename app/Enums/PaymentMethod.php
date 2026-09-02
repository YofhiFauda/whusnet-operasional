<?php

namespace App\Enums;

/**
 * Cara uang diterima untuk satu payment. Menggantikan literal array
 * `['cash','transfer','qris','lainnya']` yang dulu diulang di beberapa
 * tempat (PaymentController::index()/store(), select di quick-payment-modal)
 * — sekarang satu sumber kebenaran.
 *
 * `KOLEKTOR` ditambah 2026-08-18: uang ditagih kolektor lapangan, bukan
 * diterima langsung admin. Kolektornya sendiri disimpan di kolom
 * `payments.collected_by` (sudah ada), bukan field enum baru — saldo
 * kolektor tetap DERIVED lewat CollectorBalanceService.
 */
enum PaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case QRIS = 'qris';
    case KOLEKTOR = 'kolektor';
    case LAINNYA = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::TRANSFER => 'Transfer Bank',
            self::QRIS => 'QRIS',
            self::KOLEKTOR => 'Kolektor',
            self::LAINNYA => 'Lainnya',
        };
    }

    /** Transfer wajib mengisi `bank_name` + `account_number`. */
    public function requiresBankDetails(): bool
    {
        return $this === self::TRANSFER;
    }

    /** Kolektor wajib mengisi `collected_by`. */
    public function requiresCollector(): bool
    {
        return $this === self::KOLEKTOR;
    }
}
