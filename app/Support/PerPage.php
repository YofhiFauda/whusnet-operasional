<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Jumlah baris per halaman dari query string `per_page`, divalidasi lewat
 * whitelist. Tanpa whitelist, `?per_page=100000` bisa memaksa server memuat
 * seluruh tabel (plus eager-load relasinya) dalam satu request.
 * Pasangan tampilannya: komponen `<x-per-page-select>`.
 */
class PerPage
{
    /** @var list<int> */
    public const OPTIONS = [10, 25, 50, 100];

    public const DEFAULT = 10;

    public static function resolve(Request $request): int
    {
        $perPage = (int) $request->query('per_page', self::DEFAULT);

        return in_array($perPage, self::OPTIONS, true) ? $perPage : self::DEFAULT;
    }
}
