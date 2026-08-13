<?php

namespace App\Services\Receipts;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Baca nomor pembayaran dari LAPISAN TEKS sebuah PDF.
 *
 * Jalur tercepat dan paling pasti untuk kwitansi hasil "Print → Save as PDF":
 * kwitansi mencetak nomornya dua kali — sebagai QR *dan* sebagai teks di
 * sampingnya — dan browser membawa teks itu apa adanya ke dalam PDF. Tak ada
 * render, tak ada DPI, tak ada blur, dan seluruh halaman terbaca sekaligus.
 *
 * Terbukti lebih teliti daripada memindai QR hasil raster: pada lembar 8
 * kwitansi, pemindaian ubin menemukan 7 nomor (satu QR terpotong batas ubin),
 * sedangkan lapisan teks memberi kedelapan-delapannya.
 *
 * Yang TIDAK bisa ditangani di sini: PDF hasil scan kertas, yang isinya cuma
 * gambar tanpa lapisan teks. Untuk itu jalur raster + QR tetap dipakai
 * (PdfPageRasterizer).
 */
class PdfTextNumberReader
{
    use DetectsPdfFiles;

    private const TIMEOUT_SECONDS = 20;

    public function isAvailable(): bool
    {
        return $this->binaryPath() !== null;
    }

    /**
     * Semua nomor pembayaran unik di dalam berkas, urut kemunculan.
     *
     * Mengembalikan array kosong (bukan null) kalau tak ada — pemanggil
     * membedakan "tidak ada nomor" dari "banyak nomor" lewat jumlahnya, jadi
     * satu bentuk kembalian sudah cukup.
     *
     * @return array<int, string>
     */
    public function numbers(string $absolutePath): array
    {
        $binary = $this->binaryPath();

        // Gerbang PDF DULU, sebelum menjalankan proses apa pun. Tanpa ini
        // `pdftotext` di-spawn untuk JPG/PNG juga — hasilnya tetap benar
        // (gagal → array kosong → jatuh ke jalur QR), tapi unggah 200 foto
        // berarti 200 proses eksternal percuma.
        if ($binary === null || ! $this->isPdf($absolutePath)) {
            return [];
        }

        // `-layout` dipertahankan supaya urutan baca mengikuti tata letak
        // cetak (grid 2 kolom), bukan urutan objek teks di dalam PDF. Nomor
        // pertama yang ditemukan jadi milik baris kwitansi yang asli, jadi
        // urutannya sebaiknya sama dengan yang dilihat admin di kertas.
        $process = new Process([$binary, '-layout', $absolutePath, '-']);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $process->mustRun();
        } catch (ProcessFailedException) {
            return [];
        }

        // Pola yang SAMA dengan ReceiptNumberExtractor::normalize(). Lapisan
        // teks memuat seluruh isi kwitansi (nama, alamat, nominal), jadi pola
        // inilah yang memisahkan nomor pembayaran dari teks lain.
        if (preg_match_all('/PAY-\d{6}-\d+/i', $process->getOutput(), $matches) === false || $matches[0] === []) {
            return [];
        }

        return array_values(array_unique(array_map('strtoupper', $matches[0])));
    }

    private function binaryPath(): ?string
    {
        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
