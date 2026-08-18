<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setoran Kas Admin — serah-terima uang admin → owner/bank.
     *
     * Sengaja bercermin pada `collector_deposits`, TAPI angka-angkanya punya
     * sifat yang sama pentingnya untuk tidak tertukar:
     *   - nilai sumber (dari setoran kolektor & pembayaran manual) TURUNAN,
     *     tak pernah disimpan di sini;
     *   - `declared_amount` / `difference` = catatan hasil pemeriksaan.
     *
     * `depositor_id` & `pop_id` NULLABLE khusus untuk satu baris sentinel
     * titik nol (`status = saldo_awal`) yang tidak dimiliki siapa pun.
     *
     * docs/plan/kolektor/analisa-setoran-kas-admin.md §4.1, §7.
     */
    public function up(): void
    {
        Schema::create('cash_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('deposit_number')->unique();

            $table->foreignId('depositor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pop_id')->nullable()->constrained('pops')->nullOnDelete();

            $table->string('status', 30);

            $table->decimal('declared_amount', 12, 2)->default(0);
            $table->decimal('difference', 12, 2)->default(0);
            $table->text('note')->nullable();

            // Ke mana uangnya diserahkan. Pertanyaan pertama Owner setiap kali
            // membuka halaman ini, jadi disimpan eksplisit — bukan diselipkan
            // ke dalam `note` yang tak bisa difilter maupun dijumlahkan.
            $table->string('channel', 30)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('reference_no', 100)->nullable();
            // Disimpan di disk `local` (privat). Bukti transfer memuat nomor
            // rekening perusahaan — tidak boleh dilayani lewat URL publik yang
            // bisa ditebak, sama seperti lampiran tiket.
            $table->string('proof_path')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('written_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('written_off_at')->nullable();
            $table->text('write_off_reason')->nullable();

            // Jaring pengaman submit ganda (klik dobel / retry jaringan) —
            // pola sama dengan `collector_deposits`.
            $table->string('idempotency_key')->nullable()->unique();

            $table->timestamps();

            $table->index(['depositor_id', 'status']);
            $table->index(['pop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_deposits');
    }
};
