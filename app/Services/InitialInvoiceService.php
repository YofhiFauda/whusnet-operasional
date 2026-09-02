<?php

namespace App\Services;

use App\Models\CustomerService as CustomerServiceModel;
use App\Support\Money;
use Carbon\Carbon;

/**
 * Perhitungan nominal Tagihan Awal (PSB).
 *
 * Sengaja dipisah dari controller: form verifikasi mengirim field prorate,
 * subtotal, ppn, dan total sebagai input `readonly` yang dihitung JavaScript.
 * Readonly cuma penghalang UI — siapa pun yang bisa POST bisa mengirim nominal
 * apa saja. Server WAJIB menghitung ulang dan mengabaikan angka kiriman;
 * kiriman klien statusnya cuma preview.
 *
 * Sistem lama (lihat ALUR-PEMBAYARAN.md) juga menghitung prorata di server
 * (`GetTagihanAwal()`), tapi TOTALBIAYA-nya dijumlah di klien — celah yang
 * sama. Jangan diwariskan.
 */
class InitialInvoiceService
{
    /**
     * Hitung rincian tagihan awal dari data layanan + tanggal terbit.
     *
     * Bulan aktivasi ditagih prorata dengan konvensi legacy: hari aktivasi
     * TIDAK ditagih (`jumlah_hari_bulan - tanggal_aktivasi`), sama seperti
     * helper `GetTagihanAwal()` di sistem lama. Hari pemasangan digratiskan.
     * Pembulatannya juga mengikuti legacy (`round`, bukan `floor`) supaya
     * nominal tagihan awal hasil migrasi bisa dicocokkan angka per angka.
     *
     * Kasus aktivasi di hari terakhir bulan menghasilkan 0 hari sisa. Keputusan
     * bisnis 2026-07-21: yang ditagih bukan nol dan bukan 1 hari (cabang legacy
     * "besok sudah tanggal 1"), tapi SEBULAN PENUH. Konsekuensinya ada tebing di
     * ujung bulan — aktif 30 Juli bayar 1 hari, aktif 31 Juli bayar penuh. Itu
     * disengaja; jangan "dirapikan" jadi 1 hari tanpa keputusan bisnis baru.
     *
     * PPN disimpan sebagai PERSEN di `invoices.ppn` — `invoices/show.blade.php`
     * merender ulang nominalnya dari persen itu. Menyimpan nominal di kolom
     * tersebut bikin detail tagihan menampilkan "PPN 16500%".
     *
     * Materai masuk lewat `other_fee`. Ditagih sekali di tagihan awal dan
     * TIDAK pernah ikut tagihan bulanan — `GenerateMonthlyInvoicesCommand`
     * menghitung nominalnya murni dari `monthly_price`, jadi kolom ini tidak
     * boleh disalin ke `customer_services.other_fee` (kolom bernama sama di
     * tabel itu artinya beda: biaya di luar standar yang melekat ke layanan).
     *
     * `prorate_amount_override` adalah SATU-SATUNYA nominal turunan yang boleh
     * ditimpa admin — dipakai untuk kasus nego harga / koreksi pembulatan yang
     * tidak tertangkap rumus prorata standar. Semua nominal lain (subtotal,
     * ppn, total) tetap dihitung ulang penuh di server dan mengabaikan kiriman
     * klien, sesuai keputusan lama di kelas ini. Kalau override kosong/null,
     * fallback ke hasil hitung otomatis.
     *
     * @param  array{extra_installation_fee?: mixed, extra_cable_fee?: mixed, extra_pole_fee?: mixed, other_fee?: mixed, prorate_amount_override?: mixed}  $fees
     * @return array{prorate_amount: float, prorate_days: int, days_in_month: int, subtotal: float, discount: float, ppn: float, ppn_amount: float, extra_installation_fee: float, extra_cable_fee: float, extra_pole_fee: float, other_fee: float, total_amount: float, next_month_amount: float}
     */
    public function calculate(CustomerServiceModel $service, string $issueDate, array $fees = []): array
    {
        $issue = Carbon::parse($issueDate);
        $daysInMonth = $issue->daysInMonth;

        // Hari aktivasi tidak ditagih (konvensi legacy). Aktivasi di hari
        // terakhir bulan menyisakan 0 hari — dibulatkan ke sebulan penuh,
        // bukan digratiskan. Lihat docblock di atas.
        $prorateDays = $daysInMonth - $issue->day;
        if ($prorateDays <= 0) {
            $prorateDays = $daysInMonth;
        }

        $basePrice = (float) $service->monthly_price;
        $prorateAmount = round(($prorateDays / $daysInMonth) * $basePrice);

        $prorateOverride = $fees['prorate_amount_override'] ?? null;
        if ($prorateOverride !== null && $prorateOverride !== '') {
            $prorateAmount = Money::atLeastZero($prorateOverride);
        }

        $installationFee = Money::atLeastZero($fees['extra_installation_fee'] ?? 0);
        $cableFee = Money::atLeastZero($fees['extra_cable_fee'] ?? 0);
        $poleFee = Money::atLeastZero($fees['extra_pole_fee'] ?? 0);
        $otherFee = Money::atLeastZero($fees['other_fee'] ?? 0);

        // Lima komponen dijumlahkan sekaligus di ranah sen. Rantai penjumlahan
        // float menumpuk galat, dan angka inilah yang dicetak di kwitansi
        // pertama pelanggan — nominal yang tak sama dengan jumlah rinciannya
        // adalah dokumen yang tidak bisa dipertanggungjawabkan.
        $subtotal = Money::sum([$prorateAmount, $installationFee, $cableFee, $poleFee, $otherFee]);
        $discount = Money::atLeastZero($service->discount ?? 0);
        // PPN itu PERSEN, bukan rupiah — tidak lewat Money.
        $ppnRate = max(0, (float) ($service->ppn ?? 0));

        $afterDiscount = Money::atLeastZero(Money::sub($subtotal, $discount));
        $ppnAmount = Money::of($afterDiscount * ($ppnRate / 100));

        // Nominal bulan berikutnya, dipakai kwitansi untuk menjawab pertanyaan
        // pelanggan yang paling sering ("bulan depan bayar berapa?") tanpa admin
        // perlu menghitung. Rumusnya sengaja dijaga identik dengan
        // GenerateMonthlyInvoicesCommand: harga paket - diskon, lalu PPN persen.
        // Tanpa prorata dan tanpa materai — keduanya hanya ada di tagihan awal.
        $nextMonthAfterDiscount = Money::atLeastZero(Money::sub($basePrice, $discount));
        $nextMonthAmount = Money::add($nextMonthAfterDiscount, $nextMonthAfterDiscount * ($ppnRate / 100));

        return [
            'prorate_amount' => $prorateAmount,
            'prorate_days' => $prorateDays,
            'days_in_month' => $daysInMonth,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'ppn' => $ppnRate,
            'ppn_amount' => $ppnAmount,
            'extra_installation_fee' => $installationFee,
            'extra_cable_fee' => $cableFee,
            'extra_pole_fee' => $poleFee,
            'other_fee' => $otherFee,
            'total_amount' => Money::add($afterDiscount, $ppnAmount),
            'next_month_amount' => $nextMonthAmount,
        ];
    }
}
