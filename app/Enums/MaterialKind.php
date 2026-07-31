<?php

namespace App\Enums;

/**
 * Fase pencatatan material pada satu FopTask.
 *
 * ESTIMASI ditulis teknisi survey (perkiraan kebutuhan), TERPAKAI ditulis
 * teknisi pemasangan (realisasi). Dua-duanya tinggal di tabel yang sama supaya
 * selisih estimasi-vs-realisasi cukup satu self-join, bukan union dua skema
 * kembar yang gampang menyimpang.
 */
enum MaterialKind: string
{
    case ESTIMASI = 'estimasi';
    case TERPAKAI = 'terpakai';

    public function label(): string
    {
        return match ($this) {
            self::ESTIMASI => 'Estimasi Kebutuhan Alat',
            self::TERPAKAI => 'Perangkat Pasif Terpakai',
        };
    }
}
