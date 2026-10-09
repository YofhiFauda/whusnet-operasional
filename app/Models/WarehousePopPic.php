<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penunjukan PIC Gudang per cabang — lihat Pop::gudangPics() /
 * User::picGudangPops() dan docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md §5.3.
 */
#[Fillable(['pop_id', 'user_id'])]
class WarehousePopPic extends Model
{
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
