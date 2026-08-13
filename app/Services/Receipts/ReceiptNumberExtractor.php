<?php

namespace App\Services\Receipts;

use App\Enums\ReceiptMatchMethod;

/**
 * SATU-SATUNYA tempat urutan baca nomor kwitansi ditentukan.
 *
 * Urutannya: lapisan teks PDF → QR → OCR. Dari yang paling pasti ke yang
 * paling menebak, bukan sekadar preferensi teknis.
 *
 *   1. **Lapisan teks** — dokumen hasil "Print → Save as PDF" membawa nomor
 *      yang dicetak sistem apa adanya. Tak ada render, tak ada DPI, tak ada
 *      blur, dan seluruh halaman terbaca sekaligus. Pada lembar 8 kwitansi,
 *      jalur ini memberi 8 nomor sementara pemindaian QR hasil raster cuma
 *      menemukan 7.
 *   2. **QR** — untuk berkas yang isinya cuma piksel (foto/scan kertas), yang
 *      lapisan teksnya memang tidak ada. Deterministik, gratis, dan punya
 *      checksum: rusak = gagal baca, BUKAN salah baca.
 *   3. **OCR** — hanya kalau QR-nya sobek/buram. Berbayar dan probabilistik,
 *      karena itu paling belakang. QR TIDAK pernah diserahkan ke OCR: model
 *      bahasa buruk membaca matriks QR dan akan mengarang nomor berformat
 *      benar — kegagalan paling berbahaya karena lolos gerbang pola.
 *
 * Sebelum ini urutannya tersebar: lapisan teks diputuskan di
 * PaymentReceiptService, QR→OCR di sini. Dua tempat memutuskan satu aturan yang
 * sama — persis pola yang gampang menyimpang diam-diam.
 *
 * Reader yang `isAvailable()` false dilewati diam-diam (OCR tanpa API key
 * adalah keadaan normal). Kalau semua jalur habis tanpa hasil, itu bukan
 * kegagalan sistem — berkasnya tinggal menunggu manusia.
 */
class ReceiptNumberExtractor
{
    /** @var array<int, ReceiptNumberReader> */
    private array $readers;

    public function __construct(
        QrReceiptNumberReader $qr,
        GeminiOcrReceiptNumberReader $ocr,
        private readonly PdfTextNumberReader $pdfText,
    ) {
        $this->readers = [$qr, $ocr];
    }

    /**
     * SEMUA nomor pembayaran di dalam berkas, urut kemunculan.
     *
     * Satu berkas bisa memuat banyak kwitansi: halaman cetak menghasilkan grid
     * 2 kolom bergaris putus-putus, 8 kwitansi per lembar A4 untuk digunting.
     * Karena itu bentuk kembaliannya selalu daftar — berkas satuan cuma daftar
     * berisi satu.
     *
     * @return array{numbers: array<int, string>, method: ReceiptMatchMethod}|null
     *
     * @throws ReceiptReadFailure kalau SELURUH jalur gambar gagal karena masalah
     *                            teknis (bukan sekadar "tidak terbaca")
     */
    public function extractAll(string $absolutePath): ?array
    {
        // Lapisan teks lebih dulu, untuk berkas satuan maupun lembar borongan.
        // Kalau ada, jalur gambar tak perlu disentuh sama sekali.
        $fromText = $this->pdfText->numbers($absolutePath);

        if ($fromText !== []) {
            return ['numbers' => $fromText, 'method' => ReceiptMatchMethod::TEXT];
        }

        $found = $this->extract($absolutePath);

        return $found === null
            ? null
            : ['numbers' => [$found['number']], 'method' => $found['method']];
    }

    /**
     * Satu nomor dari jalur GAMBAR saja (QR → OCR).
     *
     * Dipakai internal oleh extractAll(); pemanggil di luar kelas ini sebaiknya
     * memakai extractAll() supaya lembar borongan tidak diam-diam terpotong
     * jadi satu nomor.
     *
     * @return array{number: string, method: ReceiptMatchMethod}|null
     *
     * @throws ReceiptReadFailure kalau SELURUH jalur gagal karena masalah
     *                            teknis (bukan sekadar "tidak terbaca")
     */
    public function extract(string $absolutePath): ?array
    {
        /** @var array<int, string> $failures */
        $failures = [];

        foreach ($this->readers as $reader) {
            if (! $reader->isAvailable()) {
                continue;
            }

            try {
                $raw = $reader->read($absolutePath);
            } catch (\Throwable $e) {
                // Satu pembaca yang meledak TIDAK BOLEH menghentikan rantai.
                //
                // `Zxing\QrReader` melempar untuk gambar yang GD-nya tak bisa
                // buka (mis. WEBP di build tanpa dukungan WEBP) — dan
                // `getimagesize()` tetap mengenali berkas itu, jadi penjaga
                // isImage() pun lolos. Waktu exception-nya merambat keluar,
                // OCR — yang justru ADA untuk kasus "QR tak terbaca" — tak
                // pernah dicoba sama sekali.
                $failures[] = $reader->method()->value.': '.$e->getMessage();

                continue;
            }

            $number = $this->normalize($raw);

            if ($number !== null) {
                return ['number' => $number, 'method' => $reader->method()];
            }
        }

        // Tak ada hasil DAN ada kegagalan teknis ⇒ layak dicoba ulang oleh
        // queue. Beda dari "semua pembaca jalan normal tapi nomornya memang
        // tak ada" — yang itu mengembalikan null dan langsung jadi urusan
        // manusia.
        if ($failures !== []) {
            throw new ReceiptReadFailure(implode(' | ', $failures));
        }

        return null;
    }

    /**
     * Ambil pola nomor pembayaran dari apa pun yang dikembalikan reader.
     *
     * Perlu karena isi QR bisa saja URL lengkap dan jawaban OCR bisa membawa
     * teks liar. Pola inilah gerbang pertama; gerbang kedua adalah keberadaan
     * payment-nya di database (PaymentReceiptService). Nomor yang lolos pola
     * tapi tak ada payment-nya berakhir `MISMATCH`, bukan tercocokkan asal.
     */
    private function normalize(?string $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        if (preg_match('/PAY-\d{6}-\d+/i', $raw, $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[0]);
    }
}
