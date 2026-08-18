<?php

namespace App\Enums;

/**
 * Ke mana uang kas admin diserahkan.
 *
 * Dipisah dari status karena menjawab pertanyaan yang berbeda: status menjawab
 * "sudah diperiksa belum", channel menjawab "uangnya sekarang di mana" —
 * pertanyaan yang justru paling sering ditanyakan Owner.
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §4.6.
 */
enum CashDepositChannel: string
{
    /** Diserahkan fisik ke brankas kantor / Owner. */
    case TUNAI_BRANKAS = 'tunai_brankas';

    /** Disetorkan ke rekening bank. Wajib bank + no. referensi + bukti. */
    case TRANSFER_BANK = 'transfer_bank';

    public function label(): string
    {
        return match ($this) {
            self::TUNAI_BRANKAS => 'Tunai (Brankas / Owner)',
            self::TRANSFER_BANK => 'Transfer Bank',
        };
    }

    /**
     * Channel yang identitas tujuannya wajib diisi. Setoran ke bank tanpa nama
     * bank & nomor referensi tidak bisa dicocokkan dengan mutasi rekening —
     * catatan seperti itu tak lebih baik daripada tidak ada catatan.
     */
    public function requiresBankDetails(): bool
    {
        return $this === self::TRANSFER_BANK;
    }
}
