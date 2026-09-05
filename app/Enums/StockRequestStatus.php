<?php

namespace App\Enums;

/**
 * Status `stock_requests` — Permintaan Stok Cabang→Pusat (2026-09-03).
 * Jawaban atas gap "Jetis habis stok, Pusat gak sadar": ini SINYAL/tiket
 * inisiatif dari CABANG, bukan Pusat nunggu notice sendiri lewat badge
 * Stok Rendah pasif.
 *
 * SENGAJA BUKAN sumber pergerakan stok — request yang Fulfilled TETAP gak
 * mindahin barang apa pun sendiri, admin Pusat tetap wajib bikin Transfer
 * sungguhan (`WarehouseTransferController`, ledger-backed) secara terpisah.
 * Prinsip "jangan bikin sumber kebenaran kedua" (rancangan-ui.md) — request
 * ini murni antrean komunikasi/tiket, ledger `inventory_transactions` tetap
 * satu-satunya yang nyatet pergerakan fisik.
 */
enum StockRequestStatus: string
{
    case PENDING = 'pending';
    case FULFILLED = 'fulfilled';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Diproses',
            self::FULFILLED => 'Sudah Dipenuhi',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::PENDING;
    }
}
