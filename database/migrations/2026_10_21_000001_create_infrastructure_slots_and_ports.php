<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Model posisi kanonis Slot dan Port (rancangan hierarki §2, §12, §16.2).
     *
     * ADITIF: `olt_slots`, `network_node_ports`, dan node `pon_port`/`olt_card`
     * lama TIDAK disentuh — keduanya tetap terbaca untuk histori. Pemetaan
     * slot lama ke tabel baru dikerjakan migration koneksi/mapping (Task 3)
     * lewat `legacy_olt_slot_id`, bukan di sini.
     *
     * Kenapa tabel baru, bukan memperluas `olt_slots`: slot sekarang juga
     * milik Switch dan wajib punya port sendiri (PON/Port Switch) dengan
     * kapasitas bersatuan. `network_node_ports` tetap khusus port ODP/node
     * lama — mencampur PON ke sana bikin kapasitas PON (pelanggan aktif) dan
     * kapasitas ODP (port pelanggan) jadi satu kolom tanpa satuan (§12).
     */
    public function up(): void
    {
        Schema::create('infrastructure_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_node_id')->constrained('network_nodes')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('kind', 20);
            $table->string('code', 50)->nullable();
            $table->string('label', 100)->nullable();
            $table->boolean('is_logical')->default(false);
            $table->string('status', 30)->default('belum_didata');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('capacity_unit', 30)->nullable();
            $table->foreignId('legacy_olt_slot_id')->nullable()->unique()->constrained('olt_slots')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['network_node_id', 'number']);
            $table->unique(['network_node_id', 'code']);
        });

        Schema::create('infrastructure_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('infrastructure_slot_id')->constrained('infrastructure_slots')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('kind', 20);
            $table->string('code', 50)->nullable();
            $table->string('status', 30)->default('belum_didata');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('capacity_unit', 30)->nullable();
            $table->unsignedTinyInteger('red_zone_threshold')->nullable();
            $table->foreignId('legacy_network_node_id')->nullable()->unique()->constrained('network_nodes')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['infrastructure_slot_id', 'number']);
            $table->unique(['infrastructure_slot_id', 'code']);
        });

        // Tipe Switch operasional baru (§16.2). insertOrIgnore + cek exists
        // supaya DB lama (tipe sudah ada dari seeder) dan DB baru berakhir
        // di baris yang sama. Aturan anchor Mini POP ditulis di sini karena
        // NetworkNodeTypeSeeder hanya menulis aturan untuk tipe yang BARU ia
        // buat — tanpa ini DB lama punya tipe tanpa aturan placement.
        if (! DB::table('network_node_types')->where('code', 'switch_mini_pop')->exists()) {
            DB::table('network_node_types')->insert([
                'code' => 'switch_mini_pop',
                'name' => 'Switch Mini POP',
                'has_ports' => true,
                'monitors_capacity' => false,
                'max_ports' => null,
                'red_zone_threshold' => null,
                'is_active' => true,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $switchTypeId = DB::table('network_node_types')->where('code', 'switch_mini_pop')->value('id');
        DB::table('network_node_type_rules')->insertOrIgnore([
            'child_type_id' => $switchTypeId,
            'anchor_pop_type' => 'mini_pop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // aggregation_switch tetap ada (histori/audit lama — pola sama
        // `TicketHistoryAction::dicek_noc`), hanya berhenti ditawarkan di
        // form karena NetworkNodeController menolak tipe nonaktif.
        DB::table('network_node_types')->where('code', 'aggregation_switch')->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_ports');
        Schema::dropIfExists('infrastructure_slots');

        DB::table('network_node_types')->where('code', 'aggregation_switch')->update(['is_active' => true]);
        $switchTypeId = DB::table('network_node_types')->where('code', 'switch_mini_pop')->value('id');
        if ($switchTypeId !== null && ! DB::table('network_nodes')->where('node_type_id', $switchTypeId)->exists()) {
            DB::table('network_node_type_rules')->where('child_type_id', $switchTypeId)->delete();
            DB::table('network_node_types')->where('id', $switchTypeId)->delete();
        }
    }
};
