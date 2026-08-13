<?php

namespace App\Enums;

/**
 * Bagaimana sebuah kwitansi akhirnya tercocokkan ke pembayaran.
 *
 * Disimpan bukan untuk statistik, tapi untuk audit: `MANUAL` berarti ada
 * manusia yang memutuskan berkas ini milik pembayaran tertentu, dan
 * keputusannya bisa salah. `TEXT` dan `QR` deterministik — mesin membaca nomor
 * yang sistem sendiri cetak. `OCR` satu-satunya yang menebak.
 *
 * Urutan keandalannya: TEXT > QR > OCR > MANUAL. Waktu admin melihat kwitansi
 * yang tertaut ke pembayaran yang salah, kolom inilah yang menentukan seberapa
 * jauh harus ditelusuri.
 */
enum ReceiptMatchMethod: string
{
    /**
     * Nomor diambil dari lapisan teks PDF — bukan dari gambar sama sekali.
     * Paling pasti: dokumen hasil "Print → Save as PDF" membawa nomor yang
     * dicetak sistem apa adanya, seluruh halaman sekaligus.
     */
    case TEXT = 'teks';

    case QR = 'qr';
    case OCR = 'ocr';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Teks PDF',
            self::QR => 'QR',
            self::OCR => 'OCR',
            self::MANUAL => 'Manual',
        };
    }
}
