<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Master data Agent (mitra akuisisi pelanggan) — BUKAN akun login,
        // Agent tidak pernah masuk sistem. Diinput/dikelola Business
        // Development, dipakai sebagai pilihan dropdown saat Busdev
        // mendaftarkan pelanggan atas nama Agent (Skema 3, 2026-09-12).
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
