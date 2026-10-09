<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Draft pointer "cara pemilikan modem" (rancangan ownership modem
     * sewa/beli, 2026-10-09, task 4) untuk Laporan Pemasangan (PSB) — sama
     * pola `selected_inventory_serial_id` existing: diisi berkali-kali aman
     * di `storePemasangan()` (resubmittable), eksekusi sungguhan
     * (`installSerial()`/`recordCustomerOwnedDevice()`/`recordSerialSale()`)
     * baru jalan SEKALI di `storeSpeedtest()`.
     *
     * Hanya relevan kalau `customer.customerService.contract_type === 'beli'`
     * — pelanggan `sewa` tidak pernah mengisi kolom-kolom ini, selamanya
     * null (sama seperti `selected_inventory_serial_id` untuk instalasi yang
     * tidak ke-track Inventory).
     */
    public function up(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->string('modem_acquisition_choice')->nullable()->after('roll_meters_used');
            $table->string('manual_device_brand', 100)->nullable()->after('modem_acquisition_choice');
            $table->string('manual_device_model', 100)->nullable()->after('manual_device_brand');
            $table->string('manual_device_serial_number', 100)->nullable()->after('manual_device_model');
            $table->decimal('modem_sale_price', 15, 2)->nullable()->after('manual_device_serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropColumn([
                'modem_acquisition_choice',
                'manual_device_brand',
                'manual_device_model',
                'manual_device_serial_number',
                'modem_sale_price',
            ]);
        });
    }
};
