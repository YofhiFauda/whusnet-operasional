<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADHOC-108 (K7) — Edit Pembayaran boleh mengoreksi nominal/saldo lebih
     * dari sekali pada payment yang sama. Koreksi dicatat sebagai baris
     * DELTA baru (`BalanceMutationSource::KOREKSI`), bukan membalik baris
     * lama (lihat CustomerBalanceService::applyCorrection()) — tapi unique
     * index `(payment_id, type, source)` (ADHOC-92) cuma mengizinkan SATU
     * baris `koreksi` per payment per type. Edit kedua pada payment yang
     * sama akan bentrok index itu tanpa kolom penanda urutan.
     *
     * `revision` menomori baris koreksi ke berapa (1, 2, 3, ...) — baris lama
     * (bukan koreksi) semuanya `0` lewat default, jadi unique index lama
     * tetap berlaku utuh untuk baris non-koreksi.
     *
     * SETIAP LANGKAH DIJAGA IDEMPOTEN (`hasColumn`/`hasIndex`) — MySQL
     * meng-commit tiap statement DDL sendiri-sendiri (tak bisa rollback
     * sebagian kalau migrasi berhenti di tengah), jadi kalau proses migrate
     * terhenti di tengah, migrasi TETAP tercatat belum jalan (baris
     * `migrations` cuma ditulis di akhir) — run berikutnya mengulang dari
     * awal dan gagal di langkah yang sudah kepalang jalan. Ditemukan persis
     * begini di DB dev 2026-09-29 (kolom `revision` sudah ada, migrasi masih
     * "Pending").
     *
     * URUTAN index SENGAJA "tambah index BARU dulu, baru hapus yang LAMA" —
     * dibalik dari urutan wajar "drop lalu add". `payment_id` punya foreign
     * key ke `payments`; index unik lama (`..._unique`, diawali `payment_id`)
     * ternyata satu-satunya index yang menutupi kolom FK itu (tak ada index
     * `payment_id_foreign` terpisah). MySQL/InnoDB MENOLAK men-drop index
     * semacam ini kalau tak ada index lain yang sudah menutupi kolom FK-nya
     * ("Cannot drop index ...: needed in a foreign key constraint") — juga
     * ditemukan di DB dev 2026-09-29. Index baru (`..._revision_unique`)
     * SAMA-SAMA diawali `payment_id`, jadi begitu index baru itu ada, index
     * lama boleh dihapus tanpa membuat kolom FK sesaat pun kehilangan index
     * penutup. Data lama aman: seluruh baris punya `revision=0` (default),
     * jadi kombinasi 4 kolom index baru tetap unik selama kombinasi 3 kolom
     * lama juga unik (revision cuma menambah, tidak pernah mengurangi
     * keunikan).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('customer_balance_mutations', 'revision')) {
            Schema::table('customer_balance_mutations', function (Blueprint $table) {
                $table->unsignedSmallInteger('revision')->default(0)->after('source');
            });
        }

        if (! Schema::hasIndex('customer_balance_mutations', 'customer_balance_mutations_payment_type_source_revision_unique')) {
            Schema::table('customer_balance_mutations', function (Blueprint $table) {
                $table->unique(['payment_id', 'type', 'source', 'revision'], 'customer_balance_mutations_payment_type_source_revision_unique');
            });
        }

        if (Schema::hasIndex('customer_balance_mutations', 'customer_balance_mutations_payment_type_source_unique')) {
            Schema::table('customer_balance_mutations', function (Blueprint $table) {
                $table->dropUnique('customer_balance_mutations_payment_type_source_unique');
            });
        }
    }

    public function down(): void
    {
        // Sama alasannya dengan up() — tambah dulu index yang mau
        // dipertahankan (lama), baru hapus index baru yang sedang jadi
        // satu-satunya penutup kolom FK `payment_id`.
        if (! Schema::hasIndex('customer_balance_mutations', 'customer_balance_mutations_payment_type_source_unique')) {
            Schema::table('customer_balance_mutations', function (Blueprint $table) {
                $table->unique(['payment_id', 'type', 'source'], 'customer_balance_mutations_payment_type_source_unique');
            });
        }

        if (Schema::hasIndex('customer_balance_mutations', 'customer_balance_mutations_payment_type_source_revision_unique')) {
            Schema::table('customer_balance_mutations', function (Blueprint $table) {
                $table->dropUnique('customer_balance_mutations_payment_type_source_revision_unique');
            });
        }

        if (Schema::hasColumn('customer_balance_mutations', 'revision')) {
            Schema::table('customer_balance_mutations', function (Blueprint $table) {
                $table->dropColumn('revision');
            });
        }
    }
};
