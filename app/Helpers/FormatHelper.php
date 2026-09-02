<?php

namespace App\Helpers;

use Carbon\Carbon;

class FormatHelper
{
    /**
     * Samarkan nama pelanggan buat halaman publik tanpa auth (QR §6.1
     * langkah 1 — "MASUDAH Y****** F*****"). Kata pertama tetap utuh (biar
     * pelanggan yakin ini kartunya), kata berikutnya cuma huruf pertama +
     * jumlah bintang TETAP (bukan mengikuti panjang asli) — panjang kata
     * asli sendiri juga informasi yang gak perlu bocor ke siapa pun yang
     * motret stikernya.
     */
    public static function maskName(?string $fullName): string
    {
        $words = array_values(array_filter(explode(' ', trim((string) $fullName))));

        if ($words === []) {
            return '';
        }

        $masked = [strtoupper($words[0])];

        for ($i = 1; $i < count($words); $i++) {
            $masked[] = mb_strtoupper(mb_substr($words[$i], 0, 1)).'*****';
        }

        return implode(' ', $masked);
    }

    /**
     * Format a number to Indonesian Rupiah (Rp).
     *
     * @param  float|int|string|null  $amount
     */
    public static function rupiah($amount, bool $showDecimal = false): string
    {
        if ($amount === null || $amount === '') {
            return 'Rp 0';
        }

        $decimals = $showDecimal ? 2 : 0;

        return 'Rp '.number_format((float) $amount, $decimals, ',', '.');
    }

    /**
     * Nilai awal untuk input bermasking `data-rupiah` — TANPA prefiks "Rp".
     *
     * Sen dipertahankan kalau ada (`150000.5` → `150.000,50`) dan dibuang
     * kalau nol (`150000` → `150.000`). Membulatkan tanpa syarat seperti
     * `number_format($v, 0, ...)` terlihat rapi tapi mengubah nominalnya:
     * sisa tagihan 150.000,50 tampil 150.001 lalu ditolak validasi "melebihi
     * sisa tagihan" — baris yang tidak disentuh siapa pun jadi tak bisa
     * dibayar. Di form setoran akibatnya lebih jauh lagi: uang fisik yang
     * benar terkirim dibulatkan, setoran ditandai SELISIH, dan kolektor
     * ditagih uang yang sudah dia serahkan.
     *
     * @param  float|int|string|null  $amount
     */
    public static function rupiahInput($amount): string
    {
        $nilai = (float) ($amount ?? 0);
        $adaSen = round(fmod(abs($nilai) * 100, 100)) > 0;

        return number_format($nilai, $adaSen ? 2 : 0, ',', '.');
    }

    /**
     * Format a date to Indonesian Format (e.g. 16 Juni 2026 or Selasa, 16 Juni 2026).
     *
     * @param  string|\DateTime|Carbon|null  $date
     */
    public static function tanggal($date, bool $withDay = false): string
    {
        if (empty($date)) {
            return '-';
        }

        try {
            $carbon = Carbon::parse($date)->locale('id');
            $format = $withDay ? 'l, d F Y' : 'd F Y';

            return $carbon->translatedFormat($format);
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format time to Indonesian Format (e.g. 09:55 WIB).
     *
     * @param  string|\DateTime|Carbon|null  $time
     */
    public static function jam($time, bool $withWib = true): string
    {
        if (empty($time)) {
            return '-';
        }

        try {
            $carbon = Carbon::parse($time)->locale('id');
            $formatted = $carbon->translatedFormat('H:i');

            return $withWib ? $formatted.' WIB' : $formatted;
        } catch (\Exception $e) {
            return $time;
        }
    }

    /**
     * Format datetime to Indonesian Format (e.g. 16 Juni 2026 09:55 WIB).
     *
     * @param  string|\DateTime|Carbon|null  $datetime
     */
    public static function datetime($datetime, bool $withDay = false): string
    {
        if (empty($datetime)) {
            return '-';
        }

        try {
            $carbon = Carbon::parse($datetime)->locale('id');
            $dateFormat = $withDay ? 'l, d F Y' : 'd F Y';

            return $carbon->translatedFormat($dateFormat.' H:i').' WIB';
        } catch (\Exception $e) {
            return $datetime;
        }
    }
}
