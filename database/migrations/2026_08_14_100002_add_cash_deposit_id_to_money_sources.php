<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda "uang ini sudah ikut disetorkan admin ke owner/bank".
     *
     * Dua kolom, BUKAN tabel pivot: sumber kas admin cuma ada dua jenis, dan
     * masing-masing sudah punya barisnya sendiri. Tabel penghubung di sini
     * hanya menambah satu lapis tanpa menjawab pertanyaan baru.
     *
     * `null` = belum disetor = masih jadi saldo di tangan admin. Sama seperti
     * `payments.collector_deposit_id` di sisi kolektor, itulah SATU-SATUNYA
     * definisi saldo — tidak ada kolom saldo yang di-increment di mana pun.
     *
     * Konsekuensinya seluruh query kas cukup punya satu aturan
     * (`cash_deposit_id IS NULL`); tidak ada syarat kedua berbasis tanggal
     * yang harus diingat-ingat pemanggil berikutnya.
     *
     * docs/plan/kolektor/analisa-setoran-kas-admin.md §4.1, §7.
     */
    public function up(): void
    {
        Schema::table('collector_deposits', function (Blueprint $table) {
            $table->foreignId('cash_deposit_id')->nullable()->after('settles_deposit_id')
                ->constrained('cash_deposits')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cash_deposit_id')->nullable()->after('collector_deposit_id')
                ->constrained('cash_deposits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collector_deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_deposit_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_deposit_id');
        });
    }
};
