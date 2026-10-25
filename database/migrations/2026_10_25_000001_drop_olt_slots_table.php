<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuang `olt_slots` (ADHOC-213 Task 14). Slot OLT kini baris
     * `network_nodes` bertipe `olt_slot` (anak chassis lewat `parent_id`), dan
     * konsep "kartu OLT dipasang ke slot" (`card_node_id`) sudah tidak ada.
     *
     * Aman dibuang karena satu-satunya FK MASUK ke tabel ini
     * (`infrastructure_slots.legacy_olt_slot_id`) ikut hilang saat
     * 2026_10_24_000002 men-drop `infrastructure_slots`. FK KELUAR
     * (`chassis_node_id`, `card_node_id` → `network_nodes`) lenyap bersama
     * tabelnya, tidak menyentuh `network_nodes`. `dropIfExists` dipakai supaya
     * DB yang tabelnya memang sudah tidak ada (mis. dibangun ulang manual)
     * tidak membuat migrate gagal. Isi tabel adalah data kartu lama yang
     * sudah dipetakan ke node Slot oleh Task sebelumnya; tidak ada pembaca
     * yang tersisa di kode.
     */
    public function up(): void
    {
        Schema::dropIfExists('olt_slots');
    }

    /**
     * Hanya mengembalikan STRUKTUR (kosong) — isi kartu lama tidak bisa
     * dipulihkan, sama seperti 2026_10_24_000002.
     */
    public function down(): void
    {
        Schema::create('olt_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chassis_node_id')->constrained('network_nodes')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->foreignId('card_node_id')->nullable()->unique()->constrained('network_nodes')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['chassis_node_id', 'number']);
        });
    }
};
