<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengganti MAX+1 di PaymentController::generatePaymentNumber() (dulu
     * `orderBy('payment_number','desc')->lockForUpdate()->first()`, yang tak
     * mengunci apa pun kalau periode masih kosong — phantom read klasik saat
     * dua request pertama di bulan itu jalan bersamaan). Pola sama
     * `PopSequence`/`Pop::generateRegistrationNumber()`: kunci baris counter
     * yang SELALU ada (bukan MAX dari tabel target), baru increment.
     *
     * `current_number` unsigned tanpa batas atas — lebar digit format
     * `PAY-{periode}-%0Nd` dinaikkan otomatis kalau lewat 9999 (bukan
     * dipatok statis ke 6 digit), jadi payment_number lama tetap 4 digit
     * dan kompatibel.
     *
     * docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §A-7 #5,
     * §C-2(b).
     */
    public function up(): void
    {
        Schema::create('payment_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('period_code', 6)->unique();
            $table->unsignedInteger('current_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_number_sequences');
    }
};
