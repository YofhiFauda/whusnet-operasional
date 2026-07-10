<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class IndonesianDate
{
    private const TIMEZONE = 'Asia/Jakarta';

    public static function date(CarbonInterface|string|null $date): string
    {
        if (! $date) {
            return '-';
        }

        return self::carbon($date)->translatedFormat('d F Y');
    }

    public static function dateTime(CarbonInterface|string|null $date): string
    {
        if (! $date) {
            return '-';
        }

        return self::carbon($date)->translatedFormat('d F Y, H.i').' WIB';
    }

    public static function time(CarbonInterface|string|null $date): string
    {
        if (! $date) {
            return '-';
        }

        return self::carbon($date)->format('H.i').' WIB';
    }

    private static function carbon(CarbonInterface|string $date): CarbonInterface
    {
        if ($date instanceof CarbonInterface) {
            return $date->copy()->timezone(self::TIMEZONE)->locale('id');
        }

        return Carbon::parse($date, self::TIMEZONE)->locale('id');
    }
}
