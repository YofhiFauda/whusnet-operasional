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
        // Tingkat kesulitan dulu cuma ditempel ke survey_note ("Tingkat Kesulitan: X").
        // Sekarang punya kolom sendiri supaya bisa diedit & dibaca tanpa parsing teks.
        // Baris lama tetap punya nilai di survey_note — CustomerSurvey::difficultyAndNote()
        // membaca kolom dulu, lalu fallback ke format teks lama.
        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->string('difficulty_level', 10)->nullable()->after('survey_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->dropColumn('difficulty_level');
        });
    }
};
