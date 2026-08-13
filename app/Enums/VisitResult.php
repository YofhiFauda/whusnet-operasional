<?php

namespace App\Enums;

/**
 * Hasil satu kunjungan penagihan.
 *
 * `BAYAR` sengaja tidak sederajat dengan yang lain dari sisi kewenangan: ia
 * cuma boleh lahir sebagai turunan payment yang benar-benar tersimpan, tak
 * pernah dari input manual kolektor (lihat migration `collector_visits`).
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §12.
 */
enum VisitResult: string
{
    case BAYAR = 'bayar';
    case TIDAK_ADA_ORANG = 'tidak_ada_orang';
    case MENOLAK = 'menolak';
    case JANJI_BAYAR = 'janji_bayar';

    public function label(): string
    {
        return match ($this) {
            self::BAYAR => 'Bayar',
            self::TIDAK_ADA_ORANG => 'Tidak Ada Orang',
            self::MENOLAK => 'Menolak Bayar',
            self::JANJI_BAYAR => 'Janji Bayar',
        };
    }

    /**
     * Hasil yang boleh diinput manual kolektor. `bayar` TIDAK termasuk —
     * kalau boleh diketik, kolektor bisa mengaku sudah menagih tanpa uang
     * masuk, dan tabel kunjungan justru jadi alat menutupi lubang yang
     * seharusnya dia ungkap.
     *
     * @return array<int, string>
     */
    public static function manualValues(): array
    {
        return [
            self::TIDAK_ADA_ORANG->value,
            self::MENOLAK->value,
            self::JANJI_BAYAR->value,
        ];
    }

    /**
     * Kunjungan yang tidak menghasilkan uang — bahan utama laporan aging.
     */
    public function isUnproductive(): bool
    {
        return $this !== self::BAYAR;
    }
}
