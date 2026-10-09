<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rincian baris tagihan (ADHOC-60).
     *
     * Sebelumnya satu tagihan = satu layanan + kolom biaya tetap, sehingga
     * "Langganan Bulanan + Tambah Kabel + Denda" tidak bisa diwakili dalam
     * satu tagihan dan laporan tidak punya dimensi kategori pendapatan.
     *
     * INVARIAN yang dijaga `InvoiceItemBuilder` dan
     * `InvoiceItemSubtotalInvariantTest`:
     *
     *     SUM(invoice_items.amount) == invoices.subtotal
     *
     * Baris di sini adalah komponen SUBTOTAL (DPP — sebelum diskon & PPN).
     * Diskon dan PPN tetap di level tagihan dan TIDAK dipecah per baris,
     * karena PPN bukan pendapatan melainkan titipan pajak. Percobaan pertama
     * (ADHOC-58, di-rewind) menempelkan PPN hanya ke bagian langganan sehingga
     * `SUM(baris) ≠ total_amount` — angka laporan per kategori jadi tidak bisa
     * direkonsiliasi dengan total tagihan, dan tidak ada satu pun assert yang
     * bisa menjaganya. Jangan diulang.
     *
     * Kolom biaya lama di `invoices` SENGAJA tidak dihapus: data lama dan
     * jalur tagihan AWAL masih menulisnya, dan halaman detail tagihan jatuh
     * balik ke kolom itu kalau sebuah tagihan belum punya baris.
     * `total_amount` tetap kolom otoritatif untuk nominal yang ditagihkan.
     *
     * Dua kolom snapshot nama disimpan berdampingan dengan FK-nya, alasan sama
     * dengan `customer_services.package_name_snapshot`: master boleh dinamai
     * ulang atau dinonaktifkan, dan tagihan yang sudah terbit harus tetap
     * terbaca persis seperti waktu diterbitkan. Laporan agregat tetap group by
     * `revenue_category_id` yang stabil; snapshot hanya untuk tampilan.
     *
     * `revenue_subcategory_id` nullable KHUSUS kategori `lainnya` — di situ
     * nama kategorinya diketik admin dan disimpan di
     * `subcategory_name_snapshot`, tanpa baris master apa pun.
     *
     * `restrictOnDelete` ke master menegakkan aturan "master pendapatan tidak
     * pernah dihapus, cuma dinonaktifkan" di lapis DB — controller-nya memang
     * tidak menyediakan aksi hapus, ini jaring untuk jalur lain (tinker, SQL).
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('revenue_category_id')->constrained('revenue_categories')->restrictOnDelete();
            $table->foreignId('revenue_subcategory_id')->nullable()->constrained('revenue_subcategories')->restrictOnDelete();
            $table->string('category_name_snapshot', 100);
            $table->string('subcategory_name_snapshot', 100);
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
            // Laporan pendapatan per kategori memfilter periode lewat join ke
            // invoices, tapi group by-nya di kolom ini.
            $table->index('revenue_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
