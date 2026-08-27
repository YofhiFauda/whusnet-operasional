<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §4.1 — Fase 1 (fondasi).
 * Kolom PIN (§6.5) SENGAJA belum di sini — menyusul di migration terpisah
 * saat Fase 2 mulai, biar tiap fase punya jejak migrasinya sendiri.
 *
 * Tabel terpisah dari `customers`, bukan kolom — satu pelanggan bisa punya
 * beberapa token seumur hidup (stiker hilang → terbitkan baru, yang lama
 * dicabut) dan riwayat pencabutan perlu disimpan untuk audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // 128-bit acak, base32 26 char. BUKAN ULID — ULID membocorkan
            // timestamp penerbitan dan cuma 80-bit random (§3.2).
            $table->string('token', 26)->unique();

            // Bahan HMAC dibekukan saat penerbitan. WAJIB disimpan, tidak
            // boleh dibaca ulang dari relasi `customers` saat verifikasi —
            // kalau pelanggan dipindah POP, `customers.pop_id` berubah dan
            // signature yang dihitung ulang jadi beda dari yang TERCETAK di
            // stiker. Dengan disimpan, mismatch pop_id terdeteksi eksplisit
            // sebagai "token perlu diterbitkan ulang", bukan QR rusak
            // misterius (§4.1).
            $table->foreignId('signed_pop_id')->constrained('pops');
            $table->string('signed_customer_code', 30);

            // Snapshot display_id saat diterbitkan. HANYA untuk audit &
            // label stiker — JANGAN dipakai untuk verifikasi, display_id
            // berubah seiring lifecycle (§2.1).
            $table->string('issued_display_id', 40)->nullable();

            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users');

            // Pencabutan. Token dicabut TIDAK dihapus — jejak audit.
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->string('revoke_reason', 255)->nullable();

            $table->timestamp('last_scanned_at')->nullable();
            $table->unsignedInteger('scan_count')->default(0);

            $table->timestamps();

            $table->index(['customer_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_qr_tokens');
    }
};
