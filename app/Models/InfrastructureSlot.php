<?php

namespace App\Models;

use App\Enums\InfrastructureCapacityUnit;
use App\Enums\InfrastructureRecordStatus;
use App\Enums\InfrastructureSlotKind;
use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Posisi Slot di dalam OLT/Switch (§2). Identitasnya = FK perangkat + nomor;
 * `code`/`label` hanya label tampilan, BUKAN dasar join. Slot tetap ada
 * walau kartu fisiknya dicabut/diganti — isi slot dibaca dari aset Gudang
 * yang terpasang (`InventorySerial::current_infrastructure_slot_id`).
 *
 * Buat & ubah lewat `InfrastructurePositionService` — guard jenis perangkat
 * dan keunikan ada di sana, bukan di model.
 */
#[Fillable([
    'network_node_id',
    'number',
    'kind',
    'code',
    'label',
    'is_logical',
    'status',
    'capacity',
    'capacity_unit',
    'legacy_olt_slot_id',
    'notes',
])]
class InfrastructureSlot extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Slot Infrastruktur';

    protected array $auditEvents = ['created', 'updated'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'network_node_id' => 'integer',
            'number' => 'integer',
            'kind' => InfrastructureSlotKind::class,
            'is_logical' => 'boolean',
            'status' => InfrastructureRecordStatus::class,
            'capacity' => 'integer',
            'capacity_unit' => InfrastructureCapacityUnit::class,
        ];
    }

    /**
     * @return BelongsTo<NetworkNode, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'network_node_id');
    }

    /**
     * @return HasMany<InfrastructurePort, $this>
     */
    public function ports(): HasMany
    {
        return $this->hasMany(InfrastructurePort::class)->orderBy('number');
    }

    /**
     * @return BelongsTo<OltSlot, $this>
     */
    public function legacyOltSlot(): BelongsTo
    {
        return $this->belongsTo(OltSlot::class, 'legacy_olt_slot_id');
    }

    /**
     * @return HasMany<InventorySerial, $this>
     */
    public function deployedSerials(): HasMany
    {
        return $this->hasMany(InventorySerial::class, 'current_infrastructure_slot_id');
    }

    public function displayName(): string
    {
        return $this->label !== null && $this->label !== ''
            ? "Slot {$this->number} ({$this->label})"
            : "Slot {$this->number}";
    }
}
