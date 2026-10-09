<?php

namespace App\Support;

/**
 * Membersihkan input pencarian sebelum dipakai di `LIKE '%…%'`.
 *
 * `%` dan `_` di input adalah wildcard SQL. Tanpa dibersihkan, `%%%` cocok
 * dengan hampir semua baris dalam POP scope. Escape `\` tidak dipakai karena
 * `LIKE` tidak portabel: SQLite tidak punya ESCAPE default, jadi `\%` tidak
 * berarti literal di test. Karakter itu dibuang saja.
 */
final class LikeSearch
{
    public static function sanitize(string $term): string
    {
        return trim(str_replace(['%', '_', '\\'], '', $term));
    }
}
