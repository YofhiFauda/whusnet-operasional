<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo barang qty NON-kabel terpasang kini bisa berada di POP/Mini POP,
     * node, Slot, atau Port (§10.1, Task 7 "qty non-kabel tetap memakai aturan
     * saldo jenisnya"). Tepat satu kolom lokasi terisi per baris — ditegakkan
     * `InventoryService`. `network_node_id` jadi nullable; baris lama tetap
     * berarti saldo di node.
     *
     * Kabel (roll) TIDAK lagi punya saldo terpasang di sini (§16.4 #2) — saldo
     * kabel hanya sisa roll.
     */
    public function up(): void
    {
        Schema::table('infrastructure_balances', function (Blueprint $table) {
            $table->foreignId('network_node_id')->nullable()->change();
            $table->foreignId('pop_id')->nullable()->after('network_node_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('infrastructure_slot_id')->nullable()->after('pop_id')->constrained('infrastructure_slots')->restrictOnDelete();
            $table->foreignId('infrastructure_port_id')->nullable()->after('infrastructure_slot_id')->constrained('infrastructure_ports')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('infrastructure_balances')->whereNull('network_node_id')->where('qty', '>', 0)->exists()) {
            throw new RuntimeException('Masih ada saldo infrastruktur di POP/Slot/Port. Rekonsiliasi dahulu sebelum rollback.');
        }
        DB::table('infrastructure_balances')->whereNull('network_node_id')->delete();

        Schema::table('infrastructure_balances', function (Blueprint $table) {
            $table->dropForeign(['infrastructure_port_id']);
            $table->dropForeign(['infrastructure_slot_id']);
            $table->dropForeign(['pop_id']);
            $table->dropColumn(['pop_id', 'infrastructure_slot_id', 'infrastructure_port_id']);
            $table->foreignId('network_node_id')->nullable(false)->change();
        });
    }
};
