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
 *
 * `SALDO` ditambah ADHOC-92 — pembayaran yang SELURUHNYA berasal dari Saldo
 * Pelanggan (auto-pay tagihan BULANAN saat terbit, atau pakai saldo manual
 * yang menutup penuh tanpa uang tunai tambahan). Bukan pilihan di dropdown
 * form manapun — dibuat sistem (`CustomerBalanceService::applyToOpenInvoices()`)
 * atau otomatis dari `PaymentService::record()` saat `use_balance_amount`
 * menutup seluruh nominal. Tidak masuk kas fisik mana pun.
 */
enum PaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case KOLEKTOR = 'kolektor';
    case LAINNYA = 'lainnya';
    case SALDO = 'saldo';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::TRANSFER => 'Transfer Bank',
            self::KOLEKTOR => 'Kolektor',
            self::LAINNYA => 'Lainnya',
            self::SALDO => 'Saldo Pelanggan',
        };
    }

    /**
     * Transfer wajib memilih rekening tujuan dari Master Rekening Bank
     * (`bank_account_id`, ADHOC-95). `bank_name`/`account_number` di
     * payment tidak lagi diketik — diisi PaymentService sebagai snapshot.
     */
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
     * Metode yang menampilkan field opsional "Nama Pengirim" (ADHOC-95):
     * uangnya lewat pihak ketiga (rekening bank / kolektor lapangan),
     * jadi nama pengirim faktual bisa beda dari nama pelanggan. Cash di
     * kantor & Lainnya tidak — `sender_name` di-null-kan untuk metode itu.
     */
    public function requiresSenderName(): bool
    {
        return $this === self::TRANSFER || $this === self::KOLEKTOR;
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
