<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan task Ambil Modem (DEAC), ADHOC-86. Tabel sendiri (bukan numpang
     * `task_maintenances`) karena form DEAC tidak punya kendala teknis / foto
     * OPM / speedtest — memaksa kolom itu terisi berarti data palsu.
     *
     * SN yang dibawa TIDAK disimpan di sini: sumber kebenarannya
     * `inventory_serials` + ledger `inventory_transactions` (RETURN dengan
     * `fop_task_id`), supaya tidak ada dua salinan yang bisa menyimpang.
     */
    public function up(): void
    {
        Schema::create('task_device_retrievals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->unique()->constrained('tasks')->cascadeOnDelete();
            $table->string('outcome', 20);
            $table->string('condition_photo')->nullable();
            $table->json('accessories')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_device_retrievals');
    }
};
