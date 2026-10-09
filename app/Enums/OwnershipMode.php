<?php

namespace App\Enums;

/**
 * Axis KETIGA yang independen dari `TrackingType`/`EquipmentClass` — nentuin
 * boleh/gak boleh sebuah unit serial transisi ke status `SerialStatus::INSTALLED`.
 * OTDR/laptop/Optical Power Meter itu SERIALIZED + AKTIF sama kayak ONT/router,
 * tapi TIDAK PERNAH terpasang ke pelanggan — cuma looping ISSUED⇄RETURNED.
 * Tanpa pembeda ini, gampang ke-tag salah ("OTDR terpasang di pelanggan X").
 *
 * Guard transisi wajib ditegakkan di Service/Observer, bukan cuma konvensi —
 * lihat `warehouse_inventory_asset_traceability_analysis_advanced.md` §16.2.
 */
enum OwnershipMode: string
{
    /**
     * Boleh transisi ke INSTALLED — custody-nya berpindah permanen ke
     * pelanggan (dicatat lewat `customer_technical_details`, bukan disalin
     * ke tabel baru). Modem, ONT, router pelanggan.
     */
    case INSTALLABLE = 'installable';

    /**
     * TIDAK PERNAH boleh transisi ke INSTALLED — tetap aset perusahaan
     * selamanya, cuma dipinjam-pakaikan (ISSUED) lalu wajib balik
     * (RETURNED). OTDR, laptop, Optical Power Meter, alat kerja lain.
     */
    case COMPANY_ASSET = 'company_asset';

    public function label(): string
    {
        return match ($this) {
            self::INSTALLABLE => 'Dipasang ke Pelanggan',
            self::COMPANY_ASSET => 'Aset Perusahaan (Alat Kerja)',
        };
    }
}
