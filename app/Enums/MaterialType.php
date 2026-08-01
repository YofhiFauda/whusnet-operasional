<?php

namespace App\Enums;

/**
 * Tipe material/perangkat pasif — **USANG sebagai daftar pilihan.**
 *
 * Kategori sudah pindah ke master `item_categories` (model `ItemCategory`);
 * dropdown, validasi, dan penamaan semuanya baca dari sana. Enum ini SENGAJA
 * dipertahankan, jangan dihapus, karena dua alasan:
 *
 * 1. Tujuh case di bawah adalah code kategori bawaan (`is_system`) yang ditanam
 *    migrasi. Nilainya jadi kontrak, dan enum ini dokumentasi kontrak itu.
 * 2. Kode lama & data lama (`task_materials.item_type`,
 *    `customer_technical_details.passive_device_type`) menyimpan value-nya.
 *
 * Yang TIDAK boleh: menambah case baru di sini. Kategori baru dibuat admin
 * lewat Master Kategori Barang — nambah case cuma menghidupkan lagi dua daftar
 * yang harus disinkronkan manual, persis masalah yang bikin enum ini dipensiun.
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
