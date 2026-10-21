<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaskInfrastructureAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id', 'fop_task_id', 'action_key', 'action_type', 'network_node_id',
        'mutation_hash', 'mutation', 'result_note', 'performed_by', 'performed_at',
    ];

    protected function casts(): array
    {
        return ['mutation' => 'array', 'performed_at' => 'datetime'];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'network_node_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(InventoryTransaction::class, 'task_infrastructure_action_transactions');
    }
}
