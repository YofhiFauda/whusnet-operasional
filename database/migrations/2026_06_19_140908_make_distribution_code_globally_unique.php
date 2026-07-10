<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah constraint uniqueness kode distribusi dari per-POP menjadi global.
 *
 * Sesuai spesifikasi-pop-distribusi-cid.md:
 * "Kode distribusi harus unik secara global di seluruh sistem"
 *
 * Sebelumnya: unique(['pop_id', 'code'])  → unik per POP
 * Sesudah:    unique(['code'])            → unik di seluruh sistem
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cek apakah ada duplikasi kode di POP berbeda sebelum alter
        $duplicates = DB::table('distributions')
            ->select('code', DB::raw('COUNT(*) as cnt'))
            ->groupBy('code')
            ->having('cnt', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $codes = $duplicates->pluck('code')->implode(', ');
            throw new \RuntimeException(
                "Tidak dapat mengubah ke global unique: kode distribusi berikut duplikat di POP berbeda: {$codes}. " .
                "Perbaiki data terlebih dahulu sebelum menjalankan migration ini."
            );
        }

        // Disable FK checks SEBELUM mulai alter (harus di luar Schema::table callback)
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        // Drop composite unique index menggunakan raw SQL (lebih aman di MySQL)
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS distributions_pop_id_code_unique');
            } else {
                DB::statement('ALTER TABLE distributions DROP INDEX distributions_pop_id_code_unique');
            }
        } catch (\Exception $e) {
            // Index mungkin sudah tidak ada atau bernama berbeda, lanjutkan saja
        }

        // Tambah global unique index hanya pada code
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS distributions_code_unique ON distributions (code)');
            } else {
                DB::statement('ALTER TABLE distributions ADD UNIQUE INDEX distributions_code_unique (code)');
            }
        } catch (\Exception $e) {
            // Jika index sudah ada, lanjutkan
        }

        // Re-enable FK checks
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            // Kembalikan ke composite unique per POP
            try {
                $table->dropUnique('distributions_code_unique');
            } catch (\Exception $e) {
                // ignore
            }

            $table->unique(['pop_id', 'code']);
        });
    }
};
