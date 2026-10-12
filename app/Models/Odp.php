<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Odp extends Model
{
    use HasPopScope, RecordsAuditLogs;

    protected string $auditModule = 'Master ODP';

    protected array $auditEvents = ['created', 'updated'];

    protected $fillable = ['pop_id', 'code', 'name'];

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function ports(): HasMany
    {
        return $this->hasMany(OdpPort::class)->orderBy('number');
    }
}
