<?php

namespace App\Models;

use App\Enums\InfrastructureCapacityUnit;
use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureRecordStatus;
use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PON (milik Slot OLT) atau Port Switch (milik Slot Switch). Identitas =
 * FK slot + nomor. Kapasitas PON diisi manual bersatuan pelanggan aktif
 * (§16.3) — `NetworkNode::isInRedZone()` dan node `pon_port` lama BUKAN
 * sumber rumusnya. Perhitungan pemakaian ada di `PonCapacityService`.
 */
#[Fillable([
    'infrastructure_slot_id',
    'number',
    'kind',
    'code',
    'status',
    'capacity',
    'capacity_unit',
    'red_zone_threshold',
    'legacy_network_node_id',
    'notes',
])]
class InfrastructurePort extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Port Infrastruktur';

    protected array $auditEvents = ['created', 'updated'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'infrastructure_slot_id' => 'integer',
            'number' => 'integer',
            'kind' => InfrastructurePortKind::class,
            'status' => InfrastructureRecordStatus::class,
            'capacity' => 'integer',
            'capacity_unit' => InfrastructureCapacityUnit::class,
            'red_zone_threshold' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<InfrastructureSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(InfrastructureSlot::class, 'infrastructure_slot_id');
    }

    /**
     * @return BelongsTo<NetworkNode, $this>
     */
    public function legacyNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'legacy_network_node_id');
    }

    /**
     * @return HasMany<InventorySerial, $this>
     */
    public function deployedSerials(): HasMany
    {
        return $this->hasMany(InventorySerial::class, 'current_infrastructure_port_id');
    }

    public function displayName(): string
    {
        return $this->kind === InfrastructurePortKind::PON
            ? "PON {$this->number}"
            : "Port {$this->number}";
    }
}
