<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris barang Surat Pengajuan Barang — lihat docblock migration
 * `create_pengajuan_barang_items_table`.
 */
#[Fillable([
    'pengajuan_barang_id',
    'nama_barang',
    'spesifikasi',
    'jumlah',
    'satuan',
    'keterangan_link',
    'urutan',
])]
class PengajuanBarangItem extends Model
{
    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
        ];
    }

    public function pengajuanBarang(): BelongsTo
    {
        return $this->belongsTo(PengajuanBarang::class);
    }
}
