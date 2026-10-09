<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sub Kategori Pendapatan (ADHOC-60) — inilah lapis yang BEBAS ditambah
     * admin. Kategori induknya tetap empat dan terkunci; jenis jasa yang
     * berubah di lapangan ("Ganti ONT", "Pindah Tiang") masuk ke sini tanpa
     * deploy.
     *
     * `restrictOnDelete` ke kategori disengaja: kategori memang tidak pernah
     * dihapus (semua `is_system`), jadi constraint ini murni jaring pengaman
     * kalau ada yang menghapusnya lewat SQL langsung.
     *
     * `default_amount` opsional — tarif standar yang mengisi otomatis kolom
     * nominal di form tagihan. Tetap bisa ditimpa admin per tagihan, karena
     * "Tambah Kabel" 20 meter dan 200 meter jelas beda harga.
     *
     * Kategori `lainnya` SENGAJA tidak diberi sub kategori: seluruh gunanya
     * adalah menampung nama yang diketik bebas saat membuat tagihan. Kalau
     * dikasih sub bawaan, admin akan memilih sub itu dan kolom ketikan bebasnya
     * jadi tidak pernah kepakai.
     */
    public function up(): void
    {
        Schema::create('revenue_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revenue_category_id')->constrained('revenue_categories')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->decimal('default_amount', 12, 2)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Nama index ditulis eksplisit: nama bawaan Laravel
            // (`revenue_subcategories_revenue_category_id_is_active_sort_order_index`)
            // 67 karakter, lewat batas 64 karakter identifier MySQL dan
            // bikin migrasi gagal.
            $table->index(['revenue_category_id', 'is_active', 'sort_order'], 'revenue_subcat_cat_active_sort_idx');
        });

        $categoryIds = DB::table('revenue_categories')->pluck('id', 'code');

        // `is_system` di sini berarti "dirujuk kode", bukan sekadar bawaan:
        // `langganan_bulanan`, `prorata`, dan `biaya_aktivasi` dirakit otomatis
        // oleh InvoiceItemBuilder (dipakai InitialInvoiceService,
        // GenerateMonthlyInvoicesCommand, dan BackfillInvoiceItemsCommand),
        // jadi code-nya kontrak dan tidak boleh dinamai ulang admin.
        //
        // Tiga sisanya sengaja BUKAN is_system — itu cuma contoh isi awal dari
        // permintaan user, dan admin berhak menamai ulang atau menonaktifkannya
        // tanpa merusak apa pun.
        $now = now();
        $defaults = [
            ['category' => 'jasa_layanan_internet', 'code' => 'langganan_bulanan', 'name' => 'Langganan Bulanan', 'is_system' => true],
            ['category' => 'jasa_layanan_internet', 'code' => 'prorata', 'name' => 'Prorata', 'is_system' => true],
            ['category' => 'jasa_instalasi', 'code' => 'biaya_aktivasi', 'name' => 'Biaya Aktivasi / Registrasi', 'is_system' => true],
            ['category' => 'jasa_perbaikan', 'code' => 'tambah_kabel', 'name' => 'Tambah Kabel', 'is_system' => false],
            ['category' => 'jasa_perbaikan', 'code' => 'pindah_lokasi', 'name' => 'Pindah Lokasi', 'is_system' => false],
            ['category' => 'jasa_perbaikan', 'code' => 'tambah_tiang', 'name' => 'Tambah Tiang', 'is_system' => false],
        ];

        DB::table('revenue_subcategories')->insert(
            collect($defaults)->values()->map(fn (array $row, int $index) => [
                'revenue_category_id' => $categoryIds[$row['category']],
                'code' => $row['code'],
                'name' => $row['name'],
                'default_amount' => null,
                'is_system' => $row['is_system'],
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_subcategories');
    }
};
