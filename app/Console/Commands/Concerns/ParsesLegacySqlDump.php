<?php

namespace App\Console\Commands\Concerns;

/**
 * Parser buat dump SQL legacy (jetis_db_aplikasi_jetis.sql / sand_db_sandya.sql)
 * — extract INSERT INTO `table` (...) VALUES (...), (...); jadi array asosiatif
 * per baris tanpa perlu database driver (murni regex/text, dump-nya gak pernah
 * di-load ke DB manapun).
 */
trait ParsesLegacySqlDump
{
    private function parseTableData(string $sql, string $tableName): array
    {
        $insertMatches = [];
        preg_match_all('/INSERT INTO `'.$tableName.'` \(([^\)]+)\) VALUES\s*(.*?);/s', $sql, $insertMatches);

        $rows = [];
        foreach ($insertMatches[1] as $idx => $columnsList) {
            // Extract actual columns from this INSERT block
            $columnMatches = [];
            preg_match_all('/`([^`]+)`/', $columnsList, $columnMatches);
            $columns = $columnMatches[1];

            $valuesBlock = $insertMatches[2][$idx];

            // Trim whitespace
            $valuesBlock = trim($valuesBlock);

            // Remove the first '(' and last ')'
            if (str_starts_with($valuesBlock, '(')) {
                $valuesBlock = substr($valuesBlock, 1);
            }
            if (str_ends_with($valuesBlock, ')')) {
                $valuesBlock = substr($valuesBlock, 0, -1);
            }

            // Split by '),(' or '),\n(' or '),\r\n('
            $rowStrings = preg_split('/\),\s*\(/', $valuesBlock);

            foreach ($rowStrings as $rowString) {
                $values = $this->splitSqlValues($rowString);

                $rowData = [];
                foreach ($columns as $index => $colName) {
                    $val = $values[$index] ?? null;
                    $rowData[$colName] = $val;
                }
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    private function splitSqlValues(string $rowString): array
    {
        $values = [];
        $currentVal = '';
        $inString = false;
        $escape = false;

        for ($i = 0; $i < strlen($rowString); $i++) {
            $char = $rowString[$i];

            if ($escape) {
                $currentVal .= $char;
                $escape = false;

                continue;
            }

            if ($char === '\\') {
                $escape = true;

                continue;
            }

            if ($char === "'") {
                $inString = ! $inString;

                continue;
            }

            if ($char === ',' && ! $inString) {
                $values[] = $this->cleanParsedValue($currentVal);
                $currentVal = '';
            } else {
                $currentVal .= $char;
            }
        }

        $values[] = $this->cleanParsedValue($currentVal);

        return $values;
    }

    private function cleanParsedValue(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $val = trim($val);
        if (strtolower($val) === 'null') {
            return null;
        }

        return $val;
    }
}
