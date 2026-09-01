<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token pertukaran satu-arah staf/kolektor ke Portal (docs/plan/qr-code/
     * analisa-unifikasi-qr-staff-portal.md §4). Diterbitkan `QrScanController`
     * PERSIS di titik yang sudah membuktikan `auth()->check()` (cookie sesi
     * Operasional sah) DAN sudah resolve `$customer` dari QR yang valid —
     * token ini cuma pembawa dua fakta itu ke request API berikutnya dari
     * Portal, BUKAN kredensial baru yang berdiri sendiri.
     *
     * SENGAJA tabel terpisah dari `customer_portal_tokens` — subjeknya beda
     * total (staf `users`, bukan `customers`), mencampur keduanya berarti
     * satu bug scoping bisa menyeberangkan hak akses staf ke populasi
     * pelanggan atau sebaliknya (alasan sama seperti kenapa
     * `customer_portal_tokens` tidak numpang Sanctum `personal_access_tokens`).
     *
     * `purpose` membatasi token ke SATU aksi (`tickets` atau `kolektor`) —
     * token yang diterbitkan buat lihat worklist kolektor tidak boleh
     * dipakai buat submit tiket, walau staf yang sama.
     *
     * `consumed_at` HANYA diisi caller setelah aksi PENULISAN berhasil
     * (`POST /tickets`, `POST /kolektor/payments`) — baca (`GET worklist`)
     * TIDAK mengonsumsi token, staf boleh lihat data berkali-kali dalam TTL
     * sebelum submit. Sekali dikonsumsi, penulisan kedua dengan token yang
     * sama ditolak (`StaffPortalTokenService::consume()`).
     */
    public function up(): void
    {
        Schema::create('staff_portal_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('purpose', 20); // 'tickets' | 'kolektor'

            $table->string('token_hash', 64)->index();

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Lookup tiap request masuk (PortalStaffToken middleware).
            $table->index(['token_hash', 'consumed_at', 'expires_at'], 'staff_portal_tokens_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_portal_tokens');
    }
};
