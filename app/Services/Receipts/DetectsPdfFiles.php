<?php

namespace App\Services\Receipts;

/**
 * Deteksi PDF dari MAGIC BYTES, bukan dari ekstensi maupun mime yang dikirim
 * klien — berkas yang diunggah pengguna tak boleh dipercaya menyebut dirinya
 * sendiri.
 *
 * Dipakai bersama pembaca lapisan teks dan perender halaman. Sebelumnya cuma
 * ada di rasterizer, sehingga pembaca teks menjalankan `pdftotext` pada APA PUN
 * termasuk JPG/PNG: hasilnya tetap benar (gagal → array kosong → jatuh ke jalur
 * QR), tapi satu proses eksternal di-spawn percuma per berkas. Unggah 200 foto
 * = 200 proses percuma.
 */
trait DetectsPdfFiles
{
    public function isPdf(string $absolutePath): bool
    {
        if (! is_readable($absolutePath)) {
            return false;
        }

        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $magic = fread($handle, 5);
        fclose($handle);

        return $magic === '%PDF-';
    }
}
