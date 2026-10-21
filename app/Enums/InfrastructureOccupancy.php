<?php

namespace App\Enums;

/**
 * Lima keadaan pemakaian posisi yang WAJIB dibedakan (§4.4). Diturunkan
 * saat dibaca dari `InfrastructureRecordStatus` + koneksi aktif — lihat
 * `InfrastructureConnectionService::occupancyOf()`. Hanya
 * `KOSONG_TERVERIFIKASI` yang boleh dihitung sebagai kapasitas kosong.
 */
enum InfrastructureOccupancy: string
{
    case BELUM_DIDATA = 'belum_didata';
    case KOSONG_TERVERIFIKASI = 'kosong_terverifikasi';
    case TERPAKAI_TERVERIFIKASI = 'terpakai_terverifikasi';
    case TERPAKAI_BELUM_TERVERIFIKASI = 'terpakai_belum_terverifikasi';
    case RUSAK_NONAKTIF = 'rusak_nonaktif';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIDATA => 'Belum didata',
            self::KOSONG_TERVERIFIKASI => 'Kosong terverifikasi',
            self::TERPAKAI_TERVERIFIKASI => 'Terpakai terverifikasi',
            self::TERPAKAI_BELUM_TERVERIFIKASI => 'Terpakai belum terverifikasi',
            self::RUSAK_NONAKTIF => 'Rusak/nonaktif',
        };
    }
}
