<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pointer draft "roll kabel mana + berapa meter dipakai teknisi" — sejalan
     * `selected_inventory_serial_id` (pointer DRAFT, aman resubmit di
     * storePemasangan()), aksi konsumsi sungguhan
     * (InventoryService::consumeFromRoll()) baru jalan di storeSpeedtest().
     * Nullable — instalasi tanpa kabel ke-track Inventory tetap jalan biasa.
     */
    public function up(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->foreignId('selected_inventory_roll_id')->nullable()->after('selected_inventory_serial_id')
                ->constrained('inventory_rolls')->nullOnDelete();
            $table->decimal('roll_meters_used', 10, 2)->nullable()->after('selected_inventory_roll_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropColumn('roll_meters_used');
            $table->dropConstrainedForeignId('selected_inventory_roll_id');
        });
    }
};
