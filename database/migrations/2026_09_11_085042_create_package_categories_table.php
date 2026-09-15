<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master Kategori Paket Internet.
     *
     * Sebelumnya empat kategori ini hardcode di `InternetPackage::CATEGORIES`
     * (const array) — nambah kategori baru berarti ubah kode + deploy. Beda
     * dengan `item_categories`/`revenue_categories`, kolom `internet_packages.
     * category` TIDAK punya kontrak `code`: nilainya string nama kategori itu
     * sendiri, jadi tabel ini cukup `name` sebagai kunci tampilan tanpa perlu
     * migrasi ulang kolom `category` di `internet_packages`.
     *
     * Sengaja tanpa delete (ikut pola master lain) — paket lama yang masih
     * merujuk nama kategori tertentu harus tetap terbaca. Yang tidak dipakai
     * lagi dinonaktifkan lewat `is_active`.
     */
    public function up(): void
    {
        Schema::create('package_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Empat kategori bawaan ditanam di sini supaya data existing di
        // internet_packages.category tetap punya padanan di master ini
        // begitu migrasi selesai — bukan cuma tersedia setelah seeder jalan.
        $now = now();
        $defaults = [
            'Paket Home Broadband',
            'Paket Bisnis Broadband',
            'Paket Bisnis UKM',
            'Paket Bisnis Dedicated',
        ];

        DB::table('package_categories')->insert(
            collect($defaults)->values()->map(fn (string $name, int $index) => [
                'name' => $name,
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('package_categories');
    }
};
