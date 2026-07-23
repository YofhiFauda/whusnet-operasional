<?php

use App\Helpers\FormatHelper;

if (! function_exists('format_rupiah')) {
    /**
     * Format number to Indonesian Rupiah.
     *
     * @param  float|int|string|null  $amount
     */
    function format_rupiah($amount, bool $showDecimal = false): string
    {
        return FormatHelper::rupiah($amount, $showDecimal);
    }
}

if (! function_exists('format_tanggal')) {
    /**
     * Format date to Indonesian Format.
     *
     * @param  string|DateTime|Carbon\Carbon|null  $date
     */
    function format_tanggal($date, bool $withDay = false): string
    {
        return FormatHelper::tanggal($date, $withDay);
    }
}

if (! function_exists('format_jam')) {
    /**
     * Format time to Indonesian Format (WIB).
     *
     * @param  string|DateTime|Carbon\Carbon|null  $time
     */
    function format_jam($time, bool $withWib = true): string
    {
        return FormatHelper::jam($time, $withWib);
    }
}

if (! function_exists('format_datetime')) {
    /**
     * Format datetime to Indonesian Format.
     *
     * @param  string|DateTime|Carbon\Carbon|null  $datetime
     */
    function format_datetime($datetime, bool $withDay = false): string
    {
        return FormatHelper::datetime($datetime, $withDay);
    }
}
