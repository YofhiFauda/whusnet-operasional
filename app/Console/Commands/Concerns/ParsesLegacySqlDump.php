<?php

namespace App\Console\Commands\Concerns;

use RuntimeException;

/**
 * Parser buat dump SQL legacy (jetis_db, sand_db, caru_db, samb_db, paci_db)
 * — extract INSERT INTO `table` (...) VALUES (...), (...); jadi array asosiatif
 * per baris tanpa perlu database driver (dump-nya gak pernah di-load ke DB).
 *
 * Kenapa scanner karakter, bukan regex `VALUES (.*?);`: regex itu berhenti di
 * `;` PERTAMA yang ketemu, termasuk `;` di DALAM string. Satu alamat
 * `'luworo rt;09 rw;02'` (caru_db, PE000070) memotong satu statement INSERT
 * di tengah dan 121 pelanggan sesudahnya (PE000071–PE000191) hilang dari
 * migrasi TANPA error apa pun (2026-11-10). Hal yang sama berlaku buat
 * `preg_split('),(')` kalau ada `),(` di dalam teks. Scanner di bawah melacak
 * status "di dalam string" untuk SEMUA pemisah (`;`, `,`, `(`, `)`).
 *
 * Prinsip: lebih baik command GAGAL keras daripada baris pelanggan hilang
 * diam-diam — statement yang gak tertutup atau jumlah nilai ≠ jumlah kolom
 * dilempar sebagai RuntimeException, jangan dilunakkan jadi skip/warning.
 */
trait ParsesLegacySqlDump
{
    private function parseTableData(string $sql, string $tableName): array
    {
        $headerMatches = [];
        preg_match_all(
            '/INSERT INTO `'.preg_quote($tableName, '/').'` \(([^\)]+)\) VALUES\s*/',
            $sql,
            $headerMatches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        $rows = [];
        foreach ($headerMatches as $match) {
            $columnMatches = [];
            preg_match_all('/`([^`]+)`/', $match[1][0], $columnMatches);
            $columns = $columnMatches[1];
            $columnCount = count($columns);

            $offset = $match[0][1] + strlen($match[0][0]);

            foreach ($this->scanInsertValues($sql, $offset, $tableName) as $values) {
                if (count($values) !== $columnCount) {
                    throw new RuntimeException(sprintf(
                        'Dump legacy rusak di tabel `%s`: baris punya %d nilai, kolom %d. Awal baris: %s',
                        $tableName,
                        count($values),
                        $columnCount,
                        mb_substr(implode(', ', array_map(fn ($v) => (string) $v, $values)), 0, 120)
                    ));
                }

                $rows[] = array_combine($columns, $values);
            }
        }

        return $rows;
    }

    /**
     * Baca tuple `(...), (...), ...;` mulai dari $offset sampai `;` penutup
     * statement yang BERADA DI LUAR string.
     *
     * @return list<list<?string>>
     */
    private function scanInsertValues(string $sql, int $offset, string $tableName): array
    {
        $length = strlen($sql);
        $tuples = [];
        $values = [];
        $current = '';
        $inTuple = false;
        $inString = false;

        for ($i = $offset; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $this->unescapeSqlChar($sql[++$i]);

                    continue;
                }
                if ($char === "'") {
                    // '' di dalam string = satu tanda kutip literal (gaya phpMyAdmin)
                    if ($i + 1 < $length && $sql[$i + 1] === "'") {
                        $current .= "'";
                        $i++;

                        continue;
                    }
                    $inString = false;

                    continue;
                }
                $current .= $char;

                continue;
            }

            if (! $inTuple) {
                if ($char === '(') {
                    $inTuple = true;
                    $values = [];
                    $current = '';
                } elseif ($char === ';') {
                    return $tuples;
                }

                // Spasi/newline/koma antar tuple diabaikan.
                continue;
            }

            if ($char === "'") {
                $inString = true;

                continue;
            }

            if ($char === ',') {
                $values[] = $this->cleanParsedValue($current);
                $current = '';

                continue;
            }

            if ($char === ')') {
                $values[] = $this->cleanParsedValue($current);
                $tuples[] = $values;
                $inTuple = false;

                continue;
            }

            $current .= $char;
        }

        throw new RuntimeException("Dump legacy rusak: statement INSERT `{$tableName}` tidak ditutup `;` (string/tuple terbuka sampai akhir file).");
    }

    private function unescapeSqlChar(string $char): string
    {
        return match ($char) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '0' => "\0",
            'Z' => "\x1A",
            default => $char,
        };
    }

    /**
     * Literal `null` (tanpa kutip) maupun string 'null' sama-sama jadi null —
     * sistem legacy menyimpan teks "null" di kolom HP dsb. sebagai "kosong".
     */
    private function cleanParsedValue(string $val): ?string
    {
        $val = trim($val);
        if (strtolower($val) === 'null') {
            return null;
        }

        return $val;
    }
}
