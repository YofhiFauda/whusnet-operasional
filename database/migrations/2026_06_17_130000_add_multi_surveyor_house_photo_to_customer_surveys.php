<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah field multi-petugas survey dan foto rumah ke customer_surveys.
     *
     * Gap yang ditutup:
     * - surveyor_2_id, surveyor_3_id: petugas survey ke-2 dan ke-3 (relasi ke users)
     * - house_photo: foto rumah pelanggan (terpisah dari foto ODP/survey lapangan)
     */
    public function up(): void
    {
        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->foreignId('surveyor_2_id')->nullable()->after('technician_id')
                ->constrained('users')->onDelete('set null');

            $table->foreignId('surveyor_3_id')->nullable()->after('surveyor_2_id')
                ->constrained('users')->onDelete('set null');

            $table->string('house_photo')->nullable()->after('survey_photo');
        });
    }

    public function down(): void
    {
        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->dropForeign(['surveyor_2_id']);
            $table->dropForeign(['surveyor_3_id']);
            $table->dropColumn(['surveyor_2_id', 'surveyor_3_id', 'house_photo']);
        });
    }
};
