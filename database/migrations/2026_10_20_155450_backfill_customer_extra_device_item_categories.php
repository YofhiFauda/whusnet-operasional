<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrasi penanda AP/STB yang sebelumnya hanya diisi oleh seeder.
     * Instalasi yang menjalankan migrate tanpa db:seed tetap dapat mengenali
     * perangkat tambahan yang sudah di-issue ke teknisi.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('item_categories')
                ->where('code', 'access_point')
                ->update(['is_customer_extra_device' => true]);

            $setTopBox = DB::table('item_categories')->where('code', 'stb');

            if ($setTopBox->exists()) {
                $setTopBox->update(['is_customer_extra_device' => true]);

                return;
            }

            DB::table('item_categories')->insert([
                'code' => 'stb',
                'name' => 'Set Top Box (STB)',
                'default_unit' => 'pcs',
                'equipment_class' => 'aktif',
                'is_customer_extra_device' => true,
                'is_active' => true,
                'sort_order' => 48,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Nilai lama penanda AP/STB tidak tersimpan. Membaliknya akan menyembunyikan
     * perangkat yang sudah dicatat dan berisiko merusak laporan pemasangan.
     * Rollback aman mempertahankan data; koreksi berikutnya memakai migrasi maju.
     */
    public function down(): void
    {
        // Data kategori dipertahankan.
    }
};
