<?php

namespace App\Services\Receipts;

use App\Enums\ReceiptMatchMethod;

/**
 * Pembaca nomor pembayaran dari berkas kwitansi.
 *
 * Kontrak supaya tiap jalur gambar punya syarat ketersediaan sendiri
 * (`isAvailable()`) dan bisa mati tanpa mematikan fitur — lihat
 * ReceiptNumberExtractor. Saat ini cuma ada jalur QR.
 */
interface ReceiptNumberReader
{
    /**
     * Nomor pembayaran yang terbaca, atau null kalau tak ditemukan.
     * Melempar exception hanya untuk kegagalan teknis (mis. decoder meledak) —
     * "tidak terbaca" bukan kegagalan, itu hasil yang sah.
     */
    public function read(string $absolutePath): ?string;

    public function method(): ReceiptMatchMethod;

    /**
     * Boleh dipakai di lingkungan ini (mis. QR butuh GD/Imagick). False
     * berarti jalur dilewati diam-diam — bukan error.
     */
    public function isAvailable(): bool;
}
