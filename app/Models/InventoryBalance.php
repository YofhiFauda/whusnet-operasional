<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stok gudang SAAT INI — proyeksi yang DITURUNKAN dari `inventory_transactions`
 * (ledger), bukan sumber kebenaran sendiri. Jangan pernah di-`update()` qty-nya
 * langsung dari Controller — itu tugas Service yang JUGA menulis baris ledger
 * di transaksi yang sama (§25 warehouse_inventory_asset_traceability_analysis.md).
 *
 * `pop_id` menunjuk `pops` bertipe `pusat`/`cabang` — TIDAK PERNAH `mini_pop`.
 * Ditegakkan lewat `Pop::scopeWarehouse()` di titik penulisan (Service), bukan
 * FK/CHECK constraint DB (migration gak bisa cek `pops.type` lintas tabel).
 *
 * `lot_no` default string kosong (BUKAN nullable) untuk item non-BATCH, biar
 * constraint unique (pop_id, item_id, lot_no) tetap menjaga "satu baris per
 * gudang+barang" — lihat komentar migration `create_inventory_balances_table`.
 */
#[Fillable([
    'pop_id',
    'item_id',
    'lot_no',
    'qty',
    'minimum_stock',
    'maximum_stock',
])]
class InventoryBalance extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'maximum_stock' => 'decimal:2',
        ];
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Dipakai Dashboard Gudang (rancangan-ui.md §2.1) — cuma berarti kalau
     * `minimum_stock` diisi; item yang belum py minimum gak pernah dianggap
     * "rendah" (tidak ada patokan buat dibandingkan).
     */
    public function isLowStock(): bool
    {
        return $this->minimum_stock !== null && $this->qty < $this->minimum_stock;
    }

    public function scopeLowStock($query)
    {
        return $query->whereNotNull('minimum_stock')->whereColumn('qty', '<', 'minimum_stock');
    }
}
