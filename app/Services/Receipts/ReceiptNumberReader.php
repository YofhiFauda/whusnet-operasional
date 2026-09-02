<?php

namespace App\Services\Receipts;

use App\Enums\ReceiptMatchMethod;

/**
 * Pembaca nomor pembayaran dari berkas kwitansi.
 *
 * Sengaja dibuat kontrak, bukan satu kelas besar bercabang: jalur QR
 * deterministik dan gratis, jalur OCR probabilistik dan berbiaya. Keduanya
 * punya syarat ketersediaan sendiri (`isAvailable()`), dan yang kedua bisa
 * mati total tanpa mematikan fitur — lihat ReceiptNumberExtractor.
 */
interface ReceiptNumberReader
{
    /**
     * Nomor pembayaran yang terbaca, atau null kalau tak ditemukan.
     * Melempar exception hanya untuk kegagalan teknis (mis. API error) —
     * "tidak terbaca" bukan kegagalan, itu hasil yang sah.
     */
    public function read(string $absolutePath): ?string;

    public function method(): ReceiptMatchMethod;

    /**
     * Boleh dipakai di lingkungan ini. OCR mengembalikan false selama API key
     * belum diisi, dan itu keadaan normal — bukan error.
     */
    public function isAvailable(): bool;
}
