<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Terapkan sort ASC/DESC dari query string `sort`/`dir` ke query Eloquent,
 * divalidasi lewat whitelist supaya `sort` dari user tidak pernah dipakai
 * mentah sebagai nama kolom (hindari SQL injection / kolom tak sengaja
 * ke-expose). `$columns` = [key => kolom asli ATAU closure(Builder $query, string $dir)
 * untuk kolom relasi/subquery yang tidak bisa langsung `orderBy()`].
 *
 * Kalau `sort` tidak ada di whitelist (atau kosong), query TIDAK disentuh —
 * pemanggil tetap pakai default order-nya sendiri (`->latest(...)` dst).
 */
class SortRequest
{
    /**
     * @param  array<string, string|callable(Builder, string): void>  $columns
     * @return string|null kolom aktif yang akhirnya dipakai (null kalau fallback ke default)
     */
    public static function apply(Request $request, Builder $query, array $columns): ?string
    {
        $sort = trim((string) $request->query('sort', ''));
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        if ($sort === '' || ! array_key_exists($sort, $columns)) {
            return null;
        }

        $target = $columns[$sort];

        if (is_callable($target)) {
            $target($query, $dir);
        } else {
            $query->orderBy($target, $dir);
        }

        return $sort;
    }
}
