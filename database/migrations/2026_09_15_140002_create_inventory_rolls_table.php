<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris per roll kabel fisik (App\Enums\TrackingType::ROLL) — sejalan
     * `inventory_serials` (identitas per-unit + lokasi/custody), TAPI numerik
     * bukan biner: roll gak pernah "terpasang" atomik, dia habis dipotong
     * sedikit-sedikit sampai `length_remaining` = 0. `status` pakai
     * App\Enums\RollStatus — vocabulary terpisah dari SerialStatus (§ alasan
     * sama seperti kenapa CustodyStatus terpisah dari SerialStatus: sifat
     * lifecycle beda, unit roll bisa "sebagian dipakai").
     *
     * `roll_code` unique GLOBAL, format `{item.code}-{YYYYMMDD}-{6 digit}` —
     * digenerate sistem saat Receive (App\Services\InventoryReceiveService::
     * receiveRoll()), BUKAN diinput staf (beda dari serial_number yang datang
     * dari vendor).
     *
     * `vendor` per-roll (bukan kolom items) — vendor kabel bisa beda tiap
     * kedatangan barang walau barangnya sama.
     *
     * `length_total` = snapshot `items.meter_per_roll` saat roll digenerate.
     * `length_remaining` berkurang tiap InventoryService::consumeFromRoll()
     * dipanggil dari Laporan Pemasangan/Maintenance — TIDAK menulis baris
     * inventory_transactions (pola sama TechnicianCustody::qty_remaining,
     * lihat InventoryService::consumeFromCustody()), cukup TaskMaterial.
     *
     * `current_pop_id`/`current_technician_id` — cuma SATU yang relevan
     * tergantung `status`, ditegakkan Service bukan DB constraint (pola sama
     * inventory_serials).
     */
    public function up(): void
    {
        Schema::create('inventory_rolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('roll_code', 40)->unique();
            $table->string('vendor', 150)->nullable();

            $table->decimal('length_total', 10, 2);
            $table->decimal('length_remaining', 10, 2);
            $table->decimal('unit_price_snapshot', 12, 2)->nullable();

            $table->string('status', 20)->default('received');

            $table->foreignId('current_pop_id')->nullable()->constrained('pops')->restrictOnDelete();
            $table->foreignId('current_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_from_pop_id')->nullable()->constrained('pops')->nullOnDelete();

            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('current_pop_id');
            $table->index('current_technician_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_rolls');
    }
};
