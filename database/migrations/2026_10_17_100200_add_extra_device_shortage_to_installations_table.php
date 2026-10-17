<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalur kekurangan perangkat tambahan di Laporan Pemasangan (ADHOC-190
     * task 4, spec §4.2). Kolom sendiri, bukan diselipkan ke teks
     * installation_note, supaya FOP/Gudang bisa query laporan yang perlu
     * ditindaklanjuti tanpa parsing teks bebas.
     */
    public function up(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->boolean('extra_device_shortage')->default(false);
            $table->string('extra_device_shortage_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropColumn(['extra_device_shortage', 'extra_device_shortage_reason']);
        });
    }
};
