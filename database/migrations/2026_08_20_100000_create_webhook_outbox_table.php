<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antrean + jejak pengiriman webhook keluar (API 1: `installation.activated`
     * ke Website B & Telegram Eksternal; API 2 nanti: `invoice.updated` ke
     * portal). SATU baris = SATU event, bukan satu baris per percobaan kirim —
     * `attempts` dinaikkan di tempat dan payload TIDAK dirakit ulang tiap retry.
     * Kalau payload dirakit ulang, percobaan ke-3 bisa membawa data yang sudah
     * berubah, lalu dibuang penerima sebagai duplikat `event_id` — perubahan
     * hilang tanpa jejak. Baris ditulis di dalam transaksi trigger-nya, HTTP
     * baru terjadi setelah commit (lihat SendWebhookOutboxJob::$afterCommit).
     *
     * `destination` SENGAJA BUKAN foreign key. Tujuan webhook hardcode di
     * config/webhooks.php (rev. 8, docs/api/keputusan.md) — cuma ada 1
     * konsumen tiap transport sekarang, tabel `webhook_endpoints` + form admin
     * dicabut dari rancangan karena melayani konsumen yang belum ada.
     */
    public function up(): void
    {
        Schema::create('webhook_outbox', function (Blueprint $table) {
            $table->id();

            // Identifier tetap ke entri config/webhooks.php: 'website_b' /
            // 'telegram_external'. Bukan FK — lihat komentar kelas di atas.
            $table->string('destination', 30);

            $table->string('event', 50);

            // Tetap sama di SEMUA percobaan kirim event yang sama — dipakai
            // penerima buang kiriman dobel akibat retry jaringan.
            $table->uuid('event_id');

            // Berubah tiap penekanan Aktivasi (installation:{id}:activation:{n}).
            // Dipakai penerima kenali event ini state TERBARU untuk pemasangan
            // itu — nomor tertinggi yang menang, bukan waktu terima.
            $table->string('idempotency_key', 100)->nullable();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->json('payload');

            // pending / delivered / failed / skipped. `skipped` dipakai dua
            // makna (dibedakan lewat last_error): (1) transport telegram yang
            // teksnya identik dgn kiriman terakhir — sengaja tidak dikirim;
            // (2) event lama yang datang belakangan (superseded oleh
            // idempotency_key lebih tinggi yang sudah delivered).
            $table->string('status', 15)->default('pending');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            // Worker ambil pekerjaan yang siap dicoba.
            $table->index(['status', 'next_attempt_at']);
            // Halaman riwayat per tujuan.
            $table->index(['destination', 'created_at']);
            $table->index('event_id');
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_outbox');
    }
};
