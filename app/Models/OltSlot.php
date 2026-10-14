<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chassis_node_id', 'number', 'card_node_id'])]
class OltSlot extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Slot OLT';

    protected array $auditEvents = ['created', 'updated'];

    public function chassis(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'chassis_node_id');
    }

    public function cardNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'card_node_id');
    }
}
