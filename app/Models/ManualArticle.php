<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug', 'title', 'category', 'summary', 'body_markdown',
    'required_permission', 'route_names', 'published_revision_id',
    'published_at', 'created_by', 'updated_by',
])]
class ManualArticle extends Model
{
    use HasFactory, RecordsAuditLogs;

    protected string $auditModule = 'Pusat Panduan';

    protected array $auditEvents = ['created', 'updated'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'route_names' => 'array'];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ManualArticleRevision::class)->orderByDesc('version');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(ManualArticleRevision::class, 'published_revision_id');
    }

    public function recordRevision(?int $editorId): ManualArticleRevision
    {
        return $this->revisions()->create([
            'version' => (int) $this->revisions()->max('version') + 1,
            'title' => $this->title,
            'category' => $this->category,
            'summary' => $this->summary,
            'body_markdown' => $this->body_markdown,
            'required_permission' => $this->required_permission,
            'route_names' => $this->route_names ?? [],
            'edited_by' => $editorId,
        ]);
    }
}
