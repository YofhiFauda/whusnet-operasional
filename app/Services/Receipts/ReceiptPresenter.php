<?php

namespace App\Services\Receipts;

use App\Enums\PaymentStatus;
use App\Models\Payment;

/**
 * Satu sumber isi kwitansi untuk SEMUA bentuk cetakan.
 *
 * Sebelum kelas ini ada, tiga halaman cetak membaca `$payment` sendiri-sendiri:
 * struk thermal (`payments/receipt`), lembar A4 di detail pembayaran
 * (`payments/show`), dan kartu kolektor (`collector-worksheet/receipt-print`).
 * Akibatnya satu pembayaran yang sama tercetak dengan isi berbeda tergantung
 * dari halaman mana tombolnya ditekan — alamat & kolektor cuma ada di A4,
 * paket & periode cuma ada di thermal. Untuk dokumen yang diserahkan ke
 * pelanggan dan dipakai saat audit, perbedaan itu bukan selera tata letak.
 *
 * Aturannya: **bentuk boleh berbeda, isi tidak.** Tiap view merangkai kunci
 * yang sama dengan tata letaknya sendiri (80mm vs A4 vs kartu). Field baru
 * ditambahkan DI SINI supaya ketiganya ikut, bukan di salah satu view.
 *
 * Kelas ini sengaja tidak memformat HTML apa pun — cuma nilai siap tampil.
 *
 * @phpstan-type ReceiptData array{
 *     nomor: string,
 *     status: string,
 *     status_valid: bool,
 *     keterangan_cicilan: string|null,
 *     tanggal_bayar: string,
 *     tanggal_ditagih: string,
 *     metode: string,
 *     pop: string,
 *     pelanggan: array{nama: string, cid: string, hp: string, alamat: string, alamat_baris: list<string>},
 *     invoice: array{ada: bool, nomor: string, periode: string, paket: string, total: string, sisa: string, lunas: bool},
 *     dibayar: string,
 *     lebih_bayar: string|null,
 *     penerima: string,
 *     penagih: string,
 *     catatan: string|null,
 *     dicetak: string
 * }
 */
class ReceiptPresenter
{
    /**
     * @return ReceiptData
     */
    public function for(Payment $payment): array
    {
        $customer = $payment->customer;
        $invoice = $payment->invoice;

        return [
            'nomor' => $payment->payment_number,

            // Status dibawa APA ADANYA beserta penandanya. Lembar A4 dulu
            // mencetaknya hijau tanpa syarat — pembayaran `ditolak` pun tampil
            // hijau di kwitansi yang mengaku "resmi".
            'status' => $payment->payment_status->label(),
            'status_valid' => $payment->payment_status === PaymentStatus::VALID,

            'keterangan_cicilan' => $this->keteranganCicilan($payment),

            'tanggal_bayar' => $payment->payment_date?->format('d/m/Y') ?: '-',
            // Kolektor menagih di tanggal yang bisa berbeda dari tanggal
            // pembayaran dibukukan; kalau tidak ada, jatuh ke tanggal bayar.
            'tanggal_ditagih' => $payment->collected_date?->format('d/m/Y')
                ?: ($payment->payment_date?->format('d/m/Y') ?: '-'),

            'metode' => strtoupper((string) $payment->payment_method),
            'pop' => $payment->pop->name ?? 'Kantor Pusat',

            'pelanggan' => [
                'nama' => $customer->full_name ?? '-',
                'cid' => $customer?->cid ?: ($customer?->customer_code ?: '-'),
                'hp' => $customer?->primary_phone ?: ($customer?->alternative_phone ?: '-'),
                'alamat' => $customer?->address ?: '-',
                'alamat_baris' => $this->pecahAlamat($customer?->address),
            ],

            'invoice' => [
                'ada' => (bool) $invoice,
                'nomor' => $invoice->invoice_number ?? '-',
                'periode' => $invoice?->billing_period ?: '-',
                'paket' => $invoice?->internetPackage->name ?? '-',
                'total' => $this->rupiah($invoice?->total_amount),
                'sisa' => $this->rupiah($invoice?->remaining_amount),
                'lunas' => $invoice ? (float) $invoice->remaining_amount <= 0 : false,
            ],

            'dibayar' => $this->rupiah($payment->amount),
            // null, bukan "Rp 0" — baris lebih bayar memang tidak dicetak kalau
            // tidak ada kelebihan.
            'lebih_bayar' => (float) $payment->overpay_amount > 0
                ? $this->rupiah($payment->overpay_amount)
                : null,

            'penerima' => $payment->receiver->name ?? '-',
            'penagih' => $payment->collector?->name
                ?: 'Kasir POP '.($payment->pop->name ?? '-'),

            // Catatan kosong tetap null. Versi A4 dulu mengarang kalimat
            // "Tagihan Bulanan…" saat catatan kosong, sehingga kwitansi
            // menampilkan keterangan yang tidak pernah ditulis petugas mana pun.
            'catatan' => $payment->note ?: null,

            'dicetak' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * "Melunasi Tagihan" vs "Cicilan Ke-N" — dipakai ketiga bentuk cetakan
     * supaya pembayaran sebagian tidak tercetak seolah pelunasan penuh.
     */
    private function keteranganCicilan(Payment $payment): ?string
    {
        $context = $payment->installmentContext();

        if (! $context) {
            return null;
        }

        return $context['settles'] ? 'Melunasi Tagihan' : 'Cicilan Ke-'.$context['number'];
    }

    /**
     * Alamat panjang dipenggal jadi dua baris di titik yang masuk akal dibaca:
     * "…RT. 002/RW. 002, Joresan" / "Kec. Mlarak, Kabupaten Ponorogo".
     *
     * Ini murni PENYAJIAN — `customers.address` tetap satu kolom teks bebas.
     * Memecahnya jadi kolom desa/kecamatan/kota demi kerapian cetakan berarti
     * migrasi + menebak struktur ~1.900 alamat legacy yang formatnya tidak
     * seragam; salah tebak di dokumen alamat lebih mahal daripada satu baris
     * yang kepanjangan.
     *
     * Karena itu penggalan cuma dilakukan kalau ada penanda administratif yang
     * jelas (`Kec.`/`Kecamatan`). Tanpa penanda, alamat dibiarkan utuh satu
     * baris — membelah di koma sembarang bisa memisahkan "Jl. Veteran" dari
     * nomornya.
     *
     * @return list<string>
     */
    private function pecahAlamat(?string $alamat): array
    {
        $alamat = trim((string) $alamat);

        if ($alamat === '') {
            return ['-'];
        }

        // limit 2: kecamatan pertama yang ditemukan jadi awal baris kedua,
        // sisanya (kabupaten, provinsi) ikut di baris yang sama.
        $bagian = preg_split('/,\s*(?=Kec(?:\.|amatan)\s)/iu', $alamat, 2);

        return array_values(array_filter(array_map('trim', $bagian ?: [$alamat]), fn ($baris) => $baris !== ''));
    }

    private function rupiah(mixed $nilai): string
    {
        return 'Rp '.number_format((float) $nilai, 0, ',', '.');
    }
}
