<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konversi Roll→Meter tetap di master barang (App\Enums\TrackingType::ROLL)
     * — bukan diinput ulang tiap Receive. Di-snapshot ke
     * `inventory_rolls.length_total` saat roll digenerate, supaya perubahan
     * nilai ini belakangan gak nyeret ubah roll yang sudah terlanjur dicetak.
     * Nullable — cuma relevan buat item tracking_type=roll.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('meter_per_roll', 10, 2)->nullable()->after('equipment_class_override');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('meter_per_roll');
        });
    }
};
