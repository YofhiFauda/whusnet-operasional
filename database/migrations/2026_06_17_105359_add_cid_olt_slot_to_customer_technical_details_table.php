<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration ini sebelumnya kosong — dijaga kosong karena olt_number dan
     * olt_slot sudah ditambahkan di migration 2026_06_17_104310.
     * cid sudah ada di migration customers table sebelumnya.
     * Tidak ada kolom baru yang perlu ditambahkan di sini.
     */
    public function up(): void
    {
        // No-op: kolom sudah ditambahkan di migration sebelumnya
    }

    public function down(): void
    {
        // No-op
    }
};
