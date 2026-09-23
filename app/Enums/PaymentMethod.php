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
 *
 * `QRIS` DIHAPUS (2026-09-22, permintaan user) — tidak pernah dipakai
 * operasional. Baris lama di DB (kalau ada) tetap tersimpan apa adanya
 * (kolom `payments.payment_method` string biasa, bukan cast enum), cuma
 * tak lagi bisa dipilih dari form/filter mana pun.
 */
enum PaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case KOLEKTOR = 'kolektor';
    case LAINNYA = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::TRANSFER => 'Transfer Bank',
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

    /**
     * Lainnya wajib mengisi keterangan (metode apa persisnya — mis. "OVO",
     * "Dana", "GoPay") — form menampilkan input tambahan begitu dipilih
     * (permintaan user 2026-09-22).
     */
    public function requiresDescription(): bool
    {
        return $this === self::LAINNYA;
    }
}
