<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah field teknisi ke-2 dan ke-3 ke customer_installations.
     *
     * Gap yang ditutup:
     * - technician_2_id: teknisi pemasangan ke-2 (relasi ke users)
     * - technician_3_id: teknisi pemasangan ke-3 (relasi ke users)
     */
    public function up(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->foreignId('technician_2_id')->nullable()->after('technician_id')
                ->constrained('users')->onDelete('set null');

            $table->foreignId('technician_3_id')->nullable()->after('technician_2_id')
                ->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropForeign(['technician_2_id']);
            $table->dropForeign(['technician_3_id']);
            $table->dropColumn(['technician_2_id', 'technician_3_id']);
        });
    }
};
