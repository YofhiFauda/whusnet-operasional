<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemakaian material per task — estimasi (dari Laporan Survey) dan realisasi
     * (dari Laporan Pemasangan) dalam satu tabel.
     *
     * Ini adalah tabel `fop_task_materials` yang direncanakan
     * docs/post-mvp/inventory-fop.md, dibangun lebih awal dengan bentuk final
     * supaya modul Inventory nanti cuma menambah (master stok, pergerakan,
     * dashboard biaya) — bukan mengubah bentuk tabel atau UI-nya.
     */
    public function up(): void
    {
        Schema::create('task_materials', function (Blueprint $table) {
            $table->id();

            // Anchor ke FopTask, bukan ke customer_installations: FopTask satu-satunya
            // entitas yang dimiliki SEMUA jenis pekerjaan (SRV, PSB, MTN, C-REQ, O-REQ).
            // Inventory butuh "pemakaian material per task", bukan per instalasi saja.
            $table->foreignId('fop_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('kind', 20);

            // Nullable HANYA untuk kasus "lainnya" — barang yang belum terdaftar di
            // master dan gak boleh menghalangi teknisi menyelesaikan laporan di
            // lapangan. Kalau di-required-kan, teknisi akan memaksakan item yang
            // salah demi bisa submit, dan datanya justru lebih kotor daripada null.
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();

            // Snapshot tipe & nama. Redundan terhadap items, dan itu disengaja:
            // laporan yang sudah tersimpan gak boleh berubah isinya kalau master
            // di-rename atau di-nonaktifkan belakangan.
            $table->string('item_type', 50);
            $table->string('item_name', 150);

            // decimal, bukan integer — kabel dihitung meter dan bisa pecahan.
            $table->decimal('qty', 10, 2);
            $table->string('unit', 20);

            // Snapshot harga saat dipakai. Kosong sampai Inventory ada. Snapshot,
            // bukan join ke master, supaya histori biaya gak ikut berubah waktu
            // harga item naik.
            $table->decimal('unit_price_snapshot', 12, 2)->nullable();

            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fop_task_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_materials');
    }
};
