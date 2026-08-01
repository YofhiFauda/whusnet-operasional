<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom lama cuma DATE — jam registrasi legacy (mis. 15:34:22 WIB)
        // kepotong jadi tengah malam. Ganti ke DATETIME biar timeline "Ringkasan
        // Waktu & Penanggung Jawab" bisa nampilin jam:menit:detik yang benar.
        Schema::table('customers', function (Blueprint $table) {
            $table->dateTime('registration_date')->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('registration_date')->change();
        });
    }
};
