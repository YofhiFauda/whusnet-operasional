<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sambungan fisik dari PON/Port Switch ke tujuan (ODP, port lain, atau
 * label bebas untuk tujuan yang belum didata). TERPISAH dari parent/lokasi
 * (§3). Ditulis hanya oleh `InfrastructureConnectionService` — pelepasan
 * mengisi `released_at` dan mengosongkan `active_key`, baris tidak dihapus.
 */
#[Fillable([
    'source_infrastructure_port_id',
    'target_network_node_id',
    'target_infrastructure_port_id',
    'target_label',
    'verification_status',
    'active_key',
    'created_by',
    'verified_by',
    'verified_at',
    'released_by',
    'released_at',
    'release_note',
    'notes',
])]
class InfrastructureConnection extends Model
{
    public const VERIFIED = 'terverifikasi';

    public const UNVERIFIED = 'belum_terverifikasi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_infrastructure_port_id' => 'integer',
            'target_network_node_id' => 'integer',
            'target_infrastructure_port_id' => 'integer',
            'active_key' => 'integer',
            'verified_by' => 'integer',
            'verified_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<InfrastructureConnection>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active_key', 1);
    }

    /**
     * @param  Builder<InfrastructureConnection>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->where('verification_status', self::VERIFIED);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFIED;
    }

    /**
     * Nama kolom source/target_infrastructure_port_id bersifat historis: kini berisi
     * id node bertipe Port Switch / PON di network_nodes.
     *
     * @return BelongsTo<NetworkNode, $this>
     */
    public function sourcePort(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'source_infrastructure_port_id');
    }

    /**
     * @return BelongsTo<NetworkNode, $this>
     */
    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'target_network_node_id');
    }

    /**
     * @return BelongsTo<NetworkNode, $this>
     */
    public function targetPort(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'target_infrastructure_port_id');
    }
}
