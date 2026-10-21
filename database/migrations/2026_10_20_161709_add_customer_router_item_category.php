<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $category = DB::table('item_categories')->where('code', 'customer_router');

            if ($category->exists()) {
                $category->update(['is_customer_extra_device' => true]);

                return;
            }

            DB::table('item_categories')->insert([
                'code' => 'customer_router',
                'name' => 'Router Pelanggan',
                'default_unit' => 'pcs',
                'equipment_class' => 'aktif',
                'is_customer_extra_device' => true,
                'is_active' => true,
                'sort_order' => 49,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Pertahankan kategori agar perangkat yang sudah terpasang tetap terlacak.
    }
};
