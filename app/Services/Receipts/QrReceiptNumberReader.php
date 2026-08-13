<?php

namespace App\Services\Receipts;

use App\Enums\ReceiptMatchMethod;
use Zxing\QrReader;

/**
 * Jalur UTAMA: baca QR yang sistem sendiri cetak di kwitansi.
 *
 * Deterministik dan gratis — isinya `payment_number`, bukan tebakan. Inilah
 * alasan pencocokan kwitansi tidak perlu AI: dokumen ini dicetak oleh sistem,
 * jadi menyuruh model bahasa "membaca" kembali data yang kita tulis sendiri
 * adalah biaya tanpa informasi baru (§13.1).
 *
 * OCR tetap ada, tapi untuk kwitansi yang QR-nya sobek/buram/hasil fotokopi.
 */
class QrReceiptNumberReader implements ReceiptNumberReader
{
    /**
     * Batas halaman untuk percobaan ulang di resolusi tinggi. 10 halaman × 15
     * dtk × 2 putaran menembus batas waktu job; 3 halaman menahannya di
     * 10×15 + 3×15 = 195 dtk, masih di bawah MatchPaymentReceipt::$timeout.
     */
    private const MAX_PAGES_DPI_TINGGI = 3;

    public function __construct(private readonly PdfPageRasterizer $rasterizer) {}

    public function read(string $absolutePath): ?string
    {
        if ($this->isImage($absolutePath)) {
            return $this->decode($absolutePath);
        }

        // PDF dirender dulu jadi PNG. Dulu di sini cuma `return null` dengan
        // alasan "jalur berikutnya yang menangani" — padahal jalur berikutnya
        // (OCR) mati secara default, jadi SETIAP kwitansi PDF pasti berakhir
        // FAILED dan menunggu kerja manual. Halaman cetak kwitansi sendiri
        // mengembalikan HTML, sehingga Print → "Save as PDF" adalah alur yang
        // paling wajar ditempuh admin: format yang dipancing sistem sendiri
        // tak boleh jadi format yang sistem tak sanggup baca.
        if (! $this->rasterizer->isPdf($absolutePath)) {
            return null;
        }

        $pages = min($this->rasterizer->pageCount($absolutePath), PdfPageRasterizer::MAX_PAGES);

        $text = $this->scanPages($absolutePath, $pages, PdfPageRasterizer::DPI);

        if ($text !== null) {
            return $text;
        }

        // Percobaan kedua di resolusi tinggi. Yang tertolong bukan PDF vektor
        // (itu sudah tajam di DPI biasa) melainkan hasil scan kertas 96 DPI dan
        // fotokopi, yang QR-nya baru terbaca kalau dirender lebih besar.
        //
        // Dibatasi dokumen tipis: satu putaran penuh di 400 DPI atas 10 halaman
        // akan menembus batas waktu job (MatchPaymentReceipt::$timeout). Berkas
        // scan kwitansi nyata satu-dua halaman, jadi batas ini praktis tak
        // pernah kena pada kasus yang memang ingin ditolong.
        if ($pages > self::MAX_PAGES_DPI_TINGGI) {
            return null;
        }

        return $this->scanPages($absolutePath, $pages, PdfPageRasterizer::DPI_TINGGI);
    }

    private function scanPages(string $absolutePath, int $pages, int $dpi): ?string
    {
        for ($page = 1; $page <= $pages; $page++) {
            $rendered = $this->rasterizer->pageToPng($absolutePath, $page, $dpi);

            if ($rendered === null) {
                continue;
            }

            try {
                $text = $this->decode($rendered);
            } finally {
                // Wajib di `finally`: decoder bisa melempar (lihat catatan di
                // ReceiptNumberExtractor), dan berkas sementara yang tertinggal
                // menumpuk diam-diam di /tmp setiap upload bulk.
                @unlink($rendered);
            }

            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    private function decode(string $imagePath): ?string
    {
        $text = (new QrReader($imagePath))->text();

        return is_string($text) && $text !== '' ? trim($text) : null;
    }

    public function method(): ReceiptMatchMethod
    {
        return ReceiptMatchMethod::QR;
    }

    /**
     * `Zxing\QrReader` memakai Imagick kalau ada, dan baru jatuh ke GD kalau
     * tidak. Memeriksa GD saja membuat server ber-imagick-tanpa-gd melewatkan
     * jalur QR sepenuhnya — setiap kwitansi jatuh ke OCR (berbayar) atau kerja
     * manual, tanpa satu pun pesan error yang menjelaskan kenapa.
     */
    public function isAvailable(): bool
    {
        return extension_loaded('gd') || extension_loaded('imagick');
    }

    private function isImage(string $absolutePath): bool
    {
        if (! is_readable($absolutePath)) {
            return false;
        }

        return @getimagesize($absolutePath) !== false;
    }
}
