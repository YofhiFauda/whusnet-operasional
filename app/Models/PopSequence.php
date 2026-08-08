<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pop_id',
    'sequence_type',
    'current_number',
])]
class PopSequence extends Model
{
    public const TYPE_REGISTRATION = 'registration';

    public const TYPE_CID = 'cid';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pop_id' => 'integer',
            'current_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }
}
