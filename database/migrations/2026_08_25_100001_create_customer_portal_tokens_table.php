<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token akses & refresh portal pelanggan (docs/api/api-portal-pelanggan/,
     * Fase 2). BUKAN numpang Sanctum `personal_access_tokens` — tabel itu
     * polymorphic dan dipakai bareng `users` (staf); mencampur kredensial
     * pelanggan dan staf berarti satu bug scoping bisa menyeberangkan hak
     * akses antar dua populasi yang seharusnya tidak pernah bersinggungan
     * (database-schema.md §2).
     *
     * `token_hash` INDEX BIASA, bukan unique — dokumen eksplisit bilang
     * begitu (kolisi SHA-256 diabaikan sebagai risiko; kalau nanti terbukti
     * perlu unique, itu perubahan terpisah, bukan diam-diam ditambah di
     * sini).
     *
     * `parent_id` cukup tanpa kolom `family_id` tambahan — rotasi refresh
     * SELALU 1:1 (satu refresh lama → satu refresh baru), rantainya
     * linked-list linear, bukan tree bercabang. Mencabut "seluruh turunan"
     * saat reuse terdeteksi cukup traversal iteratif maju lewat
     * `where parent_id = X` — lihat CustomerPortalToken::revokeDescendants().
     */
    public function up(): void
    {
        Schema::create('customer_portal_tokens', function (Blueprint $table) {
            $table->id();

            // Denormal (bukan cuma diturunkan lewat token induk) — supaya
            // pencabutan massal ("logout dari semua perangkat", pelanggan
            // terminated) satu query tanpa join.
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('token_hash', 64)->index();

            // access / refresh — access 15 menit, refresh 30 hari rotating.
            $table->string('type', 10);

            $table->foreignId('parent_id')->nullable()->constrained('customer_portal_tokens')->nullOnDelete();

            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Cabut massal per pelanggan (logout-all, pelanggan terminated).
            $table->index(['customer_id', 'type', 'revoked_at']);
            // Lookup tiap request masuk (EnsurePortalCustomerToken) &
            // verifikasi refresh saat /auth/refresh.
            $table->index(['type', 'token_hash', 'revoked_at', 'expires_at'], 'customer_portal_tokens_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_portal_tokens');
    }
};
