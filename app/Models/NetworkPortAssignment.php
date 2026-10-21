<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'network_node_port_id', 'customer_id', 'target_node_id', 'target_port_id', 'manual_label',
    'active_key', 'assigned_by', 'released_by', 'assigned_at', 'released_at', 'release_note',
])]
class NetworkPortAssignment extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Penempatan Port Jaringan';

    protected array $auditEvents = ['created', 'updated'];

    protected function casts(): array
    {
        return [
            'active_key' => 'integer',
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(NetworkNodePort::class, 'network_node_port_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function targetPort(): BelongsTo
    {
        return $this->belongsTo(NetworkNodePort::class, 'target_port_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'target_node_id');
    }
}
