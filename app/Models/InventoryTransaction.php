<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger APPEND-ONLY — satu-satunya sumber histori inventory. `inventory_balances`/
 * `technician_custody`/`inventory_serials` adalah proyeksi yang DITURUNKAN dari
 * tabel ini, bukan sebaliknya (§25 warehouse_inventory_asset_traceability_analysis.md).
 *
 * TIDAK BOLEH di-`update()`/`delete()` siapa pun termasuk owner. Guard-nya
 * BELUM dipasang di model ini — itu tugas `InventoryTransactionObserver`
 * (Fase Observer berikutnya). Model ini SENGAJA cuma struktur data dulu,
 * jangan dianggap sudah aman dari edit sebelum Observer-nya benar-benar ada.
 *
 * Kombinasi kolom `from_*`/`to_*`/`reason`/`notes` yang valid beda-beda per
 * `type` — lihat komentar lengkap di migration `create_inventory_transactions_table`.
 * Validasi kombinasi itu tugas Service, bukan model ini.
 */
#[Fillable([
    'type',
    'reference_number',
    'inventory_transfer_id',
    'item_id',
    'lot_no',
    'serial_id',
    'roll_id',
    'qty',
    'unit_price_snapshot',
    'from_pop_id',
    'to_pop_id',
    'from_network_node_id',
    'to_network_node_id',
    'from_mini_pop_id',
    'to_mini_pop_id',
    'from_infrastructure_pop_id',
    'to_infrastructure_pop_id',
    'from_infrastructure_slot_id',
    'to_infrastructure_slot_id',
    'from_infrastructure_port_id',
    'to_infrastructure_port_id',
    'from_technician_id',
    'to_technician_id',
    'fop_task_id',
    'reason',
    'notes',
    'evidence_file_path',
    'resulting_status',
    'created_by',
    'source_inventory_transaction_id',
    'source_custody_id',
    'action_key',
    'action_fingerprint',
])]
class InventoryTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InventoryTransactionType::class,
            'qty' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'from_network_node_id' => 'integer',
            'to_network_node_id' => 'integer',
            'from_mini_pop_id' => 'integer',
            'to_mini_pop_id' => 'integer',
            'from_infrastructure_pop_id' => 'integer',
            'to_infrastructure_pop_id' => 'integer',
            'from_infrastructure_slot_id' => 'integer',
            'to_infrastructure_slot_id' => 'integer',
            'from_infrastructure_port_id' => 'integer',
            'to_infrastructure_port_id' => 'integer',
            'to_pop_id' => 'integer',
            'from_pop_id' => 'integer',
        ];
    }

    public function fromMiniPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'from_mini_pop_id');
    }

    public function toMiniPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'to_mini_pop_id');
    }

    public function fromInfrastructurePop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'from_infrastructure_pop_id');
    }

    public function toInfrastructurePop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'to_infrastructure_pop_id');
    }

    // Nama kolom *_slot_id/*_port_id bersifat historis: kini berisi id node bertipe
    // Slot / PON / Port Switch di network_nodes (FK ke network_nodes.id).
    public function fromInfrastructureSlot(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'from_infrastructure_slot_id');
    }

    public function toInfrastructureSlot(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'to_infrastructure_slot_id');
    }

    public function fromInfrastructurePort(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'from_infrastructure_port_id');
    }

    public function toInfrastructurePort(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'to_infrastructure_port_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'serial_id');
    }

    public function roll(): BelongsTo
    {
        return $this->belongsTo(InventoryRoll::class, 'roll_id');
    }

    public function fromPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'from_pop_id');
    }

    public function toPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'to_pop_id');
    }

    public function fromNetworkNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'from_network_node_id');
    }

    public function toNetworkNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'to_network_node_id');
    }

    public function fromTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_technician_id');
    }

    public function toTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_technician_id');
    }

    public function fopTask(): BelongsTo
    {
        return $this->belongsTo(FopTask::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeType($query, InventoryTransactionType $type)
    {
        return $query->where('type', $type->value);
    }
}
