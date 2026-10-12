<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Master Tipe Node Jaringan — katalog jenis simpul di pohon `network_nodes`
 * (Core Router, Backbone, Agregasi POP, PON Port, ODC, ODP, dst). Flag
 * `has_ports`/`monitors_capacity`/`max_ports`/`red_zone_threshold` menentukan
 * perilaku node turunannya (apakah punya daftar port, apakah dipantau
 * kapasitasnya) — dipakai `NetworkNode` (Task 4) dan `getOdpSummary()`/
 * `addPorts()` (Task 7).
 */
#[Fillable([
    'code',
    'name',
    'has_ports',
    'monitors_capacity',
    'max_ports',
    'red_zone_threshold',
    'is_active',
    'sort_order',
])]
class NetworkNodeType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_ports' => 'boolean',
            'monitors_capacity' => 'boolean',
            'max_ports' => 'integer',
            'red_zone_threshold' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
