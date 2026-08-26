<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kredensial login portal pelanggan (docs/api/api-portal-pelanggan/,
     * Fase 2) — TABEL TERPISAH dari `customers`, bukan kolom nempel di sana.
     *
     * Alasannya bukan estetika. `Customer` pakai trait `RecordsAuditLogs`
     * tanpa override `$auditEvents`, jadi tiap `updated` menulis nilai lama
     * DAN baru mentah ke `audit_logs` lewat `getChanges()` —
     * `$hidden`/`auditHidden` tidak menolong jalur itu sama sekali (ia
     * memfilter attributesToArray(), bukan getChanges()). Kalau password
     * nempel di `customers`, tiap ganti password nyimpen hash bcrypt lama +
     * baru permanen di audit log, terbaca staf mana pun. `User` (staf) lolos
     * dari masalah ini justru karena override `$auditEvents = ['deleted']`
     * (app/Models/User.php:28) — pola yang sama TIDAK cukup di sini karena
     * kolom lain (status, failed_attempts, last_login_at) tetap perlu
     * "diaudit" (atau justru sengaja TIDAK, lihat model — trait ini sama
     * sekali gak dipasang di CustomerPortalAccount, bukan cuma dikecualikan
     * sebagian). docs/api/api-portal-pelanggan/database-schema.md §1.
     */
    public function up(): void
    {
        Schema::create('customer_portal_accounts', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete — akun portal tidak berarti apa-apa tanpa
            // pelanggan induknya (beda dari webhook_outbox yang nullOnDelete,
            // itu baris riwayat, ini kredensial aktif).
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();

            // {registration_prefix}-{customer_code}, mis. PNG-RQ000631.
            // Satu-satunya jalur lookup saat login — unique cukup jadi index.
            $table->string('login_id', 64)->unique();

            $table->string('password_hash', 255);
            $table->timestamp('password_changed_at')->nullable();

            // Disimpan di DB, bukan cuma cache — cache bisa di-flush, lockout
            // ikut hilang bersamanya (alasan sama seperti lockout PIN §6.5.4).
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            // pending_claim / active / disabled.
            $table->string('status', 20)->default('pending_claim');

            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_portal_accounts');
    }
};
