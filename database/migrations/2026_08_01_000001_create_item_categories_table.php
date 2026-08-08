<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master kategori barang/material.
     *
     * Sebelumnya kategori hidup sebagai enum `MaterialType` — nambah kategori
     * berarti ubah kode + deploy, padahal jenis perangkat pasif di lapangan
     * berubah lebih cepat daripada siklus rilis. Kategori dipindah ke tabel
     * supaya admin bisa menambah sendiri.
     *
     * `code` yang jadi kunci pemakaian, BUKAN `id`. Alasannya: `task_materials`
     * dan `customer_technical_details.passive_device_type` menyimpan kategori
     * sebagai string snapshot, dan snapshot itu harus tetap terbaca walau baris
     * masternya dihapus/dinonaktifkan. Tujuh kategori bawaan memakai code yang
     * PERSIS sama dengan value enum lama supaya data existing tidak perlu
     * ditulis ulang.
     *
     * `is_system` mengunci tujuh bawaan itu dari penghapusan & perubahan code —
     * `CustomerSurveyController` merakit baris dropcore otomatis dengan code
     * `kabel_dropcore` yang di-hardcode, jadi code itu memang kontrak, bukan
     * sekadar data.
     */
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);

            // Satuan default — dipakai form buat auto-isi kolom satuan begitu
            // kategori dipilih, biar teknisi gak salah tulis "pcs" untuk kabel.
            // Dulu ini match() di MaterialType::defaultUnit().
            $table->string('default_unit', 20)->default('pcs');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Tujuh bawaan ditanam DI SINI, bukan di seeder: migrasi berikutnya
        // mem-backfill item_category_id dengan mencocokkan code, dan seeder baru
        // jalan setelah seluruh migrasi selesai. Kalau baris ini di seeder,
        // backfill-nya no-op dan semua data lama kehilangan kategori.
        //
        // Daftarnya sengaja ditulis literal, tidak membaca enum MaterialType —
        // migrasi yang bergantung pada kode aplikasi akan berubah artinya kalau
        // enum-nya nanti disunting, padahal migrasi lama harus tetap
        // menghasilkan bentuk DB yang sama.
        $now = now();
        $defaults = [
            ['code' => 'splitter_odp', 'name' => 'Splitter / ODP', 'default_unit' => 'pcs'],
            ['code' => 'kabel_dropcore', 'name' => 'Kabel Dropcore', 'default_unit' => 'meter'],
            ['code' => 'patch_cord', 'name' => 'Patch Cord', 'default_unit' => 'pcs'],
            ['code' => 'media_converter', 'name' => 'Media Converter', 'default_unit' => 'pcs'],
            ['code' => 'antena_radio', 'name' => 'Antena / Radio', 'default_unit' => 'pcs'],
            ['code' => 'aksesoris_pasang', 'name' => 'Aksesoris Pemasangan', 'default_unit' => 'pcs'],
            ['code' => 'lainnya', 'name' => 'Lainnya', 'default_unit' => 'pcs'],
        ];

        DB::table('item_categories')->insert(
            collect($defaults)->values()->map(fn (array $row, int $index) => $row + [
                'is_system' => true,
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('item_categories');
    }
};
