<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->text('full_address')->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('village', 100)->nullable();

            // Relational region keys
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('village_id')->nullable()->constrained('villages')->nullOnDelete();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('house_photo')->nullable();
            $table->string('ktp_photo')->nullable();
            $table->string('contract_photo')->nullable();
            $table->timestamps();
        });

        // Copy existing address data from customers to customer_addresses
        $customers = DB::table('customers')->get();
        foreach ($customers as $customer) {
            // Get region names if available to populate text columns
            $cityName = null;
            if (isset($customer->city_id)) {
                $cityName = DB::table('cities')->where('id', $customer->city_id)->value('name');
            }
            $districtName = null;
            if (isset($customer->district_id)) {
                $districtName = DB::table('districts')->where('id', $customer->district_id)->value('name');
            }
            $villageName = null;
            if (isset($customer->village_id)) {
                $villageName = DB::table('villages')->where('id', $customer->village_id)->value('name');
            }

            DB::table('customer_addresses')->insert([
                'customer_id' => $customer->id,
                'full_address' => $customer->address ?? null,
                'province' => 'Jawa Timur', // Default province for Ponorogo/Madiun
                'city' => $cityName,
                'district' => $districtName,
                'village' => $villageName,
                'city_id' => $customer->city_id ?? null,
                'district_id' => $customer->district_id ?? null,
                'village_id' => $customer->village_id ?? null,
                'latitude' => $customer->latitude ?? null,
                'longitude' => $customer->longitude ?? null,
                'house_photo' => $customer->foto_rumah ?? null,
                'ktp_photo' => $customer->foto_ktp ?? null,
                'contract_photo' => $customer->foto_kontrak ?? null,
                'created_at' => $customer->created_at ?? now(),
                'updated_at' => $customer->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
