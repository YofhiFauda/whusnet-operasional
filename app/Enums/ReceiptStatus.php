<?php

namespace App\Enums;

/**
 * Status satu berkas kwitansi di sumbu DOKUMEN.
 *
 * Sengaja tidak punya kaitan apa pun dengan status setoran: uang sudah selesai
 * dihitung jauh sebelum berkasnya diupload (§13.2). Status di sini hanya
 * menjawab "dokumen ini sudah nempel ke pembayaran yang benar atau belum".
 */
enum ReceiptStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case MATCHED = 'matched';
    case MISMATCH = 'mismatch';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Diproses',
            self::PROCESSING => 'Sedang Dibaca',
            self::MATCHED => 'Cocok',
            self::MISMATCH => 'Nomor Tidak Dikenali',
            self::FAILED => 'Gagal Dibaca',
        };
    }

    /**
     * Butuh tangan manusia. `MISMATCH` = nomornya terbaca tapi tak menunjuk
     * pembayaran yang sah; `FAILED` = tak terbaca sama sekali. Dibedakan
     * karena tindak lanjutnya beda: yang pertama biasanya salah cetak/salah
     * berkas, yang kedua biasanya kualitas gambar.
     */
    public function needsAttention(): bool
    {
        return in_array($this, [self::MISMATCH, self::FAILED], true);
    }
}
