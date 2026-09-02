<?php

namespace App\Services\Receipts;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Membuat QR untuk dicetak di kwitansi.
 *
 * **SVG, bukan PNG** — dirender tanpa GD/imagick, tajam di printer berapa pun
 * DPI-nya, dan bisa ditanam langsung sebagai data URI di halaman cetak tanpa
 * menyimpan berkas sementara.
 *
 * **Isi QR = `payment_number` polos**, bukan URL. Alasannya kwitansi ini
 * dibaca kembali oleh sistem sendiri saat upload; membungkusnya jadi URL cuma
 * menambah teks yang harus dikupas lagi, dan mengikat kertas yang sudah
 * dicetak pada domain yang bisa berubah.
 *
 * **Error correction HIGH**: kwitansi kertas terlipat, kena air, difotokopi.
 * Level H memungkinkan ~30% modul rusak dan tetap terbaca — itu selisih antara
 * pencocokan otomatis dan pekerjaan manual admin.
 */
class ReceiptQrRenderer
{
    public function dataUri(string $paymentNumber, int $size = 220): string
    {
        $result = Builder::create()
            ->writer(new SvgWriter)
            ->data($paymentNumber)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size($size)
            ->margin(8)
            ->build();

        return $result->getDataUri();
    }
}
