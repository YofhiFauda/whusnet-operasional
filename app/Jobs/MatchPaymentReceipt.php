<?php

namespace App\Jobs;

use App\Models\PaymentReceipt;
use App\Services\Receipts\PaymentReceiptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Membaca satu berkas kwitansi di latar belakang.
 *
 * Di queue, bukan di request: OCR bisa lambat dan upload bulk bisa puluhan
 * berkas sekaligus — admin tak boleh menunggu layar diam.
 *
 * `$tries` sengaja kecil. Berkas yang tak terbaca hampir selalu tetap tak
 * terbaca pada percobaan kelima; mengulangnya cuma membebani queue dan (kalau
 * OCR aktif) membakar biaya. Lebih cepat menyerahkannya ke manusia.
 */
class MatchPaymentReceipt implements ShouldQueue
{
    use Queueable;

    /** Sejalan dengan PaymentReceiptService::MAX_ATTEMPTS. */
    public int $tries = PaymentReceiptService::MAX_ATTEMPTS;

    public int $backoff = 30;

    /**
     * Pembacaan bisa panjang: `pdftotext` untuk seluruh dokumen, lalu — kalau
     * lapisan teksnya kosong — raster per halaman. Batas 60 detik bawaan
     * supervisor Horizon memotongnya di tengah, mengulang tiga kali, lalu
     * menyerah; kwitansi yang sebenarnya terbaca berakhir FAILED.
     *
     * Angka ini WAJIB lebih kecil dari `retry_after` koneksi queue
     * (REDIS_QUEUE_RETRY_AFTER), kalau tidak job yang masih berjalan sudah
     * dilepas dan diambil worker lain — satu berkas dibaca dua kali.
     */
    public int $timeout = 240;

    public function __construct(public readonly int $receiptId)
    {
        // Antrean SENDIRI, bukan `default`.
        //
        // Antrean `default` juga memikul seluruh event broadcast (dashboard
        // FOP, task teknisi, status tagihan). Upload bulk 100 kwitansi menaruh
        // 100 job lambat di depan barisan, dan layar realtime berhenti bergerak
        // sampai tumpukan itu habis — tanpa error, tanpa petunjuk.
        //
        // Lewat onQueue(), BUKAN properti `$queue`: trait Queueable sudah
        // mendeklarasikan properti itu dengan tipe berbeda, dan menimpanya
        // membuat kelas ini gagal dikomposisi (fatal error saat dispatch).
        $this->onQueue('kwitansi');
    }

    public function handle(PaymentReceiptService $receipts): void
    {
        $receipt = PaymentReceipt::find($this->receiptId);

        if (! $receipt) {
            return;
        }

        $receipts->match($receipt);
    }
}
