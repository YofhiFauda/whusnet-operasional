<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hapus-atau-nonaktifkan untuk data master (POP, paket, kategori barang, dll).
 *
 * Aturannya satu tombol, dua hasil: kalau record masih direferensikan tabel
 * lain (FK apa pun, termasuk yang `nullOnDelete`/`cascadeOnDelete`) → cuma
 * dinonaktifkan, karena menghapusnya bakal memutus jejak histori. Kalau tidak
 * ada yang merujuk → hard delete.
 *
 * Dependensi dibaca dari skema FK asli, bukan daftar relasi manual, supaya
 * tabel baru yang nanti merujuk master ini otomatis ikut dicek.
 */
class MasterRecordRemovalService
{
    /** @var array<string, list<array{table: string, column: string}>> */
    private array $referencesCache = [];

    /**
     * @param  array<string, mixed>  $deactivation  atribut yang di-set kalau nonaktif, mis. ['is_active' => false]
     * @return bool true kalau benar-benar dihapus, false kalau hanya dinonaktifkan
     */
    public function remove(Model $record, array $deactivation): bool
    {
        if ($this->hasDependents($record)) {
            $record->forceFill($deactivation)->save();

            return false;
        }

        $record->delete();

        return true;
    }

    public function hasDependents(Model $record): bool
    {
        $key = $record->getKey();

        foreach ($this->referencesTo($record->getTable()) as $reference) {
            if (DB::table($reference['table'])->where($reference['column'], $key)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{table: string, column: string}>
     */
    private function referencesTo(string $parentTable): array
    {
        if (isset($this->referencesCache[$parentTable])) {
            return $this->referencesCache[$parentTable];
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $references = match ($driver) {
            'mysql', 'mariadb' => collect($connection->select(
                'SELECT TABLE_NAME AS child_table, COLUMN_NAME AS child_column
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND REFERENCED_TABLE_NAME = ?',
                [$parentTable]
            ))->map(fn ($row) => ['table' => $row->child_table, 'column' => $row->child_column])->all(),

            'sqlite' => $this->sqliteReferences($parentTable),

            default => throw new RuntimeException("Driver {$driver} belum didukung untuk cek dependensi master."),
        };

        return $this->referencesCache[$parentTable] = $references;
    }

    /**
     * @return list<array{table: string, column: string}>
     */
    private function sqliteReferences(string $parentTable): array
    {
        $references = [];

        foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'table'") as $table) {
            $childTable = $table->name;

            foreach (DB::select('PRAGMA foreign_key_list("'.$childTable.'")') as $foreignKey) {
                if ($foreignKey->table === $parentTable) {
                    $references[] = ['table' => $childTable, 'column' => $foreignKey->from];
                }
            }
        }

        return $references;
    }
}
