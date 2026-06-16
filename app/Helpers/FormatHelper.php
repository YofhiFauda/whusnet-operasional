<?php

namespace App\Helpers;

use Carbon\Carbon;

class FormatHelper
{
    /**
     * Format a number to Indonesian Rupiah (Rp).
     *
     * @param float|int|string|null $amount
     * @param bool $showDecimal
     * @return string
     */
    public static function rupiah($amount, bool $showDecimal = false): string
    {
        if ($amount === null || $amount === '') {
            return 'Rp 0';
        }

        $decimals = $showDecimal ? 2 : 0;
        return 'Rp ' . number_format((float) $amount, $decimals, ',', '.');
    }

    /**
     * Format a date to Indonesian Format (e.g. 16 Juni 2026 or Selasa, 16 Juni 2026).
     *
     * @param string|\DateTime|Carbon|null $date
     * @param bool $withDay
     * @return string
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
     * @param string|\DateTime|Carbon|null $time
     * @param bool $withWib
     * @return string
     */
    public static function jam($time, bool $withWib = true): string
    {
        if (empty($time)) {
            return '-';
        }

        try {
            $carbon = Carbon::parse($time)->locale('id');
            $formatted = $carbon->translatedFormat('H:i');
            return $withWib ? $formatted . ' WIB' : $formatted;
        } catch (\Exception $e) {
            return $time;
        }
    }

    /**
     * Format datetime to Indonesian Format (e.g. 16 Juni 2026 09:55 WIB).
     *
     * @param string|\DateTime|Carbon|null $datetime
     * @param bool $withDay
     * @return string
     */
    public static function datetime($datetime, bool $withDay = false): string
    {
        if (empty($datetime)) {
            return '-';
        }

        try {
            $carbon = Carbon::parse($datetime)->locale('id');
            $dateFormat = $withDay ? 'l, d F Y' : 'd F Y';
            return $carbon->translatedFormat($dateFormat . ' H:i') . ' WIB';
        } catch (\Exception $e) {
            return $datetime;
        }
    }
}
