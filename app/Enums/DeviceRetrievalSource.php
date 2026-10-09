<?php

namespace App\Enums;

/**
 * Jalur masuk modem hasil pengambilan dari pelanggan (ADHOC-88).
 */
enum DeviceRetrievalSource: string
{
    /** Teknisi mencabut lewat task Ambil Modem (DEAC), transit dulu sebelum diterima gudang. */
    case DEAC = 'deac';

    /** Pelanggan mengantar sendiri ke gudang, tanpa task. Langsung diterima. */
    case WALK_IN = 'walk_in';

    /**
     * Modem lama dicabut saat teknisi memasang SN baru (C-REQ Ganti Modem/
     * Migrasi, `InventoryService::installSerial()`). Sub-jenisnya (Ganti
     * Modem vs Migrasi) ditelusuri dari `task->creqDetail->category`, BUKAN
     * case enum terpisah — satu jalur kode (`installSerial()`) buat semua
     * kategori C-REQ yang kebetulan ganti modem.
     */
    case CREQ_SWAP = 'creq_swap';

    public function label(): string
    {
        return match ($this) {
            self::DEAC => 'Diambil teknisi (Task DEAC)',
            self::WALK_IN => 'Diantar pelanggan',
            self::CREQ_SWAP => 'Digantikan saat C-REQ',
        };
    }
}
