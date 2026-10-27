<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('network_node_ports')
            ->where('is_used', true)
            ->orderBy('id')
            ->chunkById(500, function ($ports): void {
                foreach ($ports as $port) {
                    if (DB::table('network_port_assignments')
                        ->where('network_node_port_id', $port->id)
                        ->where('active_key', 1)
                        ->exists()) {
                        continue;
                    }

                    DB::table('network_port_assignments')->insert([
                        'network_node_port_id' => $port->id,
                        'manual_label' => 'Terpakai (data lama; perlu verifikasi)',
                        'active_key' => 1,
                        'assigned_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Riwayat penempatan yang sudah diverifikasi setelah migrasi tidak aman dihapus otomatis.
    }
};
