<?php

namespace App\Services\Receipts;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Render halaman pertama sebuah PDF jadi PNG sementara, supaya pembaca QR —
 * yang cuma mengerti gambar — tetap bisa bekerja pada kwitansi ber-PDF.
 *
 * Halaman cetak kwitansi mengembalikan View HTML, jadi alur alami admin adalah
 * Print → "Save as PDF" di browser. Tanpa langkah ini, format yang justru
 * dipancing oleh halaman cetak sendiri tak pernah bisa dibaca otomatis: QR
 * reader melewatkan non-gambar, dan OCR mati secara default.
 *
 * Memakai `pdftoppm` (poppler-utils), SENGAJA bukan Imagick: Debian mematikan
 * coder PDF di policy.xml ImageMagick secara default, sehingga jalur itu gagal
 * dengan "not authorized" yang menyesatkan.
 *
 * Merender per HALAMAN, dibatasi MAX_PAGES. Versi pertama cuma merender
 * halaman 1 dengan alasan "satu berkas = satu pembayaran" — dan itu membuat
 * PDF 8 halaman dinyatakan tidak terbaca padahal QR-nya utuh di halaman 3,
 * tanpa satu pun pesan yang menjelaskan kenapa.
 */
class PdfPageRasterizer
{
    use DetectsPdfFiles;

    /** Cukup untuk QR kwitansi (dicetak 220px), tanpa membuat berkas raksasa. */
    public const DPI = 200;

    /**
     * Percobaan kedua untuk berkas yang gagal di DPI biasa — scan kertas 96 DPI,
     * hasil fotokopi, atau QR yang tercetak kecil. Vektor tidak butuh ini;
     * yang tertolong adalah dokumen yang sumbernya sendiri sudah kasar.
     */
    public const DPI_TINGGI = 400;

    /**
     * Batas halaman yang dirender. Bukan batas bisnis, tapi rem biaya: PDF
     * ratusan halaman yang salah unggah tak boleh menyandera worker selama
     * belasan menit. Kwitansi nyata jauh di bawah angka ini.
     *
     * MAX_PAGES × TIMEOUT_SECONDS harus muat di dalam
     * MatchPaymentReceipt::$timeout, ditambah jatah untuk `pdftotext` yang
     * dicoba lebih dulu. 10 × 15 + 20 = 170 detik, di bawah 240.
     */
    public const MAX_PAGES = 10;

    private const TIMEOUT_SECONDS = 15;

    public function isAvailable(): bool
    {
        return $this->binaryPath() !== null;
    }

    /**
     * Jumlah halaman menurut `pdfinfo`, atau 1 kalau tak bisa ditentukan —
     * menganggap 1 halaman lebih aman daripada 0 (yang bikin loop pemanggil
     * tidak pernah jalan sama sekali).
     */
    public function pageCount(string $absolutePath): int
    {
        $binary = $this->binaryPath('pdfinfo');

        if ($binary === null || ! $this->isPdf($absolutePath)) {
            return 1;
        }

        $process = new Process([$binary, $absolutePath]);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $process->mustRun();
        } catch (ProcessFailedException) {
            return 1;
        }

        if (preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $matches) !== 1) {
            return 1;
        }

        return max(1, (int) $matches[1]);
    }

    /**
     * Path PNG sementara satu halaman, atau null kalau gagal.
     *
     * Pemanggil WAJIB menghapus berkasnya setelah selesai — lihat
     * QrReceiptNumberReader yang membungkusnya dengan try/finally.
     */
    public function pageToPng(string $absolutePath, int $page = 1, int $dpi = self::DPI): ?string
    {
        $binary = $this->binaryPath();

        if ($binary === null || ! $this->isPdf($absolutePath)) {
            return null;
        }

        // pdftoppm menambahkan sendiri akhiran halaman + ekstensi ke prefix
        // yang diberikan, jadi yang dikirim adalah prefix dan hasilnya ditebak
        // balik lewat glob — bukan nama berkas jadi.
        $prefix = tempnam(sys_get_temp_dir(), 'kwitansi-');
        if ($prefix === false) {
            return null;
        }

        // tempnam() sudah membuat berkas kosong; pdftoppm butuh prefix, jadi
        // berkas itu dibuang lebih dulu supaya tidak tertinggal sebagai sampah
        // 0 byte yang tak pernah dihapus siapa pun.
        @unlink($prefix);

        $process = new Process([
            $binary,
            '-png',
            '-r', (string) $dpi,
            '-f', (string) $page,
            '-l', (string) $page,
            '-singlefile',
            $absolutePath,
            $prefix,
        ]);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $process->mustRun();
        } catch (ProcessFailedException) {
            $this->cleanupPrefix($prefix);

            return null;
        }

        $rendered = $prefix.'.png';

        if (! is_file($rendered)) {
            $this->cleanupPrefix($prefix);

            return null;
        }

        return $rendered;
    }

    private function cleanupPrefix(string $prefix): void
    {
        foreach (glob($prefix.'*') ?: [] as $leftover) {
            @unlink($leftover);
        }
    }

    private function binaryPath(string $binary = 'pdftoppm'): ?string
    {
        foreach (['/usr/bin/', '/usr/local/bin/'] as $dir) {
            if (is_executable($dir.$binary)) {
                return $dir.$binary;
            }
        }

        return null;
    }
}
