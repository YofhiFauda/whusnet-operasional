<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setoran Kolektor — satu sesi serah-terima UANG FISIK dari kolektor ke
     * admin. Beda entitas dengan `payment_batches` (satu sesi SUBMIT): satu
     * setoran bisa memuat pembayaran dari banyak batch, jadi bukan kolomnya
     * `payment_batches` yang diperluas (lihat komentar di migration itu).
     *
     * Yang SENGAJA tidak ada di sini: kolom saldo kolektor. Saldo adalah angka
     * TURUNAN — Σ payment belum tersetor − Σ setoran terverifikasi — dihitung
     * di CollectorBalanceService. Kolom saldo yang di-`+=`/`-=` pasti bohong
     * begitu ada payment di-reject, dan angka uang yang bohong tak punya
     * alarm: ketahuannya berbulan-bulan kemudian dan tak bisa direkonstruksi.
     *
     * Yang ADA dan wajib dipahami:
     *
     * - `declared_amount` — uang fisik yang DIHITUNG ADMIN di meja saat
     *   verifikasi. Bukan angka yang diketik kolektor: kalau kolektor yang
     *   mendeklarasikan, dia mengontrol kedua sisi persamaan dan cross check
     *   kehilangan artinya.
     * - `difference` = `declared_amount` − (Σ payment setoran ini +
     *   `settlement_amount`). Disimpan sebagai HASIL verifikasi, bukan angka
     *   hidup — aman karena payment tak bisa keluar dari setoran yang sudah
     *   diverifikasi (guard di PaymentController::reject()).
     * - `settlement_amount` + `settles_deposit_id` — pelunasan selisih setoran
     *   sebelumnya yang ikut dibawa setoran ini. WAJIB field terpisah: kalau
     *   uang pelunasan cuma dilebur ke `declared_amount`, hasilnya `difference`
     *   positif alias "lebih setor" — selisih baru yang menggantung, dan
     *   laporan selisih tak pernah nol.
     * - `settled_amount` — akumulasi pelunasan yang DITERIMA setoran ini dari
     *   setoran-setoran berikutnya. Sisa kewajiban = kurang setor −
     *   `settled_amount`; jadi pelunasan bertahap tetap terlacak.
     *
     * Status: menunggu_verifikasi → terverifikasi | selisih → selisih_lunas |
     * dihapus_buku. `selisih` BUKAN status terminal — wajib punya jalan pulang
     * (dilunasi setoran berikutnya, atau dihapus buku oleh Owner + alasan).
     *
     * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §11.
     */
    public function up(): void
    {
        Schema::create('collector_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('deposit_number')->unique();
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();

            // POP representatif untuk listing & filter. Otorisasi verifikasi
            // TIDAK bersandar pada kolom ini — admin wajib bisa melihat SELURUH
            // payment di setoran lewat POP scope-nya (CollectorDepositService).
            // Kolektor ber-scope pop_tree bisa punya setoran lintas POP; kalau
            // otorisasi cuma mengecek satu kolom, setoran seperti itu bocor.
            $table->foreignId('pop_id')->nullable()->constrained('pops')->nullOnDelete();

            $table->string('status')->default('menunggu_verifikasi')->index();
            $table->decimal('declared_amount', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->nullable();
            $table->decimal('settlement_amount', 15, 2)->default(0);
            $table->decimal('settled_amount', 15, 2)->default(0);
            $table->foreignId('settles_deposit_id')->nullable()->constrained('collector_deposits')->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('written_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('written_off_at')->nullable();
            $table->text('write_off_reason')->nullable();

            // Cegah setoran dobel dari klik ganda / retry jaringan — pola sama
            // dengan payment_batches.
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['collector_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_deposits');
    }
};
