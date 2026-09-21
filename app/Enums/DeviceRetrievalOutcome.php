<?php

namespace App\Enums;

/**
 * Hasil lapangan task Ambil Modem (DEAC) — dipilih teknisi di form laporan
 * DEAC (ADHOC-86). Hanya `DIAMBIL` yang menggerakkan inventori dan mengisi
 * `customer_devices.device_retrieved_at`; dua lainnya selesai tanpa SN dengan
 * alasan wajib, sehingga badge "Sudah Diambil" di List Putus Langganan tidak
 * berbohong (alat masih di pelanggan).
 */
enum DeviceRetrievalOutcome: string
{
    case DIAMBIL = 'diambil';
    case TIDAK_DITEMUKAN = 'tidak_ditemukan';
    case DITOLAK = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::DIAMBIL => 'Alat berhasil diambil',
            self::TIDAK_DITEMUKAN => 'Alat tidak ditemukan',
            self::DITOLAK => 'Pelanggan menolak / tidak bisa ditemui',
        };
    }

    public function isRetrieved(): bool
    {
        return $this === self::DIAMBIL;
    }
}
