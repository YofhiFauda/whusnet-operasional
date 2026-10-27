<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuang `olt_slots` (ADHOC-213 Task 14). Slot OLT kini baris
     * `network_nodes` bertipe `olt_slot` (anak chassis lewat `parent_id`), dan
     * konsep "kartu OLT dipasang ke slot" (`card_node_id`) sudah tidak ada.
     *
     * Isi tabel ini TIDAK dipetakan ke node Slot oleh migrasi mana pun —
     * pemetaan legacy dipensiunkan (spec §12 D4). Jadi membuangnya berarti
     * catatan "kartu X di slot n chassis Y" hilang permanen. Karena itu,
     * sama seperti 2026_10_24_000002, migrasi MENOLAK jalan di luar env
     * `testing` bila tabel masih berisi baris, kecuali operator menyalakan
     * saklar `infrastructure.confirm_dummy_reset` (env
     * INFRA_CONFIRM_DUMMY_RESET=true) — menyatakan sendiri bahwa datanya
     * dummy. Tabel kosong / tidak ada lolos tanpa saklar.
     *
     * Satu-satunya FK MASUK (`infrastructure_slots.legacy_olt_slot_id`) sudah
     * hilang saat 2026_10_24_000002 men-drop `infrastructure_slots`; FK KELUAR
     * (`chassis_node_id`, `card_node_id` → `network_nodes`) lenyap bersama
     * tabelnya tanpa menyentuh `network_nodes`.
     */
    public function up(): void
    {
        if (Schema::hasTable('olt_slots')) {
            $this->assertConfirmed(DB::table('olt_slots')->count());
        }

        Schema::dropIfExists('olt_slots');
    }

    /**
     * `=== true`, bukan truthy (string "false" dari env mentah tidak lolos);
     * dibaca dari config(), bukan env(), supaya benar saat config di-cache.
     */
    public function assertConfirmed(int $rows): void
    {
        if ($rows === 0
            || app()->environment('testing')
            || config('infrastructure.confirm_dummy_reset') === true) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Migrasi drop olt_slots DITOLAK di env "%s": tabel masih berisi %d baris data kartu/slot OLT lama yang tidak dipetakan ke node Slot '
            .'dan akan HILANG PERMANEN. Jalankan hanya bila database target terbukti dummy: set INFRA_CONFIRM_DUMMY_RESET=true '
            .'(config infrastructure.confirm_dummy_reset; jalankan php artisan config:clear bila config di-cache), lalu ulangi migrate.',
            app()->environment(),
            $rows,
        ));
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
