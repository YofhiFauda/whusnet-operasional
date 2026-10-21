<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Satu baris aksi laporan O-REQ/INFR-REQ. Lokasi kerja tepat: tepat satu dari
 * `work_pop_id` (POP/Mini POP), `network_node_id`, `work_infrastructure_slot_id`,
 * `work_infrastructure_port_id` — atau semuanya null untuk inspeksi O-REQ
 * nonjaringan. Ditulis hanya oleh `TaskInfrastructureActionService`.
 */
class TaskInfrastructureAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id', 'fop_task_id', 'action_key', 'action_type', 'network_node_id',
        'work_pop_id', 'work_infrastructure_slot_id', 'work_infrastructure_port_id',
        'work_latitude', 'work_longitude', 'work_location_snapshot',
        'end_from_pop_id', 'end_from_network_node_id', 'end_from_infrastructure_port_id',
        'end_to_pop_id', 'end_to_network_node_id', 'end_to_infrastructure_port_id',
        'mutation_hash', 'mutation', 'scope_snapshot', 'result_note', 'performed_by', 'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'mutation' => 'array',
            'scope_snapshot' => 'array',
            'work_location_snapshot' => 'array',
            'performed_at' => 'datetime',
            'network_node_id' => 'integer',
            'work_pop_id' => 'integer',
            'work_infrastructure_slot_id' => 'integer',
            'work_infrastructure_port_id' => 'integer',
            'work_latitude' => 'decimal:7',
            'work_longitude' => 'decimal:7',
            'end_from_pop_id' => 'integer',
            'end_from_network_node_id' => 'integer',
            'end_from_infrastructure_port_id' => 'integer',
            'end_to_pop_id' => 'integer',
            'end_to_network_node_id' => 'integer',
            'end_to_infrastructure_port_id' => 'integer',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'network_node_id');
    }

    public function workPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'work_pop_id');
    }

    public function workSlot(): BelongsTo
    {
        return $this->belongsTo(InfrastructureSlot::class, 'work_infrastructure_slot_id');
    }

    public function workPort(): BelongsTo
    {
        return $this->belongsTo(InfrastructurePort::class, 'work_infrastructure_port_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(InventoryTransaction::class, 'task_infrastructure_action_transactions');
    }

    /**
     * Breadcrumb lokasi kerja saat aksi disimpan (snapshot), fallback ke node
     * untuk baris lama sebelum snapshot ada.
     */
    public function workLocationLabel(): ?string
    {
        return $this->work_location_snapshot['breadcrumb'] ?? $this->node?->breadcrumbLabel();
    }
}
