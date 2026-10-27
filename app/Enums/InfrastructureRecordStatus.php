<?php

namespace App\Enums;

/**
 * Status DATA posisi (Slot/Port), bukan status pemakaian. "Kosong/terpakai"
 * diturunkan dari koneksi/assignment saat dibaca — lihat
 * `InfrastructureOccupancy` — supaya tidak ada dua sumber kebenaran yang
 * bisa menyimpang. `belum_didata` WAJIB dibedakan dari kosong (§4.4):
 * kapasitas kosong tidak boleh menghitung posisi yang belum didata.
 */
enum InfrastructureRecordStatus: string
{
    case BELUM_DIDATA = 'belum_didata';
    case TERVERIFIKASI = 'terverifikasi';
    case RUSAK_NONAKTIF = 'rusak_nonaktif';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIDATA => 'Belum didata',
            self::TERVERIFIKASI => 'Terverifikasi',
            self::RUSAK_NONAKTIF => 'Rusak/nonaktif',
        };
    }
}
