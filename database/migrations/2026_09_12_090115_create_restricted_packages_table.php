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
        // Daftar paket GLOBAL yang boleh dipilih role ber-
        // `roles.is_package_restricted = true` (Skema 1, 2026-09-12).
        // Satu daftar dipakai bareng semua role restricted (keputusan user
        // — bukan per-role terpisah), dikelola Business Development lewat
        // permission `package_restrictions.update`.
        Schema::create('restricted_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->unique()->constrained('internet_packages')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restricted_packages');
    }
};
