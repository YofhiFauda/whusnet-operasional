<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master Kategori Pendapatan (ADHOC-60).
     *
     * Sebelum ini "jenis pendapatan" hidup sebagai KOLOM di `invoices`
     * (`prorate_amount`, `extra_cable_fee`, `extra_installation_fee`,
     * `extra_pole_fee`, `other_fee`). Konsekuensinya jenis jasa baru — "Ganti
     * ONT", "Pindah Tiang" — butuh migrasi + deploy, dan laporan tidak bisa
     * memecah pendapatan per jasa karena tidak ada dimensinya.
     *
     * Kategori sengaja TIDAK bisa ditambah/dihapus admin (semua `is_system`):
     * tiap code punya perilaku khusus di kode — `jasa_layanan_internet`
     * menandai baris langganan yang nominalnya diambil dari
     * `customer_services` dan menentukan tagihan kena guard "satu tagihan
     * langganan per periode", `lainnya` menandai baris bernama ketikan bebas.
     * Kategori kelima yang dibuat admin tidak akan punya perilaku apa pun,
     * cuma kelihatan seperti punya. Yang bebas ditambah admin adalah SUB
     * kategori — lihat migrasi berikutnya.
     *
     * Seperti `item_categories`, `code` yang jadi kontrak — bukan `id` —
     * karena dipakai konstanta di `RevenueCategory` dan dibaca
     * `ManualInvoiceService` serta `InvoiceItemBuilder`.
     */
    public function up(): void
    {
        Schema::create('revenue_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Ditanam di migrasi, bukan seeder: migrasi berikutnya
        // (revenue_subcategories) mem-FK ke baris ini lewat pencocokan `code`,
        // dan seeder baru jalan setelah SELURUH migrasi selesai.
        //
        // Daftar ditulis literal, tidak membaca konstanta model — migrasi yang
        // bergantung pada kode aplikasi berubah artinya kalau konstantanya
        // nanti disunting, padahal migrasi lama harus tetap menghasilkan
        // bentuk DB yang sama.
        $now = now();
        $defaults = [
            ['code' => 'jasa_layanan_internet', 'name' => 'Jasa Layanan Internet', 'description' => 'Langganan internet bulanan & prorata.'],
            ['code' => 'jasa_instalasi', 'name' => 'Jasa Instalasi', 'description' => 'Biaya aktivasi / registrasi pemasangan baru.'],
            ['code' => 'jasa_perbaikan', 'name' => 'Jasa Perbaikan', 'description' => 'Tambah kabel, pindah lokasi, dan pekerjaan lapangan berbayar lain.'],
            ['code' => 'lainnya', 'name' => 'Lainnya', 'description' => 'Pendapatan di luar tiga kategori di atas — nama kategorinya diketik saat membuat tagihan.'],
        ];

        DB::table('revenue_categories')->insert(
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
        Schema::dropIfExists('revenue_categories');
    }
};
