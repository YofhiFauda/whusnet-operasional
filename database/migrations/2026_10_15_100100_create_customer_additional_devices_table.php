<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perangkat tambahan pelanggan di luar perangkat utama (AP, STB, dll.).
     * Sengaja tidak memuat field PPPOE/ODP/VLAN/WiFi/sinyal — itu hanya milik
     * perangkat utama (customer_devices).
     */
    public function up(): void
    {
        Schema::create('customer_additional_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('device_type');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('mac_address')->nullable();
            $table->text('technical_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_additional_devices');
    }
};
