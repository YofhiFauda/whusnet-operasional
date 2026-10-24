<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string> kode tipe baru => nama
     */
    private const TYPES = [
        'olt_slot' => 'Slot OLT',
        'olt_pon' => 'PON',
        'switch_slot' => 'Slot Switch',
        'switch_port' => 'Port Switch',
    ];

    /**
     * Aturan induk: anak => induk.
     *
     * @var array<string, string>
     */
    private const PARENT_RULES = [
        'olt_slot' => 'olt_chassis',
        'olt_pon' => 'olt_slot',
        'switch_slot' => 'switch_mini_pop',
        'switch_port' => 'switch_slot',
        'odp' => 'olt_pon',
    ];

    /**
     * Slot, PON, dan Port Switch menjadi baris `network_nodes` (ADHOC-213).
     *
     * Kolom posisi nullable supaya node lama (ODP, chassis, dst) tidak
     * tersentuh. `record_status` kolom BARU — `status` tetap 'active'/
     * 'inactive' (aktif tidaknya baris), sedangkan `record_status` status
     * pendataan (belum_didata, dst); mencampurnya merusak filter lama.
     *
     * Aturan anchor ODP ke Cabang/Mini POP dicabut: hanya menutup penempatan
     * BARU (ODP wajib di bawah PON). Baris `network_nodes` lama TIDAK diubah.
     */
    public function up(): void
    {
        Schema::table('network_nodes', function (Blueprint $table) {
            $table->unsignedSmallInteger('number')->nullable()->after('code');
            $table->string('capacity_unit', 30)->nullable();
            $table->boolean('is_logical')->default(false);
            $table->string('record_status', 30)->nullable();
            $table->unsignedTinyInteger('red_zone_threshold')->nullable();
        });

        // Kode posisi bersusun (mis. kode OLT + slot + PON) lebih panjang dari 50.
        // Unique index lama dijatuhkan lalu dipasang ulang eksplisit supaya
        // hasilnya sama di sqlite (rebuild tabel) maupun mysql/pgsql.
        Schema::table('network_nodes', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
        Schema::table('network_nodes', function (Blueprint $table) {
            $table->string('code', 120)->change();
        });
        Schema::table('network_nodes', function (Blueprint $table) {
            $table->unique('code');
            // Nomor unik per induk (bukan global): dua OLT boleh sama-sama punya slot 1.
            $table->unique(['parent_id', 'number'], 'network_nodes_parent_number_unique');
        });

        $sort = (int) DB::table('network_node_types')->max('sort_order');
        foreach (self::TYPES as $code => $name) {
            if (! DB::table('network_node_types')->where('code', $code)->exists()) {
                DB::table('network_node_types')->insert([
                    'code' => $code,
                    'name' => $name,
                    'has_ports' => false,
                    'monitors_capacity' => false,
                    'max_ports' => null,
                    'red_zone_threshold' => null,
                    'is_active' => true,
                    'sort_order' => ++$sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $ids = DB::table('network_node_types')->pluck('id', 'code');
        foreach (self::PARENT_RULES as $child => $parent) {
            if (! isset($ids[$child], $ids[$parent])) {
                continue;
            }
            DB::table('network_node_type_rules')->insertOrIgnore([
                'child_type_id' => $ids[$child],
                'parent_type_id' => $ids[$parent],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (isset($ids['odp'])) {
            DB::table('network_node_type_rules')
                ->where('child_type_id', $ids['odp'])
                ->whereIn('anchor_pop_type', ['cabang', 'mini_pop'])
                ->delete();
        }
    }

    public function down(): void
    {
        // `code` sengaja TIDAK disusutkan kembali ke 50: kode posisi yang lebih panjang bisa terpotong/gagal.
        $ids = DB::table('network_node_types')->pluck('id', 'code');

        foreach (self::PARENT_RULES as $child => $parent) {
            if (isset($ids[$child], $ids[$parent])) {
                DB::table('network_node_type_rules')
                    ->where('child_type_id', $ids[$child])
                    ->where('parent_type_id', $ids[$parent])
                    ->delete();
            }
        }

        if (isset($ids['odp'])) {
            foreach (['cabang', 'mini_pop'] as $anchor) {
                DB::table('network_node_type_rules')->insertOrIgnore([
                    'child_type_id' => $ids['odp'],
                    'anchor_pop_type' => $anchor,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (array_keys(self::TYPES) as $code) {
            if (isset($ids[$code]) && ! DB::table('network_nodes')->where('node_type_id', $ids[$code])->exists()) {
                DB::table('network_node_type_rules')->where('child_type_id', $ids[$code])->delete();
                DB::table('network_node_types')->where('id', $ids[$code])->delete();
            }
        }

        Schema::table('network_nodes', function (Blueprint $table) {
            $table->dropUnique('network_nodes_parent_number_unique');
        });
        Schema::table('network_nodes', function (Blueprint $table) {
            $table->dropColumn(['number', 'capacity_unit', 'is_logical', 'record_status', 'red_zone_threshold']);
        });
    }
};
