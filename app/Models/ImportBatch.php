<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'file_name',
        'uploaded_by',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'imported_rows',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    public static function generateBatchNumber(): string
    {
        $prefix = 'IMP-'.date('Ymd');
        $latest = self::where('batch_number', 'like', $prefix.'-%')
            ->orderBy('batch_number', 'desc')
            ->first();

        if (! $latest) {
            return $prefix.'-0001';
        }

        $lastNumber = (int) substr($latest->batch_number, -4);
        $nextNumber = str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);

        return $prefix.'-'.$nextNumber;
    }
}
