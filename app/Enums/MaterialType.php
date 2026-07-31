<?php

namespace App\Enums;

/**
 * Tipe material/perangkat pasif yang dipakai pekerjaan lapangan.
 *
 * Nilainya SENGAJA identik dengan daftar `passive_device_type` di
 * CustomerDeviceController — dua tempat itu bicara barang yang sama, cuma beda
 * sudut pandang (aset terpasang vs material terpakai). Kalau daftarnya
 * menyimpang, laporan pemakaian dan data perangkat pelanggan gak bisa
 * dicocokkan lagi.
 */
enum MaterialType: string
{
    case SPLITTER_ODP = 'splitter_odp';
    case KABEL_DROPCORE = 'kabel_dropcore';
    case PATCH_CORD = 'patch_cord';
    case MEDIA_CONVERTER = 'media_converter';
    case ANTENA_RADIO = 'antena_radio';
    case AKSESORIS_PASANG = 'aksesoris_pasang';
    case LAINNYA = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::SPLITTER_ODP => 'Splitter / ODP',
            self::KABEL_DROPCORE => 'Kabel Dropcore',
            self::PATCH_CORD => 'Patch Cord',
            self::MEDIA_CONVERTER => 'Media Converter',
            self::ANTENA_RADIO => 'Antena / Radio',
            self::AKSESORIS_PASANG => 'Aksesoris Pemasangan',
            self::LAINNYA => 'Lainnya',
        };
    }

    /**
     * Satuan default — dipakai form buat auto-isi kolom satuan begitu tipe
     * dipilih, biar teknisi gak salah tulis "pcs" untuk kabel.
     */
    public function defaultUnit(): string
    {
        return match ($this) {
            self::KABEL_DROPCORE => 'meter',
            default => 'pcs',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
