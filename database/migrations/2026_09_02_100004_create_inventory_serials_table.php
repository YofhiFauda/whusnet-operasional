<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris per unit fisik barang SERIALIZED (modem, ONT, router, OTDR).
     * `status` pakai App\Enums\SerialStatus — SATU-SATUNYA acuan status buat
     * tabel ini (§16.6 doc advanced), jangan bikin status lain di tempat lain.
     *
     * `current_pop_id`/`current_technician_id`/`customer_id` — cuma SATU yang
     * relevan tergantung `status` (gudang/custody teknisi/terpasang di
     * pelanggan). Konsistensi "cuma satu yang keisi sesuai status" ditegakkan
     * Service/Observer, BUKAN DB constraint (dropdown status × lokasi terlalu
     * banyak kombinasi buat CHECK constraint yang portable ke SQLite & MySQL).
     *
     * `customer_id` NUNJUK ke pelanggan, TIDAK menyalin field device
     * (`router_or_ont_serial` dkk tetap tinggal di `customer_technical_details`
     * sebagai sumber kebenaran instalasi — §29.3 analisa pertama). Kolom ini
     * cuma buat traceability "SN ini sekarang di pelanggan mana".
     *
     * `serial_number` unique GLOBAL (bukan per-gudang/per-item) — identitas
     * fisik barang, satu SN gak mungkin muncul dua kali di seluruh sistem.
     */
    public function up(): void
    {
        Schema::create('inventory_serials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('serial_number', 100)->unique();
            $table->string('mac_address', 50)->nullable();

            $table->string('status', 20)->default('received');

            $table->foreignId('current_pop_id')->nullable()->constrained('pops')->restrictOnDelete();
            $table->foreignId('current_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('fop_task_id')->nullable()->constrained('fop_tasks')->nullOnDelete();

            $table->timestamp('installed_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('current_pop_id');
            $table->index('current_technician_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_serials');
    }
};
