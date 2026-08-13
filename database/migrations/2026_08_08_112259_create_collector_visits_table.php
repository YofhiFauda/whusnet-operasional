<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan kunjungan kolektor — termasuk kunjungan yang TIDAK menghasilkan
     * uang.
     *
     * Ini satu-satunya kontrol anti-fraud di modul kolektor, dan alasannya
     * struktural. Setoran cuma menangkap "laporan jujur, kas tidak jujur";
     * skenario "laporan tidak jujur" lolos 100%:
     *
     *     Kolektor tagih Ani 100rb → Ani TIDAK dilaporkan sama sekali →
     *     uang dikantongi. Setoran cocok sempurna, status terverifikasi,
     *     invoice Ani tetap belum_dibayar, Ani merasa sudah bayar,
     *     sistem diam.
     *
     * Tanpa tabel ini, "tidak ada baris" itu ambigu: pelanggan belum didatangi,
     * atau didatangi lalu uangnya raib. Dengan tabel ini muncul pola yang layak
     * diaudit — "pelanggan ini 5× `tidak_ada_orang` tapi tunggakannya menua".
     *
     * Dua jalur pengisian, sengaja beda kewenangan:
     *   - `bayar` → HANYA diturunkan otomatis dari payment yang benar-benar
     *     tersimpan (CollectorPaymentService). Tidak boleh diinput manual,
     *     kalau tidak kolektor bisa mengaku "sudah bayar" tanpa uang dan
     *     justru menutupi lubang yang mau dijaga tabel ini.
     *   - selain itu → diinput kolektor dari Worklist-nya.
     *
     * Satu baris = satu kunjungan ke satu pintu pada satu hari. Kunci unik
     * (collector, customer, tanggal) mencegah satu kunjungan tercatat berkali-
     * kali cuma karena pelanggannya punya 3 tagihan yang dibayar sekaligus.
     *
     * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §12.
     */
    public function up(): void
    {
        Schema::create('collector_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            // Disalin dari pelanggan saat kunjungan dicatat — laporan aging
            // per POP tak boleh ikut berubah kalau pelanggannya dipindah POP
            // belakangan, dan scope admin dibaca dari kolom ini.
            $table->foreignId('pop_id')->nullable()->constrained('pops')->nullOnDelete();

            $table->date('visited_at');
            $table->string('result')->index();
            $table->date('promised_date')->nullable();
            $table->text('note')->nullable();

            // Terisi hanya untuk `result = bayar`. Kalau satu kunjungan
            // menghasilkan beberapa payment (pelanggan melunasi 3 tagihan
            // sekaligus), yang disimpan payment terakhir sebagai penunjuk —
            // uang lengkapnya tetap dibaca dari tabel `payments`, bukan dari
            // sini. Tabel ini soal KUNJUNGAN, bukan soal uang.
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->timestamps();

            $table->unique(['collector_id', 'customer_id', 'visited_at'], 'collector_visits_unique_per_day');
            $table->index(['collector_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_visits');
    }
};
