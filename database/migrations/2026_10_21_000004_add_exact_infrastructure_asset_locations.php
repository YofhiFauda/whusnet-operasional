<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lokasi akhir tepat untuk aset berserial (§16.1). ADITIF — kolom lama
     * `current_pop_id` (Gudang) / `current_network_node_id` (node) tetap
     * dengan makna yang sama. Mini POP SENGAJA kolom baru, bukan menumpang
     * `current_pop_id`/`to_pop_id`: keduanya sudah bermakna Gudang dan
     * snapshot Cabang — memakainya untuk DEPLOYED membuat keadaan Gudang dan
     * terpasang tidak bisa dibedakan (§11.2).
     *
     * Eksklusivitas (tepat satu dari Gudang/custody/pelanggan/posisi jaringan)
     * ditegakkan `InventoryService`, bukan constraint DB — sama seperti kolom
     * lokasi lama.
     */
    public function up(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->foreignId('current_mini_pop_id')->nullable()->after('current_network_node_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('current_infrastructure_slot_id')->nullable()->after('current_mini_pop_id')->constrained('infrastructure_slots')->restrictOnDelete();
            $table->foreignId('current_infrastructure_port_id')->nullable()->after('current_infrastructure_slot_id')->constrained('infrastructure_ports')->restrictOnDelete();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('from_mini_pop_id')->nullable()->after('to_network_node_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('to_mini_pop_id')->nullable()->after('from_mini_pop_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('from_infrastructure_slot_id')->nullable()->after('to_mini_pop_id')->constrained('infrastructure_slots')->restrictOnDelete();
            $table->foreignId('to_infrastructure_slot_id')->nullable()->after('from_infrastructure_slot_id')->constrained('infrastructure_slots')->restrictOnDelete();
            $table->foreignId('from_infrastructure_port_id')->nullable()->after('to_infrastructure_slot_id')->constrained('infrastructure_ports')->restrictOnDelete();
            $table->foreignId('to_infrastructure_port_id')->nullable()->after('from_infrastructure_port_id')->constrained('infrastructure_ports')->restrictOnDelete();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('infrastructure_location_kind', 20)->nullable()->after('infrastructure_node_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('infrastructure_location_kind');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            foreach (['from_mini_pop_id', 'to_mini_pop_id', 'from_infrastructure_slot_id', 'to_infrastructure_slot_id', 'from_infrastructure_port_id', 'to_infrastructure_port_id'] as $column) {
                $table->dropForeign([$column]);
            }
            $table->dropColumn(['from_mini_pop_id', 'to_mini_pop_id', 'from_infrastructure_slot_id', 'to_infrastructure_slot_id', 'from_infrastructure_port_id', 'to_infrastructure_port_id']);
        });

        Schema::table('inventory_serials', function (Blueprint $table) {
            foreach (['current_mini_pop_id', 'current_infrastructure_slot_id', 'current_infrastructure_port_id'] as $column) {
                $table->dropForeign([$column]);
            }
            $table->dropColumn(['current_mini_pop_id', 'current_infrastructure_slot_id', 'current_infrastructure_port_id']);
        });
    }
};
