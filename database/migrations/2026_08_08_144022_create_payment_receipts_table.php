<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arsip kwitansi pembayaran — dokumen, BUKAN uang.
     *
     * Dua sumbu yang sengaja dipisah (docs/plan/kolektor/analisa-alur-kolektor-2.0.md §13.2):
     *
     *   Sumbu KAS     : bayar → setor → cross check → terverifikasi   ← selesai hari itu
     *   Sumbu DOKUMEN : cetak → upload → cocokkan → matched           ← status sendiri
     *
     * Setoran terverifikasi TANPA menunggu kwitansi diupload. Rancangan awal
     * menggantung status verifikasi kolektor pada selesainya OCR — itu
     * menyandera uang (yang sudah dihitung dua orang di meja) pada dokumen
     * yang bisa gagal dibaca.
     *
     * Yang perlu dipahami soal nilai tabel ini: kwitansi dicetak SETELAH
     * pembayaran tersimpan, jadi ia **bukan kontrol anti-fraud**. Kolektor yang
     * menerima uang lalu tak melaporkannya tidak pernah mencetak kwitansi —
     * tak ada yang hilang, tak ada alarm. Yang menangkap kasus itu tetap
     * `collector_visits` (§12). Kwitansi = bukti bagi pelanggan saat sengketa.
     *
     * `payment_id` nullable: file bisa mendarat sebelum diketahui milik siapa
     * (QR tak terbaca). Baris tetap ada supaya dokumen yang belum tercocokkan
     * tidak hilang diam-diam dari daftar kerja admin.
     */
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();

            // Terisi begitu tercocokkan. nullOnDelete: kalau payment dihapus,
            // filenya tetap tersimpan sebagai arsip yatim daripada ikut lenyap.
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();

            // Disalin dari payment saat cocok — dipakai POP scope. Sebelum
            // cocok masih null, dan pada saat itu yang menjaga akses adalah
            // `uploaded_by` + permission halaman.
            $table->foreignId('pop_id')->nullable()->constrained('pops')->nullOnDelete();

            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();

            $table->string('original_filename');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // SHA-256 isi file. Unique: upload ulang berkas yang sama persis
            // (folder scan dipilih dua kali) tidak melahirkan baris kedua yang
            // menunggu dicocokkan padahal dokumennya identik.
            $table->string('checksum', 64)->unique();

            $table->string('status')->default('pending')->index();
            $table->string('match_method')->nullable();
            $table->string('detected_number')->nullable();

            // Percobaan baca otomatis. Dibatasi supaya file yang memang tak
            // terbaca berhenti membebani queue dan segera jadi urusan manusia.
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
