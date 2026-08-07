<?php

namespace App\Models;

use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribution extends Model
{
    use HasFactory, HasPopScope;

    protected $fillable = [
        'pop_id',
        'code',
        'name',
        'description',
    ];

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }
}
