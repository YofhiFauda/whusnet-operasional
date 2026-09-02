<?php

use App\Helpers\FormatHelper;
use Illuminate\Support\Facades\Storage;

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

if (! function_exists('foto_publik')) {
    /**
     * URL publik sebuah foto di disk `public`, atau null kalau path kosong
     * ATAU filenya sudah tidak ada di disk.
     *
     * Cek exists() bukan paranoia: data lama menyimpan nama file hash telanjang
     * tanpa folder (skema sebelum FileUploadService, mis. `d481de7d….jpg`) yang
     * filenya sudah hilang. Tanpa cek ini view merender <img> ke URL yang pasti
     * 404 — pengguna cuma lihat ikon rusak dan console penuh error, padahal
     * blok @else "tidak ada foto" yang sudah ada di view justru yang benar.
     *
     * Pakai ini di view sebagai pengganti `asset('storage/'.$path)` langsung.
     */
    function foto_publik(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return asset('storage/'.$path);
    }
}
