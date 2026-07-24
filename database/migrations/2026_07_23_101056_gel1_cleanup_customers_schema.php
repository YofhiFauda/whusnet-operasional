<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gel.1 — pembersihan skema customers (rancangan-fase4-persons.md §2).
 *
 * Destruktif: menghapus kolom. Wajib `migrate:fresh` + import ulang legacy.
 * Ditulis sebagai migration terpisah (bukan edit create_*) supaya reviewable
 * dan tidak perlu rekonsiliasi dengan migration tambal (enlarge_cid dll).
 *
 * Kolom yang dibuang harus SUDAH tidak dirujuk kode saat migration ini jalan —
 * lihat perubahan di CustomerController / CustomerVerificationController /
 * seeder / test pada commit yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Zombie: duplikat `status` yang gampang menyimpang. Sumber kebenaran
            // status layanan = customer_services.service_status.
            $table->dropColumn('customer_status');

            // `phone` disatukan ke `primary_phone` (rancangan §2.3). Seluruh
            // read/write/search di aplikasi + factory + seeder + ~80 penulisan di
            // tests sudah dipindah ke primary_phone. `alternative_phone` (kontak
            // sekunder) tetap. Sheet import legacy masih membawa kolom 'phone'
            // sebagai SUMBER — dibaca importer jadi fallback primary_phone — jadi
            // bukan kolom customers, tidak terpengaruh drop ini.
            $table->dropColumn('phone');

            // Jejak status akun sistem lama. Hanya pernah DITULIS saat import,
            // tidak pernah dibaca logika mana pun (sheet import & template masih
            // membawanya sebagai kolom SUMBER, tapi tidak lagi dipersist ke
            // customers). Nilai nol → buang.
            $table->dropColumn('old_account_status');

            // Persempit tipe kolom (rancangan §2.4). Di utf8mb4, varchar(255) =
            // 1020 byte per entri index. Kolom ini masuk composite index (status)
            // atau kandidat lookup (cid, auditable_type) — makin sempit, makin
            // banyak muat di buffer pool. Data nyata jauh di bawah target
            // (cid max 13, status max 24, auditable_type max 34), jadi nol risiko
            // truncation. Definisi di-respecify penuh supaya `->change()` tidak
            // menghapus nullable/default yang sudah ada.
            $table->string('cid', 50)->nullable()->change();       // dari 150 (bekas enlarge legacy)
            $table->string('status', 30)->default('registered')->change(); // dari 50, NOT NULL, default tetap
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('auditable_type', 100)->nullable()->change(); // dari 255; isinya FQCN model
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_status', 50)->default('calon_pelanggan')->after('data_completeness_status');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('old_account_status', 50)->nullable()->after('npwp');
            $table->string('cid', 150)->nullable()->change();
            $table->string('status', 50)->default('registered')->change();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('auditable_type', 255)->nullable()->change();
        });
    }
};
