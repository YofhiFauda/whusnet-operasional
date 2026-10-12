<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdpPort extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Port ODP';

    protected array $auditEvents = ['created', 'updated'];

    protected $fillable = ['odp_id', 'number', 'is_used'];

    protected function casts(): array
    {
        return ['is_used' => 'boolean'];
    }

    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }
}
