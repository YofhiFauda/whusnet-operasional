<?php

namespace App\Support;

/**
 * Batas ukuran upload foto/bukti — SATU sumber untuk rule validasi server
 * dan hint "Maks. 5 MB" di form (`<x-upload-limit-hint />`).
 *
 * Dulu tiap form punya angka sendiri (2 MB / 4 MB / 5 MB) dan teks hint
 * di-hardcode terpisah — foto kamera HP (3–6 MB) ditolak di form 2 MB, dan
 * hint gampang menyimpang dari rule aslinya. Keputusan user 2026-11-14:
 * sementara semua upload foto maks 5 MB. Ubah di sini, rule & hint ikut.
 *
 * Tidak berlaku untuk unggah kwitansi massal (8 MB/berkas,
 * PaymentReceiptController) dan import Excel — itu bukan upload foto.
 */
final class UploadLimit
{
    public const PHOTO_MAX_KB = 5120;

    /** Rule Laravel `max:` (kilobyte) untuk kolom file. */
    public static function rule(): string
    {
        return 'max:'.self::PHOTO_MAX_KB;
    }

    /** Label untuk ditampilkan ke user, mis. "5 MB". */
    public static function label(): string
    {
        return (self::PHOTO_MAX_KB / 1024).' MB';
    }
}
