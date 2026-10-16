<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'version', 'title', 'category', 'summary', 'body_markdown',
    'required_permission', 'route_names', 'edited_by',
])]
class ManualArticleRevision extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['route_names' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(ManualArticle::class, 'manual_article_id');
    }
}
