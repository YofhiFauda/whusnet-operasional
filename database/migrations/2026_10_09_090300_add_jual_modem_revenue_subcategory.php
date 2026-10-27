<?php

use App\Models\RevenueCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sub kategori "Jual Modem" (ownership modem sewa/beli, ADHOC-2026-10-09)
     * — baris tagihan untuk nominal "Beli dari Gudang" yang dirakit
     * `InitialInvoiceService::lineSpecs()` saat pelanggan membeli modem alih-alih
     * menyewa.
     *
     * Numpang kategori `jasa_perbaikan` yang sudah ada (bukan kategori baru):
     * sifatnya sama dengan `tambah_kabel`/`tambah_tiang` — biaya tambahan
     * sekali-jalan di luar langganan & instalasi standar. `is_system = false`
     * karena tidak dirujuk konstanta kode di manapun (beda dari `prorata`/
     * `biaya_aktivasi`) — mengikuti pola baris `tambah_kabel`/`pindah_lokasi`/
     * `tambah_tiang` yang sudah ada di
     * `2026_09_09_100002_create_revenue_subcategories_table.php`.
     */
    public function up(): void
    {
        $categoryId = DB::table('revenue_categories')
            ->where('code', RevenueCategory::CODE_JASA_PERBAIKAN)
            ->value('id');

        $maxSortOrder = (int) DB::table('revenue_subcategories')
            ->where('revenue_category_id', $categoryId)
            ->max('sort_order');

        DB::table('revenue_subcategories')->insert([
            'revenue_category_id' => $categoryId,
            'code' => 'jual_modem',
            'name' => 'Jual Modem',
            'default_amount' => null,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $maxSortOrder + 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('revenue_subcategories')->where('code', 'jual_modem')->delete();
    }
};
