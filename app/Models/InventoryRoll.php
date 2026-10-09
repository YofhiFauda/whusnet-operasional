<?php

namespace App\Models;

use App\Enums\RollStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris per roll kabel fisik (App\Enums\TrackingType::ROLL). Sejalan
 * `InventorySerial` (identitas per-unit + lokasi/custody) tapi numerik —
 * `length_remaining` berkurang sebagian-sebagian lewat
 * `InventoryService::consumeFromRoll()`, bukan transisi atomik seperti
 * INSTALLED di SerialStatus. `status` SATU-SATUNYA acuan (`RollStatus`).
 *
 * `current_pop_id`/`current_technician_id` — cuma SATU yang relevan
 * tergantung `status`, ditegakkan Service, BUKAN model ini atau DB
 * constraint (pola sama InventorySerial).
 *
 * `unit_price_snapshot` SELALU harga PER METER — walau staf beli/input di
 * form Receive dalam harga PER ROLL ("Harga Beli per Roll"). Konversi
 * (÷ `meter_per_roll`) terjadi SEKALI di `InventoryReceiveService::receiveRoll()`,
 * satu-satunya titik masuk roll ke sistem — WAJIB tetap per-meter di sini
 * karena SEMUA qty hilir (`InventoryTransaction`, `TaskMaterial` pas
 * `consumeFromRoll()`) satuannya meter, bukan roll. Kalau nyimpen mentah
 * harga-per-roll di sini, tiap kalkulasi nilai (qty-meter × harga) kekali
 * `meter_per_roll` (bug nyata, ketauan 2026-09-18 — 1.000x lipat di kasus
 * 1.000 meter/roll). Dokumen KE LUAR yang emang perlu satuan roll
 * (Invoice) konversi BALIK ke per-roll di titik presentasi
 * (`WarehouseTransferController::invoice()`), bukan di sini.
 */
#[Fillable([
    'item_id',
    'roll_code',
    'vendor',
    'length_total',
    'length_remaining',
    'unit_price_snapshot',
    'status',
    'current_pop_id',
    'current_technician_id',
    'issued_from_pop_id',
    'received_at',
])]
class InventoryRoll extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RollStatus::class,
            'length_total' => 'decimal:2',
            'length_remaining' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function currentPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'current_pop_id');
    }

    public function currentTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_technician_id');
    }

    public function issuedFromPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'issued_from_pop_id');
    }

    public function scopeStatus($query, RollStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopeActive($query)
    {
        return $query->where('length_remaining', '>', 0);
    }

    public function isDepleted(): bool
    {
        return (float) $this->length_remaining <= 0.0;
    }

    /**
     * "Sisa Kecil" (docs/plan/warehouse/analisa-gap-roll-kabel.md §8) —
     * cuma berarti kalau `item.minimum_length` diisi (sama prinsip
     * `InventoryBalance::isLowStock()`) DAN roll masih idle/dipegang aktif
     * (AVAILABLE/RECEIVED/ISSUED/IN_USE) dengan sisa > 0. Roll DEPLETED/
     * DAMAGED/LOST/SCRAPPED/QUARANTINE/TRANSFERRED sengaja TIDAK ikut
     * di-flag — itu udah status final/transisi, "sisa kecil nganggur" cuma
     * relevan buat roll yang masih bisa dipakai tapi keburu numpuk.
     */
    public function isLowRemaining(): bool
    {
        if ($this->item === null || $this->item->minimum_length === null) {
            return false;
        }

        $eligibleStatuses = [RollStatus::AVAILABLE, RollStatus::RECEIVED, RollStatus::ISSUED, RollStatus::IN_USE];

        return in_array($this->status, $eligibleStatuses, true)
            && (float) $this->length_remaining > 0.0
            && (float) $this->length_remaining < (float) $this->item->minimum_length;
    }

    /**
     * Query-level padanan `isLowRemaining()` — join ke `items` karena
     * ambangnya nempel di master barang, bukan kolom `inventory_rolls`
     * sendiri (beda dari `InventoryBalance::scopeLowStock()` yang
     * `whereColumn` di tabel yang sama).
     */
    public function scopeLowRemaining($query)
    {
        return $query
            ->join('items', 'items.id', '=', 'inventory_rolls.item_id')
            ->whereNotNull('items.minimum_length')
            ->where('inventory_rolls.length_remaining', '>', 0)
            ->whereColumn('inventory_rolls.length_remaining', '<', 'items.minimum_length')
            ->whereIn('inventory_rolls.status', [
                RollStatus::AVAILABLE->value,
                RollStatus::RECEIVED->value,
                RollStatus::ISSUED->value,
                RollStatus::IN_USE->value,
            ])
            ->select('inventory_rolls.*');
    }
}
