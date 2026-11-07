<?php

namespace App\Models;

use App\Enums\PengajuanBarangStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Surat Pengajuan Barang — lihat docblock lengkap di migration
 * `create_pengajuan_barang_table`.
 */
#[Fillable([
    'nomor_request',
    'pengaju_id',
    'nama_kegiatan',
    'pop_id',
    'status',
    'decided_by',
    'decided_at',
    'rejection_reason',
])]
class PengajuanBarang extends Model
{
    protected $table = 'pengajuan_barang';

    protected function casts(): array
    {
        return [
            'status' => PengajuanBarangStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengaju_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PengajuanBarangItem::class)->orderBy('urutan');
    }

    public function scopeMenungguFop($query)
    {
        return $query->where('status', PengajuanBarangStatus::MENUNGGU_FOP->value);
    }
}
