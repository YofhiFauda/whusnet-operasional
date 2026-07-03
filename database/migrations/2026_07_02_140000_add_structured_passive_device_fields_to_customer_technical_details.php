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
        Schema::table('customer_technical_details', function (Blueprint $table) {
            $table->string('passive_device_type', 50)->nullable()->after('passive_device');
            $table->string('passive_device_qty', 50)->nullable()->after('passive_device_type');
            $table->string('passive_device_note', 255)->nullable()->after('passive_device_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_technical_details', function (Blueprint $table) {
            $table->dropColumn(['passive_device_type', 'passive_device_qty', 'passive_device_note']);
        });
    }
};
