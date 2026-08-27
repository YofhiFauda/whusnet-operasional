<?php

namespace App\Services;

use App\Services\Receipts\ReceiptQrRenderer;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Render QR stiker/kartu pelanggan (docs/plan/qr-code/
 * rancangan-qr-pelanggan-final.md §3.1).
 *
 * SVG, bukan PNG — pola sama seperti {@see ReceiptQrRenderer}:
 * tajam di ukuran cetak berapa pun, tanam langsung sebagai data URI tanpa
 * berkas sementara.
 *
 * **ECC level M** (bukan H seperti kwitansi) — payload QR pelanggan ~62
 * karakter (URL lengkap `/q1/{token}.{sig}`) sudah menekan kapasitas versi
 * 4; level M cukup buat stiker vinyl 2×2cm. Kalau nanti ukuran cetak jadi
 * kendala, naikkan ke level Q dan PERBESAR stiker — jangan potong panjang
 * signature (§3.1).
 */
class CustomerQrCodeRenderer
{
    public function dataUri(string $url, int $size = 240): string
    {
        $result = Builder::create()
            ->writer(new SvgWriter)
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size($size)
            ->margin(8)
            ->build();

        return $result->getDataUri();
    }
}
