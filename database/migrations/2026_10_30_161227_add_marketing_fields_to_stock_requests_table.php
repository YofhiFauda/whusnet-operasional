<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->string('request_kind', 20)->default('operational')->index();
            $table->foreignId('inventory_transfer_id')->nullable()->unique()->constrained('inventory_transfers')->restrictOnDelete();
            $table->uuid('request_key')->nullable()->unique();
        });

        DB::table('item_categories')->insertOrIgnore([
            'code' => 'merchandise_marketing',
            'name' => 'Merchandise Marketing',
            'default_unit' => 'pcs',
            'equipment_class' => 'pasif',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_transfer_id');
            $table->dropColumn(['request_kind', 'request_key']);
        });
    }
};
