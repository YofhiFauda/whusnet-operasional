<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §4.2 — Fase 1 (fondasi).
 *
 * Semua scan dicatat, TERMASUK yang gagal — scan gagal justru sinyal paling
 * berharga: token dicabut yang masih dipindai = stiker lama beredar; scan di
 * luar radius berulang = indikasi absen fiktif (Fase 3, kolom geolocation
 * dipakai nanti).
 *
 * Kolom geolocation/task/ticket dibuat sekarang meski baru dipakai penuh di
 * Fase 2/3 — mengubah skema tabel log setelah baris mulai terisi lebih
 * mahal daripada menambah kolom nullable dari awal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_qr_token_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = pemindai publik

            $table->string('purpose', 20); // payment | attendance | ticketing | login
            $table->string('result', 30);  // success | bad_signature | token_not_found
            // | token_revoked | pop_mismatch | out_of_scope
            // | no_eligible_task | out_of_radius | verify_failed
            $table->string('reason', 255)->nullable();

            // Geolocation dari browser saat scan (Fase 3 — absen). Nullable.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_meters')->nullable();
            $table->unsignedInteger('distance_meters')->nullable();

            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['customer_id', 'scanned_at']);
            $table->index(['user_id', 'scanned_at']);
            $table->index(['result', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scan_logs');
    }
};
