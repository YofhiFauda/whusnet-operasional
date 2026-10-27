<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['olt_chassis' => ['Chassis OLT', false], 'olt_card' => ['Kartu OLT', true]] as $code => [$name, $hasPorts]) {
            if (! DB::table('network_node_types')->where('code', $code)->exists()) {
                DB::table('network_node_types')->insert([
                    'code' => $code,
                    'name' => $name,
                    'has_ports' => $hasPorts,
                    'max_ports' => $code === 'olt_card' ? 128 : null,
                    'monitors_capacity' => false,
                    'is_active' => true,
                    'sort_order' => $code === 'olt_chassis' ? 35 : 40,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $typeId = DB::table('network_node_types')->where('code', $code)->value('id');
            if (DB::table('network_node_type_rules')->where('child_type_id', $typeId)->exists()) {
                continue;
            }

            // Migration ini jalan SEBELUM backfill 140545 bisa mengisi aturan
            // untuk dua tipe ini (tipenya belum ada waktu 140545 jalan di DB
            // lama) — jadi aturan final chassis/kartu SEBENARNYA terbentuk di
            // sini, bukan di 140545. Chassis OLT boleh anchor Mini POP ATAU
            // Cabang langsung (Mini POP = lokasi fisik, bisa menaungi
            // beberapa chassis). Kartu OLT wajib jadi anak chassis — bukan
            // anchor POP langsung — karena satu chassis bisa punya banyak
            // kartu.
            if ($code === 'olt_chassis') {
                foreach (['mini_pop', 'cabang'] as $popType) {
                    DB::table('network_node_type_rules')->insert([
                        'child_type_id' => $typeId,
                        'anchor_pop_type' => $popType,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                continue;
            }

            $chassisTypeId = DB::table('network_node_types')->where('code', 'olt_chassis')->value('id');
            DB::table('network_node_type_rules')->insert([
                'child_type_id' => $typeId,
                'parent_type_id' => $chassisTypeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('items', function (Blueprint $table): void {
            $table->foreignId('infrastructure_node_type_id')->nullable()
                ->constrained('network_node_types')->restrictOnDelete();
        });

        foreach (['OLT-ZTE-C320' => 'olt_chassis', 'OLT-MODULE-GPON-8P' => 'olt_card'] as $itemCode => $typeCode) {
            DB::table('items')->where('code', $itemCode)->whereNull('infrastructure_node_type_id')
                ->update(['infrastructure_node_type_id' => DB::table('network_node_types')->where('code', $typeCode)->value('id')]);
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('infrastructure_node_type_id');
        });
    }
};
