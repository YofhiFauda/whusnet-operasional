<?php

namespace App\Services\Receipts;

use App\Enums\ReceiptMatchMethod;
use App\Enums\ReceiptStatus;
use App\Jobs\MatchPaymentReceipt;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Siklus hidup berkas kwitansi: upload → dibaca (QR/OCR) → cocok / butuh
 * manusia → dicocokkan manual.
 *
 * Semuanya di sumbu DOKUMEN. Tak satu pun operasi di sini boleh menyentuh
 * status setoran atau nilai pembayaran (§13.2).
 *
 * docs/kolektor/business-logic.md.
 */
class PaymentReceiptService
{
    /**
     * Batas percobaan baca otomatis. Harus SAMA dengan `$tries` pada
     * MatchPaymentReceipt — dua angka yang menggambarkan satu aturan.
     */
    public const MAX_ATTEMPTS = 3;

    public function __construct(private readonly ReceiptNumberExtractor $extractor) {}

    /**
     * Simpan satu berkas dan antrekan pembacaannya.
     *
     * Mengembalikan baris yang SUDAH ADA kalau isi berkasnya identik
     * (checksum sama) — memilih folder scan dua kali tidak boleh melahirkan
     * dua pekerjaan yang menunggu dicocokkan untuk dokumen yang sama.
     */
    public function store(UploadedFile $file, User $uploader): PaymentReceipt
    {
        $checksum = hash_file('sha256', $file->getRealPath());

        $existing = PaymentReceipt::where('checksum', $checksum)->first();
        if ($existing) {
            return $existing;
        }

        // Disk `local` (privat), BUKAN `public`. Kwitansi memuat nama & nominal
        // pelanggan; aturan repo untuk lampiran berisi data pelanggan adalah
        // akses hanya lewat controller yang mengecek permission + POP scope.
        $path = $file->store('receipts/'.now()->format('Y/m'), 'local');

        $receipt = PaymentReceipt::create([
            'uploaded_by' => $uploader->id,
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => $checksum,
            'status' => ReceiptStatus::PENDING->value,
        ]);

        // Pembacaan di queue, bukan di request: OCR bisa lambat dan upload
        // bulk bisa puluhan berkas sekaligus. Admin tak boleh menunggu.
        //
        // Dibungkus try/catch karena pada koneksi queue `sync` job berjalan
        // SEKETIKA di dalam request ini, dan kegagalan teknis pembacaan
        // (decoder meledak untuk berkas rusak) akan merambat keluar sebagai
        // 500 pada endpoint upload. Unggahan sendiri sudah berhasil — berkas
        // tersimpan, barisnya ada, statusnya tercatat — jadi menggagalkan
        // seluruh request cuma karena satu berkas tak terbaca adalah salah
        // sasaran. Pada queue async (Horizon), blok ini tak pernah kena.
        try {
            MatchPaymentReceipt::dispatch($receipt->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return $receipt;
    }

    /**
     * Dipanggil dari queue. Menentukan status akhir berkas.
     */
    public function match(PaymentReceipt $receipt): PaymentReceipt
    {
        if ($receipt->status === ReceiptStatus::MATCHED) {
            return $receipt;
        }

        $absolutePath = Storage::disk('local')->path($receipt->path);

        $receipt->update([
            'status' => ReceiptStatus::PROCESSING->value,
            'attempts' => $receipt->attempts + 1,
        ]);

        try {
            // extractAll(), bukan extract(): satu berkas bisa memuat banyak
            // kwitansi (lembar cetak = grid 8 slip untuk digunting). Berkas
            // satuan cuma daftar berisi satu, jadi tak ada jalur terpisah untuk
            // "satuan" dan "borongan" — satu alur menangani dua-duanya.
            $found = $this->extractor->extractAll($absolutePath);
        } catch (\Throwable $e) {
            report($e);

            // Kegagalan TEKNIS (API mati, decoder meledak) berbeda dari "tidak
            // terbaca": yang ini bisa berhasil kalau diulang. Selama jatah
            // percobaan masih ada, exception-nya DILEMPAR LAGI supaya queue
            // benar-benar mengulang — kalau ditelan di sini, `$tries` pada
            // job cuma konfigurasi mati yang menjanjikan perilaku yang tak
            // pernah terjadi.
            if ($receipt->attempts < self::MAX_ATTEMPTS) {
                $receipt->update(['last_error' => $e->getMessage()]);

                throw $e;
            }

            return tap($receipt)->update([
                'status' => ReceiptStatus::FAILED->value,
                'last_error' => $e->getMessage(),
            ]);
        }

        if ($found === null) {
            return tap($receipt)->update([
                'status' => ReceiptStatus::FAILED->value,
                'last_error' => 'Nomor pembayaran tidak terbaca dari berkas.',
            ]);
        }

        return $this->attachNumbers($receipt, $found['numbers'], $found['method']);
    }

    /**
     * Kaitkan berkas ini ke SEMUA nomor yang terbaca di dalamnya.
     *
     * Satu berkas bisa memuat banyak kwitansi (lembar cetak = grid 8 slip untuk
     * digunting), jadi bentuk hasilnya: satu baris `payment_receipts` per
     * nomor. Baris asli dipakai untuk nomor pertama, sisanya disalin dari situ.
     * Model "satu baris = satu pembayaran" tetap utuh, sehingga seluruh UI,
     * gerbang POP scope, pencocokan manual, dan `detach` bekerja tanpa
     * perubahan.
     *
     * Seluruh baris menunjuk berkas yang SAMA — lembar yang diunggah. Berkas
     * itu arsip lembar FISIK yang dicetak dan diserahkan, bukan "dokumen
     * kwitansi" pelanggan.
     *
     * Kwitansi satuan tidak disimpan sebagai berkas: ia dirender ulang dari
     * data lewat halaman cetak, satu `payment_id`. Sempat dicoba memotong
     * lembar jadi PNG per kwitansi — dan itu keliru dua kali. Pertama, ia
     * menebak-nebak geometri hasil cetakan sistem sendiri (empat pendekatan,
     * tiap kali muncul kasus tepi baru). Kedua, gambar beku menyimpan klaim
     * yang bisa jadi bohong: pembayaran yang kelak DITOLAK tetap terpampang
     * "Lunas" selamanya, sementara halaman yang dirender dari data menampilkan
     * status terkininya.
     *
     * Nomor yang payment-nya tidak ada tetap dibuatkan barisnya sendiri dengan
     * status MISMATCH: gerbang kedua tetap berlaku (pola boleh lolos, tapi
     * tanpa payment-nya berkas TIDAK dicocokkan asal), pekerjaan yang
     * tertinggal harus kelihatan, dan satu nomor bermasalah tak boleh
     * menggagalkan tujuh lainnya.
     *
     * @param  array<int, string>  $numbers
     */
    private function attachNumbers(PaymentReceipt $receipt, array $numbers, ReceiptMatchMethod $method): PaymentReceipt
    {
        $payments = Payment::whereIn('payment_number', $numbers)->get()->keyBy('payment_number');

        // Baris yang sudah ada untuk lembar ini. Dibaca ulang dari database —
        // `$receipt` yang dioper bisa membawa keadaan basi (mis. hasil
        // pencocokan manual sebelumnya), dan menilai "sudah terpakai atau
        // belum" dari objek basi itulah yang dulu membuat satu baris dipakai
        // dua kali sehingga satu pembayaran hilang dari lembar.
        $existing = PaymentReceipt::where('checksum', $receipt->checksum)->get();

        // `$dipakai` mengunci satu baris untuk satu nomor dalam satu jalannya
        // proses. Pencocokan lama (`detected_number` peninggalan pencocokan
        // manual) TIDAK boleh jadi acuan: nomor itu belum tentu ada di lembar,
        // dan kalau dipercaya begitu saja, baris yang sama direbut dua nomor.
        $dipakai = [];
        $result = null;

        // SATU transaksi untuk seluruh lembar, bukan satu per baris.
        //
        // Tiap kwitansi butuh tiga tulisan (insert baris, update saat cocok,
        // insert audit). Dalam autocommit, 200 kwitansi = ±600 transaksi
        // terpisah, masing-masing dengan biaya commit sendiri — terukur 16
        // detik pada lembar 200 kwitansi nyata, padahal membaca PDF-nya cuma
        // 0,64 detik. Seluruh sisanya biaya commit.
        //
        // Batas transaksinya sengaja SETELAH pembacaan PDF: `pdftotext` dan
        // raster tak boleh dijalankan sambil memegang transaksi terbuka.
        //
        // MISMATCH BUKAN kegagalan — itu hasil sah ("nomor terbaca, payment-nya
        // tidak ada") dan wajib ikut commit. Yang boleh membatalkan seluruh
        // lembar cuma kegagalan penyimpanan sungguhan. Kalau suatu saat ada
        // yang mengubah MISMATCH jadi exception, satu nomor asing akan
        // membatalkan 199 kwitansi lain yang sudah benar.
        DB::transaction(function () use ($numbers, $payments, $existing, $receipt, $method, &$dipakai, &$result) {
            foreach ($numbers as $number) {
                $payment = $payments->get($number);

                $row = $this->rowForNumber($existing, $dipakai, $number, $payment?->id);

                if (! $row) {
                    // `checksum` ikut disalin — unggah ulang lembar yang sama
                    // tetap terdeteksi duplikat di store(), dan indeks unique
                    // (checksum, payment_id) menahan satu pembayaran tercatat
                    // dua kali dari lembar yang sama.
                    $row = $this->duplicateRowFor($receipt);
                    $existing->push($row);
                }

                $dipakai[] = $row->id;

                if (! $payment) {
                    $row->update([
                        'status' => ReceiptStatus::MISMATCH->value,
                        'detected_number' => $number,
                        'last_error' => "Nomor {$number} terbaca tapi tidak ada pembayaran dengan nomor itu.",
                    ]);
                } else {
                    $this->attach($row, $payment, $method, $number);
                }

                $result ??= $row;
            }
        });

        // Selalu ada minimal satu nomor di sini — extractAll() mengembalikan
        // null, bukan daftar kosong, waktu tak ada yang terbaca.
        return $result ?? $receipt;
    }

    /**
     * Baris mana yang dipakai untuk satu nomor di lembar ini.
     *
     * Urutan pencarian sengaja dari yang paling pasti ke paling longgar:
     *   1. baris yang SUDAH tercocokkan ke pembayaran itu — pembacaan ulang
     *      harus mendarat di baris yang sama, bukan bikin baris kedua;
     *   2. baris menganggur mana pun milik lembar ini (belum punya pembayaran
     *      dan belum dipakai nomor lain di jalannya proses ini).
     * Kalau dua-duanya nihil, pemanggil membuat baris baru.
     *
     * @param  Collection<int, PaymentReceipt>  $existing
     * @param  array<int, int>  $dipakai
     */
    private function rowForNumber(Collection $existing, array $dipakai, string $number, ?int $paymentId): ?PaymentReceipt
    {
        if ($paymentId !== null) {
            $cocok = $existing->first(fn (PaymentReceipt $row) => (int) $row->payment_id === $paymentId);

            if ($cocok) {
                return $cocok;
            }
        }

        return $existing->first(
            fn (PaymentReceipt $row) => $row->payment_id === null && ! in_array($row->id, $dipakai, true)
        );
    }

    /**
     * Baris kembar untuk lembar yang sama — dipakai saat satu berkas memuat
     * banyak kwitansi. Dicari dulu supaya pembacaan ulang (retry queue) tidak
     * melahirkan baris kedua untuk nomor yang sudah punya barisnya.
     */
    private function duplicateRowFor(PaymentReceipt $receipt): PaymentReceipt
    {
        return PaymentReceipt::create([
            'uploaded_by' => $receipt->uploaded_by,
            'original_filename' => $receipt->original_filename,
            'path' => $receipt->path,
            'mime_type' => $receipt->mime_type,
            'size_bytes' => $receipt->size_bytes,
            'checksum' => $receipt->checksum,
            'status' => ReceiptStatus::PROCESSING->value,
            'attempts' => $receipt->attempts,
        ]);
    }

    /**
     * Admin mencocokkan sendiri berkas yang gagal dibaca.
     *
     * Jalur ini WAJIB ada: status dokumen tak boleh disandera keberhasilan
     * mesin. QR sobek dan OCR mati adalah kejadian normal, dan kwitansinya
     * tetap harus bisa sampai ke pelanggan yang benar.
     */
    public function matchManually(PaymentReceipt $receipt, Payment $payment, User $actor): PaymentReceipt
    {
        if ($receipt->status === ReceiptStatus::MATCHED) {
            throw new RuntimeException('Kwitansi ini sudah tercocokkan. Lepaskan dulu kalau memang keliru.');
        }

        return $this->attach($receipt, $payment, ReceiptMatchMethod::MANUAL, $receipt->detected_number, $actor);
    }

    /**
     * Lepas kaitan supaya berkas bisa dicocokkan ulang. Dicatat di audit log
     * karena ini membatalkan keputusan sebelumnya atas dokumen pelanggan.
     */
    public function detach(PaymentReceipt $receipt, User $actor): PaymentReceipt
    {
        $previous = $receipt->payment_id;

        $receipt->update([
            'payment_id' => null,
            // `pop_id` SENGAJA DIPERTAHANKAN. Melepas kaitan tidak membuat
            // dokumen ini kembali "tak diketahui miliknya" — POP-nya sudah
            // pernah diketahui. Menolnya justru melebarkan akses: gerbang
            // download/cocokkan melewatkan berkas ber-`pop_id` null, jadi
            // admin cabang lain mendadak bisa membuka dokumen pelanggan yang
            // bukan wilayahnya.
            'status' => ReceiptStatus::MISMATCH->value,
            'match_method' => null,
            'matched_by' => null,
            'matched_at' => null,
        ]);

        $this->audit($receipt, $actor, 'kwitansi_dilepas', ['payment_id_sebelumnya' => $previous]);

        return $receipt;
    }

    private function attach(
        PaymentReceipt $receipt,
        Payment $payment,
        ReceiptMatchMethod $method,
        ?string $detectedNumber,
        ?User $actor = null,
    ): PaymentReceipt {
        $receipt->update([
            'payment_id' => $payment->id,
            // POP disalin dari payment — inilah yang membuat berkas ikut POP
            // scope begitu ia punya pemilik.
            'pop_id' => $payment->pop_id,
            'status' => ReceiptStatus::MATCHED->value,
            'match_method' => $method->value,
            'detected_number' => $detectedNumber ?? $payment->payment_number,
            'last_error' => null,
            'matched_by' => $actor?->id,
            'matched_at' => now(),
        ]);

        $this->audit($receipt, $actor, 'kwitansi_dicocokkan', [
            'payment_number' => $payment->payment_number,
            'metode' => $method->value,
        ]);

        return $receipt;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function audit(PaymentReceipt $receipt, ?User $actor, string $action, array $values): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            'module' => 'kolektor',
            'action' => $action,
            'auditable_type' => PaymentReceipt::class,
            'auditable_id' => $receipt->id,
            'new_values' => $values,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
