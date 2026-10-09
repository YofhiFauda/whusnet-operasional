<?php

namespace App\Exceptions;

use App\Models\Item;
use RuntimeException;

/**
 * Dilempar `InventoryService::consumeFromCustody()` kalau tim teknisi
 * mengklaim pemakaian lebih besar dari gabungan sisa custody mereka. Ini
 * BUKAN sekadar validasi input — ini ceiling struktural yang mencegah
 * overclaim material non-serial secara sistem, bukan cuma dicurigai
 * belakangan lewat anomaly detection. Lihat
 * docs/plan/warehouse/kontrol-anti-manipulasi.md §7.
 *
 * Caller (controller laporan pemasangan/maintenance/dst) yang menerjemahkan
 * ini jadi pesan validasi ke teknisi — service tidak tahu apa-apa soal HTTP.
 */
class InsufficientCustodyException extends RuntimeException
{
    /**
     * @param  list<int>  $technicianIds  anggota tim yang custody-nya digabung dicek
     */
    public function __construct(
        public readonly array $technicianIds,
        public readonly Item $item,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(sprintf(
            'Sisa custody tim untuk %s tidak cukup: diklaim %s, tersedia %s (gabungan seluruh anggota tim).',
            $item->name,
            number_format($requested, 2),
            number_format($available, 2),
        ));
    }
}
