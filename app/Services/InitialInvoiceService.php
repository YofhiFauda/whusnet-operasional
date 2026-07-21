<?php

namespace App\Services;

use App\Models\CustomerService as CustomerServiceModel;
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
     * Bulan aktivasi ditagih prorata: hari aktivasi ikut dihitung
     * (aktivasi tanggal 1 pada bulan 30 hari = 30/30 = harga penuh).
     * Konvensi ini beda 1 hari dari sistem lama yang memakai
     * `jumlah_hari - tanggal_hari_ini` (hari aktivasi tidak ditagih);
     * pelanggan tetap dapat layanan di hari aktivasi, jadi ditagih.
     *
     * PPN disimpan sebagai PERSEN di `invoices.ppn` — `invoices/show.blade.php`
     * merender ulang nominalnya dari persen itu. Menyimpan nominal di kolom
     * tersebut bikin detail tagihan menampilkan "PPN 16500%".
     *
     * @param  array{extra_installation_fee?: mixed, extra_cable_fee?: mixed, extra_pole_fee?: mixed}  $fees
     * @return array{prorate_amount: float, prorate_days: int, days_in_month: int, subtotal: float, discount: float, ppn: float, ppn_amount: float, extra_installation_fee: float, extra_cable_fee: float, extra_pole_fee: float, total_amount: float}
     */
    public function calculate(CustomerServiceModel $service, string $issueDate, array $fees = []): array
    {
        $issue = Carbon::parse($issueDate);
        $daysInMonth = $issue->daysInMonth;
        $prorateDays = $daysInMonth - $issue->day + 1;

        $basePrice = (float) $service->monthly_price;
        $prorateAmount = round(($prorateDays / $daysInMonth) * $basePrice);

        $installationFee = max(0, (float) ($fees['extra_installation_fee'] ?? 0));
        $cableFee = max(0, (float) ($fees['extra_cable_fee'] ?? 0));
        $poleFee = max(0, (float) ($fees['extra_pole_fee'] ?? 0));

        $subtotal = $prorateAmount + $installationFee + $cableFee + $poleFee;
        $discount = max(0, (float) ($service->discount ?? 0));
        $ppnRate = max(0, (float) ($service->ppn ?? 0));

        $afterDiscount = max(0, $subtotal - $discount);
        $ppnAmount = round($afterDiscount * ($ppnRate / 100), 2);

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
            'total_amount' => $afterDiscount + $ppnAmount,
        ];
    }
}
