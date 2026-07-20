<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * customer_code (nomor registrasi, basis CID/PPPoE) sebelumnya unique
     * global. Legacy request-number (RQ...) dari tiap cabang lama mulai
     * hitung dari 1 sendiri-sendiri — bukan unik lintas cabang — jadi begitu
     * 2+ cabang digabung ke 1 database, angka yang sama bisa muncul di 2
     * pelanggan beda cabang dan salah satunya kepaksa dapat nomor pengganti.
     *
     * CID lengkap ({pop.cid_prefix}{customer_code}...) sebenarnya sudah
     * unik secara alami karena tiap cabang punya cid_prefix sendiri —
     * yang perlu unik cuma customer_code DALAM satu cabang yang sama, bukan
     * global. Ganti constraint jadi composite (pop_id, customer_code) supaya
     * nomor legacy asli bisa dipertahankan apa adanya lintas cabang.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_customer_code_unique');
            $table->unique(['pop_id', 'customer_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['pop_id', 'customer_code']);
            $table->unique('customer_code');
        });
    }
};
