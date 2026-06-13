<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'document_type',
    'file_path',
    'uploaded_by',
])]
class CustomerDocument extends Model
{
    public const TYPES = [
        'ktp' => 'Dokumen KTP',
        'rumah' => 'Foto Rumah',
        'kontrak' => 'Dokumen Kontrak',
        'survey' => 'Foto Survey',
        'pemasangan' => 'Foto Pemasangan',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? ucwords(str_replace('_', ' ', $this->document_type));
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
