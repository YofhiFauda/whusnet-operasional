<?php

namespace App\Enums;

/**
 * Asal modem/ONT pelanggan (`customer_devices.acquisition_source`). Authoritative
 * untuk label display, dibaca per-device, BUKAN per-pelanggan seperti
 * `contract_type` — supaya tetap akurat kalau pelanggan ganti modem di tengah
 * jalan (Sewa → Beli dari Gudang).
 */
enum DeviceAcquisitionSource: string
{
    case SEWA = 'sewa';

    case BELI_SENDIRI = 'beli_sendiri';

    case BELI_GUDANG = 'beli_gudang';

    public function label(): string
    {
        return match ($this) {
            self::SEWA => 'Sewa',
            self::BELI_SENDIRI => 'Beli Sendiri',
            self::BELI_GUDANG => 'Beli dari Gudang',
        };
    }

    /**
     * Nama varian badge (info/warning/success), bukan kelas CSS — pemetaan ke
     * warna dilakukan view supaya enum tidak terikat ke Tailwind.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::SEWA => 'info',
            self::BELI_SENDIRI => 'warning',
            self::BELI_GUDANG => 'success',
        };
    }
}
