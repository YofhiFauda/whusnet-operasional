<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Port generik milik sebuah `NetworkNode` (ODC/ODP/splitter/dst). Nomor port
 * unik per node — ditegakkan unique index `(network_node_id, number)`, bukan
 * hanya validasi aplikasi, supaya jalur import/tinker juga tidak bisa
 * menduplikasi nomor.
 */
#[Fillable([
    'network_node_id',
    'number',
    'is_used',
])]
class NetworkNodePort extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'network_node_id' => 'integer',
            'number' => 'integer',
            'is_used' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<NetworkNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'network_node_id');
    }
}
