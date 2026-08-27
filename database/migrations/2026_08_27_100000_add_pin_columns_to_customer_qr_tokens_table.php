<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §4.1, §6.5 — Fase 2.
 *
 * Kolom PIN pelanggan — SENGAJA migration TERPISAH dari
 * 2026_08_26_100000_create_customer_qr_tokens_table (Fase 1), biar tiap
 * fase punya jejak migrasinya sendiri (lihat komentar di migration itu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_qr_tokens', function (Blueprint $table) {
            // Namanya masih `pin_hash` (nama kolom dibekukan sejak awal),
            // tapi ISINYA reversible — `Crypt::encryptString()`, BUKAN
            // bcrypt (koreksi 2026-08-26, lihat docblock
            // `CustomerQrTokenService::issuePin()`/`revealPin()`). PIN wajib
            // bisa dibuka ulang buat kartu reprintable (`/qr/cetak`) — kalau
            // kolom ini bocor lewat dump/backup/log, PIN plaintext BISA
            // dibaca ulang oleh siapa pun yang pegang `APP_KEY`, bukan cuma
            // brute-force per baris seperti bcrypt.
            $table->string('pin_hash')->nullable()->after('scan_count');

            // Rotasi TANPA menyentuh token/signature — pelanggan lupa PIN
            // cukup dapat PIN baru, stiker QR tetap berlaku (§6.5.2).
            $table->timestamp('pin_issued_at')->nullable()->after('pin_hash');
            $table->foreignId('pin_issued_by')->nullable()->after('pin_issued_at')->constrained('users');
            $table->unsignedTinyInteger('pin_version')->default(1)->after('pin_issued_by');

            // PIN cetak = PIN AKTIVASI sekali pakai, bukan PIN permanen —
            // ada jendela waktu teknisi memegang PIN secara fisik saat
            // serah-terima kartu, wajib-ganti saat login pertama membuat
            // pengetahuan itu kedaluwarsa (§6.5.2 poin "pin_must_change").
            $table->boolean('pin_must_change')->default(true)->after('pin_version');
            $table->timestamp('pin_first_used_at')->nullable()->after('pin_must_change');

            // PIN kedaluwarsa otomatis kalau gak pernah dipakai 90 hari —
            // menggantikan dashboard "PIN belum diaktivasi" yang cuma jadi
            // kebisingan (mayoritas pelanggan gak pernah login, bayar lewat
            // kolektor). PIN yang SUDAH diganti sendiri (pasca wajib-ganti)
            // tidak ikut kedaluwarsa — di-null-kan di Service, bukan di sini.
            $table->timestamp('pin_expires_at')->nullable()->after('pin_first_used_at');

            // Anti brute-force yang bertahan lintas request & restart cache —
            // rate limiter Laravel berbasis cache SAJA gak cukup (cache flush
            // menghapus hitungan, itu jalur bypass yang gampang, §6.5.4).
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0)->after('pin_expires_at');
            $table->timestamp('pin_locked_until')->nullable()->after('pin_failed_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('customer_qr_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pin_issued_by');
            $table->dropColumn([
                'pin_hash', 'pin_issued_at', 'pin_version',
                'pin_must_change', 'pin_first_used_at', 'pin_expires_at',
                'pin_failed_attempts', 'pin_locked_until',
            ]);
        });
    }
};
