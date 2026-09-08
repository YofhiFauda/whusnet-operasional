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

    /**
     * Sebagian item/qty udah dicatat terkirim (2026-09-07,
     * `StockRequestService::recordDelivery()`) tapi belum semua — masih
     * "terbuka" (bisa dikirim susulan atau ditutup manual), TAPI beda dari
     * PENDING murni: udah gak boleh ditolak/dibatalkan lagi (barang beneran
     * udah mulai bergerak, lihat `canRejectOrCancel()`).
     */
    case PARTIAL = 'partial';

    case FULFILLED = 'fulfilled';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Diproses',
            self::PARTIAL => 'Sebagian Dipenuhi',
            self::FULFILLED => 'Sudah Dipenuhi',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    /**
     * Masih bisa diproses lebih lanjut (catat pengiriman / tandai cukup) —
     * PENDING dan PARTIAL dua-duanya, beda dari `canRejectOrCancel()` yang
     * cuma PENDING.
     */
    public function isOpen(): bool
    {
        return $this === self::PENDING || $this === self::PARTIAL;
    }

    /**
     * Tolak & Batalkan cuma sah selagi BELUM ada barang yang tercatat
     * terkirim sama sekali — begitu masuk PARTIAL, batalin/nolak tiket jadi
     * gak masuk akal (fisiknya udah mulai jalan), satu-satunya jalan
     * penutup tinggal fulfill()/"Tandai Cukup".
     */
    public function canRejectOrCancel(): bool
    {
        return $this === self::PENDING;
    }
}
