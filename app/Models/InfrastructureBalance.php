<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stok QUANTITY/ROLL yang SAAT INI dideploy ke titik infrastruktur jaringan
 * (ODC/ODP/splitter) — proyeksi yang DITURUNKAN dari `inventory_transactions`
 * (tipe DEPLOY/RETRIEVE_INFRA), bukan sumber kebenaran sendiri. Jangan
 * pernah di-`update()` qty-nya langsung dari Controller — itu tugas Service
 * yang JUGA menulis baris ledger di transaksi yang sama.
 *
 * TERPISAH dari `inventory_balances` (yang khusus gudang `pusat`/`cabang` —
 * lihat `Pop::WAREHOUSE_TYPES`) karena `network_node_id` di sini tidak punya
 * batasan tipe POP sama sekali; mencampur keduanya berarti melonggarkan
 * guard gudang yang sudah ada, di luar cakupan ini (§3.6 design spec).
 *
 * TIDAK ADA konsep low-stock-alert di sini (beda dari `InventoryBalance`) —
 * tabel ini cuma "berapa barang ini yang sekarang dideploy di node ini".
 */
#[Fillable([
    'item_id',
    'lot_no',
    'network_node_id',
    'qty',
])]
class InfrastructureBalance extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function networkNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class);
    }
}
