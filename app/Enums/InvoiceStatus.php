<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case BELUM_DIBAYAR = 'belum_dibayar';
    case SEBAGIAN = 'sebagian';
    case LUNAS = 'lunas';
    case BATAL = 'batal';

    /**
     * Piutang yang dihapus buku (ADHOC-90). BUKAN batal: tagihannya sah dan
     * pernah menagih, hanya diakui tak akan tertagih. Sengaja di luar
     * `Invoice::OUTSTANDING_STATUSES` supaya otomatis keluar dari semua
     * hitungan piutang/tunggakan.
     */
    case TAK_TERTAGIH = 'tak_tertagih';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIBAYAR => 'Belum Dibayar',
            self::SEBAGIAN => 'Sebagian',
            self::LUNAS => 'Lunas',
            self::BATAL => 'Batal',
            self::TAK_TERTAGIH => 'Tak Tertagih',
        };
    }
}
